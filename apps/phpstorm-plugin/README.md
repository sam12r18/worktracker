# WorkTracker Context Bridge for PhpStorm

First-party alpha.8 plugin that exports IDE metadata to the local WorkTracker Windows Agent.

## Supported PhpStorm versions

The release package is declared compatible with:

- PhpStorm 2025.1 (build 251)
- PhpStorm 2025.2 (build 252)
- PhpStorm 2025.3 (build 253)
- PhpStorm 2026.1 (build 261)
- PhpStorm 2026.2 (build 262)

The recommended release build compiles against the oldest supported platform, PhpStorm 2025.1, using Java 21. This keeps the compile-time API floor at 2025.1 while allowing the same plugin ZIP to run on later compatible PhpStorm releases.

Java requirements when explicitly compiling against a target IDE:

- PhpStorm 2025.x: Java 21
- PhpStorm 2026.1: Java 21
- PhpStorm 2026.2: Java 25

## Build the release-compatible ZIP

From repository root on Windows:

```powershell
.\tools\build-phpstorm-plugin.ps1 `
    -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin"
```

`-JavaHome` accepts the JDK root, its `bin` directory, or the full path to `java.exe`.

The default compile target is PhpStorm 2025.1. The generated plugin declares compatibility from build `251` through `262.*`.

## Compile-check a specific PhpStorm version

Examples:

```powershell
.\tools\build-phpstorm-plugin.ps1 -PlatformVersion 2025.3
```

```powershell
.\tools\build-phpstorm-plugin.ps1 `
    -PlatformVersion 2026.2 `
    -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-25"
```

Compiling directly against 2026.2 requires Java 25. It is not required for the normal release build that uses the 2025.1 compatibility floor.

## Optional compatibility verification

```powershell
.\tools\build-phpstorm-plugin.ps1 `
    -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin" `
    -VerifyCompatibility
```

This runs JetBrains Plugin Verifier against PhpStorm 2025.1, 2025.2, 2025.3, 2026.1, and 2026.2. It downloads multiple IDE distributions and can take significant time and disk space.

Output:

```text
apps\phpstorm-plugin\build\distributions\worktracker-phpstorm-context-0.1.0-alpha.8.0.zip
```

## Install

In PhpStorm use `Settings -> Plugins -> gear icon -> Install Plugin from Disk`, select the distribution ZIP, then restart the IDE.

The plugin has no WorkTracker token and opens no network port. It writes short-lived protocol-v1 JSON metadata under `%LOCALAPPDATA%\WorkTracker\ide\phpstorm` for the Windows Agent to read.
