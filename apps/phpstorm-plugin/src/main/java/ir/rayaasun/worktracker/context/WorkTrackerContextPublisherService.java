package ir.rayaasun.worktracker.context;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.intellij.execution.executors.DefaultDebugExecutor;
import com.intellij.execution.configurations.RunConfiguration;
import com.intellij.execution.process.ProcessHandler;
import com.intellij.execution.runners.ExecutionEnvironment;
import com.intellij.ide.plugins.PluginManagerCore;
import com.intellij.openapi.Disposable;
import com.intellij.openapi.application.ApplicationInfo;
import com.intellij.openapi.application.ReadAction;
import com.intellij.openapi.extensions.PluginId;
import com.intellij.openapi.fileEditor.FileEditorManager;
import com.intellij.openapi.project.Project;
import com.intellij.openapi.util.io.FileUtil;
import com.intellij.openapi.vfs.VirtualFile;
import com.intellij.util.concurrency.AppExecutorUtil;
import git4idea.repo.GitRepository;
import git4idea.repo.GitRepositoryManager;
import org.jetbrains.annotations.NotNull;
import org.jetbrains.annotations.Nullable;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.AtomicMoveNotSupportedException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.time.Instant;
import java.util.Comparator;
import java.util.LinkedHashMap;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;

public final class WorkTrackerContextPublisherService implements Disposable {
    private static final int PROTOCOL_VERSION = 1;
    private static final String PLUGIN_ID = "ir.rayaasun.worktracker.context";
    private static final long HEARTBEAT_SECONDS = 2L;

    private final Project project;
    private final Gson gson = new GsonBuilder().disableHtmlEscaping().create();
    private final ConcurrentHashMap<ProcessHandler, ExecutionState> executions = new ConcurrentHashMap<>();
    private final AtomicBoolean started = new AtomicBoolean(false);
    private final AtomicBoolean publishQueued = new AtomicBoolean(false);
    private final Path outputFile;
    private volatile ScheduledFuture<?> heartbeat;

    public WorkTrackerContextPublisherService(@NotNull Project project) {
        this.project = project;
        this.outputFile = resolveOutputFile(project);
    }

    public void start() {
        if (!started.compareAndSet(false, true)) {
            return;
        }

        publishSoon();
        heartbeat = AppExecutorUtil.getAppScheduledExecutorService().scheduleWithFixedDelay(
            this::publishSafely,
            HEARTBEAT_SECONDS,
            HEARTBEAT_SECONDS,
            TimeUnit.SECONDS);
    }

    public void publishSoon() {
        start();
        if (!publishQueued.compareAndSet(false, true)) {
            return;
        }

        AppExecutorUtil.getAppExecutorService().execute(() -> {
            try {
                publishSafely();
            } finally {
                publishQueued.set(false);
            }
        });
    }

    public void executionStarted(@NotNull String executorId,
                                 @NotNull ExecutionEnvironment environment,
                                 @NotNull ProcessHandler handler) {
        var profile = environment.getRunProfile();
        var configurationName = profile.getName();
        String configurationType = profile.getClass().getSimpleName();
        if (profile instanceof RunConfiguration configuration) {
            configurationType = configuration.getType().getId();
        }

        var mode = determineExecutionMode(executorId, configurationName, configurationType);
        executions.put(handler, new ExecutionState(mode, configurationName, configurationType));
        publishSoon();
    }

    public void executionTerminated(@NotNull ProcessHandler handler) {
        executions.remove(handler);
        publishSoon();
    }

    private void publishSafely() {
        if (project.isDisposed()) {
            return;
        }

        try {
            var snapshot = ReadAction.compute(this::collectContext);
            writeAtomically(snapshot);
        } catch (Throwable ignored) {
            // The bridge is diagnostic/enrichment infrastructure. It must never break PhpStorm.
            // A later heartbeat will retry automatically.
        }
    }

