using Microsoft.Data.Sqlite;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Sync;

public sealed class SyncOutboxRepository(LocalDatabase database)
{
    public async Task<IReadOnlyList<OutboxItem>> GetDueAsync(int limit = 200, CancellationToken ct = default)
    {
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = """
            SELECT id,entity_type,entity_id,operation,payload_json,attempt_count
            FROM sync_outbox
            WHERE next_attempt_at IS NULL OR next_attempt_at <= $now
            ORDER BY CASE entity_type
                WHEN 'project_rule' THEN 0
                WHEN 'project' THEN 1
                ELSE 2
            END, created_at, id LIMIT $limit;
            """;
        cmd.Parameters.AddWithValue("$now", DateTimeOffset.UtcNow.ToString("O"));
        cmd.Parameters.AddWithValue("$limit", limit);
        var result = new List<OutboxItem>();
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct)) result.Add(new(reader.GetString(0),reader.GetString(1),reader.GetString(2),reader.GetString(3),reader.GetString(4),reader.GetInt32(5)));
        return result;
    }

    public async Task<SyncAcknowledgeResult> MarkAcceptedAsync(
        IReadOnlyList<OutboxItem> sentItems,
        IReadOnlyList<SyncAccepted> accepted,
        bool wholeBatchAccepted,
        CancellationToken ct = default)
    {
        // alpha.7.3+: the server echoes client_outbox_id, so acknowledgement can target the exact
        // local queue row that was sent. This remains backward compatible with older servers by
        // falling back to entity/id matching, and only then to whole-batch acceptance when the
        // response count proves that every sent row was accepted and there were no conflicts.
        var sentByOutboxId = sentItems.ToDictionary(x => x.Id, x => x, StringComparer.OrdinalIgnoreCase);
        var acceptedOutboxIds = accepted
            .Where(x => !string.IsNullOrWhiteSpace(x.ClientOutboxId))
            .Select(x => x.ClientOutboxId!.Trim())
            .Where(sentByOutboxId.ContainsKey)
            .ToHashSet(StringComparer.OrdinalIgnoreCase);

        var acceptedKeys = accepted
            .Where(x => !string.IsNullOrWhiteSpace(x.Entity) && !string.IsNullOrWhiteSpace(x.Id))
            .Select(x => EntityKey(x.Entity, x.Id))
            .ToHashSet(StringComparer.OrdinalIgnoreCase);

        var sentByKey = sentItems
            .GroupBy(x => EntityKey(x.EntityType, x.EntityId), StringComparer.OrdinalIgnoreCase)
            .ToDictionary(g => g.Key, g => g.ToList(), StringComparer.OrdinalIgnoreCase);

        var unmatchedAccepted = accepted
            .Where(x => string.IsNullOrWhiteSpace(x.ClientOutboxId) || !sentByOutboxId.ContainsKey(x.ClientOutboxId.Trim()))
            .Select(x => EntityKey(x.Entity, x.Id))
            .Where(x => !sentByKey.ContainsKey(x))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .Take(20)
            .ToList();

        var matched = 0;
        var deleted = 0;
        var usedWholeBatchFallback = false;

        await using var connection = database.OpenConnection();
        await using var tx = await connection.BeginTransactionAsync(ct);

        foreach (var item in sentItems)
        {
            var exactOutboxMatched = acceptedOutboxIds.Contains(item.Id);
            var entityKeyMatched = acceptedKeys.Contains(EntityKey(item.EntityType, item.EntityId));
            var keyMatched = exactOutboxMatched || entityKeyMatched;

            if (!keyMatched && !wholeBatchAccepted)
                continue;

            if (keyMatched)
                matched++;
            else
                usedWholeBatchFallback = true;

            await using var delete = connection.CreateCommand();
            delete.Transaction = (SqliteTransaction)tx;
            delete.CommandText = "DELETE FROM sync_outbox WHERE id=$outbox_id";
            delete.Parameters.AddWithValue("$outbox_id", item.Id);
            deleted += await delete.ExecuteNonQueryAsync(ct);

            // A newer edit may have replaced the sent outbox row while HTTP was in flight.
            // Only mark the entity synced when no newer local work is still pending.
            if (!await HasPendingOutboxAsync(connection, (SqliteTransaction)tx, item.EntityType, item.EntityId, ct))
                await SetEntitySyncStateAsync(connection, (SqliteTransaction)tx, item.EntityType, item.EntityId, "synced", ct);
        }

        await tx.CommitAsync(ct);
        return new SyncAcknowledgeResult(sentItems.Count, accepted.Count, matched, deleted, usedWholeBatchFallback, unmatchedAccepted);
    }

    private static string EntityKey(string entity, string id)
        => $"{entity.Trim().ToLowerInvariant()}\u001f{id.Trim().ToLowerInvariant()}";

    private static async Task<bool> HasPendingOutboxAsync(
        SqliteConnection connection, SqliteTransaction tx, string entity, string id, CancellationToken ct)
    {
        await using var cmd = connection.CreateCommand();
        cmd.Transaction = tx;
        cmd.CommandText = "SELECT 1 FROM sync_outbox WHERE entity_type=$entity AND entity_id=$id LIMIT 1";
        cmd.Parameters.AddWithValue("$entity", entity);
        cmd.Parameters.AddWithValue("$id", id);
        return await cmd.ExecuteScalarAsync(ct) is not null;
    }

    public async Task MarkConflictsAsync(IEnumerable<SyncConflict> conflicts, CancellationToken ct = default)
    {
        await using var connection=database.OpenConnection(); await using var tx=await connection.BeginTransactionAsync(ct);
        foreach(var conflict in conflicts)
        {
            await using var ins=connection.CreateCommand();ins.Transaction=(SqliteTransaction)tx;
            ins.CommandText="""
                INSERT INTO sync_conflicts(id,entity_type,entity_id,server_version,reason,created_at,resolved_at)
                VALUES($cid,$entity,$id,$version,$reason,$created,NULL);
                """;
            ins.Parameters.AddWithValue("$cid",Guid.NewGuid().ToString());ins.Parameters.AddWithValue("$entity",conflict.Entity);ins.Parameters.AddWithValue("$id",conflict.Id);ins.Parameters.AddWithValue("$version",conflict.ServerVersion);ins.Parameters.AddWithValue("$reason",(object?)conflict.Reason??"server_newer");ins.Parameters.AddWithValue("$created",DateTimeOffset.UtcNow.ToString("O"));await ins.ExecuteNonQueryAsync(ct);
            await using var del=connection.CreateCommand();del.Transaction=(SqliteTransaction)tx;del.CommandText="DELETE FROM sync_outbox WHERE entity_type=$entity AND entity_id=$id";del.Parameters.AddWithValue("$entity",conflict.Entity);del.Parameters.AddWithValue("$id",conflict.Id);await del.ExecuteNonQueryAsync(ct);
            await SetEntitySyncStateAsync(connection,(SqliteTransaction)tx,conflict.Entity,conflict.Id,"conflict",ct);
        }
        await tx.CommitAsync(ct);
    }

    public async Task MarkFailedAsync(IEnumerable<OutboxItem> items, string error, CancellationToken ct = default)
    {
        await using var connection=database.OpenConnection();
        foreach(var item in items)
        {
            var attempt=item.AttemptCount+1;var delaySeconds=Math.Min(300,Math.Max(5,(int)Math.Pow(2,Math.Min(attempt,8))));
            await using var cmd=connection.CreateCommand();cmd.CommandText="UPDATE sync_outbox SET attempt_count=$attempt,next_attempt_at=$next,last_error=$error WHERE id=$id";
            cmd.Parameters.AddWithValue("$attempt",attempt);cmd.Parameters.AddWithValue("$next",DateTimeOffset.UtcNow.AddSeconds(delaySeconds).ToString("O"));cmd.Parameters.AddWithValue("$error",error.Length>1000?error[..1000]:error);cmd.Parameters.AddWithValue("$id",item.Id);await cmd.ExecuteNonQueryAsync(ct);
        }
    }

    public async Task<int> RetryDelayedNowAsync(CancellationToken ct = default)
    {
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = "UPDATE sync_outbox SET next_attempt_at = NULL WHERE next_attempt_at IS NOT NULL";
        return await cmd.ExecuteNonQueryAsync(ct);
    }

    public async Task<SyncQueueDiagnostics> GetQueueDiagnosticsAsync(CancellationToken ct = default)
    {
        await using var connection = database.OpenConnection();
        var now = DateTimeOffset.UtcNow.ToString("O");
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = """
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN next_attempt_at IS NULL OR next_attempt_at <= $now THEN 1 ELSE 0 END) AS due,
                SUM(CASE WHEN next_attempt_at IS NOT NULL AND next_attempt_at > $now THEN 1 ELSE 0 END) AS delayed,
                SUM(CASE WHEN last_error IS NOT NULL AND last_error <> '' THEN 1 ELSE 0 END) AS failed,
                COALESCE(MAX(attempt_count), 0) AS max_attempts,
                MIN(CASE WHEN next_attempt_at IS NOT NULL AND next_attempt_at > $now THEN next_attempt_at END) AS next_retry
            FROM sync_outbox;
            """;
        cmd.Parameters.AddWithValue("$now", now);

        int total, due, delayed, failed, maxAttempts;
        DateTimeOffset? nextRetry = null;
        await using (var reader = await cmd.ExecuteReaderAsync(ct))
        {
            await reader.ReadAsync(ct);
            total = reader.IsDBNull(0) ? 0 : Convert.ToInt32(reader.GetInt64(0));
            due = reader.IsDBNull(1) ? 0 : Convert.ToInt32(reader.GetInt64(1));
            delayed = reader.IsDBNull(2) ? 0 : Convert.ToInt32(reader.GetInt64(2));
            failed = reader.IsDBNull(3) ? 0 : Convert.ToInt32(reader.GetInt64(3));
            maxAttempts = reader.IsDBNull(4) ? 0 : Convert.ToInt32(reader.GetInt64(4));
            if (!reader.IsDBNull(5) && DateTimeOffset.TryParse(reader.GetString(5), out var parsed)) nextRetry = parsed;
        }

        await using var last = connection.CreateCommand();
        last.CommandText = """
            SELECT entity_type,entity_id,last_error
            FROM sync_outbox
            WHERE last_error IS NOT NULL AND last_error <> ''
            ORDER BY attempt_count DESC, COALESCE(next_attempt_at, created_at) DESC
            LIMIT 1;
            """;
        string? entity = null, entityId = null, error = null;
        await using (var reader = await last.ExecuteReaderAsync(ct))
        {
            if (await reader.ReadAsync(ct))
            {
                entity = reader.IsDBNull(0) ? null : reader.GetString(0);
                entityId = reader.IsDBNull(1) ? null : reader.GetString(1);
                error = reader.IsDBNull(2) ? null : reader.GetString(2);
            }
        }

        return new SyncQueueDiagnostics(total, due, delayed, failed, maxAttempts, nextRetry, error, entity, entityId);
    }

    public async Task<int> CountConflictsAsync(CancellationToken ct=default)
    {
        await using var connection=database.OpenConnection();await using var cmd=connection.CreateCommand();cmd.CommandText="SELECT COUNT(*) FROM sync_conflicts WHERE resolved_at IS NULL";return Convert.ToInt32(await cmd.ExecuteScalarAsync(ct));
    }

    public async Task<IReadOnlyList<OpenSyncConflict>> GetOpenConflictsAsync(CancellationToken ct=default)
    {
        await using var connection=database.OpenConnection();await using var cmd=connection.CreateCommand();cmd.CommandText="SELECT id,entity_type,entity_id,server_version,reason,created_at FROM sync_conflicts WHERE resolved_at IS NULL ORDER BY created_at DESC LIMIT 200";
        var result=new List<OpenSyncConflict>();await using var reader=await cmd.ExecuteReaderAsync(ct);while(await reader.ReadAsync(ct))result.Add(new(reader.GetString(0),reader.GetString(1),reader.GetString(2),reader.GetInt32(3),reader.IsDBNull(4)?null:reader.GetString(4),DateTimeOffset.Parse(reader.GetString(5))));return result;
    }


    public async Task<IReadOnlyList<string>> GetPendingResolutionAckIdsAsync(CancellationToken ct=default)
    {
        await using var connection=database.OpenConnection();await using var cmd=connection.CreateCommand();cmd.CommandText="SELECT conflict_id FROM sync_resolution_acks ORDER BY created_at LIMIT 500";
        var result=new List<string>();await using var reader=await cmd.ExecuteReaderAsync(ct);while(await reader.ReadAsync(ct))result.Add(reader.GetString(0));return result;
    }

    public async Task MarkResolutionAcksSentAsync(IEnumerable<string> ids,CancellationToken ct=default)
    {
        await using var connection=database.OpenConnection();foreach(var id in ids){await using var cmd=connection.CreateCommand();cmd.CommandText="DELETE FROM sync_resolution_acks WHERE conflict_id=$id";cmd.Parameters.AddWithValue("$id",id);await cmd.ExecuteNonQueryAsync(ct);}
    }

    public async Task ApplyResolutionsAsync(IEnumerable<ConflictResolution> resolutions,CancellationToken ct=default)
    {
        await using var connection=database.OpenConnection();await using var tx=await connection.BeginTransactionAsync(ct);
        foreach(var r in resolutions)
        {
            await using var conflict=connection.CreateCommand();conflict.Transaction=(SqliteTransaction)tx;conflict.CommandText="UPDATE sync_conflicts SET resolved_at=$now WHERE entity_type=$entity AND entity_id=$id AND resolved_at IS NULL";conflict.Parameters.AddWithValue("$now",DateTimeOffset.UtcNow.ToString("O"));conflict.Parameters.AddWithValue("$entity",r.Entity);conflict.Parameters.AddWithValue("$id",r.Id);await conflict.ExecuteNonQueryAsync(ct);
            await SetEntitySyncStateAsync(connection,(SqliteTransaction)tx,r.Entity,r.Id,"synced",ct);
            await SetEntityVersionAsync(connection,(SqliteTransaction)tx,r.Entity,r.Id,r.ServerVersion,ct);
            await using var ack=connection.CreateCommand();ack.Transaction=(SqliteTransaction)tx;ack.CommandText="INSERT OR IGNORE INTO sync_resolution_acks(conflict_id,created_at) VALUES($id,$created)";ack.Parameters.AddWithValue("$id",r.ConflictId);ack.Parameters.AddWithValue("$created",DateTimeOffset.UtcNow.ToString("O"));await ack.ExecuteNonQueryAsync(ct);
        }
        await tx.CommitAsync(ct);
    }
    private static async Task SetEntityVersionAsync(SqliteConnection connection,SqliteTransaction tx,string entity,string id,int version,CancellationToken ct)
    {
        var table=entity switch{"activity_session"=>"activity_sessions","project"=>"projects","project_rule"=>"project_rules",_=>null};if(table is null)return;
        await using var cmd=connection.CreateCommand();cmd.Transaction=tx;cmd.CommandText=$"UPDATE {table} SET version=$version WHERE id=$id";cmd.Parameters.AddWithValue("$version",version);cmd.Parameters.AddWithValue("$id",id);await cmd.ExecuteNonQueryAsync(ct);
    }

    private static async Task SetEntitySyncStateAsync(SqliteConnection connection, SqliteTransaction tx, string entity, string id, string state, CancellationToken ct)
    {
        var table=entity switch{"activity_session"=>"activity_sessions","project"=>"projects","project_rule"=>"project_rules",_=>null};if(table is null)return;
        await using var cmd=connection.CreateCommand();cmd.Transaction=tx;cmd.CommandText=$"UPDATE {table} SET sync_state=$state WHERE id=$id";cmd.Parameters.AddWithValue("$state",state);cmd.Parameters.AddWithValue("$id",id);await cmd.ExecuteNonQueryAsync(ct);
    }
}
