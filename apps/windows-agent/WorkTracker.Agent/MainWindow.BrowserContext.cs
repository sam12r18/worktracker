using System.Windows.Threading;
using WorkTracker.Agent.Integrations.Browser;

namespace WorkTracker.Agent;

public partial class MainWindow
{
    private readonly BrowserContextBridgeService _browserStatusBridge = new();
    private DispatcherTimer? _browserStatusTimer;

    protected override void OnContentRendered(EventArgs e)
    {
        base.OnContentRendered(e);

        if (_browserStatusTimer is null)
        {
            _browserStatusTimer = new DispatcherTimer
            {
                Interval = TimeSpan.FromSeconds(5),
            };
            _browserStatusTimer.Tick += (_, _) => RefreshBrowserContextHeader();
            _browserStatusTimer.Start();
        }

        RefreshBrowserContextHeader();
    }

    private void RefreshBrowserContextHeader()
    {
        var status = _browserStatusBridge.GetStatus();
        var machine = Environment.MachineName;
        var shortDeviceId = _deviceId[..Math.Min(10, _deviceId.Length)];

        if (!status.Connected)
        {
            DeviceText.Text = $"دستگاه: {machine} · {shortDeviceId} · Chrome Context: —";
            return;
        }

        var path = Shorten(status.Path, 46);
        var age = status.Stale ? $" · {status.AgeSeconds}s" : string.Empty;
        DeviceText.Text = $"دستگاه: {machine} · {shortDeviceId} · Chrome: {status.Host}{path}{age}";
    }

    private static string Shorten(string? value, int maxLength)
    {
        if (string.IsNullOrWhiteSpace(value) || value == "-") return string.Empty;
        var text = value.Trim();
        return text.Length <= maxLength ? text : text[..Math.Max(1, maxLength - 1)] + "…";
    }
}
