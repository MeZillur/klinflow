<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class SettingsController extends BaseController
{
    /* =======================================================
     * Low-level helpers
     * ===================================================== */

    /** DB table name for POS branding */
    private function brandingTable(PDO $pdo): string
    {
        $tbl = 'pos_branding';

        // Keep schema compatible with your existing table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$tbl}` (
                `org_id`        BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                `business_name` VARCHAR(200)   NOT NULL,
                `address`       TEXT           NULL,
                `phone`         VARCHAR(50)    NULL,
                `email`         VARCHAR(200)   NULL,
                `website`       VARCHAR(200)   NULL,
                `logo_path`     VARCHAR(255)   NULL,
                `updated_at`    TIMESTAMP      NOT NULL
                               DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_0900_ai_ci;
        ");

        return $tbl;
    }

    /** FS base where identity JSON lives: modules/POS/Assets/settings/org_{id}/identity.json */
    private function identityBaseDir(): string
    {
        return \dirname(__DIR__, 2) . '/Assets/settings';
    }

    /** FS base where logos live: modules/POS/Assets/Brand/logo/{org_id}/logo.png */
    private function logoBaseDir(): string
    {
        return \dirname(__DIR__, 2) . '/Assets/Brand/logo';
    }

    private function ensureDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0775, true);
        }
    }

    /* =======================================================
     * Branding load/save (DB + session + FS identity)
     * ===================================================== */

    /**
     * Load branding row for org and merge with global org defaults.
     * Also safe to call even if no row exists (falls back to org[]).
     */
    private function getBranding(PDO $pdo, int $orgId, array $ctxOrg = []): array
    {
        $tbl = $this->brandingTable($pdo);

        $st = $pdo->prepare("SELECT * FROM {$tbl} WHERE org_id = ? LIMIT 1");
        $st->execute([$orgId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $branding = [
            'business_name' => $row['business_name'] ?? ($ctxOrg['name']    ?? 'Your Business Name'),
            'address'       => $row['address']       ?? ($ctxOrg['address'] ?? ''),
            'phone'         => $row['phone']         ?? ($ctxOrg['phone']   ?? ''),
            'email'         => $row['email']         ?? ($ctxOrg['email']   ?? ''),
            'website'       => $row['website']       ?? ($ctxOrg['website'] ?? ''),
            'logo_path'     => $row['logo_path']     ?? '',
        ];

        // Mirror into session so landing/invoices can reuse without extra DB hit
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['pos_branding'] = $branding;

        return $branding;
    }

    /**
     * Persist branding row in DB and refresh session cache.
     */
    private function saveBrandingRow(PDO $pdo, int $orgId, array $data): void
    {
        $tbl = $this->brandingTable($pdo);

        $sql = "
            INSERT INTO {$tbl}
                (org_id, business_name, address, phone, email, website, logo_path)
            VALUES
                (:org_id, :business_name, :address, :phone, :email, :website, :logo_path)
            ON DUPLICATE KEY UPDATE
                business_name = VALUES(business_name),
                address       = VALUES(address),
                phone         = VALUES(phone),
                email         = VALUES(email),
                website       = VALUES(website),
                logo_path     = VALUES(logo_path)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':org_id'        => $orgId,
            ':business_name' => $data['business_name'],
            ':address'       => $data['address'],
            ':phone'         => $data['phone'],
            ':email'         => $data['email'],
            ':website'       => $data['website'],
            ':logo_path'     => $data['logo_path'],
        ]);

        // Refresh session cache
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['pos_branding'] = $data;
    }

    /**
     * Write a BizFlow-style identity.json file so landing / PDFs
     * can behave exactly like BizFlow.
     */
    private function saveIdentityJson(int $orgId, array $branding, array $ctxOrg = []): void
    {
        $baseDir = $this->identityBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        // Map to BizFlow-style keys
        $data = [
            'name'    => $branding['business_name'] ?: (string)($ctxOrg['name'] ?? ''),
            'address' => $branding['address']       ?: (string)($ctxOrg['address'] ?? ''),
            'phone'   => $branding['phone']         ?: (string)($ctxOrg['phone'] ?? ''),
            'email'   => $branding['email']         ?: (string)($ctxOrg['email'] ?? ''),
        ];

        $file = $dir . '/identity.json';
        @\file_put_contents($file, \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @\chmod($file, 0660);
    }

    /* =======================================================
     * GET /apps/pos/settings
     * ===================================================== */

    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $org   = (array)($c['org'] ?? []);
            $orgId = $this->requireOrg();

            $branding = $this->getBranding($pdo, $orgId, $org);

            // Provide BizFlow-style identity block for views that expect it
            $identity = [
                'name'    => $branding['business_name'],
                'address' => $branding['address'],
                'phone'   => $branding['phone'],
                'email'   => $branding['email'],
                'website' => $branding['website'],
            ];

            $this->view($c['module_dir'] . "/Views/settings/index.php", [
                'title'       => 'POS Branding',
                'branding'    => $branding,
                'identity'    => $identity,           // <- BizFlow-style
                'org'         => $org,
                'org_id'      => $orgId,
                'module_base' => $c['module_base'],
                'base'        => $c['module_base'],   // old param kept for safety
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops("POS settings index failed", $e);
        }
    }

    /* =======================================================
     * POST /apps/pos/settings
     * ===================================================== */

    public function save(array $ctx = []): void
    {
        try {
            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $org   = (array)($c['org'] ?? []);
            $orgId = $this->requireOrg();

            $brandingOld = $this->getBranding($pdo, $orgId, $org);

            // ---------- Grab fields (support BOTH POS + BizFlow names) ----------
            $business = \trim(
                (string)($_POST['business_name'] ?? $_POST['org_name'] ?? '')
            );
            if ($business === '') {
                $business = $brandingOld['business_name'] ?: 'Your Business Name';
            }

            $address = \trim((string)($_POST['address'] ?? $brandingOld['address'] ?? ''));
            $phone   = \trim((string)($_POST['phone']   ?? $brandingOld['phone']   ?? ''));
            $email   = \trim((string)($_POST['email']   ?? $brandingOld['email']   ?? ''));
            $website = \trim((string)($_POST['website'] ?? $brandingOld['website'] ?? ''));

            // ---------- Logo upload (support "logo" and "org_logo") ----------
            $logoPath = $brandingOld['logo_path'] ?? '';

            $fileField = null;
            if (!empty($_FILES['logo']['tmp_name'])) {
                $fileField = 'logo';
            } elseif (!empty($_FILES['org_logo']['tmp_name'])) {
                // BizFlow-style field name
                $fileField = 'org_logo';
            }

            if ($fileField !== null
                && !empty($_FILES[$fileField]['tmp_name'])
                && \is_uploaded_file($_FILES[$fileField]['tmp_name'])
            ) {
                $baseDir = $this->logoBaseDir();
                $this->ensureDir($baseDir);

                $orgKey = (string)$orgId;
                $dir    = $baseDir . '/' . $orgKey;
                $this->ensureDir($dir);

                $target = $dir . '/logo.png';

                // Remove any old logo.* to avoid junk
                foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
                    $old = $dir . '/logo.' . $ext;
                    if (\is_file($old)) {
                        @\unlink($old);
                    }
                }

                @\move_uploaded_file($_FILES[$fileField]['tmp_name'], $target);
                @\chmod($target, 0644);

                // HTTP path – keep exactly what your invoices/landing expect
                $logoPath = \rtrim($c['module_base'], '/') . "/Assets/Brand/logo/{$orgKey}/logo.png";
            }

            $save = [
                'business_name' => $business,
                'address'       => $address,
                'phone'         => $phone,
                'email'         => $email,
                'website'       => $website,
                'logo_path'     => $logoPath,
            ];

            // DB row + session cache
            $this->saveBrandingRow($pdo, $orgId, $save);

            // Also write BizFlow-style identity.json so landing & PDFs
            // can read from filesystem without DB if needed.
            $this->saveIdentityJson($orgId, $save, $org);

            if (!\headers_sent()) {
                \header("Location: " . \rtrim($c['module_base'], '/') . "/settings?saved=1");
            }
            exit;

        } catch (Throwable $e) {
            $this->oops("POS settings save failed", $e);
        }
    }
}