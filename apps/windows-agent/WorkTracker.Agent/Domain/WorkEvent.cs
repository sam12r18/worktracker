namespace WorkTracker.Agent.Domain;

public sealed record ContinuityBridge(
    DateTimeOffset StartedAt,
    DateTimeOffset EndedAt,
    int DurationSeconds,
    string AnchorProjectId,
    IReadOnlyList<string> InterruptedProjectIds);

public sealed record WorkEvent(
    string Id,
    string? ProjectId,
    DateTimeOffset StartedAt,
    DateTimeOffset EndedAt,
    int CreditedSeconds,
    int DirectSeconds,
    int BridgeSeconds,
    IReadOnlyList<ActivitySession> Sessions,
    IReadOnlyList<ContinuityBridge> Bridges,
    string ContextKey);
