using System.Text.Json.Serialization;

namespace WorkTracker.Agent.Integrations.Browser;

public sealed record BrowserContextSnapshot(
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
    public bool IsSupported => ProtocolVersion == SupportedProtocolVersion;
}

public sealed record BrowserContextBridgeStatus(
    bool Connected,
    bool Stale,
    string Browser,
    string Host,
    string Path,
    DateTimeOffset? ObservedAtUtc,
    int AgeSeconds,
    string Message)
{
    public static BrowserContextBridgeStatus Disconnected(string message)
        => new(false, false, "Chrome", "-", "-", null, 0, message);
}
