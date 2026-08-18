using Microsoft.Data.Sqlite;
using WorkTracker.Agent.Domain;

namespace WorkTracker.Agent.Storage;

public sealed class ActivitySessionRepository(LocalDatabase database)
{
    public async Task AddAsync(ActivitySession session, CancellationToken cancellationToken = default)
    {
        await using var connection = database.OpenConnection();
        await using var transaction = await connection.BeginTransactionAsync(cancellationToken);

        await using var cmd = connection.CreateCommand();
        cmd.Transaction = (SqliteTransaction)transaction;
        cmd.CommandText = """
            INSERT INTO activity_sessions (
                id,user_id,device_id,project_id,task_id,activity_type_id,activity_type_confidence,activity_type_source,activity_type_reason,ide_context_json,is_billable,source,process_name,executable_path,window_title,
                classification_confidence,classification_reason,started_at,ended_at,duration_seconds,idle_seconds,
                note,version,sync_state,created_at_device,updated_at_device
            ) VALUES (
                $id,$user_id,$device_id,$project_id,$task_id,$activity_type_id,$activity_type_confidence,$activity_type_source,$activity_type_reason,$ide_context_json,$is_billable,$source,$process_name,$executable_path,$window_title,
                $confidence,$reason,$started_at,$ended_at,$duration,$idle,$note,$version,$sync_state,$created,$updated
            );
            """;
        Bind(cmd, session);
        await cmd.ExecuteNonQueryAsync(cancellationToken);
        await QueueSessionOutboxAsync(connection, (SqliteTransaction)transaction, session, clearExisting: false, includeCreatedAtDevice: true, ct: cancellationToken);
        await transaction.CommitAsync(cancellationToken);
    }

