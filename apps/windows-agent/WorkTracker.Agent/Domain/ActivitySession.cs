namespace WorkTracker.Agent.Domain;

public sealed record ActivitySession(
    string Id,
    string UserId,
    string DeviceId,
    string? ProjectId,
    string? TaskId,
    ActivitySource Source,
    string? ProcessName,
    string? ExecutablePath,
    string? WindowTitle,
    double? ClassificationConfidence,
    string? ClassificationReason,
    DateTimeOffset StartedAt,
    DateTimeOffset EndedAt,
    int DurationSeconds,
    int IdleSeconds,
    string? Note,
    string? ActivityTypeId = null,
    bool? IsBillable = null,
    int Version = 1,
    string SyncState = "pending",
    double? ActivityTypeConfidence = null,
    string? ActivityTypeReason = null,
    string? ActivityTypeSource = null,
    string? IdeContextJson = null
);
