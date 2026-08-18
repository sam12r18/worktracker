using System.IO;
using System.Text.RegularExpressions;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Classification;

public sealed class ProjectClassificationService(ProjectRepository projects)
{
    private readonly ProjectResolver _resolver = new();
    private static readonly Regex BrowserCounterSuffix = new(@"\s*[\(\[]\d+[\)\]]\s*$", RegexOptions.Compiled | RegexOptions.CultureInvariant);
    private static readonly string[] BrowserSeparators = [" — ", " – ", " - ", " | ", " :: "];
    private static readonly HashSet<string> GenericBrowserSegments = new(StringComparer.OrdinalIgnoreCase)
    {
        "google", "google search", "new tab", "home", "chatgpt", "gmail", "bale web",
        "google chrome", "microsoft edge", "mozilla firefox", "brave", "opera", "vivaldi"
    };

    public async Task<ProjectResolution?> ResolveAsync(ForegroundSnapshot snapshot, CancellationToken ct = default)
    {
        var ideResolution = await ResolveIdeProjectAsync(snapshot, ct);
        if (ideResolution is not null) return ideResolution;
        return _resolver.Resolve(snapshot, await projects.GetRulesAsync(ct));
    }

    private async Task<ProjectResolution?> ResolveIdeProjectAsync(ForegroundSnapshot snapshot, CancellationToken ct)
    {
        var ide = snapshot.IdeContext;
        if (ide is null || string.IsNullOrWhiteSpace(ide.ProjectName) && string.IsNullOrWhiteSpace(ide.ProjectPath)) return null;

        var active = await projects.GetActiveAsync(ct);
        var hints = new List<(string Value, string Reason)>();
        if (!string.IsNullOrWhiteSpace(ide.ProjectName)) hints.Add((NormalizeProjectHint(ide.ProjectName!), "ide_plugin_project_name"));
        if (!string.IsNullOrWhiteSpace(ide.ProjectPath))
        {
            var leaf = Path.GetFileName(ide.ProjectPath!.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar));
            if (!string.IsNullOrWhiteSpace(leaf)) hints.Add((NormalizeProjectHint(leaf), "ide_plugin_project_path_leaf"));
        }

        var matches = new List<(Project Project, string Reason)>();
        foreach (var project in active)
        {
            var projectKeys = new[] { project.Name, project.Code }
                .Where(x => !string.IsNullOrWhiteSpace(x))
                .Select(x => NormalizeProjectHint(x!))
                .Where(x => x.Length >= 2)
                .ToHashSet(StringComparer.OrdinalIgnoreCase);

            foreach (var hint in hints)
            {
                if (hint.Value.Length >= 2 && projectKeys.Contains(hint.Value))
                {
                    matches.Add((project, hint.Reason));
                    break;
                }
            }
        }

        var distinct = matches.GroupBy(x => x.Project.Id, StringComparer.OrdinalIgnoreCase).Select(x => x.First()).ToList();
        if (distinct.Count != 1) return null;

