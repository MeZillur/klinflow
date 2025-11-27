<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use Shared\View;
use Shared\DB;
use Shared\Csrf;

final class MeController
{
    /* ------------------------ Small helpers ------------------------ */

    private function user(): array {
        $u = $_SESSION['tenant_user'] ?? null;
        if (!$u) { header('Location: /tenant/login', true, 302); exit; }
        return (array)$u;
    }

    private function org(): array {
        $o = $_SESSION['tenant_org'] ?? null;
        if (!$o) { header('Location: /tenant/login', true, 302); exit; }
        return (array)$o;
    }

    private function ctx(): array {
        $org  = $this->org();
        $slug = (string)($org['slug'] ?? '');
        return [
            'org'         => $org,
            'module_base' => $slug ? "/t/{$slug}/apps/dms" : '/t/unknown/apps/dms',
        ];
    }

    private function shellRender(string $view, array $vars = []): void {
        $base = $this->ctx();
        $vars['org']         = $base['org'];
        $vars['module_base'] = $base['module_base'];
        View::render('tenant/'.$view, $vars, 'tenant/shared/layouts/shell');
    }

    private function flash(string $key, string $msg): void {
        $_SESSION['flash'][$key] = $msg;
    }

    private function take(?string $key): ?string {
        if ($key === null) return null;
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }

    private function back(): void {
        $to = (string)($_SERVER['HTTP_REFERER'] ?? '');
        header('Location: '.($to ?: '/me'), true, 302);
        exit;
    }

    /* ---------------- Avatar helpers (canonical + fallback) -------- */

    private function avatarPublicUrl(array $org, array $user): string {
        $orgId  = (int)($org['id'] ?? 0);
        $userId = (int)($user['id'] ?? 0);

        // absolute URL already?
        $rel = (string)($user['avatar'] ?? $user['avatar_path'] ?? '');
        if ($rel !== '' && (str_starts_with($rel, 'http://') || str_starts_with($rel, 'https://'))) {
            return $rel;
        }

        // if starts with /public/..., convert to public URL by stripping /public
        if ($rel !== '' && str_starts_with($rel, '/public/')) {
            $abs = BASE_PATH . $rel;
            if (is_file($abs)) return substr($rel, 7) . '?v=' . filemtime($abs); // '/uploads/...'
        }

        // if already an /uploads/... path
        if ($rel !== '' && str_starts_with($rel, '/uploads/')) {
            $abs = BASE_PATH . '/public' . $rel;
            if (is_file($abs)) return $rel . '?v=' . filemtime($abs);
        }

        // plain relative like "13/4/avatar.jpg"
        if ($rel !== '' && !str_starts_with($rel, '/')) {
            $abs = BASE_PATH . '/public/uploads/avatars/' . $rel;
            if (is_file($abs)) return '/uploads/avatars/' . $rel . '?v=' . filemtime($abs);
        }

        // canonical
        foreach (['png','jpg','jpeg'] as $ext) {
            $abs = BASE_PATH . "/public/uploads/avatars/{$orgId}/{$userId}/avatar.{$ext}";
            if (is_file($abs)) {
                return "/uploads/avatars/{$orgId}/{$userId}/avatar.{$ext}?v=" . filemtime($abs);
            }
        }

        // legacy
        foreach (glob(BASE_PATH . "/public/uploads/avatars/u{$userId}_*.{png,jpg,jpeg}", GLOB_BRACE) ?: [] as $abs) {
            return '/uploads/avatars/' . basename($abs) . '?v=' . filemtime($abs);
        }

        // default
        return '/assets/img/avatar-default.png';
    }

