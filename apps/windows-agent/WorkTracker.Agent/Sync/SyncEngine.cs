using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Sync;

public sealed class SyncEngine : IAsyncDisposable
{
    private readonly SyncSettingsStore _settings;
    private readonly SyncOutboxRepository _outbox;
    private readonly RemoteChangeApplier _applier;
    private readonly ApiSyncClient _client;
    private readonly ActivitySessionRepository _activities;
    private readonly string _deviceId;
    private readonly SemaphoreSlim _gate = new(1, 1);
    private CancellationTokenSource? _cts;
    private Task? _loop;

    public SyncStatus Status { get; private set; } = SyncStatus.Disabled();
    public event EventHandler<SyncStatus>? StatusChanged;

    public SyncEngine(
        SyncSettingsStore settings,
        SyncOutboxRepository outbox,
        RemoteChangeApplier applier,
        ApiSyncClient client,
        ActivitySessionRepository activities,
        string deviceId)
    {
        _settings = settings;
        _outbox = outbox;
        _applier = applier;
        _client = client;
        _activities = activities;
        _deviceId = deviceId;
    }

    public void Start()
    {
        if (_cts is not null) return;
        _cts = new CancellationTokenSource();
        _loop = LoopAsync(_cts.Token);
    }

    public async Task TriggerAsync(CancellationToken ct = default)
    {
        await _gate.WaitAsync(ct);
        var correlationId = Guid.NewGuid().ToString("N");

        try
        {
            var settings = await _settings.LoadAsync(ct);
            var queueBefore = await _outbox.GetQueueDiagnosticsAsync(ct);
            var conflictCount = await _outbox.CountConflictsAsync(ct);

            await AgentLog.InfoAsync("sync", "sync cycle started", new
            {
                device_id = _deviceId,
                configured = settings.IsConfigured,
                api = settings.ApiBaseUrl,
                has_cursor = !string.IsNullOrWhiteSpace(settings.Cursor),
                queue = QueueLog(queueBefore),
                conflicts = conflictCount,
            }, correlationId);

            if (!settings.IsConfigured)
            {
                Set(SyncStatus.Disabled($"Sync پیکربندی نشده · {queueBefore.Total} مورد در صف"));
                await AgentLog.WarnAsync("sync", "sync skipped because connection is not configured", correlationId: correlationId);
                return;
            }

            Set(new SyncStatus("syncing", "در حال همگام‌سازی…", settings.LastSuccessfulSyncAt, queueBefore.Total, conflictCount));

            var items = await _outbox.GetDueAsync(200, ct);
            var ackIds = await _outbox.GetPendingResolutionAckIdsAsync(ct);
            var batchByEntity = items.GroupBy(x => x.EntityType).ToDictionary(x => x.Key, x => x.Count());

            await AgentLog.InfoAsync("sync", "outbox batch prepared", new
            {
                due_selected = items.Count,
                batch_by_entity = batchByEntity,
                acknowledged_conflicts = ackIds.Count,
                queue = QueueLog(queueBefore),
            }, correlationId);

            try
            {
                await _client.RegisterDeviceAsync(settings, _deviceId, correlationId, ct);
                var result = await _client.SyncAsync(settings, _deviceId, items, ackIds, correlationId, ct);

                await AgentLog.InfoAsync("sync", "server sync response parsed", new
                {
                    accepted = result.Accepted.Count,
                    conflicts = result.Conflicts.Count,
                    resolutions = result.Resolutions.Count,
                    remote_changes = result.RemoteChanges.Count,
                    remote_by_entity = result.RemoteChanges.GroupBy(x => x.Entity).ToDictionary(x => x.Key, x => x.Count()),
                }, correlationId);

                if (ackIds.Count > 0)
                    await _outbox.MarkResolutionAcksSentAsync(ackIds, ct);

                await _outbox.MarkAcceptedAsync(result.Accepted, ct);
                await _outbox.MarkConflictsAsync(result.Conflicts, ct);
                await _applier.ApplyAsync(result.RemoteChanges, correlationId, ct);
                await _applier.ApplyConflictResolutionsAsync(result.Resolutions, correlationId, ct);
                await _outbox.ApplyResolutionsAsync(result.Resolutions, ct);

                var now = DateTimeOffset.UtcNow;
                await _settings.SaveCheckpointAsync(result.ServerCursor, now, ct);

                var queueAfter = await _outbox.GetQueueDiagnosticsAsync(ct);
                conflictCount = await _outbox.CountConflictsAsync(ct);
                var message = (queueAfter.Total == 0 ? "همگام‌سازی کامل" : "همگام‌سازی انجام شد؛ مواردی در صف باقی مانده")
                    + $" · ارسال {result.Accepted.Count} · دریافت {result.RemoteChanges.Count}";
                Set(new SyncStatus("ok", message, now, queueAfter.Total, conflictCount));

                await AgentLog.InfoAsync("sync", "sync cycle completed", new
                {
                    accepted = result.Accepted.Count,
                    pulled = result.RemoteChanges.Count,
                    conflicts = conflictCount,
                    queue = QueueLog(queueAfter),
                    next_cursor_saved = true,
                }, correlationId);
            }
            catch (Exception ex)
            {
                if (items.Count > 0)
                    await _outbox.MarkFailedAsync(items, ex.Message, ct);

                var queueAfterFailure = await _outbox.GetQueueDiagnosticsAsync(ct);
                conflictCount = await _outbox.CountConflictsAsync(ct);
                Set(new SyncStatus("error", ex.Message, settings.LastSuccessfulSyncAt, queueAfterFailure.Total, conflictCount));

                await AgentLog.ErrorAsync("sync", "sync cycle failed", ex, new
                {
                    attempted_items = items.Count,
                    attempted_by_entity = batchByEntity,
                    queue = QueueLog(queueAfterFailure),
                    conflicts = conflictCount,
                }, correlationId);
            }
        }
        finally
        {
            _gate.Release();
        }
    }

    private async Task LoopAsync(CancellationToken ct)
    {
        while (!ct.IsCancellationRequested)
        {
            try
            {
                await TriggerAsync(ct);
                await Task.Delay(TimeSpan.FromSeconds(60), ct);
            }
            catch (OperationCanceledException) when (ct.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                await AgentLog.ErrorAsync("sync.loop", "background sync loop failed outside the normal sync handler", ex);
                await Task.Delay(TimeSpan.FromSeconds(30), ct);
            }
        }
    }

    private static object QueueLog(SyncQueueDiagnostics q) => new
    {
        total = q.Total,
        due = q.Due,
        delayed = q.Delayed,
        failed = q.Failed,
        max_attempts = q.MaxAttempts,
        next_retry_at = q.NextRetryAt?.ToString("O"),
        last_error = q.LastError,
        last_error_entity = q.LastErrorEntity,
        last_error_entity_id = q.LastErrorEntityId,
    };

    private void Set(SyncStatus status)
    {
        Status = status;
        StatusChanged?.Invoke(this, status);
    }

    public async ValueTask DisposeAsync()
    {
        if (_cts is null) return;
        _cts.Cancel();
        if (_loop is not null)
        {
            try { await _loop; }
            catch (OperationCanceledException) { }
        }
        _cts.Dispose();
        _gate.Dispose();
    }
}
