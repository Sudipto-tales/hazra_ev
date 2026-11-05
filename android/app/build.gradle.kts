import java.util.Properties

// Upload-key credentials. Kept out of the repo (android/.gitignore) — the file
// points at a .jks that lives outside the checkout. Without it the release
// build falls back to the debug key, so a fresh clone still compiles.
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
val hasReleaseKey = keystorePropertiesFile.exists()
if (hasReleaseKey) {
    keystorePropertiesFile.inputStream().use { keystoreProperties.load(it) }
}

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.hazraelectricalbike.app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = "27.0.12077973"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        applicationId = "com.hazraelectricalbike.app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        // Default for EVERY build type, overridden only by `debug` below.
        // Set here rather than per-type on purpose: Flutter drives three build
        // types (debug, profile, release), and a placeholder that is missing
        // on any one of them fails the manifest merger for that type. Declaring
        // it per-type means `flutter run --profile` breaks the moment someone
        // reaches for it.
        manifestPlaceholders["appNameSuffix"] = ""
    }

    signingConfigs {
        if (hasReleaseKey) {
            create("release") {
                storeFile = file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName(if (hasReleaseKey) "release" else "debug")
            // Inherits the empty suffix from defaultConfig: production is
            // plain "Hazra EV" with the unsuffixed applicationId, exactly as
            // before. Nothing about the CI release path changes.
        }

        // A locally built app installs ALONGSIDE the production one instead of
        // replacing it. That is the point of the id suffix, and it is not
        // cosmetic:
        //
        //  * Same applicationId means Android treats the two as one app, so a
        //    debug install silently overwrites the Play/sideloaded production
        //    build — taking its signed-in session and queued offline fixes with
        //    it. On a phone that is also doing real fieldwork that is data loss.
        //  * The FileProvider authority above is `${applicationId}.updates`.
        //    Provider authorities are globally unique per device, so without a
        //    distinct applicationId the second install is refused outright with
        //    INSTALL_FAILED_CONFLICTING_PROVIDER. The suffix is what makes
        //    side-by-side possible at all.
        //
        // Release is untouched: `flutter build apk --release` and the CI
        // pipeline produce exactly what they did before.
        debug {
            applicationIdSuffix = ".local"
            versionNameSuffix = "-local"
            manifestPlaceholders["appNameSuffix"] = " (local)"
        }
    }
}

flutter {
    source = "../.."
}
