using System.Text.Json;
using Microsoft.Data.Sqlite;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Sync;

public sealed class RemoteChangeApplier(LocalDatabase database)
{
    public async Task ApplyAsync(IEnumerable<RemoteChange> changes, string? correlationId = null, CancellationToken ct=default)
    {
        await using var connection=database.OpenConnection(); await using var tx=await connection.BeginTransactionAsync(ct);
        foreach(var change in changes)
        {
            if(change.Entity is not ("project" or "project_rule" or "activity_type" or "activity_type_rule")) continue;
            if(change.Entity is not ("activity_type" or "activity_type_rule") && await HasPendingLocalChangeAsync(connection,(SqliteTransaction)tx,change.Entity,change.Id,ct))
            {
                await AgentLog.WarnAsync("sync.apply", "remote change skipped because a local pending change exists", new { entity = change.Entity, id = change.Id, version = change.Version }, correlationId);
                continue;
            }

            try
            {
                if(change.Entity=="project") await ApplyProjectAsync(connection,(SqliteTransaction)tx,change,ct);
                else if(change.Entity=="project_rule") await ApplyRuleAsync(connection,(SqliteTransaction)tx,change,ct);
                else if(change.Entity=="activity_type") await ApplyActivityTypeAsync(connection,(SqliteTransaction)tx,change,ct);
                else await ApplyActivityTypeRuleAsync(connection,(SqliteTransaction)tx,change,ct);
            }
            catch (Exception ex)
            {
                await AgentLog.ErrorAsync("sync.apply", "failed to apply remote change to SQLite", ex, new { entity = change.Entity, id = change.Id, version = change.Version }, correlationId);
                throw;
            }
        }
        await tx.CommitAsync(ct);
    }

    public async Task ApplyConflictResolutionsAsync(IEnumerable<ConflictResolution> resolutions, string? correlationId = null, CancellationToken ct=default)
    {
        await using var connection=database.OpenConnection(); await using var tx=await connection.BeginTransactionAsync(ct);
        foreach(var r in resolutions)
        {
            if(r.Resolution!="keep_server" || r.ServerPayload is null) continue;
            var payload=r.ServerPayload.Value; var updated=DateTimeOffset.UtcNow.ToString("O");
            try
            {
                if(r.Entity=="project") await ApplyProjectAsync(connection,(SqliteTransaction)tx,new RemoteChange("project",r.Id,r.ServerVersion,payload,updated),ct);
                else if(r.Entity=="project_rule") await ApplyRuleAsync(connection,(SqliteTransaction)tx,new RemoteChange("project_rule",r.Id,r.ServerVersion,payload,updated),ct);
                else if(r.Entity=="activity_session") await ApplyActivityAsync(connection,(SqliteTransaction)tx,r.Id,r.ServerVersion,payload,ct);
            }
            catch (Exception ex)
            {
                await AgentLog.ErrorAsync("sync.resolution", "failed to apply conflict resolution", ex, new { entity = r.Entity, id = r.Id, resolution = r.Resolution, server_version = r.ServerVersion }, correlationId);
                throw;
            }
        }
        await tx.CommitAsync(ct);
    }

