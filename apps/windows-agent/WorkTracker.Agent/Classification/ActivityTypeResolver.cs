using System.Text.RegularExpressions;
using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Classification;

public sealed record ActivityTypeResolution(
    string ActivityTypeId,
    double Confidence,
    string Source,
    string Reason,
    int Score = 0,
    IReadOnlyList<string>? Matches = null);

public sealed class ActivityTypeResolver
{
    private const int MinimumRuleScore = 40;
    private const int MinimumWinningMargin = 10;

    public ActivityTypeResolution? Resolve(
        ForegroundSnapshot snapshot,
        string? projectId,
        string? projectDefaultActivityTypeId,
        IReadOnlyList<ActivityType> activityTypes,
        IReadOnlyList<ActivityTypeRule> rules)
    {
        var activeTypes = activityTypes.Where(x => x.IsActive).ToList();
        var context = ActivityContextNormalizer.Describe(snapshot);

        var explicitSignal = ResolveExplicitIdeSignal(snapshot, activeTypes);
        if (explicitSignal.Recognized) return explicitSignal.Resolution;

        var ruleResolution = ResolveRules(snapshot, context, projectId, activeTypes, rules);
        if (ruleResolution is not null) return ruleResolution;

        if (!string.IsNullOrWhiteSpace(projectDefaultActivityTypeId) &&
            activeTypes.Any(x => string.Equals(x.Id, projectDefaultActivityTypeId, StringComparison.OrdinalIgnoreCase)))
        {
            return new ActivityTypeResolution(
                projectDefaultActivityTypeId!,
                0.72,
                "project_default",
                "project_default_activity_type");
        }

        return null;
    }

    private static (bool Recognized, ActivityTypeResolution? Resolution) ResolveExplicitIdeSignal(ForegroundSnapshot snapshot, IReadOnlyList<ActivityType> types)
    {
        var process = (snapshot.ProcessName ?? string.Empty).Trim().ToLowerInvariant();
        var isIde = process is "phpstorm64" or "phpstorm" or "code" or "code-insiders" or "devenv";
        if (!isIde) return (false, null);

        var signal = $"{snapshot.WindowTitle} {snapshot.ProcessName}".ToLowerInvariant();

        if (ContainsAny(signal, "debugger", "[debug]", " debug: ", " - debug ", " – debug ", " دیباگر "))
            return (true, MatchType(types, ["debug", "debugging", "دیباگ"], 0.99, "ide_signal", "ide_debug_signal"));

        if (ContainsAny(signal, "phpunit", "test runner", "test results", "unit test runner", "[test]", " اجرای تست "))
            return (true, MatchType(types, ["test", "testing", "تست"], 0.97, "ide_signal", "ide_test_signal"));

        if (ContainsAny(signal, "code review", "review changes", "pull request", "merge request", "git diff", "diff viewer"))
            return (true, MatchType(types, ["review", "code review", "بازبینی"], 0.92, "ide_signal", "ide_review_signal"));

        return (false, null);
    }

