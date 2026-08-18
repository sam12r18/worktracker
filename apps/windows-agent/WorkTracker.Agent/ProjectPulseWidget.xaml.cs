using MessageBox = System.Windows.MessageBox;
using System.ComponentModel;
using System.Windows;
using System.Windows.Input;
using System.Windows.Threading;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Services;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Sync;
using WorkTracker.Agent.Tracking;
using WorkTracker.Agent.ViewModels;

namespace WorkTracker.Agent;

public partial class ProjectPulseWidget : Window
{
    private readonly ActivitySessionRepository _repository;
    private readonly ProjectRepository _projects;
    private readonly ActivityTypeRepository _activityTypes;
    private readonly ManualTimerService _manualTimer;
    private readonly TrackingEngine _tracking;
    private readonly SyncEngine _sync;
    private readonly DispatcherTimer _timer;
    private IReadOnlyList<ActivitySession> _persistedSessions = [];
    private IReadOnlyDictionary<string, string> _projectNames = new Dictionary<string, string>();
    private volatile bool _dataDirty = true;
    private DateTimeOffset _lastDataReload = DateTimeOffset.MinValue;
    private bool _refreshing;
    private bool _allowClose;
    private bool _isCompact;
    private double _normalWidth = 330;
    private double _normalHeight = 330;

