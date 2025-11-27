<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use Shared\DB;
use Shared\Csrf;
use Shared\View;
use App\Services\Mailer;

/**
 * Tenant Users Controller
 * Handles: 
 *   /t/{slug}/users
 *   /t/{slug}/users/invite
 *   /tenant/invite/accept
 *   /t/{slug}/users/me
 */
final class UsersController
{
    /* --------------------- Utilities --------------------- */
    private function redirect(string $to): void { header('Location: ' . $to, true, 302); exit; }
    private function flash(string $k, string $v): void { $_SESSION[$k] = $v; }
    private function take(string $k): ?string { $v = $_SESSION[$k] ?? null; unset($_SESSION[$k]); return $v; }

    private function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /** Safe local guard (no override of BaseController) */
    private function requireTenant(string $slug): array
    {
        $org  = $_SESSION['tenant_org']  ?? null;
        $user = $_SESSION['tenant_user'] ?? null;
        if (!$org || !$user || ($org['slug'] ?? '') !== $slug) {
            $this->redirect('/tenant/login');
        }
        return [(array)$org, (array)$user, $slug];
    }

    private function assertOwner(array $user, string $slug): void
    {
        if (strtolower((string)($user['role'] ?? '')) !== 'owner') {
            $this->flash('_err', 'Only the tenant owner can access Users.');
            $this->redirect("/t/{$slug}/dashboard");
        }
    }

    private function render(string $view, array $vars): void
    {
        View::render($view, $vars, 'tenant/shared/layouts/shell');
    }

