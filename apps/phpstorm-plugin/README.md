# WorkTracker Context Bridge for PhpStorm

First-party Alpha 8.1 plugin that exports IDE metadata to the local WorkTracker Windows Agent.

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
.\tools\build-phpstorm-plugin.ps1
```

The script discovers a suitable Java runtime automatically and can bootstrap a private JDK for WorkTracker when required. `-JavaHome` is still available when an explicit JDK should be used.

The default compile target is PhpStorm 2025.1. The generated plugin declares compatibility from build `251` through `262.*`.

Output:

```text
apps\phpstorm-plugin\build\distributions\worktracker-phpstorm-context-0.1.0-alpha.8.1.zip
```

## Install or update

1. Open PhpStorm.
2. Go to `Settings -> Plugins`.
3. Open the gear menu and choose `Install Plugin from Disk`.
4. Select the ZIP from `apps\phpstorm-plugin\build\distributions`.
5. Restart PhpStorm completely.
6. Open a normal project and wait a few seconds.

The plugin should create a short-lived heartbeat file under:

```text
%LOCALAPPDATA%\WorkTracker\ide\phpstorm\context-<phpstorm-pid>-<project-hash>.json
```

The heartbeat updates every two seconds while the project is open. The plugin does not need a WorkTracker token and opens no network port.

## Diagnostics

From repository root run:

```powershell
.\tools\check-context-integrations.ps1
```

A healthy PhpStorm integration should show a recent context heartbeat (normally <= 15 seconds old).

The plugin also writes start/success/failure messages into PhpStorm `idea.log` using the text `WorkTracker Context Bridge`. Persistent publish failures are rate-limited to one warning per minute so the IDE log is not flooded.

If no heartbeat appears after installing the ZIP and restarting PhpStorm, check `Help -> Show Log in Explorer` and search `idea.log` for `WorkTracker Context Bridge`.

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
.\tools\build-phpstorm-plugin.ps1 -VerifyCompatibility
```

This runs JetBrains Plugin Verifier against PhpStorm 2025.1, 2025.2, 2025.3, 2026.1, and 2026.2. It downloads multiple IDE distributions and can take significant time and disk space.
