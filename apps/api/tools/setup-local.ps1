$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'PHP is not installed or not in PATH.' }
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { throw 'Composer is not installed or not in PATH.' }
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
composer install
if (-not (Select-String -Path .env -Pattern '^APP_KEY=base64:' -Quiet)) { php artisan key:generate }
php artisan optimize:clear
php artisan migrate
Write-Host 'Set WORKTRACKER_ADMIN_EMAIL / WORKTRACKER_ADMIN_PASSWORD in .env, then run: php artisan db:seed' -ForegroundColor Yellow
Write-Host 'Health: http://127.0.0.1:8000/worktracker/health' -ForegroundColor Cyan
Write-Host 'Run: php artisan serve' -ForegroundColor Green
