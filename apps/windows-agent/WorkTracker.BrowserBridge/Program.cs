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

    public static async Task<int> Main()
    {
        try
        {
            var request = await ReadMessageAsync(Console.OpenStandardInput());
            if (request is null) return 0;
            var response = await HandleAsync(request.Value);
            await WriteMessageAsync(Console.OpenStandardOutput(), response);
            return response.Ok ? 0 : 2;
        }
        catch (Exception ex)
        {
            try { await WriteMessageAsync(Console.OpenStandardOutput(), NativeResponse.Fail(ex.Message)); }
            catch { }
            return 3;
        }
    }

    private static async Task<NativeResponse> HandleAsync(JsonElement request)
    {
        if (!request.TryGetProperty("action", out var actionElement)) return NativeResponse.Fail("Missing action.");
        var action = actionElement.GetString();
        if (string.Equals(action, "ping", StringComparison.Ordinal)) return NativeResponse.Success();
        if (!string.Equals(action, "context.update", StringComparison.Ordinal)) return NativeResponse.Fail("Unsupported action.");
        if (!request.TryGetProperty("context", out var contextElement)) return NativeResponse.Fail("Missing context.");

        var incoming = contextElement.Deserialize<BrowserContextSnapshot>(Json);
        if (incoming is null) return NativeResponse.Fail("Invalid context.");
        var sanitized = Sanitize(incoming);
        if (sanitized is null) return NativeResponse.Fail("Context rejected.");

        var directory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "WorkTracker", "browser", "chrome");
        Directory.CreateDirectory(directory);
        var target = Path.Combine(directory, "context.json");
        var temp = Path.Combine(directory, $"context-{Guid.NewGuid():N}.tmp");
        await File.WriteAllTextAsync(temp, JsonSerializer.Serialize(sanitized, Json), new UTF8Encoding(false));
        File.Move(temp, target, overwrite: true);
        return NativeResponse.Success(DateTimeOffset.UtcNow);
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
