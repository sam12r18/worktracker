using System.IO;
using System.Text.Json;
using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Integrations.Ide;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Tracking;

public sealed class TrackingEngine : IAsyncDisposable
{
    private readonly IForegroundWindowObserver _foreground;
    private readonly IIdleTimeProvider _idle;
    private readonly ActivitySessionRepository _repository;
    private readonly ProjectClassificationService _classification;
    private readonly ActivityTypeInferenceService _activityTypeInference;
    private readonly IdeContextBridgeService _ideContextBridge;
    private readonly string _userId;
    private readonly string _deviceId;
    private readonly TimeSpan _pollInterval = TimeSpan.FromSeconds(2);
    private readonly TimeSpan _idleThreshold = TimeSpan.FromMinutes(5);
    private CancellationTokenSource? _cts;
    private Task? _loop;
    private ForegroundSnapshot? _current;
    private DateTimeOffset _currentStartedAt;
    private bool _paused;
    private LiveActivitySnapshot? _liveActivity;

    public TrackingEngine(IForegroundWindowObserver foreground, IIdleTimeProvider idle, ActivitySessionRepository repository, ProjectClassificationService classification, ActivityTypeInferenceService activityTypeInference, IdeContextBridgeService ideContextBridge, string userId, string deviceId)
    { _foreground=foreground; _idle=idle; _repository=repository; _classification=classification; _activityTypeInference=activityTypeInference; _ideContextBridge=ideContextBridge; _userId=userId; _deviceId=deviceId; }

    public TrackingState State { get; private set; } = TrackingState.Paused;
    public event EventHandler? StateChanged;
    public event EventHandler<ActivitySession>? SessionSaved;
    public event EventHandler<ForegroundSnapshot?>? ForegroundChanged;
    public event EventHandler<LiveActivitySnapshot?>? LiveActivityChanged;

    public LiveActivitySnapshot? LiveActivity => _liveActivity;

    public ActivitySession? CreateProvisionalSession(DateTimeOffset now)
    {
        var live = _liveActivity;
        if (live is null) return null;
        if (now < live.StartedAt) now = live.StartedAt;
        var duration = Math.Max(0, (int)Math.Floor((now - live.StartedAt).TotalSeconds));
        if (duration <= 0) return null;
        return new ActivitySession(
            $"live:{_deviceId}", _userId, _deviceId, live.ProjectId, null, ActivitySource.AutoForeground,
            live.Snapshot.ProcessName, live.Snapshot.ExecutablePath, live.Snapshot.WindowTitle,
            live.ClassificationConfidence, live.ClassificationReason, live.StartedAt, now, duration, 0, null,
            live.ActivityTypeId, null, 1, "live", live.ActivityTypeConfidence, live.ActivityTypeReason, live.ActivityTypeSource, SerializeIdeContext(live.Snapshot));
    }

    public void Start(){ if(_loop is not null)return; _cts=new CancellationTokenSource(); _paused=false; State=TrackingState.Tracking; RaiseState(); _loop=RunAsync(_cts.Token); }
    public async Task PauseAsync(){ if(_paused)return; _paused=true; await FlushCurrentAsync(DateTimeOffset.UtcNow,CancellationToken.None); State=TrackingState.Paused; RaiseState(); }
    public void Resume(){ if(!_paused)return; _paused=false; _current=null; _liveActivity=null; State=TrackingState.Tracking; RaiseState(); }

    private async Task RunAsync(CancellationToken ct)
    {
        using var timer=new PeriodicTimer(_pollInterval);
        while(await timer.WaitForNextTickAsync(ct))
        {
            if(_paused)continue;
            var now=DateTimeOffset.UtcNow; var idleTime=_idle.GetIdleTime();
            if(idleTime>=_idleThreshold){ var lastInputAt=now-idleTime; await FlushCurrentAsync(lastInputAt,ct); if(State!=TrackingState.Idle){State=TrackingState.Idle;RaiseState();} continue; }
            if(State!=TrackingState.Tracking){State=TrackingState.Tracking;RaiseState();}
            var snapshot=_foreground.Capture();
            if(snapshot is null || string.Equals(snapshot.ProcessName, Environment.ProcessPath is null?null:Path.GetFileNameWithoutExtension(Environment.ProcessPath), StringComparison.OrdinalIgnoreCase)){await FlushCurrentAsync(now,ct);continue;}
            snapshot = await _ideContextBridge.EnrichAsync(snapshot, ct);
            if(_current is null){await SetCurrentAsync(snapshot,now,ct);continue;}
            if(!SameContext(_current,snapshot)){await FlushCurrentAsync(now,ct);await SetCurrentAsync(snapshot,now,ct);}
            else
            {
                _current = snapshot;
                if (_liveActivity is not null) _liveActivity = _liveActivity with { Snapshot = snapshot };
                ForegroundChanged?.Invoke(this, snapshot);
                LiveActivityChanged?.Invoke(this, _liveActivity);
            }
        }
    }

