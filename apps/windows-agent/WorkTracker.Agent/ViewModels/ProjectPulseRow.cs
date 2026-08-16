namespace WorkTracker.Agent.ViewModels;

public sealed record ProjectPulseRow(
    string ProjectId,
    string Project,
    string Duration,
    string Direct,
    string Bridge,
    string Application,
    string State,
    bool IsActive);
