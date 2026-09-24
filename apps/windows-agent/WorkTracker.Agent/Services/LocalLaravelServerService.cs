using System.Diagnostics;
using System.IO;
using System.Net;
using WorkTracker.Agent.Diagnostics;

namespace WorkTracker.Agent.Services;

internal sealed record LocalLaravelLaunchPlan(
    Uri HealthUri,
    string Host,
    int Port,
    string WorkingDirectory);

public sealed class LocalLaravelServerService : IAsyncDisposable, IDisposable
{
    private static readonly TimeSpan HealthTimeout = TimeSpan.FromSeconds(2);
    private static readonly TimeSpan StartupRetryDelay = TimeSpan.FromMilliseconds(500);
    private const int StartupHealthAttempts = 12;

    private readonly HttpClient _healthClient;
    private Process? _ownedProcess;
    private Task<string>? _stdoutTask;
    private Task<string>? _stderrTask;
    private int _disposed;

    public LocalLaravelServerService()
    {
        _healthClient = new HttpClient
        {
            Timeout = Timeout.InfiniteTimeSpan,
        };
    }

    public async Task EnsureReadyAsync(string? apiBaseUrl, CancellationToken ct = default)
    {
        var target = BuildTarget(apiBaseUrl);
        if (target is null)
            return;

        try
        {
            var initialProbe = await ProbeHealthAsync(target.HealthUri, ct);
            if (initialProbe.Kind == HealthProbeKind.Healthy)
            {
                await AgentLog.InfoAsync("laravel.local", "local Laravel backend already healthy", new
                {
                    health_url = target.HealthUri.ToString(),
                    ownership = "external",
                });
                return;
            }

            if (initialProbe.Kind == HealthProbeKind.ReachableUnhealthy)
            {
                await AgentLog.WarnAsync("laravel.local", "local API endpoint is reachable but health check is not successful; auto-start skipped to avoid a duplicate server", new
                {
                    health_url = target.HealthUri.ToString(),
                    status_code = initialProbe.StatusCode,
                });
                return;
            }

            var apiDirectory = ResolveApiDirectory();
            if (apiDirectory is null)
            {
                await AgentLog.WarnAsync("laravel.local", "local Laravel API is unavailable and apps/api could not be located", new
                {
                    health_url = target.HealthUri.ToString(),
                    environment_override = Environment.GetEnvironmentVariable("WORKTRACKER_LARAVEL_PATH"),
                    base_directory = AppContext.BaseDirectory,
                    current_directory = Environment.CurrentDirectory,
                });
                return;
            }

            var plan = BuildLaunchPlan(apiBaseUrl, apiDirectory);
            if (plan is null)
                return;

            await StartOwnedServerAsync(plan, ct);
        }
        catch (OperationCanceledException) when (ct.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception ex)
        {
            await AgentLog.ErrorAsync("laravel.local", "local Laravel startup check failed; Agent will continue offline", ex, new
            {
                api_base_url = apiBaseUrl,
            });
        }
    }

    internal static LocalLaravelLaunchPlan? BuildLaunchPlan(string? apiBaseUrl, string apiDirectory)
    {
        var target = BuildTarget(apiBaseUrl);
        if (target is null || string.IsNullOrWhiteSpace(apiDirectory))
            return null;

        return new LocalLaravelLaunchPlan(
            target.HealthUri,
            target.Host,
            target.Port,
            apiDirectory);
    }

    private static LocalLaravelTarget? BuildTarget(string? apiBaseUrl)
    {
        if (string.IsNullOrWhiteSpace(apiBaseUrl) ||
            !Uri.TryCreate(apiBaseUrl.Trim(), UriKind.Absolute, out var uri) ||
            !string.Equals(uri.Scheme, Uri.UriSchemeHttp, StringComparison.OrdinalIgnoreCase) ||
            !IsLoopbackHost(uri.Host))
            return null;

        var port = uri.IsDefaultPort ? 80 : uri.Port;
        if (port is <= 0 or > 65535)
            return null;

        var health = new UriBuilder(Uri.UriSchemeHttp, uri.Host, port, "/worktracker/health")
        {
            Query = string.Empty,
            Fragment = string.Empty,
        }.Uri;

        return new LocalLaravelTarget(health, uri.Host, port);
    }

    private static bool IsLoopbackHost(string host)
    {
        if (string.Equals(host, "localhost", StringComparison.OrdinalIgnoreCase))
            return true;

        return IPAddress.TryParse(host, out var address) && IPAddress.IsLoopback(address);
    }

