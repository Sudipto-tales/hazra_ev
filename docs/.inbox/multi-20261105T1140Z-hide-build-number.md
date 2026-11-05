---
agent: main
date: 2026-11-05T11:40Z
status: done
needs-api: false
---

# Build number is no longer shown anywhere; the version number is set apart

Folders touched: `mobile_app/`, `website/app/page/`, `website/assets/js/`
(plus this entry). Two columns in one task because the same string is rendered
by both consumers — flagging the cross-folder reach rather than hiding it.

Verification: `flutter analyze lib test` **15 issues, 0 errors** — the
baseline 14 plus one that this task then fixed (an unnecessary `dart:ui`
import in the new widget); re-analysed clean · `flutter test` **220 passed /
23 skipped / 0 failed**, unchanged · `php -l` and `node --check` clean on the
two website files.

## What changed

**Mobile — `mobile_app/lib/features/profile/version_label.dart` (new).**
One widget, `VersionLabel`, is now the only way a version reaches the screen.
It renders "Version" in the surrounding text style and the number itself in
`w800` with `letterSpacing: 0.6` and `FontFeature.tabularFigures()`, so the
number reads as a distinct typographic object rather than as more body text.
`prefix` covers the one caller that needs a lead-in (`Admin console · `);
`numberStyle` lets the version screen push the number to display size.
The project ships no custom font families, so the emphasis is weight,
tracking and figure style rather than a second typeface.

**`AppInfo.label` is now `'Version $version'`** — the ` · build $build` half
is gone. That one getter fed six of the nine render sites, so the build
number disappeared from all of them at once:

| Where | Was | Now |
| --- | --- | --- |
| `role_select_page.dart` footer | `Version 0.1.7 · build 8` | `VersionLabel` |
| `login_page.dart` footer | same | `VersionLabel` |
| `admin_login_page.dart` footer | same | `VersionLabel` |
| `info_pages.dart` (About) | same | `VersionLabel` |
| `admin_profile_page.dart` footer | `Admin console · …` | `VersionLabel(prefix:)` |
| `version_page.dart` "App version" row | same | `VersionLabel` |
| `version_page.dart` reveal | `Version 0.1.7` + `build 8` on two lines | one `VersionLabel`, number at `headlineMedium`/`w800` |
| `profile_page.dart` About subtitle | `Version 0.1.7 · build 8` | `AppInfo.label` → version only (tile subtitle is a `String`) |
| `support_page.dart` diagnostics | `'App version': …· build 8` | `AppInfo.label` → version only |

`AppInfo.build` **stays**, and is deliberately still populated by `init()`.
Nothing renders it; its only remaining job is the `versionCode >` comparison
in `update_controller.dart:38`, which is what decides whether an update is
offered. Removing the field would break the update check.

**Website.** The public download page listed a `Build <n>` chip in each
release card's meta row, in both code paths that build that row:
`website/app/page/app-download.php:603` (first page, server-rendered) and
`website/assets/js/app-download.js:100` (the "load more" path). Both chips
removed; the two paths are commented as needing to stay in step. The version
name (`v0.1.7`) remains the card heading, which is now the only release
identity a visitor sees.

**Test.** `test/settings_about_test.dart` asserted on both strings. It now
asserts `find.text('Version …', findRichText: true)` — `findRichText` because
`VersionLabel` is a `Text.rich`, so the full string only exists in the span
tree — plus `find.textContaining('build'/'Build')` → `findsNothing`, which is
the regression guard that keeps the number out. The guard matches the word,
not `AppInfo.build`, whose test-harness fallback (`'1'`) is a substring of the
fallback version (`'0.1.0'`) and would match the line above it.

## Left alone, deliberately

- **Admin console release manager** (`website/assets/admin/js/pages/app-releases.js`
  — the `+<version_code>` sub-cell and the required "Version code" upload
  field). The version code *is* the APK's identity for the person uploading
  it, and the API rejects an upload without one. This is operator UI, not
  something an employee or a visitor sees.
- **API responses** (`AppReleaseAccess.php`, `AppReleaseController.php` still
  return `versionCode`). Contract unchanged — the mobile update check depends
  on it. No `docs/02-API-PLAN.md` edit needed.
- **CLI tools** (`website/tools/publish-app-release.php`,
  `import-app-release.php` echo "build <n>"). Maintainer terminal output.

## For docs-agent

If any product doc shows the version as `Version X · build Y`, that format no
longer exists in the UI.