    private static async Task ApplyActivityAsync(SqliteConnection c,SqliteTransaction tx,string id,int version,JsonElement p,CancellationToken ct)
    {
        await using var cmd=c.CreateCommand();cmd.Transaction=tx;cmd.CommandText="""
            UPDATE activity_sessions SET project_id=$project,task_id=$task,activity_type_id=$activity_type,activity_type_confidence=$activity_type_confidence,activity_type_source=$activity_type_source,activity_type_reason=$activity_type_reason,is_billable=$billable,
            source=$source,process_name=$process,executable_path=$path,window_title=$title,
            classification_confidence=$confidence,classification_reason=$reason,started_at=$started,ended_at=$ended,
            duration_seconds=$duration,idle_seconds=$idle,note=$note,version=$version,sync_state='synced',updated_at_device=$updated WHERE id=$id;
            """;
        cmd.Parameters.AddWithValue("$project",(object?)GetString(p,"project_id")??DBNull.Value);cmd.Parameters.AddWithValue("$task",(object?)GetString(p,"task_id")??DBNull.Value);
        cmd.Parameters.AddWithValue("$activity_type",(object?)GetString(p,"activity_type_id")??DBNull.Value);cmd.Parameters.AddWithValue("$activity_type_confidence",GetNullableDouble(p,"activity_type_confidence") is double typeConfidence?typeConfidence:DBNull.Value);cmd.Parameters.AddWithValue("$activity_type_source",(object?)GetString(p,"activity_type_source")??DBNull.Value);cmd.Parameters.AddWithValue("$activity_type_reason",(object?)GetString(p,"activity_type_reason")??DBNull.Value);cmd.Parameters.AddWithValue("$billable",GetNullableBool(p,"is_billable") is bool b?(b?1:0):DBNull.Value);
        cmd.Parameters.AddWithValue("$source",GetString(p,"source")??"manual_entry");cmd.Parameters.AddWithValue("$process",(object?)GetString(p,"process_name")??DBNull.Value);cmd.Parameters.AddWithValue("$path",(object?)GetString(p,"executable_path")??DBNull.Value);cmd.Parameters.AddWithValue("$title",(object?)GetString(p,"window_title")??DBNull.Value);
        cmd.Parameters.AddWithValue("$confidence",GetNullableDouble(p,"classification_confidence") is double confidence?confidence:DBNull.Value);cmd.Parameters.AddWithValue("$reason",(object?)GetString(p,"classification_reason")??DBNull.Value);cmd.Parameters.AddWithValue("$started",GetString(p,"started_at")??DateTimeOffset.UtcNow.ToString("O"));cmd.Parameters.AddWithValue("$ended",GetString(p,"ended_at")??DateTimeOffset.UtcNow.ToString("O"));cmd.Parameters.AddWithValue("$duration",GetInt(p,"duration_seconds",0));cmd.Parameters.AddWithValue("$idle",GetInt(p,"idle_seconds",0));cmd.Parameters.AddWithValue("$note",(object?)GetString(p,"note")??DBNull.Value);cmd.Parameters.AddWithValue("$version",version);cmd.Parameters.AddWithValue("$updated",GetString(p,"updated_at_device")??DateTimeOffset.UtcNow.ToString("O"));cmd.Parameters.AddWithValue("$id",id);await cmd.ExecuteNonQueryAsync(ct);
    }

    private static async Task<bool> HasPendingLocalChangeAsync(SqliteConnection c,SqliteTransaction tx,string entity,string id,CancellationToken ct)
    { await using var cmd=c.CreateCommand();cmd.Transaction=tx;cmd.CommandText="SELECT 1 FROM sync_outbox WHERE entity_type=$entity AND entity_id=$id LIMIT 1";cmd.Parameters.AddWithValue("$entity",entity);cmd.Parameters.AddWithValue("$id",id);return await cmd.ExecuteScalarAsync(ct) is not null; }

    private static async Task ApplyProjectAsync(SqliteConnection c,SqliteTransaction tx,RemoteChange change,CancellationToken ct)
    {
        var p=change.Payload; var name=GetString(p,"name")??"بدون نام"; var status=GetString(p,"status")??"active";
        await using var cmd=c.CreateCommand();cmd.Transaction=tx;cmd.CommandText="""
            INSERT INTO projects(id,name,code,parent_id,status,version,sync_state,updated_at,customer_id,rate_multiplier,is_billable_default,default_activity_type_id)
            VALUES($id,$name,$code,$parent,$status,$version,'synced',$updated,$customer,$multiplier,$billable,$default_activity_type)
            ON CONFLICT(id) DO UPDATE SET name=excluded.name,code=excluded.code,parent_id=excluded.parent_id,status=excluded.status,
            version=excluded.version,sync_state='synced',updated_at=excluded.updated_at,customer_id=excluded.customer_id,
            rate_multiplier=excluded.rate_multiplier,is_billable_default=excluded.is_billable_default,default_activity_type_id=excluded.default_activity_type_id
            WHERE excluded.version >= projects.version;
            """;
        cmd.Parameters.AddWithValue("$id",change.Id);cmd.Parameters.AddWithValue("$name",name);cmd.Parameters.AddWithValue("$code",(object?)GetString(p,"code")??DBNull.Value);cmd.Parameters.AddWithValue("$parent",(object?)GetString(p,"parent_id")??DBNull.Value);cmd.Parameters.AddWithValue("$status",status);cmd.Parameters.AddWithValue("$version",change.Version);cmd.Parameters.AddWithValue("$updated",change.UpdatedAt);cmd.Parameters.AddWithValue("$customer",(object?)GetString(p,"customer_id")??DBNull.Value);cmd.Parameters.AddWithValue("$multiplier",GetDouble(p,"rate_multiplier",1.0));cmd.Parameters.AddWithValue("$billable",GetBool(p,"is_billable_default",true)?1:0);cmd.Parameters.AddWithValue("$default_activity_type",(object?)GetString(p,"default_activity_type_id")??DBNull.Value);await cmd.ExecuteNonQueryAsync(ct);
    }

