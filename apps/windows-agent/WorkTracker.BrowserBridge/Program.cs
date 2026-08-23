using System.Buffers.Binary;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace WorkTracker.BrowserBridge;

internal static class Program
{
    private const int MaxMessageBytes = 1024 * 1024;
    private static readonly JsonSerializerOptions Json = new(JsonSerializerDefaults.Web)
    {
        PropertyNameCaseInsensitive = true,
        WriteIndented = false,
    };

    public static async Task<int> Main(string[] args)
    {
        if (args.Any(x => string.Equals(x, "--diagnostics", StringComparison.OrdinalIgnoreCase)) || !Console.IsInputRedirected)
        {
            PrintManualLaunchDiagnostics();
            return 0;
        }

        await LogAsync("info", "host.started", new { process_id = Environment.ProcessId });

        try
        {
            var request = await ReadMessageAsync(Console.OpenStandardInput());
            if (request is null)
            {
                await LogAsync("warning", "host.empty_input");
                return 0;
            }

            var response = await HandleAsync(request.Value);
            await WriteMessageAsync(Console.OpenStandardOutput(), response);
            return response.Ok ? 0 : 2;
        }
        catch (Exception ex)
        {
            await LogAsync("error", "host.failed", new
            {
                exception = ex.GetType().Name,
                message = ex.Message,
            });

            try { await WriteMessageAsync(Console.OpenStandardOutput(), NativeResponse.Fail(ex.Message)); }
            catch { }
            return 3;
        }
    }

    private static void PrintManualLaunchDiagnostics()
    {
        Console.OutputEncoding = Encoding.UTF8;
        Console.WriteLine("WorkTracker BrowserBridge");
        Console.WriteLine("-------------------------");
        Console.WriteLine("This executable is a Chrome Native Messaging host, not a desktop application.");
        Console.WriteLine("Chrome launches it automatically and communicates through redirected stdin/stdout.");
        Console.WriteLine();
        Console.WriteLine($"Executable : {Environment.ProcessPath ?? "-"}");
        Console.WriteLine($"Context    : {GetContextPath()}");
        Console.WriteLine($"Log        : {GetLogPath()}");
        Console.WriteLine($"Manifest   : {GetExpectedManifestPath()}");
        Console.WriteLine();
        Console.WriteLine("Install flow:");
        Console.WriteLine("1. Open chrome://extensions and enable Developer mode.");
        Console.WriteLine("2. Load unpacked: apps\\chrome-extension");
        Console.WriteLine("3. Copy the real 32-character extension ID shown by Chrome.");
        Console.WriteLine("4. Run: .\\tools\\install-chrome-native-host.ps1 -ExtensionId \"<REAL_ID>\"");
        Console.WriteLine("5. Restart Chrome and enable Browser Context from the extension popup.");
        Console.WriteLine();
        Console.WriteLine("Running this EXE manually should not create browser context by itself.");
    }

