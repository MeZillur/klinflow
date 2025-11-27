<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use Shared\DB;
use Shared\Csrf;
use Shared\SessionBootstrap;
use App\Services\ModuleAccess;

final class AuthController
{
    /** ===== Tunables ===== */
    private const MAX_ATTEMPTS = 6;
    private const WINDOW_MIN   = 10;

    private const REMEMBER_COOKIE_NAME = 'tn_remember';
    private const REMEMBER_COOKIE_DAYS = 30;
    private const REMEMBER_COOKIE_SALT = 'tenant-remember-v1';

    private const CSRF_NS = 'tenant'; // isolate tenant CSRF from control panel

    /** ===== Utilities ===== */
    private function ensureSession(): void
    {
        // Idempotent + safe; will not emit warnings if session already active.
        SessionBootstrap::ensureStarted();
    }

    private function flash(string $k, string $v): void { $this->ensureSession(); $_SESSION[$k] = $v; }
    private function take(?string $k): ?string { $this->ensureSession(); $v = $_SESSION[$k] ?? null; if ($k) unset($_SESSION[$k]); return $v; }
    private function redirect(string $to, int $code = 302): void { header('Location: '.$to, true, $code); exit; }
    private function now(): int { return time(); }
    private function normalizeMobile(string $s): string { return preg_replace('/\D+/', '', $s); }
    private function isHttps(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
    private function sendNoCache(): void {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    private function cookieDomain(): ?string
    {
        // read from .env via getenv; return null for host-only
        $d = (string)(getenv('COOKIE_DOMAIN') ?: '');
        $d = trim($d);
        return $d === '' ? null : $d; // e.g. ".klinflow.com"
    }

    private function renderLogin(array $locals = []): void
    {
        $file = dirname(__DIR__, 3).'/apps/Tenant/Views/tenant/login.php';
        if (!is_file($file)) { http_response_code(500); echo 'Tenant login view missing.'; return; }
        extract($locals, EXTR_SKIP);
        include $file;
    }

    /** ===== App key & remember-me helpers ===== */
    private function appKey(): string
    {
        $k = (string)(getenv('APP_KEY') ?: '');
        if ($k === '') return hash('sha256', __FILE__.$_SERVER['SERVER_NAME'].$_SERVER['DOCUMENT_ROOT'].self::REMEMBER_COOKIE_SALT, true);
        if (str_starts_with($k, 'base64:')) {
            $d = base64_decode(substr($k, 7), true);
            if ($d !== false) $k = $d;
        }
        return $k;
    }
    private function b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
    private function b64urlDecode(string $s): ?string {
        $pad = strlen($s) % 4; if ($pad) $s .= str_repeat('=', 4 - $pad);
        $dec = base64_decode(strtr($s, '-_', '+/'), true);
        return $dec === false ? null : $dec;
    }
    private function makeRememberCookieValue(int $userId, string $orgSlug, int $ttlDays=self::REMEMBER_COOKIE_DAYS): string
    {
        $payload = ['uid'=>$userId,'org'=>$orgSlug,'exp'=>$this->now()+$ttlDays*86400];
        $b   = $this->b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $mac = hash_hmac('sha256', self::REMEMBER_COOKIE_SALT.'.'.$b, $this->appKey(), true);
        return $b.'.'.$this->b64url($mac);
    }
    private function parseRememberCookie(?string $cookie): ?array
    {
        if (!$cookie || !str_contains($cookie, '.')) return null;
        [$b, $sig] = explode('.', $cookie, 2);
        $expMac = hash_hmac('sha256', self::REMEMBER_COOKIE_SALT.'.'.$b, $this->appKey(), true);
        $gotMac = $this->b64urlDecode($sig);
        if ($gotMac === null || !hash_equals($expMac, $gotMac)) return null;
        $json = $this->b64urlDecode($b); if ($json === null) return null;
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['uid']) || empty($data['org']) || empty($data['exp'])) return null;
        if ((int)$data['exp'] < $this->now()) return null;
        return ['uid'=>(int)$data['uid'],'org'=>(string)$data['org']];
    }
    private function setRememberCookie(string $value): void
    {
        $params = [
            'expires'  => $this->now()+self::REMEMBER_COOKIE_DAYS*86400,
            'path'     => '/',
            'secure'   => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($this->cookieDomain()) $params['domain'] = $this->cookieDomain();
        setcookie(self::REMEMBER_COOKIE_NAME, $value, $params);
    }
    private function clearRememberCookie(): void
    {
        $params = [
            'expires'  => $this->now()-3600,
            'path'     => '/',
            'secure'   => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($this->cookieDomain()) $params['domain'] = $this->cookieDomain();
        setcookie(self::REMEMBER_COOKIE_NAME, '', $params);
    }

    /** ===== CSRF helpers ===== */
    private function pullPostedCsrf(): string
    {
        // Accept common names/headers to play nice with any middleware
        $p = $_POST['_csrf'] ?? $_POST['_token'] ?? '';
        if ($p === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) $p = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        if ($p === '' && isset($_SERVER['HTTP_X_XSRF_TOKEN'])) $p = (string)$_SERVER['HTTP_X_XSRF_TOKEN'];
        return (string)$p;
    }

    private function issueXsrfCookies(string $token): void
    {
        // Prefer library cookie (namespaced), but also set a plain cookie for global guards.
        // Both are SameSite=Lax and domain-wide if COOKIE_DOMAIN is set.
        $domain = $this->cookieDomain();

        // If the Csrf helper supports domain-aware issuing, use it:
        try {
            if (method_exists(Cmsf::class ?? '', 'issueCookie')) {
                // typo guard: in case of autoload oddities, the manual path below still runs
                Csrf::issueCookie(self::CSRF_NS, $domain);
            } else {
                // fall through to manual cookies
                throw new \RuntimeException('manual');
            }
        } catch (\Throwable) {
            $base = [
                'expires'  => time()+3600,
                'path'     => '/',
                'secure'   => $this->isHttps(),
                'httponly' => false,
                'samesite' => 'Lax',
            ];
            if ($domain) $base['domain'] = $domain;

            // 1) Namespaced cookie many apps expect
            setcookie('XSRF-TOKEN-'.self::CSRF_NS, $token, $base);
            // 2) Generic cookie many global middlewares expect
            setcookie('XSRF-TOKEN', $token, $base);
        }
    }

    private function cookieTokens(): array
    {
        // Read multiple cookie names for compatibility with any global middleware.
        return array_filter([
            (string)($_COOKIE['XSRF-TOKEN-'.self::CSRF_NS] ?? ''),
            (string)($_COOKIE['XSRF-TOKEN'] ?? ''),
            // If your Csrf helper set a specific namespaced name internally:
            (string)(method_exists(Csrf::class, 'cookieToken') ? Csrf::cookieToken(self::CSRF_NS) : ''),
        ], static fn($v) => $v !== '');
    }

    /** ================== ROUTES ================== */

    /** GET /tenant/login */
    public function showLogin(): void
    {
        $this->ensureSession();
        $this->sendNoCache();

        // Already logged in → send to default module
        if (!empty($_SESSION['tenant_user']) && !empty($_SESSION['tenant_org']['slug'])) {
            $slug = (string)$_SESSION['tenant_org']['slug'];
            $def  = ModuleAccess::defaultModuleFor($slug);
            $this->redirect($def ? "/t/{$slug}/{$def}" : "/t/{$slug}");
        }

        // Remember-me auto-login (org-scoped)
        if (!empty($_COOKIE[self::REMEMBER_COOKIE_NAME])) {
            $parsed = $this->parseRememberCookie($_COOKIE[self::REMEMBER_COOKIE_NAME]);
            if ($parsed) {
                $pdo = DB::pdo();
                $orgQ = $pdo->prepare("SELECT id,slug,name,plan,status,trial_end FROM cp_organizations WHERE slug=? LIMIT 1");
                $orgQ->execute([$parsed['org']]);
                $org = $orgQ->fetch(\PDO::FETCH_ASSOC);

                if ($org && in_array($org['status'], ['active','trial'], true)) {
                    $trialOk = (($org['plan'] ?? '') !== 'trial')
                            || empty($org['trial_end'])
                            || strtotime((string)$org['trial_end']) >= $this->now();

                    if ($trialOk) {
                        $uQ = $pdo->prepare("SELECT * FROM tenant_users WHERE id=? AND org_id=? AND is_active=1 LIMIT 1");
                        $uQ->execute([$parsed['uid'], $org['id']]);
                        $user = $uQ->fetch(\PDO::FETCH_ASSOC);

                        if ($user) {
                            unset($user['password_hash']);
                            $_SESSION['tenant_user'] = $user;
                            $_SESSION['tenant_org']  = ['id'=>$org['id'],'slug'=>$org['slug'],'name'=>$org['name']];
                            $this->setRememberCookie($this->makeRememberCookieValue((int)$user['id'], (string)$org['slug']));
                            $def = ModuleAccess::defaultModuleFor((string)$org['slug']);
                            $this->redirect($def ? "/t/{$org['slug']}/{$def}" : "/t/{$org['slug']}");
                        }
                    }
                }
            }
            $this->clearRememberCookie();
        }

        // Generate namespaced CSRF and issue cookie copies (domain-aware; resilient to session restarts)
        $token = Csrf::token(self::CSRF_NS);
        $this->issueXsrfCookies($token);

        $this->renderLogin([
            'csrf'       => $token,           // your view should put this into hidden _csrf and _token
            'csrfName'   => '_csrf',
            'error'      => $this->take('_err'),
            'brandColor' => '#228B22',
            'logoPath'   => '/assets/brand/logo.png',
        ]);
    }

    /** POST /tenant/login */
    public function login(): void
    {
        $this->ensureSession();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/tenant/login');
        }

        // Accept either session-verified CSRF OR any double-submit cookie match for the login endpoint
        $posted = $this->pullPostedCsrf();
        $okSession = ($posted !== '' && Csrf::verify($posted, self::CSRF_NS));

        $okCookie = false;
        if ($posted !== '') {
            foreach ($this->cookieTokens() as $c) {
                if ($c !== '' && hash_equals($c, $posted)) { $okCookie = true; break; }
            }
        }

        if (!$okCookie && !$okSession) {
            $this->flash('_err','Session expired or CSRF failed. Please try again.');
            $this->redirect('/tenant/login');
        }

        $identity = trim((string)($_POST['identity'] ?? ''));
        $pass     = (string)($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);
        if ($identity === '' || $pass === '') {
            $this->flash('_err','All fields are required.');
            $this->redirect('/tenant/login');
        }

        $pdo = DB::pdo();

        // Throttling (per identity + IP)
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_login_attempts (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              ip VARBINARY(16) NULL,
              identity VARCHAR(190) NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_ip (ip), INDEX idx_identity (identity), INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $cut = (new \DateTimeImmutable('-'.self::WINDOW_MIN.' minutes'))->format('Y-m-d H:i:s');

        $thQ = $pdo->prepare(
            "SELECT COUNT(*) FROM tenant_login_attempts
             WHERE identity=? AND created_at>=? AND (ip=INET6_ATON(?) OR ip IS NULL)"
        );
        $thQ->execute([$identity, $cut, $ip]);
        if ((int)$thQ->fetchColumn() >= self::MAX_ATTEMPTS) {
            $this->flash('_err','Too many attempts. Please try again later.');
            $this->redirect('/tenant/login');
        }
        $pdo->prepare("INSERT INTO tenant_login_attempts (ip,identity) VALUES (INET6_ATON(?),?)")
            ->execute([$ip,$identity]);

        // Fetch user + org (strict org scoping)
        $sql = "
            SELECT 
                tu.*,
                o.id   AS org_id,
                o.slug AS org_slug,
                o.name AS org_name,
                o.plan,
                o.status,
                o.trial_end
            FROM tenant_users tu
            JOIN cp_organizations o ON o.id = tu.org_id
            WHERE tu.is_active = 1
              AND o.status IN ('active','trial')
              AND (
                    tu.email    = :id_email
                 OR tu.username = :id_user
                 OR tu.mobile   = :mobile
              )
            ORDER BY tu.id DESC
            LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':id_email' => $identity,
            ':id_user'  => $identity,
            ':mobile'   => $this->normalizeMobile($identity),
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$row) { $this->flash('_err','Invalid credentials.'); $this->redirect('/tenant/login'); }

        if (($row['plan'] ?? '') === 'trial'
            && !empty($row['trial_end'])
            && strtotime((string)$row['trial_end']) < $this->now()) {
            $this->flash('_err','This organization’s trial has ended.');
            $this->redirect('/tenant/login');
        }

        if (!password_verify($pass, (string)($row['password_hash'] ?? ''))) {
            $this->flash('_err','Invalid credentials.');
            $this->redirect('/tenant/login');
        }

        // Success → rotate session ID to prevent fixation
        session_regenerate_id(true);

        // Write tenant-scoped session
        $user = $row;
        unset($user['password_hash'], $user['plan'], $user['status'], $user['trial_end']);
        $_SESSION['tenant_user'] = $user;
        $_SESSION['tenant_org']  = [
            'id'   => (int)$row['org_id'],
            'slug' => (string)$row['org_slug'],
            'name' => (string)$row['org_name'],
        ];

        if ($remember && !empty($row['org_slug'])) {
            $this->setRememberCookie($this->makeRememberCookieValue((int)$row['id'], (string)$row['org_slug']));
        }

        // Rotate session CSRF after auth; cookie token will be refreshed on next GET
        Csrf::rotate(self::CSRF_NS);

        $def = ModuleAccess::defaultModuleFor((string)$row['org_slug']);
        $this->redirect($def ? "/t/{$row['org_slug']}/{$def}" : "/t/{$row['org_slug']}");
    }

    /** POST /tenant/logout */
    public function logout(): void
    {
        $this->ensureSession();
        unset($_SESSION['tenant_user'], $_SESSION['tenant_org']);
        $this->clearRememberCookie();
        Csrf::rotate(self::CSRF_NS);
        $this->redirect('/tenant/login');
    }
}