    private static async Task ApplyRuleAsync(SqliteConnection c,SqliteTransaction tx,RemoteChange change,CancellationToken ct)
    {
        var p=change.Payload;var projectId=GetString(p,"project_id");if(string.IsNullOrWhiteSpace(projectId))return;
        await using var cmd=c.CreateCommand();cmd.Transaction=tx;cmd.CommandText="""
            INSERT INTO project_rules(id,project_id,rule_type,operator,pattern,weight,priority,is_enabled,version,sync_state,updated_at)
            VALUES($id,$project,$type,$operator,$pattern,$weight,$priority,$enabled,$version,'synced',$updated)
            ON CONFLICT(id) DO UPDATE SET project_id=excluded.project_id,rule_type=excluded.rule_type,operator=excluded.operator,pattern=excluded.pattern,
            weight=excluded.weight,priority=excluded.priority,is_enabled=excluded.is_enabled,version=excluded.version,sync_state='synced',updated_at=excluded.updated_at
            WHERE excluded.version >= project_rules.version;
            """;
        cmd.Parameters.AddWithValue("$id",change.Id);cmd.Parameters.AddWithValue("$project",projectId);cmd.Parameters.AddWithValue("$type",GetString(p,"rule_type")??"WindowTitle");cmd.Parameters.AddWithValue("$operator",GetString(p,"operator")??"contains");cmd.Parameters.AddWithValue("$pattern",GetString(p,"pattern")??"");cmd.Parameters.AddWithValue("$weight",GetInt(p,"weight",50));cmd.Parameters.AddWithValue("$priority",GetInt(p,"priority",0));cmd.Parameters.AddWithValue("$enabled",GetBool(p,"is_enabled",true)?1:0);cmd.Parameters.AddWithValue("$version",change.Version);cmd.Parameters.AddWithValue("$updated",change.UpdatedAt);await cmd.ExecuteNonQueryAsync(ct);
    }

