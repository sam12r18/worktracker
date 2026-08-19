# Windows PowerShell 5.1 compatibility: keep this script ASCII-only.
param(
    [string]$PlatformVersion = '2026.1',
    [string]$JavaHome = '',
    [switch]$NoBootstrap
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repo = Split-Path -Parent $PSScriptRoot
$pluginRoot = Join-Path $repo 'apps\phpstorm-plugin'
$gradleVersion = '9.0.0'
$toolRoot = Join-Path $env:LOCALAPPDATA 'WorkTracker\tools'
$gradleRoot = Join-Path $toolRoot "gradle-$gradleVersion"
$gradleExe = Join-Path $gradleRoot 'bin\gradle.bat'
$managedJdkRoot = Join-Path $toolRoot 'temurin-jdk-21'
$temurinUrl = 'https://api.adoptium.net/v3/binary/latest/21/ga/windows/x64/jdk/hotspot/normal/eclipse'

function Get-JavaMajor([string]$JavaExe) {
    if (-not $JavaExe -or -not (Test-Path $JavaExe)) { return 0 }
    $versionText = (& $JavaExe -version 2>&1 | Out-String)
    if ($versionText -match 'version\s+"(?<major>\d+)') { return [int]$Matches.major }
    if ($versionText -match 'openjdk\s+(?<major>\d+)(?:\.|\s)') { return [int]$Matches.major }
    return 0
}

function Add-JavaCandidate([System.Collections.Generic.List[string]]$List, [string]$Path) {
    if ($Path -and (Test-Path $Path)) { $List.Add($Path) }
}

function Add-JbrFromPhpStormExe([System.Collections.Generic.List[string]]$List, [string]$PhpStormExe) {
    if (-not $PhpStormExe -or -not (Test-Path $PhpStormExe)) { return }
    $binDir = Split-Path -Parent $PhpStormExe
    $installDir = Split-Path -Parent $binDir
    Add-JavaCandidate $List (Join-Path $installDir 'jbr\bin\java.exe')
    Add-JavaCandidate $List (Join-Path $installDir 'jre\bin\java.exe')
}

function Get-PhpStormExecutablesFromRegistry {
    $paths = New-Object System.Collections.Generic.List[string]
    $keys = @(
        'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\phpstorm64.exe',
        'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\phpstorm64.exe',
        'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\App Paths\phpstorm64.exe'
    )
    foreach ($key in $keys) {
        try {
            $item = Get-ItemProperty -Path $key -ErrorAction Stop
            if ($item.'(default)') { $paths.Add([string]$item.'(default)') }
            elseif ($item.PSObject.Properties.Name -contains '(default)') { $paths.Add([string]$item.'(default)') }
        }
        catch { }
    }
    return @($paths | Select-Object -Unique)
}

function Resolve-JavaExecutable {
    $candidates = New-Object System.Collections.Generic.List[string]

    if ($JavaHome) {
        Add-JavaCandidate $candidates (Join-Path $JavaHome 'bin\java.exe')
    }

    if ($env:JAVA_HOME) {
        Add-JavaCandidate $candidates (Join-Path $env:JAVA_HOME 'bin\java.exe')
    }

    $command = Get-Command java -ErrorAction SilentlyContinue
    if ($command) { Add-JavaCandidate $candidates $command.Source }

    # Reuse a JDK previously bootstrapped by WorkTracker.
    if (Test-Path $managedJdkRoot) {
        Get-ChildItem -Path $managedJdkRoot -Recurse -File -Filter 'java.exe' -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match '\\bin\\java\.exe$' } |
            ForEach-Object { Add-JavaCandidate $candidates $_.FullName }
    }

    # If PhpStorm is currently running, this is the most reliable way to find a custom install.
    try {
        Get-CimInstance Win32_Process -Filter "Name='phpstorm64.exe'" -ErrorAction Stop |
            ForEach-Object { Add-JbrFromPhpStormExe $candidates $_.ExecutablePath }
    }
    catch { }

    # Registry App Paths, when present.
    foreach ($phpStormExe in Get-PhpStormExecutablesFromRegistry) {
        Add-JbrFromPhpStormExe $candidates $phpStormExe
    }

    # Common standalone and Toolbox locations.
    $roots = @(
        (Join-Path $env:LOCALAPPDATA 'Programs'),
        (Join-Path $env:LOCALAPPDATA 'JetBrains\Toolbox\apps\PhpStorm'),
        (Join-Path $env:APPDATA 'JetBrains\Toolbox\apps\PhpStorm'),
        (Join-Path $env:ProgramFiles 'JetBrains'),
        (Join-Path ${env:ProgramFiles(x86)} 'JetBrains')
    ) | Where-Object { $_ -and (Test-Path $_) }

    foreach ($root in $roots) {
        Get-ChildItem -Path $root -Recurse -File -Filter 'java.exe' -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match '\\jbr\\bin\\java\.exe$|\\jre\\bin\\java\.exe$' } |
            Sort-Object LastWriteTime -Descending |
            ForEach-Object { Add-JavaCandidate $candidates $_.FullName }
    }

    foreach ($candidate in @($candidates | Select-Object -Unique)) {
        try {
            $major = Get-JavaMajor $candidate
            if ($major -ge 21) {
                Write-Host "==> Using Java candidate: $candidate" -ForegroundColor DarkGray
                return $candidate
            }
        }
        catch { }
    }

    if ($NoBootstrap) {
        throw 'Java 21+ was not found and -NoBootstrap disables automatic JDK download.'
    }

    return Install-ManagedJdk21
}

