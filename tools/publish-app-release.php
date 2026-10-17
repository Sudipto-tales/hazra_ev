<?php
// Deployment-only command: validates the uploaded artifact before publication.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 5 || !ctype_digit($argv[2]) || !preg_match('/^[a-f0-9]{64}$/', $argv[3]) || !preg_match('/^[a-f0-9]{40}$/', $argv[4])) {
    fwrite(STDERR, "Usage: php tools/publish-app-release.php VERSION CODE SHA256 COMMIT\n");
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->safeLoad();
require $root . '/config/env.php';
require $root . '/config/db.php';
require $root . '/core/AppReleaseAccess.php';
$row = db_fetch_one("SELECT * FROM app_releases WHERE platform = 'android' AND version_code = ?", [(int) $argv[2]]);
if (!$row || $row['version_name'] !== $argv[1] || $row['git_sha'] !== $argv[4] || !in_array($row['status'], ['draft', 'published'], true)) {
    throw new RuntimeException('The expected draft release was not found.');
}
$path = AppReleaseAccess::filePath($row, $root);
if (!$path || !hash_equals($argv[3], (string) $row['checksum_sha256']) || !hash_equals($argv[3], hash_file('sha256', $path)) || filesize($path) !== (int) $row['file_size']) {
    throw new RuntimeException('The uploaded APK failed integrity verification.');
}
$pdo->beginTransaction();
try {
    $now = gmdate('Y-m-d\TH:i:s\Z');
    db_execute("UPDATE app_releases SET status = 'archived', updated_at = ? WHERE platform = 'android' AND status = 'published' AND id <> ?", [$now, $row['id']]);
    db_execute("UPDATE app_releases SET status = 'published', published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ? AND status IN ('draft', 'published')", [$now, $now, $row['id']]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
echo "Published Android {$row['version_name']} (build {$row['version_code']}); SHA-256 verified.\n";
