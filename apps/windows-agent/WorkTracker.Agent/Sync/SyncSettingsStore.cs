using System.Security.Cryptography;
using System.Text;
using Microsoft.Data.Sqlite;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Sync;

public sealed class SyncSettingsStore(LocalDatabase database)
{
    private const string ApiUrlKey = "sync_api_url";
    private const string TokenKey = "sync_access_token_dpapi";
    private const string CursorKey = "sync_cursor";
    private const string LastSuccessKey = "sync_last_success";

    public async Task<SyncSettings> LoadAsync(CancellationToken ct = default)
    {
        var values = await ReadManyAsync([ApiUrlKey, TokenKey, CursorKey, LastSuccessKey], ct);
        var url = values.GetValueOrDefault(ApiUrlKey) ?? "";
        var token = Unprotect(values.GetValueOrDefault(TokenKey));
        DateTimeOffset? last = DateTimeOffset.TryParse(values.GetValueOrDefault(LastSuccessKey), out var parsed) ? parsed : null;
        return new SyncSettings(url, token, last, values.GetValueOrDefault(CursorKey));
    }

    public async Task SaveConnectionAsync(string apiBaseUrl, string? accessToken, CancellationToken ct = default)
    {
        apiBaseUrl = (apiBaseUrl ?? "").Trim().TrimEnd('/');
        if (apiBaseUrl.EndsWith("/api/v1", StringComparison.OrdinalIgnoreCase)) apiBaseUrl = apiBaseUrl[..^7].TrimEnd('/');
        if (!string.IsNullOrWhiteSpace(apiBaseUrl) && !Uri.TryCreate(apiBaseUrl, UriKind.Absolute, out _))
            throw new ArgumentException("آدرس API معتبر نیست.");

        await SetAsync(ApiUrlKey, apiBaseUrl, ct);
        if (!string.IsNullOrWhiteSpace(accessToken)) await SetAsync(TokenKey, Protect(accessToken.Trim()), ct);
    }

    public async Task SaveCheckpointAsync(string cursor, DateTimeOffset successAt, CancellationToken ct = default)
    {
        await SetAsync(CursorKey, cursor, ct);
        await SetAsync(LastSuccessKey, successAt.ToString("O"), ct);
    }

    public Task ClearTokenAsync(CancellationToken ct = default) => SetAsync(TokenKey, null, ct);

    private async Task<Dictionary<string,string?>> ReadManyAsync(string[] keys, CancellationToken ct)
    {
        var result = keys.ToDictionary(x => x, _ => (string?)null);
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = $"SELECT key,value FROM device_state WHERE key IN ({string.Join(',', keys.Select((_,i)=>'$'+"k"+i))})";
        for (var i=0;i<keys.Length;i++) cmd.Parameters.AddWithValue("$k"+i, keys[i]);
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct)) result[reader.GetString(0)] = reader.IsDBNull(1) ? null : reader.GetString(1);
        return result;
    }

    private async Task SetAsync(string key, string? value, CancellationToken ct)
    {
        await using var connection = database.OpenConnection();
        await using var cmd = connection.CreateCommand();
        cmd.CommandText = "INSERT INTO device_state(key,value) VALUES($key,$value) ON CONFLICT(key) DO UPDATE SET value=excluded.value";
        cmd.Parameters.AddWithValue("$key", key);
        cmd.Parameters.AddWithValue("$value", (object?)value ?? DBNull.Value);
        await cmd.ExecuteNonQueryAsync(ct);
    }

    private static string Protect(string value)
    {
        var bytes = Encoding.UTF8.GetBytes(value);
        return Convert.ToBase64String(ProtectedData.Protect(bytes, null, DataProtectionScope.CurrentUser));
    }

    private static string? Unprotect(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        try
        {
            var protectedBytes = Convert.FromBase64String(value);
            return Encoding.UTF8.GetString(ProtectedData.Unprotect(protectedBytes, null, DataProtectionScope.CurrentUser));
        }
        catch { return null; }
    }
}
