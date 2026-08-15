# WorkTracker alpha.7.2 — WPF operator keyword hotfix

## Problem
The Windows Agent Release build failed with CS1041 / CS1525 in `ProjectRepository.cs` because the anonymous JSON payload used `operator` as a C# identifier. `operator` is a reserved C# keyword.

## Fix
The project-rule outbox payload is now built with `Dictionary<string, object?>`. The serialized JSON contract remains unchanged and still contains the exact key `operator`.

## Apply
Extract this archive over the WorkTracker repository root, replacing the existing file, then run:

```powershell
cd I:\worktracker
.\tools\build-windows-agent.ps1
```

No Laravel migration or SQLite migration is required for this hotfix.
