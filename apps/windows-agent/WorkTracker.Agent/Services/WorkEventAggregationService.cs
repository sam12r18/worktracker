using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Services;

public static class WorkEventAggregationService
{
    public const int ContinuityBridgeMaxSeconds = 120;
    public const int ContinuityInitialAnchorMinimumSeconds = 60;
    public const int ContinuityBridgeRearmSeconds = 120;
    private static readonly TimeSpan MergeGap = TimeSpan.FromSeconds(15);

    public static IReadOnlyList<WorkEvent> Aggregate(IEnumerable<ActivitySession> source)
        => AggregateWithDiagnostics(source).Events;

    public static WorkEventAggregationResult AggregateWithDiagnostics(IEnumerable<ActivitySession> source)
    {
        var sessions = source.OrderBy(x => x.StartedAt).ThenBy(x => x.EndedAt).ToList();
        var automatic = sessions.Where(x => x.Source == ActivitySource.AutoForeground).ToList();
        var manual = sessions.Where(x => x.Source != ActivitySource.AutoForeground).ToList();

        // Base spans remain immutable for the bridge projection. This is intentional: multiple
        // projects may bridge overlapping foreground interruptions at the same time. A direct span
        // that participates in Project A's bridge must remain available to Project B/C projections.
        var baseSpans = BuildForegroundSpans(automatic);
        var decisions = new List<WorkEventAggregationDecision>();
        var projected = BuildProjectWorkEvents(baseSpans, decisions);

        // Unknown foreground contexts are still useful in Timeline/learning, but they do not own a
        // project continuity chain until the user classifies them.
        projected.AddRange(baseSpans
            .Where(x => string.IsNullOrWhiteSpace(x.ProjectId))
            .Select(x => x.ToWorkEvent()));

        projected.AddRange(manual.Select(CreateManualEvent));

        return new WorkEventAggregationResult(
            projected.OrderByDescending(x => x.StartedAt).ThenByDescending(x => x.EndedAt).ToList(),
            decisions);
    }

    public static int TotalBridgeSeconds(IEnumerable<WorkEvent> events)
        => events.Sum(x => x.BridgeSeconds);

    private static List<EventBuilder> BuildForegroundSpans(IReadOnlyList<ActivitySession> sessions)
    {
        var result = new List<EventBuilder>();
        foreach (var session in sessions)
        {
            var groupKey = ForegroundGroupKey(session);
            var last = result.LastOrDefault();
            if (last is not null &&
                string.Equals(last.GroupKey, groupKey, StringComparison.OrdinalIgnoreCase) &&
                session.StartedAt <= last.EndedAt + MergeGap)
            {
                last.Add(session);
                continue;
            }

            result.Add(new EventBuilder(groupKey, session));
        }
        return result;
    }

    private static List<WorkEvent> BuildProjectWorkEvents(
        IReadOnlyList<EventBuilder> baseSpans,
        List<WorkEventAggregationDecision> decisions)
    {
        var result = new List<WorkEvent>();
        var projectIds = baseSpans
            .Select(x => x.ProjectId)
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Select(x => x!)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();

        foreach (var projectId in projectIds)
        {
            var directSpans = baseSpans
                .Where(x => string.Equals(x.ProjectId, projectId, StringComparison.OrdinalIgnoreCase))
                .OrderBy(x => x.StartedAt)
                .ThenBy(x => x.EndedAt)
                .ToList();
            if (directSpans.Count == 0) continue;

            var chain = directSpans[0].Clone();
            var directSinceLastBridge = chain.DirectSeconds;
            var hasBridge = false;
            decisions.Add(new(projectId, WorkEventAggregationState.Direct, chain.StartedAt, "anchor_started", directSinceLastBridge));

            for (var index = 1; index < directSpans.Count; index++)
            {
                var next = directSpans[index];

                if (next.StartedAt <= chain.EndedAt + MergeGap)
                {
                    chain.MergeDirect(next);
                    directSinceLastBridge += next.DirectSeconds;
                    decisions.Add(new(projectId, WorkEventAggregationState.Direct, next.StartedAt, "same_project_continuation", directSinceLastBridge));
                    continue;
                }

                var gapSeconds = Math.Max(0, (int)Math.Floor((next.StartedAt - chain.EndedAt).TotalSeconds));
                var interruptedProjects = FindInterruptedProjects(baseSpans, chain.EndedAt, next.StartedAt, projectId);
                decisions.Add(new(projectId, WorkEventAggregationState.Suspended, chain.EndedAt, "foreground_left_project", directSinceLastBridge, gapSeconds, interruptedProjects));

                var requiredDirectSeconds = hasBridge
                    ? ContinuityBridgeRearmSeconds
                    : ContinuityInitialAnchorMinimumSeconds;

                var eligibleByAnchor = directSinceLastBridge >= requiredDirectSeconds;
                var eligibleByGap = gapSeconds > 0 && gapSeconds <= ContinuityBridgeMaxSeconds;
                var fullyObserved = eligibleByGap && HasContinuousObservedInterruption(baseSpans, chain.EndedAt, next.StartedAt, projectId);

                if (eligibleByAnchor && eligibleByGap && fullyObserved)
                {
                    decisions.Add(new(projectId, WorkEventAggregationState.BridgeCandidate, chain.EndedAt, "bounded_observed_interruption", directSinceLastBridge, gapSeconds, interruptedProjects));
                    chain.Bridges.Add(new ContinuityBridge(
                        chain.EndedAt,
                        next.StartedAt,
                        gapSeconds,
                        projectId,
                        interruptedProjects));
                    chain.MergeDirect(next);
                    hasBridge = true;
                    directSinceLastBridge = next.DirectSeconds;
                    decisions.Add(new(projectId, WorkEventAggregationState.Bridged, next.StartedAt, "continuity_restored", directSinceLastBridge, gapSeconds, interruptedProjects));
                    continue;
                }

                var reason = !eligibleByAnchor
                    ? hasBridge ? "bridge_rearm_not_ready" : "initial_anchor_not_ready"
                    : !eligibleByGap
                        ? "interruption_exceeds_bridge_limit"
                        : "interruption_not_continuously_observed";

                decisions.Add(new(projectId, WorkEventAggregationState.Closed, chain.EndedAt, reason, directSinceLastBridge, gapSeconds, interruptedProjects));
                result.Add(chain.ToWorkEvent());

                chain = next.Clone();
                directSinceLastBridge = chain.DirectSeconds;
                hasBridge = false;
                decisions.Add(new(projectId, WorkEventAggregationState.Direct, chain.StartedAt, "anchor_started", directSinceLastBridge));
            }

            decisions.Add(new(projectId, WorkEventAggregationState.Closed, chain.EndedAt, "end_of_observed_data", directSinceLastBridge));
            result.Add(chain.ToWorkEvent());
        }

        return result;
    }

