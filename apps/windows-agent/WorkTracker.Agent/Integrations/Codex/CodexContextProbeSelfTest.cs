namespace WorkTracker.Agent.Integrations.Codex;

public static class CodexContextProbeSelfTest
{
    public static IReadOnlyList<string> Run()
    {
        var failures = new List<string>();

        var titleOnly = CodexContextProbe.ResolveUniqueProjectPath(new[] { "Codex" });
        if (titleOnly is not null)
            failures.Add("Codex static title alone must never resolve a project path");

        var single = CodexContextProbe.ResolveUniqueProjectPath(new[]
        {
            @"Workspace I:\worktracker"
        });
        if (!string.Equals(single, @"I:\worktracker", StringComparison.OrdinalIgnoreCase))
            failures.Add("Codex probe should resolve one explicit Windows workspace path");

        var ambiguous = CodexContextProbe.ResolveUniqueProjectPath(new[]
        {
            @"I:\worktracker",
            @"C:\projects\municipal-works"
        });
        if (ambiguous is not null)
            failures.Add("Codex probe must stay Unknown when multiple project paths are visible");

        return failures;
    }
}
