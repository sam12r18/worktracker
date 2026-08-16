namespace WorkTracker.Agent.Domain;

public sealed record ContinuityBridge(
    DateTimeOffset StartedAt,
    DateTimeOffset EndedAt,
    int DurationSeconds,
    string AnchorProjectId,
    IReadOnlyList<string> InterruptedProjectIds);

public enum WorkEventAggregationState
{
    Direct,
    Suspended,
    BridgeCandidate,
    Bridged,
    Closed
}

public sealed record WorkEventAggregationDecision(
    string ProjectId,
    WorkEventAggregationState State,
    DateTimeOffset At,
    string Reason,
    int DirectSinceLastBridgeSeconds,
    int? GapSeconds = null,
    IReadOnlyList<string>? InterruptedProjectIds = null);

public sealed record WorkEventAggregationResult(
    IReadOnlyList<WorkEvent> Events,
    IReadOnlyList<WorkEventAggregationDecision> Decisions);

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
