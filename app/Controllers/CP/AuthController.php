<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\View;
use Shared\DB;
use Shared\Csrf;
use Shared\AuthRemember;

final class AuthController
{
    private const MAX_ATTEMPTS = 5;   // attempts per WINDOW_MIN for same ip+email
    private const WINDOW_MIN   = 10;  // minutes

    private function flash(string $k, string $v): void { $_SESSION[$k] = $v; }
    private function take(string $k): ?string { $v = $_SESSION[$k] ?? null; unset($_SESSION[$k]); return $v; }
    private function redirect(string $p): void { header('Location: '.$p, true, 302); exit; }

    /** GET /cp/login — no shell to prevent “double shell” */
    public function showLogin(): void
    {
        if (!empty($_SESSION['cp_user'])) $this->redirect('/cp/dashboard');

        View::render('cp/login', [
            'layout' => false,                 // prevent global shell here
            'scope'  => 'cp',
            'title'  => 'Control Panel Login',
            'csrf'   => Csrf::token(),
            'error'  => $this->take('_err'),
        ]);
    }

    /** POST /cp/login */
    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect('/cp/login');
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Your session expired. Please try again.');
            $this->redirect('/cp/login');
        }

        $login = trim((string)($_POST['login'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($login === '' || $pass === '') {
            $this->flash('_err', 'Email/Username/Mobile and password are required.');
            $this->redirect('/cp/login');
        }

        $pdo = DB::pdo();

        // Ensure cp_login_attempts exists (matches your schema dump)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cp_login_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                ip_address VARBINARY(16) DEFAULT NULL,
                attempted_at DATETIME NOT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cp_attempts_email_time (email, attempted_at),
                KEY idx_cp_attempts_ip_time (ip_address, attempted_at),
                KEY idx_created (created_at),
                KEY idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Throttle window for this ip + “email” field (we store whatever was typed)
        $ipStr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ipBin = @inet_pton($ipStr) ?: null;
        $window = (int) self::WINDOW_MIN;

        $q = $pdo->prepare("
            SELECT COUNT(*) FROM cp_login_attempts
             WHERE email = ?
               AND (ip_address <=> ?)
               AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)
        ");
        $q->execute([$login, $ipBin]);
        if ((int)$q->fetchColumn() >= self::MAX_ATTEMPTS) {
            $this->flash('_err', 'Too many attempts. Please wait a few minutes and try again.');
            $this->redirect('/cp/login');
        }

        // Look up user by email, mobile (digits only), or username
        $user = null;

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT id,name,role,email,username,mobile,password_hash,is_active FROM cp_users WHERE email=? LIMIT 1");
            $stmt->execute([$login]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        } else {
            $digits = preg_replace('/\D+/', '', $login);
            if ($digits !== '') {
                $stmt = $pdo->prepare("SELECT id,name,role,email,username,mobile,password_hash,is_active FROM cp_users WHERE mobile=? LIMIT 1");
                $stmt->execute([$digits]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
            if (!$user) {
                $stmt = $pdo->prepare("SELECT id,name,role,email,username,mobile,password_hash,is_active FROM cp_users WHERE username=? LIMIT 1");
                $stmt->execute([$login]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
        }

        $ok = $user && (int)$user['is_active'] === 1 && password_verify($pass, (string)$user['password_hash']);

        // Log attempt either way
        $ins = $pdo->prepare("INSERT INTO cp_login_attempts (email, ip_address, attempted_at, success) VALUES (?, ?, NOW(), ?)");
        $ins->execute([$login, $ipBin, $ok ? 1 : 0]);

        if (!$ok) {
            $this->flash('_err', 'Invalid credentials.');
            $this->redirect('/cp/login');
        }

        // Successful login
        session_regenerate_id(true);
        $_SESSION['cp_user'] = [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['name'],
            'role'  => (string)$user['role'],
            'email' => (string)$user['email'],
        ];

        // Update last_login_at
        $pdo->prepare("UPDATE cp_users SET last_login_at = NOW() WHERE id = ? LIMIT 1")
            ->execute([(int)$user['id']]);

        // Rotate CSRF post-login
        unset($_SESSION['_csrf']);
        Csrf::token();

        // Remember me
        if (!empty($_POST['remember'])) {
            AuthRemember::issue((int)$user['id']);           // requires cp_remember_tokens
        } else {
            AuthRemember::clearAllForUser((int)$user['id']); // safe even if none exist
        }

        $this->redirect('/cp/dashboard');
    }

    /** POST /cp/logout */
    public function logout(): void
    {
        // revoke persistent cookie first
        AuthRemember::clearCurrent();
        if (!empty($_SESSION['cp_user']['id'])) {
            AuthRemember::clearAllForUser((int)$_SESSION['cp_user']['id']);
        }

        // clear session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', isset($_SERVER['HTTPS']), true);
        }
        session_destroy();

        header('Location: /', true, 302);
        exit;
    }
}