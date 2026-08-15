$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$repo = Split-Path -Parent $PSScriptRoot
$project = Join-Path $repo 'apps\windows-agent\WorkTracker.Agent\WorkTracker.Agent.csproj'
& dotnet run --project $project
if ($LASTEXITCODE -ne 0) { throw "WorkTracker.Agent exited with code $LASTEXITCODE." }
