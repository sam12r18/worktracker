# HOTFIX alpha.8.0 - PhpStorm 2026.2 Java 25 toolchain

PhpStorm / IntelliJ Platform 2026.2 requires Java 25 for plugin development. The previous alpha.8 build script only required Java 21 and the Gradle project explicitly compiled with Java 21, which caused Gradle to request an unavailable Java 25 toolchain while `JAVA_HOME` pointed to JDK 21.

Changes:

- Java requirement is now selected from the target PhpStorm version.
- PhpStorm 2026.1 uses Java 21.
- PhpStorm 2026.2+ uses Java 25.
- Build script searches existing JDK/JBR installations for the required major version and bootstraps the matching Eclipse Temurin JDK when necessary.
- `build.gradle.kts` now configures the Java toolchain, source/target compatibility and compiler release dynamically from `platformVersion`.
- Existing JDK 21 installations remain valid for PhpStorm 2026.1 builds but are intentionally skipped for 2026.2 targets.
