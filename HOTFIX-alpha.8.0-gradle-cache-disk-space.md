# WorkTracker alpha.8.0 - Gradle cache disk-space hotfix

## Problem

PhpStorm 2025.1 SDK extraction failed under `C:\Users\<user>\.gradle` with:

`java.io.IOException: There is not enough space on the disk`

The build itself was healthy; Gradle was downloading and extracting the large PhpStorm distribution on the system drive.

## Fix

`tools/build-phpstorm-plugin.ps1` now uses a repository-local Gradle cache by default:

`<repo>\.worktracker-cache\gradle`

For a checkout at `I:\worktracker`, the large Gradle/PhpStorm artifacts therefore stay on drive `I:`.

The script also:

- redirects Gradle temporary files to `<repo>\.worktracker-cache\tmp`;
- prints the selected cache path and available free space before the build;
- warns when less than 8 GB is available;
- accepts `-GradleUserHome` to choose another drive/path;
- creates a local `.gitignore` so build caches are not accidentally committed;
- restores the caller's `GRADLE_USER_HOME`, `TEMP`, and `TMP` after the build.

No application/runtime code or database migration is changed.