    /** Ensure user/invite tables exist */
    private function ensureTables(): void
    {
        $pdo = DB::pdo();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_users (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              name VARCHAR(120) DEFAULT NULL,
              email VARCHAR(190) NOT NULL,
              username VARCHAR(120) DEFAULT NULL,
              role ENUM('owner','manager','employee') NOT NULL DEFAULT 'employee',
              mobile VARCHAR(32) DEFAULT NULL,
              password_hash VARCHAR(255) DEFAULT NULL,
              is_active TINYINT(1) NOT NULL DEFAULT 1,
              last_login_at DATETIME DEFAULT NULL,
              last_login_ip VARBINARY(16) DEFAULT NULL,
              failed_attempts INT NOT NULL DEFAULT 0,
              locked_until DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_org_email (org_id, email),
              KEY idx_org (org_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_user_invites (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              email VARCHAR(190) NOT NULL,
              role ENUM('owner','manager','employee') NOT NULL DEFAULT 'employee',
              token CHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              accepted_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_invite_token (token),
              UNIQUE KEY uq_org_email_pending (org_id, email),
              KEY idx_org (org_id),
              KEY idx_exp (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /* ========================== USERS LIST ========================== */
    public function index(array $params = []): void
    {
        $slug = (string)($params['slug'] ?? '');
        [$org, $user, $slug] = $this->requireTenant($slug);
        $this->assertOwner($user, $slug);

        $rows = [];
        try {
            $this->ensureTables();
            $pdo = DB::pdo();
            $q = $pdo->prepare("
                SELECT id, name, email, username, role, is_active, created_at
                FROM tenant_users
                WHERE org_id = ?
                ORDER BY created_at DESC
            ");
            $q->execute([(int)$org['id']]);
            $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $this->flash('_err', 'Could not load users list.');
        }

        $this->render('tenant/users/index', [
            'title' => 'Users',
            'rows'  => $rows,
            'slug'  => $slug,
            'org'   => $org,
            'flash' => $this->take('_msg'),
            'error' => $this->take('_err'),
        ]);
    }

    /* ========================= INVITE USER ========================= */
    public function inviteForm(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');
        [$org, $user, $slug] = $this->requireTenant($slug);
        $this->assertOwner($user, $slug);

        $this->render('tenant/users/invite', [
            'title' => 'Invite User',
            'csrf'  => Csrf::token(),
            'slug'  => $slug,
            'org'   => $org,
            'error' => $this->take('_err'),
            'flash' => $this->take('_msg'),
        ]);
    }

    public function sendInvite(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');
        [$org, $user, $slug] = $this->requireTenant($slug);
        $this->assertOwner($user, $slug);

        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired.');
            $this->redirect("/t/{$slug}/users/invite");
        }

        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $roleIn = (string)($_POST['role'] ?? 'employee');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('_err', 'Valid email required.');
            $this->redirect("/t/{$slug}/users/invite");
        }

        $role = in_array($roleIn, ['owner', 'manager', 'employee'], true) ? $roleIn : 'employee';

        try {
            $this->ensureTables();
            $pdo = DB::pdo();

            $q = $pdo->prepare("SELECT 1 FROM tenant_users WHERE org_id=? AND LOWER(email)=LOWER(?) LIMIT 1");
            $q->execute([(int)$org['id'], $email]);
            if ($q->fetch()) {
                $this->flash('_err', 'This email is already a user.');
                $this->redirect("/t/{$slug}/users");
            }

            $token = bin2hex(random_bytes(32));
            $ttl = 72;
            $pdo->prepare("DELETE FROM tenant_user_invites WHERE org_id=? AND LOWER(email)=LOWER(?)")
                ->execute([(int)$org['id'], $email]);

            $ins = $pdo->prepare("
                INSERT INTO tenant_user_invites (org_id,email,role,token,expires_at)
                VALUES (?,?,?,?, NOW() + INTERVAL {$ttl} HOUR)
            ");
            $ins->execute([(int)$org['id'], $email, $role, $token]);

            $link = $this->baseUrl() . "/tenant/invite/accept?token={$token}";
            $subject = 'You are invited to KlinFlow workspace';
            $html = $this->inviteEmailHtml($org['name'] ?? $slug, $link, $ttl);
            $from = 'invites@klinflow.com';

            $sent = false;
            if (class_exists(Mailer::class)) {
                try {
                    $mailer = new Mailer();
                    if (method_exists($mailer, 'send')) {
                        $sent = (bool)$mailer->send([
                            'to' => [$email],
                            'from' => $from,
                            'subject' => $subject,
                            'html' => $html,
                        ]);
                    }
                } catch (\Throwable) { /* fallback below */ }
            }
            if (!$sent) {
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: KlinFlow <{$from}>\r\n";
                @mail($email, $subject, $html, $headers);
            }

            $this->flash('_msg', 'Invite sent.');
        } catch (\Throwable) {
            $this->flash('_err', 'Failed to send invite.');
        }

        $this->redirect("/t/{$slug}/users");
    }

    /* ===================== ACCEPT INVITE (PUBLIC) ===================== */
    public function acceptForm(): void
    {
        $token = $_GET['token'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { echo 'Invalid link.'; return; }

        try {
            $this->ensureTables();
            $pdo = DB::pdo();
            $q = $pdo->prepare("
                SELECT i.id, i.email, i.role, i.org_id, o.slug, o.name
                FROM tenant_user_invites i
                JOIN cp_organizations o ON o.id = i.org_id
                WHERE i.token=? AND i.expires_at>=NOW() AND i.accepted_at IS NULL
                LIMIT 1
            ");
            $q->execute([$token]);
            $row = $q->fetch(\PDO::FETCH_ASSOC);
            if (!$row) { echo 'This invite link is invalid or expired.'; return; }

            $hasTenant = isset($_SESSION['tenant_org']['id']) && (int)$_SESSION['tenant_org']['id'] === (int)$row['org_id'];

            $vars = [
                'title' => 'Accept Invitation',
                'csrf'  => Csrf::token(),
                'token' => $token,
                'email' => $row['email'],
                'org'   => $row['name'] ?? $row['slug'],
                'slug'  => $row['slug'],
            ];

            if ($hasTenant) {
                $this->render('tenant/users/accept', $vars);
            } else {
                View::render('tenant/users/accept', [
                    'layout' => null,
                    'title'  => $vars['title'],
                    'csrf'   => $vars['csrf'],
                    'token'  => $vars['token'],
                    'email'  => $vars['email'],
                    'org'    => $vars['org'],
                ]);
            }
        } catch (\Throwable) {
            echo 'Unexpected error.';
        }
    }

    public function accept(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? '')) { echo 'Session expired.'; return; }
        $token = $_POST['token'] ?? '';
        $name  = trim((string)($_POST['name'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if (!preg_match('/^[a-f0-9]{64}$/', $token) || $name === '' || strlen($pass) < 8) {
            echo 'Invalid input.'; return;
        }

        try {
            $this->ensureTables();
            $pdo = DB::pdo();
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                SELECT * FROM tenant_user_invites
                WHERE token=? AND expires_at>=NOW() AND accepted_at IS NULL
                LIMIT 1
            ");
            $q->execute([$token]);
            $inv = $q->fetch(\PDO::FETCH_ASSOC);
            if (!$inv) { throw new \RuntimeException('Invite not found'); }

            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $emailNorm = strtolower((string)$inv['email']);
            $username  = preg_replace('/[^a-z0-9]+/i', '', explode('@', $emailNorm)[0]) ?: 'user';

            $exists = $pdo->prepare("SELECT 1 FROM tenant_users WHERE org_id=? AND LOWER(email)=LOWER(?) LIMIT 1");
            $exists->execute([(int)$inv['org_id'], $emailNorm]);
            if (!$exists->fetch()) {
                $ins = $pdo->prepare("
                    INSERT INTO tenant_users (org_id, name, email, username, role, password_hash, is_active, created_at)
                    VALUES (?,?,?,?,?,?,1,NOW())
                ");
                $ins->execute([(int)$inv['org_id'], $name, $emailNorm, $username, $inv['role'], $hash]);
            }

            $pdo->prepare("UPDATE tenant_user_invites SET accepted_at=NOW() WHERE id=?")
                ->execute([(int)$inv['id']]);

            $pdo->commit();
            echo 'Account created. <a href="/tenant/login">Sign in</a>';
        } catch (\Throwable) {
            if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
            echo 'Failed to accept invite.';
        }
    }

    /* ========================= Me (optional) ========================= */
    public function profile(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');
        [$org, $user, $slug] = $this->requireTenant($slug);

        $this->render('tenant/me/index', [
            'title' => 'My Profile',
            'slug'  => $slug,
            'user'  => $user,
            'csrf'  => Csrf::token(),
            'flash' => $this->take('_msg'),
            'error' => $this->take('_err'),
        ]);
    }

    public function updateProfile(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');
        [$org, $user, $slug] = $this->requireTenant($slug);

        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired.');
            $this->redirect("/t/{$slug}/users/me");
        }

        $name  = trim((string)($_POST['name']  ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $tz    = trim((string)($_POST['timezone'] ?? ''));

        try {
            $pdo = DB::pdo();
            $u = $pdo->prepare("UPDATE tenant_users SET name=?, mobile=?, updated_at=NOW() WHERE id=? AND org_id=?");
            $u->execute([$name ?: $user['name'], $phone ?: $user['mobile'], (int)$user['id'], (int)$org['id']]);

            if ($tz !== '') { $_SESSION['tenant_org']['timezone'] = $tz; }
            $_SESSION['tenant_user']['name']   = $name ?: $user['name'];
            $_SESSION['tenant_user']['mobile'] = $phone ?: $user['mobile'];

            $this->flash('_msg', 'Profile updated.');
        } catch (\Throwable) {
            $this->flash('_err', 'Failed to update profile.');
        }

        $this->redirect("/t/{$slug}/users/me");
    }

    /* ------------------------------- Email ------------------------------- */
    private function inviteEmailHtml(string $orgName, string $link, int $ttlHours): string
    {
        $o = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');
        $l = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!doctype html><html><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial">
  <h2>You’re invited to {$o} on KlinFlow</h2>
  <p>Click the button below to create your account.</p>
  <p><a href="{$l}" style="background:#228B22;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Accept Invitation</a></p>
  <p>If the button doesn’t work, copy & paste this URL:<br><code>{$l}</code></p>
  <p>This link expires in {$ttlHours} hours.</p>
</body></html>
HTML;
    }
}