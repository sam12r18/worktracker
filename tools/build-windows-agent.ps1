$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repo = Split-Path -Parent $PSScriptRoot
$project = Join-Path $repo 'apps\windows-agent\WorkTracker.Agent\WorkTracker.Agent.csproj'

function Invoke-DotNetStep {
    param(
        [Parameter(Mandatory=$true)][string]$Label,
        [Parameter(Mandatory=$true)][string[]]$Arguments
    )

    Write-Host "==> $Label"
    & dotnet @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "dotnet $($Arguments -join ' ') failed with exit code $LASTEXITCODE."
    }
}

Invoke-DotNetStep -Label 'dotnet info' -Arguments @('--info')
Invoke-DotNetStep -Label 'restore' -Arguments @('restore', $project)
Invoke-DotNetStep -Label 'build Release' -Arguments @('build', $project, '-c', 'Release', '--no-restore')

Write-Host '==> Build succeeded.' -ForegroundColor Green
