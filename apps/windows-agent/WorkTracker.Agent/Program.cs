using System.IO;
using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Integrations.Codex;
using WorkTracker.Agent.Integrations.Context;
using WorkTracker.Agent.Services;

namespace WorkTracker.Agent;

internal static class Program
{
    private const string SelfTestArgument = "--self-test-activity-intelligence";

    [STAThread]
    public static int Main(string[] args)
    {
        if (args.Any(x => string.Equals(x, SelfTestArgument, StringComparison.OrdinalIgnoreCase)))
            return RunDeterministicSelfTests();

        var app = new App();
        app.InitializeComponent();
        return app.Run();
    }

    private static int RunDeterministicSelfTests()
    {
        var failures = ActivityIntelligenceSelfTest.Run().ToList();
        failures.AddRange(IntegrationStatusSelfTest.Run());
        failures.AddRange(CodexContextProbeSelfTest.Run());
        failures.AddRange(LocalLaravelServerSelfTest.Run());

        var output = Path.Combine(Path.GetTempPath(), "worktracker-activity-intelligence-self-test.txt");
        IEnumerable<string> lines = failures.Count == 0
            ? new[] { "PASS: Activity Intelligence, Context Integration and local Laravel startup deterministic scenarios" }
            : failures.Select(x => $"FAIL: {x}");
        File.WriteAllLines(output, lines);
        return failures.Count == 0 ? 0 : 2;
    }
}
