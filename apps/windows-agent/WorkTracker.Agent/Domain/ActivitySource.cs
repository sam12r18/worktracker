namespace WorkTracker.Agent.Domain;

public enum ActivitySource
{
    AutoForeground,
    ManualTimer,
    ManualEntry,
    IdleReclassified
}

public static class ActivitySourceExtensions
{
    public static string ToStorageValue(this ActivitySource source) => source switch
    {
        ActivitySource.AutoForeground => "auto_foreground",
        ActivitySource.ManualTimer => "manual_timer",
        ActivitySource.ManualEntry => "manual_entry",
        ActivitySource.IdleReclassified => "idle_reclassified",
        _ => throw new ArgumentOutOfRangeException(nameof(source), source, null)
    };

    public static ActivitySource FromStorageValue(string value) => value switch
    {
        "auto_foreground" => ActivitySource.AutoForeground,
        "manual_timer" => ActivitySource.ManualTimer,
        "manual_entry" => ActivitySource.ManualEntry,
        "idle_reclassified" => ActivitySource.IdleReclassified,
        _ => throw new InvalidOperationException($"Unknown activity source: {value}")
    };
}
