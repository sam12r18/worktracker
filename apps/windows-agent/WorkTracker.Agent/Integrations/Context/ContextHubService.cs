using WorkTracker.Agent.Diagnostics;
using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Integrations.Context;

public sealed class ContextHubService
{
    private readonly IReadOnlyList<IContextProvider> _providers;

    public ContextHubService(IEnumerable<IContextProvider> providers)
    {
        ArgumentNullException.ThrowIfNull(providers);

        var resolved = providers.ToArray();
        if (resolved.Length == 0)
            throw new ArgumentException("At least one Context provider is required.", nameof(providers));

        foreach (var provider in resolved)
        {
            if (provider is null)
                throw new ArgumentException("Context providers cannot contain null entries.", nameof(providers));
            if (string.IsNullOrWhiteSpace(provider.ProviderId))
                throw new ArgumentException("Every Context provider must expose a non-empty ProviderId.", nameof(providers));
        }

        var duplicateProvider = resolved
            .GroupBy(x => x.ProviderId, StringComparer.OrdinalIgnoreCase)
            .FirstOrDefault(x => x.Count() > 1);
        if (duplicateProvider is not null)
            throw new ArgumentException($"Duplicate Context provider id: {duplicateProvider.Key}", nameof(providers));

        _providers = resolved;
        ProviderIds = resolved.Select(x => x.ProviderId).ToArray();
    }

    public IReadOnlyList<string> ProviderIds { get; }

    public async Task<ForegroundSnapshot> EnrichAsync(
        ForegroundSnapshot snapshot,
        CancellationToken ct = default)
    {
        var current = snapshot;

        foreach (var provider in _providers)
        {
            ct.ThrowIfCancellationRequested();
            try
            {
                current = await provider.EnrichAsync(current, ct);
            }
            catch (OperationCanceledException) when (ct.IsCancellationRequested)
            {
                throw;
            }
            catch (Exception ex)
            {
                await AgentLog.WarnAsync(
                    "context.hub",
                    "context provider failed; foreground observation continues without that enrichment",
                    new
                    {
                        provider = provider.ProviderId,
                        process_name = current.ProcessName,
                        process_id = current.ProcessId,
                        exception = ex.GetType().Name,
                        message = ex.Message,
                    });
            }
        }

        return current;
    }
}
