# Windows PowerShell 5.1 compatibility: keep this script ASCII-only.
param(
    [string]$PlatformVersion = '2025.1',
    [string]$JavaHome = '',
    [string]$GradleUserHome = '',
    [switch]$NoBootstrap,
    [switch]$VerifyCompatibility
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repo = Split-Path -Parent $PSScriptRoot
$pluginRoot = Join-Path $repo 'apps\phpstorm-plugin'
$gradleVersion = '9.0.0'
$toolRoot = Join-Path $env:LOCALAPPDATA 'WorkTracker\tools'
$gradleRoot = Join-Path $toolRoot "gradle-$gradleVersion"
$gradleExe = Join-Path $gradleRoot 'bin\gradle.bat'

# Keep the large IntelliJ/Gradle caches off the system drive by default.
# PhpStorm distributions can require several GB while Gradle extracts/transforms them.
if (-not $GradleUserHome) {
    $GradleUserHome = Join-Path $repo '.worktracker-cache\gradle'
}
$GradleUserHome = [IO.Path]::GetFullPath($GradleUserHome)
$workTrackerCacheRoot = Split-Path -Parent $GradleUserHome
$gradleTempRoot = Join-Path $workTrackerCacheRoot 'tmp'
New-Item -ItemType Directory -Path $GradleUserHome -Force | Out-Null
New-Item -ItemType Directory -Path $gradleTempRoot -Force | Out-Null

# Protect local build caches from accidental git add.
$cacheGitIgnore = Join-Path $workTrackerCacheRoot '.gitignore'
if (-not (Test-Path $cacheGitIgnore)) {
    Set-Content -Path $cacheGitIgnore -Value "*`r`n!.gitignore`r`n" -Encoding ASCII
}

function Get-FreeSpaceGb([string]$Path) {
    try {
        $root = [IO.Path]::GetPathRoot([IO.Path]::GetFullPath($Path))
        $drive = New-Object System.IO.DriveInfo($root)
        return [Math]::Round($drive.AvailableFreeSpace / 1GB, 2)
    }
    catch { return -1 }
}

function Get-RequiredJavaMajor([string]$VersionText) {
    try {
        $v = [Version]$VersionText
        if ($v -ge [Version]'2026.2') { return 25 }
    }
    catch {
        if ($VersionText -match '^2026\.(?<minor>\d+)') {
            if ([int]$Matches.minor -ge 2) { return 25 }
        }
    }
    return 21
}

$supportedPlatforms = @('2025.1', '2025.2', '2025.3', '2026.1', '2026.2')
if ($supportedPlatforms -notcontains $PlatformVersion) {
    throw "Unsupported PhpStorm target '$PlatformVersion'. Supported targets: $($supportedPlatforms -join ', ')."
}

$requiredJavaMajor = Get-RequiredJavaMajor $PlatformVersion
$managedJdkRoot = Join-Path $toolRoot "temurin-jdk-$requiredJavaMajor"
$temurinUrl = "https://api.adoptium.net/v3/binary/latest/$requiredJavaMajor/ga/windows/x64/jdk/hotspot/normal/eclipse"

function Get-JavaMajor([string]$JavaExe) {
    if (-not $JavaExe -or -not (Test-Path $JavaExe)) { return 0 }

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $JavaExe
    $psi.Arguments = '-version'
    $psi.UseShellExecute = $false
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.CreateNoWindow = $true

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $psi
    if (-not $process.Start()) { return 0 }
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    $versionText = ($stdout + "`n" + $stderr)
    $process.Dispose()

    if ($versionText -match 'version\s+"(?<major>\d+)') { return [int]$Matches.major }
    if ($versionText -match 'openjdk\s+(?<major>\d+)(?:\.|\s)') { return [int]$Matches.major }
    return 0
}

function Resolve-JavaPath([string]$Path) {
    if (-not $Path) { return $null }
    $Path = $Path.Trim().Trim('"')

    if ((Test-Path $Path -PathType Leaf) -and ([IO.Path]::GetFileName($Path) -ieq 'java.exe')) {
        return $Path
    }

    if (Test-Path $Path -PathType Container) {
        $direct = Join-Path $Path 'java.exe'
        if (Test-Path $direct -PathType Leaf) { return $direct }

        $underBin = Join-Path $Path 'bin\java.exe'
        if (Test-Path $underBin -PathType Leaf) { return $underBin }
    }

    return $null
}

function Add-JavaCandidate([System.Collections.Generic.List[string]]$List, [string]$Path) {
    $resolved = Resolve-JavaPath $Path
    if ($resolved) { $List.Add($resolved) }
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

    if ($JavaHome) { Add-JavaCandidate $candidates $JavaHome }
    if ($env:JAVA_HOME) { Add-JavaCandidate $candidates $env:JAVA_HOME }

    $command = Get-Command java -ErrorAction SilentlyContinue
    if ($command) { Add-JavaCandidate $candidates $command.Source }

    if (Test-Path $managedJdkRoot) {
        Get-ChildItem -Path $managedJdkRoot -Recurse -File -Filter 'java.exe' -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match '\\bin\\java\.exe$' } |
            ForEach-Object { Add-JavaCandidate $candidates $_.FullName }
    }

    try {
        Get-CimInstance Win32_Process -Filter "Name='phpstorm64.exe'" -ErrorAction Stop |
            ForEach-Object { Add-JbrFromPhpStormExe $candidates $_.ExecutablePath }
    }
    catch { }

    foreach ($phpStormExe in Get-PhpStormExecutablesFromRegistry) {
        Add-JbrFromPhpStormExe $candidates $phpStormExe
    }

    $roots = @(
        (Join-Path $env:LOCALAPPDATA 'Programs'),
        (Join-Path $env:LOCALAPPDATA 'JetBrains\Toolbox\apps\PhpStorm'),
        (Join-Path $env:APPDATA 'JetBrains\Toolbox\apps\PhpStorm'),
        (Join-Path $env:ProgramFiles 'JetBrains'),
        (Join-Path ${env:ProgramFiles(x86)} 'JetBrains')
    ) | Where-Object { $_ -and (Test-Path $_) }

    $jdkRoots = @(
        (Join-Path $env:ProgramFiles 'Eclipse Adoptium'),
        (Join-Path $env:ProgramFiles 'Java'),
        (Join-Path $env:ProgramFiles 'Microsoft'),
        (Join-Path $env:LOCALAPPDATA 'Programs\Eclipse Adoptium')
    ) | Where-Object { $_ -and (Test-Path $_) }

    foreach ($jdkRoot in $jdkRoots) {
        Get-ChildItem -Path $jdkRoot -Recurse -File -Filter 'java.exe' -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match '\\bin\\java\.exe$' } |
            Sort-Object LastWriteTime -Descending |
            ForEach-Object { Add-JavaCandidate $candidates $_.FullName }
    }

    foreach ($root in $roots) {
        Get-ChildItem -Path $root -Recurse -File -Filter 'java.exe' -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match '\\jbr\\bin\\java\.exe$|\\jre\\bin\\java\.exe$' } |
            Sort-Object LastWriteTime -Descending |
            ForEach-Object { Add-JavaCandidate $candidates $_.FullName }
    }

    foreach ($candidate in @($candidates | Select-Object -Unique)) {
        try {
            $major = Get-JavaMajor $candidate
            if ($major -ge $requiredJavaMajor) {
                Write-Host "==> Using Java candidate: $candidate" -ForegroundColor DarkGray
                return $candidate
            }
            if ($major -gt 0) {
                Write-Host ("==> Skipping Java {0} candidate; PhpStorm {1} requires Java {2}: {3}" -f $major, $PlatformVersion, $requiredJavaMajor, $candidate) -ForegroundColor DarkYellow
            }
        }
        catch { }
    }

    if ($NoBootstrap) {
        throw "Java $requiredJavaMajor+ was not found and -NoBootstrap disables automatic JDK download."
    }

    return Install-ManagedJdk
}

