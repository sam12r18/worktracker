param(
    [switch]$Strict
)

$ErrorActionPreference = 'Continue'
$repoRoot = Split-Path -Parent $PSScriptRoot
$failures = New-Object System.Collections.Generic.List[string]

function Write-Check([string]$Name, [bool]$Ok, [string]$Detail) {
    $state = if ($Ok) { 'OK' } else { 'FAIL' }
    $color = if ($Ok) { 'Green' } else { 'Yellow' }
    Write-Host ("[{0}] {1}: {2}" -f $state, $Name, $Detail) -ForegroundColor $color
    if (-not $Ok) { $failures.Add($Name) }
}

Write-Host 'WorkTracker Context Integration Diagnostics' -ForegroundColor Cyan
Write-Host ('Repo: ' + $repoRoot) -ForegroundColor DarkGray
Write-Host ''

# Chrome extension source
$extensionRoot = Join-Path $repoRoot 'apps\chrome-extension'
$extensionManifest = Join-Path $extensionRoot 'manifest.json'
Write-Check 'Chrome extension source' (Test-Path $extensionManifest) $extensionManifest
if (Test-Path $extensionManifest) {
    try {
        $manifest = Get-Content $extensionManifest -Raw | ConvertFrom-Json
        Write-Host ("      name={0} version={1} manifest_version={2}" -f $manifest.name, $manifest.version, $manifest.manifest_version) -ForegroundColor DarkGray
    }
    catch {
        Write-Check 'Chrome extension manifest JSON' $false $_.Exception.Message
    }
}

# Native Messaging registration
$regPath = 'HKCU:\Software\Google\Chrome\NativeMessagingHosts\ir.rayaasun.worktracker.browser'
$registeredManifest = $null
if (Test-Path $regPath) {
    try { $registeredManifest = (Get-Item $regPath).GetValue('') } catch { }
}
$registryDetail = if ([string]::IsNullOrWhiteSpace($registeredManifest)) { 'not registered' } else { [string]$registeredManifest }
Write-Check 'Chrome Native Messaging registry' (-not [string]::IsNullOrWhiteSpace($registeredManifest)) $registryDetail

if (-not [string]::IsNullOrWhiteSpace($registeredManifest)) {
    Write-Check 'Native host manifest file' (Test-Path $registeredManifest) $registeredManifest
    if (Test-Path $registeredManifest) {
        try {
            $nativeManifest = Get-Content $registeredManifest -Raw | ConvertFrom-Json
            $origin = @($nativeManifest.allowed_origins) | Select-Object -First 1
            $originId = if ($origin -match '^chrome-extension://(?<id>[a-p]{32})/$') { $Matches.id } else { $null }
            $originDetail = if ([string]::IsNullOrWhiteSpace([string]$origin)) { 'missing/invalid' } else { [string]$origin }
            Write-Check 'Native host executable' (Test-Path $nativeManifest.path) ([string]$nativeManifest.path)
            Write-Check 'Allowed Chrome origin' (-not [string]::IsNullOrWhiteSpace($originId)) $originDetail
            if ($originId) {
                Write-Host ("      Extension ID registered with Native Messaging: {0}" -f $originId) -ForegroundColor Cyan
                Write-Host '      Compare this ID with the WorkTracker card in chrome://extensions.' -ForegroundColor DarkGray
            }
        }
        catch {
            Write-Check 'Native host manifest JSON' $false $_.Exception.Message
        }
    }
}

# Browser context and bridge logs
$browserContextPath = Join-Path $env:LOCALAPPDATA 'WorkTracker\browser\chrome\context.json'
if (Test-Path $browserContextPath) {
    try {
        $browserContext = Get-Content $browserContextPath -Raw | ConvertFrom-Json
        $observed = [DateTimeOffset]::Parse([string]$browserContext.observed_at_utc)
        $age = [Math]::Max(0, [int]((Get-Date).ToUniversalTime().Subtract($observed.UtcDateTime).TotalSeconds))
        Write-Check 'Chrome browser context' $true ("{0}{1} age={2}s" -f $browserContext.host, $browserContext.path, $age)
    }
    catch {
        Write-Check 'Chrome browser context' $false ("exists but cannot be parsed: {0}" -f $_.Exception.Message)
    }
}
else {
    Write-Check 'Chrome browser context' $false 'context.json not present (expected until extension is enabled on a focused HTTPS tab)'
}

