namespace WorkTracker.Agent.Tracking;

public sealed record ForegroundSnapshot(
    nint WindowHandle,
    int ProcessId,
    string? ProcessName,
    string? ExecutablePath,
    string? WindowTitle,
    DateTimeOffset ObservedAt);