    private static async Task<NativeResponse> HandleAsync(JsonElement request)
    {
        if (!request.TryGetProperty("action", out var actionElement))
        {
            await LogAsync("warning", "request.rejected", new { reason = "missing_action" });
            return NativeResponse.Fail("Missing action.");
        }

        var action = actionElement.GetString();
        if (string.Equals(action, "ping", StringComparison.Ordinal))
        {
            await LogAsync("info", "request.ping");
            return NativeResponse.Success();
        }

        if (string.Equals(action, "context.clear", StringComparison.Ordinal))
        {
            var response = ClearContext();
            await LogAsync(response.Ok ? "info" : "warning", "context.clear", new { ok = response.Ok, error = response.Error });
            return response;
        }

        if (!string.Equals(action, "context.update", StringComparison.Ordinal))
        {
            await LogAsync("warning", "request.rejected", new { reason = "unsupported_action", action });
            return NativeResponse.Fail("Unsupported action.");
        }

        if (!request.TryGetProperty("context", out var contextElement))
        {
            await LogAsync("warning", "context.rejected", new { reason = "missing_context" });
            return NativeResponse.Fail("Missing context.");
        }

        var incoming = contextElement.Deserialize<BrowserContextSnapshot>(Json);
        if (incoming is null)
        {
            await LogAsync("warning", "context.rejected", new { reason = "invalid_context" });
            return NativeResponse.Fail("Invalid context.");
        }

        var sanitized = Sanitize(incoming);
        if (sanitized is null)
        {
            await LogAsync("warning", "context.rejected", new
            {
                reason = "privacy_or_integrity_validation",
                browser = incoming.Browser,
                incognito = incoming.Incognito,
                focused = incoming.Focused,
            });
            return NativeResponse.Fail("Context rejected.");
        }

        var directory = GetContextDirectory();
        Directory.CreateDirectory(directory);
        var target = GetContextPath();
        var temp = Path.Combine(directory, $"context-{Guid.NewGuid():N}.tmp");
        try
        {
            await File.WriteAllTextAsync(temp, JsonSerializer.Serialize(sanitized, Json), new UTF8Encoding(false));
            File.Move(temp, target, overwrite: true);
        }
        finally
        {
            try { if (File.Exists(temp)) File.Delete(temp); }
            catch { }
        }

        await LogAsync("info", "context.updated", new
        {
            host = sanitized.Host,
            path = sanitized.Path,
            extension_version = sanitized.ExtensionVersion,
        });

        return NativeResponse.Success(DateTimeOffset.UtcNow);
    }

    private static NativeResponse ClearContext()
    {
        var target = GetContextPath();
        try
        {
            if (File.Exists(target)) File.Delete(target);
            return NativeResponse.Success(DateTimeOffset.UtcNow);
        }
        catch (IOException ex)
        {
            return NativeResponse.Fail($"Failed to clear browser context: {ex.Message}");
        }
        catch (UnauthorizedAccessException ex)
        {
            return NativeResponse.Fail($"Failed to clear browser context: {ex.Message}");
        }
    }

