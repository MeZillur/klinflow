<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class SettingsController extends BaseController
{
    /* ---------------------------------------------------------
     * Internals — generic settings table
     * ------------------------------------------------------- */

    private function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dms_settings (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              setting_key VARCHAR(120) NOT NULL,
              value LONGTEXT NULL,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_org_key (org_id, setting_key),
              KEY idx_org (org_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private function allSettings(PDO $pdo, int $orgId): array
    {
        try {
            $this->ensureTable($pdo);
            $st = $pdo->prepare("SELECT setting_key, value FROM dms_settings WHERE org_id=?");
            $st->execute([$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            return array_map(static fn($v) => is_string($v) ? $v : '', $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function put(PDO $pdo, int $orgId, string $key, string $val): void
    {
        $this->ensureTable($pdo);
        $up = $pdo->prepare("
            INSERT INTO dms_settings (org_id, setting_key, value)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=NOW()
        ");
        $up->execute([$orgId, $key, $val]);
    }

    /* ---------------------------------------------------------
     * Logo filesystem helpers
     * Path: modules/DMS/storage/uploads/logo/org_<id>/logo.ext
     * ------------------------------------------------------- */

    private function logoBaseDir(): string
    {
        // modules/DMS
        $moduleRoot = dirname(__DIR__, 2);
        return $moduleRoot . '/storage/uploads/logo';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function logoFsPath(int $orgId): ?string
    {
        $base   = $this->logoBaseDir();
        $orgKey = 'org_' . $orgId;
        $dir    = $base . '/' . $orgKey;

        $this->ensureDir($dir);

        foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
            $p = $dir . '/logo.' . $ext;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Data URL for previews (settings page, landing page).
     * Returns null if no logo stored yet.
     */
    private function logoDataUrl(int $orgId): ?string
    {
        $fs = $this->logoFsPath($orgId);
        if (!$fs) {
            return null;
        }

        $ext  = strtolower(pathinfo($fs, PATHINFO_EXTENSION));
        $mime = 'image/png';
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } elseif ($ext === 'svg') {
            $mime = 'image/svg+xml';
        }

        $raw = @file_get_contents($fs);
        if ($raw === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    /**
     * Save uploaded logo into:
     *   modules/DMS/storage/uploads/logo/org_<id>/logo.ext
     *
     * Returns relative key "org_<id>/logo.ext" (for future use if needed).
     */
    private function saveUploadedLogo(int $orgId, array $file): ?string
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }

        $name = (string)($file['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
            throw new \RuntimeException('Logo must be PNG, JPG, WEBP, or SVG.');
        }

        $base   = $this->logoBaseDir();
        $orgKey = 'org_' . $orgId;
        $dir    = $base . '/' . $orgKey;
        $this->ensureDir($dir);

        // Cleanup old logo.* files so we only keep one
        foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $other) {
            $old = $dir . '/logo.' . $other;
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $dest = $dir . '/logo.' . $ext;
        if (!@move_uploaded_file($tmp, $dest)) {
            throw new \RuntimeException('Failed to save uploaded logo.');
        }

        @chmod($dest, 0644);

        return $orgKey . '/logo.' . $ext;
    }

    /* ---------------------------------------------------------
     * UI — POS-style simple branding page
     * ------------------------------------------------------- */

    /** GET /apps/dms/settings */
    public function index(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $org   = (array)($c['org'] ?? ($_SESSION['tenant_org'] ?? []));
        $base  = $this->moduleBase($c);

        $kv = $this->allSettings($pdo, $orgId);

        $orgName  = trim((string)($org['name']    ?? ''));
        $orgAddr  = trim((string)($org['address'] ?? ''));
        $orgPhone = trim((string)($org['phone']   ?? ''));
        $orgEmail = trim((string)($org['email']   ?? ''));
        $orgWeb   = trim((string)($org['website'] ?? ''));

        $branding = [
            'business_name' => (string)($kv['business_name'] ?? $kv['biz_name']    ?? ($orgName ?: 'Your Business Name')),
            'address'       => (string)($kv['address']       ?? $kv['biz_address'] ?? $orgAddr),
            'phone'         => (string)($kv['phone']         ?? $kv['biz_phone']   ?? $orgPhone),
            'email'         => (string)($kv['email']         ?? $kv['biz_email']   ?? $orgEmail),
            'website'       => (string)($kv['website']       ?? $orgWeb),
            'logo_path'     => $this->logoDataUrl($orgId) ?: '',
        ];

        $this->view('settings/index', [
            'title'    => 'DMS Branding',
            'branding' => $branding,
            'base'     => $base,
            'org'      => $org,
        ], $c);
    }

    /** POST /apps/dms/settings */
    public function update(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $org   = (array)($c['org'] ?? ($_SESSION['tenant_org'] ?? []));

        $orgName  = trim((string)($org['name']    ?? ''));
        $orgAddr  = trim((string)($org['address'] ?? ''));
        $orgPhone = trim((string)($org['phone']   ?? ''));
        $orgEmail = trim((string)($org['email']   ?? ''));
        $orgWeb   = trim((string)($org['website'] ?? ''));

        // Identity fields (POS-style)
        $business = trim((string)($_POST['business_name'] ?? ''));
        if ($business === '') {
            $business = $orgName ?: 'Your Business Name';
        }

        $address = trim((string)($_POST['address'] ?? ''));
        if ($address === '') {
            $address = $orgAddr;
        }

        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($phone === '') {
            $phone = $orgPhone;
        }

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '') {
            $email = $orgEmail;
        }

        $website = trim((string)($_POST['website'] ?? ''));
        if ($website === '') {
            $website = $orgWeb;
        }

        // Save to dms_settings (both new & old keys so nothing breaks)
        $this->put($pdo, $orgId, 'business_name', $business);
        $this->put($pdo, $orgId, 'biz_name',      $business);

        $this->put($pdo, $orgId, 'address',       $address);
        $this->put($pdo, $orgId, 'biz_address',   $address);

        $this->put($pdo, $orgId, 'phone',         $phone);
        $this->put($pdo, $orgId, 'biz_phone',     $phone);

        $this->put($pdo, $orgId, 'email',         $email);
        $this->put($pdo, $orgId, 'biz_email',     $email);

        $this->put($pdo, $orgId, 'website',       $website);

        // Optional logo upload (field name = "logo")
        if (!empty($_FILES['logo']) && is_array($_FILES['logo'])) {
            try {
                $rel = $this->saveUploadedLogo($orgId, $_FILES['logo']);
                if ($rel !== null) {
                    $this->put($pdo, $orgId, 'logo_rel', $rel);
                }
            } catch (\Throwable $e) {
                if (\PHP_SESSION_ACTIVE !== \session_status()) {
                    @\session_start();
                }
                $_SESSION['flash_err'] = 'Logo upload failed: ' . $e->getMessage();
            }
        }

        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['flash_ok'] = 'Branding updated.';

        $this->redirect($this->moduleBase($c) . '/settings');
    }
}