    private static async Task ApplyActivityTypeRuleAsync(SqliteConnection c, SqliteTransaction tx, RemoteChange change, CancellationToken ct)
    {
        var p = change.Payload;
        var activityTypeId = GetString(p, "activity_type_id");
        if (string.IsNullOrWhiteSpace(activityTypeId)) return;
        await using var cmd = c.CreateCommand();
        cmd.Transaction = tx;
        cmd.CommandText = """
            INSERT INTO activity_type_rules(id,project_id,activity_type_id,rule_type,operator,pattern,weight,priority,confidence,is_enabled,version,updated_at)
            VALUES($id,$project,$activity_type,$type,$operator,$pattern,$weight,$priority,$confidence,$enabled,$version,$updated)
            ON CONFLICT(id) DO UPDATE SET project_id=excluded.project_id,activity_type_id=excluded.activity_type_id,rule_type=excluded.rule_type,
            operator=excluded.operator,pattern=excluded.pattern,weight=excluded.weight,priority=excluded.priority,confidence=excluded.confidence,
            is_enabled=excluded.is_enabled,version=excluded.version,updated_at=excluded.updated_at
            WHERE excluded.version >= activity_type_rules.version;
            """;
        cmd.Parameters.AddWithValue("$id", change.Id);
        cmd.Parameters.AddWithValue("$project", (object?)GetString(p, "project_id") ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$activity_type", activityTypeId);
        cmd.Parameters.AddWithValue("$type", GetString(p, "rule_type") ?? "WindowTitle");
        cmd.Parameters.AddWithValue("$operator", GetString(p, "operator") ?? "contains");
        cmd.Parameters.AddWithValue("$pattern", GetString(p, "pattern") ?? "");
        cmd.Parameters.AddWithValue("$weight", GetInt(p, "weight", 80));
        cmd.Parameters.AddWithValue("$priority", GetInt(p, "priority", 0));
        cmd.Parameters.AddWithValue("$confidence", GetDouble(p, "confidence", 0.9));
        cmd.Parameters.AddWithValue("$enabled", GetBool(p, "is_enabled", true) ? 1 : 0);
        cmd.Parameters.AddWithValue("$version", change.Version);
        cmd.Parameters.AddWithValue("$updated", change.UpdatedAt);
        await cmd.ExecuteNonQueryAsync(ct);
    }

    private static async Task ApplyActivityTypeAsync(SqliteConnection c,SqliteTransaction tx,RemoteChange change,CancellationToken ct)
    {
        var p=change.Payload;
        await using var cmd=c.CreateCommand();cmd.Transaction=tx;cmd.CommandText="""
            INSERT INTO activity_types(id,code,name,is_billable_default,base_hourly_rate_minor,currency,is_active,sort_order,version,updated_at)
            VALUES($id,$code,$name,$billable,$rate,$currency,$active,$sort,$version,$updated)
            ON CONFLICT(id) DO UPDATE SET code=excluded.code,name=excluded.name,is_billable_default=excluded.is_billable_default,
            base_hourly_rate_minor=excluded.base_hourly_rate_minor,currency=excluded.currency,is_active=excluded.is_active,
            sort_order=excluded.sort_order,version=excluded.version,updated_at=excluded.updated_at
            WHERE excluded.version >= activity_types.version;
            """;
        cmd.Parameters.AddWithValue("$id",change.Id);cmd.Parameters.AddWithValue("$code",GetString(p,"code")??change.Id);cmd.Parameters.AddWithValue("$name",GetString(p,"name")??"فعالیت");cmd.Parameters.AddWithValue("$billable",GetBool(p,"is_billable_default",true)?1:0);cmd.Parameters.AddWithValue("$rate",GetLong(p,"base_hourly_rate_minor",0));cmd.Parameters.AddWithValue("$currency",GetString(p,"currency")??"IRT");cmd.Parameters.AddWithValue("$active",GetBool(p,"is_active",true)?1:0);cmd.Parameters.AddWithValue("$sort",GetInt(p,"sort_order",0));cmd.Parameters.AddWithValue("$version",change.Version);cmd.Parameters.AddWithValue("$updated",change.UpdatedAt);await cmd.ExecuteNonQueryAsync(ct);
    }

    private static string? GetString(JsonElement e,string name)=>e.TryGetProperty(name,out var v)&&v.ValueKind!=JsonValueKind.Null?v.GetString():null;
    private static int GetInt(JsonElement e,string name,int fallback)=>e.TryGetProperty(name,out var v)&&v.TryGetInt32(out var n)?n:fallback;
    private static long GetLong(JsonElement e,string name,long fallback)=>e.TryGetProperty(name,out var v)&&v.TryGetInt64(out var n)?n:fallback;
    private static double GetDouble(JsonElement e,string name,double fallback)=>e.TryGetProperty(name,out var v)&&v.TryGetDouble(out var n)?n:fallback;
    private static bool GetBool(JsonElement e,string name,bool fallback)=>e.TryGetProperty(name,out var v)&&v.ValueKind is JsonValueKind.True or JsonValueKind.False?v.GetBoolean():fallback;
    private static bool? GetNullableBool(JsonElement e,string name)=>e.TryGetProperty(name,out var v)&&v.ValueKind is JsonValueKind.True or JsonValueKind.False?v.GetBoolean():null;
    private static double? GetNullableDouble(JsonElement e,string name)=>e.TryGetProperty(name,out var v)&&v.ValueKind==JsonValueKind.Number&&v.TryGetDouble(out var n)?n:null;
}
