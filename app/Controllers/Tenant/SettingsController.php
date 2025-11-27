<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use Shared\DB;
use Shared\Csrf;
use Shared\View;

final class SettingsController
{
    private function redirect(string $to): void { header('Location: '.$to, true, 302); exit; }
    private function flash(string $k, string $v): void { $_SESSION[$k] = $v; }
    private function take(string $k): ?string { $v = $_SESSION[$k] ?? null; unset($_SESSION[$k]); return $v; }

    private function guard(string $slug): array
    {
        $org  = $_SESSION['tenant_org']  ?? null;
        $user = $_SESSION['tenant_user'] ?? null;
        if (!$org || !$user || ($org['slug'] ?? '') !== $slug) $this->redirect('/tenant/login');
        return [$org, $user];
    }

    /* ----------------------- helpers: org meta ----------------------- */

    /** Ensure meta table exists (key/value per org). */
    private function ensureMetaTable(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS cp_org_meta (
            org_id INT UNSIGNED NOT NULL,
            meta_key VARCHAR(64) NOT NULL,
            meta_value TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, meta_key),
            KEY idx_org (org_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        try { DB::pdo()->exec($sql); } catch (\Throwable $e) { /* ignore */ }
    }

    /** Fetch several meta keys for an org. */
    private function getMeta(int $orgId, array $keys): array
    {
        if (!$keys) return [];
        $in  = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT meta_key, meta_value FROM cp_org_meta WHERE org_id=? AND meta_key IN ($in)";
        $st  = DB::pdo()->prepare($sql);
        $st->execute(array_merge([$orgId], $keys));
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) $out[$r['meta_key']] = (string)$r['meta_value'];
        return $out;
    }

    /** Upsert meta keys. */
    private function setMeta(int $orgId, array $kv): void
    {
        if (!$kv) return;
        $pdo = DB::pdo();
        $sql = "INSERT INTO cp_org_meta (org_id, meta_key, meta_value, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated_at=NOW()";
        $st  = $pdo->prepare($sql);
        foreach ($kv as $k => $v) $st->execute([$orgId, $k, (string)$v]);
    }

    /** Resolve a cache-busted public logo URL or empty string. */
    private function resolveLogoUrl(int $orgId): string
    {
        $root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $pub  = $root.'/public';
        $dir  = $pub.'/uploads/org-logos';
        if (!is_dir($dir)) return '';

        foreach (['png','jpg','jpeg','webp'] as $ext) {
            $fs = "{$dir}/org-{$orgId}.{$ext}";
            if (is_file($fs)) {
                $ts = @filemtime($fs) ?: time();
                return "/uploads/org-logos/org-{$orgId}.{$ext}?v={$ts}";
            }
        }
        return '';
    }

    /* ----------------------------- GET ----------------------------- */

    /** GET /t/{slug}/settings */
    public function index(array $params): void
    {
        [$org] = $this->guard((string)$params['slug']);

        $pdo  = DB::pdo();
        $stmt = $pdo->prepare("SELECT id, name, slug, timezone, country, status FROM cp_organizations WHERE id=? LIMIT 1");
        $stmt->execute([(int)$org['id']]);
        $orgRow = $stmt->fetch(\PDO::FETCH_ASSOC) ?: $org;

        $this->ensureMetaTable();
        $meta = $this->getMeta((int)$org['id'], ['address','phone','email']);

        // Attach meta + logo URL for the view
        $orgRow['meta']     = $meta;
        $orgRow['logo_url'] = $this->resolveLogoUrl((int)$org['id']);

        View::render('tenant/settings', [
            'scope'   => 'tenant',
            'layout'  => 'tenant/shared/layouts/shell',
            'title'   => 'Organization Settings',
            'csrf'    => Csrf::token(),
            'org'     => $orgRow,
            'slug'    => $org['slug'],
            'flash'   => $this->take('_msg'),
            'error'   => $this->take('_err'),
        ]);
    }

    /* ---------------------------- POST ----------------------------- */

    /** POST /t/{slug}/settings */
    public function update(array $params): void
    {
        [$org] = $this->guard((string)$params['slug']);
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err','Session expired.'); $this->redirect('/t/'.$org['slug'].'/settings');
        }

        $name = trim((string)($_POST['name']     ?? ''));
        $tz   = trim((string)($_POST['timezone'] ?? ''));
        $cty  = trim((string)($_POST['country']  ?? ''));

        $addr = trim((string)($_POST['address']  ?? ''));
        $phone= trim((string)($_POST['phone']    ?? ''));
        $email= trim((string)($_POST['email']    ?? ''));

        $pdo = DB::pdo();
        $upd = $pdo->prepare("UPDATE cp_organizations SET name=?, timezone=?, country=?, updated_at=NOW() WHERE id=? LIMIT 1");
        $upd->execute([$name ?: $org['name'], $tz ?: null, $cty ?: null, (int)$org['id']]);

        // Save identity to meta
        $this->ensureMetaTable();
        $metaToSave = [
            'address' => $addr,
            'phone'   => $phone,
            'email'   => $email,
        ];
        $this->setMeta((int)$org['id'], $metaToSave);

        // Handle logo (optional)
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
            $dir  = $root.'/public/uploads/org-logos';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','webp'], true)) {
                $dest = $dir.'/org-'.$org['id'].'.'.$ext;
                // Remove prior ones with different extensions
                foreach (glob($dir.'/org-'.$org['id'].'.*') ?: [] as $old) { @unlink($old); }
                move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
            }
        }

        // Refresh tenant_org session so shell & documents see latest identity/logo
        try {
            // base org row
            $stmt = $pdo->prepare("SELECT id, name, slug, timezone, country, status FROM cp_organizations WHERE id=? LIMIT 1");
            $stmt->execute([(int)$org['id']]);
            $fresh = $stmt->fetch(\PDO::FETCH_ASSOC) ?: $org;

            // meta + logo url
            $meta = $this->getMeta((int)$org['id'], ['address','phone','email']);
            $fresh['meta']     = $meta;
            $fresh['logo_url'] = $this->resolveLogoUrl((int)$org['id']);

            $_SESSION['tenant_org'] = $fresh; // ← used by shell, invoices, orders, purchases, etc.
        } catch (\Throwable $e) {
            // keep going even if session refresh fails
        }

        $this->flash('_msg','Settings updated.');
        $this->redirect('/t/'.$org['slug'].'/settings');
    }
}