using WorkTracker.Agent.Domain;

namespace WorkTracker.Agent.Services;

public sealed record TimeAccountingSummary(int EffortSeconds, int ElapsedCoverageSeconds, int ConcurrentEffortSeconds);

public static class TimeAccountingService
{
    public static TimeAccountingSummary Summarize(IEnumerable<ActivitySession> sessions, DateTimeOffset rangeStart, DateTimeOffset rangeEnd, int additionalEffortSeconds = 0)
    {
        var intervals = new List<(DateTimeOffset Start, DateTimeOffset End)>();
        var effort = Math.Max(0, additionalEffortSeconds);

        foreach (var session in sessions)
        {
            var start = session.StartedAt < rangeStart ? rangeStart : session.StartedAt;
            var end = session.EndedAt > rangeEnd ? rangeEnd : session.EndedAt;
            if (end <= start) continue;

            effort += Math.Max(0, (int)Math.Floor((end - start).TotalSeconds));
            intervals.Add((start, end));
        }

        var coverage = UnionSeconds(intervals);
        return new TimeAccountingSummary(effort, coverage, Math.Max(0, effort - coverage));
    }

    private static int UnionSeconds(List<(DateTimeOffset Start, DateTimeOffset End)> intervals)
    {
        if (intervals.Count == 0) return 0;
        intervals.Sort((a, b) => a.Start.CompareTo(b.Start));

        var total = 0d;
        var currentStart = intervals[0].Start;
        var currentEnd = intervals[0].End;

        for (var i = 1; i < intervals.Count; i++)
        {
            var next = intervals[i];
            if (next.Start <= currentEnd)
            {
                if (next.End > currentEnd) currentEnd = next.End;
                continue;
            }

            total += (currentEnd - currentStart).TotalSeconds;
            currentStart = next.Start;
            currentEnd = next.End;
        }

        total += (currentEnd - currentStart).TotalSeconds;
        return Math.Max(0, (int)Math.Floor(total));
    }
}
