using System.ComponentModel;
using System.Windows;
using System.Windows.Threading;
using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Services;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;
using WorkTracker.Agent.ViewModels;
using WorkTracker.Agent.Sync;

namespace WorkTracker.Agent;

public partial class MainWindow : Window
{
    private readonly string _deviceId; private readonly TrackingEngine _tracking; private readonly ManualTimerService _manualTimer; private readonly ActivitySessionRepository _repository; private readonly ProjectRepository _projects; private readonly ActivityTypeRepository _activityTypes; private readonly ActivityCorrectionService _corrections; private readonly SyncEngine _sync; private readonly SyncSettingsStore _syncSettings; private readonly SyncOutboxRepository _syncOutbox; private readonly DispatcherTimer _uiTimer; private bool _allowClose;
    public event EventHandler? HideRequested; public event EventHandler? ExitRequested;

    public MainWindow(TrackingEngine tracking, ManualTimerService manualTimer, ActivitySessionRepository repository, ProjectRepository projects, ActivityTypeRepository activityTypes, ActivityCorrectionService corrections, SyncEngine sync, SyncSettingsStore syncSettings, SyncOutboxRepository syncOutbox, string deviceId)
    {
        InitializeComponent(); _deviceId=deviceId; _tracking=tracking;_manualTimer=manualTimer;_repository=repository;_projects=projects;_activityTypes=activityTypes;_corrections=corrections;_sync=sync;_syncSettings=syncSettings;_syncOutbox=syncOutbox;
        DeviceText.Text=$"دستگاه: {Environment.MachineName} · {deviceId[..Math.Min(10,deviceId.Length)]}";
        _sync.StatusChanged+=(_,status)=>Dispatcher.Invoke(()=>UpdateSyncStatus(status)); _tracking.StateChanged+=(_,_)=>Dispatcher.Invoke(UpdateTrackingState); _tracking.ForegroundChanged+=(_,snapshot)=>Dispatcher.Invoke(()=>{TodaySummary.CurrentActivity=snapshot?.WindowTitle??"بدون فعالیت فعال";TodaySummary.CurrentProcess=snapshot?.ProcessName??"-";}); _tracking.SessionSaved+=async(_,_)=>await Dispatcher.InvokeAsync(RefreshAsync);
        _uiTimer=new DispatcherTimer{Interval=TimeSpan.FromSeconds(5)};_uiTimer.Tick+=async(_,_)=>await RefreshAsync();_uiTimer.Start();Loaded+=async(_,_)=>{UpdateTrackingState();await LoadSyncSettingsAsync();UpdateSyncStatus(_sync.Status);await RefreshAsync();};
    }

    public async Task ToggleTrackingAsync(){if(_tracking.State==TrackingState.Paused)_tracking.Resume();else await _tracking.PauseAsync();UpdateTrackingState();}
    private async void TrackingButton_Click(object sender,RoutedEventArgs e)=>await ToggleTrackingAsync();
    private async void StartManualTimerButton_Click(object sender,RoutedEventArgs e){try{var projectId=ManualProjectCombo.SelectedValue as string;var activityTypeId=ManualActivityTypeCombo.SelectedValue as string;_manualTimer.Start(projectId,null,activityTypeId,ManualBillableCheckBox.IsChecked,string.IsNullOrWhiteSpace(ManualNoteTextBox.Text)?null:ManualNoteTextBox.Text.Trim());ManualNoteTextBox.Clear();await RefreshAsync();}catch(Exception ex){MessageBox.Show(ex.Message,"WorkTracker",MessageBoxButton.OK,MessageBoxImage.Warning);}}
    private async void StopManualTimerButton_Click(object sender,RoutedEventArgs e){try{if(ActiveManualTimersList.SelectedItem is not ActiveManualTimer timer){MessageBox.Show("یک تایمر دستی فعال را انتخاب کنید.","WorkTracker",MessageBoxButton.OK,MessageBoxImage.Information);return;}await _manualTimer.StopAsync(timer.Id);await RefreshAsync();}catch(Exception ex){MessageBox.Show(ex.Message,"WorkTracker",MessageBoxButton.OK,MessageBoxImage.Warning);}}

