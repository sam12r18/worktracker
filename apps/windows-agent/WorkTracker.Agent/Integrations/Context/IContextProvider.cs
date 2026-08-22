using WorkTracker.Agent.Tracking;

namespace WorkTracker.Agent.Integrations.Context;

public interface IContextProvider
{
    string ProviderId { get; }

    Task<ForegroundSnapshot> EnrichAsync(
        ForegroundSnapshot snapshot,
        CancellationToken ct = default);
}
