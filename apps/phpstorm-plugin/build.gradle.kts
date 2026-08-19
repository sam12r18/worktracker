plugins {
    java
    id("org.jetbrains.intellij.platform") version "2.18.1"
}

group = "ir.rayaasun.worktracker"
version = providers.gradleProperty("pluginVersion").get()

repositories {
    mavenCentral()
    intellijPlatform {
        defaultRepositories()
    }
}

dependencies {
    intellijPlatform {
        phpstorm(providers.gradleProperty("platformVersion").get())
        bundledPlugin("com.jetbrains.php")
        bundledPlugin("Git4Idea")
    }
}

java {
    sourceCompatibility = JavaVersion.VERSION_21
    targetCompatibility = JavaVersion.VERSION_21
}

tasks.withType<JavaCompile>().configureEach {
    options.release.set(21)
}

intellijPlatform {
    pluginConfiguration {
        name = "WorkTracker Context Bridge"
        version = providers.gradleProperty("pluginVersion").get()
        description = "Publishes local PhpStorm project, active file, execution state, run configuration, and Git branch context to the WorkTracker Windows Agent."
        ideaVersion {
            sinceBuild = "261"
            untilBuild = "262.*"
        }
        vendor {
            name = "Rayaasun"
        }
    }
}