    private async Task StartOwnedServerAsync(LocalLaravelLaunchPlan plan, CancellationToken ct)
    {
        var artisan = Path.Combine(plan.WorkingDirectory, "artisan");
        if (!File.Exists(artisan))
        {
            await AgentLog.WarnAsync("laravel.local", "Laravel artisan file not found; auto-start skipped", new
            {
                artisan,
                working_directory = plan.WorkingDirectory,
            });
            return;
        }

        var phpExecutable = Environment.GetEnvironmentVariable("WORKTRACKER_PHP_EXECUTABLE");
        if (string.IsNullOrWhiteSpace(phpExecutable))
            phpExecutable = "php";

        var startInfo = new ProcessStartInfo
        {
            FileName = phpExecutable,
            WorkingDirectory = plan.WorkingDirectory,
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        };
        startInfo.ArgumentList.Add("artisan");
        startInfo.ArgumentList.Add("serve");
        startInfo.ArgumentList.Add($"--host={plan.Host}");
        startInfo.ArgumentList.Add($"--port={plan.Port}");

        await AgentLog.InfoAsync("laravel.local", "starting local Laravel backend", new
        {
            executable = phpExecutable,
            working_directory = plan.WorkingDirectory,
            host = plan.Host,
            port = plan.Port,
            health_url = plan.HealthUri.ToString(),
        });

        var process = new Process { StartInfo = startInfo };
        if (!process.Start())
        {
            process.Dispose();
            await AgentLog.WarnAsync("laravel.local", "php artisan serve did not start a process");
            return;
        }

        _ownedProcess = process;
        _stdoutTask = process.StandardOutput.ReadToEndAsync(ct);
        _stderrTask = process.StandardError.ReadToEndAsync(ct);

        for (var attempt = 1; attempt <= StartupHealthAttempts; attempt++)
        {
            ct.ThrowIfCancellationRequested();

            var probe = await ProbeHealthAsync(plan.HealthUri, ct);
            if (probe.Kind == HealthProbeKind.Healthy)
            {
                var owned = !process.HasExited;
                await AgentLog.InfoAsync("laravel.local", owned
                    ? "local Laravel backend started and is healthy"
                    : "local Laravel backend became healthy after launch attempt; spawned process already exited", new
                {
                    pid = process.Id,
                    owned,
                    health_url = plan.HealthUri.ToString(),
                    attempt,
                });

                if (!owned)
                    ReleaseExitedProcess();
                return;
            }

            if (probe.Kind == HealthProbeKind.ReachableUnhealthy)
            {
                await AgentLog.WarnAsync("laravel.local", "Laravel process is reachable but health endpoint is not successful", new
                {
                    pid = process.Id,
                    health_url = plan.HealthUri.ToString(),
                    status_code = probe.StatusCode,
                    attempt,
                });
                await StopOwnedProcessAsync();
                return;
            }

            if (process.HasExited)
            {
                var output = await CollectProcessOutputAsync();
                await AgentLog.WarnAsync("laravel.local", "php artisan serve exited before health check succeeded", new
                {
                    pid = process.Id,
                    exit_code = process.ExitCode,
                    stdout = Tail(output.Stdout),
                    stderr = Tail(output.Stderr),
                    attempt,
                });
                ReleaseExitedProcess();
                return;
            }

            await Task.Delay(StartupRetryDelay, ct);
        }

        var finalOutput = await CollectProcessOutputIfCompletedAsync();
        await AgentLog.WarnAsync("laravel.local", "local Laravel backend did not become healthy within startup timeout", new
        {
            pid = process.Id,
            health_url = plan.HealthUri.ToString(),
            stdout = Tail(finalOutput.Stdout),
            stderr = Tail(finalOutput.Stderr),
        });
        await StopOwnedProcessAsync();
    }

