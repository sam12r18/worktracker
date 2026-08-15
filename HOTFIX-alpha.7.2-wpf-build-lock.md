# WorkTracker alpha.7.2 — WPF build-lock hotfix

This hotfix fixes two related Windows Agent issues:

1. `build-windows-agent.ps1` detects a WorkTracker Agent process launched from this repository and stops it before the Release build. This prevents MSBuild `MSB3027` / `MSB3021` file-lock failures when `WorkTracker.Agent.exe` is still running in the System Tray.
2. The Agent now uses a per-user named mutex and refuses to start a second instance. This protects the single foreground-capture stream from accidental duplicate Agent processes on the same Windows user/device.

The build script only stops `WorkTracker.Agent` processes whose executable is under this repository's `apps\windows-agent\WorkTracker.Agent\bin` directory. It does not terminate an installed copy located elsewhere.
