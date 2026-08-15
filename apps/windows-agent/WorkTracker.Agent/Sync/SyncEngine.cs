using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Sync;

public sealed class SyncEngine : IAsyncDisposable
{
    private readonly SyncSettingsStore _settings;private readonly SyncOutboxRepository _outbox;private readonly RemoteChangeApplier _applier;private readonly ApiSyncClient _client;private readonly ActivitySessionRepository _activities;private readonly string _deviceId;private readonly SemaphoreSlim _gate=new(1,1);private CancellationTokenSource? _cts;private Task? _loop;
    public SyncStatus Status { get; private set; }=SyncStatus.Disabled();
    public event EventHandler<SyncStatus>? StatusChanged;
    public SyncEngine(SyncSettingsStore settings,SyncOutboxRepository outbox,RemoteChangeApplier applier,ApiSyncClient client,ActivitySessionRepository activities,string deviceId){_settings=settings;_outbox=outbox;_applier=applier;_client=client;_activities=activities;_deviceId=deviceId;}
    public void Start(){if(_cts is not null)return;_cts=new CancellationTokenSource();_loop=LoopAsync(_cts.Token);}
    public async Task TriggerAsync(CancellationToken ct=default)
    {
        if(!await _gate.WaitAsync(0,ct))return;
        try
        {
            var settings=await _settings.LoadAsync(ct);var pending=await _activities.CountPendingSyncAsync(ct);var conflictCount=await _outbox.CountConflictsAsync(ct);
            if(!settings.IsConfigured){Set(SyncStatus.Disabled($"Sync پیکربندی نشده · {pending} مورد در صف"));return;}
            Set(new("syncing","در حال همگام‌سازی…",settings.LastSuccessfulSyncAt,pending,conflictCount));
            var items=await _outbox.GetDueAsync(200,ct);var ackIds=await _outbox.GetPendingResolutionAckIdsAsync(ct);
            try
            {
                await _client.RegisterDeviceAsync(settings,_deviceId,ct);
                var result=await _client.SyncAsync(settings,_deviceId,items,ackIds,ct);
                if(ackIds.Count>0)await _outbox.MarkResolutionAcksSentAsync(ackIds,ct);
                await _outbox.MarkAcceptedAsync(result.Accepted,ct);await _outbox.MarkConflictsAsync(result.Conflicts,ct);
                await _applier.ApplyAsync(result.RemoteChanges,ct);await _applier.ApplyConflictResolutionsAsync(result.Resolutions,ct);await _outbox.ApplyResolutionsAsync(result.Resolutions,ct);
                var now=DateTimeOffset.UtcNow;await _settings.SaveCheckpointAsync(result.ServerCursor,now,ct);
                pending=await _activities.CountPendingSyncAsync(ct);conflictCount=await _outbox.CountConflictsAsync(ct);Set(new("ok",pending==0?"همگام‌سازی کامل":"همگام‌سازی انجام شد؛ مواردی در صف باقی مانده",now,pending,conflictCount));
            }
            catch(Exception ex)
            {
                if(items.Count>0)await _outbox.MarkFailedAsync(items,ex.Message,ct);Set(new("error",ex.Message,settings.LastSuccessfulSyncAt,await _activities.CountPendingSyncAsync(ct),await _outbox.CountConflictsAsync(ct)));
            }
        }
        finally{_gate.Release();}
    }
    private async Task LoopAsync(CancellationToken ct){while(!ct.IsCancellationRequested){try{await TriggerAsync(ct);await Task.Delay(TimeSpan.FromSeconds(60),ct);}catch(OperationCanceledException)when(ct.IsCancellationRequested){break;}catch{await Task.Delay(TimeSpan.FromSeconds(30),ct);}}}
    private void Set(SyncStatus status){Status=status;StatusChanged?.Invoke(this,status);}
    public async ValueTask DisposeAsync(){if(_cts is null)return;_cts.Cancel();if(_loop is not null)try{await _loop;}catch(OperationCanceledException){} _cts.Dispose();_gate.Dispose();}
}