    private async Task RefreshAsync()
    {
        try
        {
            var projects=await _projects.GetActiveAsync(); var map=projects.ToDictionary(x=>x.Id,x=>x.Name); var activityTypes=await _activityTypes.GetActiveAsync(); var typeMap=activityTypes.ToDictionary(x=>x.Id,x=>x.Name);
            var manualSelected=ManualProjectCombo.SelectedValue as string; var manualTypeSelected=ManualActivityTypeCombo.SelectedValue as string; var timelineTypeSelected=TimelineActivityTypeCombo.SelectedValue as string; var unknownSelected=UnknownProjectCombo.SelectedValue as string;
            ManualProjectCombo.ItemsSource=projects; ManualActivityTypeCombo.ItemsSource=activityTypes; TimelineActivityTypeCombo.ItemsSource=activityTypes; UnknownProjectCombo.ItemsSource=projects;
            if(manualSelected is not null) ManualProjectCombo.SelectedValue=manualSelected; if(manualTypeSelected is not null) ManualActivityTypeCombo.SelectedValue=manualTypeSelected; if(timelineTypeSelected is not null) TimelineActivityTypeCombo.SelectedValue=timelineTypeSelected; if(unknownSelected is not null) UnknownProjectCombo.SelectedValue=unknownSelected;
            var sessions=await _repository.GetForLocalDayAsync(DateTime.Now.Date); ActivitiesList.ItemsSource=sessions.Select(s=>new ActivityRow(s,s.ProjectId is not null&&map.TryGetValue(s.ProjectId,out var n)?n:null,s.ActivityTypeId is not null&&typeMap.TryGetValue(s.ActivityTypeId,out var tn)?tn:null)).ToList();
            var unknown=sessions.Where(x=>string.IsNullOrWhiteSpace(x.ProjectId)).ToList(); UnknownActivitiesList.ItemsSource=unknown.Select(s=>new ActivityRow(s)).ToList(); ActiveManualTimersList.ItemsSource=_manualTimer.ActiveTimers;
            SyncConflictsGrid.ItemsSource=await _syncOutbox.GetOpenConflictsAsync();
            RulesGrid.ItemsSource=(await _projects.GetRulesAsync()).Select(r=>new{Project=map.TryGetValue(r.ProjectId,out var pn)?pn:r.ProjectId,Type=r.Type,Pattern=r.Pattern,Weight=r.Weight,Priority=r.Priority,Enabled=r.IsEnabled}).ToList();
            var dayStart=new DateTimeOffset(DateTime.Now.Date,TimeZoneInfo.Local.GetUtcOffset(DateTime.Now.Date));var summary=TimeAccountingService.Summarize(sessions,dayStart.ToUniversalTime(),dayStart.AddDays(1).ToUniversalTime()); TodaySummary.Effort=Format(summary.EffortSeconds);TodaySummary.Coverage=Format(summary.ElapsedCoverageSeconds);TodaySummary.Concurrent=Format(summary.ConcurrentEffortSeconds);TodaySummary.UnknownSync=$"{unknown.Count} / {await _repository.CountPendingSyncAsync()}";UpdateTrackingState();
        }catch{/* refresh must never stop capture */}
    }

    private async void AssignActivityTypeButton_Click(object sender,RoutedEventArgs e)
    {
        try { if(ActivitiesList.SelectedItem is not ActivityRow row){MessageBox.Show("یک Activity را از Timeline انتخاب کنید.","WorkTracker",MessageBoxButton.OK,MessageBoxImage.Information);return;} if(TimelineActivityTypeCombo.SelectedValue is not string typeId){MessageBox.Show("نوع فعالیت را انتخاب کنید.","WorkTracker",MessageBoxButton.OK,MessageBoxImage.Information);return;} await _repository.AssignActivityTypeAsync(row.Id,typeId,TimelineBillableCheckBox.IsChecked); await RefreshAsync(); }
        catch(Exception ex){MessageBox.Show(ex.Message,"WorkTracker",MessageBoxButton.OK,MessageBoxImage.Warning);}
    }

