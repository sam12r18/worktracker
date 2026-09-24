using System.Reflection;

namespace WorkTracker.Agent.Services;

public static class LocalLaravelServerSelfTest
{
    public static IReadOnlyList<string> Run()
    {
        var failures = new List<string>();
        var serviceType = typeof(LocalLaravelServerSelfTest).Assembly.GetType("WorkTracker.Agent.Services.LocalLaravelServerService");
        if (serviceType is null)
        {
            failures.Add("Local Laravel startup service must exist");
            return failures;
        }

        var buildPlan = serviceType.GetMethod(
            "BuildLaunchPlan",
            BindingFlags.Static | BindingFlags.Public | BindingFlags.NonPublic);
        if (buildPlan is null)
        {
            failures.Add("Local Laravel startup service must expose deterministic launch planning");
            return failures;
        }

        var apiDirectory = Path.Combine("I:\\worktracker", "apps", "api");
        var localPlan = buildPlan.Invoke(null, new object?[] { "http://127.0.0.1:8082", apiDirectory });
        Expect(failures, localPlan is not null,
            "127.0.0.1 HTTP API must be eligible for local Laravel auto-start");

        if (localPlan is not null)
        {
            var planType = localPlan.GetType();
            var healthUri = planType.GetProperty("HealthUri")?.GetValue(localPlan)?.ToString();
            var port = planType.GetProperty("Port")?.GetValue(localPlan);
            var host = planType.GetProperty("Host")?.GetValue(localPlan)?.ToString();
            var workingDirectory = planType.GetProperty("WorkingDirectory")?.GetValue(localPlan)?.ToString();

            Expect(failures, string.Equals(healthUri, "http://127.0.0.1:8082/worktracker/health", StringComparison.OrdinalIgnoreCase),
                "local Laravel health URI must target /worktracker/health on the configured API origin");
            Expect(failures, Equals(port, 8082),
                "local Laravel auto-start must reuse the configured API port");
            Expect(failures, string.Equals(host, "127.0.0.1", StringComparison.OrdinalIgnoreCase),
                "local Laravel auto-start must bind to the configured loopback host");
            Expect(failures, string.Equals(workingDirectory, apiDirectory, StringComparison.OrdinalIgnoreCase),
                "local Laravel auto-start must run from apps/api");
        }

        var localhostPlan = buildPlan.Invoke(null, new object?[] { "http://localhost:8082", apiDirectory });
        Expect(failures, localhostPlan is not null,
            "localhost HTTP API must be eligible for local Laravel auto-start");

        var remotePlan = buildPlan.Invoke(null, new object?[] { "https://tracker.example.com", apiDirectory });
        Expect(failures, remotePlan is null,
            "remote API must never trigger a local Laravel process");

        var httpsLocalPlan = buildPlan.Invoke(null, new object?[] { "https://127.0.0.1:8082", apiDirectory });
        Expect(failures, httpsLocalPlan is null,
            "HTTPS loopback API must not be replaced by php artisan serve HTTP");

        var emptyPlan = buildPlan.Invoke(null, new object?[] { "", apiDirectory });
        Expect(failures, emptyPlan is null,
            "empty API configuration must not trigger a local Laravel process");

        return failures;
    }

    private static void Expect(List<string> failures, bool condition, string message)
    {
        if (!condition) failures.Add(message);
    }
}
