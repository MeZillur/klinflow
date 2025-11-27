<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use DateTimeImmutable;
use Throwable;

final class BiometricController extends BaseController
{
    /**
     * POST /biometric/upload
     *
     * Handles:
     *  - kind = id_front | id_back | face
     *  - file field = image
     *
     * Stores under (filesystem):
     *   modules/hotelflow/Assets/storage/
     *      guest_id/org_{org_id}/{Y}/{m}/...
     *      guest_photo/org_{org_id}/{Y}/{m}/...
     *
     * Returns JSON:
     *   { ok: true, path: "guest_photo/org_9/2025/11/file.jpg", url: "/modules/hotelflow/Assets/storage/guest_photo/..." }
     */
    public function upload(array $ctx): void
    {
        // Always normalize context (slug, org, module_dir, module_base, etc.)
        $c = $this->ctx($ctx);

        /* -------------------------------------------------------------
         * 1) Method + basic request validation
         * ----------------------------------------------------------- */
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->jsonErr('Method not allowed', 405);
            return;
        }

        // Optional: light AJAX check (place-holder if you later want CSRF here)
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            // You can enforce a CSRF header here in future.
        }

        /* -------------------------------------------------------------
         * 2) Resolve organisation context
         * ----------------------------------------------------------- */
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        $sessionOrg = (array)($_SESSION['tenant_org'] ?? []);

        $orgId = (int)($c['org_id'] ?? ($sessionOrg['id'] ?? 0));
        if ($orgId <= 0) {
            $this->jsonErr('Missing org context', 400);
            return;
        }

        /* -------------------------------------------------------------
         * 3) Determine kind (id_front | id_back | face | other)
         * ----------------------------------------------------------- */
        $kind = (string)($_POST['kind'] ?? '');
        if (!\in_array($kind, ['id_front', 'id_back', 'face'], true)) {
            $kind = 'other';
        }

        /* -------------------------------------------------------------
         * 4) Validate uploaded file (size + MIME)
         * ----------------------------------------------------------- */
        if (empty($_FILES['image']) || !isset($_FILES['image']['tmp_name'])) {
            $this->jsonErr('No image uploaded');
            return;
        }

        $file = $_FILES['image'];

        // PHP upload error codes
        if (!empty($file['error']) && $file['error'] !== \UPLOAD_ERR_OK) {
            $msg = match ($file['error']) {
                \UPLOAD_ERR_INI_SIZE,
                \UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
                \UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
                \UPLOAD_ERR_NO_FILE   => 'No file uploaded.',
                default               => 'Upload error (code ' . $file['error'] . ').',
            };
            $this->jsonErr($msg);
            return;
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->jsonErr('Invalid upload temp file');
            return;
        }

        // Size limit: 5 MB
        $maxBytes = 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            $this->jsonErr('File exceeds 5 MB limit');
            return;
        }

        // MIME validation using finfo
        $mime = 'application/octet-stream';
        if (\function_exists('finfo_open')) {
            $f = @\finfo_open(\FILEINFO_MIME_TYPE);
            if ($f) {
                $det = @\finfo_file($f, $file['tmp_name']);
                if (\is_string($det) && $det !== '') {
                    $mime = $det;
                }
                @\finfo_close($f);
            }
        }

        $allowed = [
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
        ];

        $ext = $allowed[$mime] ?? null;
        if ($ext === null) {
            $this->jsonErr('Unsupported file type. Only JPEG and PNG are allowed.');
            return;
        }

        /* -------------------------------------------------------------
         * 5) Resolve storage paths
         * ----------------------------------------------------------- */
        // Module dir should be modules/hotelflow
        $moduleDir = (string)($c['module_dir'] ?? realpath(\dirname(__DIR__, 2)));
        if ($moduleDir === '' || !is_dir($moduleDir)) {
            $this->jsonErr('Invalid module directory');
            return;
        }

        $storageRoot = rtrim($moduleDir, '/') . '/Assets/storage';

        // Map kind → subfolder inside storage root
        $subFolder = ($kind === 'face') ? 'guest_photo' : 'guest_id';

        $now   = new DateTimeImmutable('now');
        $year  = $now->format('Y');
        $month = $now->format('m');

        // Sub-path under Assets/storage: guest_photo/org_{id}/Y/m or guest_id/org_{id}/Y/m
        $subPath = $subFolder . '/org_' . $orgId . '/' . $year . '/' . $month;

        // Full directory on disk
        $destDir = $storageRoot . '/' . $subPath;

        if (!is_dir($destDir) && !mkdir($destDir, 0770, true) && !is_dir($destDir)) {
            $this->jsonErr('Failed to create storage directory');
            return;
        }

        /* -------------------------------------------------------------
         * 6) Generate safe filename + move uploaded file
         * ----------------------------------------------------------- */
        try {
            $random = \bin2hex(\random_bytes(8));
        } catch (Throwable) {
            $random = (string)\mt_rand(100000, 999999);
        }

        $baseName = $kind . '_' . $orgId . '_' . $now->format('Ymd_His') . '_' . $random;
        // Just in case, strip any weird characters
        $baseName = \preg_replace('~[^a-zA-Z0-9_\-]+~', '_', $baseName) ?: ('img_' . $orgId);

        $fileName = $baseName . $ext;
        $destPath = $destDir . '/' . $fileName;

        if (!\move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->jsonErr('Failed to move uploaded file');
            return;
        }

        // Tighten file permissions (best-effort)
        @\chmod($destPath, 0660);

        /* -------------------------------------------------------------
         * 7) Build stored path + public URL
         * ----------------------------------------------------------- */
        // This is what we will store in DB and return as "path"
        // Example: "guest_photo/org_9/2025/11/face_9_2025....jpg"
        $relative = $subPath . '/' . $fileName;

        // Public URL that matches your show helper:
        // /modules/hotelflow/Assets/storage/{relative}
        $url = '/modules/hotelflow/Assets/storage/' . $relative;

        /* -------------------------------------------------------------
         * 8) Final JSON response
         * ----------------------------------------------------------- */
        $this->jsonOk([
            'kind'     => $kind,
            // 👇 JS + controller rely on this key
            'path'     => $relative,
            'url'      => $url,
            'mime'     => $mime,
            'filesize' => (int)$file['size'],
            'org_id'   => $orgId,
        ]);
    }
}