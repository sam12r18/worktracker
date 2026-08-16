using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Services;

namespace WorkTracker.Agent.Diagnostics;

public static class ActivityIntelligenceSelfTest
{
    public static IReadOnlyList<string> Run()
    {
        var failures = new List<string>();
        TestSameProjectAggregation(failures);
        TestInitialAnchorAtSixtySeconds(failures);
        TestAnchorBelowSixtySeconds(failures);
        TestMutualBridgeScenario(failures);
        TestThreeProjectOverlappingBridges(failures);
        TestBridgeRearm(failures);
        TestLongGapDoesNotBridge(failures);
        TestUnobservedGapDoesNotBridge(failures);
        return failures;
    }

    private static void TestSameProjectAggregation(List<string> failures)
    {
        var sessions = new[]
        {
            S("a1", "A", 0, 600, "phpstorm64", "Ketabnow – README.md"),
            S("a2", "A", 600, 1200, "phpstorm64", "Ketabnow – app.php"),
        };
        var events = WorkEventAggregationService.Aggregate(sessions).Where(x => x.ProjectId == "A").ToList();
        Expect(failures, events.Count == 1 && events[0].DirectSeconds == 1200 && events[0].BridgeSeconds == 0,
            "same-project file/title changes must remain one Work Event");
    }

    private static void TestInitialAnchorAtSixtySeconds(List<string> failures)
    {
        var sessions = new[]
        {
            S("a1", "A", 0, 60),
            S("b1", "B", 60, 90),
            S("a2", "A", 90, 120),
        };
        var a = WorkEventAggregationService.Aggregate(sessions).Where(x => x.ProjectId == "A").ToList();
        Expect(failures, a.Count == 1 && a[0].DirectSeconds == 90 && a[0].BridgeSeconds == 30 && a[0].CreditedSeconds == 120,
            "60 seconds of initial direct work must qualify the anchor for a bounded bridge");
    }

    private static void TestAnchorBelowSixtySeconds(List<string> failures)
    {
        var sessions = new[]
        {
            S("a1", "A", 0, 50),
            S("b1", "B", 50, 80),
            S("a2", "A", 80, 110),
        };
        var a = WorkEventAggregationService.Aggregate(sessions).Where(x => x.ProjectId == "A").ToList();
        Expect(failures, a.Count == 2 && a.Sum(x => x.BridgeSeconds) == 0,
            "an anchor below 60 seconds must not create a bridge");
    }

    private static void TestMutualBridgeScenario(List<string> failures)
    {
        // 10:00–10:10 A, 10:10–10:11 B, 10:11–10:12 A,
        // 10:12–10:20 B, 10:20–10:30 A.
        var sessions = new[]
        {
            S("a1", "A", 0, 600),
            S("b1", "B", 600, 660),
            S("a2", "A", 660, 720),
            S("b2", "B", 720, 1200),
            S("a3", "A", 1200, 1800),
        };
        var result = WorkEventAggregationService.Aggregate(sessions);
        var a = result.Where(x => x.ProjectId == "A").ToList();
        var b = result.Where(x => x.ProjectId == "B").ToList();

        Expect(failures, a.Count == 2 && a.Sum(x => x.DirectSeconds) == 1260 && a.Sum(x => x.BridgeSeconds) == 60 && a.Sum(x => x.CreditedSeconds) == 1320,
            "Project A must keep its first short interruption as a bridge but close before the later 8-minute interruption");
        Expect(failures, b.Count == 1 && b.Sum(x => x.DirectSeconds) == 540 && b.Sum(x => x.BridgeSeconds) == 60 && b.Sum(x => x.CreditedSeconds) == 600,
            "Project B must independently bridge the 60-second return to Project A; mutual bridges are valid");
        Expect(failures, result.Sum(x => x.CreditedSeconds) == 1920,
            "mutual bridges must allow 32 minutes of credited effort over 30 minutes of wall time in the scenario");
    }

