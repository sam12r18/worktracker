using System.Runtime.InteropServices;
using System.Text;
using System.Text.RegularExpressions;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Integrations.Context;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Integrations.Codex;

public sealed class CodexContextProbe : IContextProvider
{
    private static readonly Regex WindowsPathRegex = new(
        @"(?<![A-Za-z0-9_])(?<path>[A-Za-z]:\\(?:[^\\/:*?\""<>|\r\n]+\\)*[^\\/:*?\""<>|\r\n]+)",
        RegexOptions.Compiled | RegexOptions.CultureInvariant);

    private const int MaxWindowTexts = 64;
    private const int MaxWindowTextLength = 512;

    private readonly object _statusGate = new();
    private CodexProbeStatus _status = CodexProbeStatus.NotForeground();
    private string? _lastLogSignature;

    public static CodexContextProbe Shared { get; } = new();

    private CodexContextProbe()
    {
    }

    public string ProviderId => "codex-probe";

    public CodexProbeStatus GetStatus()
    {
        lock (_statusGate)
        {
            return _status;
        }
    }

    public async Task<ForegroundSnapshot> EnrichAsync(ForegroundSnapshot snapshot, CancellationToken ct = default)
    {
        ct.ThrowIfCancellationRequested();
        var now = DateTimeOffset.UtcNow;

        if (!LooksLikeCodex(snapshot.ProcessName))
        {
            SetStatus(CodexProbeStatus.NotForeground(now));
            return snapshot;
        }

        var signals = new List<string>();
        if (!string.IsNullOrWhiteSpace(snapshot.WindowTitle))
            signals.Add(snapshot.WindowTitle!);

        signals.AddRange(CollectWindowTexts(snapshot.ProcessId));
        var candidates = ExtractPathCandidates(signals);

        var status = candidates.Count switch
        {
            1 => new CodexProbeStatus(
                ForegroundDetected: true,
                ProcessId: snapshot.ProcessId,
                State: "resolved",
                ProjectPath: candidates[0],
                Signal: "window_text_path",
                Message: "یک Workspace path یکتا از سیگنال‌های قابل مشاهده Windows پیدا شد.",
                ObservedAtUtc: now),
            > 1 => new CodexProbeStatus(
                ForegroundDetected: true,
                ProcessId: snapshot.ProcessId,
                State: "ambiguous",
                ProjectPath: null,
                Signal: "multiple_window_text_paths",
                Message: "چند مسیر متفاوت دیده شد؛ پروژه حدس زده نمی‌شود.",
                ObservedAtUtc: now),
            _ => new CodexProbeStatus(
                ForegroundDetected: true,
                ProcessId: snapshot.ProcessId,
                State: "probe",
                ProjectPath: null,
                Signal: "window_text",
                Message: "Codex در foreground است ولی Workspace signal پایدار هنوز پیدا نشده است.",
                ObservedAtUtc: now),
        };

        SetStatus(status);
        await LogStatusChangeAsync(status, candidates.Count);
        return snapshot;
    }

    public static string? ResolveUniqueProjectPath(IEnumerable<string> signals)
    {
        ArgumentNullException.ThrowIfNull(signals);
        var candidates = ExtractPathCandidates(signals);
        return candidates.Count == 1 ? candidates[0] : null;
    }

    private static List<string> ExtractPathCandidates(IEnumerable<string> signals)
    {
        var candidates = new HashSet<string>(StringComparer.OrdinalIgnoreCase);

        foreach (var signal in signals)
        {
            if (string.IsNullOrWhiteSpace(signal)) continue;

            foreach (Match match in WindowsPathRegex.Matches(signal))
            {
                var raw = match.Groups["path"].Value
                    .Trim()
                    .TrimEnd('.', ',', ';', ':', ')', ']', '}', '\'', '"');

                if (raw.Length < 4) continue;

                try
                {
                    var normalized = Path.GetFullPath(raw)
                        .TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                    if (normalized.Length >= 4) candidates.Add(normalized);
                }
                catch
                {
                    // Probe-only: malformed path-looking text is ignored.
                }
            }
        }

        return candidates.OrderBy(x => x, StringComparer.OrdinalIgnoreCase).ToList();
    }

    private static bool LooksLikeCodex(string? processName)
        => !string.IsNullOrWhiteSpace(processName)
           && processName.Contains("codex", StringComparison.OrdinalIgnoreCase);

    private static IReadOnlyList<string> CollectWindowTexts(int processId)
    {
        if (processId <= 0) return Array.Empty<string>();

        var results = new List<string>();
        EnumWindows((window, _) =>
        {
            if (results.Count >= MaxWindowTexts) return false;
            _ = GetWindowThreadProcessId(window, out var ownerProcessId);
            if (ownerProcessId != (uint)processId) return true;

            AddWindowText(window, results);
            EnumChildWindows(window, (child, _) =>
            {
                if (results.Count >= MaxWindowTexts) return false;
                AddWindowText(child, results);
                return true;
            }, IntPtr.Zero);
            return results.Count < MaxWindowTexts;
        }, IntPtr.Zero);

        return results;
    }

    private static void AddWindowText(IntPtr window, List<string> results)
    {
        var length = Math.Min(GetWindowTextLength(window), MaxWindowTextLength - 1);
        if (length <= 0) return;

        var builder = new StringBuilder(length + 1);
        if (GetWindowText(window, builder, builder.Capacity) <= 0) return;
        var text = builder.ToString().Trim();
        if (string.IsNullOrWhiteSpace(text)) return;
        if (results.Any(x => string.Equals(x, text, StringComparison.Ordinal))) return;
        results.Add(text);
    }

    private void SetStatus(CodexProbeStatus status)
    {
        lock (_statusGate)
        {
            _status = status;
        }
    }

    private async Task LogStatusChangeAsync(CodexProbeStatus status, int candidateCount)
    {
        var signature = $"{status.ProcessId}|{status.State}|{status.ProjectPath}|{status.Signal}|{candidateCount}";
        if (string.Equals(signature, _lastLogSignature, StringComparison.Ordinal)) return;
        _lastLogSignature = signature;

        await AgentLog.InfoAsync("context.codex.probe", "Codex context probe state changed", new
        {
            process_id = status.ProcessId,
            state = status.State,
            signal = status.Signal,
            project_path = status.ProjectPath,
            path_candidate_count = candidateCount,
        });
    }

    private delegate bool EnumWindowsProc(IntPtr window, IntPtr lParam);

    [DllImport("user32.dll")]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool EnumWindows(EnumWindowsProc lpEnumFunc, IntPtr lParam);

    [DllImport("user32.dll")]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool EnumChildWindows(IntPtr hWndParent, EnumWindowsProc lpEnumFunc, IntPtr lParam);

    [DllImport("user32.dll")]
    private static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int GetWindowText(IntPtr hWnd, StringBuilder lpString, int nMaxCount);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int GetWindowTextLength(IntPtr hWnd);
}
