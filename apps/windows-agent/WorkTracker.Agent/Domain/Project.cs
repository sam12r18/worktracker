namespace WorkTracker.Agent.Domain;

public sealed record Project(
    string Id,
    string Name,
    string? Code,
    string? ParentId,
    string Status = "active",
    DateTimeOffset? UpdatedAt = null,
    string? CustomerId = null,
    decimal RateMultiplier = 1.0000m,
    bool IsBillableDefault = true)
{
    public override string ToString() => string.IsNullOrWhiteSpace(Code) ? Name : $"{Name} ({Code})";
}
