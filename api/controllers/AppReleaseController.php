<?php

require_once __DIR__ . '/../support/V1Controller.php';

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

    private const VALID_STATUSES = ['draft', 'published', 'archived'];
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

        Envelope::ok($this->present($row));
    }

    public function index(): never
    {
        $limit = Cursor::limit((string) $this->query('limit', '20'));
        $cursorStr = $this->query('cursor', '');

        $sql = "SELECT * FROM app_releases WHERE status IN ('published', 'archived')";
        $params = [];

        $cursor = null;
        if ($cursorStr !== '') {
            $cursor = Cursor::decode($cursorStr);
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

        $totalRow = db_fetch_one("SELECT COUNT(*) as c FROM app_releases WHERE status IN ('published', 'archived')", []);
        $total = $totalRow['c'] ?? 0;

        Envelope::ok(array_map([$this, 'present'], $rows), [
            'total' => (int) $total,
            'nextCursor' => $nextCursor
        ]);
    }

    public function download(): never
    {
        $id = $this->param('id');
        $row = $this->findRelease($id);

        if ($row['status'] !== 'published') {
            Envelope::notFound('RELEASE_NOT_FOUND', 'Release not found or not available');
        }

        $this->serveFile($row);
    }

    public function requestDownload(): never
    {
        $id = $this->param('id');
        $release = $this->findRelease($id);

        if ($release['status'] !== 'published') {
            Envelope::notFound('RELEASE_NOT_PUBLISHED', 'Release is not published or unavailable');
        }

        $body = ApiRequest::body();
        $employeeCode = trim((string) ($body['employee_code'] ?? ''));
        $mobile = trim((string) ($body['mobile'] ?? ''));

        if ($employeeCode === '' || $mobile === '') {
            Envelope::invalid('Employee ID and mobile number are required');
        }

        $inputDigits = preg_replace('/\D/', '', $mobile);
        if (strlen($inputDigits) < 10) {
            Envelope::invalid('Please enter a valid 10-digit mobile number');
        }

        // Look up employee in users + employee_profiles
        $employee = db_fetch_one(
            "SELECT u.id, u.name, u.phone, u.active, ep.employee_code
             FROM users u
             JOIN employee_profiles ep ON ep.user_id = u.id
             WHERE UPPER(ep.employee_code) = UPPER(?) AND u.active = 1",
            [$employeeCode]
        );

        if (!$employee) {
            Envelope::fail('EMPLOYEE_NOT_FOUND', 'Employee ID not found or account is inactive', 403);
        }

        $empDigits = preg_replace('/\D/', '', (string) $employee['phone']);
        if (substr($inputDigits, -10) !== substr($empDigits, -10)) {
            Envelope::fail('MOBILE_MISMATCH', 'Mobile number does not match registered employee records', 403);
        }

        // Audit log the verified download
        $logId = Uuid::v4();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
        $now = Wire::now();

        try {
            db_execute(
                "INSERT INTO app_download_logs (id, release_id, employee_code, mobile, ip, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$logId, $id, $employee['employee_code'], $mobile, $ip, $ua, $now]
            );
        } catch (\Throwable $e) {
            error_log('Failed to log app download: ' . $e->getMessage());
        }

        $base = rtrim(env('APP_URL', ''), '/');
        $downloadUrl = $base . '/api/v1/app-releases/' . $id . '/download';

        Envelope::ok([
            'verified'      => true,
            'employee_name' => $employee['name'],
            'download_url'  => $downloadUrl,
        ]);
    }

    public function adminIndex(): never
    {
        $this->requireAdmin();

        $limit = Cursor::limit((string) $this->query('limit', '20'));
        $cursorStr = $this->query('cursor', '');

        $conditions = ["1=1"];
        $params = [];

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

        $cursor = null;
        if ($cursorStr !== '') {
            $cursor = Cursor::decode($cursorStr);
            if ($cursor) {
                $conditions[] = "created_at < ?";
                $params[] = $cursor['value'];
            }
        }

        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT * FROM app_releases WHERE $whereClause ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit + 1;

        $rows = db_fetch_all($sql, $params);

        $nextCursor = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $last = end($rows);
            $nextCursor = Cursor::encode(['value' => $last['created_at']]);
        }

        $countParams = array_slice($params, 0, -1);
        $totalRow = db_fetch_one("SELECT COUNT(*) as c FROM app_releases WHERE $whereClause", $countParams);
        $total = $totalRow['c'] ?? 0;

        Envelope::ok(array_map([$this, 'present'], $rows), [
            'total' => (int) $total,
            'nextCursor' => $nextCursor
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
        $minAndroidSdk = isset($_POST['min_android_sdk']) ? (int) $_POST['min_android_sdk'] : null;

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
            $updates[] = "min_android_sdk = ?";
            $params[] = $body['min_android_sdk'] === null ? null : (int) $body['min_android_sdk'];
        }

        if (array_key_exists('status', $body)) {
            $status = $body['status'];
            if (!in_array($status, self::VALID_STATUSES, true)) {
                Envelope::invalid('Invalid status');
            }
            
            if ($status !== $row['status']) {
                $absPath = __DIR__ . '/../../' . $row['file_path'];
                if (!file_exists($absPath)) {
                    Envelope::conflict('FILE_MISSING', 'Cannot change status because file does not exist on disk.');
                }
                
                $updates[] = "status = ?";
                $params[] = $status;

                if ($status === 'published') {
                    $this->archivePreviousPublished($row['platform']);
                    $updates[] = "published_at = ?";
                    $params[] = Wire::now();
                    $updates[] = "published_by = ?";
                    $params[] = Ctx::id();
                } else if ($status === 'archived' && $row['status'] === 'published') {
                    $updates[] = "published_at = NULL";
                    $updates[] = "published_by = NULL";
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
        db_execute($sql, $params);

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

    private function serveFile(array $row): never
    {
        $filePath = __DIR__ . '/../../' . $row['file_path'];
        if (!file_exists($filePath)) {
            Envelope::fail('FILE_MISSING', 'Release file not found on server', 500);
        }

        header('Content-Type: ' . $row['mime']);
        header('Content-Disposition: attachment; filename="' . $row['file_name'] . '"');
        header('Content-Length: ' . $row['file_size']);
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
        $storageBase = env('APP_RELEASE_STORAGE', 'storage/apk');
        $dir = __DIR__ . '/../../' . $storageBase . '/' . $versionName;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'app-v' . $versionCode . '.apk';
        $targetPath = $dir . '/' . $filename;

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
            "UPDATE app_releases SET status = 'archived', published_at = NULL, published_by = NULL, updated_at = ? WHERE platform = ? AND status = 'published'",
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
