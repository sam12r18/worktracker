namespace WorkTracker.Agent.Tracking;

public interface IIdleTimeProvider
{
    TimeSpan GetIdleTime();
}
