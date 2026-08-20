using WorkTracker.Agent.Integrations.Ide;
using WorkTracker.Agent.Integrations.Browser;

namespace WorkTracker.Agent.Tracking;

public sealed record ForegroundSnapshot(
    nint WindowHandle,
    int ProcessId,
    string? ProcessName,
    string? ExecutablePath,
    string? WindowTitle,
    DateTimeOffset ObservedAt,
    IdeContextSnapshot? IdeContext = null,
    BrowserContextSnapshot? BrowserContext = null);