    private async Task AssignUnknownAsync(bool learn)
    {
        if(UnknownActivitiesList.SelectedItem is not ActivityRow row){MessageBox.Show("یک فعالیت ناشناخته را انتخاب کنید.","WorkTracker",MessageBoxButton.OK,MessageBoxImage.Information);return;}
        if(UnknownProjectCombo.SelectedValue is not string projectId){MessageBox.Show("پروژه را انتخاب کنید.","WorkTracker",MessageBoxButton.OK,MessageBoxImage.Information);return;}
        await _corrections.AssignAsync(row.Session,projectId,learn);await RefreshAsync();
    }
    private async void AssignUnknownButton_Click(object sender,RoutedEventArgs e)=>await AssignUnknownAsync(false);
    private async void AssignAndLearnButton_Click(object sender,RoutedEventArgs e)=>await AssignUnknownAsync(true);
    private async void AddProjectButton_Click(object sender,RoutedEventArgs e){try{await _projects.CreateAsync(NewProjectNameTextBox.Text,NewProjectCodeTextBox.Text);NewProjectNameTextBox.Clear();NewProjectCodeTextBox.Clear();await RefreshAsync();}catch(Exception ex){MessageBox.Show(ex.Message,"WorkTracker",MessageBoxButton.OK,MessageBoxImage.Warning);}}

    private async Task LoadSyncSettingsAsync()
    {
        var settings=await _syncSettings.LoadAsync();ApiUrlTextBox.Text=settings.ApiBaseUrl;TokenStateText.Text=string.IsNullOrWhiteSpace(settings.AccessToken)?"Token ذخیره نشده":"Token با DPAPI ذخیره شده";SyncConflictsGrid.ItemsSource=await _syncOutbox.GetOpenConflictsAsync();
    }
    private void UpdateSyncStatus(SyncStatus status)
    {
        SyncStatusText.Text=$"{status.Message} · صف {status.Pending} · تعارض {status.Conflicts}";
        LastSyncText.Text=status.LastSuccess is null?"آخرین Sync: -":$"آخرین Sync: {status.LastSuccess.Value.ToLocalTime():yyyy-MM-dd HH:mm:ss}";
    }
    private async void SaveSyncSettingsButton_Click(object sender,RoutedEventArgs e)
    {
        try{await _syncSettings.SaveConnectionAsync(ApiUrlTextBox.Text,SyncTokenPasswordBox.Password);SyncTokenPasswordBox.Clear();await LoadSyncSettingsAsync();await _sync.TriggerAsync();}
        catch(Exception ex){MessageBox.Show(ex.Message,"WorkTracker Sync",MessageBoxButton.OK,MessageBoxImage.Warning);}
    }
    private async void SyncNowButton_Click(object sender,RoutedEventArgs e){await _sync.TriggerAsync();SyncConflictsGrid.ItemsSource=await _syncOutbox.GetOpenConflictsAsync();await RefreshAsync();}
    private void CopyDeviceIdButton_Click(object sender,RoutedEventArgs e){Clipboard.SetText(_deviceId);MessageBox.Show("شناسه کامل دستگاه کپی شد. هنگام ساخت Device Token در داشبورد همین شناسه را وارد کنید.","WorkTracker",MessageBoxButton.OK,MessageBoxImage.Information);}
    private async void ClearSyncTokenButton_Click(object sender,RoutedEventArgs e){await _syncSettings.ClearTokenAsync();SyncTokenPasswordBox.Clear();await LoadSyncSettingsAsync();UpdateSyncStatus(SyncStatus.Disabled("Token حذف شد"));}

    private static string Format(int seconds)=>TimeSpan.FromSeconds(seconds).ToString(@"hh\:mm\:ss");
    private void UpdateTrackingState(){var manualCount=_manualTimer.ActiveTimers.Count;TrackingStateText.Text=_tracking.State switch{TrackingState.Tracking when manualCount>0=>$"● ردیابی فعال · {manualCount} تایمر دستی همزمان",TrackingState.Idle when manualCount>0=>$"● سیستم بیکار · {manualCount} تایمر دستی فعال",TrackingState.Tracking=>"● در حال ردیابی",TrackingState.Idle=>"● سیستم بیکار",_ when manualCount>0=>$"● ردیابی متوقف · {manualCount} تایمر دستی فعال",_=>"● ردیابی متوقف"};TrackingButton.Content=_tracking.State==TrackingState.Paused?"ادامه ردیابی":"توقف ردیابی";}
    public async Task PrepareForExitAsync(){_uiTimer.Stop();await _manualTimer.StopAllAsync();}
    private async void RefreshButton_Click(object sender,RoutedEventArgs e)=>await RefreshAsync(); private void ExitButton_Click(object sender,RoutedEventArgs e){_allowClose=true;ExitRequested?.Invoke(this,EventArgs.Empty);} private void Window_Closing(object? sender,CancelEventArgs e){if(_allowClose)return;e.Cancel=true;HideRequested?.Invoke(this,EventArgs.Empty);}
}