    private Map<String, Object> collectContext() {
        var selectedFiles = FileEditorManager.getInstance(project).getSelectedFiles();
        VirtualFile currentFile = selectedFiles.length > 0 ? selectedFiles[0] : null;
        var execution = currentExecution();
        var gitBranch = currentGitBranch(currentFile);

        var result = new LinkedHashMap<String, Object>();
        result.put("protocol_version", PROTOCOL_VERSION);
        result.put("plugin_version", pluginVersion());
        result.put("ide_product", ApplicationInfo.getInstance().getFullApplicationName());
        result.put("ide_build", ApplicationInfo.getInstance().getBuild().asString());
        result.put("process_id", Math.toIntExact(ProcessHandle.current().pid()));
        result.put("project_name", project.getName());
        result.put("project_path", project.getBasePath());
        result.put("current_file", currentFile == null ? null : currentFile.getName());
        result.put("current_file_path", currentFile == null ? null : FileUtil.toSystemDependentName(currentFile.getPath()));
        result.put("git_branch", gitBranch);
        result.put("execution_mode", execution.mode());
        result.put("run_configuration", execution.configurationName());
        result.put("run_configuration_type", execution.configurationType());
        result.put("observed_at_utc", Instant.now().toString());
        result.put("source", "phpstorm-plugin");
        return result;
    }

    private ExecutionState currentExecution() {
        if (executions.isEmpty()) {
            return ExecutionState.IDLE;
        }

        return executions.values().stream()
            .max(Comparator.comparingInt(ExecutionState::priority))
            .orElse(ExecutionState.IDLE);
    }

    @Nullable
    private String currentGitBranch(@Nullable VirtualFile currentFile) {
        var repositories = GitRepositoryManager.getInstance(project).getRepositories();
        if (repositories.isEmpty()) {
            return null;
        }

        GitRepository repository = null;
        if (currentFile != null) {
            var path = currentFile.getPath();
            repository = repositories.stream()
                .filter(candidate -> path.equals(candidate.getRoot().getPath()) || path.startsWith(candidate.getRoot().getPath() + "/"))
                .max(Comparator.comparingInt(candidate -> candidate.getRoot().getPath().length()))
                .orElse(null);
        }
        if (repository == null) {
            repository = repositories.get(0);
        }
        return repository.getCurrentBranchName();
    }

    private static String determineExecutionMode(String executorId, String configurationName, String configurationType) {
        if (DefaultDebugExecutor.EXECUTOR_ID.equals(executorId)) {
            return "debug";
        }

        var text = (configurationName + " " + configurationType).toLowerCase(Locale.ROOT);
        if (text.contains("phpunit") || text.contains("pest") || text.contains("test")) {
            return "test";
        }
        return "run";
    }

    private void writeAtomically(Map<String, Object> payload) throws IOException {
        Files.createDirectories(outputFile.getParent());
        var temp = outputFile.resolveSibling(outputFile.getFileName() + ".tmp");
        Files.writeString(temp, gson.toJson(payload), StandardCharsets.UTF_8);
        try {
            Files.move(temp, outputFile, StandardCopyOption.REPLACE_EXISTING, StandardCopyOption.ATOMIC_MOVE);
        } catch (AtomicMoveNotSupportedException ex) {
            Files.move(temp, outputFile, StandardCopyOption.REPLACE_EXISTING);
        }
    }

    private static Path resolveOutputFile(Project project) {
        var localAppData = System.getenv("LOCALAPPDATA");
        Path root;
        if (localAppData != null && !localAppData.isBlank()) {
            root = Path.of(localAppData);
        } else {
            root = Path.of(System.getProperty("user.home"), ".worktracker-local");
        }
        var processId = ProcessHandle.current().pid();
        var identity = project.getBasePath() != null ? project.getBasePath() : project.getName();
        return root.resolve("WorkTracker")
            .resolve("ide")
            .resolve("phpstorm")
            .resolve("context-" + processId + "-" + shortHash(identity) + ".json");
    }

    private static String shortHash(String input) {
        try {
            var digest = MessageDigest.getInstance("SHA-256").digest(input.getBytes(StandardCharsets.UTF_8));
            var builder = new StringBuilder();
            for (int i = 0; i < 6; i++) {
                builder.append(String.format("%02x", digest[i]));
            }
            return builder.toString();
        } catch (NoSuchAlgorithmException e) {
            return Integer.toHexString(input.hashCode());
        }
    }

    private static String pluginVersion() {
        var descriptor = PluginManagerCore.getPlugin(PluginId.getId(PLUGIN_ID));
        return descriptor == null ? "unknown" : descriptor.getVersion();
    }

    @Override
    public void dispose() {
        if (heartbeat != null) {
            heartbeat.cancel(false);
        }
        try {
            Files.deleteIfExists(outputFile);
        } catch (IOException ignored) {
        }
    }

    private record ExecutionState(String mode, String configurationName, String configurationType) {
        private static final ExecutionState IDLE = new("idle", null, null);

        int priority() {
            return switch (mode) {
                case "debug" -> 30;
                case "test" -> 20;
                case "run" -> 10;
                default -> 0;
            };
        }
    }
}
