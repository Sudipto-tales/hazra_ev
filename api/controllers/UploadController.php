<?php
require_once __DIR__ . '/../support/V1Controller.php';

final class UploadController extends V1Controller
{
    private const ALLOWED_MIME_TYPES = [
        'image/webp'    => 'webp',
        'image/jpeg'    => 'jpg',
        'image/jpg'     => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg',
    ];

    private const MAX_FILE_BYTES = 10 * 1024 * 1024; // 10MB limit

    public function upload(): never
    {
        if (empty($_FILES['file'])) {
            // Also check for 'image' key
            if (!empty($_FILES['image'])) {
                $_FILES['file'] = $_FILES['image'];
            } else {
                Envelope::fail('NO_FILE_UPLOADED', 'No file was submitted in request.', 400);
            }
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Envelope::fail('UPLOAD_FAILED', 'File upload error code: ' . $file['error'], 400);
        }

        if ($file['size'] > self::MAX_FILE_BYTES) {
            Envelope::fail('FILE_TOO_LARGE', 'Maximum file size allowed is 10 MB.', 400);
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
            // Fallback check extension if finfo is ambiguous for webp/svg
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $validExts = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg'];
            if (!in_array($ext, $validExts, true)) {
                Envelope::fail('INVALID_FILE_TYPE', 'File format ' . ($mime ?: $ext) . ' is not supported. Upload WebP, JPG, PNG, GIF, or SVG.', 400);
            }
            $extension = $ext === 'jpeg' ? 'jpg' : $ext;
        } else {
            $extension = self::ALLOWED_MIME_TYPES[$mime];
        }

        $targetDir = dirname(__DIR__, 2) . '/assets/uploads/images';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Envelope::fail('SAVE_FAILED', 'Failed to save uploaded file to disk.', 500);
        }

        $relativeUrl = 'assets/uploads/images/' . $filename;

        // Save upload entry in gallery database tables so it appears immediately across admin panel & website
        $id = Uuid::v4();
        $now = Wire::now();
        $title = pathinfo($file['name'], PATHINFO_FILENAME);

        try {
            db_execute(
                "INSERT INTO gallery_items (id, title, image_url, image_path, alt, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$id, $title, $relativeUrl, $relativeUrl, $title, $now, $now]
            );
        } catch (Throwable) {}

        try {
            db_execute(
                "INSERT INTO admin_gallery_items (id, title, image_url, image_path, alt, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$id, $title, $relativeUrl, $relativeUrl, $title, $now, $now]
            );
        } catch (Throwable) {}

        Envelope::ok([
            'id'            => $id,
            'url'           => $relativeUrl,
            'image_url'     => $relativeUrl,
            'path'          => $relativeUrl,
            'filename'      => $filename,
            'title'         => $title,
            'original_name' => $file['name'],
            'size'          => $file['size'],
            'sizeBytes'     => $file['size'],
            'mime'          => $mime,
        ]);
    }
}
