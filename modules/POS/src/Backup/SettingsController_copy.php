<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class SettingsController extends BaseController
{
    /* -------------------------------------------------------
       Ensure branding table exists
    ------------------------------------------------------- */
    private function brandingTable(PDO $pdo): string
    {
        $tbl = 'pos_branding';

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$tbl}` (
                `org_id`        BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                `business_name` VARCHAR(200)   NOT NULL,
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
       Load branding (merged with global org defaults)
    ------------------------------------------------------- */
    private function getBranding(PDO $pdo, int $orgId, array $ctxOrg = []): array
    {
        $tbl = $this->brandingTable($pdo);
        $st  = $pdo->prepare("SELECT * FROM {$tbl} WHERE org_id = ? LIMIT 1");
        $st->execute([$orgId]);

        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'business_name' => $row['business_name'] ?? ($ctxOrg['name']    ?? 'Your Business Name'),
            'address'       => $row['address']       ?? ($ctxOrg['address'] ?? ''),
            'phone'         => $row['phone']         ?? ($ctxOrg['phone']   ?? ''),
            'email'         => $row['email']         ?? ($ctxOrg['email']   ?? ''),
            'website'       => $row['website']       ?? ($ctxOrg['website'] ?? ''),
            'logo_path'     => $row['logo_path']     ?? '',
        ];
    }

    /* -------------------------------------------------------
       Save branding row + session sync
    ------------------------------------------------------- */
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

        // Sync session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['pos_branding'] = $data;
    }

    /* -------------------------------------------------------
       GET /pos/settings
    ------------------------------------------------------- */
    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();

            $branding = $this->getBranding($pdo, $orgId, $c['org'] ?? []);

            $this->view($c['module_dir']."/Views/settings/index.php", [
                'title'    => 'POS Branding',
                'branding' => $branding,
                'base'     => $c['module_base'],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops("POS settings index failed", $e);
        }
    }

    /* -------------------------------------------------------
       POST /pos/settings
    ------------------------------------------------------- */
    public function save(array $ctx = []): void
    {
        try {
            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();

            $brandingOld = $this->getBranding($pdo, $orgId, $c['org'] ?? []);

            // Grab fields
            $business = trim($_POST['business_name'] ?? '');
            if ($business === '') $business = 'Your Business Name';

            $address  = trim($_POST['address'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $website  = trim($_POST['website'] ?? '');

            // Logo
            $logoPath = $brandingOld['logo_path'];

            if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {

                $dir = rtrim($c['module_dir'], '/')."/Assets/Brand/logo/".$orgId;

                if (!is_dir($dir)) @mkdir($dir, 0775, true);

                $target = $dir."/logo.png";

                @move_uploaded_file($_FILES['logo']['tmp_name'], $target);

                $logoPath = rtrim($c['module_base'], '/')."/Assets/Brand/logo/".$orgId."/logo.png";
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

            header("Location: ".$c['module_base']."/settings?saved=1");
            exit;

        } catch (Throwable $e) {
            $this->oops("POS settings save failed", $e);
        }
    }
}