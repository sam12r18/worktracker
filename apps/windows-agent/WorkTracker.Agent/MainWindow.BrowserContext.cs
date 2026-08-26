using System.Windows;
using System.Windows.Controls;
using System.Windows.Threading;
using WorkTracker.Agent.Integrations.Browser;
using WorkTracker.Agent.Integrations.Codex;
using WorkTracker.Agent.Integrations.Context;

namespace WorkTracker.Agent;

public partial class MainWindow
{
    private readonly BrowserContextBridgeService _browserStatusBridge = BrowserContextBridgeService.Shared;
    private readonly CodexContextProbe _codexStatusProbe = CodexContextProbe.Shared;
    private DispatcherTimer? _integrationStatusTimer;
    private TextBlock? _chromeIntegrationStatusText;
    private TextBlock? _codexIntegrationStatusText;

    protected override void OnContentRendered(EventArgs e)
    {
        base.OnContentRendered(e);

        EnsureIntegrationStatusRows();

        if (_integrationStatusTimer is null)
        {
            _integrationStatusTimer = new DispatcherTimer
            {
                Interval = TimeSpan.FromSeconds(5),
            };
            _integrationStatusTimer.Tick += (_, _) => RefreshIntegrationStatuses();
            _integrationStatusTimer.Start();
        }

        RefreshIntegrationStatuses();
    }

    private void EnsureIntegrationStatusRows()
    {
        if (_chromeIntegrationStatusText is not null && _codexIntegrationStatusText is not null) return;
        if (IdeContextStatusText.Parent is not Panel parent) return;

        var ideIndex = parent.Children.IndexOf(IdeContextStatusText);
        if (ideIndex < 0) return;

        _chromeIntegrationStatusText = CreateIntegrationStatusTextBlock();
        _codexIntegrationStatusText = CreateIntegrationStatusTextBlock();
        parent.Children.Insert(ideIndex + 1, _chromeIntegrationStatusText);
        parent.Children.Insert(ideIndex + 2, _codexIntegrationStatusText);
    }

    private static TextBlock CreateIntegrationStatusTextBlock()
        => new()
        {
            Margin = new Thickness(0, 4, 0, 0),
            FontSize = 11,
            Opacity = 0.78,
            TextWrapping = TextWrapping.Wrap,
        };

    private void RefreshIntegrationStatuses()
    {
        var ide = IntegrationStatus.FromIde(_ideContext.GetStatus());
        var browser = IntegrationStatus.FromBrowser(_browserStatusBridge.GetStatus());
        var codex = IntegrationStatus.FromCodex(_codexStatusProbe.GetStatus());

        IdeContextStatusText.Text = FormatIntegrationStatus(ide);
        if (_chromeIntegrationStatusText is not null)
            _chromeIntegrationStatusText.Text = FormatIntegrationStatus(browser);
        if (_codexIntegrationStatusText is not null)
            _codexIntegrationStatusText.Text = FormatIntegrationStatus(codex);
    }

    private static string FormatIntegrationStatus(IntegrationStatus status)
    {
        var state = status.State switch
        {
            "connected" => "متصل",
            "stale" => "قدیمی",
            "disconnected" => "قطع",
            "resolved" => "Probe: مسیر پیدا شد",
            "ambiguous" => "Probe: مبهم",
            "probe" => "Probe",
            "idle" => "غیرفعال",
            _ => status.State,
        };
        var age = status.AgeSeconds is null ? string.Empty : $" · {status.AgeSeconds}s";
        var summary = status.Summary == "-" ? status.Message : Shorten(status.Summary, 110);
        return $"{status.DisplayName}: {state} · {status.Transport}{age} · {summary}";
    }

    private static string Shorten(string? value, int maxLength)
    {
        if (string.IsNullOrWhiteSpace(value) || value == "-") return string.Empty;
        var text = value.Trim();
        return text.Length <= maxLength ? text : text[..Math.Max(1, maxLength - 1)] + "…";
    }
}
