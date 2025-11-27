<?php
declare(strict_types=1);

namespace App\Services;

use Shared\DB;

final class TenantSeeder
{
    /**
     * Idempotent seed for a tenant org DB.
     * - Ensures base schema (via DB::ensureTenantSchema).
     * - Creates an owner user from cp_organizations.owner_email if not present.
     */
    public static function run(array $org): void
    {
        // 1) ensure schema exists
        DB::ensureTenantSchema();

        $orgId = (int)($org['id'] ?? 0);
        if ($orgId <= 0) return;

        // fetch owner_email/name from CP side (global DB)
        $cp = DB::pdo();
        $stmt = $cp->prepare("SELECT owner_email, name FROM cp_organizations WHERE id=? LIMIT 1");
        $stmt->execute([$orgId]);
        $row = $stmt->fetch();
        if (!$row) return;

        $ownerEmail = trim((string)$row['owner_email']);
        $orgName    = trim((string)$row['name']);

        if ($ownerEmail === '') return;

        $tenant = DB::tenant();

        // already seeded?
        $chk = $tenant->prepare("SELECT id FROM tenant_users WHERE org_id=? AND email=? LIMIT 1");
        $chk->execute([$orgId, $ownerEmail]);
        if ($chk->fetch()) {
            return; // owner already exists – nothing to do
        }

        // create initial owner with a temporary strong password
        $temp = bin2hex(random_bytes(6)); // 12 hex chars
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        $ins = $tenant->prepare("
            INSERT INTO tenant_users (org_id, name, email, username, role, password_hash, is_active, created_at, updated_at)
            VALUES (?,?,?,?, 'owner', ?, 1, NOW(), NOW())
        ");
        $username = strtolower(preg_replace('/[^a-z0-9]+/i', '.', explode('@',$ownerEmail)[0] ?? 'owner'));
        $name     = $orgName !== '' ? $orgName.' Owner' : 'Owner';
        $ins->execute([$orgId, $name, $ownerEmail, $username, $hash]);

        // optional: write audit log
        try {
            $aud = $tenant->prepare("
                INSERT INTO tenant_audit_logs (org_id, user_id, action, meta, ip, ua, created_at)
                VALUES (?,?,?,?,INET6_ATON(?),?,NOW())
            ");
            $aud->execute([
                $orgId, null, 'seed.owner.create',
                json_encode(['email' => $ownerEmail], JSON_UNESCAPED_SLASHES),
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\Throwable $e) { /* ignore */ }

        // NOTE: You can send an email here with the temp password if desired.
        // Keeping silent to avoid leaking secrets; owner can use "Forgot Password" to set a password.
    }
}