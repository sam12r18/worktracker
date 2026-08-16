using System.Text.Json;
using System.Text.Encodings.Web;

namespace WorkTracker.Agent.Diagnostics;

public static class AgentLog
{
    private static readonly SemaphoreSlim Gate = new(1, 1);
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web) { Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping };
    private static int _cleanupDone;

    public static string LogDirectory { get; } = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "WorkTracker",
        "logs");

    public static string CurrentLogPath => Path.Combine(LogDirectory, $"agent-{DateTime.Now:yyyy-MM-dd}.log");

    public static Task InfoAsync(string category, string message, object? data = null, string? correlationId = null) =>
        WriteAsync("INFO", category, message, data, correlationId);

    public static Task WarnAsync(string category, string message, object? data = null, string? correlationId = null) =>
        WriteAsync("WARN", category, message, data, correlationId);

    public static Task ErrorAsync(string category, string message, Exception? exception = null, object? data = null, string? correlationId = null) =>
        WriteAsync("ERROR", category, message, new
        {
            data,
            exception = exception is null ? null : new
            {
                type = exception.GetType().FullName,
                exception.Message,
                stack = exception.StackTrace,
                inner = exception.InnerException?.Message,
            }
        }, correlationId);

    public static async Task<string> ReadRecentTextAsync(int maxLines = 600, CancellationToken ct = default)
    {
        Directory.CreateDirectory(LogDirectory);
        var files = Directory.GetFiles(LogDirectory, "agent-*.log")
            .OrderByDescending(File.GetLastWriteTimeUtc)
            .Take(3)
            .Reverse()
            .ToArray();

        if (files.Length == 0)
            return "هنوز لاگی ثبت نشده است.";

        var lines = new List<string>();
        foreach (var file in files)
        {
            ct.ThrowIfCancellationRequested();
            try
            {
                var fileLines = await File.ReadAllLinesAsync(file, ct);
                lines.AddRange(fileLines);
            }
            catch (IOException)
            {
                // A log write can overlap this read for a few milliseconds. Skip and retry on refresh.
            }
        }

        return string.Join(Environment.NewLine, lines.TakeLast(Math.Max(1, maxLines)));
    }

    public static async Task ClearAsync(CancellationToken ct = default)
    {
        await Gate.WaitAsync(ct);
        try
        {
            Directory.CreateDirectory(LogDirectory);
            foreach (var file in Directory.GetFiles(LogDirectory, "agent-*.log"))
            {
                try { File.Delete(file); } catch (IOException) { }
            }
        }
        finally
        {
            Gate.Release();
        }
    }

    private static async Task WriteAsync(string level, string category, string message, object? data, string? correlationId)
    {
        try
        {
            await Gate.WaitAsync();
            try
            {
                Directory.CreateDirectory(LogDirectory);
                CleanupOldLogsOnce();
                var entry = new
                {
                    timestamp = DateTimeOffset.Now.ToString("O"),
                    level,
                    category,
                    correlation_id = correlationId,
                    message,
                    data,
                };
                var line = JsonSerializer.Serialize(entry, JsonOptions) + Environment.NewLine;
                await File.AppendAllTextAsync(CurrentLogPath, line);
            }
            finally
            {
                Gate.Release();
            }
        }
        catch
        {
            // Diagnostics must never interrupt tracking or sync.
        }
    }

    private static void CleanupOldLogsOnce()
    {
        if (Interlocked.Exchange(ref _cleanupDone, 1) != 0)
            return;

        var cutoff = DateTime.UtcNow.AddDays(-14);
        foreach (var file in Directory.GetFiles(LogDirectory, "agent-*.log"))
        {
            try
            {
                if (File.GetLastWriteTimeUtc(file) < cutoff)
                    File.Delete(file);
            }
            catch
            {
                // Best effort cleanup only.
            }
        }
    }
}
