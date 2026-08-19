# WorkTracker alpha.8.0 - PhpStorm plugin Java bootstrap hotfix

The PhpStorm plugin build script now resolves Java 21+ from:

1. `-JavaHome`
2. `JAVA_HOME`
3. `java` on PATH
4. a WorkTracker-managed JDK from a previous run
5. the currently running `phpstorm64.exe` installation
6. Windows App Paths registry entries
7. common standalone JetBrains and Toolbox directories

If Java 21+ still cannot be found, and `-NoBootstrap` is not specified, the script downloads a private Eclipse Temurin JDK 21 into:

`%LOCALAPPDATA%\WorkTracker\tools\temurin-jdk-21`

It does not install Java system-wide and does not require changing the global `JAVA_HOME` permanently. `JAVA_HOME` is set only for the current build process.

The download uses the stable Eclipse Adoptium API endpoint for the latest GA JDK 21 Windows x64 HotSpot build.

Use `-NoBootstrap` to disable both automatic JDK and Gradle downloads.

Example:

```powershell
.\tools\build-phpstorm-plugin.ps1 -PlatformVersion 2026.2
```

Explicit JDK override:

```powershell
.\tools\build-phpstorm-plugin.ps1 -PlatformVersion 2026.2 -JavaHome 'C:\Program Files\Java\jdk-21'
```
