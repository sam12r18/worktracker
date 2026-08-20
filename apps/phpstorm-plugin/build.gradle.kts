import org.jetbrains.intellij.platform.gradle.IntelliJPlatformType

plugins {
    java
    id("org.jetbrains.intellij.platform") version "2.18.1"
}

group = "ir.rayaasun.worktracker"
version = providers.gradleProperty("pluginVersion").get()

val targetPlatformVersion = providers.gradleProperty("platformVersion").orElse("2025.1").get()
val platformParts = targetPlatformVersion.split('.')
val platformYear = platformParts.getOrNull(0)?.toIntOrNull() ?: 2025
val platformRelease = platformParts.getOrNull(1)?.toIntOrNull() ?: 1
val requiredJavaMajor = if (platformYear > 2026 || (platformYear == 2026 && platformRelease >= 2)) 25 else 21
val pluginSinceBuild = providers.gradleProperty("pluginSinceBuild").orElse("251").get()
val pluginUntilBuild = providers.gradleProperty("pluginUntilBuild").orElse("262.*").get()

repositories {
    mavenCentral()
    intellijPlatform {
        defaultRepositories()
    }
}

dependencies {
    intellijPlatform {
        phpstorm(targetPlatformVersion)
        bundledPlugin("com.jetbrains.php")
        bundledPlugin("Git4Idea")
    }
}

java {
    toolchain {
        languageVersion.set(JavaLanguageVersion.of(requiredJavaMajor))
    }
    sourceCompatibility = JavaVersion.toVersion(requiredJavaMajor)
    targetCompatibility = JavaVersion.toVersion(requiredJavaMajor)
}

tasks.withType<JavaCompile>().configureEach {
    options.release.set(requiredJavaMajor)
}

intellijPlatform {
    pluginConfiguration {
        name = "WorkTracker Context Bridge"
        version = providers.gradleProperty("pluginVersion").get()
        description = "Publishes local PhpStorm project, active file, execution state, run configuration, and Git branch context to the WorkTracker Windows Agent."
        ideaVersion {
            sinceBuild = pluginSinceBuild
            untilBuild = pluginUntilBuild
        }
        vendor {
            name = "Rayaasun"
        }
    }

    pluginVerification {
        ides {
            create(IntelliJPlatformType.PhpStorm, "2025.1")
            create(IntelliJPlatformType.PhpStorm, "2025.2")
            create(IntelliJPlatformType.PhpStorm, "2025.3")
            create(IntelliJPlatformType.PhpStorm, "2026.1")
            create(IntelliJPlatformType.PhpStorm, "2026.2")
        }
    }
}
