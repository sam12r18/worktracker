param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-p]{32}$')]
    [string]$ExtensionId,
    [ValidateSet('Debug', 'Release')]
    [string]$Configuration = 'Release',
    [string]$HostExe
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$project = Join-Path $repoRoot 'apps\windows-agent\WorkTracker.BrowserBridge\WorkTracker.BrowserBridge.csproj'

if ([string]::IsNullOrWhiteSpace($HostExe)) {
    Write-Host '==> Building WorkTracker.BrowserBridge'
    dotnet build $project -c $Configuration
    if ($LASTEXITCODE -ne 0) { throw 'BrowserBridge build failed.' }
    $HostExe = Join-Path $repoRoot "apps\windows-agent\WorkTracker.BrowserBridge\bin\$Configuration\net10.0-windows\WorkTracker.BrowserBridge.exe"
}
if (-not (Test-Path $HostExe)) { throw "Native host executable not found: $HostExe" }

$hostExeResolved = (Resolve-Path $HostExe).Path
$manifestDir = Join-Path $env:LOCALAPPDATA 'WorkTracker\browser-host'
$manifestPath = Join-Path $manifestDir 'ir.rayaasun.worktracker.browser.json'
New-Item -ItemType Directory -Force -Path $manifestDir | Out-Null
$manifest = [ordered]@{
    name = 'ir.rayaasun.worktracker.browser'
    description = 'WorkTracker Browser Context Bridge'
    path = $hostExeResolved
    type = 'stdio'
    allowed_origins = @("chrome-extension://$ExtensionId/")
}
$json = $manifest | ConvertTo-Json -Depth 5
[System.IO.File]::WriteAllText($manifestPath, $json, [System.Text.UTF8Encoding]::new($false))
$regPath = 'HKCU:\Software\Google\Chrome\NativeMessagingHosts\ir.rayaasun.worktracker.browser'
New-Item -Force -Path $regPath | Out-Null
Set-Item -Path $regPath -Value $manifestPath
Write-Host 'Chrome Native Messaging host registered.'
Write-Host "Extension ID : $ExtensionId"
Write-Host "Manifest     : $manifestPath"
Write-Host "Executable   : $hostExeResolved"
Write-Host 'Restart Chrome after registration.'
