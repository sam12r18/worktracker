using System.ComponentModel;
using System.Windows;
using System.Windows.Input;
using System.Windows.Threading;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Services;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;
using WorkTracker.Agent.ViewModels;

namespace WorkTracker.Agent;

public partial class ProjectPulseWidget : Window
{
    private readonly ActivitySessionRepository _repository;
    private readonly ProjectRepository _projects;
    private readonly TrackingEngine _tracking;
    private readonly DispatcherTimer _timer;
    private IReadOnlyList<ActivitySession> _persistedSessions = [];
    private IReadOnlyDictionary<string, string> _projectNames = new Dictionary<string, string>();
    private volatile bool _dataDirty = true;
    private DateTimeOffset _lastDataReload = DateTimeOffset.MinValue;
    private bool _refreshing;
    private bool _allowClose;

    public ProjectPulseWidget(ActivitySessionRepository repository, ProjectRepository projects, TrackingEngine tracking)
    {
        InitializeComponent();
        _repository = repository;
        _projects = projects;
        _tracking = tracking;

        _tracking.SessionSaved += (_, _) => _dataDirty = true;
        _tracking.LiveActivityChanged += (_, _) => Dispatcher.InvokeAsync(RefreshAsync);

        _timer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(1) };
        _timer.Tick += async (_, _) => await RefreshAsync();
        _timer.Start();

        Loaded += async (_, _) =>
        {
            DockToRight();
            await RefreshAsync();
        };
    }

    public void ShowAndActivate()
    {
        if (!IsVisible) Show();
        if (WindowState == WindowState.Minimized) WindowState = WindowState.Normal;
        Activate();
    }

    public void CloseForExit()
    {
        _allowClose = true;
        _timer.Stop();
        Close();
    }

    private async Task RefreshAsync()
    {
        if (_refreshing) return;
        _refreshing = true;
        try
        {
            if (_dataDirty || DateTimeOffset.UtcNow - _lastDataReload >= TimeSpan.FromSeconds(15))
            {
                var dayStartLocal = new DateTimeOffset(DateTime.Now.Date, TimeZoneInfo.Local.GetUtcOffset(DateTime.Now.Date));
                var dayStartUtc = dayStartLocal.ToUniversalTime();
                var dayEndUtc = dayStartLocal.AddDays(1).ToUniversalTime();
                _persistedSessions = (await _repository.GetForLocalDayAsync(DateTime.Now.Date))
                    .Select(x => ClipToRange(x, dayStartUtc, dayEndUtc))
                    .Where(x => x is not null)
                    .Select(x => x!)
                    .ToList();
                _projectNames = (await _projects.GetActiveAsync()).ToDictionary(x => x.Id, x => x.Name);
                _dataDirty = false;
                _lastDataReload = DateTimeOffset.UtcNow;
            }

            var now = DateTimeOffset.UtcNow;
            var sessions = _persistedSessions.ToList();
            var live = _tracking.CreateProvisionalSession(now);
            if (live is not null) sessions.Add(live);

            var aggregation = WorkEventAggregationService.AggregateWithDiagnostics(sessions);
            var projectEvents = aggregation.Events.Where(x => !string.IsNullOrWhiteSpace(x.ProjectId)).ToList();
            var currentProjectId = _tracking.LiveActivity?.ProjectId;

            var rows = projectEvents
                .GroupBy(x => x.ProjectId!, StringComparer.OrdinalIgnoreCase)
                .Select(group =>
                {
                    var ordered = group.OrderByDescending(x => x.EndedAt).ToList();
                    var latest = ordered[0];
                    var credited = group.Sum(x => x.CreditedSeconds);
                    var direct = group.Sum(x => x.DirectSeconds);
                    var bridge = group.Sum(x => x.BridgeSeconds);
                    var application = latest.Sessions
                        .OrderByDescending(x => x.EndedAt)
                        .Select(x => x.ProcessName)
                        .FirstOrDefault(x => !string.IsNullOrWhiteSpace(x)) ?? "ثبت دستی";
                    var active = !string.IsNullOrWhiteSpace(currentProjectId) && string.Equals(group.Key, currentProjectId, StringComparison.OrdinalIgnoreCase);
                    var state = active ? "در حال کار" : bridge > 0 ? $"Bridge {FormatShort(bridge)}" : "اخیراً";
                    return new
                    {
                        Row = new ProjectPulseRow(
                            group.Key,
                            _projectNames.TryGetValue(group.Key, out var name) ? name : group.Key,
                            FormatLong(credited),
                            FormatShort(direct),
                            FormatShort(bridge),
                            application,
                            state,
                            active),
                        Latest = latest.EndedAt,
                        Active = active
                    };
                })
                .OrderByDescending(x => x.Active)
                .ThenByDescending(x => x.Latest)
                .Take(3)
                .Select(x => x.Row)
                .ToList();

            PulseRowsList.ItemsSource = rows;
            WidgetStateText.Text = rows.Count == 0
                ? "هنوز پروژه‌ای برای امروز ثبت نشده"
                : "سه پروژه آخر · زمان اعتباری امروز";

            var dayStart = new DateTimeOffset(DateTime.Now.Date, TimeZoneInfo.Local.GetUtcOffset(DateTime.Now.Date));
            var summary = TimeAccountingService.Summarize(
                sessions,
                dayStart.ToUniversalTime(),
                dayStart.AddDays(1).ToUniversalTime(),
                WorkEventAggregationService.TotalBridgeSeconds(aggregation.Events));
            PulseEffortText.Text = FormatLong(summary.EffortSeconds);
            PulseCoverageText.Text = FormatLong(summary.ElapsedCoverageSeconds);
            PulseConcurrentText.Text = FormatLong(summary.ConcurrentEffortSeconds);
        }
        catch (Exception ex)
        {
            WidgetStateText.Text = "خطا در بروزرسانی ویجت";
            await AgentLog.ErrorAsync("widget.pulse", "Project Pulse refresh failed", ex);
        }
        finally
        {
            _refreshing = false;
        }
    }

    private void DockToRight()
    {
        var workArea = SystemParameters.WorkArea;
        Left = Math.Max(workArea.Left, workArea.Right - Width - 12);
        Top = Math.Max(workArea.Top, workArea.Top + 90);
    }

    private void Window_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
    {
        if (e.ButtonState == MouseButtonState.Pressed) DragMove();
    }

    private void HideButton_Click(object sender, RoutedEventArgs e) => Hide();

    private void Window_Closing(object? sender, CancelEventArgs e)
    {
        if (_allowClose) return;
        e.Cancel = true;
        Hide();
    }

    private static ActivitySession? ClipToRange(ActivitySession session, DateTimeOffset rangeStart, DateTimeOffset rangeEnd)
    {
        var start = session.StartedAt < rangeStart ? rangeStart : session.StartedAt;
        var end = session.EndedAt > rangeEnd ? rangeEnd : session.EndedAt;
        if (end <= start) return null;
        return session with
        {
            StartedAt = start,
            EndedAt = end,
            DurationSeconds = Math.Max(0, (int)Math.Floor((end - start).TotalSeconds))
        };
    }

    private static string FormatLong(int seconds)
        => TimeSpan.FromSeconds(Math.Max(0, seconds)).ToString(@"hh\:mm\:ss");

    private static string FormatShort(int seconds)
        => TimeSpan.FromSeconds(Math.Max(0, seconds)).ToString(seconds >= 3600 ? @"hh\:mm\:ss" : @"mm\:ss");
}
