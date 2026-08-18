using System.IO;
using WorkTracker.Agent.Classification;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Integrations.Ide;
using System.Text.Json;
using WorkTracker.Agent.Services;
using WorkTracker.Agent.Tracking;

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
        TestActivityTypeProjectDefault(failures);
        TestActivityTypeExplicitDebugOverridesDefault(failures);
        TestExplicitSignalWithoutTaxonomyDoesNotFallBack(failures);
        TestActivityTypeExplicitTestingOverridesDefault(failures);
        TestDebugNamedSourceFileDoesNotTriggerDebugging(failures);
        TestActivityTypeConfiguredRule(failures);
        TestProjectScopedRuleWinsAtEqualPriority(failures);
        TestActivityTypeAmbiguousRulesStayUnknown(failures);
        TestBrowserAssignAndLearnPattern(failures);
        TestPhpStormPluginContextSelection(failures);
        TestPhpStormPluginDebugClassification(failures);
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


    private static void TestActivityTypeProjectDefault(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var types = Types();
        var snapshot = Snapshot("phpstorm64", "Ketabnow – app.php");
        var result = resolver.Resolve(snapshot, "A", "development", types, []);
        Expect(failures, result is not null && result.ActivityTypeId == "development" && result.Source == "project_default" && Math.Abs(result.Confidence - 0.72) < 0.001,
            "plain IDE work must use the configured Project default instead of a hard-coded Development guess");
    }

    private static void TestActivityTypeExplicitDebugOverridesDefault(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var result = resolver.Resolve(Snapshot("phpstorm64", "WorkTracker Debugger"), "A", "development", Types(), []);
        Expect(failures, result is not null && result.ActivityTypeId == "debugging" && result.Source == "ide_signal" && result.Confidence >= 0.98,
            "an explicit IDE Debug signal must override a Development Project default");
    }


    private static void TestExplicitSignalWithoutTaxonomyDoesNotFallBack(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var types = Types().Where(x => x.Id != "debugging").ToArray();
        var result = resolver.Resolve(Snapshot("phpstorm64", "WorkTracker Debugger"), "A", "development", types, []);
        Expect(failures, result is null,
            "an explicit Debug signal without a Debugging taxonomy must remain Unknown instead of silently falling back to Development");
    }

    private static void TestActivityTypeExplicitTestingOverridesDefault(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var result = resolver.Resolve(Snapshot("phpstorm64", "WorkTracker — PHPUnit Test Runner"), "A", "development", Types(), []);
        Expect(failures, result is not null && result.ActivityTypeId == "testing" && result.Source == "ide_signal" && result.Confidence >= 0.96,
            "an explicit PHPUnit/Test Runner signal must override a Development Project default");
    }

    private static void TestDebugNamedSourceFileDoesNotTriggerDebugging(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var result = resolver.Resolve(Snapshot("phpstorm64", "Ketabnow – DebugService.php"), "A", "development", Types(), []);
        Expect(failures, result is not null && result.ActivityTypeId == "development" && result.Source == "project_default",
            "a source file containing the word Debug must not be treated as an explicit debugger signal");
    }

    private static void TestActivityTypeConfiguredRule(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var rules = new[]
        {
            new ActivityTypeRule("r1", "A", "development", ActivityTypeRuleType.ProcessName, "equals", "phpstorm64", 80, 10, 0.91, true),
        };
        var result = resolver.Resolve(Snapshot("phpstorm64", "Ketabnow – app.php"), "A", null, Types(), rules);
        Expect(failures, result is not null && result.ActivityTypeId == "development" && result.Source == "rule" && result.Confidence >= 0.79,
            "a Project-scoped Activity Type Rule must classify matching IDE work");
    }

    private static void TestProjectScopedRuleWinsAtEqualPriority(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var rules = new[]
        {
            new ActivityTypeRule("g1", null, "development", ActivityTypeRuleType.ProcessName, "equals", "phpstorm64", 80, 0, 0.90, true),
            new ActivityTypeRule("p1", "A", "review", ActivityTypeRuleType.ProcessName, "equals", "phpstorm64", 80, 0, 0.93, true),
        };
        var result = resolver.Resolve(Snapshot("phpstorm64", "Ketabnow – app.php"), "A", null, Types(), rules);
        Expect(failures, result is not null && result.ActivityTypeId == "review" && result.Source == "rule",
            "a Project-scoped rule must outrank an equally-prioritized global rule because its scope is more specific");
    }

    private static void TestActivityTypeAmbiguousRulesStayUnknown(List<string> failures)
    {
        var resolver = new ActivityTypeResolver();
        var rules = new[]
        {
            new ActivityTypeRule("r1", null, "development", ActivityTypeRuleType.ProcessName, "equals", "phpstorm64", 60, 0, 0.90, true),
            new ActivityTypeRule("r2", null, "review", ActivityTypeRuleType.ProcessName, "equals", "phpstorm64", 55, 0, 0.90, true),
        };
        var result = resolver.Resolve(Snapshot("phpstorm64", "Ketabnow – app.php"), "A", null, Types(), rules);
        Expect(failures, result is null, "two close Activity Type candidates with the same priority must remain Unknown");
    }


    private static void TestBrowserAssignAndLearnPattern(List<string> failures)
    {
        var sessions = new[]
        {
            S("browser-1", "A", 0, 30, "chrome", "پیشنهاد ارسال فایل ZIP - Ketabnow - Google Chrome"),
            S("browser-2", "A", 30, 60, "chrome", "Health مشکل و راه حل - Ketabnow - Google Chrome"),
        };
        var pattern = ProjectClassificationService.SuggestBrowserPattern(sessions);
        Expect(failures, string.Equals(pattern, "Ketabnow", StringComparison.OrdinalIgnoreCase),
            "explicit browser Assign + Learn must derive the stable project-like title segment instead of an exact volatile tab title");
    }


    private static void TestPhpStormPluginContextSelection(List<string> failures)
    {
        var root = Path.Combine(Path.GetTempPath(), "worktracker-ide-selftest-" + Guid.NewGuid().ToString("N"));
        var phpStormDir = Path.Combine(root, "phpstorm");
        Directory.CreateDirectory(phpStormDir);
        try
        {
            var now = DateTimeOffset.UtcNow;
            var first = new IdeContextSnapshot(1, "0.1.0-alpha.8.0", "PhpStorm", "261", 4242, "Ketabnow", @"I:\ketabnow", "app.php", @"I:\ketabnow\app.php", "main", "idle", null, null, now, "phpstorm-plugin");
            var second = new IdeContextSnapshot(1, "0.1.0-alpha.8.0", "PhpStorm", "261", 4242, "WorkTracker", @"I:\worktracker", "TrackingEngine.cs", @"I:\worktracker\TrackingEngine.cs", "alpha8", "debug", "WorkTracker.Agent", "PHP", now, "phpstorm-plugin");
            File.WriteAllText(Path.Combine(phpStormDir, "context-4242-a.json"), JsonSerializer.Serialize(first));
            File.WriteAllText(Path.Combine(phpStormDir, "context-4242-b.json"), JsonSerializer.Serialize(second));

            var service = new IdeContextBridgeService(root);
            var foreground = new ForegroundSnapshot(0, 4242, "phpstorm64", null, "WorkTracker – TrackingEngine.cs", now);
            var enriched = service.EnrichAsync(foreground).GetAwaiter().GetResult();
            Expect(failures, enriched.IdeContext?.ProjectName == "WorkTracker" && enriched.IdeContext.Mode == "debug",
                "PhpStorm bridge must select the project context matching the foreground window when one IDE process hosts multiple projects");
        }
        finally
        {
            try { Directory.Delete(root, true); } catch { }
        }
    }

    private static void TestPhpStormPluginDebugClassification(List<string> failures)
    {
        var now = DateTimeOffset.UtcNow;
        var ide = new IdeContextSnapshot(1, "0.1.0-alpha.8.0", "PhpStorm", "261", 4242, "WorkTracker", @"I:\worktracker", "SyncEngine.cs", @"I:\worktracker\SyncEngine.cs", "alpha8", "debug", "WorkTracker.Agent", "PHP", now, "phpstorm-plugin");
        var snapshot = new ForegroundSnapshot(0, 4242, "phpstorm64", null, "WorkTracker – SyncEngine.cs", now, ide);
        var context = ActivityContextNormalizer.Describe(snapshot);
        var result = new ActivityTypeResolver().Resolve(snapshot, "A", "development", Types(), []);
        Expect(failures, context.Key.Contains("worktracker", StringComparison.OrdinalIgnoreCase),
            "PhpStorm plugin project identity must stabilize the IDE ContextKey independently of the active file");
        Expect(failures, result is not null && result.ActivityTypeId == "debugging" && result.Source == "ide_plugin" && result.Confidence >= 0.999,
            "PhpStorm plugin Debug state must classify Debugging with deterministic confidence 1.0");
    }

    private static IReadOnlyList<ActivityType> Types() => new[]
    {
        new ActivityType("development", "development", "Development", true, 0, "IRT", true),
        new ActivityType("debugging", "debugging", "Debugging", true, 0, "IRT", true),
        new ActivityType("testing", "testing", "Testing", true, 0, "IRT", true),
        new ActivityType("review", "review", "Code Review", true, 0, "IRT", true),
    };

    private static ForegroundSnapshot Snapshot(string process, string title)
        => new(0, 1, process, null, title, DateTimeOffset.UtcNow);

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
