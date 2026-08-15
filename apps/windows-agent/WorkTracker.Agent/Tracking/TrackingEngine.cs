using System.IO;
using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Tracking;

public sealed class TrackingEngine : IAsyncDisposable
{
    private readonly IForegroundWindowObserver _foreground;
    private readonly IIdleTimeProvider _idle;
    private readonly ActivitySessionRepository _repository;
    private readonly ProjectClassificationService _classification;
    private readonly string _userId;
    private readonly string _deviceId;
    private readonly TimeSpan _pollInterval = TimeSpan.FromSeconds(2);
    private readonly TimeSpan _idleThreshold = TimeSpan.FromMinutes(5);
    private CancellationTokenSource? _cts;
    private Task? _loop;
    private ForegroundSnapshot? _current;
    private DateTimeOffset _currentStartedAt;
    private bool _paused;

    public TrackingEngine(IForegroundWindowObserver foreground, IIdleTimeProvider idle, ActivitySessionRepository repository, ProjectClassificationService classification, string userId, string deviceId)
    { _foreground=foreground; _idle=idle; _repository=repository; _classification=classification; _userId=userId; _deviceId=deviceId; }

    public TrackingState State { get; private set; } = TrackingState.Paused;
    public event EventHandler? StateChanged;
    public event EventHandler<ActivitySession>? SessionSaved;
    public event EventHandler<ForegroundSnapshot?>? ForegroundChanged;

    public void Start(){ if(_loop is not null)return; _cts=new CancellationTokenSource(); _paused=false; State=TrackingState.Tracking; RaiseState(); _loop=RunAsync(_cts.Token); }
    public async Task PauseAsync(){ if(_paused)return; _paused=true; await FlushCurrentAsync(DateTimeOffset.UtcNow,CancellationToken.None); State=TrackingState.Paused; RaiseState(); }
    public void Resume(){ if(!_paused)return; _paused=false; _current=null; State=TrackingState.Tracking; RaiseState(); }

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
            if(_current is null){SetCurrent(snapshot,now);continue;}
            if(!SameContext(_current,snapshot)){await FlushCurrentAsync(now,ct);SetCurrent(snapshot,now);}
        }
    }

    private void SetCurrent(ForegroundSnapshot snapshot, DateTimeOffset now){_current=snapshot;_currentStartedAt=now;ForegroundChanged?.Invoke(this,snapshot);}

    private async Task FlushCurrentAsync(DateTimeOffset end,CancellationToken ct)
    {
        if(_current is null)return; var start=_currentStartedAt; if(end<start)end=start; var duration=(int)Math.Floor((end-start).TotalSeconds); var snapshot=_current; _current=null; ForegroundChanged?.Invoke(this,null); if(duration<2)return;
        var resolution=await _classification.ResolveAsync(snapshot,ct);
        var session=new ActivitySession(Guid.NewGuid().ToString(),_userId,_deviceId,resolution?.ProjectId,null,ActivitySource.AutoForeground,
            snapshot.ProcessName,snapshot.ExecutablePath,snapshot.WindowTitle,resolution?.Confidence,resolution is null?"unclassified":string.Join("; ",resolution.Reasons),start,end,duration,0,null);
        await _repository.AddAsync(session,ct); SessionSaved?.Invoke(this,session);
    }

    private static bool SameContext(ForegroundSnapshot a,ForegroundSnapshot b)=>string.Equals(a.ProcessName,b.ProcessName,StringComparison.OrdinalIgnoreCase)&&string.Equals(a.ExecutablePath,b.ExecutablePath,StringComparison.OrdinalIgnoreCase)&&string.Equals(a.WindowTitle,b.WindowTitle,StringComparison.Ordinal);
    private void RaiseState()=>StateChanged?.Invoke(this,EventArgs.Empty);
    public async ValueTask DisposeAsync(){if(_cts is null)return;_cts.Cancel();try{if(_loop is not null)await _loop;}catch(OperationCanceledException){}await FlushCurrentAsync(DateTimeOffset.UtcNow,CancellationToken.None);_cts.Dispose();}
}
