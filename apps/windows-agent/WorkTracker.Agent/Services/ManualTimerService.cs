using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;

namespace WorkTracker.Agent.Services;

public sealed record ActiveManualTimer(
    string Id,
    DateTimeOffset StartedAt,
    string? ProjectId,
    string? TaskId,
    string? ActivityTypeId,
    bool? IsBillable,
    string? Note,
    string Kind = "manual",
    string? Label = null)
{
    public override string ToString() => $"{StartedAt.ToLocalTime():HH:mm} · {Label ?? Note ?? "فعالیت دستی"}";
}

public sealed class ManualTimerService(ActivitySessionRepository repository, string userId, string deviceId)
{
    public const string PhoneCallKind = "phone_call";

    private readonly Dictionary<string, ActiveManualTimer> _active = new(StringComparer.Ordinal);
    private readonly object _gate = new();

    public event EventHandler? TimersChanged;
    public event EventHandler<ActivitySession>? SessionSaved;

    public IReadOnlyList<ActiveManualTimer> ActiveTimers
    {
        get
        {
            lock (_gate)
                return _active.Values.OrderBy(x => x.StartedAt).ToList();
        }
    }

    public ActiveManualTimer Start(
        string? projectId,
        string? taskId,
        string? activityTypeId,
        bool? isBillable,
        string? note,
        string kind = "manual",
        string? label = null)
    {
        var timer = new ActiveManualTimer(
            Guid.NewGuid().ToString(),
            DateTimeOffset.UtcNow,
            projectId,
            taskId,
            activityTypeId,
            isBillable,
            note,
            kind,
            label);

        lock (_gate)
            _active.Add(timer.Id, timer);

        TimersChanged?.Invoke(this, EventArgs.Empty);
        return timer;
    }

    public ActiveManualTimer StartPhoneCall(string projectId, string? activityTypeId = null)
    {
        if (string.IsNullOrWhiteSpace(projectId))
            throw new ArgumentException("برای شروع تماس، پروژه باید مشخص باشد.", nameof(projectId));

        lock (_gate)
        {
            if (_active.Values.Any(x => string.Equals(x.Kind, PhoneCallKind, StringComparison.OrdinalIgnoreCase)))
                throw new InvalidOperationException("یک تماس تلفنی در حال ثبت است. ابتدا تماس فعلی را پایان دهید.");
        }

        return Start(
            projectId,
            taskId: null,
            activityTypeId,
            isBillable: null,
            note: "تماس تلفنی",
            kind: PhoneCallKind,
            label: "تماس تلفنی");
    }

    public ActiveManualTimer? GetActivePhoneCall()
        => ActiveTimers.FirstOrDefault(x => string.Equals(x.Kind, PhoneCallKind, StringComparison.OrdinalIgnoreCase));

    public IReadOnlyList<ActivitySession> CreateProvisionalSessions(DateTimeOffset now)
    {
        return ActiveTimers
            .Select(timer => CreateProvisionalSession(timer, now))
            .Where(session => session is not null)
            .Select(session => session!)
            .ToList();
    }

    public async Task<ActivitySession?> StopAsync(string timerId, CancellationToken cancellationToken = default)
    {
        ActiveManualTimer? timer;
        lock (_gate)
        {
            if (!_active.Remove(timerId, out timer)) return null;
        }

        TimersChanged?.Invoke(this, EventArgs.Empty);

        var end = DateTimeOffset.UtcNow;
        var duration = Math.Max(1, (int)(end - timer.StartedAt).TotalSeconds);
        var session = new ActivitySession(
            Guid.NewGuid().ToString(),
            userId,
            deviceId,
            timer.ProjectId,
            timer.TaskId,
            ActivitySource.ManualTimer,
            null,
            null,
            timer.Label ?? "Manual timer",
            1,
            timer.Kind,
            timer.StartedAt,
            end,
            duration,
            0,
            timer.Note,
            timer.ActivityTypeId,
            timer.IsBillable);

        await repository.AddAsync(session, cancellationToken);
        SessionSaved?.Invoke(this, session);
        return session;
    }

    public async Task<IReadOnlyList<ActivitySession>> StopAllAsync(CancellationToken cancellationToken = default)
    {
        var ids = ActiveTimers.Select(x => x.Id).ToList();
        var result = new List<ActivitySession>();
        foreach (var id in ids)
        {
            var session = await StopAsync(id, cancellationToken);
            if (session is not null) result.Add(session);
        }
        return result;
    }

    private ActivitySession? CreateProvisionalSession(ActiveManualTimer timer, DateTimeOffset now)
    {
        if (now < timer.StartedAt) now = timer.StartedAt;
        var duration = Math.Max(0, (int)Math.Floor((now - timer.StartedAt).TotalSeconds));
        if (duration <= 0) return null;

        return new ActivitySession(
            $"live-manual:{timer.Id}",
            userId,
            deviceId,
            timer.ProjectId,
            timer.TaskId,
            ActivitySource.ManualTimer,
            null,
            null,
            timer.Label ?? "Manual timer",
            1,
            timer.Kind,
            timer.StartedAt,
            now,
            duration,
            0,
            timer.Note,
            timer.ActivityTypeId,
            timer.IsBillable,
            1,
            "live");
    }
}
