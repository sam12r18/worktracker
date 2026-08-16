using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Services;

public static class WorkEventAggregationService
{
    public const int ContinuityBridgeMaxSeconds = 120;
    public const int ContinuityAnchorMinimumSeconds = 120;
    private static readonly TimeSpan MergeGap = TimeSpan.FromSeconds(15);

    public static IReadOnlyList<WorkEvent> Aggregate(IEnumerable<ActivitySession> source)
    {
        var sessions = source.OrderBy(x => x.StartedAt).ThenBy(x => x.EndedAt).ToList();
        var automatic = sessions.Where(x => x.Source == ActivitySource.AutoForeground).ToList();
        var manual = sessions.Where(x => x.Source != ActivitySource.AutoForeground).ToList();

        var foregroundEvents = BuildForegroundEvents(automatic);
        ApplyContinuityBridges(foregroundEvents);

        var result = foregroundEvents.Where(x => !x.Absorbed).Select(x => x.ToWorkEvent()).ToList();
        result.AddRange(manual.Select(CreateManualEvent));
        return result.OrderByDescending(x => x.StartedAt).ThenByDescending(x => x.EndedAt).ToList();
    }

    public static int TotalBridgeSeconds(IEnumerable<WorkEvent> events)
        => events.Sum(x => x.BridgeSeconds);

    private static List<EventBuilder> BuildForegroundEvents(IReadOnlyList<ActivitySession> sessions)
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

    private static void ApplyContinuityBridges(List<EventBuilder> events)
    {
        for (var i = 0; i < events.Count; i++)
        {
            var anchor = events[i];
            if (anchor.Absorbed || string.IsNullOrWhiteSpace(anchor.ProjectId)) continue;

            var directSinceLastBridge = anchor.DirectSeconds;
            var searchFrom = i;

            while (directSinceLastBridge >= ContinuityAnchorMinimumSeconds)
            {
                var nextAnchorIndex = FindReturn(events, searchFrom + 1, anchor.ProjectId!, anchor.EndedAt);
                if (nextAnchorIndex < 0) break;

                // A bridge is created only when we actually observed at least one foreground
                // interruption between the two anchor spans. Unobserved gaps (idle, pause, WorkTracker
                // UI itself, sleep) are never credited automatically.
                if (nextAnchorIndex <= searchFrom + 1 || !HasContinuousObservedInterruption(events, searchFrom, nextAnchorIndex)) break;

                var returned = events[nextAnchorIndex];
                var bridgeStart = anchor.EndedAt;
                var bridgeEnd = returned.StartedAt;
                var bridgeSeconds = Math.Max(0, (int)Math.Floor((bridgeEnd - bridgeStart).TotalSeconds));
                if (bridgeSeconds <= 0 || bridgeSeconds > ContinuityBridgeMaxSeconds) break;

                var interruptedProjects = events
                    .Skip(searchFrom + 1)
                    .Take(nextAnchorIndex - searchFrom - 1)
                    .Select(x => x.ProjectId)
                    .Where(x => !string.IsNullOrWhiteSpace(x) && !string.Equals(x, anchor.ProjectId, StringComparison.OrdinalIgnoreCase))
                    .Select(x => x!)
                    .Distinct(StringComparer.OrdinalIgnoreCase)
                    .ToList();

                anchor.Bridges.Add(new ContinuityBridge(bridgeStart, bridgeEnd, bridgeSeconds, anchor.ProjectId!, interruptedProjects));
                anchor.Merge(returned);
                returned.Absorbed = true;

                // Anchor-based anti-oscillation: another interruption can be bridged only after
                // at least 120 seconds of direct foreground work after the previous return.
                directSinceLastBridge = returned.DirectSeconds;
                searchFrom = nextAnchorIndex;
            }
        }
    }


    private static bool HasContinuousObservedInterruption(IReadOnlyList<EventBuilder> events, int anchorIndex, int returnIndex)
    {
        var cursor = events[anchorIndex].EndedAt;
        for (var index = anchorIndex + 1; index < returnIndex; index++)
        {
            var interruption = events[index];
            if (interruption.Absorbed) continue;
            if (interruption.StartedAt > cursor + MergeGap) return false;
            if (interruption.EndedAt > cursor) cursor = interruption.EndedAt;
        }
        return events[returnIndex].StartedAt <= cursor + MergeGap;
    }

    private static int FindReturn(IReadOnlyList<EventBuilder> events, int startIndex, string projectId, DateTimeOffset interruptionStartedAt)
    {
        for (var index = startIndex; index < events.Count; index++)
        {
            var candidate = events[index];
            if (candidate.Absorbed) continue;
            if ((candidate.StartedAt - interruptionStartedAt).TotalSeconds > ContinuityBridgeMaxSeconds) return -1;
            if (string.Equals(candidate.ProjectId, projectId, StringComparison.OrdinalIgnoreCase)) return index;
        }
        return -1;
    }

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

        public string GroupKey { get; }
        public string? ProjectId { get; }
        public DateTimeOffset StartedAt { get; private set; }
        public DateTimeOffset EndedAt { get; private set; }
        public List<ActivitySession> Sessions { get; } = [];
        public List<ContinuityBridge> Bridges { get; } = [];
        public bool Absorbed { get; set; }
        public int DirectSeconds => Sessions.Sum(x => x.DurationSeconds);

        public void Add(ActivitySession session)
        {
            Sessions.Add(session);
            if (session.StartedAt < StartedAt) StartedAt = session.StartedAt;
            if (session.EndedAt > EndedAt) EndedAt = session.EndedAt;
        }

        public void Merge(EventBuilder other)
        {
            Sessions.AddRange(other.Sessions);
            Bridges.AddRange(other.Bridges);
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
