namespace WorkTracker.Agent.Domain;

public sealed record ActivityType(
    string Id,
    string Code,
    string Name,
    bool IsBillableDefault,
    long BaseHourlyRateMinor,
    string Currency,
    bool IsActive,
    int SortOrder = 0)
{
    public override string ToString() => Name;
}
