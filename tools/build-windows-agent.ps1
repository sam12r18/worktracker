$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repo = Split-Path -Parent $PSScriptRoot
$agentRoot = Join-Path $repo 'apps\windows-agent\WorkTracker.Agent'
$project = Join-Path $agentRoot 'WorkTracker.Agent.csproj'
$agentBinRoot = Join-Path $agentRoot 'bin'

function Get-RepositoryAgentProcesses {
    $binPrefix = [System.IO.Path]::GetFullPath($agentBinRoot).TrimEnd('\') + '\'
    $result = @()

    foreach ($process in @(Get-Process -Name 'WorkTracker.Agent' -ErrorAction SilentlyContinue)) {
        $processPath = $null
        try {
            $processPath = $process.MainModule.FileName
        }
        catch {
            # Access to Path can fail for a process owned by another user. Such a process
            # cannot normally be the repository build output that this script owns.
        }

        if ([string]::IsNullOrWhiteSpace($processPath)) {
            continue
        }

        $fullPath = [System.IO.Path]::GetFullPath($processPath)
        if ($fullPath.StartsWith($binPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            $result += $process
        }
    }

    return @($result)
}

function Stop-RepositoryAgentForBuild {
    $running = @(Get-RepositoryAgentProcesses)
    if ($running.Count -eq 0) {
        return
    }

    $processList = ($running | ForEach-Object { "PID $($_.Id)" }) -join ', '
    Write-Host "==> WorkTracker.Agent is running from this repository ($processList)." -ForegroundColor Yellow
    Write-Host '    Stopping it before build so MSBuild can replace WorkTracker.Agent.exe.' -ForegroundColor Yellow

    foreach ($process in $running) {
        try {
            Stop-Process -Id $process.Id -Force -ErrorAction Stop
        }
        catch {
            throw "Could not stop WorkTracker.Agent process $($process.Id). Exit the Agent from the System Tray and run the build again. $($_.Exception.Message)"
        }
    }

    $deadline = [DateTime]::UtcNow.AddSeconds(5)
    do {
        Start-Sleep -Milliseconds 150
        $stillRunning = @(Get-RepositoryAgentProcesses)
    } while ($stillRunning.Count -gt 0 -and [DateTime]::UtcNow -lt $deadline)

    if ($stillRunning.Count -gt 0) {
        $remaining = ($stillRunning | ForEach-Object { $_.Id }) -join ', '
        throw "WorkTracker.Agent is still running (PID: $remaining). Exit it from the System Tray or Task Manager, then run the build again."
    }
}

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

Stop-RepositoryAgentForBuild
Invoke-DotNetStep -Label 'dotnet info' -Arguments @('--info')
Invoke-DotNetStep -Label 'restore' -Arguments @('restore', $project)
Invoke-DotNetStep -Label 'build Release' -Arguments @('build', $project, '-c', 'Release', '--no-restore')

Write-Host '==> Build succeeded.' -ForegroundColor Green
