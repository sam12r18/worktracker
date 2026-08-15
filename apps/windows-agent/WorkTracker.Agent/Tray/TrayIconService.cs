using System.Drawing;
using System.Windows.Forms;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Tray;

public sealed class TrayIconService : IDisposable
{
    private readonly NotifyIcon _icon;
    public event EventHandler? ShowRequested;
    public event EventHandler? PauseResumeRequested;
    public event EventHandler? ExitRequested;

    public TrayIconService()
    {
        var menu = new ContextMenuStrip();
        menu.Items.Add("نمایش WorkTracker", null, (_,_) => ShowRequested?.Invoke(this,EventArgs.Empty));
        menu.Items.Add("توقف / ادامه ردیابی", null, (_,_) => PauseResumeRequested?.Invoke(this,EventArgs.Empty));
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("خروج", null, (_,_) => ExitRequested?.Invoke(this,EventArgs.Empty));
        _icon = new NotifyIcon { Text="WorkTracker", Icon=SystemIcons.Application, Visible=true, ContextMenuStrip=menu };
        _icon.DoubleClick += (_,_) => ShowRequested?.Invoke(this,EventArgs.Empty);
    }

    public void SetState(TrackingState state, int pendingSync)
    {
        _icon.Text = state switch
        {
            TrackingState.Tracking => $"WorkTracker - در حال ردیابی - همگام‌سازی: {pendingSync}",
            TrackingState.Idle => $"WorkTracker - بیکار - همگام‌سازی: {pendingSync}",
            _ => $"WorkTracker - متوقف - همگام‌سازی: {pendingSync}"
        };
    }

    public void Dispose() { _icon.Visible=false; _icon.Dispose(); }
}
