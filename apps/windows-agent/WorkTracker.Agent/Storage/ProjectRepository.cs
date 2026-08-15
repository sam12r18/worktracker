using Microsoft.Data.Sqlite;
using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Domain;

namespace WorkTracker.Agent.Storage;

public sealed class ProjectRepository(LocalDatabase database)
{
    public async Task<IReadOnlyList<Project>> GetActiveAsync(CancellationToken cancellationToken = default)
    {
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = """
            SELECT id,name,code,parent_id,status,updated_at,customer_id,rate_multiplier,is_billable_default
            FROM projects WHERE status='active' ORDER BY name COLLATE NOCASE
            """;
        var result = new List<Project>();
        await using var reader = await cmd.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken))
            result.Add(new Project(reader.GetString(0), reader.GetString(1), reader.IsDBNull(2)?null:reader.GetString(2), reader.IsDBNull(3)?null:reader.GetString(3), reader.GetString(4), DateTimeOffset.Parse(reader.GetString(5)), reader.IsDBNull(6)?null:reader.GetString(6), Convert.ToDecimal(reader.GetDouble(7)), reader.GetInt32(8)==1));
        return result;
    }

    public async Task<Project> CreateAsync(string name, string? code = null, string? parentId = null, CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrWhiteSpace(name)) throw new ArgumentException("نام پروژه الزامی است.", nameof(name));
        var project = new Project(Guid.NewGuid().ToString(), name.Trim(), string.IsNullOrWhiteSpace(code)?null:code.Trim(), parentId, "active", DateTimeOffset.UtcNow);
        await using var connection = database.OpenConnection();
        await using var tx = await connection.BeginTransactionAsync(cancellationToken);
        await using var cmd = connection.CreateCommand();
        cmd.Transaction = (SqliteTransaction)tx;
        cmd.CommandText = """
            INSERT INTO projects(id,name,code,parent_id,status,updated_at,customer_id,rate_multiplier,is_billable_default)
            VALUES($id,$name,$code,$parent,'active',$updated,NULL,1.0,1)
            """;
        cmd.Parameters.AddWithValue("$id", project.Id); cmd.Parameters.AddWithValue("$name", project.Name); cmd.Parameters.AddWithValue("$code", (object?)project.Code ?? DBNull.Value); cmd.Parameters.AddWithValue("$parent", (object?)project.ParentId ?? DBNull.Value); cmd.Parameters.AddWithValue("$updated", project.UpdatedAt!.Value.ToString("O"));
        await cmd.ExecuteNonQueryAsync(cancellationToken);
        await InsertOutboxAsync(connection, (SqliteTransaction)tx, "project", project.Id, new { id=project.Id, name=project.Name, code=project.Code, parent_id=project.ParentId, status=project.Status, customer_id=(string?)null, rate_multiplier=1.0, is_billable_default=true, version=1 }, cancellationToken);
        await tx.CommitAsync(cancellationToken); return project;
    }

    public async Task<IReadOnlyList<ProjectRule>> GetRulesAsync(CancellationToken cancellationToken = default)
    {
        await using var connection = database.OpenConnection(); await using var cmd = connection.CreateCommand(); cmd.CommandText = "SELECT id,project_id,rule_type,operator,pattern,weight,priority,is_enabled FROM project_rules ORDER BY priority DESC, weight DESC";
        var result = new List<ProjectRule>(); await using var reader = await cmd.ExecuteReaderAsync(cancellationToken);
        while (await reader.ReadAsync(cancellationToken)) { if (!Enum.TryParse<ProjectRuleType>(reader.GetString(2), true, out var type)) continue; result.Add(new ProjectRule(reader.GetString(0),reader.GetString(1),type,reader.IsDBNull(3)?"contains":reader.GetString(3),reader.GetString(4),reader.GetInt32(5),reader.GetInt32(6),reader.GetInt32(7)==1)); }
        return result;
    }

    public async Task<ProjectRule> AddRuleAsync(string projectId, ProjectRuleType type, string pattern, int weight = 80, int priority = 0, CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrWhiteSpace(pattern)) throw new ArgumentException("الگوی Rule خالی است.", nameof(pattern)); pattern = pattern.Trim();
        await using (var lc = database.OpenConnection()) await using (var lookup = lc.CreateCommand()) { lookup.CommandText = "SELECT id,project_id,rule_type,operator,pattern,weight,priority,is_enabled FROM project_rules WHERE project_id=$project AND rule_type=$type AND pattern=$pattern LIMIT 1"; lookup.Parameters.AddWithValue("$project", projectId); lookup.Parameters.AddWithValue("$type", type.ToString()); lookup.Parameters.AddWithValue("$pattern", pattern); await using var reader = await lookup.ExecuteReaderAsync(cancellationToken); if (await reader.ReadAsync(cancellationToken)) return new ProjectRule(reader.GetString(0),reader.GetString(1),type,reader.IsDBNull(3)?"contains":reader.GetString(3),reader.GetString(4),reader.GetInt32(5),reader.GetInt32(6),reader.GetInt32(7)==1); }
        var rule = new ProjectRule(Guid.NewGuid().ToString(), projectId, type, "contains", pattern, Math.Clamp(weight,1,200), priority, true);
        await using var connection = database.OpenConnection(); await using var tx = await connection.BeginTransactionAsync(cancellationToken); await using var cmd = connection.CreateCommand(); cmd.Transaction=(SqliteTransaction)tx;
        cmd.CommandText = "INSERT INTO project_rules(id,project_id,rule_type,operator,pattern,weight,priority,is_enabled,updated_at) VALUES($id,$project,$type,$operator,$pattern,$weight,$priority,1,$updated)";
        cmd.Parameters.AddWithValue("$id",rule.Id); cmd.Parameters.AddWithValue("$project",rule.ProjectId); cmd.Parameters.AddWithValue("$type",rule.Type.ToString()); cmd.Parameters.AddWithValue("$operator",rule.Operator); cmd.Parameters.AddWithValue("$pattern",rule.Pattern); cmd.Parameters.AddWithValue("$weight",rule.Weight); cmd.Parameters.AddWithValue("$priority",rule.Priority); cmd.Parameters.AddWithValue("$updated",DateTimeOffset.UtcNow.ToString("O")); await cmd.ExecuteNonQueryAsync(cancellationToken);
        await InsertOutboxAsync(connection,(SqliteTransaction)tx,"project_rule",rule.Id,new { id=rule.Id, project_id=rule.ProjectId, rule_type=rule.Type.ToString(), operator=rule.Operator, pattern=rule.Pattern, weight=rule.Weight, priority=rule.Priority, is_enabled=true, version=1 },cancellationToken); await tx.CommitAsync(cancellationToken); return rule;
    }

    private static async Task InsertOutboxAsync(SqliteConnection connection, SqliteTransaction tx, string entity, string id, object payload, CancellationToken ct)
    {
        await using var outbox=connection.CreateCommand(); outbox.Transaction=tx; outbox.CommandText="INSERT INTO sync_outbox(id,entity_type,entity_id,operation,payload_json,created_at) VALUES($id,$entity,$entity_id,'upsert',$payload,$created)";
        outbox.Parameters.AddWithValue("$id",Guid.NewGuid().ToString("N")); outbox.Parameters.AddWithValue("$entity",entity); outbox.Parameters.AddWithValue("$entity_id",id); outbox.Parameters.AddWithValue("$payload",System.Text.Json.JsonSerializer.Serialize(payload)); outbox.Parameters.AddWithValue("$created",DateTimeOffset.UtcNow.ToString("O")); await outbox.ExecuteNonQueryAsync(ct);
    }
}
