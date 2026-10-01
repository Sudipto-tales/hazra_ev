<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../../core/AppReleaseAccess.php';

/**
 * Controller for managing App Releases (APK files, versions, etc.)
 */
final class AppReleaseController extends V1Controller
{
    private const ALLOWED_MIME = [
        'application/vnd.android.package-archive' => 'apk',
        'application/octet-stream' => 'apk',
        'application/java-archive' => 'apk',
    ];

    private const VALID_STATUSES = ['draft', 'published', 'archived', 'withdrawn'];
    private const VALID_PLATFORMS = ['android'];
    private const VALID_CHANNELS = ['production', 'beta'];
    private const VALID_SOURCES = ['github_actions', 'admin_upload'];

    public function latest(): never
    {
        $row = db_fetch_one(
            "SELECT * FROM app_releases WHERE status = 'published' AND platform = 'android' ORDER BY version_code DESC LIMIT 1",
            []
        );

        if (!$row) {
            Envelope::notFound('NO_PUBLISHED_RELEASE', 'No published release available');
        }

        Envelope::ok(AppReleaseAccess::publicMetadata($row));
    }

    public function index(): never
    {
        $limit = Cursor::limit((string) ($this->query('limit') ?? $this->query('pageSize', '20')));
        $cursorStr = $this->query('cursor', '');

        $sql = "SELECT * FROM app_releases WHERE platform = 'android' AND status IN ('published', 'archived')";
        $params = [];
        $excludeId = (string) $this->query('exclude', '');
        if ($excludeId !== '') { $sql .= ' AND id != ?'; $params[] = $excludeId; }
        $countSql = $sql;
        $countParams = $params;
        if ($limit === 0) {
            $total = db_fetch_one(str_replace('SELECT *', 'SELECT COUNT(*) AS c', $countSql), $countParams);
            Envelope::ok([], ['total' => (int) $total['c'], 'nextCursor' => null]);
        }

        $cursor = null;
        if ($cursorStr !== '') {
            $cursor = Cursor::decode($cursorStr);
            if (!$cursor || !isset($cursor['value']) || !is_numeric($cursor['value'])) Envelope::invalid('Invalid release cursor', 'cursor');
            if ($cursor) {
                $sql .= " AND version_code < ?";
                $params[] = $cursor['value'];
            }
        }

        $sql .= " ORDER BY version_code DESC LIMIT ?";
        $params[] = $limit + 1;

        $rows = db_fetch_all($sql, $params);

        $nextCursor = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $last = end($rows);
            $nextCursor = Cursor::encode(['value' => $last['version_code']]);
        }

        $totalRow = db_fetch_one(str_replace('SELECT *', 'SELECT COUNT(*) as c', $countSql), $countParams);
        $total = $totalRow['c'] ?? 0;

