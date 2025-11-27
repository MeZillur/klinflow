<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class SettingsController extends BaseController
{
    /* -------------------------------------------------------
       Ensure hotel branding table exists
    ------------------------------------------------------- */
    private function brandingTable(PDO $pdo): string
    {
        $tbl = 'hms_branding';

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$tbl}` (
                `org_id`        BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                `property_name` VARCHAR(200)   NOT NULL,
                `address`       TEXT           NULL,
                `phone`         VARCHAR(50)    NULL,
                `email`         VARCHAR(200)   NULL,
                `website`       VARCHAR(200)   NULL,
                `logo_path`     VARCHAR(255)   NULL,
                `updated_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        return $tbl;
    }

    /* -------------------------------------------------------
       Load branding (merged with org defaults)
    ------------------------------------------------------- */
    private function getBranding(PDO $pdo, int $orgId, array $ctxOrg = []): array
    {
        $tbl = $this->brandingTable($pdo);
        $st  = $pdo->prepare("SELECT * FROM {$tbl} WHERE org_id = ? LIMIT 1");
        $st->execute([$orgId]);

        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            // View e amra business_name use korbo, DB te property_name
            'business_name' => $row['property_name'] ?? ($ctxOrg['name']    ?? 'Your Hotel Name'),
            'address'       => $row['address']       ?? ($ctxOrg['address'] ?? ''),
            'phone'         => $row['phone']         ?? ($ctxOrg['phone']   ?? ''),
            'email'         => $row['email']         ?? ($ctxOrg['email']   ?? ''),
            'website'       => $row['website']       ?? ($ctxOrg['website'] ?? ''),
            'logo_path'     => $row['logo_path']     ?? '',
        ];
    }

    /* -------------------------------------------------------
       Save branding row + optional session sync
    ------------------------------------------------------- */
    private function saveBrandingRow(PDO $pdo, int $orgId, array $data): void
    {
        $tbl = $this->brandingTable($pdo);

        $sql = "
            INSERT INTO {$tbl}
                (org_id, property_name, address, phone, email, website, logo_path)
            VALUES
                (:org_id, :property_name, :address, :phone, :email, :website, :logo_path)
            ON DUPLICATE KEY UPDATE
                property_name = VALUES(property_name),
                address       = VALUES(address),
                phone         = VALUES(phone),
                email         = VALUES(email),
                website       = VALUES(website),
                logo_path     = VALUES(logo_path)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':org_id'        => $orgId,
            ':property_name' => $data['business_name'],
            ':address'       => $data['address'],
            ':phone'         => $data['phone'],
            ':email'         => $data['email'],
            ':website'       => $data['website'],
            ':logo_path'     => $data['logo_path'],
        ]);

        // Session e rakha – HotelFlow header / folio te use korbo
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['hotelflow_branding'] = $data;
    }

    /* -------------------------------------------------------
       Small helper: resolve org_id from ctx or session
    ------------------------------------------------------- */
    private function resolveOrgId(array $c): int
    {
        $orgId = isset($c['org_id']) ? (int)$c['org_id'] : 0;

        if ($orgId <= 0) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $org   = $_SESSION['tenant_org'] ?? null;
            $orgId = (int)($org['id'] ?? 0);
        }

        return $orgId;
    }

    /* -------------------------------------------------------
       GET /settings  (branding form)
    ------------------------------------------------------- */
    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($c);

            if ($orgId <= 0) {
                http_response_code(400);
                echo 'Organisation context missing for HotelFlow settings.';
                return;
            }

            $branding    = $this->getBranding($pdo, $orgId, $c['org'] ?? []);
            $moduleBase  = rtrim((string)($c['module_base'] ?? '/apps/hotelflow'), '/');
            $saved       = isset($_GET['saved']) && $_GET['saved'] === '1';

            $this->view('settings/index', [
                'title'       => 'Hotel Branding',
                'branding'    => $branding,
                'module_base' => $moduleBase,
                'saved'       => $saved,
            ], $c);

        } catch (Throwable $e) {
            if (method_exists($this, 'oops')) {
                $this->oops('HotelFlow settings index failed', $e);
            } else {
                http_response_code(500);
                echo 'HotelFlow settings index failed: '
                    .htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }

    /* -------------------------------------------------------
       POST /settings  (save branding)
    ------------------------------------------------------- */
    public function save(array $ctx = []): void
    {
        try {
            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($c);

            if ($orgId <= 0) {
                http_response_code(400);
                echo 'Organisation context missing for HotelFlow settings.';
                return;
            }

            $brandingOld = $this->getBranding($pdo, $orgId, $c['org'] ?? []);
            $moduleBase  = rtrim((string)($c['module_base'] ?? '/apps/hotelflow'), '/');

            // Basic fields
            $business = trim((string)($_POST['business_name'] ?? ''));
            if ($business === '') {
                $business = 'Your Hotel Name';
            }

            $address = trim((string)($_POST['address'] ?? ''));
            $phone   = trim((string)($_POST['phone'] ?? ''));
            $email   = trim((string)($_POST['email'] ?? ''));
            $website = trim((string)($_POST['website'] ?? ''));

            // Existing logo (if any)
            $logoPath = $brandingOld['logo_path'] ?? '';

            // Handle new upload
            if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {

                // Module root = Controllers/.. → hotelflow/
                $moduleDir = \dirname(__DIR__, 1);
                $dir       = rtrim($moduleDir, '/')."/Assets/Brand/logo/".$orgId;

                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }

                $target = $dir."/logo.png";
                @move_uploaded_file($_FILES['logo']['tmp_name'], $target);

                // Correct public URL (direct from /modules/, NOT via /t/{slug}/apps/…)
                $logoPath = "/modules/hotelflow/Assets/Brand/logo/".$orgId."/logo.png";
            }

            $save = [
                'business_name' => $business,
                'address'       => $address,
                'phone'         => $phone,
                'email'         => $email,
                'website'       => $website,
                'logo_path'     => $logoPath,
            ];

            $this->saveBrandingRow($pdo, $orgId, $save);

            header("Location: ".$moduleBase."/settings?saved=1");
            exit;

        } catch (Throwable $e) {
            if (method_exists($this, 'oops')) {
                $this->oops('HotelFlow settings save failed', $e);
            } else {
                http_response_code(500);
                echo 'HotelFlow settings save failed: '
                    .htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}