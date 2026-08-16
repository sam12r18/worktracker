using WorkTracker.Agent.Domain;

namespace WorkTracker.Agent.Storage;

public sealed class ActivityTypeRuleRepository(LocalDatabase database)
{
    public async Task<IReadOnlyList<ActivityTypeRule>> GetEnabledAsync(CancellationToken ct = default)
    {
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = """
            SELECT id,project_id,activity_type_id,rule_type,operator,pattern,weight,priority,confidence,is_enabled,version
            FROM activity_type_rules
            WHERE is_enabled=1
            ORDER BY priority DESC,weight DESC,id;
            """;
        var rows = new List<ActivityTypeRule>();
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            if (!Enum.TryParse<ActivityTypeRuleType>(reader.GetString(3), true, out var type)) continue;
            rows.Add(new ActivityTypeRule(
                reader.GetString(0),
                reader.IsDBNull(1) ? null : reader.GetString(1),
                reader.GetString(2),
                type,
                reader.IsDBNull(4) ? "contains" : reader.GetString(4),
                reader.GetString(5),
                reader.GetInt32(6),
                reader.GetInt32(7),
                reader.GetDouble(8),
                reader.GetInt32(9) == 1,
                reader.GetInt32(10)));
        }
        return rows;
    }
}
