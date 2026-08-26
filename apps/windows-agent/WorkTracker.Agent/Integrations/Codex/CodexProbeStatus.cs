namespace WorkTracker.Agent.Integrations.Codex;

public sealed record CodexProbeStatus(
    bool ForegroundDetected,
    int? ProcessId,
    string State,
    string? ProjectPath,
    string Signal,
    string Message,
    DateTimeOffset ObservedAtUtc)
{
    public static CodexProbeStatus NotForeground(DateTimeOffset? now = null)
        => new(
            ForegroundDetected: false,
            ProcessId: null,
            State: "idle",
            ProjectPath: null,
            Signal: "none",
            Message: "Codex در foreground نیست.",
            ObservedAtUtc: now ?? DateTimeOffset.UtcNow);
}
