using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Text;

namespace WorkTracker.Agent.Tracking;

public sealed class WindowsForegroundWindowObserver : IForegroundWindowObserver
{
    public ForegroundSnapshot? Capture()
    {
        var hwnd = GetForegroundWindow();
        if (hwnd == nint.Zero) return null;

        _ = GetWindowThreadProcessId(hwnd, out var processId);
        if (processId == 0) return null;

        string? processName = null;
        string? executablePath = null;
        try
        {
            using var process = Process.GetProcessById((int)processId);
            processName = process.ProcessName;
            try { executablePath = process.MainModule?.FileName; } catch { }
        }
        catch { }

        var title = ReadWindowTitle(hwnd);
        return new ForegroundSnapshot(hwnd, (int)processId, processName, executablePath, title, DateTimeOffset.Now);
    }

    private static string? ReadWindowTitle(nint hwnd)
    {
        var length = GetWindowTextLength(hwnd);
        if (length <= 0) return null;
        var buffer = new StringBuilder(length + 1);
        _ = GetWindowText(hwnd, buffer, buffer.Capacity);
        return buffer.ToString();
    }

    [DllImport("user32.dll")]
    private static extern nint GetForegroundWindow();

    [DllImport("user32.dll")]
    private static extern uint GetWindowThreadProcessId(nint hWnd, out uint processId);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int GetWindowText(nint hWnd, StringBuilder text, int count);

    [DllImport("user32.dll")]
    private static extern int GetWindowTextLength(nint hWnd);
}
