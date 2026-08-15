using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Services;

public sealed record ActiveManualTimer(string Id, DateTimeOffset StartedAt, string? ProjectId, string? TaskId, string? ActivityTypeId, bool? IsBillable, string? Note)
{
    public override string ToString() => $"{StartedAt.ToLocalTime():HH:mm} · {Note ?? "فعالیت دستی"}";
}

public sealed class ManualTimerService(ActivitySessionRepository repository, string userId, string deviceId)
{
    private readonly Dictionary<string, ActiveManualTimer> _active = new(StringComparer.Ordinal); private readonly object _gate = new();
    public IReadOnlyList<ActiveManualTimer> ActiveTimers { get { lock (_gate) return _active.Values.OrderBy(x => x.StartedAt).ToList(); } }
    public ActiveManualTimer Start(string? projectId, string? taskId, string? activityTypeId, bool? isBillable, string? note) { var timer = new ActiveManualTimer(Guid.NewGuid().ToString(), DateTimeOffset.UtcNow, projectId, taskId, activityTypeId, isBillable, note); lock (_gate) _active.Add(timer.Id, timer); return timer; }
    public async Task<ActivitySession?> StopAsync(string timerId, CancellationToken cancellationToken = default)
    {
        ActiveManualTimer? timer; lock (_gate) { if (!_active.Remove(timerId, out timer)) return null; }
        var end = DateTimeOffset.UtcNow; var duration = Math.Max(1, (int)(end - timer.StartedAt).TotalSeconds);
        var session = new ActivitySession(Guid.NewGuid().ToString(), userId, deviceId, timer.ProjectId, timer.TaskId, ActivitySource.ManualTimer, null, null, "Manual timer", 1, "manual", timer.StartedAt, end, duration, 0, timer.Note, timer.ActivityTypeId, timer.IsBillable);
        await repository.AddAsync(session, cancellationToken); return session;
    }
    public async Task<IReadOnlyList<ActivitySession>> StopAllAsync(CancellationToken cancellationToken = default) { var ids = ActiveTimers.Select(x => x.Id).ToList(); var result = new List<ActivitySession>(); foreach (var id in ids) { var session = await StopAsync(id, cancellationToken); if (session is not null) result.Add(session); } return result; }
}
