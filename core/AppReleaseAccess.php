<?php

/** Public release metadata and shared download eligibility rules. */
final class AppReleaseAccess
{
    public const PUBLIC_STATUSES = ['published', 'archived'];
    public const TOKEN_TTL = 600;

    public static function isPublic(array $release): bool
    {
        return ($release['platform'] ?? '') === 'android'
            && in_array($release['status'] ?? '', self::PUBLIC_STATUSES, true);
    }

    public static function filePath(array $release, ?string $root = null): ?string
    {
        $root = realpath($root ?? __DIR__ . '/..');
        $relative = (string) ($release['file_path'] ?? '');
        if (!$root || $relative === '') return null;
        $path = realpath($root . '/' . $relative);
        if (!$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) return null;
        return $path;
    }

    public static function androidRequirement(?int $sdk): string
    {
        if (!$sdk) return 'Android';
        $versions = [21 => '5.0', 22 => '5.1', 23 => '6.0', 24 => '7.0', 25 => '7.1',
            26 => '8.0', 27 => '8.1', 28 => '9', 29 => '10', 30 => '11', 31 => '12',
            32 => '12L', 33 => '13', 34 => '14', 35 => '15'];
        return isset($versions[$sdk]) ? 'Android ' . $versions[$sdk] . '+' : 'Android (API ' . $sdk . '+)';
    }

    public static function publicMetadata(array $row): array
    {
        return [
            'id' => $row['id'], 'platform' => $row['platform'],
            'versionName' => $row['version_name'], 'versionCode' => (int) $row['version_code'],
            'fileSize' => (int) $row['file_size'], 'status' => $row['status'], 'channel' => $row['channel'],
            'releaseNotes' => $row['release_notes'] ?? '',
            'minAndroidSdk' => isset($row['min_android_sdk']) ? (int) $row['min_android_sdk'] : null,
            'androidRequirement' => self::androidRequirement(isset($row['min_android_sdk']) ? (int) $row['min_android_sdk'] : null),
            'publishedAt' => $row['published_at'], 'createdAt' => $row['created_at'],
            'available' => self::isPublic($row) && self::filePath($row) !== null,
            'downloadPageUrl' => base_url('download?release=' . urlencode($row['id']) . '#changelog'),
        ];
    }
}
