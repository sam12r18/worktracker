using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Classification;

public sealed class ActivityTypeInferenceService(
    ActivityTypeRepository activityTypes,
    ActivityTypeRuleRepository activityTypeRules,
    ProjectRepository projects)
{
    private readonly ActivityTypeResolver _resolver = new();

    public async Task<ActivityTypeResolution?> ResolveAsync(ForegroundSnapshot snapshot, string? projectId, CancellationToken ct = default)
    {
        var types = await activityTypes.GetActiveAsync(ct);
        var rules = await activityTypeRules.GetEnabledAsync(ct);
        var project = string.IsNullOrWhiteSpace(projectId) ? null : await projects.GetByIdAsync(projectId, ct);
        var resolution = _resolver.Resolve(snapshot, projectId, project?.DefaultActivityTypeId, types, rules);

        if (resolution is not null)
        {
            await AgentLog.InfoAsync("activity.type", "activity type resolved", new
            {
                project_id = projectId,
                activity_type_id = resolution.ActivityTypeId,
                resolution.Confidence,
                resolution.Source,
                resolution.Reason,
                resolution.Score,
                matches = resolution.Matches,
                process = snapshot.ProcessName,
                title = snapshot.WindowTitle,
                context_key = ActivityContextNormalizer.Describe(snapshot).Key,
            });
        }

        return resolution;
    }
}