    private async Task SetCurrentAsync(ForegroundSnapshot snapshot, DateTimeOffset now, CancellationToken ct)
    {
        var resolution = await _classification.ResolveAsync(snapshot, ct);
        var activityType = await _activityTypeInference.ResolveAsync(snapshot, resolution?.ProjectId, ct);
        var reasons = new List<string>();
        if (resolution is not null) reasons.AddRange(resolution.Reasons); else reasons.Add("unclassified");
        if (activityType is not null) reasons.Add($"activity_type:{activityType.Reason}");

        _current = snapshot;
        _currentStartedAt = now;
        _liveActivity = new LiveActivitySnapshot(
            snapshot,
            resolution?.ProjectId,
            activityType?.ActivityTypeId,
            resolution?.Confidence,
            string.Join("; ", reasons),
            now,
            activityType?.Confidence,
            activityType?.Reason,
            activityType?.Source);
        ForegroundChanged?.Invoke(this, snapshot);
        LiveActivityChanged?.Invoke(this, _liveActivity);
    }

    private async Task FlushCurrentAsync(DateTimeOffset end,CancellationToken ct)
    {
        if(_current is null)return;
        var start=_currentStartedAt;
        if(end<start)end=start;
        var duration=(int)Math.Floor((end-start).TotalSeconds);
        var snapshot=_current;
        var live=_liveActivity;
        _current=null;
        _liveActivity=null;
        ForegroundChanged?.Invoke(this,null);
        LiveActivityChanged?.Invoke(this,null);
        if(duration<2)return;
        var session=new ActivitySession(Guid.NewGuid().ToString(),_userId,_deviceId,live?.ProjectId,null,ActivitySource.AutoForeground,
            snapshot.ProcessName,snapshot.ExecutablePath,snapshot.WindowTitle,live?.ClassificationConfidence,live?.ClassificationReason,start,end,duration,0,null,live?.ActivityTypeId,null,1,"pending",live?.ActivityTypeConfidence,live?.ActivityTypeReason,live?.ActivityTypeSource,SerializeIdeContext(snapshot));
        await _repository.AddAsync(session,ct); SessionSaved?.Invoke(this,session);
    }

    private static string? SerializeIdeContext(ForegroundSnapshot snapshot)
        => snapshot.IdeContext is null ? null : JsonSerializer.Serialize(snapshot.IdeContext);

    private static bool SameContext(ForegroundSnapshot a, ForegroundSnapshot b)
    {
        if (!string.Equals(a.ProcessName, b.ProcessName, StringComparison.OrdinalIgnoreCase) ||
            !string.Equals(a.ExecutablePath, b.ExecutablePath, StringComparison.OrdinalIgnoreCase))
            return false;

        if (a.IdeContext is { } ai && b.IdeContext is { } bi)
        {
            var aProject = !string.IsNullOrWhiteSpace(ai.ProjectPath) ? ai.ProjectPath : ai.ProjectName;
            var bProject = !string.IsNullOrWhiteSpace(bi.ProjectPath) ? bi.ProjectPath : bi.ProjectName;
            if (!string.IsNullOrWhiteSpace(aProject) &&
                string.Equals(aProject, bProject, StringComparison.OrdinalIgnoreCase))
            {
                return string.Equals(ai.Mode, bi.Mode, StringComparison.OrdinalIgnoreCase) &&
                       string.Equals(ai.RunConfiguration ?? string.Empty, bi.RunConfiguration ?? string.Empty, StringComparison.OrdinalIgnoreCase);
            }
        }

        return string.Equals(a.WindowTitle, b.WindowTitle, StringComparison.Ordinal);
    }
    private void RaiseState()=>StateChanged?.Invoke(this,EventArgs.Empty);
    public async ValueTask DisposeAsync(){if(_cts is null)return;_cts.Cancel();try{if(_loop is not null)await _loop;}catch(OperationCanceledException){}await FlushCurrentAsync(DateTimeOffset.UtcNow,CancellationToken.None);_cts.Dispose();}
}