    public async Task<IReadOnlyList<ActivitySession>> GetForLocalDayAsync(DateTime localDay, CancellationToken cancellationToken = default)
    {
        var startLocal = new DateTimeOffset(localDay.Date, TimeZoneInfo.Local.GetUtcOffset(localDay.Date));
        var endLocal = startLocal.AddDays(1);
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = """
            SELECT * FROM activity_sessions
            WHERE started_at < $end AND ended_at > $start
            ORDER BY started_at DESC;
            """;
        cmd.Parameters.AddWithValue("$start", startLocal.ToUniversalTime().ToString("O"));
        cmd.Parameters.AddWithValue("$end", endLocal.ToUniversalTime().ToString("O"));
        var result = new List<ActivitySession>();
        await using var reader = await cmd.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken)) result.Add(Read(reader));
        return result;
    }

    public async Task<IReadOnlyList<ActivitySession>> GetUnknownForLocalDayAsync(DateTime localDay, CancellationToken cancellationToken = default)
    {
        var all = await GetForLocalDayAsync(localDay, cancellationToken);
        return all.Where(x => string.IsNullOrWhiteSpace(x.ProjectId)).OrderByDescending(x => x.StartedAt).ToList();
    }

    public async Task<ActivitySession?> AssignProjectAsync(string sessionId, string projectId, string reason = "user_correction", double confidence = 1.0, CancellationToken cancellationToken = default)
    {
        var updated = await AssignProjectsAsync([sessionId], projectId, reason, confidence, cancellationToken);
        return updated.FirstOrDefault();
    }

    public async Task<IReadOnlyList<ActivitySession>> AssignProjectsAsync(IEnumerable<string> sessionIds, string projectId, string reason = "user_correction", double confidence = 1.0, CancellationToken cancellationToken = default)
    {
        var ids = sessionIds.Where(x => !string.IsNullOrWhiteSpace(x)).Distinct(StringComparer.OrdinalIgnoreCase).ToList();
        if (ids.Count == 0) return [];

        await using var connection = database.OpenConnection();
        await using var tx = await connection.BeginTransactionAsync(cancellationToken);
        var updatedAt = DateTimeOffset.UtcNow.ToString("O");
        var result = new List<ActivitySession>();

        foreach (var id in ids)
        {
            await using var update = connection.CreateCommand();
            update.Transaction = (SqliteTransaction)tx;
            update.CommandText = "UPDATE activity_sessions SET project_id=$project, classification_confidence=$confidence, classification_reason=$reason, version=version+1, sync_state='pending', updated_at_device=$updated WHERE id=$id";
            update.Parameters.AddWithValue("$project", projectId);
            update.Parameters.AddWithValue("$confidence", confidence);
            update.Parameters.AddWithValue("$reason", reason);
            update.Parameters.AddWithValue("$updated", updatedAt);
            update.Parameters.AddWithValue("$id", id);
            if (await update.ExecuteNonQueryAsync(cancellationToken) == 0) continue;

            var session = await ReadByIdAsync(connection, (SqliteTransaction)tx, id, cancellationToken);
            if (session is null) continue;
            await QueueSessionOutboxAsync(connection, (SqliteTransaction)tx, session, clearExisting: true, includeCreatedAtDevice: false, ct: cancellationToken);
            result.Add(session);
        }

        await tx.CommitAsync(cancellationToken);
        return result;
    }

    public async Task<ActivitySession?> AssignActivityTypeAsync(string sessionId, string activityTypeId, bool? isBillable, CancellationToken cancellationToken = default)
    {
        var updated = await AssignActivityTypeManyAsync([sessionId], activityTypeId, isBillable, cancellationToken);
        return updated.FirstOrDefault();
    }

    public async Task<IReadOnlyList<ActivitySession>> AssignActivityTypeManyAsync(IEnumerable<string> sessionIds, string activityTypeId, bool? isBillable, CancellationToken cancellationToken = default)
    {
        var ids = sessionIds.Where(x => !string.IsNullOrWhiteSpace(x)).Distinct(StringComparer.OrdinalIgnoreCase).ToList();
        if (ids.Count == 0) return [];

        await using var connection = database.OpenConnection();
        await using var tx = await connection.BeginTransactionAsync(cancellationToken);
        var updatedAt = DateTimeOffset.UtcNow.ToString("O");
        var result = new List<ActivitySession>();

        foreach (var id in ids)
        {
            await using var update = connection.CreateCommand();
            update.Transaction = (SqliteTransaction)tx;
            update.CommandText = "UPDATE activity_sessions SET activity_type_id=$type,activity_type_confidence=1.0,activity_type_source='user_override',activity_type_reason='user_activity_type_correction',is_billable=$billable,version=version+1,sync_state='pending',updated_at_device=$updated WHERE id=$id";
            update.Parameters.AddWithValue("$type", activityTypeId);
            update.Parameters.AddWithValue("$billable", isBillable.HasValue ? (object)(isBillable.Value ? 1 : 0) : DBNull.Value);
            update.Parameters.AddWithValue("$updated", updatedAt);
            update.Parameters.AddWithValue("$id", id);
            if (await update.ExecuteNonQueryAsync(cancellationToken) == 0) continue;

            var session = await ReadByIdAsync(connection, (SqliteTransaction)tx, id, cancellationToken);
            if (session is null) continue;
            await QueueSessionOutboxAsync(connection, (SqliteTransaction)tx, session, clearExisting: true, includeCreatedAtDevice: false, ct: cancellationToken);
            result.Add(session);
        }

        await tx.CommitAsync(cancellationToken);
        return result;
    }

    public async Task<int> CountPendingSyncAsync(CancellationToken cancellationToken = default)
    {
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = "SELECT COUNT(*) FROM sync_outbox";
        return Convert.ToInt32(await cmd.ExecuteScalarAsync(cancellationToken));
    }

    private static async Task<ActivitySession?> ReadByIdAsync(SqliteConnection connection, SqliteTransaction tx, string id, CancellationToken ct)
    {
        await using var select = connection.CreateCommand();
        select.Transaction = tx;
        select.CommandText = "SELECT * FROM activity_sessions WHERE id=$id";
        select.Parameters.AddWithValue("$id", id);
        await using var reader = await select.ExecuteReaderAsync(ct);
        return await reader.ReadAsync(ct) ? Read(reader) : null;
    }

    private static async Task QueueSessionOutboxAsync(SqliteConnection connection, SqliteTransaction tx, ActivitySession session, bool clearExisting, bool includeCreatedAtDevice, CancellationToken ct)
    {
        if (clearExisting)
        {
            await using var clear = connection.CreateCommand();
            clear.Transaction = tx;
            clear.CommandText = "DELETE FROM sync_outbox WHERE entity_type='activity_session' AND entity_id=$entity";
            clear.Parameters.AddWithValue("$entity", session.Id);
            await clear.ExecuteNonQueryAsync(ct);
        }

        await using var outbox = connection.CreateCommand();
        outbox.Transaction = tx;
        outbox.CommandText = "INSERT INTO sync_outbox(id,entity_type,entity_id,operation,payload_json,created_at) VALUES($id,'activity_session',$entity_id,'upsert',$payload,$created_at)";
        outbox.Parameters.AddWithValue("$id", Guid.NewGuid().ToString("N"));
        outbox.Parameters.AddWithValue("$entity_id", session.Id);
        outbox.Parameters.AddWithValue("$payload", System.Text.Json.JsonSerializer.Serialize(BuildSyncPayload(session, includeCreatedAtDevice)));
        outbox.Parameters.AddWithValue("$created_at", DateTimeOffset.UtcNow.ToString("O"));
        await outbox.ExecuteNonQueryAsync(ct);
    }

    private static Dictionary<string, object?> BuildSyncPayload(ActivitySession session, bool includeCreatedAtDevice)
    {
        var payload = new Dictionary<string, object?>
        {
            ["id"] = session.Id,
            ["device_id"] = session.DeviceId,
            ["project_id"] = session.ProjectId,
            ["task_id"] = session.TaskId,
            ["activity_type_id"] = session.ActivityTypeId,
            ["activity_type_confidence"] = session.ActivityTypeConfidence,
            ["activity_type_source"] = session.ActivityTypeSource,
            ["activity_type_reason"] = session.ActivityTypeReason,
            ["ide_context"] = string.IsNullOrWhiteSpace(session.IdeContextJson) ? null : System.Text.Json.JsonSerializer.Deserialize<object>(session.IdeContextJson),
            ["is_billable"] = session.IsBillable,
            ["source"] = session.Source.ToStorageValue(),
            ["process_name"] = session.ProcessName,
            ["executable_path"] = session.ExecutablePath,
            ["window_title"] = session.WindowTitle,
            ["classification_confidence"] = session.ClassificationConfidence,
            ["classification_reason"] = session.ClassificationReason,
            ["started_at"] = session.StartedAt.ToUniversalTime().ToString("O"),
            ["ended_at"] = session.EndedAt.ToUniversalTime().ToString("O"),
            ["duration_seconds"] = session.DurationSeconds,
            ["idle_seconds"] = session.IdleSeconds,
            ["note"] = session.Note,
            ["version"] = session.Version,
            ["updated_at_device"] = DateTimeOffset.UtcNow.ToString("O"),
        };
        if (includeCreatedAtDevice) payload["created_at_device"] = DateTimeOffset.UtcNow.ToString("O");
        return payload;
    }

    private static void Bind(SqliteCommand cmd, ActivitySession s)
    {
        cmd.Parameters.AddWithValue("$id", s.Id);
        cmd.Parameters.AddWithValue("$user_id", s.UserId);
        cmd.Parameters.AddWithValue("$device_id", s.DeviceId);
        cmd.Parameters.AddWithValue("$project_id", (object?)s.ProjectId ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$task_id", (object?)s.TaskId ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$activity_type_id", (object?)s.ActivityTypeId ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$activity_type_confidence", (object?)s.ActivityTypeConfidence ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$activity_type_source", (object?)s.ActivityTypeSource ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$activity_type_reason", (object?)s.ActivityTypeReason ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$ide_context_json", (object?)s.IdeContextJson ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$is_billable", s.IsBillable.HasValue ? (object)(s.IsBillable.Value ? 1 : 0) : DBNull.Value);
        cmd.Parameters.AddWithValue("$source", s.Source.ToStorageValue());
        cmd.Parameters.AddWithValue("$process_name", (object?)s.ProcessName ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$executable_path", (object?)s.ExecutablePath ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$window_title", (object?)s.WindowTitle ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$confidence", (object?)s.ClassificationConfidence ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$reason", (object?)s.ClassificationReason ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$started_at", s.StartedAt.ToUniversalTime().ToString("O"));
        cmd.Parameters.AddWithValue("$ended_at", s.EndedAt.ToUniversalTime().ToString("O"));
        cmd.Parameters.AddWithValue("$duration", s.DurationSeconds);
        cmd.Parameters.AddWithValue("$idle", s.IdleSeconds);
        cmd.Parameters.AddWithValue("$note", (object?)s.Note ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$version", s.Version);
        cmd.Parameters.AddWithValue("$sync_state", s.SyncState);
        var now = DateTimeOffset.UtcNow.ToString("O");
        cmd.Parameters.AddWithValue("$created", now);
        cmd.Parameters.AddWithValue("$updated", now);
    }

    private static ActivitySession Read(SqliteDataReader r) => new(
        r.GetString(r.GetOrdinal("id")), r.GetString(r.GetOrdinal("user_id")), r.GetString(r.GetOrdinal("device_id")),
        NullableString(r,"project_id"), NullableString(r,"task_id"), ActivitySourceExtensions.FromStorageValue(r.GetString(r.GetOrdinal("source"))),
        NullableString(r,"process_name"), NullableString(r,"executable_path"), NullableString(r,"window_title"), NullableDouble(r,"classification_confidence"),
        NullableString(r,"classification_reason"), DateTimeOffset.Parse(r.GetString(r.GetOrdinal("started_at"))), DateTimeOffset.Parse(r.GetString(r.GetOrdinal("ended_at"))),
        r.GetInt32(r.GetOrdinal("duration_seconds")), r.GetInt32(r.GetOrdinal("idle_seconds")), NullableString(r,"note"), NullableString(r,"activity_type_id"), NullableBool(r,"is_billable"), r.GetInt32(r.GetOrdinal("version")), r.GetString(r.GetOrdinal("sync_state")),
        NullableDouble(r,"activity_type_confidence"), NullableString(r,"activity_type_reason"), NullableString(r,"activity_type_source"), NullableString(r,"ide_context_json"));

    private static string? NullableString(SqliteDataReader r, string name) { var i=r.GetOrdinal(name); return r.IsDBNull(i)?null:r.GetString(i); }
    private static bool? NullableBool(SqliteDataReader r, string name) { var i=r.GetOrdinal(name); return r.IsDBNull(i)?null:r.GetInt32(i)==1; }
    private static double? NullableDouble(SqliteDataReader r, string name) { var i=r.GetOrdinal(name); return r.IsDBNull(i)?null:r.GetDouble(i); }
}
