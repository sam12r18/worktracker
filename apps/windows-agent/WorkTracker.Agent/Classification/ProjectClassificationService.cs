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
            _ when !string.IsNullOrWhiteSpace(snapshot.WindowTitle) => (ProjectRuleType.WindowTitle, NormalizeWindowTitle(snapshot.WindowTitle!), 80),
            _ when !string.IsNullOrWhiteSpace(snapshot.ProcessName) => (ProjectRuleType.ProcessName, snapshot.ProcessName!, 55),
            _ => (ProjectRuleType.Keyword, string.Empty, 40)
        };
        if (string.IsNullOrWhiteSpace(pattern)) return null;
        return await projects.AddRuleAsync(projectId, type, pattern, weight, 0, ct);
    }

    public static string NormalizeWindowTitle(string title)
    {
        title = title.Trim();
        foreach (var suffix in new[] { " - Google Chrome", " — Mozilla Firefox", " - Microsoft Edge", " – PhpStorm", " - Visual Studio Code" })
            if (title.EndsWith(suffix, StringComparison.OrdinalIgnoreCase)) title = title[..^suffix.Length].Trim();
        return title.Length <= 120 ? title : title[..120];
    }
}
