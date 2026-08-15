namespace WorkTracker.Agent.Classification;

public sealed record ProjectRule(
    string Id,
    string ProjectId,
    ProjectRuleType Type,
    string Pattern,
    int Weight,
    int Priority,
    bool IsEnabled = true);

public enum ProjectRuleType
{
    Path,
    WindowTitle,
    ProcessName,
    ExecutablePath,
    Keyword
}
