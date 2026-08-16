using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Classification;

public sealed class ProjectClassificationService(ProjectRepository projects)
{
    private readonly ProjectResolver _resolver = new();

    public async Task<ProjectResolution?> ResolveAsync(ForegroundSnapshot snapshot, CancellationToken ct = default)
        => _resolver.Resolve(snapshot, await projects.GetRulesAsync(ct));

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
        return await projects.AddRuleAsync(projectId, type, pattern, weight, 0, ct);
    }

    public async Task<ProjectRule?> LearnFromSessionsAsync(string projectId, IEnumerable<ActivitySession> sessions, CancellationToken ct = default)
    {
        var source = sessions
            .Where(x => x.Source == ActivitySource.AutoForeground)
            .OrderBy(x => x.StartedAt)
            .ToList();
        if (source.Count == 0) return null;

        var contexts = source.Select(ActivityContextNormalizer.Describe).ToList();
        var stablePatterns = contexts
            .Select(x => x.StableWindowPattern)
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();
        if (stablePatterns.Count == 1)
            return await projects.AddRuleAsync(projectId, ProjectRuleType.WindowTitle, stablePatterns[0]!, 85, 0, ct);

        // Browsers do not expose a reliable URL/domain yet. Prefer a selected Project name/code
        // that is actually present in the observed titles instead of learning one exact tab title
        // or, worse, a broad ProcessName=chrome rule.
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
                    return await projects.AddRuleAsync(projectId, ProjectRuleType.WindowTitle, hint, 82, 0, ct);
                }
            }
        }

        // Do not fabricate an exact browser-tab rule when no stable Project hint exists. The Web
        // Rule Builder can preview a manually chosen pattern against recent activity until the
        // browser extension provides URL/domain context in 0.2.
        if (contexts.All(x => x.Kind == ActivityContextKind.Browser)) return null;

        var snapshot = new ForegroundSnapshot(0, 0, source[0].ProcessName, source[0].ExecutablePath, source[0].WindowTitle, source[0].StartedAt);
        return await LearnFromAsync(projectId, snapshot, ProjectRuleType.WindowTitle, ct);
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
