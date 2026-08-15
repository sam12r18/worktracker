using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using WorkTracker.Agent.Infrastructure;

namespace WorkTracker.Agent.Sync;

public sealed class ApiSyncClient(HttpClient http)
{
    private static readonly JsonSerializerOptions JsonOptions=new(JsonSerializerDefaults.Web){PropertyNameCaseInsensitive=true};

    public async Task RegisterDeviceAsync(SyncSettings settings,string deviceId,CancellationToken ct=default)
    {
        var payload=new{id=deviceId,name=Environment.MachineName,platform="windows",app_version=BuildInfo.Version};
        using var request=CreateRequest(settings,HttpMethod.Post,"devices");request.Content=JsonContent.Create(payload,options:JsonOptions);
        using var response=await http.SendAsync(request,ct);await EnsureSuccessAsync(response,ct);
    }

    public async Task<SyncResponse> SyncAsync(SyncSettings settings,string deviceId,IReadOnlyList<OutboxItem> items,IReadOnlyList<string> acknowledgedConflictIds,CancellationToken ct=default)
    {
        var changes=items.Select(x=>new{entity=x.EntityType,id=x.EntityId,operation=x.Operation,version=x.Version,payload=JsonSerializer.Deserialize<JsonElement>(x.PayloadJson,JsonOptions)}).ToArray();
        var payload=new{device_id=deviceId,cursor=settings.Cursor,changes,pull_limit=500,acknowledged_conflict_ids=acknowledgedConflictIds};
        using var request=CreateRequest(settings,HttpMethod.Post,"sync");request.Content=JsonContent.Create(payload,options:JsonOptions);
        using var response=await http.SendAsync(request,ct);await EnsureSuccessAsync(response,ct);
        var body=await response.Content.ReadFromJsonAsync<SyncResponse>(JsonOptions,ct);return body??throw new InvalidOperationException("پاسخ Sync خالی است.");
    }

    private static HttpRequestMessage CreateRequest(SyncSettings settings,HttpMethod method,string path)
    {
        var baseUrl=settings.ApiBaseUrl.TrimEnd('/');var uri=new Uri($"{baseUrl}/api/v1/{path}");var request=new HttpRequestMessage(method,uri);request.Headers.Authorization=new AuthenticationHeaderValue("Bearer",settings.AccessToken);request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));return request;
    }

    private static async Task EnsureSuccessAsync(HttpResponseMessage response,CancellationToken ct)
    {
        if(response.IsSuccessStatusCode)return;var body=await response.Content.ReadAsStringAsync(ct);throw new HttpRequestException($"HTTP {(int)response.StatusCode}: {(body.Length>500?body[..500]:body)}");
    }
}
