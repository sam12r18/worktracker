using System.Net.Http;
using System.Windows;
using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Services;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;
using WorkTracker.Agent.Tray;
using WorkTracker.Agent.Sync;

namespace WorkTracker.Agent;

public partial class App : System.Windows.Application
{
    private TrackingEngine? _tracking; private TrayIconService? _tray; private MainWindow? _window; private SyncEngine? _sync; private HttpClient? _http;
    protected override async void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);
        try
        {
            var database=new LocalDatabase();await database.InitializeAsync();var identity=new DeviceIdentityStore(database);var deviceId=await identity.GetOrCreateDeviceIdAsync();var userId=await identity.GetOrCreateLocalUserIdAsync();
            var repository=new ActivitySessionRepository(database);var projects=new ProjectRepository(database);var activityTypes=new ActivityTypeRepository(database);var classifier=new ProjectClassificationService(projects);var corrections=new ActivityCorrectionService(repository,projects,classifier);
            var syncSettings=new SyncSettingsStore(database);var outbox=new SyncOutboxRepository(database);var applier=new RemoteChangeApplier(database);_http=new HttpClient{Timeout=TimeSpan.FromSeconds(30)};_sync=new SyncEngine(syncSettings,outbox,applier,new ApiSyncClient(_http),repository,deviceId);
            _tracking=new TrackingEngine(new WindowsForegroundWindowObserver(),new WindowsIdleTimeProvider(),repository,classifier,userId,deviceId);var manualTimer=new ManualTimerService(repository,userId,deviceId);_tray=new TrayIconService();_window=new MainWindow(_tracking,manualTimer,repository,projects,activityTypes,corrections,_sync,syncSettings,outbox,deviceId);
            _tray.ShowRequested+=(_,_)=>ShowWindow();_tray.PauseResumeRequested+=async(_,_)=>await _window.ToggleTrackingAsync();_tray.ExitRequested+=async(_,_)=>await ExitAsync();_window.HideRequested+=(_,_)=>_window.Hide();_window.ExitRequested+=async(_,_)=>await ExitAsync();_tracking.StateChanged+=async(_,_)=>_tray.SetState(_tracking.State,await repository.CountPendingSyncAsync());_tracking.SessionSaved+=async(_,_)=>_tray.SetState(_tracking.State,await repository.CountPendingSyncAsync());
            _tracking.Start();_sync.Start();_tray.SetState(_tracking.State,await repository.CountPendingSyncAsync());ShowWindow();
        }catch(Exception ex){System.Windows.MessageBox.Show($"راه‌اندازی WorkTracker ناموفق بود.\n\n{ex.Message}","WorkTracker",MessageBoxButton.OK,MessageBoxImage.Error);Shutdown(-1);}
    }
    private void ShowWindow(){if(_window is null)return;_window.Show();if(_window.WindowState==WindowState.Minimized)_window.WindowState=WindowState.Normal;_window.Activate();}
    private async Task ExitAsync(){if(_window is not null)await _window.PrepareForExitAsync();if(_tracking is not null)await _tracking.DisposeAsync();if(_sync is not null)await _sync.DisposeAsync();_http?.Dispose();_tray?.Dispose();Shutdown();}
}
