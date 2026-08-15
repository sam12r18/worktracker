using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Classification;

public sealed record ProjectResolution(string ProjectId, int Score, double Confidence, IReadOnlyList<string> Reasons);

public sealed class ProjectResolver
{
    private const int MinimumScore = 50;
    private const int MinimumWinningMargin = 10;

    public ProjectResolution? Resolve(ForegroundSnapshot snapshot, IEnumerable<ProjectRule> rules)
    {
        var candidates = new Dictionary<string, Candidate>();
        foreach (var rule in rules.Where(r => r.IsEnabled).OrderByDescending(r => r.Priority))
        {
            if (!Matches(snapshot, rule)) continue;
            if (!candidates.TryGetValue(rule.ProjectId, out var current)) current = new Candidate();
            current.Score += rule.Weight;
            current.HighestPriority = Math.Max(current.HighestPriority, rule.Priority);
            current.Reasons.Add($"{rule.Type}:{rule.Pattern}");
            candidates[rule.ProjectId] = current;
        }

        if (candidates.Count == 0) return null;
        var ranked = candidates
            .OrderByDescending(x => x.Value.HighestPriority)
            .ThenByDescending(x => x.Value.Score)
            .ToList();
        var winner = ranked[0];
        if (winner.Value.Score < MinimumScore) return null;

        if (ranked.Count > 1)
        {
            var runnerUp = ranked[1];
            if (runnerUp.Value.HighestPriority == winner.Value.HighestPriority &&
                winner.Value.Score - runnerUp.Value.Score < MinimumWinningMargin)
                return null; // ambiguous evidence is safer in Unknown Inbox than a false project attribution.
        }

        var confidence = Math.Min(1.0, winner.Value.Score / 100.0);
        return new ProjectResolution(winner.Key, winner.Value.Score, confidence, winner.Value.Reasons);
    }

    private static bool Matches(ForegroundSnapshot s, ProjectRule r)
    {
        static bool Has(string? value, string pattern) => value?.Contains(pattern, StringComparison.OrdinalIgnoreCase) == true;
        return r.Type switch
        {
            ProjectRuleType.Path or ProjectRuleType.ExecutablePath => Has(s.ExecutablePath, r.Pattern),
            ProjectRuleType.WindowTitle => Has(s.WindowTitle, r.Pattern),
            ProjectRuleType.ProcessName => Has(s.ProcessName, r.Pattern),
            ProjectRuleType.Keyword => Has(s.WindowTitle, r.Pattern) || Has(s.ExecutablePath, r.Pattern),
            _ => false
        };
    }

    private sealed class Candidate
    {
        public int Score { get; set; }
        public int HighestPriority { get; set; }
        public List<string> Reasons { get; } = new();
    }
}
