using System.Text.RegularExpressions;
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
            current.Reasons.Add($"{rule.Type}:{rule.Operator}:{rule.Pattern}");
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
                return null;
        }

        var confidence = Math.Min(1.0, winner.Value.Score / 100.0);
        return new ProjectResolution(winner.Key, winner.Value.Score, confidence, winner.Value.Reasons);
    }

    private static bool Matches(ForegroundSnapshot snapshot, ProjectRule rule)
    {
        return rule.Type switch
        {
            ProjectRuleType.Path => MatchValue(snapshot.IdeContext?.ProjectPath ?? snapshot.ExecutablePath, rule),
            ProjectRuleType.ExecutablePath => MatchValue(snapshot.ExecutablePath, rule),
            ProjectRuleType.WindowTitle => MatchValue(snapshot.WindowTitle, rule),
            ProjectRuleType.ProcessName => MatchValue(snapshot.ProcessName, rule),
            ProjectRuleType.BrowserHost => MatchValue(snapshot.BrowserContext?.Host, rule),
            ProjectRuleType.BrowserPath => MatchValue(snapshot.BrowserContext?.Path, rule),
            ProjectRuleType.BrowserTitle => MatchValue(snapshot.BrowserContext?.Title, rule),
            ProjectRuleType.Keyword => MatchValue(snapshot.WindowTitle, rule)
                                      || MatchValue(snapshot.ExecutablePath, rule)
                                      || MatchValue(snapshot.IdeContext?.ProjectName, rule)
                                      || MatchValue(snapshot.IdeContext?.ProjectPath, rule)
                                      || MatchValue(snapshot.IdeContext?.CurrentFilePath, rule)
                                      || MatchValue(snapshot.BrowserContext?.Host, rule)
                                      || MatchValue(snapshot.BrowserContext?.Path, rule)
                                      || MatchValue(snapshot.BrowserContext?.Title, rule),
            _ => false
        };
    }

    private static bool MatchValue(string? value, ProjectRule rule)
    {
        if (string.IsNullOrWhiteSpace(value) || string.IsNullOrWhiteSpace(rule.Pattern)) return false;

        return (rule.Operator ?? "contains").Trim().ToLowerInvariant() switch
        {
            "equals" => string.Equals(value, rule.Pattern, StringComparison.OrdinalIgnoreCase),
            "starts_with" => value.StartsWith(rule.Pattern, StringComparison.OrdinalIgnoreCase),
            "ends_with" => value.EndsWith(rule.Pattern, StringComparison.OrdinalIgnoreCase),
            "regex" => SafeRegexIsMatch(value, rule.Pattern),
            _ => value.Contains(rule.Pattern, StringComparison.OrdinalIgnoreCase),
        };
    }

    private static bool SafeRegexIsMatch(string value, string pattern)
    {
        try
        {
            return Regex.IsMatch(value, pattern, RegexOptions.IgnoreCase | RegexOptions.CultureInvariant, TimeSpan.FromMilliseconds(150));
        }
        catch (ArgumentException)
        {
            return false;
        }
        catch (RegexMatchTimeoutException)
        {
            return false;
        }
    }

    private sealed class Candidate
    {
        public int Score { get; set; }
        public int HighestPriority { get; set; }
        public List<string> Reasons { get; } = new();
    }
}
