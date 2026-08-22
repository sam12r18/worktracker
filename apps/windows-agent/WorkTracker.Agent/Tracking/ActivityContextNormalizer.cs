using System.IO;
using System.Text.Json;
using WorkTracker.Agent.Integrations.Ide;
using WorkTracker.Agent.Integrations.Browser;
using WorkTracker.Agent.Domain;

namespace WorkTracker.Agent.Tracking;

public enum ActivityContextKind
{
    Ide,
    Browser,
    Generic
}

public sealed record ActivityContextDescriptor(
    ActivityContextKind Kind,
    string Key,
    string DisplayName,
    string? StableWindowPattern);

public static class ActivityContextNormalizer
{
    private static readonly string[] Separators = [" — ", " – ", " - "];

    public static ActivityContextDescriptor Describe(ForegroundSnapshot snapshot)
    {
        var process = NormalizeProcess(snapshot.ProcessName);
        var ide = snapshot.IdeContext;
        if (ide is not null && IsPhpStorm(process))
        {
            var workspace = ide.ProjectDisplay;
            if (!string.IsNullOrWhiteSpace(workspace) && workspace != "-")
            {
                var identity = !string.IsNullOrWhiteSpace(ide.ProjectPath) ? ide.ProjectPath! : workspace;
                return new(ActivityContextKind.Ide, $"ide:phpstorm:{KeyPart(identity)}", workspace, workspace);
            }
        }

        if (snapshot.BrowserContext is { } browser && IsBrowser(process))
        {
            var host = string.IsNullOrWhiteSpace(browser.Host) ? "unknown" : browser.Host!;
            var path = string.IsNullOrWhiteSpace(browser.Path) ? "/" : browser.Path!;
            var display = !string.IsNullOrWhiteSpace(browser.Title) ? browser.Title! : host;
            return new(ActivityContextKind.Browser, $"browser:{process}:{KeyPart(host)}:{KeyPart(path)}", display, null);
        }

        return Describe(snapshot.ProcessName, snapshot.WindowTitle);
    }

    public static ActivityContextDescriptor Describe(ActivitySession session)
    {
        if (!string.IsNullOrWhiteSpace(session.IdeContextJson))
        {
            try
            {
                var ide = JsonSerializer.Deserialize<IdeContextSnapshot>(session.IdeContextJson);
                if (ide is not null && ide.IsSupported)
                {
                    return Describe(new ForegroundSnapshot(0, ide.ProcessId, session.ProcessName, session.ExecutablePath, session.WindowTitle, session.StartedAt, ide));
                }
            }
            catch (JsonException)
            {
                // Historical/invalid enrichment must never make event aggregation fail.
            }
        }

        if (!string.IsNullOrWhiteSpace(session.BrowserContextJson))
        {
            try
            {
                var browser = JsonSerializer.Deserialize<BrowserContextSnapshot>(session.BrowserContextJson);
                if (browser is not null && browser.IsSupported)
                {
                    return Describe(new ForegroundSnapshot(0, 0, session.ProcessName, session.ExecutablePath, session.WindowTitle, session.StartedAt, null, browser));
                }
            }
            catch (JsonException)
            {
                // Historical/invalid browser enrichment must never make event aggregation fail.
            }
        }

        return Describe(session.ProcessName, session.WindowTitle);
    }

