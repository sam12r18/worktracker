param(
    [switch]$SkipWindowsBuild,
    [switch]$SkipLaravelTests,
    [switch]$SkipPhpStormPlugin,
    [switch]$VerifyPhpStormCompatibility
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$repo = Split-Path -Parent $PSScriptRoot

if (-not $SkipWindowsBuild) {
    Write-Host '==> Windows Agent + deterministic Activity Intelligence tests' -ForegroundColor Cyan
    & (Join-Path $PSScriptRoot 'build-windows-agent.ps1')
    if ($LASTEXITCODE -ne 0) { throw 'Windows Agent regression gate failed.' }
}

if (-not $SkipLaravelTests) {
    $api = Join-Path $repo 'apps\api'
    Push-Location $api
    try {
        Write-Host '==> Laravel focused tests' -ForegroundColor Cyan
        php artisan test --filter=WorkEventProjectionServiceTest
        if ($LASTEXITCODE -ne 0) { throw 'Laravel Work Event tests failed.' }
        php artisan route:list --path=api/v1/sync | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Laravel route check failed.' }
    }
    finally { Pop-Location }
}

if (-not $SkipPhpStormPlugin) {
    Write-Host '==> PhpStorm Context Bridge plugin' -ForegroundColor Cyan
    $pluginBuildArgs = @{}
    if ($VerifyPhpStormCompatibility) { $pluginBuildArgs['VerifyCompatibility'] = $true }
    & (Join-Path $PSScriptRoot 'build-phpstorm-plugin.ps1') @pluginBuildArgs
    if ($LASTEXITCODE -ne 0) { throw 'PhpStorm plugin build failed.' }
}

Write-Host '==> alpha.8.0 automated regression gate succeeded.' -ForegroundColor Green
Write-Host '    Manual integration checklist: docs\testing\alpha8.0-phpstorm-context-smoke-test.md'
