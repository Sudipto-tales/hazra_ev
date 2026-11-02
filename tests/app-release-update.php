<?php
// Run: php tests/app-release-update.php. In-memory DB only, no app configuration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--case') {
    require_once __DIR__ . '/../core/ApiController.php';
    require_once __DIR__ . '/../api/controllers/AppReleaseController.php';
    class JwtAuth { public static function authenticate() { return null; } }
    function base_url($path = '') { return 'https://example.invalid/' . $path; }
    function db_fetch_one($sql, $params = []) {
        global $pdo;
        $query = $pdo->prepare($sql); $query->execute($params);
        return $query->fetch(PDO::FETCH_ASSOC);
    }
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE app_releases (id TEXT, platform TEXT, version_name TEXT, version_code INTEGER, status TEXT, channel TEXT, file_size INTEGER, checksum_sha256 TEXT, file_path TEXT, file_name TEXT, published_at TEXT, created_at TEXT)');
    $insert = $pdo->prepare('INSERT INTO app_releases VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ([['published', 'production', 4], ['published', 'beta', 50], ['draft', 'production', 51], ['archived', 'production', 52], ['withdrawn', 'production', 53]] as [$status, $channel, $code]) {
        $insert->execute([sprintf('00000000-0000-4000-8000-%012d', $code), 'android', '0.1.' . $code, $code, $status, $channel, filesize(__FILE__), hash_file('sha256', __FILE__), 'tests/app-release-update.php', 'update.apk', '2026-11-02', '2026-11-02']);
    }
    $case = $argv[2];
    if ($case !== 'unauthorized') Ctx::impersonate(['id' => 'employee', 'role' => 'employee', 'active' => 1]);
    if ($case === 'no-release') $pdo->exec("DELETE FROM app_releases WHERE status='published' AND channel='production'");
    if ($case === 'missing-file') $pdo->exec("UPDATE app_releases SET file_path='missing.apk' WHERE version_code=4");
    $controller = new AppReleaseController();
    if (in_array($case, ['download', 'archived', 'beta', 'withdrawn', 'draft'])) {
        $code = ['download' => 4, 'archived' => 52, 'beta' => 50, 'withdrawn' => 53, 'draft' => 51][$case];
        $controller->setRouteParams(['id' => sprintf('00000000-0000-4000-8000-%012d', $code)]);
        $controller->updateDownload();
    }
    $controller->updateInfo();
}
function runCase(string $case): string {
    $process = proc_open([PHP_BINARY, __FILE__, '--case', $case], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $error !== '') throw new RuntimeException($case . ': ' . $error);
    return $output;
}
function ensure(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$latest = json_decode(runCase('latest'), true, 512, JSON_THROW_ON_ERROR);
ensure($latest['data']['versionCode'] === 4, 'Only published production releases may force updates');
ensure($latest['data']['checksumSha256'] === hash_file('sha256', __FILE__), 'Updater must receive integrity metadata');
ensure($latest['data']['available'] === true, 'Readable published APK must be available');
ensure(json_decode(runCase('unauthorized'), true)['error']['code'] === 'UNAUTHENTICATED', 'Updater metadata requires authentication');
ensure(json_decode(runCase('no-release'), true)['error']['code'] === 'NO_PUBLISHED_RELEASE', 'Withdrawal must release the update gate');
ensure(json_decode(runCase('missing-file'), true)['data']['available'] === false, 'Missing APK cannot auto-download');
ensure(runCase('download') === file_get_contents(__FILE__), 'Authenticated employee receives exact APK bytes');
foreach (['archived', 'beta', 'withdrawn', 'draft'] as $case) {
    ensure(json_decode(runCase($case), true)['error']['code'] === 'RELEASE_NOT_FOUND', "$case must not be offered as an update");
}
echo "Authenticated update metadata, production selection, withdrawal, and download checks passed.\n";