    public static ActivityContextDescriptor Describe(string? processName, string? windowTitle)
    {
        var process = NormalizeProcess(processName);
        var title = (windowTitle ?? string.Empty).Trim();

        if (IsPhpStorm(process))
        {
            var normalized = StripKnownApplicationSuffix(title, "PhpStorm");
            var workspace = ChooseWorkspacePart(normalized, preferFirst: true);
            if (!string.IsNullOrWhiteSpace(workspace))
                return new(ActivityContextKind.Ide, $"ide:phpstorm:{KeyPart(workspace)}", workspace, workspace);
        }

        if (IsVisualStudioCode(process))
        {
            var normalized = StripKnownApplicationSuffix(title, "Visual Studio Code");
            var workspace = ChooseWorkspacePart(normalized, preferFirst: false);
            if (!string.IsNullOrWhiteSpace(workspace))
                return new(ActivityContextKind.Ide, $"ide:vscode:{KeyPart(workspace)}", workspace, workspace);
        }

        if (IsVisualStudio(process))
        {
            var normalized = StripKnownApplicationSuffix(title, "Microsoft Visual Studio");
            var workspace = ChooseWorkspacePart(normalized, preferFirst: false);
            if (!string.IsNullOrWhiteSpace(workspace))
                return new(ActivityContextKind.Ide, $"ide:visualstudio:{KeyPart(workspace)}", workspace, workspace);
        }

        if (IsBrowser(process))
        {
            var normalized = StripBrowserSuffix(title);
            var display = string.IsNullOrWhiteSpace(normalized) ? process : normalized;
            return new(ActivityContextKind.Browser, $"browser:{process}:{KeyPart(display)}", display, null);
        }

        var generic = string.IsNullOrWhiteSpace(title) ? process : title;
        return new(ActivityContextKind.Generic, $"app:{process}:{KeyPart(generic)}", generic, null);
    }

    public static string NormalizeWindowTitle(string title)
    {
        title = StripBrowserSuffix(title.Trim());
        foreach (var suffix in new[] { " — Mozilla Firefox", " – PhpStorm", " - PhpStorm", " - Visual Studio Code", " - Microsoft Visual Studio" })
            title = StripSuffix(title, suffix);
        return title.Length <= 240 ? title : title[..240];
    }

    private static string NormalizeProcess(string? processName)
        => string.IsNullOrWhiteSpace(processName) ? "unknown" : processName.Trim().ToLowerInvariant();

    private static bool IsPhpStorm(string process) => process is "phpstorm64" or "phpstorm";
    private static bool IsVisualStudioCode(string process) => process is "code" or "code-insiders";
    private static bool IsVisualStudio(string process) => process is "devenv";
    private static bool IsBrowser(string process) => process is "chrome" or "msedge" or "firefox" or "brave" or "opera" or "vivaldi";

    private static string StripBrowserSuffix(string title)
    {
        foreach (var suffix in new[] { " - Google Chrome", " - Microsoft Edge", " — Mozilla Firefox", " - Brave", " - Opera", " - Vivaldi" })
            title = StripSuffix(title, suffix);
        return title.Trim();
    }

    private static string StripKnownApplicationSuffix(string title, string appName)
    {
        foreach (var separator in Separators)
            title = StripSuffix(title, separator + appName);
        return title.Trim();
    }

    private static string StripSuffix(string value, string suffix)
        => value.EndsWith(suffix, StringComparison.OrdinalIgnoreCase)
            ? value[..^suffix.Length].Trim()
            : value;

    private static string ChooseWorkspacePart(string title, bool preferFirst)
    {
        var parts = Split(title);
        if (parts.Length == 0) return title.Trim();
        if (parts.Length == 1) return parts[0].Trim();

        var first = parts[0].Trim();
        var last = parts[^1].Trim();
        if (LooksLikeFile(first) && !LooksLikeFile(last)) return last;
        if (LooksLikeFile(last) && !LooksLikeFile(first)) return first;
        return preferFirst ? first : last;
    }

    private static bool LooksLikeFile(string value)
    {
        var leaf = value.Trim().TrimEnd('*');
        var extension = Path.GetExtension(leaf);
        return !string.IsNullOrWhiteSpace(extension) && extension.Length <= 12;
    }

    private static string[] Split(string title)
    {
        foreach (var separator in Separators)
        {
            if (!title.Contains(separator, StringComparison.Ordinal)) continue;
            return title.Split(separator, StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries);
        }
        return string.IsNullOrWhiteSpace(title) ? [] : [title.Trim()];
    }

    private static string KeyPart(string value)
    {
        var normalized = string.Join(" ", value.Split([' ', '\t', '\r', '\n'], StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)).Trim().ToLowerInvariant();
        return normalized.Length <= 180 ? normalized : normalized[..180];
    }
}