    private static string GetContextDirectory()
        => Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "WorkTracker",
            "browser",
            "chrome");

    private static string GetContextPath() => Path.Combine(GetContextDirectory(), "context.json");

    private static string GetExpectedManifestPath()
        => Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "WorkTracker",
            "browser-host",
            "ir.rayaasun.worktracker.browser.json");

    private static string GetLogPath()
        => Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "WorkTracker",
            "logs",
            $"browser-bridge-{DateTime.UtcNow:yyyy-MM-dd}.log");

    private static async Task LogAsync(string level, string eventName, object? data = null)
    {
        try
        {
            var path = GetLogPath();
            Directory.CreateDirectory(Path.GetDirectoryName(path)!);
            var line = JsonSerializer.Serialize(new
            {
                timestamp_utc = DateTimeOffset.UtcNow,
                level,
                category = "browser.bridge",
                @event = eventName,
                process_id = Environment.ProcessId,
                data,
            }, Json);
            await File.AppendAllTextAsync(path, line + Environment.NewLine, new UTF8Encoding(false));
        }
        catch
        {
            // Native Messaging protocol must never fail because diagnostics cannot be written.
        }
    }

    private static BrowserContextSnapshot? Sanitize(BrowserContextSnapshot value)
    {
        if (value.ProtocolVersion != BrowserContextSnapshot.SupportedProtocolVersion) return null;
        if (!string.Equals(value.Browser, "chrome", StringComparison.OrdinalIgnoreCase)) return null;
        if (value.Incognito || !value.Focused || string.IsNullOrWhiteSpace(value.Host)) return null;
        if (!Uri.TryCreate(value.Url, UriKind.Absolute, out var uri) || uri.Scheme is not ("http" or "https")) return null;

        var builder = new UriBuilder(uri) { UserName = string.Empty, Password = string.Empty, Query = string.Empty, Fragment = string.Empty };
        var normalizedHost = builder.Uri.Authority.ToLowerInvariant();
        if (!string.Equals(normalizedHost, value.Host.Trim(), StringComparison.OrdinalIgnoreCase)) return null;
        var path = NormalizePath(builder.Path);
        builder.Path = path;
        var now = DateTimeOffset.UtcNow;
        if (value.ObservedAtUtc > now.AddMinutes(5) || value.ObservedAtUtc < now.AddDays(-1)) return null;

        return value with {
            Browser = "chrome",
            ExtensionVersion = Clip(value.ExtensionVersion, 64),
            Title = Clip(value.Title, 1024),
            Url = Clip(builder.Uri.GetLeftPart(UriPartial.Path), 4096),
            Host = Clip(normalizedHost, 512),
            Path = Clip(path, 2048),
            Source = "chrome_extension"
        };
    }

    private static string NormalizePath(string? path)
    {
        var value = string.IsNullOrWhiteSpace(path) ? "/" : path.Trim();
        if (!value.StartsWith('/')) value = "/" + value;
        while (value.Contains("//", StringComparison.Ordinal)) value = value.Replace("//", "/", StringComparison.Ordinal);
        return value;
    }

    private static string? Clip(string? value, int max)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        value = value.Trim();
        return value.Length <= max ? value : value[..max];
    }

    private static async Task<JsonElement?> ReadMessageAsync(Stream input)
    {
        var lengthBuffer = new byte[4];
        var first = await input.ReadAsync(lengthBuffer);
        if (first == 0) return null;
        await ReadExactlyAsync(input, lengthBuffer, first);
        var length = BinaryPrimitives.ReadInt32LittleEndian(lengthBuffer);
        if (length <= 0 || length > MaxMessageBytes) throw new InvalidDataException("Invalid native message length.");
        var payload = new byte[length];
        await ReadExactlyAsync(input, payload, 0);
        using var document = JsonDocument.Parse(payload);
        return document.RootElement.Clone();
    }

    private static async Task ReadExactlyAsync(Stream input, byte[] buffer, int alreadyRead)
    {
        var offset = alreadyRead;
        while (offset < buffer.Length)
        {
            var read = await input.ReadAsync(buffer.AsMemory(offset));
            if (read == 0) throw new EndOfStreamException();
            offset += read;
        }
    }

    private static async Task WriteMessageAsync(Stream output, NativeResponse response)
    {
        var payload = JsonSerializer.SerializeToUtf8Bytes(response, Json);
        var length = new byte[4];
        BinaryPrimitives.WriteInt32LittleEndian(length, payload.Length);
        await output.WriteAsync(length);
        await output.WriteAsync(payload);
        await output.FlushAsync();
    }
}

internal sealed record BrowserContextSnapshot(
    [property: JsonPropertyName("protocol_version")] int ProtocolVersion,
    [property: JsonPropertyName("extension_version")] string? ExtensionVersion,
    [property: JsonPropertyName("browser")] string? Browser,
    [property: JsonPropertyName("title")] string? Title,
    [property: JsonPropertyName("url")] string? Url,
    [property: JsonPropertyName("host")] string? Host,
    [property: JsonPropertyName("path")] string? Path,
    [property: JsonPropertyName("tab_id")] int TabId,
    [property: JsonPropertyName("window_id")] int WindowId,
    [property: JsonPropertyName("incognito")] bool Incognito,
    [property: JsonPropertyName("focused")] bool Focused,
    [property: JsonPropertyName("observed_at_utc")] DateTimeOffset ObservedAtUtc,
    [property: JsonPropertyName("source")] string? Source)
{
    public const int SupportedProtocolVersion = 1;
}

internal sealed record NativeResponse(
    [property: JsonPropertyName("ok")] bool Ok,
    [property: JsonPropertyName("written_at_utc")] string? WrittenAtUtc = null,
    [property: JsonPropertyName("error")] string? Error = null)
{
    public static NativeResponse Success(DateTimeOffset? at = null) => new(true, at?.ToString("O"), null);
    public static NativeResponse Fail(string error) => new(false, null, error.Length <= 500 ? error : error[..500]);
}