        var winner = distinct[0];
        return new ProjectResolution(
            winner.Project.Id,
            120,
            1.0,
            new[] { $"{winner.Reason}:{ide.ProjectName ?? ide.ProjectPath}" });
    }

    private static string NormalizeProjectHint(string value)
        => new(value.Trim().ToLowerInvariant().Where(char.IsLetterOrDigit).ToArray());

    public async Task<ProjectRule?> LearnFromAsync(string projectId, ForegroundSnapshot snapshot, ProjectRuleType preferredType = ProjectRuleType.WindowTitle, CancellationToken ct = default)
    {
        var (type, pattern, weight) = preferredType switch
        {
            ProjectRuleType.ProcessName when !string.IsNullOrWhiteSpace(snapshot.ProcessName) => (ProjectRuleType.ProcessName, snapshot.ProcessName!, 55),
            ProjectRuleType.ExecutablePath when !string.IsNullOrWhiteSpace(snapshot.ExecutablePath) => (ProjectRuleType.ExecutablePath, snapshot.ExecutablePath!, 90),
            _ when !string.IsNullOrWhiteSpace(snapshot.WindowTitle) => WindowTitleLearning(snapshot),
            _ when !string.IsNullOrWhiteSpace(snapshot.ProcessName) => (ProjectRuleType.ProcessName, snapshot.ProcessName!, 55),
            _ => (ProjectRuleType.Keyword, string.Empty, 40)
        };
        if (string.IsNullOrWhiteSpace(pattern)) return null;
        var rule = await projects.AddRuleAsync(projectId, type, pattern, weight, 0, ct);
        await LogLearnedRuleAsync(projectId, rule, "single_snapshot");
        return rule;
    }

    public async Task<ProjectRule?> LearnFromSessionsAsync(string projectId, IEnumerable<ActivitySession> sessions, CancellationToken ct = default)
    {
        var source = sessions
            .Where(x => x.Source == ActivitySource.AutoForeground)
            .OrderBy(x => x.StartedAt)
            .ToList();
        if (source.Count == 0)
        {
            await AgentLog.WarnAsync("classification.learn", "project learning skipped because the selected event has no auto-foreground sessions", new { project_id = projectId });
            return null;
        }

        var contexts = source.Select(ActivityContextNormalizer.Describe).ToList();
        var stablePatterns = contexts
            .Select(x => x.StableWindowPattern)
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();
        if (stablePatterns.Count == 1)
        {
            var rule = await projects.AddRuleAsync(projectId, ProjectRuleType.WindowTitle, stablePatterns[0]!, 85, 0, ct);
            await LogLearnedRuleAsync(projectId, rule, "stable_context_pattern");
            return rule;
        }

        // First prefer an explicit Project code/name already present in the observed browser/IDE title.
        // This is deterministic and is safer than learning a whole volatile tab title.
        var project = (await projects.GetActiveAsync(ct)).FirstOrDefault(x =>
            string.Equals(x.Id, projectId, StringComparison.OrdinalIgnoreCase));
        if (project is not null)
        {
            var hints = new[] { project.Code, project.Name }
                .Where(x => !string.IsNullOrWhiteSpace(x) && x!.Trim().Length >= 3)
                .Select(x => x!.Trim())
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .OrderByDescending(x => x.Length);

            foreach (var hint in hints)
            {
                if (source.Any(x => !string.IsNullOrWhiteSpace(x.WindowTitle) &&
                    x.WindowTitle!.Contains(hint, StringComparison.OrdinalIgnoreCase)))
                {
                    var rule = await projects.AddRuleAsync(projectId, ProjectRuleType.WindowTitle, hint, 82, 0, ct);
                    await LogLearnedRuleAsync(projectId, rule, "project_name_or_code_in_title");
                    return rule;
                }
            }
        }

        // Browser tabs do not expose URL/domain without the browser extension. For an explicit
        // "Assign + Learn" action we can still learn a bounded stable title segment such as
        // "Ketabnow" from "پیشنهاد ارسال فایل ZIP - Ketabnow - Google Chrome". We deliberately
        // refuse a one-piece volatile title (e.g. "Bale Web (17)") so the rule does not become
        // an accidental exact-tab rule.
        if (contexts.All(x => x.Kind == ActivityContextKind.Browser))
        {
            var browserPattern = SuggestBrowserPattern(source);
            if (!string.IsNullOrWhiteSpace(browserPattern))
            {
                var rule = await projects.AddRuleAsync(projectId, ProjectRuleType.WindowTitle, browserPattern, 80, 0, ct);
                await LogLearnedRuleAsync(projectId, rule, "browser_stable_title_segment");
                return rule;
            }

            await AgentLog.WarnAsync("classification.learn", "browser learning could not derive a safe stable title segment", new
            {
                project_id = projectId,
                titles = source.Select(x => x.WindowTitle).Where(x => !string.IsNullOrWhiteSpace(x)).Distinct().Take(5).ToArray(),
            });
            return null;
        }

        var snapshot = new ForegroundSnapshot(0, 0, source[0].ProcessName, source[0].ExecutablePath, source[0].WindowTitle, source[0].StartedAt);
        return await LearnFromAsync(projectId, snapshot, ProjectRuleType.WindowTitle, ct);
    }

    public static string? SuggestBrowserPattern(IEnumerable<ActivitySession> sessions)
    {
        var perTitle = sessions
            .Where(x => x.Source == ActivitySource.AutoForeground && !string.IsNullOrWhiteSpace(x.WindowTitle))
            .Select(x => BrowserCandidates(ActivityContextNormalizer.NormalizeWindowTitle(x.WindowTitle!)))
            .Where(x => x.Count > 0)
            .ToList();

        if (perTitle.Count == 0) return null;

        // Prefer a candidate that survives every title represented by the selected Work Event.
        var common = new HashSet<string>(perTitle[0], StringComparer.OrdinalIgnoreCase);
        foreach (var set in perTitle.Skip(1)) common.IntersectWith(set);
        if (common.Count > 0)
            return PreferBrowserCandidate(common, perTitle[0]);

        // If the selected event has one title only, using its bounded segmented candidate is still
        // intentional because the user explicitly requested learning for that exact event.
        if (perTitle.Count == 1)
            return PreferBrowserCandidate(perTitle[0], perTitle[0]);

        return null;
    }

    private static List<string> BrowserCandidates(string normalizedTitle)
    {
        if (string.IsNullOrWhiteSpace(normalizedTitle)) return [];

        var parts = new List<string> { normalizedTitle.Trim() };
        foreach (var separator in BrowserSeparators)
        {
            parts = parts
                .SelectMany(part => part.Contains(separator, StringComparison.Ordinal)
                    ? part.Split(separator, StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
                    : [part])
                .ToList();
        }

        // A single unsplit browser title is intentionally not learned. It tends to contain volatile
        // counts/status text and does not provide enough evidence for a reusable project pattern.
        if (parts.Count < 2) return [];

        return parts
            .Select(CleanBrowserSegment)
            .Where(IsSafeBrowserSegment)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    private static string CleanBrowserSegment(string value)
    {
        var cleaned = BrowserCounterSuffix.Replace(value.Trim(), string.Empty).Trim(' ', '-', '—', '–', '|', ':');
        return cleaned.Length <= 80 ? cleaned : cleaned[..80].Trim();
    }

    private static bool IsSafeBrowserSegment(string value)
    {
        if (string.IsNullOrWhiteSpace(value) || value.Length < 3 || value.Length > 80) return false;
        if (GenericBrowserSegments.Contains(value)) return false;
        if (value.All(char.IsDigit)) return false;
        return value.Any(char.IsLetterOrDigit);
    }

    private static string? PreferBrowserCandidate(IEnumerable<string> candidates, IReadOnlyList<string> originalOrder)
    {
        var candidateSet = candidates.ToHashSet(StringComparer.OrdinalIgnoreCase);

        // Browser/project naming conventions commonly put the stable workspace/site label at the
        // right-hand side. Prefer that when it is safe (e.g. "... - Ketabnow").
        for (var i = originalOrder.Count - 1; i >= 0; i--)
            if (candidateSet.Contains(originalOrder[i]) && IsSafeBrowserSegment(originalOrder[i]))
                return originalOrder[i];

        return candidateSet.OrderBy(x => x.Length).ThenBy(x => x, StringComparer.OrdinalIgnoreCase).FirstOrDefault();
    }

    private static async Task LogLearnedRuleAsync(string projectId, ProjectRule rule, string strategy)
    {
        await AgentLog.InfoAsync("classification.learn", "project rule learned from explicit user correction", new
        {
            project_id = projectId,
            rule_id = rule.Id,
            rule_type = rule.Type.ToString(),
            @operator = rule.Operator,
            pattern = rule.Pattern,
            weight = rule.Weight,
            priority = rule.Priority,
            strategy,
        });
    }

    private static (ProjectRuleType Type, string Pattern, int Weight) WindowTitleLearning(ForegroundSnapshot snapshot)
    {
        var context = ActivityContextNormalizer.Describe(snapshot);
        var pattern = !string.IsNullOrWhiteSpace(context.StableWindowPattern)
            ? context.StableWindowPattern!
            : ActivityContextNormalizer.NormalizeWindowTitle(snapshot.WindowTitle!);
        var weight = context.Kind == ActivityContextKind.Ide ? 85 : 80;
        return (ProjectRuleType.WindowTitle, pattern, weight);
    }

    public static string NormalizeWindowTitle(string title) => ActivityContextNormalizer.NormalizeWindowTitle(title);
}
