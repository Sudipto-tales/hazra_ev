<?php
// Import a CI-built APK over the authenticated deployment SSH connection.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 6 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,31}$/', $argv[1]) || !ctype_digit($argv[2]) || (int) $argv[2] < 1 || !preg_match('/^[a-f0-9]{64}$/', $argv[3]) || !preg_match('/^[a-f0-9]{40}$/', $argv[4])) {
    throw new RuntimeException('Expected VERSION CODE SHA256 COMMIT APK_PATH.');
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->safeLoad();
require $root . '/config/env.php';
require $root . '/config/db.php';
require $root . '/api/support/Uuid.php';
require $root . '/api/support/Wire.php';
$file = realpath($argv[5]);
if (!$file || !is_file($file) || !hash_equals($argv[3], hash_file('sha256', $file)) || filesize($file) > (int) env('APP_RELEASE_MAX_BYTES', 104857600)) {
    throw new RuntimeException('APK integrity or size check failed.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['application/zip', 'application/vnd.android.package-archive', 'application/java-archive', 'application/octet-stream'], true)) throw new RuntimeException('Invalid APK MIME type.');
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true || $zip->locateName('AndroidManifest.xml') === false || $zip->locateName('classes.dex') === false) {
        throw new RuntimeException('The artifact is not an Android APK.');
    }
    $zip->close();
}
$existing = db_fetch_one("SELECT * FROM app_releases WHERE platform = 'android' AND version_code = ?", [(int) $argv[2]]);
if ($existing) {
    if ($existing['version_name'] !== $argv[1] || $existing['git_sha'] !== $argv[4] || $existing['checksum_sha256'] !== $argv[3]) throw new RuntimeException('Version code already belongs to a different artifact.');
    echo "The matching release already exists; continuing verification.\n";
    exit(0);
}
$storage = trim((string) env('APP_RELEASE_STORAGE', 'storage/apk'), '/');
if ($storage === '' || str_contains($storage, '..') || str_contains($storage, '\\') || str_contains($storage, ':')) throw new RuntimeException('Invalid release storage directory.');
$relative = $storage . '/' . $argv[1] . '/app-v' . $argv[2] . '.apk';
$directory = dirname($root . '/' . $relative);
if (!is_dir($directory) && !mkdir($directory, 0775, true)) throw new RuntimeException('Could not create release storage.');
$target = $root . '/' . $relative;
if (file_exists($target)) throw new RuntimeException('Release file already exists.');
if (!copy($file, $target) || !hash_equals($argv[3], hash_file('sha256', $target))) throw new RuntimeException('Could not store the verified APK.');
$now = Wire::now();
try {
    db_execute('INSERT INTO app_releases (id, platform, version_name, version_code, file_path, file_name, file_size, checksum_sha256, mime, status, channel, release_notes, git_tag, git_sha, build_source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [Uuid::v4(), 'android', $argv[1], (int) $argv[2], $relative, 'app-release.apk', filesize($target), $argv[3], 'application/vnd.android.package-archive', 'draft', 'production', 'Product editor updates, account deletion requests, privacy and terms links, and tracking improvements.', 'mobile-v' . $argv[1], $argv[4], 'github_actions', $now, $now]);
} catch (Throwable $error) {
    unlink($target);
    throw $error;
}
echo "Imported verified Android {$argv[1]} (build {$argv[2]}) as draft.\n";
