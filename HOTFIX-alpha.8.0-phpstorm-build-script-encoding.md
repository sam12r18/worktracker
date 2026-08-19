# WorkTracker alpha.8.0 - PhpStorm build script encoding hotfix

## Problem

`tools/build-phpstorm-plugin.ps1` contained Persian UTF-8 string literals. Windows PowerShell 5.1 can interpret UTF-8 files without BOM using the active ANSI code page, producing mojibake and parser errors such as `Unexpected token`, missing string terminators, and missing braces.

## Fix

- Converted all user-facing literals in `build-phpstorm-plugin.ps1` to ASCII-only English.
- Saved the script using Windows CRLF line endings.
- Added an explicit compatibility note to keep this build script ASCII-only.
- Build behavior, Java detection, Gradle bootstrap, platform selection, and plugin output paths are unchanged.

## Test

Run from the repository root:

```powershell
.\tools\build-phpstorm-plugin.ps1
```

Or for PhpStorm 2026.2:

```powershell
.\tools\build-phpstorm-plugin.ps1 -PlatformVersion 2026.2
```