$bridgeLog = Join-Path $env:LOCALAPPDATA ("WorkTracker\logs\browser-bridge-{0}.log" -f (Get-Date).ToUniversalTime().ToString('yyyy-MM-dd'))
if (Test-Path $bridgeLog) {
    Write-Check 'BrowserBridge log' $true $bridgeLog
    Write-Host '      Last BrowserBridge entries:' -ForegroundColor DarkGray
    Get-Content $bridgeLog -Tail 6 | ForEach-Object { Write-Host ('      ' + $_) -ForegroundColor DarkGray }
}
else {
    Write-Check 'BrowserBridge log' $false "$bridgeLog not found (new build writes this after Chrome invokes the host)"
}

Write-Host ''
Write-Host 'PhpStorm Context Bridge' -ForegroundColor Cyan

# Build artifact
$distributionDir = Join-Path $repoRoot 'apps\phpstorm-plugin\build\distributions'
$pluginZip = $null
if (Test-Path $distributionDir) {
    $pluginZip = Get-ChildItem $distributionDir -Filter '*.zip' -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1
}
$pluginZipDetail = if ($pluginZip) { $pluginZip.FullName } else { 'build artifact not found; run .\tools\build-phpstorm-plugin.ps1' }
Write-Check 'PhpStorm plugin ZIP' ($null -ne $pluginZip) $pluginZipDetail

# Running PhpStorm
$phpStormProcesses = @(Get-Process phpstorm64 -ErrorAction SilentlyContinue)
$phpStormProcessDetail = if ($phpStormProcesses.Count -gt 0) {
    ($phpStormProcesses | ForEach-Object { "pid=$($_.Id)" }) -join ', '
} else {
    'phpstorm64.exe is not running'
}
Write-Check 'PhpStorm process' ($phpStormProcesses.Count -gt 0) $phpStormProcessDetail

# Context heartbeat files
$ideContextDir = Join-Path $env:LOCALAPPDATA 'WorkTracker\ide\phpstorm'
$ideFiles = @()
if (Test-Path $ideContextDir) {
    $ideFiles = @(Get-ChildItem $ideContextDir -Filter 'context-*.json' -File -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending)
}
if ($ideFiles.Count -gt 0) {
    $latestIde = $ideFiles[0]
    $ageSeconds = [Math]::Max(0, [int]((Get-Date).Subtract($latestIde.LastWriteTime).TotalSeconds))
    Write-Check 'PhpStorm context heartbeat' ($ageSeconds -le 15) ("{0} age={1}s" -f $latestIde.FullName, $ageSeconds)
    try {
        $ideContext = Get-Content $latestIde.FullName -Raw | ConvertFrom-Json
        Write-Host ("      project={0} file={1} mode={2} plugin={3}" -f $ideContext.project_name, $ideContext.current_file, $ideContext.execution_mode, $ideContext.plugin_version) -ForegroundColor DarkGray
    }
    catch { }
}
else {
    Write-Check 'PhpStorm context heartbeat' $false "$ideContextDir contains no context files"
}

# JetBrains logs can confirm plugin start/publish failures.
$jetBrainsLogRoots = @(
    (Join-Path $env:LOCALAPPDATA 'JetBrains'),
    (Join-Path $env:APPDATA 'JetBrains')
) | Where-Object { $_ -and (Test-Path $_) }
$ideaLog = $null
foreach ($root in $jetBrainsLogRoots) {
    $candidate = Get-ChildItem $root -Recurse -Filter 'idea.log' -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1
    if ($candidate -and (-not $ideaLog -or $candidate.LastWriteTime -gt $ideaLog.LastWriteTime)) { $ideaLog = $candidate }
}
if ($ideaLog) {
    Write-Check 'PhpStorm idea.log' $true $ideaLog.FullName
    $matches = Select-String -Path $ideaLog.FullName -Pattern 'WorkTracker Context Bridge' -SimpleMatch -ErrorAction SilentlyContinue | Select-Object -Last 6
    if ($matches) {
        Write-Host '      Recent WorkTracker plugin log entries:' -ForegroundColor DarkGray
        $matches | ForEach-Object { Write-Host ('      ' + $_.Line.Trim()) -ForegroundColor DarkGray }
    }
    else {
        Write-Host '      No WorkTracker Context Bridge entry found yet. Rebuild/reinstall the Alpha 8.1 plugin and restart PhpStorm.' -ForegroundColor Yellow
    }
}

Write-Host ''
if ($failures.Count -eq 0) {
    Write-Host 'All observable Context integration checks passed.' -ForegroundColor Green
    exit 0
}

Write-Host ("Diagnostics completed with {0} missing/failed checks: {1}" -f $failures.Count, ($failures -join ', ')) -ForegroundColor Yellow
if ($Strict) { exit 2 }
exit 0