function Install-ManagedJdk21 {
    New-Item -ItemType Directory -Path $toolRoot -Force | Out-Null
    $zip = Join-Path $toolRoot 'temurin-jdk-21-windows-x64.zip'

    Write-Host '==> Java 21+ was not found in PATH, JAVA_HOME, PhpStorm, or Toolbox.' -ForegroundColor Yellow
    Write-Host '==> Downloading a private Eclipse Temurin JDK 21 for WorkTracker...' -ForegroundColor Cyan
    Write-Host "==> Source: $temurinUrl" -ForegroundColor DarkGray

    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    }
    catch { }

    if (Test-Path $zip) { Remove-Item $zip -Force }
    Invoke-WebRequest -Uri $temurinUrl -OutFile $zip -UseBasicParsing

    if (Test-Path $managedJdkRoot) { Remove-Item $managedJdkRoot -Recurse -Force }
    New-Item -ItemType Directory -Path $managedJdkRoot -Force | Out-Null
    Expand-Archive -Path $zip -DestinationPath $managedJdkRoot -Force
    Remove-Item $zip -Force

    $java = Get-ChildItem -Path $managedJdkRoot -Recurse -File -Filter 'java.exe' -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -match '\\bin\\java\.exe$' } |
        Sort-Object FullName |
        Select-Object -First 1

    if (-not $java) {
        throw "JDK bootstrap completed but java.exe was not found under $managedJdkRoot."
    }

    $major = Get-JavaMajor $java.FullName
    if ($major -lt 21) {
        throw "JDK bootstrap returned Java $major; Java 21+ is required."
    }

    Write-Host "==> Managed JDK ready: $($java.FullName)" -ForegroundColor Green
    return $java.FullName
}

function Resolve-GradleExecutable {
    $command = Get-Command gradle -ErrorAction SilentlyContinue
    if ($command) {
        $versionText = (& $command.Source --version | Out-String)
        if ($versionText -match 'Gradle\s+(?<version>\d+)(?:\.\d+)?') {
            if ([int]$Matches.version -ge 9) { return $command.Source }
        }
    }

    if (Test-Path $gradleExe) { return $gradleExe }
    if ($NoBootstrap) { throw 'Gradle 9+ was not found and -NoBootstrap disables automatic download.' }

    New-Item -ItemType Directory -Path $toolRoot -Force | Out-Null
    $zip = Join-Path $toolRoot "gradle-$gradleVersion-bin.zip"
    $url = "https://services.gradle.org/distributions/gradle-$gradleVersion-bin.zip"
    Write-Host "==> Download Gradle $gradleVersion" -ForegroundColor Cyan
    Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
    if (Test-Path $gradleRoot) { Remove-Item $gradleRoot -Recurse -Force }
    Expand-Archive -Path $zip -DestinationPath $toolRoot -Force
    Remove-Item $zip -Force
    if (-not (Test-Path $gradleExe)) { throw "Gradle bootstrap failed: $gradleExe was not found." }
    return $gradleExe
}

$java = Resolve-JavaExecutable
$javaMajor = Get-JavaMajor $java
if ($javaMajor -lt 21) { throw "Java $javaMajor was found; this plugin requires Java 21+." }
$env:JAVA_HOME = Split-Path -Parent (Split-Path -Parent $java)
$gradle = Resolve-GradleExecutable

Write-Host "==> JAVA_HOME: $env:JAVA_HOME"
Write-Host "==> Java: $java (major $javaMajor)"
Write-Host "==> Gradle: $gradle"
Write-Host "==> PhpStorm target: $PlatformVersion"
Push-Location $pluginRoot
try {
    & $gradle clean buildPlugin verifyPluginProjectConfiguration "-PplatformVersion=$PlatformVersion"
    if ($LASTEXITCODE -ne 0) { throw "Gradle build failed with exit code $LASTEXITCODE." }

    $distribution = Get-ChildItem -Path (Join-Path $pluginRoot 'build\distributions') -Filter '*.zip' -File |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $distribution) { throw 'Plugin distribution ZIP was not created.' }
    Write-Host "==> Plugin ready: $($distribution.FullName)" -ForegroundColor Green
}
finally {
    Pop-Location
}
