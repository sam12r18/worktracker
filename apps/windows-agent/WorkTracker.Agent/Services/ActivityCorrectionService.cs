using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Services;

public sealed class ActivityCorrectionService(ActivitySessionRepository activities, ProjectRepository projects, ProjectClassificationService classifier)
{
    public async Task<ActivitySession?> AssignAsync(ActivitySession session, string projectId, bool learnWindowTitle, CancellationToken ct = default)
    {
        var corrected = await activities.AssignProjectAsync(session.Id, projectId, learnWindowTitle ? "user_correction+learned_rule" : "user_correction", 1.0, ct);
        if (corrected is null || !learnWindowTitle) return corrected;
        var snapshot = new ForegroundSnapshot(0, 0, session.ProcessName, session.ExecutablePath, session.WindowTitle, DateTimeOffset.UtcNow);
        await classifier.LearnFromAsync(projectId, snapshot, ProjectRuleType.WindowTitle, ct);
        return corrected;
    }
}