    private function saveAvatarAndPath(array $org, array $user, array $upload): string {
        $orgId  = (int)$org['id'];
        $userId = (int)$user['id'];

        $ext = strtolower(pathinfo($upload['name'] ?? 'avatar.jpg', PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg'], true)) {
            throw new \RuntimeException('Only PNG or JPG files are allowed.');
        }

        $destDir = BASE_PATH . "/public/uploads/avatars/{$orgId}/{$userId}";
        if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }

        $destAbs = $destDir . "/avatar.{$ext}";
        if (!move_uploaded_file($upload['tmp_name'] ?? '', $destAbs)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        foreach (['png','jpg','jpeg'] as $other) {
            if ($other !== $ext) @unlink($destDir . "/avatar.{$other}");
        }
        foreach (glob(BASE_PATH . "/public/uploads/avatars/u{$userId}_*.{png,jpg,jpeg}", GLOB_BRACE) ?: [] as $legacy) {
            @unlink($legacy);
        }

        return "{$orgId}/{$userId}/avatar.{$ext}";
    }

    /* --------------------------- GET /me --------------------------- */

    public function index(): void
{
    $tenantUser = $this->user();
    $tenantOrg  = $this->org();

    // Build the module base (/t/{slug}/apps/dms)
    $slug       = (string)($tenantOrg['slug'] ?? '');
    $moduleBase = $slug ? "/t/{$slug}/apps/dms" : '/t/unknown/apps/dms';

    // Avatar URL you already had
    $avatarUrl = $this->avatarPublicUrl($tenantOrg, $tenantUser);

    // Optional sidenav for DMS shell (set to null if you don’t have one)
    $sidenav = BASE_PATH . '/modules/DMS/Views/_inc/sidenav.php';
    if (!is_file($sidenav)) {
        $sidenav = null; // keep layout happy; no guessing
    }

    View::render(
        'tenant/me/index',
        [
            'title'        => 'My Profile',
            'user'         => $tenantUser,
            'org'          => $tenantOrg,
            'avatar_url'   => $avatarUrl,
            'csrf'         => \Shared\Csrf::token(),
            'module_base'  => $moduleBase,
            // wire shell options:
            'moduleSidenav'=> $sidenav,
        ],
        // <<< Use the DMS shell >>>
        'modules/DMS/Views/shared/layouts/shell.php'
    );
}

    /* ---------------------- POST /me/profile ----------------------- */

    public function updateProfile(): void {
        $u = $this->user();
        if (!Csrf::verify($_POST['_csrf'] ?? '')) { $this->flash('err', 'Session expired.'); $this->back(); }

        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $timezone = trim((string)($_POST['timezone'] ?? ''));

        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("
                UPDATE cp_org_users
                   SET full_name=?, email=?, phone=?, timezone=?, updated_at=NOW()
                 WHERE id=? LIMIT 1
            ");
            $stmt->execute([$name, $email, $phone, $timezone, (int)$u['id']]);
        } catch (\Throwable $e) {
            // soft-fail
        }

        $_SESSION['tenant_user']['name']     = $name     ?: ($u['name'] ?? '');
        $_SESSION['tenant_user']['email']    = $email    ?: ($u['email'] ?? '');
        $_SESSION['tenant_user']['phone']    = $phone    ?: ($u['phone'] ?? '');
        $_SESSION['tenant_user']['timezone'] = $timezone ?: ($u['timezone'] ?? '');

        $this->flash('ok', 'Profile updated.');
        $this->back();
    }

    /* --------------------- POST /me/password ----------------------- */

    public function updatePassword(): void
{
    $u = $this->user();  // ensures logged-in
    $o = $this->org();   // tenant org

    if (!\Shared\Csrf::verify($_POST['_csrf'] ?? '')) {
        $this->flash('err', 'Session expired.'); $this->back();
    }

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($new === '' || $new !== $confirm) {
        $this->flash('err', 'Passwords do not match.'); $this->back();
    }

    $userId = (int)($u['id'] ?? 0);
    $orgId  = (int)($o['id'] ?? 0);
    if ($userId <= 0 || $orgId <= 0) { $this->flash('err','User context missing.'); $this->back(); }

    $pdo = \Shared\DB::pdo();
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    try {
        // --- 1) Read current hash from tenant_users
        $sel = $pdo->prepare("
            SELECT password_hash
            FROM tenant_users
            WHERE id=? AND org_id=? AND is_active=1
            LIMIT 1
        ");
        $sel->execute([$userId, $orgId]);
        $hash = (string)$sel->fetchColumn();

        // If we have a stored hash, verify current password
        if ($hash !== '' && !password_verify($current, $hash)) {
            $this->flash('err', 'Current password is incorrect.'); $this->back();
        }

        // --- 2) Write new hash
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("
            UPDATE tenant_users
               SET password_hash=?, updated_at=NOW()
             WHERE id=? AND org_id=? LIMIT 1
        ");
        $ok = $upd->execute([$newHash, $userId, $orgId]);
        if (!$ok || $upd->rowCount() < 1) {
            $this->flash('err', 'Password update failed.'); $this->back();
        }

        // --- 3) Success → force re-login with a friendly banner
        session_regenerate_id(true);
        unset($_SESSION['tenant_user'], $_SESSION['tenant_org']);
        $_SESSION['flash'] = [
            'ok' => 'Your password was changed successfully. Please sign in with your new password.'
        ];
        header('Location: /tenant/login', true, 302);
        exit;

    } catch (\Throwable $e) {
        $this->flash('err', 'Unexpected error while updating password.');
        $this->back();
    }
}

    /* ----------------------- POST /me/avatar ----------------------- */

    public function updateAvatar(): void {
        $user = $this->user();
        $org  = $this->org();

        if (empty($_FILES['avatar']['tmp_name']) || (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->flash('err', 'No file selected.'); $this->back();
        }

        try {
            $relPath = $this->saveAvatarAndPath($org, $user, $_FILES['avatar']); // "org/user/avatar.jpg"

            $pdo = DB::pdo();
            $pdo->prepare("UPDATE cp_org_users SET avatar_path=?, updated_at=NOW() WHERE id=? LIMIT 1")
                ->execute([$relPath, (int)$user['id']]);

            $_SESSION['tenant_user']['avatar']      = $relPath;
            $_SESSION['tenant_user']['avatar_path'] = $relPath;

            $this->flash('ok', 'Avatar updated.');
        } catch (\Throwable $e) {
            $this->flash('err', 'Avatar upload failed.');
        }

        $this->back();
    }
}