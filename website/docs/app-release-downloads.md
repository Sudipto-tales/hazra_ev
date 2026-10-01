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
