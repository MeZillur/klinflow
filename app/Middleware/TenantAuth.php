<?php
declare(strict_types=1);

namespace App\Middleware;

final class TenantAuth
{
    /**
     * Tenant auth gate — requires a logged-in tenant user.
     * You can store tenant user under $_SESSION['tenant_user'].
     */
    public static function ensure(): void
    {
        if (empty($_SESSION['tenant_user'])) {
            header('Location: /tenant/login', true, 302);
            exit;
        }
    }

    public static function user(): ?array
    {
        return $_SESSION['tenant_user'] ?? null;
    }

    public static function ensureRole(array $roles): void
    {
        $u = self::user();
        if (!$u || !in_array(($u['role'] ?? ''), $roles, true)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}