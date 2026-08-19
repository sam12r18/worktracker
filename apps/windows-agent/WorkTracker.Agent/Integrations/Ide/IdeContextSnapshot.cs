using System.IO;
using System.Text.Json.Serialization;

namespace WorkTracker.Agent.Integrations.Ide;

public sealed record IdeContextSnapshot(
    [property: JsonPropertyName("protocol_version")] int ProtocolVersion,
    [property: JsonPropertyName("plugin_version")] string? PluginVersion,
    [property: JsonPropertyName("ide_product")] string? IdeProduct,
    [property: JsonPropertyName("ide_build")] string? IdeBuild,
    [property: JsonPropertyName("process_id")] int ProcessId,
    [property: JsonPropertyName("project_name")] string? ProjectName,
    [property: JsonPropertyName("project_path")] string? ProjectPath,
    [property: JsonPropertyName("current_file")] string? CurrentFile,
    [property: JsonPropertyName("current_file_path")] string? CurrentFilePath,
    [property: JsonPropertyName("git_branch")] string? GitBranch,
    [property: JsonPropertyName("execution_mode")] string? ExecutionMode,
    [property: JsonPropertyName("run_configuration")] string? RunConfiguration,
    [property: JsonPropertyName("run_configuration_type")] string? RunConfigurationType,
    [property: JsonPropertyName("observed_at_utc")] DateTimeOffset ObservedAtUtc,
    [property: JsonPropertyName("source")] string? Source)
{
    public const int SupportedProtocolVersion = 1;

    public bool IsSupported => ProtocolVersion == SupportedProtocolVersion;

    public string Mode => string.IsNullOrWhiteSpace(ExecutionMode)
        ? "idle"
        : ExecutionMode.Trim().ToLowerInvariant();

    public string ProjectDisplay => !string.IsNullOrWhiteSpace(ProjectName)
        ? ProjectName!
        : !string.IsNullOrWhiteSpace(ProjectPath)
            ? Path.GetFileName(ProjectPath!.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar))
            : "-";
}

public sealed record IdeContextBridgeStatus(
    bool Connected,
    bool Stale,
    string Provider,
    string Project,
    string File,
    string Mode,
    string Branch,
    string RunConfiguration,
    DateTimeOffset? ObservedAtUtc,
    int AgeSeconds,
    string Message)
{
    public static IdeContextBridgeStatus Disconnected(string message)
        => new(false, false, "PhpStorm Plugin", "-", "-", "-", "-", "-", null, 0, message);
}
