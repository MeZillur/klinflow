<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use PDO;
use Shared\View;
use Shared\DB;
use Shared\Csrf;

final class MeController
{
    /* ============================================================
     * Low-level helpers
     * ========================================================== */

    private function pdo(): PDO
    {
        return DB::pdo();
    }

    private function user(): array
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $u = $_SESSION['tenant_user'] ?? null;
        if (!$u) {
            header('Location: /tenant/login', true, 302);
            exit;
        }
        return (array)$u;
    }

    private function org(): array
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $o = $_SESSION['tenant_org'] ?? null;
        if (!$o) {
            header('Location: /tenant/login', true, 302);
            exit;
        }
        return (array)$o;
    }

    private function moduleBase(array $org): string
    {
        $slug = (string)($org['slug'] ?? '');
        // Send user to tenant home; front.php will redirect to default module or /dashboard
        return $slug !== '' ? "/t/{$slug}" : '/';
    }

    private function redirectToApps(string $message = null, string $type = 'ok'): void
    {
        $org  = $this->org();
        $slug = (string)($org['slug'] ?? '');
        $to   = $slug !== '' ? "/t/{$slug}" : '/';

        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        if ($message !== null) {
            $_SESSION['flash'][$type] = $message;
        }

        header('Location: ' . $to, true, 302);
        exit;
    }

    private function back(): void
    {
        $to = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($to === '') {
            $this->redirectToApps();
        }
        header('Location: '.$to, true, 302);
        exit;
    }

    /* ============================================================
     * Flash helpers (scoped to “me”)
     * ========================================================== */

    private function flashSet(string $key, string $msg): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['me_flash'][$key] = $msg;
    }

    private function flashBag(): array
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $bag = $_SESSION['me_flash'] ?? [];
        unset($_SESSION['me_flash']);
        return is_array($bag) ? $bag : [];
    }

    /* ============================================================
     * Avatar helpers
     * ========================================================== */

    private function avatarPublicUrl(array $org, array $user): string
    {
        $orgId  = (int)($org['id'] ?? 0);
        $userId = (int)($user['id'] ?? 0);

        $rel = (string)($user['avatar'] ?? $user['avatar_path'] ?? '');

        // absolute URL
        if ($rel !== '' && (str_starts_with($rel, 'http://') || str_starts_with($rel, 'https://'))) {
            return $rel;
        }

        // /public/…
        if ($rel !== '' && str_starts_with($rel, '/public/')) {
            $abs = BASE_PATH.$rel;
            if (is_file($abs)) {
                return substr($rel, 7).'?v='.filemtime($abs); // strip /public
            }
        }

        // /uploads/…
        if ($rel !== '' && str_starts_with($rel, '/uploads/')) {
            $abs = BASE_PATH.'/public'.$rel;
            if (is_file($abs)) {
                return $rel.'?v='.filemtime($abs);
            }
        }

        // plain relative like "13/4/avatar.jpg"
        if ($rel !== '' && $rel[0] !== '/') {
            $abs = BASE_PATH."/public/uploads/avatars/{$rel}";
            if (is_file($abs)) {
                return "/uploads/avatars/{$rel}?v=".filemtime($abs);
            }
        }

        // canonical {orgId}/{userId}/avatar.{ext}
        foreach (['png','jpg','jpeg'] as $ext) {
            $abs = BASE_PATH."/public/uploads/avatars/{$orgId}/{$userId}/avatar.{$ext}";
            if (is_file($abs)) {
                return "/uploads/avatars/{$orgId}/{$userId}/avatar.{$ext}?v=".filemtime($abs);
            }
        }

        return '/assets/img/avatar-default.png';
    }

    private function saveAvatarAndPath(array $org, array $user, array $upload): string
    {
        $orgId  = (int)$org['id'];
        $userId = (int)$user['id'];

        $ext = strtolower(pathinfo($upload['name'] ?? 'avatar.jpg', PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            throw new \RuntimeException('Only PNG or JPG files are allowed.');
        }

        $destDir = BASE_PATH."/public/uploads/avatars/{$orgId}/{$userId}";
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $destAbs = $destDir."/avatar.{$ext}";
        if (!move_uploaded_file($upload['tmp_name'] ?? '', $destAbs)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        foreach (['png','jpg','jpeg'] as $other) {
            if ($other !== $ext) {
                @unlink($destDir."/avatar.{$other}");
            }
        }

        // We’ll store just a relative path in session – DB doesn’t need a column.
        return "{$orgId}/{$userId}/avatar.{$ext}";
    }

    /* ============================================================
     * GET /me
     * ========================================================== */

    public function index(): void
    {
        $user  = $this->user();
        $org   = $this->org();
        $flash = $this->flashBag();

        $moduleBase = $this->moduleBase($org);
        $avatarUrl  = $this->avatarPublicUrl($org, $user);

        View::render(
            'tenant/me/index',
            [
                'title'       => 'My Profile',
                'user'        => $user,
                'flash'       => $flash,
                'csrf'        => Csrf::token(),
                'module_base' => $moduleBase,
                'avatar_url'  => $avatarUrl,
            ],
            // Global CP-style shell (header only, no sidenav)
            'cp/shared/layouts/shell'
        );
    }

    /* ============================================================
     * POST /me/profile
     *   Uses: tenant_users (and best-effort mirror to cp_users)
     * ========================================================== */

    public function updateProfile(): void
    {
        $u = $this->user();
        $o = $this->org();

        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flashSet('err', 'Session expired, please try again.');
            $this->back();
        }

        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $timezone = trim((string)($_POST['timezone'] ?? '')); // no DB column yet

        $userId = (int)($u['id'] ?? 0);
        $orgId  = (int)($o['id'] ?? 0);

        if ($userId <= 0 || $orgId <= 0) {
            $this->flashSet('err', 'User or organization context missing.');
            $this->back();
        }

        $pdo = $this->pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            // 1) tenant_users (REAL TABLE)
            $stmt = $pdo->prepare("
                UPDATE tenant_users
                   SET name   = ?,
                       email  = ?,
                       mobile = ?,
                       updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND org_id = ?
                 LIMIT 1
            ");
            $stmt->execute([$name, $email, $phone, $userId, $orgId]);

            if ($stmt->rowCount() < 1) {
                throw new \RuntimeException('No profile row updated in tenant_users (id/org_id mismatch).');
            }

            // 2) Best-effort mirror into cp_users (same org + email)
            try {
                $stmt2 = $pdo->prepare("
                    UPDATE cp_users
                       SET name  = ?,
                           email = ?,
                           phone = ?,
                           updated_at = CURRENT_TIMESTAMP
                     WHERE org_id = ? AND email = ?
                     LIMIT 1
                ");
                $stmt2->execute([$name, $email, $phone, $orgId, $u['email'] ?? $email]);
            } catch (\Throwable $ignored) {
                // optional – ignore error if cp_users structure changes
            }

        } catch (\Throwable $e) {
            $this->flashSet('err', 'Could not save profile (DB error): '.$e->getMessage());
            $this->back();
        }

        // Refresh session values
        $_SESSION['tenant_user']['name']     = $name     ?: ($u['name'] ?? '');
        $_SESSION['tenant_user']['email']    = $email    ?: ($u['email'] ?? '');
        $_SESSION['tenant_user']['phone']    = $phone    ?: ($u['phone'] ?? '');
        $_SESSION['tenant_user']['mobile']   = $phone    ?: ($u['mobile'] ?? '');
        $_SESSION['tenant_user']['timezone'] = $timezone ?: ($u['timezone'] ?? '');

        // Success → go back to tenant home (/t/{slug}) with flash
        $this->redirectToApps('Profile updated.');
    }

    /* ============================================================
     * POST /me/password
     *   Uses: tenant_users
     * ========================================================== */

    public function updatePassword(): void
    {
        $u = $this->user();
        $o = $this->org();

        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flashSet('err', 'Session expired, please try again.');
            $this->back();
        }

        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($new === '' || $new !== $confirm) {
            $this->flashSet('err', 'Passwords do not match.');
            $this->back();
        }

        $userId = (int)($u['id'] ?? 0);
        $orgId  = (int)($o['id'] ?? 0);
        if ($userId <= 0 || $orgId <= 0) {
            $this->flashSet('err', 'User or organization context missing.');
            $this->back();
        }

        $pdo = $this->pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            // 1) Read current hash from tenant_users
            $sel = $pdo->prepare("
                SELECT password_hash
                  FROM tenant_users
                 WHERE id = ? AND org_id = ? AND is_active = 1
                 LIMIT 1
            ");
            $sel->execute([$userId, $orgId]);
            $hash = (string)$sel->fetchColumn();

            if ($hash !== '' && !password_verify($current, $hash)) {
                $this->flashSet('err', 'Current password is incorrect.');
                $this->back();
            }

            // 2) Write new hash
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("
                UPDATE tenant_users
                   SET password_hash = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND org_id = ?
                 LIMIT 1
            ");
            $upd->execute([$newHash, $userId, $orgId]);

            if ($upd->rowCount() < 1) {
                throw new \RuntimeException('Password update affected 0 rows (id/org_id mismatch).');
            }

            // 3) Force re-login
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            session_regenerate_id(true);
            unset($_SESSION['tenant_user'], $_SESSION['tenant_org']);
            $_SESSION['me_flash'] = [
                'ok' => 'Your password was changed successfully. Please sign in with your new password.',
            ];
            header('Location: /tenant/login', true, 302);
            exit;

        } catch (\Throwable $e) {
            $this->flashSet('err', 'Could not update password (DB error): '.$e->getMessage());
            $this->back();
        }
    }

    /* ============================================================
     * POST /me/avatar
     *   File on disk only; no DB columns required
     * ========================================================== */

    public function updateAvatar(): void
    {
        $user = $this->user();
        $org  = $this->org();

        if (empty($_FILES['avatar']['tmp_name']) ||
            (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->flashSet('err', 'No file selected.');
            $this->back();
        }

        try {
            $relPath = $this->saveAvatarAndPath($org, $user, $_FILES['avatar']); // "org/user/avatar.jpg"

            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $_SESSION['tenant_user']['avatar']      = $relPath;
            $_SESSION['tenant_user']['avatar_path'] = $relPath;

            $this->flashSet('ok', 'Avatar updated.');
        } catch (\Throwable $e) {
            $this->flashSet('err', 'Avatar upload failed: '.$e->getMessage());
        }

        $this->back();
    }
}