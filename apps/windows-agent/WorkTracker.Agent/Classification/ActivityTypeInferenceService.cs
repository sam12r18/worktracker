using WorkTracker.Agent.Domain;
using WorkTracker.Agent.Storage;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Classification;

public sealed record ActivityTypeInference(string ActivityTypeId, string Reason);

public sealed class ActivityTypeInferenceService(ActivityTypeRepository activityTypes)
{
    private IReadOnlyList<ActivityType> _cache = [];
    private DateTimeOffset _cacheExpiresAt = DateTimeOffset.MinValue;

    public async Task<ActivityTypeInference?> ResolveAsync(ForegroundSnapshot snapshot, CancellationToken ct = default)
    {
        var process = (snapshot.ProcessName ?? string.Empty).Trim().ToLowerInvariant();
        if (process != "phpstorm64" && process != "phpstorm" && process != "code" && process != "code-insiders" && process != "devenv") return null;

        var signal = $"{snapshot.WindowTitle} {snapshot.ProcessName}".ToLowerInvariant();
        if (ContainsAny(signal, "debug", "debugger", "دیباگ"))
            return await MatchTypeAsync(["debug", "debugging", "دیباگ"], "ide_debug_signal", ct);

        if (ContainsAny(signal, "phpunit", "test runner", "unit test", "tests", "testing", "تست"))
            return await MatchTypeAsync(["test", "testing", "تست"], "ide_test_signal", ct);

        // Plain IDE foreground is deliberately not auto-labeled Development. The IDE may be used
        // for code review, Git, terminal, database tools, tests or debugging. Deep IDE adapters in
        // 0.2 will provide an explicit mode signal; until then Unknown is safer for billing.
        return null;
    }

    private async Task<ActivityTypeInference?> MatchTypeAsync(string[] keywords, string reason, CancellationToken ct)
    {
        var types = await GetTypesAsync(ct);
        var match = types.FirstOrDefault(type => keywords.Any(keyword =>
            type.Code.Contains(keyword, StringComparison.OrdinalIgnoreCase) ||
            type.Name.Contains(keyword, StringComparison.OrdinalIgnoreCase)));
        return match is null ? null : new ActivityTypeInference(match.Id, reason);
    }

    private async Task<IReadOnlyList<ActivityType>> GetTypesAsync(CancellationToken ct)
    {
        if (DateTimeOffset.UtcNow < _cacheExpiresAt && _cache.Count > 0) return _cache;
        _cache = await activityTypes.GetActiveAsync(ct);
        _cacheExpiresAt = DateTimeOffset.UtcNow.AddMinutes(2);
        return _cache;
    }

    private static bool ContainsAny(string value, params string[] keywords)
        => keywords.Any(keyword => value.Contains(keyword, StringComparison.OrdinalIgnoreCase));
}
