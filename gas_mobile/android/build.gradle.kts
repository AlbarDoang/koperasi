import com.android.build.gradle.LibraryExtension

plugins {
    id("com.android.application") apply false
    id("com.android.library") apply false
    id("org.jetbrains.kotlin.android") apply false
}

allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

// CUSTOM BUILD DIR FIX FOR FLUTTER
val newBuildDir: Directory = rootProject.layout.buildDirectory
    .dir("../../build")
    .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}

// Ensure library subprojects that lack an explicit namespace get a sane default
subprojects {
    // Some plugins (third-party) may not set 'namespace' yet which causes AGP to fail
    plugins.withId("com.android.library") {
        extensions.configure<LibraryExtension> {
            if (this.namespace.isNullOrBlank()) {
                // Use a predictable fallback namespace using the root app package + project name
                this.namespace = "com.example.tabungan.${project.name.replace(':','_')}"
            }
        }
    }
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}