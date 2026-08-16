using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.ViewModels;

public sealed class WorkEventRow
{
    public WorkEventRow(WorkEvent workEvent, string? projectName = null, IReadOnlyDictionary<string, string>? activityTypeNames = null)
    {
        Event = workEvent;
        Project = projectName ?? workEvent.ProjectId ?? "تشخیص‌داده‌نشده";

        var typeIds = workEvent.Sessions.Select(x => x.ActivityTypeId).Where(x => !string.IsNullOrWhiteSpace(x)).Distinct(StringComparer.OrdinalIgnoreCase).ToList();
        ActivityType = typeIds.Count switch
        {
            0 => "—",
            1 when activityTypeNames is not null && activityTypeNames.TryGetValue(typeIds[0]!, out var name) => name,
            1 => typeIds[0]!,
            _ => "ترکیبی"
        };

        var applications = workEvent.Sessions
            .Select(x => string.IsNullOrWhiteSpace(x.ProcessName) ? "ثبت دستی" : x.ProcessName!)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .Take(3)
            .ToList();
        Application = applications.Count == 0 ? "—" : string.Join(" + ", applications);

        var contexts = workEvent.Sessions
            .Select(ActivityContextNormalizer.Describe)
            .Select(x => x.DisplayName)
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .Take(3)
            .ToList();
        Title = contexts.Count == 0 ? (workEvent.Sessions.FirstOrDefault()?.Note ?? "—") : string.Join(" · ", contexts);
    }

    public WorkEvent Event { get; }
    public string Id => Event.Id;
    public string? ProjectId => Event.ProjectId;
    public IReadOnlyList<ActivitySession> Sessions => Event.Sessions;
    public ActivitySession PrimarySession => Event.Sessions[0];
    public string Time => $"{Event.StartedAt.ToLocalTime():HH:mm} - {Event.EndedAt.ToLocalTime():HH:mm}";
    public string Application { get; }
    public string Project { get; }
    public string ActivityType { get; }
    public string Title { get; }
    public string Duration => Format(Event.CreditedSeconds);
    public string Source => Event.BridgeSeconds > 0
        ? $"رویداد · Bridge {Format(Event.BridgeSeconds)}"
        : Event.Sessions.Count > 1 ? $"رویداد · {Event.Sessions.Count} بخش" : SourceName(Event.Sessions[0].Source);
    public string Detail => Event.BridgeSeconds > 0
        ? $"مستقیم {Format(Event.DirectSeconds)} + تداوم {Format(Event.BridgeSeconds)}"
        : Event.Sessions.Count > 1 ? $"{Event.Sessions.Count} بخش تجمیع‌شده" : "یک بخش";

    private static string Format(int seconds)
        => TimeSpan.FromSeconds(Math.Max(0, seconds)).ToString(seconds >= 3600 ? @"hh\:mm\:ss" : @"mm\:ss");

    private static string SourceName(ActivitySource source) => source switch
    {
        ActivitySource.AutoForeground => "خودکار",
        ActivitySource.ManualTimer => "تایمر دستی",
        ActivitySource.ManualEntry => "ثبت دستی",
        _ => "اصلاح بیکاری"
    };
}