    private static ActivityTypeResolution? ResolveRules(
        ForegroundSnapshot snapshot,
        ActivityContextDescriptor context,
        string? projectId,
        IReadOnlyList<ActivityType> types,
        IReadOnlyList<ActivityTypeRule> rules)
    {
        var typeIds = types.Select(x => x.Id).ToHashSet(StringComparer.OrdinalIgnoreCase);
        var candidates = new Dictionary<string, Candidate>(StringComparer.OrdinalIgnoreCase);

        foreach (var rule in rules.Where(x => x.IsEnabled && typeIds.Contains(x.ActivityTypeId)))
        {
            if (!string.IsNullOrWhiteSpace(rule.ProjectId) && !string.Equals(rule.ProjectId, projectId, StringComparison.OrdinalIgnoreCase)) continue;
            if (!Matches(snapshot, context, rule)) continue;

            if (!candidates.TryGetValue(rule.ActivityTypeId, out var candidate)) candidate = new Candidate();
            candidate.Score += rule.Weight;
            candidate.HighestPriority = Math.Max(candidate.HighestPriority, rule.Priority);
            candidate.ScopeSpecificity = Math.Max(candidate.ScopeSpecificity, string.IsNullOrWhiteSpace(rule.ProjectId) ? 0 : 1);
            candidate.Confidence = Math.Max(candidate.Confidence, Math.Clamp(rule.Confidence, 0.5, 1.0));
            candidate.Matches.Add($"{rule.Type}:{rule.Operator}:{rule.Pattern}");
            candidates[rule.ActivityTypeId] = candidate;
        }

        if (candidates.Count == 0) return null;
        var ranked = candidates
            .OrderByDescending(x => x.Value.HighestPriority)
            .ThenByDescending(x => x.Value.ScopeSpecificity)
            .ThenByDescending(x => x.Value.Score)
            .ToList();
        var winner = ranked[0];
        if (winner.Value.Score < MinimumRuleScore) return null;

        if (ranked.Count > 1)
        {
            var runnerUp = ranked[1];
            if (runnerUp.Value.HighestPriority == winner.Value.HighestPriority &&
                runnerUp.Value.ScopeSpecificity == winner.Value.ScopeSpecificity &&
                winner.Value.Score - runnerUp.Value.Score < MinimumWinningMargin)
                return null;
        }

        var scoreConfidence = Math.Clamp(winner.Value.Score / 100.0, 0.5, 1.0);
        var confidence = Math.Round(Math.Min(winner.Value.Confidence, scoreConfidence), 4);
        return new ActivityTypeResolution(winner.Key, confidence, "rule", "activity_type_rule_match", winner.Value.Score, winner.Value.Matches);
    }

    private static bool Matches(ForegroundSnapshot snapshot, ActivityContextDescriptor context, ActivityTypeRule rule)
    {
        return rule.Type switch
        {
            ActivityTypeRuleType.ProcessName => MatchValue(snapshot.ProcessName, rule),
            ActivityTypeRuleType.WindowTitle => MatchValue(snapshot.WindowTitle, rule),
            ActivityTypeRuleType.ExecutablePath => MatchValue(snapshot.ExecutablePath, rule),
            ActivityTypeRuleType.ContextKey => MatchValue(context.Key, rule),
            ActivityTypeRuleType.Keyword => MatchValue($"{snapshot.WindowTitle} {snapshot.ExecutablePath} {context.Key}", rule),
            _ => false,
        };
    }

    private static bool MatchValue(string? value, ActivityTypeRule rule)
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

    private static ActivityTypeResolution? MatchType(IReadOnlyList<ActivityType> types, string[] keywords, double confidence, string source, string reason)
    {
        var match = types.FirstOrDefault(type => keywords.Any(keyword =>
            type.Code.Contains(keyword, StringComparison.OrdinalIgnoreCase) ||
            type.Name.Contains(keyword, StringComparison.OrdinalIgnoreCase)));
        return match is null ? null : new ActivityTypeResolution(match.Id, confidence, source, reason, 100, [reason]);
    }

    private static bool ContainsAny(string value, params string[] keywords)
        => keywords.Any(keyword => value.Contains(keyword, StringComparison.OrdinalIgnoreCase));

    private static bool SafeRegexIsMatch(string value, string pattern)
    {
        try
        {
            return Regex.IsMatch(value, pattern, RegexOptions.IgnoreCase | RegexOptions.CultureInvariant, TimeSpan.FromMilliseconds(150));
        }
        catch (ArgumentException) { return false; }
        catch (RegexMatchTimeoutException) { return false; }
    }

    private sealed class Candidate
    {
        public int Score { get; set; }
        public int HighestPriority { get; set; } = int.MinValue;
        public int ScopeSpecificity { get; set; }
        public double Confidence { get; set; } = 0.5;
        public List<string> Matches { get; } = [];
    }
}
