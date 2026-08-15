using WorkTracker.Agent.Domain;

namespace WorkTracker.Agent.Storage;

public sealed class ActivityTypeRepository(LocalDatabase database)
{
    public async Task<IReadOnlyList<ActivityType>> GetActiveAsync(CancellationToken ct = default)
    {
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = """
            SELECT id,code,name,is_billable_default,base_hourly_rate_minor,currency,is_active,sort_order
            FROM activity_types WHERE is_active=1 ORDER BY sort_order,name COLLATE NOCASE;
            """;
        var rows = new List<ActivityType>();
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
            rows.Add(new ActivityType(reader.GetString(0), reader.GetString(1), reader.GetString(2), reader.GetInt32(3)==1, reader.GetInt64(4), reader.GetString(5), reader.GetInt32(6)==1, reader.GetInt32(7)));
        return rows;
    }
}
