using WorkTracker.Agent.Integrations.Browser;
using WorkTracker.Agent.Integrations.Codex;
using WorkTracker.Agent.Integrations.Ide;

namespace WorkTracker.Agent.Integrations.Context;

public static class IntegrationStatusSelfTest
{
    public static IReadOnlyList<string> Run()
    {
        var failures = new List<string>();

        var ide = IntegrationStatus.FromIde(new IdeContextBridgeStatus(
            Connected: true,
            Stale: false,
            Provider: "phpstorm-plugin",
            Project: "worktracker",
            File: "TrackingEngine.cs",
            Mode: "debug",
            Branch: "feature/alpha-8.1-browser-context",
            RunConfiguration: "WorkTracker.Agent",
            ObservedAtUtc: DateTimeOffset.UtcNow,
            AgeSeconds: 2,
            Message: "ok"));

        if (ide.State != "connected" || ide.Transport != "json" || !ide.Summary.Contains("worktracker", StringComparison.Ordinal))
            failures.Add("integration status maps PhpStorm to connected/json with project summary");

        var browser = IntegrationStatus.FromBrowser(new BrowserContextBridgeStatus(
            Connected: true,
            Stale: true,
            Browser: "Chrome",
            Host: "github.com",
            Path: "/sam12r18/worktracker",
            ObservedAtUtc: DateTimeOffset.UtcNow.AddSeconds(-45),
            AgeSeconds: 45,
            Message: "stale"));

        if (browser.State != "stale" || browser.Transport != "native->json" || !browser.Summary.Contains("github.com", StringComparison.Ordinal))
            failures.Add("integration status maps stale Chrome to native->json");

        var disconnected = IntegrationStatus.FromBrowser(BrowserContextBridgeStatus.Disconnected("missing"));
        if (disconnected.State != "disconnected" || disconnected.AgeSeconds is not null)
            failures.Add("integration status maps disconnected provider without age");

        var now = DateTimeOffset.UtcNow;
        var codex = IntegrationStatus.FromCodex(new CodexProbeStatus(
            ForegroundDetected: true,
            ProcessId: 123,
            State: "probe",
            ProjectPath: null,
            Signal: "window_text",
            Message: "unresolved",
            ObservedAtUtc: now), now);
        if (codex.Transport != "internal-probe" || codex.State != "probe" || codex.AgeSeconds != 0)
            failures.Add("integration status maps Codex probe to internal-probe");

        return failures;
    }
}
