using WorkTracker.Agent.Domain;

namespace WorkTracker.Agent.ViewModels;

public sealed class ActivityRow
{
    public ActivityRow(ActivitySession session, string? projectName = null, string? activityTypeName = null)
    { Session=session; Project=projectName ?? session.ProjectId ?? "تشخیص‌داده‌نشده"; ActivityType=activityTypeName ?? (session.ActivityTypeId is null ? "—" : session.ActivityTypeId); }
    public ActivitySession Session { get; }
    public string Id => Session.Id;
    public string Time => $"{Session.StartedAt.ToLocalTime():HH:mm} - {Session.EndedAt.ToLocalTime():HH:mm}";
    public string Application => string.IsNullOrWhiteSpace(Session.ProcessName) ? "ثبت دستی" : Session.ProcessName;
    public string Project { get; }
    public string ActivityType { get; }
    public string Title => Session.WindowTitle ?? Session.Note ?? "-";
    public string Duration => TimeSpan.FromSeconds(Session.DurationSeconds).ToString(Session.DurationSeconds >= 3600 ? @"hh\:mm\:ss" : @"mm\:ss");
    public string Source => Session.Source switch { ActivitySource.AutoForeground => "خودکار", ActivitySource.ManualTimer => "تایمر دستی", ActivitySource.ManualEntry => "ثبت دستی", _ => "اصلاح بیکاری" };
}
