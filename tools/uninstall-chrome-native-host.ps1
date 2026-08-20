$ErrorActionPreference = 'Stop'
$regPath = 'HKCU:\Software\Google\Chrome\NativeMessagingHosts\ir.rayaasun.worktracker.browser'
$manifestPath = Join-Path $env:LOCALAPPDATA 'WorkTracker\browser-host\ir.rayaasun.worktracker.browser.json'
if (Test-Path $regPath) { Remove-Item -Recurse -Force $regPath }
if (Test-Path $manifestPath) { Remove-Item -Force $manifestPath }
Write-Host 'WorkTracker Chrome Native Messaging host removed.'
