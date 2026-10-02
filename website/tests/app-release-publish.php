<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = dirname(__DIR__);
$root = sys_get_temp_dir() . '/hazra-release-' . bin2hex(random_bytes(6));
foreach (['tools', 'config', 'vendor', 'core', 'storage'] as $dir) mkdir($root . '/' . $dir, 0700, true);
copy($source . '/tools/publish-app-release.php', $root . '/tools/publish-app-release.php');
copy($source . '/core/AppReleaseAccess.php', $root . '/core/AppReleaseAccess.php');
file_put_contents($root . '/vendor/autoload.php', '<?php namespace Dotenv; class Dotenv { public static function createImmutable($root) { return new self; } public function safeLoad() {} }');
file_put_contents($root . '/config/env.php', '<?php');
file_put_contents($root . '/config/db.php', '<?php $pdo = new PDO("sqlite:" . dirname(__DIR__) . "/test.sqlite"); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); function db_fetch_one($sql, $params) { global $pdo; $s=$pdo->prepare($sql); $s->execute($params); return $s->fetch(PDO::FETCH_ASSOC); } function db_execute($sql, $params) { global $pdo; $s=$pdo->prepare($sql); $s->execute($params); return $s->rowCount(); }');
$pdo = new PDO('sqlite:' . $root . '/test.sqlite');
$pdo->exec('CREATE TABLE app_releases (id TEXT, platform TEXT, version_code INTEGER, version_name TEXT, git_sha TEXT, status TEXT, file_path TEXT, checksum_sha256 TEXT, file_size INTEGER, published_at TEXT, updated_at TEXT)');
$commit = str_repeat('a', 40);
$apk = 'test-artifact';
$sha = hash('sha256', $apk);
file_put_contents($root . '/storage/app.apk', $apk);
$s = $pdo->prepare('INSERT INTO app_releases VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)');
$s->execute(['old', 'android', 1, '0.1.0', $commit, 'published', 'storage/app.apk', $sha, strlen($apk)]);
$s->execute(['new', 'android', 2, '0.1.1', $commit, 'draft', 'storage/app.apk', $sha, strlen($apk)]);
function runPublish(string $hash): int {
    global $root, $commit;
    $proc = proc_open([PHP_BINARY, $root . '/tools/publish-app-release.php', '0.1.1', '2', $hash, $commit], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($proc);
}
function checkRelease(bool $condition): void { if (!$condition) throw new RuntimeException('Release publication check failed.'); }
try {
    checkRelease(runPublish(str_repeat('0', 64)) !== 0);
    checkRelease($pdo->query("SELECT status FROM app_releases WHERE id='old'")->fetchColumn() === 'published');
    checkRelease($pdo->query("SELECT status FROM app_releases WHERE id='new'")->fetchColumn() === 'draft');
    checkRelease(runPublish($sha) === 0);
    checkRelease($pdo->query("SELECT status FROM app_releases WHERE id='old'")->fetchColumn() === 'archived');
    checkRelease($pdo->query("SELECT status FROM app_releases WHERE id='new'")->fetchColumn() === 'published');
    checkRelease(runPublish($sha) === 0);
    file_put_contents($root . '/storage/app.apk', 'tampered');
    checkRelease(runPublish($sha) !== 0);
    echo "Release publication rejects invalid artifacts, archives the previous release, and safely retries.\n";
} finally {
    $s = null;
    $pdo = null;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
