<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class SettingsController extends BaseController
{
    /* ---------------------------------------------------------
     * Internals — settings table
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
     * Internals — logo filesystem helpers
     * Path: modules/DMS/storage/uploads/brand/logo/org_<id>/logo.ext
     * ------------------------------------------------------- */

    private function logoBaseDir(): string
    {
        // modules/DMS
        $moduleRoot = dirname(__DIR__, 2);
        return $moduleRoot . '/storage/uploads/brand/logo';
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
     * Data URL for previews (settings page, landing page)
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
     * Handle uploaded logo and place under:
     * modules/DMS/storage/uploads/brand/logo/org_<id>/logo.ext
     *
     * Returns relative path "org_<id>/logo.ext" for storage in dms_settings.
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

        // you can store this relative key in dms_settings if needed
        return $orgKey . '/logo.' . $ext;
    }

    /* ---------------------------------------------------------
     * UI
     * ------------------------------------------------------- */

    /** GET /apps/dms/settings */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $org   = (array)($ctx['org'] ?? ($_SESSION['tenant_org'] ?? []));
        $base  = $this->moduleBase($ctx);

        $kv = $this->allSettings($pdo, $orgId);

        $orgName = (string)($org['name']    ?? '');
        $orgAddr = (string)($org['address'] ?? '');
        $orgTel  = (string)($org['phone']   ?? '');
        $orgMail = (string)($org['email']   ?? '');

        $data = [
            // data: URL for previews (or empty)
            'invoice_logo_url' => $this->logoDataUrl($orgId) ?: '',

            // Identity (fallback to cp_organizations)
            'biz_name'    => (string)($kv['biz_name']    ?? $orgName),
            'biz_address' => (string)($kv['biz_address'] ?? $orgAddr),
            'biz_phone'   => (string)($kv['biz_phone']   ?? $orgTel),
            'biz_email'   => (string)($kv['biz_email']   ?? $orgMail),

            // Footers + prefixes
            'invoice_footer' => (string)($kv['invoice_footer'] ?? 'Thank you for your business.'),
            'order_footer'   => (string)($kv['order_footer']   ?? 'Thank you for your order.'),
            'inv_prefix'     => (string)($kv['inv_prefix']     ?? ('INV-' . date('Y') . '-')),
            'ord_prefix'     => (string)($kv['ord_prefix']     ?? ('ORD-' . date('Y') . '-')),
            'print_notes'    => (string)($kv['print_notes']    ?? ''),
        ];

        $this->view('settings/index', [
            'title'     => 'DMS Branding & Identity',
            'active'    => 'settings',
            'subactive' => 'settings.index',
            'settings'  => $data,
            'org'       => $org,
            'base'      => $base,
        ], $ctx);
    }

    /** POST /apps/dms/settings */
    public function update(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        // 1) text fields
        $fields = [
            'biz_name',
            'biz_address',
            'biz_phone',
            'biz_email',
            'invoice_footer',
            'order_footer',
            'inv_prefix',
            'ord_prefix',
            'print_notes',
        ];

        foreach ($fields as $f) {
            $val = isset($_POST[$f]) ? trim((string)$_POST[$f]) : '';
            $this->put($pdo, $orgId, $f, $val);
        }

        // 2) optional logo upload
        if (!empty($_FILES['invoice_logo']) && is_array($_FILES['invoice_logo'])) {
            try {
                $rel = $this->saveUploadedLogo($orgId, $_FILES['invoice_logo']);
                if ($rel !== null) {
                    $this->put($pdo, $orgId, 'invoice_logo_path', $rel);
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
        $_SESSION['flash_ok'] = 'Settings updated.';

        $this->redirect($this->moduleBase($ctx) . '/settings');
    }
}