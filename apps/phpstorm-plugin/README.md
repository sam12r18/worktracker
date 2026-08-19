# WorkTracker Context Bridge for PhpStorm

First-party alpha.8 plugin that exports IDE metadata to the local WorkTracker Windows Agent.

## Build

From repository root on Windows:

```powershell
.\tools\build-phpstorm-plugin.ps1
```

The build script uses Java 21+ and Gradle 9+. If Gradle 9 is not available, it can bootstrap Gradle 9.0.0 under `%LOCALAPPDATA%\WorkTracker\tools`.

Output:

```text
apps\phpstorm-plugin\build\distributions\worktracker-phpstorm-context-0.1.0-alpha.8.0.zip
```

## Install

In PhpStorm use `Settings → Plugins → ⚙ → Install Plugin from Disk`, select the distribution ZIP, then restart the IDE.

The plugin has no WorkTracker token and opens no network port. It writes short-lived protocol-v1 JSON metadata under `%LOCALAPPDATA%\WorkTracker\ide\phpstorm` for the Windows Agent to read.