        Envelope::ok(array_map([AppReleaseAccess::class, 'publicMetadata'], $rows), [
            'total' => (int) $total,
            'nextCursor' => $nextCursor
        ]);
    }

    public function download(): never
    {
        $id = $this->param('id');
        $row = $this->findRelease($id);

        if (!AppReleaseAccess::isPublic($row)) {
            Envelope::notFound('RELEASE_NOT_FOUND', 'Release not found or not available');
        }

        $this->requireFile($row);
        $token = (string) $this->query('token', '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) Envelope::fail('DOWNLOAD_VERIFICATION_REQUIRED', 'Verify your employee details on the download page first.', 403);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $grant = db_fetch_one('SELECT * FROM app_download_tokens WHERE token_hash = ? AND release_id = ? AND expires_at > ?', [hash('sha256', $token), $row['id'], Wire::now()]);
        if (!$grant || !hash_equals($grant['session_hash'], hash('sha256', session_id()))) Envelope::fail('DOWNLOAD_LINK_EXPIRED', 'This download link has expired. Please verify your details again.', 403);
        $employee = db_fetch_one("SELECT id FROM users WHERE id = ? AND role = 'employee' AND active = 1", [$grant['employee_id']]);
        if (!$employee) Envelope::fail('EMPLOYEE_UNAVAILABLE', 'Your employee account is no longer available.', 403);
        db_execute('UPDATE app_download_logs SET download_started_at = COALESCE(download_started_at, ?) WHERE id = ?', [Wire::now(), $grant['log_id']]);
        session_write_close();
        $this->serveFile($row);
    }

    public function requestDownload(): never
    {
        $id = $this->param('id');
        $row = $this->findRelease($id);

        if (!AppReleaseAccess::isPublic($row)) {
            Envelope::notFound('RELEASE_NOT_FOUND', 'Release not available');
        }
        $this->requireFile($row);

        $body = ApiRequest::body();
        $code = strtoupper(trim((string) ($body['employee_code'] ?? '')));
        $mobile = preg_replace('/\D+/', '', (string) ($body['mobile'] ?? ''));

        if (strlen($code) > 64 || ($mobile !== '' && !preg_match('/^[0-9]{10,15}$/', $mobile))) Envelope::invalid('Enter a valid employee code or mobile number');
        if ($code === '' && strlen($mobile) < 10) {
            Envelope::invalid('Enter employee code or mobile number');
        }

        $emp = null;

        if ($code !== '') {
            $emp = db_fetch_one(
                "SELECT u.id, u.name, u.phone, ep.employee_code
                 FROM users u
                 JOIN employee_profiles ep ON ep.user_id = u.id
                 WHERE ep.employee_code = ? AND u.role = 'employee' AND u.active = 1
                 LIMIT 1",
                [$code]
            );
        }

        if (!$emp && strlen($mobile) >= 10) {
            $last10 = substr($mobile, -10);
            $emp = db_fetch_one(
                "SELECT u.id, u.name, u.phone, ep.employee_code
                 FROM users u
                 JOIN employee_profiles ep ON ep.user_id = u.id
                 WHERE u.role = 'employee' AND u.active = 1
                   AND REPLACE(REPLACE(REPLACE(u.phone, ' ', ''), '-', ''), '+', '') LIKE ?
                 LIMIT 1",
                ['%' . $last10]
            );
        }

        if (!$emp) {
            Envelope::fail('EMPLOYEE_NOT_FOUND', 'No matching employee for that code or phone', 403);
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate(Wire::TS, time() + AppReleaseAccess::TOKEN_TTL);
        global $pdo;
        $pdo->beginTransaction();
        try {
            $logId = Uuid::v4();
            db_execute(
                "INSERT INTO app_download_logs
                 (id, release_id, employee_id, employee_code, mobile, version_name, ip, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $logId,
                    $row['id'],
                    $emp['id'],
                    $emp['employee_code'] ?? $code,
                    $mobile !== '' ? $mobile : preg_replace('/\D+/', '', (string) ($emp['phone'] ?? '')),
                    $row['version_name'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    Wire::now(),
                ]
            );
            db_execute('INSERT INTO app_download_tokens (token_hash, release_id, employee_id, log_id, session_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [hash('sha256', $token), $row['id'], $emp['id'], $logId, hash('sha256', session_id()), $expiresAt, Wire::now()]);
            db_execute('DELETE FROM app_download_tokens WHERE expires_at <= ?', [Wire::now()]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Failed to log app download: ' . $e->getMessage());
            Envelope::fail('DOWNLOAD_REQUEST_FAILED', 'Could not prepare your download. Please try again.', 500);
        }

        Envelope::ok([
            'downloadUrl'  => base_url('api/v1/app-releases/' . $row['id'] . '/download?token=' . $token),
            'versionName'  => $row['version_name'],
            'expiresAt' => $expiresAt,
        ]);
    }

    public function adminIndex(): never
    {
        $this->requireAdmin();

        $limit = Cursor::limit((string) ($this->query('limit') ?? $this->query('pageSize', '20')));
        $cursorStr = $this->query('cursor', '');

        $conditions = ["1=1"];
        $params = [];

        $q = trim((string) $this->query('q', ''));
        if ($q !== '') {
            $conditions[] = "(version_name LIKE ? OR git_tag LIKE ? OR release_notes LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $status = $this->query('status', '');
        if ($status !== '' && in_array($status, self::VALID_STATUSES, true)) {
            $conditions[] = "status = ?";
            $params[] = $status;
        }

        $platform = $this->query('platform', '');
        if ($platform !== '' && in_array($platform, self::VALID_PLATFORMS, true)) {
            $conditions[] = "platform = ?";
            $params[] = $platform;
        }

        $buildSource = $this->query('build_source', '');
        if ($buildSource !== '' && in_array($buildSource, self::VALID_SOURCES, true)) {
            $conditions[] = "build_source = ?";
            $params[] = $buildSource;
        }

        $countWhere = implode(' AND ', $conditions);
        $countParams = $params;
        if ($limit === 0) {
            $total = db_fetch_one("SELECT COUNT(*) AS c FROM app_releases WHERE $countWhere", $countParams);
            Envelope::ok([], ['total' => (int) $total['c'], 'nextCursor' => null]);
        }

        $cursor = null;
        if ($cursorStr !== '') {
            $cursor = Cursor::decode($cursorStr);
            if ($cursor) {
                $conditions[] = "(created_at < ? OR (created_at = ? AND id < ?))";
                $params[] = $cursor['value'];
                $params[] = $cursor['value'];
                $params[] = $cursor['id'] ?? '';
            }
        }

        $whereClause = implode(' AND ', $conditions);
        
        $page = max(1, (int) $this->query('page', '1'));
        $sort = (string) $this->query('sort', 'created_at');
        if (!in_array($sort, ['created_at', 'version_name', 'version_code', 'status'], true)) $sort = 'created_at';
        $direction = strtolower((string) $this->query('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $order = $cursorStr !== '' ? 'created_at DESC, id DESC' : "$sort $direction, id $direction";
        $sql = "SELECT * FROM app_releases WHERE $whereClause ORDER BY $order LIMIT ?";
        $params[] = $limit + 1;
        if ($cursorStr === '') { $sql .= ' OFFSET ?'; $params[] = ($page - 1) * $limit; }

        $rows = db_fetch_all($sql, $params);

        $nextCursor = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $last = end($rows);
            $nextCursor = Cursor::encode(['value' => $last['created_at'], 'id' => $last['id']]);
        }

        $totalRow = db_fetch_one("SELECT COUNT(*) as c FROM app_releases WHERE $countWhere", $countParams);
        $total = $totalRow['c'] ?? 0;

        Envelope::ok(array_map([$this, 'present'], $rows), [
            'total' => (int) $total,
            'nextCursor' => $sort === 'created_at' && $direction === 'DESC' ? $nextCursor : null,
            'page' => $page, 'pageSize' => $limit,
        ]);
    }

    public function adminStore(): never
    {
        $this->requireAdmin();

        $versionName = $_POST['version_name'] ?? '';
        $versionCode = isset($_POST['version_code']) ? (int) $_POST['version_code'] : 0;
        $releaseNotes = $_POST['release_notes'] ?? null;
        $platform = $_POST['platform'] ?? 'android';
        $channel = $_POST['channel'] ?? 'production';
        $minAndroidSdk = isset($_POST['min_android_sdk']) && $_POST['min_android_sdk'] !== '' ? (int) $_POST['min_android_sdk'] : null;
        if ($minAndroidSdk !== null && ($minAndroidSdk < 1 || $minAndroidSdk > 999)) Envelope::invalid('Enter a valid minimum Android API level.');

        if ($versionName === '' || $versionCode <= 0) {
            Envelope::invalid('Invalid version details');
        }
        if (!in_array($platform, self::VALID_PLATFORMS, true)) {
            Envelope::invalid('Invalid platform');
        }
        if (!in_array($channel, self::VALID_CHANNELS, true)) {
            Envelope::invalid('Invalid channel');
        }

        [$file, $mime] = $this->validateApkFile();
        
        $filePath = $this->storeApkFile($file, $versionName, $versionCode);
        $checksum = $this->computeChecksum($filePath);

        $id = Uuid::v4();
        $now = Wire::now();

        db_execute(
            "INSERT INTO app_releases (
                id, platform, version_name, version_code, file_path, file_name, file_size, 
                checksum_sha256, mime, status, channel, release_notes, min_android_sdk, 
                build_source, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $id, $platform, $versionName, $versionCode, $filePath, $file['name'], $file['size'],
                $checksum, $mime, 'draft', $channel, $releaseNotes, $minAndroidSdk,
                'admin_upload', $now, $now
            ]
        );

        $row = $this->findRelease($id);
        Envelope::created($this->present($row));
    }

    public function adminShow(): never
    {
        $this->requireAdmin();
        $id = $this->param('id');
        $row = $this->findRelease($id);
        Envelope::ok($this->present($row));
    }

    public function adminUpdate(): never
    {
        $this->requireAdmin();
        $id = $this->param('id');
        $row = $this->findRelease($id);

        $updates = [];
        $params = [];

        $body = ApiRequest::body();

        if (array_key_exists('release_notes', $body)) {
            $updates[] = "release_notes = ?";
            $params[] = $body['release_notes'];
        }

        if (array_key_exists('channel', $body)) {
            $channel = $body['channel'];
            if (!in_array($channel, self::VALID_CHANNELS, true)) {
                Envelope::invalid('Invalid channel');
            }
            $updates[] = "channel = ?";
            $params[] = $channel;
        }

        if (array_key_exists('min_android_sdk', $body)) {
            if ($body['min_android_sdk'] !== null && ((int) $body['min_android_sdk'] < 1 || (int) $body['min_android_sdk'] > 999)) Envelope::invalid('Enter a valid minimum Android API level.');
            $updates[] = "min_android_sdk = ?";
            $params[] = $body['min_android_sdk'] === null ? null : (int) $body['min_android_sdk'];
        }

        if (array_key_exists('status', $body)) {
            $status = $body['status'];
            if (!in_array($status, self::VALID_STATUSES, true)) {
                Envelope::invalid('Invalid status');
            }
            
            if ($status !== $row['status']) {
                if (in_array($status, ['published', 'archived'], true) && !AppReleaseAccess::filePath($row)) {
                    Envelope::conflict('FILE_MISSING', 'Cannot change status because file does not exist on disk.');
                }
                
                if ($status === 'draft' || ($row['status'] === 'draft' && $status !== 'published')) {
                    Envelope::invalid('Drafts must be published before entering release history.');
                }
                $updates[] = "status = ?";
                $params[] = $status;

                if ($status === 'published') {
                    $updates[] = "published_at = COALESCE(published_at, ?)";
                    $params[] = Wire::now();
                    $updates[] = "published_by = COALESCE(published_by, ?)";
                    $params[] = Ctx::id();
                }
            }
        }

        if (empty($updates)) {
            Envelope::ok($this->present($row));
        }

        $updates[] = "updated_at = ?";
        $params[] = Wire::now();

        $params[] = $id;

        $sql = "UPDATE app_releases SET " . implode(', ', $updates) . " WHERE id = ?";
        global $pdo;
        $pdo->beginTransaction();
        try {
            if (($body['status'] ?? '') === 'published' && $row['status'] !== 'published') {
                $this->archivePreviousPublished($row['platform']);
            }
            db_execute($sql, $params);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $updatedRow = $this->findRelease($id);
        Envelope::ok($this->present($updatedRow));
    }

    public function adminDelete(): never
    {
        $this->requireAdmin();
        $id = $this->param('id');
        $row = $this->findRelease($id);

        if ($row['status'] !== 'draft') {
            Envelope::forbidden('ONLY_DRAFTS_CAN_BE_DELETED', 'Only releases in draft status can be deleted');
        }

        $absPath = __DIR__ . '/../../' . $row['file_path'];
        if (file_exists($absPath) && !is_dir($absPath)) {
            unlink($absPath);
        }

        db_execute("DELETE FROM app_releases WHERE id = ?", [$id]);

        Envelope::noContent();
    }

    public function adminDownload(): never
    {
        $this->requireAdmin();
        $id = $this->param('id');
        $row = $this->findRelease($id);
        
        $this->serveFile($row);
    }

    public function deploy(): never
    {
        $deployKey = env('DEPLOY_API_KEY', '');
        $providedKey = $_SERVER['HTTP_X_DEPLOY_KEY'] ?? '';

        if ($deployKey === '' || !hash_equals($deployKey, $providedKey)) {
            Envelope::fail('DEPLOY_KEY_INVALID', 'Invalid or missing deploy key', 401);
        }

        $versionName = $_POST['version_name'] ?? '';
        $versionCode = isset($_POST['version_code']) ? (int) $_POST['version_code'] : 0;
        $platform = $_POST['platform'] ?? 'android';
        $releaseNotes = $_POST['release_notes'] ?? null;
        $gitTag = $_POST['git_tag'] ?? null;
        $gitSha = $_POST['git_sha'] ?? null;
        $clientChecksum = $_POST['checksum_sha256'] ?? null;

        if ($versionName === '' || $versionCode <= 0) {
            Envelope::invalid('Invalid version details');
        }
        if (!in_array($platform, self::VALID_PLATFORMS, true)) {
            Envelope::invalid('Invalid platform');
        }

        [$file, $mime] = $this->validateApkFile();
        
        $filePath = $this->storeApkFile($file, $versionName, $versionCode);
        $serverChecksum = $this->computeChecksum($filePath);

        if ($clientChecksum !== null && $clientChecksum !== $serverChecksum) {
            // Warn if mismatch, but don't reject as per spec
            error_log("Deploy warning: Checksum mismatch. Client: $clientChecksum, Server: $serverChecksum");
        }

        $id = Uuid::v4();
        $now = Wire::now();

        db_execute(
            "INSERT INTO app_releases (
                id, platform, version_name, version_code, file_path, file_name, file_size, 
                checksum_sha256, mime, status, channel, release_notes, git_tag, git_sha, 
                build_source, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $id, $platform, $versionName, $versionCode, $filePath, $file['name'], $file['size'],
                $serverChecksum, $mime, 'draft', 'production', $releaseNotes, $gitTag, $gitSha,
                'github_actions', $now, $now
            ]
        );

        Envelope::created([
            'id' => $id,
            'version_name' => $versionName,
            'status' => 'draft'
        ]);
    }

    private function requireFile(array $row): string
    {
        $path = AppReleaseAccess::filePath($row);
        if (!$path) Envelope::notFound('FILE_MISSING', 'This APK is currently unavailable. Please contact your administrator.');
        return $path;
    }

    private function serveFile(array $row): never
    {
        $filePath = $this->requireFile($row);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($row['file_name']));
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($filePath);
        exit;
    }

    private function validateApkFile(): array
    {
        if (empty($_FILES['file'])) {
            Envelope::fail('NO_FILE', 'No APK file was uploaded', 400);
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Envelope::fail('UPLOAD_ERROR', 'File upload error code: ' . $file['error'], 400);
        }

        $maxBytes = (int) env('APP_RELEASE_MAX_BYTES', 104857600);
        if ($file['size'] > $maxBytes) {
            Envelope::fail('FILE_TOO_LARGE', 'APK exceeds maximum allowed size of ' . round($maxBytes / 1048576) . ' MB', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'apk') {
            Envelope::fail('INVALID_EXTENSION', 'Only .apk files are accepted', 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            // Some systems report APK as application/zip — allow it with extension check
            if ($mime === 'application/zip' && $ext === 'apk') {
                $mime = 'application/vnd.android.package-archive';
            } else {
                Envelope::fail('INVALID_MIME', 'File MIME type is not a valid APK: ' . $mime, 400);
            }
        }

        return [$file, $mime];
    }

    private function storeApkFile(array $file, string $versionName, int $versionCode): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,31}$/', $versionName) || $versionCode <= 0) {
            Envelope::invalid('Use a valid version name (up to 32 letters, digits, dots or hyphens) and positive version code.');
        }
        if (db_fetch_one('SELECT id FROM app_releases WHERE platform = ? AND version_code = ?', ['android', $versionCode])) {
            Envelope::conflict('VERSION_EXISTS', 'This Android version code already exists. Use a new version code.');
        }
        $storageBase = env('APP_RELEASE_STORAGE', 'storage/apk');
        $dir = __DIR__ . '/../../' . $storageBase . '/' . $versionName;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'app-v' . $versionCode . '.apk';
        $targetPath = $dir . '/' . $filename;
        if (file_exists($targetPath)) Envelope::conflict('FILE_EXISTS', 'An APK already exists for this version. Use a new version code.');

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Envelope::fail('STORE_FAILED', 'Failed to save APK file to disk', 500);
        }

        return $storageBase . '/' . $versionName . '/' . $filename;
    }

    private function computeChecksum(string $relativePath): string
    {
        $absPath = __DIR__ . '/../../' . $relativePath;
        return hash_file('sha256', $absPath);
    }

    private function archivePreviousPublished(string $platform): void
    {
        db_execute(
            "UPDATE app_releases SET status = 'archived', updated_at = ? WHERE platform = ? AND status = 'published'",
            [Wire::now(), $platform],
        );
    }

    private function present(array $row): array
    {
        $base = rtrim(env('APP_URL', ''), '/');
        return [
            'id'             => $row['id'],
            'platform'       => $row['platform'],
            'versionName'    => $row['version_name'],
            'versionCode'    => (int) $row['version_code'],
            'fileName'       => $row['file_name'],
            'fileSize'       => (int) $row['file_size'],
            'checksumSha256' => $row['checksum_sha256'],
            'mime'           => $row['mime'],
            'status'         => $row['status'],
            'channel'        => $row['channel'],
            'releaseNotes'   => $row['release_notes'],
            'minAndroidSdk'  => $row['min_android_sdk'] !== null ? (int) $row['min_android_sdk'] : null,
            'gitTag'         => $row['git_tag'],
            'gitSha'         => $row['git_sha'],
            'buildSource'    => $row['build_source'],
            'downloadUrl'    => $base . '/api/v1/app-releases/' . $row['id'] . '/download',
            'publishedAt'    => $row['published_at'],
            'publishedBy'    => $row['published_by'],
            'createdAt'      => $row['created_at'],
            'updatedAt'      => $row['updated_at'],
        ];
    }

    private function findRelease(string $id): array
    {
        if (!Uuid::isValid($id)) {
            Envelope::notFound('RELEASE_NOT_FOUND', 'Invalid release ID');
        }

        $row = db_fetch_one('SELECT * FROM app_releases WHERE id = ?', [$id]);

        if (!$row) {
            Envelope::notFound('RELEASE_NOT_FOUND', 'Release not found');
        }

        return $row;
    }
}
