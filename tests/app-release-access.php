<?php
// Run: php tests/app-release-access.php. No application database is changed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../core/AppReleaseAccess.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../database/migrations/025_app_release_download_access.php';
function base_url($path = '') { return 'https://example.invalid/' . ltrim($path, '/'); }
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$release = [
    'id' => 'test-id', 'platform' => 'android', 'version_name' => '1.2.0', 'version_code' => 12,
    'file_size' => 100, 'status' => 'archived', 'channel' => 'production', 'release_notes' => '<script>test</script>',
    'min_android_sdk' => 26, 'published_at' => '2026-09-01T12:00:00Z', 'created_at' => '2026-08-01T12:00:00Z',
    'checksum_sha256' => str_repeat('a', 64), 'git_sha' => 'internal', 'file_path' => 'missing.apk'
];
foreach (['published', 'archived'] as $status) {
    check(AppReleaseAccess::isPublic(array_replace($release, ['status' => $status])), "$status must be public");
}
foreach (['draft', 'withdrawn', 'unknown'] as $status) {
    check(!AppReleaseAccess::isPublic(array_replace($release, ['status' => $status])), "$status must stay private");
}
check(!AppReleaseAccess::isPublic(array_replace($release, ['platform' => 'ios'])), 'Only Android releases are supported');
$public = AppReleaseAccess::publicMetadata($release);
foreach (['checksumSha256', 'checksum_sha256', 'gitSha', 'file_path', 'downloadUrl', 'publishedBy'] as $field) {
    check(!array_key_exists($field, $public), "$field must not be exposed publicly");
}
check(!$public['available'], 'Missing APK must be unavailable');
check($public['androidRequirement'] === 'Android 8.0+', 'Minimum Android version must be readable');
check($public['publishedAt'] === $release['published_at'], 'Original publication date must be preserved');
$root = sys_get_temp_dir() . '/hazra-access-' . bin2hex(random_bytes(5));
mkdir($root);
file_put_contents($root . '/sample.apk', 'test');
try {
    check(AppReleaseAccess::filePath(['file_path' => 'sample.apk'], $root) !== null, 'Existing readable file must resolve');
    check(AppReleaseAccess::filePath(['file_path' => '.'], $root) === null, 'Directories must not be served');
    check(AppReleaseAccess::filePath(['file_path' => '../' . basename(__FILE__)], $root) === null, 'Files outside storage root must be rejected');
} finally { unlink($root . '/sample.apk'); rmdir($root); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE app_download_logs (id TEXT PRIMARY KEY)');
$migration = new AppReleaseDownloadAccess($pdo);
$migration->up(); $migration->up();
$columns = $pdo->query('PRAGMA table_info(app_download_logs)')->fetchAll(PDO::FETCH_ASSOC);
check(in_array('download_started_at', array_column($columns, 'name')), 'Download logging migration must be idempotent');
$pdo->exec("INSERT INTO app_download_tokens VALUES ('token', 'release', 'employee', 'log', 'session', '2026-10-01T12:10:00Z', '2026-10-01T12:00:00Z')");
check((int) $pdo->query("SELECT COUNT(*) FROM app_download_tokens WHERE expires_at > '2026-10-01T12:11:00Z'")->fetchColumn() === 0, 'Expired grants must not authorize downloads');
// Render the actual page without bootstrapping or changing the application DB.
class App {
    public static function render(string $name, array $data = []): void {}
}
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function db_fetch_one($sql, $params = []) { if (!empty($GLOBALS['pageLoadFailure'])) throw new RuntimeException('Release storage unavailable'); return $GLOBALS['pageRelease']; }
function db_fetch_all($sql, $params = []) { return $GLOBALS['pageHistory']; }
function renderDownloadPage(): string {
    ob_start();
    try { include __DIR__ . '/../app/page/app-download.php'; return ob_get_contents(); }
    finally { ob_end_clean(); }
}
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $pageRelease = null; $pageHistory = [];
    $html = renderDownloadPage();
    check(str_contains($html, 'Previous releases will appear here'), 'Empty release history must render');
    check(str_contains($html, 'Download APK'), 'Empty state must show a download button');
    $pageLoadFailure = true;
    $html = renderDownloadPage();
    check(str_contains($html, 'Downloads are temporarily unavailable'), 'Storage failure must show a readable message');
    $pageLoadFailure = false;
    $pageRelease = array_replace($release, ['status' => 'published']);
    $pageHistory = [];
    for ($i = 11; $i >= 5; $i--) {
        $pageHistory[] = array_replace($release, ['id' => 'history-' . $i, 'version_code' => $i]);
    }
    $html = renderDownloadPage();
    check(substr_count($html, 'class="release-card"') === 6, 'Only six previous releases must initially render');
    check(!str_contains($html, 'id="release-history-5"'), 'Seventh release must be deferred to pagination');
    check(str_contains($html, '* Requires Android 8.0+.'), 'Installation guide must use release requirements');
    check(!str_contains($html, '<script>test</script>'), 'Release notes must be escaped in page HTML');
    check(str_contains($html, 'APK unavailable'), 'Missing APK must render as unavailable');
    preg_match('/<script id="releaseConfig"[^>]*>(.*?)<\/script>/s', $html, $match);
    $config = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    check(Cursor::decode($config['nextCursor'])['value'] === 6, 'History cursor must start after sixth release');
} finally { restore_error_handler(); }

echo "App release access and page rendering checks passed.\n";
