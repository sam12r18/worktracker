using Microsoft.Data.Sqlite;

namespace WorkTracker.Agent.Storage;

public sealed class DeviceIdentityStore(LocalDatabase database)
{
    public async Task<string> GetOrCreateDeviceIdAsync(CancellationToken cancellationToken = default)
    {
        const string key = "device_id";
        await using var connection = database.OpenConnection();
        await using var read = connection.CreateCommand();
        read.CommandText = "SELECT value FROM device_state WHERE key = $key LIMIT 1";
        read.Parameters.AddWithValue("$key", key);
        var existing = await read.ExecuteScalarAsync(cancellationToken) as string;
        if (!string.IsNullOrWhiteSpace(existing)) return existing;

        var id = Guid.NewGuid().ToString();
        await using var write = connection.CreateCommand();
        write.CommandText = "INSERT INTO device_state(key, value) VALUES($key, $value)";
        write.Parameters.AddWithValue("$key", key);
        write.Parameters.AddWithValue("$value", id);
        await write.ExecuteNonQueryAsync(cancellationToken);
        return id;
    }

    public async Task<string> GetOrCreateLocalUserIdAsync(CancellationToken cancellationToken = default)
    {
        const string key = "local_user_id";
        await using var connection = database.OpenConnection();
        await using var read = connection.CreateCommand();
        read.CommandText = "SELECT value FROM device_state WHERE key = $key LIMIT 1";
        read.Parameters.AddWithValue("$key", key);
        var existing = await read.ExecuteScalarAsync(cancellationToken) as string;
        if (!string.IsNullOrWhiteSpace(existing)) return existing;

        var id = $"local:{Environment.UserName}";
        await using var write = connection.CreateCommand();
        write.CommandText = "INSERT INTO device_state(key, value) VALUES($key, $value)";
        write.Parameters.AddWithValue("$key", key);
        write.Parameters.AddWithValue("$value", id);
        await write.ExecuteNonQueryAsync(cancellationToken);
        return id;
    }
}
