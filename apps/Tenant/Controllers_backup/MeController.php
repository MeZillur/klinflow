<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use PDO;
use Shared\View;
use Shared\DB;
use Shared\Csrf;

final class MeController
{
    /* ------------------------------------------------------------------
     * Low-level helpers
     * ----------------------------------------------------------------- */

    private function ensureSession(): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
    }

    private function user(): array
    {
        $this->ensureSession();
        $u = $_SESSION['tenant_user'] ?? null;
        if (!$u) {
            header('Location: /tenant/login', true, 302);
            exit;
        }
        return (array)$u;
    }

    private function org(): array
    {
        $this->ensureSession();
        $o = $_SESSION['tenant_org'] ?? null;
        if (!$o) {
            header('Location: /tenant/login', true, 302);
            exit;
        }
        return (array)$o;
    }

    private function pdo(): PDO
    {
        // Global connection (cp_org_users + tenant_users live here)
        return DB::pdo();
    }

    private function moduleBase(array $org): string
    {
        $slug = (string)($org['slug'] ?? '');
        return $slug !== '' ? "/t/{$slug}/apps/dms" : '/apps/dms';
    }

    /* ------------------------------------------------------------------
     * Flash helpers (simple session bag)
     * ----------------------------------------------------------------- */

    private function flashSet(string $key, string $msg): void
    {
        $this->ensureSession();
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][$key] = $msg;
    }

    private function flashTake(?string $key): ?string
    {
        $this->ensureSession();
        if ($key === null) {
            return null;
        }
        $val = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $val;
    }

    private function back(): void
    {
        $to = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($to === '') {
            $to = '/me';
        }
        header('Location: ' . $to, true, 302);
        exit;
    }

    private function redirectToApps(string $successMessage): void
    {
        $org        = $this->org();
        $moduleBase = $this->moduleBase($org);

        $this->flashSet('ok', $successMessage);
        header('Location: ' . $moduleBase, true, 302);
        exit;
    }

    /* ------------------------------------------------------------------
     * Avatar helpers
     * ----------------------------------------------------------------- */

    private function avatarPublicUrl(array $org, array $user): string
    {
        $orgId  = (int)($org['id'] ?? 0);
        $userId = (int)($user['id'] ?? 0);

        $rel = (string)($user['avatar'] ?? $user['avatar_path'] ?? '');
        if ($rel !== '' && (str_starts_with($rel, 'http://') || str_starts_with($rel, 'https://'))) {
            return $rel;
        }

        if ($rel !== '' && str_starts_with($rel, '/public/')) {
            $abs = BASE_PATH . $rel;
            if (is_file($abs)) {
                return substr($rel, 7) . '?v=' . filemtime($abs);
            }
        }

        if ($rel !== '' && str_starts_with($rel, '/uploads/')) {
            $abs = BASE_PATH . '/public' . $rel;
            if (is_file($abs)) {
                return $rel . '?v=' . filemtime($abs);
            }
        }

        if ($rel !== '' && !str_starts_with($rel, '/')) {
            $abs = BASE_PATH . '/public/uploads/avatars/' . $rel;
            if (is_file($abs)) {
                return '/uploads/avatars/' . $rel . '?v=' . filemtime($abs);
            }
        }

        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            $abs = BASE_PATH . "/public/uploads/avatars/{$orgId}/{$userId}/avatar.{$ext}";
            if (is_file($abs)) {
                return "/uploads/avatars/{$orgId}/{$userId}/avatar.{$ext}?v=" . filemtime($abs);
            }
        }

        foreach (glob(BASE_PATH . "/public/uploads/avatars/u{$userId}_*.{png,jpg,jpeg}", GLOB_BRACE) ?: [] as $abs) {
            return '/uploads/avatars/' . basename($abs) . '?v=' . filemtime($abs);
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

        $destDir = BASE_PATH . "/public/uploads/avatars/{$orgId}/{$userId}";
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $destAbs = $destDir . "/avatar.{$ext}";
        if (!move_uploaded_file($upload['tmp_name'] ?? '', $destAbs)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        foreach (['png', 'jpg', 'jpeg'] as $other) {
            if ($other !== $ext) {
                @unlink($destDir . "/avatar.{$other}");
            }
        }
        foreach (glob(BASE_PATH . "/public/uploads/avatars/u{$userId}_*.{png,jpg,jpeg}", GLOB_BRACE) ?: [] as $legacy) {
            @unlink($legacy);
        }

        return "{$orgId}/{$userId}/avatar.{$ext}";
    }

    /* ------------------------------------------------------------------
     * GET /me
     * ----------------------------------------------------------------- */

    public function index(): void
    {
        $user = $this->user();
        $org  = $this->org();

        $moduleBase = $this->moduleBase($org);
        $avatarUrl  = $this->avatarPublicUrl($org, $user);

        $flash = [
            'ok'  => $this->flashTake('ok'),
            'err' => $this->flashTake('err'),
        ];

        View::render(
            'tenant/me/index',
            [
                'title'       => 'My Profile',
                'user'        => $user,
                'org'         => $org,
                'avatar_url'  => $avatarUrl,
                'flash'       => $flash,
                'csrf'        => Csrf::token(),
                'module_base' => $moduleBase,
            ],
            // Use DMS shell (now without sidenav, as you refined)
            'modules/DMS/Views/shared/layouts/shell.php'
        );
    }

    /* ------------------------------------------------------------------
     * POST /me/profile
     * ----------------------------------------------------------------- */

    public function updateProfile(): void
    {
        $u = $this->user();
        $this->org(); // just to ensure org context exists

        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flashSet('err', 'Session expired, please try again.');
            $this->back();
        }

        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $timezone = trim((string)($_POST['timezone'] ?? ''));

        $userId = (int)($u['id'] ?? 0);
        if ($userId <= 0) {
            $this->flashSet('err', 'User context missing.');
            $this->back();
        }

        $debug = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1' || (($_GET['_debug'] ?? '') === '1'));

        try {
            $pdo = $this->pdo();
            $stmt = $pdo->prepare("
                UPDATE cp_org_users
                   SET full_name = ?,
                       email     = ?,
                       phone     = ?,
                       timezone  = ?,
                       updated_at = NOW()
                 WHERE id = ?
                 LIMIT 1
            ");
            $stmt->execute([$name, $email, $phone, $timezone, $userId]);
        } catch (\Throwable $e) {
            $msg = $debug
                ? 'DB error while saving profile: ' . $e->getMessage()
                : 'Could not save profile (DB error).';
            $this->flashSet('err', $msg);
            $this->back();
        }

        // Refresh session copy (so header avatar/menu stays in sync)
        $_SESSION['tenant_user']['name']      = $name     ?: ($u['name'] ?? '');
        $_SESSION['tenant_user']['full_name'] = $name     ?: ($u['full_name'] ?? '');
        $_SESSION['tenant_user']['email']     = $email    ?: ($u['email'] ?? '');
        $_SESSION['tenant_user']['phone']     = $phone    ?: ($u['phone'] ?? '');
        $_SESSION['tenant_user']['timezone']  = $timezone ?: ($u['timezone'] ?? '');

        // Success → go back to apps dashboard
        $this->redirectToApps('Profile updated.');
    }

    /* ------------------------------------------------------------------
     * POST /me/password
     * ----------------------------------------------------------------- */

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
            $this->flashSet('err', 'User context missing.');
            $this->back();
        }

        $debug = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1' || (($_GET['_debug'] ?? '') === '1'));

        $pdo = $this->pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
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

            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("
                UPDATE tenant_users
                   SET password_hash = ?, updated_at = NOW()
                 WHERE id = ? AND org_id = ?
                 LIMIT 1
            ");
            $ok = $upd->execute([$newHash, $userId, $orgId]);
            if (!$ok || $upd->rowCount() < 1) {
                $this->flashSet('err', 'Password update failed.');
                $this->back();
            }

            // Force re-login; show flash on login page
            session_regenerate_id(true);
            unset($_SESSION['tenant_user'], $_SESSION['tenant_org']);
            $_SESSION['flash'] = [
                'ok' => 'Password changed successfully. Please sign in with your new password.',
            ];
            header('Location: /tenant/login', true, 302);
            exit;
        } catch (\Throwable $e) {
            $msg = $debug
                ? 'DB error while updating password: ' . $e->getMessage()
                : 'Unexpected error while updating password.';
            $this->flashSet('err', $msg);
            $this->back();
        }
    }

    /* ------------------------------------------------------------------
     * POST /me/avatar
     * ----------------------------------------------------------------- */

    public function updateAvatar(): void
    {
        $user = $this->user();
        $org  = $this->org();

        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flashSet('err', 'Session expired, please try again.');
            $this->back();
        }

        if (
            empty($_FILES['avatar']['tmp_name']) ||
            (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
        ) {
            $this->flashSet('err', 'No file selected.');
            $this->back();
        }

        $debug = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1' || (($_GET['_debug'] ?? '') === '1'));

        try {
            $relPath = $this->saveAvatarAndPath($org, $user, $_FILES['avatar']);

            $pdo = $this->pdo();
            $stmt = $pdo->prepare("
                UPDATE cp_org_users
                   SET avatar_path = ?, updated_at = NOW()
                 WHERE id = ?
                 LIMIT 1
            ");
            $stmt->execute([$relPath, (int)$user['id']]);

            $_SESSION['tenant_user']['avatar']      = $relPath;
            $_SESSION['tenant_user']['avatar_path'] = $relPath;

            // Success → go back to apps dashboard
            $this->redirectToApps('Avatar updated.');
        } catch (\Throwable $e) {
            $msg = $debug
                ? 'Avatar upload failed: ' . $e->getMessage()
                : 'Avatar upload failed.';
            $this->flashSet('err', $msg);
            $this->back();
        }
    }
}