using System.Text.Json;

namespace WorkTracker.Agent.Sync;

public sealed record SyncSettings(string ApiBaseUrl, string? AccessToken, DateTimeOffset? LastSuccessfulSyncAt, string? Cursor)
{
    public bool IsConfigured => Uri.TryCreate(ApiBaseUrl, UriKind.Absolute, out _) && !string.IsNullOrWhiteSpace(AccessToken);
}

public sealed record OutboxItem(string Id, string EntityType, string EntityId, string Operation, string PayloadJson, int AttemptCount)
{
    public int Version
    {
        get
        {
            try
            {
                using var doc = JsonDocument.Parse(PayloadJson);
                return doc.RootElement.TryGetProperty("version", out var v) && v.TryGetInt32(out var n) ? n : 1;
            }
            catch { return 1; }
        }
    }
}

public sealed record SyncAccepted(string Entity, string Id, int Version);
public sealed record SyncConflict(string Entity, string Id, int ServerVersion, string? Reason = null, string? ConflictId = null);
public sealed record RemoteChange(string Entity, string Id, int Version, JsonElement Payload, string UpdatedAt);
public sealed record ConflictResolution(string ConflictId,string Entity,string Id,string Resolution,int ServerVersion,JsonElement? ServerPayload);

public sealed record SyncResponse(
    IReadOnlyList<SyncAccepted> Accepted,
    IReadOnlyList<SyncConflict> Conflicts,
    IReadOnlyList<ConflictResolution> Resolutions,
    IReadOnlyList<RemoteChange> RemoteChanges,
    string ServerCursor);

public sealed record SyncStatus(string State,string Message,DateTimeOffset? LastSuccess=null,int Pending=0,int Conflicts=0)
{
    public static SyncStatus Disabled(string message = "همگام‌سازی پیکربندی نشده") => new("disabled", message);
}

public sealed record OpenSyncConflict(string Id,string EntityType,string EntityId,int ServerVersion,string? Reason,DateTimeOffset CreatedAt);
