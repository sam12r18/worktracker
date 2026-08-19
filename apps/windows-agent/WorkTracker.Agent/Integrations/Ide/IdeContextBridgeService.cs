using System.IO;
using System.Text.Json;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Integrations.Ide;

public sealed class IdeContextBridgeService
{
    private static readonly TimeSpan FreshnessWindow = TimeSpan.FromSeconds(12);
    private static readonly TimeSpan HardStaleWindow = TimeSpan.FromMinutes(2);
    private readonly JsonSerializerOptions _json = new(JsonSerializerDefaults.Web)
    {
        PropertyNameCaseInsensitive = true,
    };
    private readonly string _phpStormDirectory;
    private readonly SemaphoreSlim _readGate = new(1, 1);
    private IdeContextSnapshot? _lastContext;
    private DateTimeOffset _lastReadAtUtc;
    private string? _lastLoggedSignature;

    public IdeContextBridgeService(string? root = null)
    {
        root ??= Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "WorkTracker",
            "ide");
        _phpStormDirectory = Path.Combine(root, "phpstorm");
    }

    public string PhpStormDirectory => _phpStormDirectory;

    public async Task<ForegroundSnapshot> EnrichAsync(ForegroundSnapshot snapshot, CancellationToken ct = default)
    {
        if (!IsPhpStorm(snapshot.ProcessName) || snapshot.ProcessId <= 0)
            return snapshot with { IdeContext = null };

        var context = await TryReadForForegroundAsync(snapshot, ct);
        if (context is null) return snapshot with { IdeContext = null };

        var enriched = snapshot with { IdeContext = context };
        await LogContextChangeAsync(enriched, context);
        return enriched;
    }

    public IdeContextBridgeStatus GetStatus(DateTimeOffset? now = null)
    {
        now ??= DateTimeOffset.UtcNow;
        var context = _lastContext;
        if (context is null)
            return IdeContextBridgeStatus.Disconnected("Plugin context دریافت نشده است.");

        var age = Math.Max(0, (int)Math.Floor((now.Value - context.ObservedAtUtc).TotalSeconds));
        var stale = age > FreshnessWindow.TotalSeconds;
        var message = stale
            ? $"Context قدیمی است ({age}s). PhpStorm یا Plugin را بررسی کنید."
            : "Context زنده از PhpStorm دریافت می‌شود.";

        return new IdeContextBridgeStatus(
            Connected: !stale,
            Stale: stale,
            Provider: context.Source ?? context.IdeProduct ?? "PhpStorm Plugin",
            Project: context.ProjectDisplay,
            File: context.CurrentFile ?? "-",
            Mode: context.Mode,
            Branch: context.GitBranch ?? "-",
            RunConfiguration: context.RunConfiguration ?? "-",
            ObservedAtUtc: context.ObservedAtUtc,
            AgeSeconds: age,
            Message: message);
    }

    private async Task<IdeContextSnapshot?> TryReadForForegroundAsync(ForegroundSnapshot foreground, CancellationToken ct)
    {
        await _readGate.WaitAsync(ct);
        try
        {
            _lastReadAtUtc = DateTimeOffset.UtcNow;
            if (!Directory.Exists(_phpStormDirectory)) return null;

            var prefix = $"context-{foreground.ProcessId}-";
            var files = Directory.EnumerateFiles(_phpStormDirectory, $"{prefix}*.json", SearchOption.TopDirectoryOnly).ToList();
            if (files.Count == 0) return null;

            var now = DateTimeOffset.UtcNow;
            var candidates = new List<Candidate>();
            foreach (var file in files)
            {
                ct.ThrowIfCancellationRequested();
                IdeContextSnapshot? context;
                try
                {
                    await using var stream = new FileStream(file, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete, 4096, useAsync: true);
                    context = await JsonSerializer.DeserializeAsync<IdeContextSnapshot>(stream, _json, ct);
                }
                catch (JsonException ex)
                {
                    await AgentLog.WarnAsync("ide.context", "invalid PhpStorm context JSON ignored", new { file, ex.Message });
                    continue;
                }
                catch (IOException)
                {
                    continue;
                }

                if (context is null || !context.IsSupported || context.ProcessId != foreground.ProcessId) continue;
                var age = now - context.ObservedAtUtc;
                if (age < TimeSpan.Zero - TimeSpan.FromSeconds(5) || age > HardStaleWindow) continue;

                candidates.Add(new Candidate(context, ScoreCandidate(context, foreground.WindowTitle), age));
            }

            if (candidates.Count == 0) return null;

            var fresh = candidates.Where(x => x.Age <= FreshnessWindow).ToList();
            if (fresh.Count == 0)
            {
                _lastContext = candidates.OrderBy(x => x.Age).First().Context;
                return null;
            }

            Candidate? selected;
            if (fresh.Count == 1)
            {
                selected = fresh[0];
            }
            else
            {
                var ranked = fresh.OrderByDescending(x => x.Score).ThenBy(x => x.Age).ToList();
                if (ranked[0].Score <= 0 || (ranked.Count > 1 && ranked[0].Score == ranked[1].Score))
                    return null;
                selected = ranked[0];
            }

            _lastContext = selected.Context;
            CleanupVeryStaleFiles(files, now);
            return selected.Context;
        }
        finally
        {
            _readGate.Release();
        }
    }

    private static int ScoreCandidate(IdeContextSnapshot context, string? windowTitle)
    {
        if (string.IsNullOrWhiteSpace(windowTitle)) return 0;
        var score = 0;
        if (!string.IsNullOrWhiteSpace(context.ProjectName) && windowTitle.Contains(context.ProjectName, StringComparison.OrdinalIgnoreCase)) score += 100;
        if (!string.IsNullOrWhiteSpace(context.ProjectPath))
        {
            var leaf = Path.GetFileName(context.ProjectPath.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar));
            if (!string.IsNullOrWhiteSpace(leaf) && windowTitle.Contains(leaf, StringComparison.OrdinalIgnoreCase)) score += 80;
        }
        if (!string.IsNullOrWhiteSpace(context.CurrentFile) && windowTitle.Contains(context.CurrentFile, StringComparison.OrdinalIgnoreCase)) score += 35;
        return score;
    }

    private static bool IsPhpStorm(string? processName)
        => processName is not null && (processName.Equals("phpstorm64", StringComparison.OrdinalIgnoreCase) || processName.Equals("phpstorm", StringComparison.OrdinalIgnoreCase));

    private async Task LogContextChangeAsync(ForegroundSnapshot snapshot, IdeContextSnapshot context)
    {
        var signature = string.Join("|", context.ProcessId, context.ProjectPath, context.CurrentFilePath, context.Mode, context.RunConfiguration, context.GitBranch);
        if (string.Equals(_lastLoggedSignature, signature, StringComparison.Ordinal)) return;
        _lastLoggedSignature = signature;
        await AgentLog.InfoAsync("ide.context", "PhpStorm context applied to foreground observation", new
        {
            process_id = snapshot.ProcessId,
            project = context.ProjectName,
            project_path = context.ProjectPath,
            current_file = context.CurrentFile,
            git_branch = context.GitBranch,
            execution_mode = context.Mode,
            run_configuration = context.RunConfiguration,
            plugin_version = context.PluginVersion,
            ide_build = context.IdeBuild,
            age_ms = Math.Max(0, (int)(DateTimeOffset.UtcNow - context.ObservedAtUtc).TotalMilliseconds),
        });
    }

    private static void CleanupVeryStaleFiles(IEnumerable<string> files, DateTimeOffset now)
    {
        foreach (var file in files)
        {
            try
            {
                var fileTime = new DateTimeOffset(File.GetLastWriteTimeUtc(file), TimeSpan.Zero);
                var age = now - fileTime;
                if (age > TimeSpan.FromDays(1)) File.Delete(file);
            }
            catch
            {
                // Best-effort cleanup only; context reading must never fail because cleanup failed.
            }
        }
    }

    private sealed record Candidate(IdeContextSnapshot Context, int Score, TimeSpan Age);
}
