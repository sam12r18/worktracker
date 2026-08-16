namespace WorkTracker.Agent.Tracking;

public sealed record LiveActivitySnapshot(
    ForegroundSnapshot Snapshot,
    string? ProjectId,
    string? ActivityTypeId,
    double? ClassificationConfidence,
    string? ClassificationReason,
    DateTimeOffset StartedAt,
    double? ActivityTypeConfidence = null,
    string? ActivityTypeReason = null,
    string? ActivityTypeSource = null);
