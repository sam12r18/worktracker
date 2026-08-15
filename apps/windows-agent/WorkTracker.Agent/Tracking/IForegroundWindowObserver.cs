namespace WorkTracker.Agent.Tracking;

public interface IForegroundWindowObserver
{
    ForegroundSnapshot? Capture();
}
