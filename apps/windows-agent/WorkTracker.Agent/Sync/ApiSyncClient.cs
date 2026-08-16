using System.Diagnostics;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Infrastructure;

namespace WorkTracker.Agent.Sync;

public sealed class ApiSyncClient(HttpClient http)
{
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web)
    {
        PropertyNameCaseInsensitive = true
    };

    public async Task RegisterDeviceAsync(SyncSettings settings, string deviceId, string correlationId, CancellationToken ct = default)
    {
        var payload = new { id = deviceId, name = Environment.MachineName, platform = "windows", app_version = BuildInfo.Version };
        using var request = CreateRequest(settings, HttpMethod.Post, "devices", correlationId);
        request.Content = JsonContent.Create(payload, options: JsonOptions);
        using var response = await SendAsync(request, "device.register", correlationId, ct);
        await EnsureSuccessAsync(response, "device.register", correlationId, ct);
    }

    public async Task<SyncResponse> SyncAsync(
        SyncSettings settings,
        string deviceId,
        IReadOnlyList<OutboxItem> items,
        IReadOnlyList<string> acknowledgedConflictIds,
        string correlationId,
        CancellationToken ct = default)
    {
        var changes = items.Select(x => new
        {
            entity = x.EntityType,
            id = x.EntityId,
            operation = x.Operation,
            version = x.Version,
            payload = JsonSerializer.Deserialize<JsonElement>(x.PayloadJson, JsonOptions)
        }).ToArray();

        var payload = new
        {
            device_id = deviceId,
            cursor = settings.Cursor,
            changes,
            pull_limit = 500,
            acknowledged_conflict_ids = acknowledgedConflictIds
        };

        using var request = CreateRequest(settings, HttpMethod.Post, "sync", correlationId);
        request.Content = JsonContent.Create(payload, options: JsonOptions);
        using var response = await SendAsync(request, "sync", correlationId, ct);
        await EnsureSuccessAsync(response, "sync", correlationId, ct);

        var body = await response.Content.ReadFromJsonAsync<SyncResponse>(JsonOptions, ct)
            ?? throw new InvalidOperationException("پاسخ Sync خالی است.");

        if (body.RemoteChanges is null)
            throw new InvalidOperationException("پاسخ Sync فاقد remote_changes است. نسخه API و Agent را بررسی کنید.");
        if (string.IsNullOrWhiteSpace(body.ServerCursor))
            throw new InvalidOperationException("پاسخ Sync فاقد server_cursor است. Checkpoint ذخیره نشد.");

        return body;
    }

    private static HttpRequestMessage CreateRequest(SyncSettings settings, HttpMethod method, string path, string correlationId)
    {
        var baseUrl = settings.ApiBaseUrl.TrimEnd('/');
        var uri = new Uri($"{baseUrl}/api/v1/{path}");
        var request = new HttpRequestMessage(method, uri);
        request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", settings.AccessToken);
        request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        request.Headers.TryAddWithoutValidation("X-WorkTracker-Correlation-ID", correlationId);
        return request;
    }

    private async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, string operation, string correlationId, CancellationToken ct)
    {
        var sw = Stopwatch.StartNew();
        try
        {
            var response = await http.SendAsync(request, ct);
            sw.Stop();
            await AgentLog.InfoAsync("http", $"{operation} response", new
            {
                method = request.Method.Method,
                url = request.RequestUri?.ToString(),
                status = (int)response.StatusCode,
                elapsed_ms = sw.ElapsedMilliseconds,
            }, correlationId);
            return response;
        }
        catch (Exception ex)
        {
            sw.Stop();
            await AgentLog.ErrorAsync("http", $"{operation} request failed", ex, new
            {
                method = request.Method.Method,
                url = request.RequestUri?.ToString(),
                elapsed_ms = sw.ElapsedMilliseconds,
            }, correlationId);
            throw;
        }
    }

    private static async Task EnsureSuccessAsync(HttpResponseMessage response, string operation, string correlationId, CancellationToken ct)
    {
        if (response.IsSuccessStatusCode)
            return;

        var body = await response.Content.ReadAsStringAsync(ct);
        var trimmed = body.Length > 1500 ? body[..1500] : body;
        await AgentLog.WarnAsync("http", $"{operation} returned HTTP error", new
        {
            status = (int)response.StatusCode,
            reason = response.ReasonPhrase,
            body = trimmed,
        }, correlationId);
        throw new HttpRequestException($"HTTP {(int)response.StatusCode}: {trimmed}");
    }
}
