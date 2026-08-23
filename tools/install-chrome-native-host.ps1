param(
    [string]$ExtensionId = '',
    [ValidateSet('Debug', 'Release')]
    [string]$Configuration = 'Release',
    [string]$HostExe
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$extensionRoot = Join-Path $repoRoot 'apps\chrome-extension'
$project = Join-Path $repoRoot 'apps\windows-agent\WorkTracker.BrowserBridge\WorkTracker.BrowserBridge.csproj'

function Show-ExtensionIdHelp {
    Write-Host ''
    Write-Host 'Chrome Extension ID is not the literal text EXTENSION_ID.' -ForegroundColor Yellow
    Write-Host '1. Open chrome://extensions' -ForegroundColor Cyan
    Write-Host '2. Enable Developer mode' -ForegroundColor Cyan
    Write-Host "3. Click Load unpacked and select: $extensionRoot" -ForegroundColor Cyan
    Write-Host '4. Copy the 32-character ID shown on the WorkTracker Browser Context extension card' -ForegroundColor Cyan
    Write-Host '5. Re-run this script with that real ID' -ForegroundColor Cyan
    Write-Host ''
}

$normalizedExtensionId = $ExtensionId.Trim().ToLowerInvariant()
if ([string]::IsNullOrWhiteSpace($normalizedExtensionId) -or
    $normalizedExtensionId -eq 'extension_id' -or
    $normalizedExtensionId -eq '<chrome_extension_id>' -or
    $normalizedExtensionId -eq 'chrome_extension_id') {
    Show-ExtensionIdHelp
    throw 'A real Chrome Extension ID is required.'
}

if ($normalizedExtensionId -notmatch '^[a-p]{32}$') {
    Show-ExtensionIdHelp
    throw "Invalid Chrome Extension ID '$ExtensionId'. Chrome unpacked-extension IDs are 32 characters using letters a through p."
}

$ExtensionId = $normalizedExtensionId

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

$registeredManifest = (Get-Item -Path $regPath).GetValue('')
if ($registeredManifest -ne $manifestPath) {
    throw "Native Messaging registry verification failed. Expected '$manifestPath', got '$registeredManifest'."
}
if (-not (Test-Path $registeredManifest)) {
    throw "Native Messaging manifest was registered but does not exist: $registeredManifest"
}

$verified = Get-Content $registeredManifest -Raw | ConvertFrom-Json
$expectedOrigin = "chrome-extension://$ExtensionId/"
if ($verified.name -ne 'ir.rayaasun.worktracker.browser') { throw 'Native Messaging manifest name verification failed.' }
if ($verified.path -ne $hostExeResolved) { throw 'Native Messaging executable path verification failed.' }
if (@($verified.allowed_origins) -notcontains $expectedOrigin) { throw 'Native Messaging allowed_origins verification failed.' }

Write-Host ''
Write-Host 'Chrome Native Messaging host registered and verified.' -ForegroundColor Green
Write-Host "Extension ID : $ExtensionId"
Write-Host "Manifest     : $manifestPath"
Write-Host "Executable   : $hostExeResolved"
Write-Host "Registry     : $regPath"
Write-Host ''
Write-Host 'Next steps:' -ForegroundColor Cyan
Write-Host '1. Fully restart Chrome (chrome://restart is sufficient).' -ForegroundColor Cyan
Write-Host '2. Open the WorkTracker Browser Context extension popup.' -ForegroundColor Cyan
Write-Host '3. Enable Browser Context tracking.' -ForegroundColor Cyan
Write-Host '4. Open a normal HTTPS page and verify context.json is created.' -ForegroundColor Cyan
Write-Host "   $env:LOCALAPPDATA\WorkTracker\browser\chrome\context.json" -ForegroundColor DarkGray
Write-Host '5. BrowserBridge protocol logs will be written under:' -ForegroundColor Cyan
Write-Host "   $env:LOCALAPPDATA\WorkTracker\logs\browser-bridge-YYYY-MM-DD.log" -ForegroundColor DarkGray
