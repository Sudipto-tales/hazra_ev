# App release downloads

The download page shows the current Android release and all previous public releases, in descending version-code order. Previous releases load six at a time. Dates use the original publication date, with upload dates as the fallback for legacy records whose publication date was previously cleared.

Admin release states:

| State | Public behavior |
| --- | --- |
| Draft | Private; admins can download or delete it. |
| Published | Current release; publishing archives the previous current release in the same transaction. |
| Archived | Listed under Previous Versions and downloadable after verification. |
| Withdrawn | Hidden from the public list and blocked from public downloads, including existing verification links. Admins can restore it to Previous Versions. |

The upload form accepts an APK, unique positive Android version code, safe version name, release notes, channel, and optional minimum Android API level. Existing APK files are not overwritten. Missing APK files appear as unavailable and cannot be published or restored; they can still be withdrawn.

All public downloads require an active employee code or registered phone number. Successful verification creates a download log for the selected release and a ten-minute, session-bound link. Following that link records the download start; it does not claim the device completed the transfer or installed the app. Cancelling the dialog cancels the browser request. Admin downloads remain authenticated and do not require employee verification.

Public HTML and release API responses do not include checksums, disk paths, or Git metadata. SHA-256 remains in authenticated admin metadata for internal checks. Shared admin links point to the verification page for the chosen version.

Deploy with `php vayu migrate` to apply migration 025. Apache must permit the repository `.htaccess` rules: the `storage/apk` rule blocks direct file access. If another web server is used, configure its equivalent deny rule for APK storage and any custom `APP_RELEASE_STORAGE` path; serve APKs through the authorized API only.

Run `php tests/app-release-access.php` for metadata, availability, migration, and page-rendering regression checks. The PHP development router also blocks direct `storage/apk` requests. Before deployment, verify APK upload, publish, previous-version download, withdrawal/restoration, missing-file behavior, employee verification, expiry, session isolation, and mobile/light/dark rendering.

The Android app now includes a mandatory in-app updater for signed-in employees and admins. On startup, after sign-in, and on resume, it requests `GET /api/v1/app-releases/update` using the normal bearer token. The latest **published production** release is required whenever its numeric Android `versionCode` exceeds the installed build number. Draft, archived, withdrawn and beta releases never trigger the updater. This deliberately requires every newer production release; there is no separate minimum-version switch or migration.

The authenticated metadata adds `checksumSha256`. `GET /api/v1/app-releases/{id}/update-apk` streams only a currently published production APK to an active authenticated account; public browser downloads retain employee verification. The app automatically streams the APK into private storage, shows download progress, checks SHA-256, size, package ID, build number and minimum Android SDK, then opens Android's package installer. Only the updates directory is exposed through a read-only FileProvider grant. APKs must use the existing application ID and signing key, a higher build number, and support the device's architecture. Android validates the signing identity at installation.

On Android 8+, the first update may require allowing installs from Hazra EV in system settings. Returning after permission is granted opens the installer automatically. Denying permission or cancelling installation leaves the blocking update screen with a retry button. The app never claims installation succeeded merely because the installer opened. A valid downloaded APK is reused after a restart; interrupted or corrupt downloads are restarted. No broad storage permission is needed.

A known required release is cached and remains enforced during an outage. With no cached requirement, a failed first check allows offline work and retries on the next resume. Signed-out users can sign in before the protected download starts. An incompatible or unavailable required APK shows a blocking message; publish a compatible release or withdraw the current production release to recover. A successful response with no published production release clears the cached requirement.

Rollout requires deploying the two API routes and distributing a signed updater-enabled APK once, with a build number higher than all existing installations. APKs distributed before this updater existed cannot acquire the checker remotely; those users must install this first build through the existing download page. Future newer production releases will then download and prompt for installation inside the app. Test on a physical Android device: automatic download, install permission denied/granted, installer cancel/retry, bad checksum, interrupted download, offline relaunch, withdrawal, and a successful upgrade preserving the login. Do not release a build pointing at the default localhost API; supply the production `API_BASE_URL` as in the release workflow.

Additional checks: `php tests/app-release-update.php` and, from `mobile_app`, `flutter test test/update_controller_test.dart`.
