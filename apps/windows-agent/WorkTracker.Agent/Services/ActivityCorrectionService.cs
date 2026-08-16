using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Services;

public sealed record ActivityCorrectionResult(
    IReadOnlyList<ActivitySession> Sessions,
    ProjectRule? LearnedRule);

public sealed class ActivityCorrectionService(ActivitySessionRepository activities, ProjectClassificationService classifier)
{
    public async Task<ActivitySession?> AssignAsync(ActivitySession session, string projectId, bool learnWindowTitle, CancellationToken ct = default)
    {
        var corrected = await activities.AssignProjectAsync(session.Id, projectId, learnWindowTitle ? "user_correction+learned_rule" : "user_correction", 1.0, ct);
        if (corrected is null || !learnWindowTitle) return corrected;
        var snapshot = new ForegroundSnapshot(0, 0, session.ProcessName, session.ExecutablePath, session.WindowTitle, DateTimeOffset.UtcNow);
        await classifier.LearnFromAsync(projectId, snapshot, ProjectRuleType.WindowTitle, ct);
        return corrected;
    }

    public async Task<ActivityCorrectionResult> AssignEventAsync(IReadOnlyList<ActivitySession> sessions, string projectId, bool learnWindowTitle, CancellationToken ct = default)
    {
        var source = sessions.Where(x => x.Source == ActivitySource.AutoForeground).ToList();
        if (source.Count == 0) source = sessions.ToList();
        var corrected = await activities.AssignProjectsAsync(source.Select(x => x.Id), projectId, learnWindowTitle ? "user_event_correction+learned_rule" : "user_event_correction", 1.0, ct);
        ProjectRule? learnedRule = null;
        if (learnWindowTitle && corrected.Count > 0)
            learnedRule = await classifier.LearnFromSessionsAsync(projectId, corrected, ct);
        return new ActivityCorrectionResult(corrected, learnedRule);
    }
}
