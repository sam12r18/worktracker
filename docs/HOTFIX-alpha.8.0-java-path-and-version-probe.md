# HOTFIX alpha.8.0 - Java path and version probe

Fixes two Windows PowerShell 5.1 issues in `tools/build-phpstorm-plugin.ps1`:

1. `-JavaHome` and `JAVA_HOME` now accept a JDK root, the JDK `bin` directory, or the full `java.exe` path.
2. `java -version` is captured through `System.Diagnostics.Process` because Java writes version information to stderr, which can otherwise become `NativeCommandError` when `$ErrorActionPreference = 'Stop'`.
3. Common Eclipse Adoptium/Java/Microsoft JDK install roots are scanned automatically before attempting a managed download.

No Laravel migration or WPF changes are included.
