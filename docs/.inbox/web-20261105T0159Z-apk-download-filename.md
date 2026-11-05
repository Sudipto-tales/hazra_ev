---
agent: web-agent
date: 2026-11-05T01:59Z
status: done
breaking: false
---

## What changed
- website/api/controllers/AppReleaseController.php — APK downloads now save as
  `Hazra-EV-v<version_name>.apk` instead of the stored upload name
  (`app-release.apk` for every build). Added private `downloadFilename()` and
  `sanitizeFilename()` helpers plus a `DOWNLOAD_BRAND` constant; `serveFile()`
  delegates to them. The strict `[^A-Za-z0-9._-]` → `_` whitelist still runs on
  the final string before it reaches `Content-Disposition` (`version_name` is
  operator-supplied, so it is treated as hostile). Extension is taken from the
  stored `file_name`, and a missing/blank `version_name` falls back to
  `basename(file_name)`.

## API contract impact
- `GET /api/v1/app-releases/{id}/download` — `Content-Disposition` filename value
  changed (header only; no body, status or JSON shape change). Not breaking.
- `GET /api/v1/admin/app-releases/{id}/download` — same.
- `GET /api/v1/app-releases/{id}/update-apk` — same header change, but the Flutter
  in-app updater ignores it: MainActivity.kt writes to a fixed
  `filesDir/updates/update.apk`. No mobile action needed.

## Needs doing next
- [ ] Nothing required. Optional: `serveFile()` still hardcodes
      `Content-Type: application/vnd.android.package-archive` even though the
      stored `mime` column exists — a non-APK artifact would download with the
      right extension but the wrong type (web-agent, only if non-APK artifacts
      ever become real).

## Blocked on
- nothing
