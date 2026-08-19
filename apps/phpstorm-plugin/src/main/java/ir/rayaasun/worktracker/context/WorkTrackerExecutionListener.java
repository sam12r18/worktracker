package ir.rayaasun.worktracker.context;

import com.intellij.execution.ExecutionListener;
import com.intellij.execution.process.ProcessHandler;
import com.intellij.execution.runners.ExecutionEnvironment;
import com.intellij.openapi.project.Project;
import org.jetbrains.annotations.NotNull;

public final class WorkTrackerExecutionListener implements ExecutionListener {
    private final Project project;

    public WorkTrackerExecutionListener(@NotNull Project project) {
        this.project = project;
    }

    @Override
    public void processStarted(@NotNull String executorId,
                               @NotNull ExecutionEnvironment env,
                               @NotNull ProcessHandler handler) {
        if (env.getProject() == project) {
            project.getService(WorkTrackerContextPublisherService.class)
                .executionStarted(executorId, env, handler);
        }
    }

    @Override
    public void processTerminated(@NotNull String executorId,
                                  @NotNull ExecutionEnvironment env,
                                  @NotNull ProcessHandler handler,
                                  int exitCode) {
        if (env.getProject() == project) {
            project.getService(WorkTrackerContextPublisherService.class)
                .executionTerminated(handler);
        }
    }
}
