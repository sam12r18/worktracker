using System.IO;
using System.Text.Json;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Integrations.Browser;

public sealed class BrowserContextBridgeService
{
    private static readonly TimeSpan FreshnessWindow = TimeSpan.FromSeconds(30);
    private static readonly TimeSpan HardStaleWindow = TimeSpan.FromHours(12);
    private readonly string _contextPath;
    private readonly SemaphoreSlim _readGate = new(1, 1);
    private readonly JsonSerializerOptions _json = new(JsonSerializerDefaults.Web)
    {
        PropertyNameCaseInsensitive = true,
    };
    private BrowserContextSnapshot? _lastContext;
    private string? _lastLoggedSignature;

    public BrowserContextBridgeService(string? root = null)
    {
        root ??= Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "WorkTracker",
            "browser",
            "chrome");
        _contextPath = Path.Combine(root, "context.json");
    }

    public string ContextPath => _contextPath;

    public async Task<ForegroundSnapshot> EnrichAsync(ForegroundSnapshot snapshot, CancellationToken ct = default)
    {
        if (!IsChrome(snapshot.ProcessName))
            return snapshot with { BrowserContext = null };

        var context = await TryReadAsync(snapshot, ct);
        if (context is null)
            return snapshot with { BrowserContext = null };

        var enriched = snapshot with { BrowserContext = context };
        await LogContextChangeAsync(context);
        return enriched;
    }

    public BrowserContextBridgeStatus GetStatus(DateTimeOffset? now = null)
    {
        now ??= DateTimeOffset.UtcNow;
        var context = _lastContext;
        if (context is null)
            return BrowserContextBridgeStatus.Disconnected("Context از افزونه Chrome دریافت نشده است.");

        var age = Math.Max(0, (int)Math.Floor((now.Value - context.ObservedAtUtc).TotalSeconds));
        var stale = age > FreshnessWindow.TotalSeconds;
        return new BrowserContextBridgeStatus(
            true,
            stale,
            context.Browser ?? "Chrome",
            context.Host ?? "-",
            context.Path ?? "-",
            context.ObservedAtUtc,
            age,
            stale
                ? "Context قدیمی است؛ تا وقتی عنوان تب با پنجره فعال تطابق دارد قابل استفاده است."
                : "Context زنده از افزونه Chrome دریافت می‌شود.");
    }

    private async Task<BrowserContextSnapshot?> TryReadAsync(ForegroundSnapshot foreground, CancellationToken ct)
    {
        await _readGate.WaitAsync(ct);
        try
        {
            if (!File.Exists(_contextPath)) return null;

            BrowserContextSnapshot? context;
            try
            {
                await using var stream = new FileStream(
                    _contextPath,
                    FileMode.Open,
                    FileAccess.Read,
                    FileShare.ReadWrite | FileShare.Delete,
                    4096,
                    useAsync: true);
                context = await JsonSerializer.DeserializeAsync<BrowserContextSnapshot>(stream, _json, ct);
            }
            catch (JsonException ex)
            {
                await AgentLog.WarnAsync("browser.context", "invalid Chrome context JSON ignored", new { ex.Message });
                return null;
            }
            catch (IOException)
            {
                return null;
            }

            if (context is null || !context.IsSupported || context.Incognito || !context.Focused ||
                !string.Equals(context.Browser, "chrome", StringComparison.OrdinalIgnoreCase))
                return null;

            var now = DateTimeOffset.UtcNow;
            var age = now - context.ObservedAtUtc;
            if (age < TimeSpan.FromSeconds(-5) || age > HardStaleWindow) return null;
            if (age > FreshnessWindow && !TitlesMatch(foreground.WindowTitle, context.Title)) return null;

            _lastContext = context;
            return context;
        }
        finally
        {
            _readGate.Release();
        }
    }

    private static bool TitlesMatch(string? foregroundTitle, string? tabTitle)
    {
        if (string.IsNullOrWhiteSpace(foregroundTitle) || string.IsNullOrWhiteSpace(tabTitle)) return false;
        var window = ActivityContextNormalizer.NormalizeWindowTitle(foregroundTitle);
        var tab = tabTitle.Trim();
        if (string.Equals(window, tab, StringComparison.OrdinalIgnoreCase)) return true;
        if (window.Length < 6 || tab.Length < 6) return false;
        return window.Contains(tab, StringComparison.OrdinalIgnoreCase)
               || tab.Contains(window, StringComparison.OrdinalIgnoreCase);
    }

    private static bool IsChrome(string? processName)
        => string.Equals(processName, "chrome", StringComparison.OrdinalIgnoreCase);

    private async Task LogContextChangeAsync(BrowserContextSnapshot context)
    {
        var signature = string.Join("|", context.Host, context.Path, context.Title);
        if (string.Equals(_lastLoggedSignature, signature, StringComparison.Ordinal)) return;
        _lastLoggedSignature = signature;
        await AgentLog.InfoAsync("browser.context", "Chrome context applied to foreground observation", new
        {
            browser = context.Browser,
            host = context.Host,
            path = context.Path,
            title = context.Title,
            extension_version = context.ExtensionVersion,
            age_ms = Math.Max(0, (int)(DateTimeOffset.UtcNow - context.ObservedAtUtc).TotalMilliseconds),
        });
    }
}
