using System.Diagnostics;
using System.IO;
using System.Net;
using System.Net.Http;
using WorkTracker.Agent.Diagnostics;

namespace WorkTracker.Agent.Services;

internal sealed record LocalLaravelLaunchPlan(
    Uri HealthUri,
    string Host,
    int Port,
    string WorkingDirectory);

public sealed class LocalLaravelServerService : IAsyncDisposable, IDisposable
{
    internal const string DefaultLocalApiBaseUrl = "http://127.0.0.1:8082";

    private static readonly TimeSpan HealthTimeout = TimeSpan.FromSeconds(2);
    private static readonly TimeSpan StartupRetryDelay = TimeSpan.FromMilliseconds(500);
    private const int StartupHealthAttempts = 12;
    private const int CapturedProcessLineLimit = 80;

    private readonly HttpClient _healthClient;
    private readonly object _outputGate = new();
    private readonly Queue<string> _stdoutLines = new();
    private readonly Queue<string> _stderrLines = new();
    private Process? _ownedProcess;
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
        var usingDefaultLocalOrigin = string.IsNullOrWhiteSpace(apiBaseUrl);
        var effectiveApiBaseUrl = usingDefaultLocalOrigin ? DefaultLocalApiBaseUrl : apiBaseUrl;
        var target = BuildTarget(effectiveApiBaseUrl);
        if (target is null)
        {
            await AgentLog.InfoAsync("laravel.local", "local Laravel auto-start not applicable for configured API origin", new
            {
                api_base_url = apiBaseUrl,
            });
            return;
        }

        try
        {
            if (usingDefaultLocalOrigin)
            {
                await AgentLog.InfoAsync("laravel.local", "local Laravel auto-start using default development API origin", new
                {
                    configured_api_base_url = apiBaseUrl,
                    effective_api_base_url = effectiveApiBaseUrl,
                });
            }

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

            var plan = BuildLaunchPlan(effectiveApiBaseUrl, apiDirectory);
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
                effective_api_base_url = effectiveApiBaseUrl,
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
        var effectiveApiBaseUrl = string.IsNullOrWhiteSpace(apiBaseUrl)
            ? DefaultLocalApiBaseUrl
            : apiBaseUrl.Trim();

        if (!Uri.TryCreate(effectiveApiBaseUrl, UriKind.Absolute, out var uri) ||
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

        ClearCapturedOutput();
        var process = new Process { StartInfo = startInfo };
        process.OutputDataReceived += (_, e) => CaptureLine(_stdoutLines, e.Data);
        process.ErrorDataReceived += (_, e) => CaptureLine(_stderrLines, e.Data);

        if (!process.Start())
        {
            process.Dispose();
            await AgentLog.WarnAsync("laravel.local", "php artisan serve did not start a process");
            return;
        }

        _ownedProcess = process;
        process.BeginOutputReadLine();
        process.BeginErrorReadLine();

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
                    stdout = CapturedTail(_stdoutLines),
                    stderr = CapturedTail(_stderrLines),
                });
                await StopOwnedProcessAsync();
                return;
            }

            if (process.HasExited)
            {
                await AgentLog.WarnAsync("laravel.local", "php artisan serve exited before health check succeeded", new
                {
                    pid = process.Id,
                    exit_code = process.ExitCode,
                    stdout = CapturedTail(_stdoutLines),
                    stderr = CapturedTail(_stderrLines),
                    attempt,
                });
                ReleaseExitedProcess();
                return;
            }

            await Task.Delay(StartupRetryDelay, ct);
        }

        await AgentLog.WarnAsync("laravel.local", "local Laravel backend did not become healthy within startup timeout", new
        {
            pid = process.Id,
            health_url = plan.HealthUri.ToString(),
            stdout = CapturedTail(_stdoutLines),
            stderr = CapturedTail(_stderrLines),
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

    private void ClearCapturedOutput()
    {
        lock (_outputGate)
        {
            _stdoutLines.Clear();
            _stderrLines.Clear();
        }
    }

    private void CaptureLine(Queue<string> queue, string? line)
    {
        if (string.IsNullOrWhiteSpace(line)) return;
        lock (_outputGate)
        {
            queue.Enqueue(line);
            while (queue.Count > CapturedProcessLineLimit)
                queue.Dequeue();
        }
    }

    private string? CapturedTail(Queue<string> queue)
    {
        lock (_outputGate)
        {
            if (queue.Count == 0) return null;
            var text = string.Join(Environment.NewLine, queue);
            return text.Length <= 1600 ? text : text[^1600..];
        }
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
        }
    }

    private void ReleaseExitedProcess()
    {
        var process = Interlocked.Exchange(ref _ownedProcess, null);
        process?.Dispose();
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