    private static void TestThreeProjectOverlappingBridges(List<string> failures)
    {
        // A and B both have valid independent continuity windows while C is a short interruption.
        // A bridge: 10:01:00–10:02:30 (90s), B bridge: 10:02:00–10:03:00 (60s).
        // The two bridge intervals overlap for 30 seconds and must both remain valid.
        var sessions = new[]
        {
            S("a1", "A", 0, 60),
            S("b1", "B", 60, 120),
            S("c1", "C", 120, 150),
            S("a2", "A", 150, 180),
            S("b2", "B", 180, 210),
        };
        var result = WorkEventAggregationService.Aggregate(sessions);
        var a = result.Where(x => x.ProjectId == "A").ToList();
        var b = result.Where(x => x.ProjectId == "B").ToList();
        var c = result.Where(x => x.ProjectId == "C").ToList();

        Expect(failures, a.Count == 1 && a.Sum(x => x.DirectSeconds) == 90 && a.Sum(x => x.BridgeSeconds) == 90,
            "Project A must keep its 90-second bridge across B/C activity");
        Expect(failures, b.Count == 1 && b.Sum(x => x.DirectSeconds) == 90 && b.Sum(x => x.BridgeSeconds) == 60,
            "Project B must independently keep its overlapping 60-second bridge across C/A activity");
        Expect(failures, c.Sum(x => x.CreditedSeconds) == 30 && result.Sum(x => x.CreditedSeconds) == 360,
            "three-project projection must preserve 360 seconds of additive effort over 210 seconds of wall coverage");
    }

    private static void TestBridgeRearm(List<string> failures)
    {
        var sessions = new[]
        {
            S("a1", "A", 0, 120),
            S("b1", "B", 120, 150),
            S("a2", "A", 150, 210),
            S("c1", "C", 210, 240),
            S("a3", "A", 240, 300),
        };
        var a = WorkEventAggregationService.Aggregate(sessions).Where(x => x.ProjectId == "A").ToList();
        Expect(failures, a.Count == 2 && a.Sum(x => x.BridgeSeconds) == 30,
            "after a bridge, less than 120 seconds of direct work must not re-arm a second bridge");
    }

    private static void TestLongGapDoesNotBridge(List<string> failures)
    {
        var sessions = new[]
        {
            S("a1", "A", 0, 180),
            S("b1", "B", 180, 301),
            S("a2", "A", 301, 360),
        };
        var a = WorkEventAggregationService.Aggregate(sessions).Where(x => x.ProjectId == "A").ToList();
        Expect(failures, a.Count == 2 && a.Sum(x => x.BridgeSeconds) == 0,
            "an interruption longer than 120 seconds must not bridge");
    }

    private static void TestUnobservedGapDoesNotBridge(List<string> failures)
    {
        var sessions = new[]
        {
            S("a1", "A", 0, 120),
            S("a2", "A", 180, 240),
        };
        var a = WorkEventAggregationService.Aggregate(sessions).Where(x => x.ProjectId == "A").ToList();
        Expect(failures, a.Count == 2 && a.Sum(x => x.BridgeSeconds) == 0,
            "an unobserved foreground gap must not be credited as continuity");
    }

    private static ActivitySession S(string id, string projectId, int startSeconds, int endSeconds, string process = "app", string? title = null)
    {
        var origin = new DateTimeOffset(2026, 8, 16, 10, 0, 0, TimeSpan.FromHours(3.5));
        var start = origin.AddSeconds(startSeconds);
        var end = origin.AddSeconds(endSeconds);
        return new ActivitySession(
            id, "test-user", "test-device", projectId, null, ActivitySource.AutoForeground,
            process, null, title ?? projectId, 1.0, "self_test", start, end,
            Math.Max(0, endSeconds - startSeconds), 0, null);
    }

    private static void Expect(List<string> failures, bool condition, string message)
    {
        if (!condition) failures.Add(message);
    }
}