    private async Task<HealthProbeResult> ProbeHealthAsync(Uri healthUri, CancellationToken ct)
    {
        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(ct);
        timeout.CancelAfter(HealthTimeout);

        try
        {
            using var request = new HttpRequestMessage(HttpMethod.Get, healthUri);
            using var response = await _healthClient.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, timeout.Token);
            return response.IsSuccessStatusCode
                ? new HealthProbeResult(HealthProbeKind.Healthy, (int)response.StatusCode)
                : new HealthProbeResult(HealthProbeKind.ReachableUnhealthy, (int)response.StatusCode);
        }
        catch (OperationCanceledException) when (!ct.IsCancellationRequested)
        {
            return new HealthProbeResult(HealthProbeKind.Unreachable, null);
        }
        catch (HttpRequestException)
        {
            return new HealthProbeResult(HealthProbeKind.Unreachable, null);
        }
    }

    private static string? ResolveApiDirectory()
    {
        var configured = Environment.GetEnvironmentVariable("WORKTRACKER_LARAVEL_PATH");
        if (!string.IsNullOrWhiteSpace(configured))
        {
            try
            {
                var full = Path.GetFullPath(configured.Trim().Trim('"'));
                if (File.Exists(Path.Combine(full, "artisan")))
                    return full;
            }
            catch
            {
                // Invalid override is reported by the caller through the normal not-found diagnostic.
            }
        }

        foreach (var root in new[] { AppContext.BaseDirectory, Environment.CurrentDirectory }.Distinct(StringComparer.OrdinalIgnoreCase))
        {
            var found = FindApiDirectoryFrom(root);
            if (found is not null)
                return found;
        }

        return null;
    }

    private static string? FindApiDirectoryFrom(string? startPath)
    {
        if (string.IsNullOrWhiteSpace(startPath))
            return null;

        DirectoryInfo? current;
        try
        {
            current = new DirectoryInfo(Path.GetFullPath(startPath));
        }
        catch
        {
            return null;
        }

        for (var depth = 0; current is not null && depth < 14; depth++, current = current.Parent)
        {
            if (File.Exists(Path.Combine(current.FullName, "artisan")))
                return current.FullName;

            var candidate = Path.Combine(current.FullName, "apps", "api");
            if (File.Exists(Path.Combine(candidate, "artisan")))
                return candidate;
        }

        return null;
    }

    private async Task StopOwnedProcessAsync()
    {
        var process = Interlocked.Exchange(ref _ownedProcess, null);
        if (process is null)
            return;

        try
        {
            if (!process.HasExited)
            {
                var pid = process.Id;
                process.Kill(entireProcessTree: true);
                using var timeout = new CancellationTokenSource(TimeSpan.FromSeconds(3));
                try
                {
                    await process.WaitForExitAsync(timeout.Token);
                }
                catch (OperationCanceledException)
                {
                    // Best effort shutdown; process ownership is still released below.
                }

                await AgentLog.InfoAsync("laravel.local", "stopped Laravel process owned by WorkTracker Agent", new { pid });
            }
        }
        catch (InvalidOperationException)
        {
            // Process already exited between checks.
        }
        catch (Exception ex)
        {
            await AgentLog.ErrorAsync("laravel.local", "failed to stop Laravel process owned by WorkTracker Agent", ex);
        }
        finally
        {
            process.Dispose();
            _stdoutTask = null;
            _stderrTask = null;
        }
    }

    private void ReleaseExitedProcess()
    {
        var process = Interlocked.Exchange(ref _ownedProcess, null);
        process?.Dispose();
        _stdoutTask = null;
        _stderrTask = null;
    }

    private async Task<(string? Stdout, string? Stderr)> CollectProcessOutputAsync()
    {
        var stdout = _stdoutTask is null ? null : await SafeAwaitAsync(_stdoutTask);
        var stderr = _stderrTask is null ? null : await SafeAwaitAsync(_stderrTask);
        return (stdout, stderr);
    }

    private async Task<(string? Stdout, string? Stderr)> CollectProcessOutputIfCompletedAsync()
    {
        var stdout = _stdoutTask is { IsCompleted: true } ? await SafeAwaitAsync(_stdoutTask) : null;
        var stderr = _stderrTask is { IsCompleted: true } ? await SafeAwaitAsync(_stderrTask) : null;
        return (stdout, stderr);
    }

    private static async Task<string?> SafeAwaitAsync(Task<string> task)
    {
        try { return await task; }
        catch { return null; }
    }

    private static string? Tail(string? value, int maxLength = 1600)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        var text = value.Trim();
        return text.Length <= maxLength ? text : text[^maxLength..];
    }

    public async ValueTask DisposeAsync()
    {
        if (Interlocked.Exchange(ref _disposed, 1) != 0)
            return;

        await StopOwnedProcessAsync();
        _healthClient.Dispose();
        GC.SuppressFinalize(this);
    }

    public void Dispose()
    {
        if (Interlocked.Exchange(ref _disposed, 1) != 0)
            return;

        try
        {
            var process = Interlocked.Exchange(ref _ownedProcess, null);
            if (process is not null)
            {
                try
                {
                    if (!process.HasExited)
                        process.Kill(entireProcessTree: true);
                }
                catch
                {
                    // Last-resort synchronous shutdown from WPF OnExit.
                }
                finally
                {
                    process.Dispose();
                }
            }
        }
        finally
        {
            _healthClient.Dispose();
            GC.SuppressFinalize(this);
        }
    }

    private sealed record LocalLaravelTarget(Uri HealthUri, string Host, int Port);
    private sealed record HealthProbeResult(HealthProbeKind Kind, int? StatusCode);

    private enum HealthProbeKind
    {
        Healthy,
        ReachableUnhealthy,
        Unreachable,
    }
}