    private static bool HasContinuousObservedInterruption(
        IReadOnlyList<EventBuilder> spans,
        DateTimeOffset gapStart,
        DateTimeOffset gapEnd,
        string anchorProjectId)
    {
        var interruptions = spans
            .Where(x => x.EndedAt > gapStart && x.StartedAt < gapEnd &&
                        !string.Equals(x.ProjectId, anchorProjectId, StringComparison.OrdinalIgnoreCase))
            .OrderBy(x => x.StartedAt)
            .ToList();
        if (interruptions.Count == 0) return false;

        var cursor = gapStart;
        foreach (var interruption in interruptions)
        {
            var start = interruption.StartedAt < gapStart ? gapStart : interruption.StartedAt;
            var end = interruption.EndedAt > gapEnd ? gapEnd : interruption.EndedAt;
            if (start > cursor + MergeGap) return false;
            if (end > cursor) cursor = end;
            if (cursor >= gapEnd - MergeGap) return true;
        }

        return cursor >= gapEnd - MergeGap;
    }

    private static IReadOnlyList<string> FindInterruptedProjects(
        IReadOnlyList<EventBuilder> spans,
        DateTimeOffset gapStart,
        DateTimeOffset gapEnd,
        string anchorProjectId)
        => spans
            .Where(x => x.EndedAt > gapStart && x.StartedAt < gapEnd)
            .Select(x => x.ProjectId)
            .Where(x => !string.IsNullOrWhiteSpace(x) && !string.Equals(x, anchorProjectId, StringComparison.OrdinalIgnoreCase))
            .Select(x => x!)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();

    private static string ForegroundGroupKey(ActivitySession session)
    {
        if (!string.IsNullOrWhiteSpace(session.ProjectId)) return $"project:{session.ProjectId}";
        var context = ActivityContextNormalizer.Describe(session);
        return $"unknown:{context.Key}";
    }

    private static WorkEvent CreateManualEvent(ActivitySession session)
        => new(session.Id, session.ProjectId, session.StartedAt, session.EndedAt, session.DurationSeconds, session.DurationSeconds, 0, [session], [], $"manual:{session.Id}");

    private sealed class EventBuilder
    {
        public EventBuilder(string groupKey, ActivitySession first)
        {
            GroupKey = groupKey;
            ProjectId = first.ProjectId;
            StartedAt = first.StartedAt;
            EndedAt = first.EndedAt;
            Sessions.Add(first);
        }

        private EventBuilder(string groupKey, string? projectId, DateTimeOffset startedAt, DateTimeOffset endedAt, IEnumerable<ActivitySession> sessions)
        {
            GroupKey = groupKey;
            ProjectId = projectId;
            StartedAt = startedAt;
            EndedAt = endedAt;
            Sessions.AddRange(sessions);
        }

        public string GroupKey { get; }
        public string? ProjectId { get; }
        public DateTimeOffset StartedAt { get; private set; }
        public DateTimeOffset EndedAt { get; private set; }
        public List<ActivitySession> Sessions { get; } = [];
        public List<ContinuityBridge> Bridges { get; } = [];
        public int DirectSeconds => Sessions.Sum(x => x.DurationSeconds);

        public EventBuilder Clone()
            => new(GroupKey, ProjectId, StartedAt, EndedAt, Sessions);

        public void Add(ActivitySession session)
        {
            Sessions.Add(session);
            if (session.StartedAt < StartedAt) StartedAt = session.StartedAt;
            if (session.EndedAt > EndedAt) EndedAt = session.EndedAt;
        }

        public void MergeDirect(EventBuilder other)
        {
            Sessions.AddRange(other.Sessions);
            if (other.StartedAt < StartedAt) StartedAt = other.StartedAt;
            if (other.EndedAt > EndedAt) EndedAt = other.EndedAt;
        }

        public WorkEvent ToWorkEvent()
        {
            var ordered = Sessions.OrderBy(x => x.StartedAt).ThenBy(x => x.EndedAt).ToList();
            var direct = ordered.Sum(x => x.DurationSeconds);
            var bridge = Bridges.Sum(x => x.DurationSeconds);
            return new WorkEvent(ordered[0].Id, ProjectId, StartedAt, EndedAt, direct + bridge, direct, bridge, ordered, Bridges.ToList(), GroupKey);
        }
    }
}
