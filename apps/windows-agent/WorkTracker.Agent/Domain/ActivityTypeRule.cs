namespace WorkTracker.Agent.Domain;

public enum ActivityTypeRuleType
{
    ProcessName,
    WindowTitle,
    ExecutablePath,
    ContextKey,
    Keyword
}

public sealed record ActivityTypeRule(
    string Id,
    string? ProjectId,
    string ActivityTypeId,
    ActivityTypeRuleType Type,
    string Operator,
    string Pattern,
    int Weight,
    int Priority,
    double Confidence,
    bool IsEnabled,
    int Version = 1);
