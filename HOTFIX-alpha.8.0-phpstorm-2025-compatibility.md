# Hotfix alpha.8.0 — PhpStorm 2025.1–2026.2 compatibility

This hotfix replaces the previous Java 25/toolchain build patch.

Changes:

- fixes the Windows PowerShell parser error caused by `$requiredJavaMajor:` interpolation;
- changes the normal plugin compile baseline to PhpStorm 2025.1 / Java 21;
- declares one plugin compatibility range from build 251 through 262.*;
- supports direct compile checks for PhpStorm 2025.1, 2025.2, 2025.3, 2026.1 and 2026.2;
- keeps Java 25 requirement only for a direct 2026.2 compile target;
- adds optional JetBrains Plugin Verifier matrix for all supported PhpStorm releases;
- disables Gradle configuration cache for this plugin build because it provided no critical value and previously obscured Java toolchain failures.

Recommended release command:

```powershell
.\tools\build-phpstorm-plugin.ps1 -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin"
```

Optional full compatibility verification:

```powershell
.\tools\build-phpstorm-plugin.ps1 -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin" -VerifyCompatibility
```