function Install-ManagedJdk {
    New-Item -ItemType Directory -Path $toolRoot -Force | Out-Null
    $zip = Join-Path $toolRoot "temurin-jdk-$requiredJavaMajor-windows-x64.zip"

    Write-Host "==> Java $requiredJavaMajor+ was not found for PhpStorm $PlatformVersion." -ForegroundColor Yellow
    Write-Host "==> Downloading a private Eclipse Temurin JDK $requiredJavaMajor for WorkTracker..." -ForegroundColor Cyan
    Write-Host "==> Source: $temurinUrl" -ForegroundColor DarkGray

    try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch { }

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

    if (-not $java) { throw "JDK bootstrap completed but java.exe was not found under $managedJdkRoot." }

    $major = Get-JavaMajor $java.FullName
    if ($major -lt $requiredJavaMajor) {
        throw "JDK bootstrap returned Java $major; Java $requiredJavaMajor+ is required for PhpStorm $PlatformVersion."
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
if ($javaMajor -lt $requiredJavaMajor) {
    throw "Java $javaMajor was found; PhpStorm $PlatformVersion requires Java $requiredJavaMajor+."
}
$env:JAVA_HOME = Split-Path -Parent (Split-Path -Parent $java)
$gradle = Resolve-GradleExecutable

$oldGradleUserHome = $env:GRADLE_USER_HOME
$oldTemp = $env:TEMP
$oldTmp = $env:TMP
$env:GRADLE_USER_HOME = $GradleUserHome
$env:TEMP = $gradleTempRoot
$env:TMP = $gradleTempRoot
$freeSpaceGb = Get-FreeSpaceGb $GradleUserHome

Write-Host "==> JAVA_HOME: $env:JAVA_HOME"
Write-Host "==> Java: $java (major $javaMajor; required $requiredJavaMajor)"
Write-Host "==> Gradle: $gradle"
Write-Host "==> GRADLE_USER_HOME: $env:GRADLE_USER_HOME"
Write-Host "==> Gradle TEMP: $env:TEMP"
if ($freeSpaceGb -ge 0) {
    Write-Host "==> Cache drive free space: $freeSpaceGb GB"
    if ($freeSpaceGb -lt 8) {
        Write-Warning "Less than 8 GB is free on the Gradle cache drive. PhpStorm SDK extraction may fail."
    }
}
Write-Host "==> PhpStorm compile target: $PlatformVersion"
Write-Host "==> Declared compatibility: PhpStorm 2025.1 (251) through 2026.2 (262.*)"


# Fast Java source sanity check before Gradle downloads/configures the IDE SDK.
# Target-typed `new(...)` is C# syntax and is never valid Java.
$javaSourceRoot = Join-Path $repo 'apps\phpstorm-plugin\src\main\java'
if (Test-Path $javaSourceRoot) {
    $invalidTargetTypedNew = Get-ChildItem $javaSourceRoot -Recurse -Filter '*.java' -File |
        Select-String -Pattern '\bnew\s*\(' -AllMatches
    if ($invalidTargetTypedNew) {
        $first = $invalidTargetTypedNew | Select-Object -First 1
        throw ("Invalid Java syntax detected before Gradle build: {0}:{1}. Use 'new Type(...)', not target-typed 'new(...)'." -f $first.Path, $first.LineNumber)
    }
    Write-Host '==> Java source preflight: OK' -ForegroundColor Green
}
Push-Location $pluginRoot
try {
    & $gradle clean buildPlugin verifyPluginProjectConfiguration "-PplatformVersion=$PlatformVersion" "-PpluginSinceBuild=251" "-PpluginUntilBuild=262.*"
    if ($LASTEXITCODE -ne 0) { throw "Gradle build failed with exit code $LASTEXITCODE." }

    if ($VerifyCompatibility) {
        Write-Host "==> Verify binary compatibility: PhpStorm 2025.1 -> 2026.2" -ForegroundColor Cyan
        Write-Host "==> This optional step downloads multiple PhpStorm distributions and can take a long time." -ForegroundColor DarkGray
        & $gradle verifyPlugin "-PplatformVersion=2025.1" "-PpluginSinceBuild=251" "-PpluginUntilBuild=262.*"
        if ($LASTEXITCODE -ne 0) { throw "Plugin compatibility verification failed with exit code $LASTEXITCODE." }
    }

    $distribution = Get-ChildItem -Path (Join-Path $pluginRoot 'build\distributions') -Filter '*.zip' -File |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $distribution) { throw 'Plugin distribution ZIP was not created.' }
    Write-Host "==> Plugin ready: $($distribution.FullName)" -ForegroundColor Green
}
finally {
    Pop-Location
    $env:GRADLE_USER_HOME = $oldGradleUserHome
    $env:TEMP = $oldTemp
    $env:TMP = $oldTmp
}
