using WorkTracker.Agent.Integrations.Browser;
using WorkTracker.Agent.Integrations.Ide;

namespace WorkTracker.Agent.Integrations.Context;

public sealed record IntegrationStatus(
    string ProviderId,
    string DisplayName,
    string State,
    string Transport,
    int? AgeSeconds,
    string Summary,
    string Message)
{
    public static IntegrationStatus FromIde(IdeContextBridgeStatus status)
    {
        var state = status.Stale
            ? "stale"
            : status.Connected
                ? "connected"
                : "disconnected";

        var parts = new List<string>();
        if (!string.IsNullOrWhiteSpace(status.Project) && status.Project != "-") parts.Add(status.Project);
        if (!string.IsNullOrWhiteSpace(status.File) && status.File != "-") parts.Add(status.File);
        if (!string.IsNullOrWhiteSpace(status.Mode) && status.Mode != "-") parts.Add(status.Mode);
        if (!string.IsNullOrWhiteSpace(status.Branch) && status.Branch != "-") parts.Add($"Git {status.Branch}");

        return new IntegrationStatus(
            ProviderId: "phpstorm",
            DisplayName: "PhpStorm",
            State: state,
            Transport: "json",
            AgeSeconds: status.ObservedAtUtc is null ? null : status.AgeSeconds,
            Summary: parts.Count == 0 ? "-" : string.Join(" · ", parts),
            Message: status.Message);
    }

    public static IntegrationStatus FromBrowser(BrowserContextBridgeStatus status)
    {
        var state = status.Stale
            ? "stale"
            : status.Connected
                ? "connected"
                : "disconnected";

        var summary = status.Connected && !string.IsNullOrWhiteSpace(status.Host) && status.Host != "-"
            ? status.Host + (string.IsNullOrWhiteSpace(status.Path) || status.Path == "-" ? string.Empty : status.Path)
            : "-";

        return new IntegrationStatus(
            ProviderId: "chrome",
            DisplayName: "Chrome",
            State: state,
            Transport: "native->json",
            AgeSeconds: status.ObservedAtUtc is null ? null : status.AgeSeconds,
            Summary: summary,
            Message: status.Message);
    }
}