    public ProjectPulseWidget(
        ActivitySessionRepository repository,
        ProjectRepository projects,
        ActivityTypeRepository activityTypes,
        ManualTimerService manualTimer,
        TrackingEngine tracking,
        SyncEngine sync)
    {
        InitializeComponent();
        _repository = repository;
        _projects = projects;
        _activityTypes = activityTypes;
        _manualTimer = manualTimer;
        _tracking = tracking;
        _sync = sync;

        _tracking.SessionSaved += (_, _) => _dataDirty = true;
        _tracking.LiveActivityChanged += (_, _) => Dispatcher.InvokeAsync(RefreshAsync);
        _manualTimer.TimersChanged += (_, _) => Dispatcher.InvokeAsync(RefreshAsync);
        _manualTimer.SessionSaved += (_, _) =>
        {
            _dataDirty = true;
            Dispatcher.InvokeAsync(RefreshAsync);
        };

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
            sessions.AddRange(_manualTimer.CreateProvisionalSessions(now));

            var aggregation = WorkEventAggregationService.AggregateWithDiagnostics(sessions);
            var projectEvents = aggregation.Events.Where(x => !string.IsNullOrWhiteSpace(x.ProjectId)).ToList();
            var currentProjectId = _tracking.LiveActivity?.ProjectId;
            var activePhoneCall = _manualTimer.GetActivePhoneCall();

            var candidates = projectEvents
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
                    var active = !string.IsNullOrWhiteSpace(currentProjectId)
                        && string.Equals(group.Key, currentProjectId, StringComparison.OrdinalIgnoreCase);
                    var state = active ? "در حال کار" : bridge > 0 ? $"Bridge {FormatShort(bridge)}" : "اخیراً";
                    return new PulseCandidate(
                        new ProjectPulseRow(
                            group.Key,
                            ProjectName(group.Key),
                            FormatLong(credited),
                            FormatShort(direct),
                            FormatShort(bridge),
                            application,
                            state,
                            active),
                        latest.EndedAt,
                        active ? 20 : 0);
                })
                .ToList();

            if (activePhoneCall is not null && !string.IsNullOrWhiteSpace(activePhoneCall.ProjectId))
            {
                var phoneSeconds = Math.Max(0, (int)Math.Floor((now - activePhoneCall.StartedAt).TotalSeconds));
                candidates.Add(new PulseCandidate(
                    new ProjectPulseRow(
                        activePhoneCall.ProjectId,
                        $"☎ {ProjectName(activePhoneCall.ProjectId)}",
                        FormatLong(phoneSeconds),
                        FormatShort(phoneSeconds),
                        "00:00",
                        "تماس تلفنی",
                        "تماس جاری",
                        true),
                    now,
                    30));
            }

            var rows = candidates
                .OrderByDescending(x => x.Priority)
                .ThenByDescending(x => x.Latest)
                .Take(4)
                .Select(x => x.Row)
                .ToList();

            PulseRowsList.ItemsSource = rows;
            CompactPulseRowsList.ItemsSource = rows;
            UpdatePhoneButtons(currentProjectId, activePhoneCall);

            if (activePhoneCall is not null && !string.IsNullOrWhiteSpace(activePhoneCall.ProjectId))
            {
                var phoneSeconds = Math.Max(0, (int)Math.Floor((now - activePhoneCall.StartedAt).TotalSeconds));
                WidgetStateText.Text = $"تماس فعال · {ProjectName(activePhoneCall.ProjectId)} · {FormatLong(phoneSeconds)}";
            }
            else
            {
                WidgetStateText.Text = rows.Count == 0
                    ? "هنوز پروژه‌ای برای امروز ثبت نشده"
                    : "چهار مورد اخیر · زمان اعتباری امروز";
            }

            var dayStart = new DateTimeOffset(DateTime.Now.Date, TimeZoneInfo.Local.GetUtcOffset(DateTime.Now.Date));
            var summary = TimeAccountingService.Summarize(
                sessions,
                dayStart.ToUniversalTime(),
                dayStart.AddDays(1).ToUniversalTime(),
                WorkEventAggregationService.TotalBridgeSeconds(aggregation.Events));
            PulseEffortText.Text = FormatLong(summary.EffortSeconds);
            PulseCoverageText.Text = FormatLong(summary.ElapsedCoverageSeconds);
            PulseConcurrentText.Text = FormatLong(summary.ConcurrentEffortSeconds);
            CompactSummaryText.Text = $"E {FormatShort(summary.EffortSeconds)} · +{FormatShort(summary.ConcurrentEffortSeconds)}";
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

    private async void PhoneCallStartButton_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            if (_manualTimer.GetActivePhoneCall() is not null)
            {
                MessageBox.Show("یک تماس تلفنی در حال ثبت است. ابتدا تماس فعلی را پایان دهید.", "WorkTracker", MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }

            var projectId = _tracking.LiveActivity?.ProjectId;
            if (string.IsNullOrWhiteSpace(projectId))
            {
                MessageBox.Show("پروژه فعال مشخص نیست. ابتدا روی فعالیتی کار کنید که چراغ پروژه آن سبز باشد.", "WorkTracker", MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }

            var activityTypeId = await ResolvePhoneActivityTypeIdAsync();
            var timer = _manualTimer.StartPhoneCall(projectId, activityTypeId);
            await AgentLog.InfoAsync("manual.phone", "phone call timer started", new
            {
                timer_id = timer.Id,
                project_id = projectId,
                project = ProjectName(projectId),
                activity_type_id = activityTypeId,
                started_at = timer.StartedAt.ToString("O"),
            });
            await RefreshAsync();
        }
        catch (Exception ex)
        {
            await AgentLog.ErrorAsync("manual.phone", "phone call timer could not start", ex);
            MessageBox.Show(ex.Message, "WorkTracker", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
    }

    private async void PhoneCallStopButton_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            var timer = _manualTimer.GetActivePhoneCall();
            if (timer is null)
            {
                MessageBox.Show("تماس تلفنی فعالی برای پایان دادن وجود ندارد.", "WorkTracker", MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }

            var session = await _manualTimer.StopAsync(timer.Id);
            _dataDirty = true;
            if (session is not null)
            {
                await AgentLog.InfoAsync("manual.phone", "phone call timer stopped and persisted", new
                {
                    timer_id = timer.Id,
                    activity_session_id = session.Id,
                    project_id = session.ProjectId,
                    duration_seconds = session.DurationSeconds,
                    activity_type_id = session.ActivityTypeId,
                });
            }

            await RefreshAsync();
            _ = TriggerSyncSafelyAsync();
        }
        catch (Exception ex)
        {
            await AgentLog.ErrorAsync("manual.phone", "phone call timer could not stop", ex);
            MessageBox.Show(ex.Message, "WorkTracker", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
    }

    private async Task TriggerSyncSafelyAsync()
    {
        try
        {
            await _sync.TriggerAsync();
        }
        catch (Exception ex)
        {
            await AgentLog.ErrorAsync("manual.phone", "phone call persisted but immediate sync trigger failed", ex);
        }
    }

    private async Task<string?> ResolvePhoneActivityTypeIdAsync()
    {
        var types = await _activityTypes.GetActiveAsync();
        var preferredCodes = new HashSet<string>(StringComparer.OrdinalIgnoreCase)
        {
            "call-meeting",
            "phone_call", "phone-call", "phonecall", "call", "phone", "telephone"
        };

        var byCode = types.FirstOrDefault(x => preferredCodes.Contains(x.Code));
        if (byCode is not null) return byCode.Id;

        var byName = types.FirstOrDefault(x =>
            x.Name.Contains("تماس", StringComparison.OrdinalIgnoreCase)
            || x.Name.Contains("phone", StringComparison.OrdinalIgnoreCase)
            || x.Name.Contains("call", StringComparison.OrdinalIgnoreCase));
        return byName?.Id;
    }

    private void UpdatePhoneButtons(string? currentProjectId, ActiveManualTimer? activePhoneCall)
    {
        var hasCall = activePhoneCall is not null;
        PhoneCallStartButton.IsEnabled = !hasCall && !string.IsNullOrWhiteSpace(currentProjectId);
        PhoneCallStopButton.IsEnabled = hasCall;

        PhoneCallStartButton.ToolTip = hasCall
            ? "یک تماس در حال ثبت است"
            : string.IsNullOrWhiteSpace(currentProjectId)
                ? "برای شروع تماس، ابتدا یک پروژه فعال با چراغ سبز لازم است"
                : $"شروع تماس تلفنی برای {ProjectName(currentProjectId)}";

        PhoneCallStopButton.ToolTip = hasCall && !string.IsNullOrWhiteSpace(activePhoneCall?.ProjectId)
            ? $"پایان تماس {ProjectName(activePhoneCall.ProjectId)}"
            : "پایان تماس تلفنی";
    }

    private string ProjectName(string projectId)
        => _projectNames.TryGetValue(projectId, out var name) ? name : projectId;

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

    private void CompactModeButton_Click(object sender, RoutedEventArgs e)
    {
        var rightEdge = Left + ActualWidth;
        if (!_isCompact)
        {
            _normalWidth = Math.Max(300, ActualWidth);
            _normalHeight = Math.Max(290, ActualHeight);
        }

        _isCompact = !_isCompact;
        ApplyLayoutMode();

        Dispatcher.BeginInvoke(() =>
        {
            var workArea = SystemParameters.WorkArea;
            Left = Math.Max(workArea.Left, Math.Min(workArea.Right - ActualWidth, rightEdge - ActualWidth));
            Top = Math.Max(workArea.Top, Math.Min(workArea.Bottom - ActualHeight, Top));
        }, DispatcherPriority.Loaded);
    }

    private void ApplyLayoutMode()
    {
        NormalHeaderText.Visibility = _isCompact ? Visibility.Collapsed : Visibility.Visible;
        CompactHeaderText.Visibility = _isCompact ? Visibility.Visible : Visibility.Collapsed;
        PulseRowsList.Visibility = _isCompact ? Visibility.Collapsed : Visibility.Visible;
        CompactPulseRowsList.Visibility = _isCompact ? Visibility.Visible : Visibility.Collapsed;
        PulseSummaryPanel.Visibility = _isCompact ? Visibility.Collapsed : Visibility.Visible;
        CompactSummaryPanel.Visibility = _isCompact ? Visibility.Visible : Visibility.Collapsed;
        CompactModeButton.Content = _isCompact ? "↗" : "فشرده";
        CompactModeButton.ToolTip = _isCompact ? "نمای کامل" : "نمای فشرده";

        if (_isCompact)
        {
            RootCard.Padding = new Thickness(8);
            RootCard.CornerRadius = new CornerRadius(14);
            CompactModeButton.Width = 24;
            CompactModeButton.MinWidth = 24;
            CompactModeButton.Padding = new Thickness(0);
            CompactModeButton.FontSize = 12;
            HideWidgetButton.Width = 24;
            HideWidgetButton.MinWidth = 24;
            PhoneCallStartButton.Width = 24;
            PhoneCallStartButton.MinWidth = 24;
            PhoneCallStopButton.Width = 24;
            PhoneCallStopButton.MinWidth = 24;
            MinWidth = 226;
            MinHeight = 148;
            Width = 244;
            Height = 160;
            ResizeMode = ResizeMode.NoResize;
        }
        else
        {
            RootCard.Padding = new Thickness(10);
            RootCard.CornerRadius = new CornerRadius(12);
            CompactModeButton.Width = double.NaN;
            CompactModeButton.MinWidth = 36;
            CompactModeButton.Padding = new Thickness(6, 0, 6, 0);
            CompactModeButton.FontSize = 10.5;
            HideWidgetButton.Width = 24;
            HideWidgetButton.MinWidth = 24;
            PhoneCallStartButton.Width = 24;
            PhoneCallStartButton.MinWidth = 24;
            PhoneCallStopButton.Width = 24;
            PhoneCallStopButton.MinWidth = 24;
            MinWidth = 300;
            MinHeight = 290;
            Width = _normalWidth;
            Height = _normalHeight;
            ResizeMode = ResizeMode.CanResizeWithGrip;
        }
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

    private sealed record PulseCandidate(ProjectPulseRow Row, DateTimeOffset Latest, int Priority);
}
