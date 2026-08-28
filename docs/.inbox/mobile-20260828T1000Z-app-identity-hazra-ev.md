---
agent: mobile-agent
date: 2026-08-28T10:00Z
status: done
handoff-to: user (back up the upload keystore, supply a ≥1024px logo), web-agent (confirm https://hazraelectricalbike.com/api/v1 is live)
---

# mobile-agent — app renamed to "Hazra EV", real launcher icon, release rebuild

Folder touched: `mobile_app/` only (plus the pre-existing `outputs/` APK copies at
the repo root, refreshed so they are not stale). `html/assets/hazraev.png` was
read as the icon source, never written.

Asked for on 2026-08-27 and not applied until now: the build still shipped as
`employeetracking_mobile_app` / `com.example.*` with the stock Flutter icon.

## Identity

| | Before | After |
| --- | --- | --- |
| Launcher label | `employeetracking_mobile_app` | `Hazra EV` |
| `applicationId` | `com.example.employeetracking_mobile_app` | `com.hazraelectricalbike.app` |
| Android `namespace` | same as above | `com.hazraelectricalbike.app` |
| `MaterialApp.title` (recents label) | `Field Tracker` | `Hazra EV` |
| Linux `BINARY_NAME` / `APPLICATION_ID` / window title | `employeetracking_mobile_app` | `hazra_ev` / `com.hazraelectricalbike.app` / `Hazra EV` |

`MainActivity.kt` moved from `kotlin/com/example/employeetracking_mobile_app/`
to `kotlin/com/hazraelectricalbike/app/` and its `package` line updated, so the
Kotlin package matches the new namespace. The two scaffold `// TODO: Specify
your own unique Application ID` lines are gone.

The Dart package name in `pubspec.yaml` is **still**
`employeetracking_mobile_app`. It is invisible to users and renaming it would
rewrite every `package:employeetracking_mobile_app/…` import in `lib/` and
`test/`. Left alone deliberately; say so if it should change.

## Icon

Generated from `html/assets/hazraev.png` (200×200 RGBA, logo occupying a
122×128 opaque box) into the five legacy `mipmap-*` densities — 48 / 72 / 96 /
144 / 192 px. The transparent margin is trimmed, the mark is scaled to 78% of
the square with LANCZOS, and it is composited on opaque white so it reads on
both light and dark launchers.

**No adaptive icon (`mipmap-anydpi-v26`) was added.** An adaptive foreground
needs a 432 px canvas at xxxhdpi with the mark inside a 66% safe zone — from a
122 px source that is a 2.3× upscale and would ship visibly soft. A logo at
**1024×1024 or larger** would let the adaptive icon be generated properly; until
then Android 8+ masks the legacy icon into a white badge, which is acceptable
but not ideal.

## Build

```sh
flutter build apk --release --split-per-abi \
  --dart-define=API_BASE_URL=https://hazraelectricalbike.com/api/v1
```

`ApiConfig` defaults to `http://localhost:8000/api/v1`, so a production APK must
carry that `--dart-define` or it will point at the developer's machine.

Verified with `aapt2 dump badging` on the arm64 APK:

```
package: name='com.hazraelectricalbike.app' versionCode='2001' versionName='0.1.0'
application-label:'Hazra EV'
```

Artifacts (2026-08-28, in `mobile_app/build/app/outputs/flutter-apk/` and copied
to `outputs/flutter-apk/` + `outputs/apk/release/`):

| APK | Size |
| --- | --- |
| `app-arm64-v8a-release.apk` | 20.3 MB |
| `app-armeabi-v7a-release.apk` | 18.0 MB |
| `app-x86_64-release.apk` | 21.8 MB |

## Signing — resolved

An upload keystore was generated on 2026-08-28 and the release build now uses it.

```
/home/weloin/keystores/hazra-ev-upload.jks     (outside the repo, chmod 600)
alias    upload
key      RSA 4096, SHA384withRSA
valid    2026-08-28 → 2054-01-13
subject  CN=Hazra EV, O=Hazra Electrical Bike, L=Bardhaman, ST=West Bengal, C=IN
SHA-1    41:9A:E0:36:F4:5D:DE:43:67:21:B1:20:D1:06:37:3A:62:67:6A:5E
```

`mobile_app/android/key.properties` holds the credentials and points at that
path. It is chmod 600 and matched by `android/.gitignore:12`; `*.jks` is also
ignored at the repo root. **The password is not recorded in this file or
anywhere else in the repo** — it was handed to the owner once, in chat.

`android/app/build.gradle.kts` reads `key.properties` if present and signs
release with it; if the file is absent (a fresh clone, CI) it falls back to the
debug key so the project still builds. The old `// TODO: Add your own signing
config` comment is gone.

Verified — `apksigner verify --print-certs` on the arm64 APK and `jarsigner
-verify` on the bundle both report
`CN=Hazra EV, O=Hazra Electrical Bike, …`, matching the keystore fingerprint.

**This keystore is now irreplaceable.** Play ties the listing to it; if it is
lost, that app can never be updated again. It must be backed up somewhere the
developer's laptop dying does not take with it.

## Artifacts

Built with

```sh
flutter build apk --release --split-per-abi \
  --dart-define=API_BASE_URL=https://hazraelectricalbike.com/api/v1
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://hazraelectricalbike.com/api/v1
```

| File | Size | Use |
| --- | --- | --- |
| `bundle/release/app-release.aab` | 43.3 MB | Play Console upload |
| `flutter-apk/app-arm64-v8a-release.apk` | 19.3 MB | sideload, modern phones |
| `flutter-apk/app-armeabi-v7a-release.apk` | 17.1 MB | sideload, 32-bit phones |
| `flutter-apk/app-x86_64-release.apk` | 20.7 MB | emulator |

Under `mobile_app/build/app/outputs/`, copied to `outputs/` at the repo root.

## Note — the root `outputs/` tree was wiped mid-build

Partway through the signed rebuild the tracked `outputs/` directory lost every
file (`git status` showed 29 deletions that nothing in this task asked for).
Restored with `git checkout -- outputs/`, then the new artifacts were copied in;
nothing was lost, since all of it was committed. Cause not identified — the
Flutter/Gradle build writes only to `mobile_app/build/`, so the collision is
unexplained and worth watching for on the next build.

This is a good argument for not tracking build artifacts at all: they are ~60 MB
per rebuild in git history, `outputs/**/app-debug.apk` (168 MB) still carries the
pre-rename identity, and the directory is evidently not stable. Gitignoring
`outputs/` and publishing releases as GitHub release assets would be cleaner.
