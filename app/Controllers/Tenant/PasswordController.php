<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use Shared\DB;
use Shared\Csrf;

final class PasswordController
{
    /* --------------------------- helpers --------------------------- */
    private function ttlMinutes(): int {
        $m = (int)(getenv('TENANT_RESET_TTL_MIN') ?: 60);
        return max(10, min(1440, $m));
    }
    private function flash(string $k, string $v): void { $_SESSION[$k] = $v; }
    private function take(string $k): ?string { $v = $_SESSION[$k] ?? null; unset($_SESSION[$k]); return $v; }
    private function redirect(string $to): void { header('Location: '.$to, true, 302); exit; }

    private function baseUrl(): string {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /** Render raw tenant auth view by name (no shell) */
    private function render(string $name, array $data = []): void {
        $file = dirname(__DIR__, 3).'/apps/Tenant/Views/tenant/'.$name.'.php';
        if (!is_file($file)) { http_response_code(500); echo "Tenant view missing: {$name}"; return; }
        extract($data, EXTR_SKIP);
        include $file;
    }

    /** Simple Resend sender with graceful fallback + logging */
    private function sendEmail(array $to, string $subject, string $html, ?string $fromEmail=null, ?string $fromName=null): bool
    {
        $driver    = strtolower((string)(getenv('MAIL_DRIVER') ?: ''));
        $apiKey    = (string)(getenv('RESEND_API_KEY') ?: '');
        $fromEmail = $fromEmail ?: (getenv('MAIL_SENDER_PASSRESET') ?: getenv('MAIL_FROM_EMAIL') ?: 'no-reply@localhost');
        $fromName  = $fromName  ?: (getenv('MAIL_FROM_NAME') ?: 'KlinFlow');

        $fromHeader = sprintf('%s <%s>', $fromName, $fromEmail);

        // Try Resend first if configured
        if ($driver === 'resend' && $apiKey !== '') {
            $payload = json_encode([
                'from'    => $fromHeader,
                'to'      => array_values($to),
                'subject' => $subject,
                'html'    => $html,
            ], JSON_UNESCAPED_SLASHES);

            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer '.$apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 20,
            ]);
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                return true;
            }

            // Log failure and continue to fallback
            $this->logMail('RESEND_FAIL', $code, $err ?: $resp);
        }

        // Fallback: native mail() — better than nothing
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromHeader}\r\n";
        $ok = @mail($to[0], $subject, $html, $headers);
        if (!$ok) $this->logMail('MAIL_FALLBACK_FAIL', 0, 'mail() returned false');
        return $ok;
    }

    private function logMail(string $tag, int $code, string $msg): void
    {
        $logDir = dirname(__DIR__, 3).'/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
        $line = sprintf("%s [%s] code=%d %s\n", date('c'), $tag, $code, $msg);
        @file_put_contents($logDir.'/mail.log', $line, FILE_APPEND);
    }

    /* --------------------------- views --------------------------- */

    /** GET /tenant/forgot */
    public function showForgot(): void {
        $this->render('forgot', [
            'csrf'  => Csrf::token(),
            'msg'   => $this->take('_msg'),
            'error' => $this->take('_err'),
        ]);
    }

    /** POST /tenant/forgot (email/username/mobile in `identity`) */
    public function sendReset(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect('/tenant/forgot');
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired. Please try again.');
            $this->redirect('/tenant/forgot');
        }

        $identity   = trim((string)($_POST['identity'] ?? ''));
        if ($identity === '') {
            $this->flash('_err', 'Email is required.');
            $this->redirect('/tenant/forgot');
        }
        $normMobile = preg_replace('/\D+/', '', $identity);

        $pdo = DB::pdo();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_password_resets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id INT UNSIGNED NOT NULL,
                email  VARCHAR(190) NOT NULL,
                token  CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token (token),
                KEY idx_org (org_id),
                KEY idx_email (email),
                KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $find = $pdo->prepare("
            SELECT 
                tu.org_id,
                tu.email  AS user_email,
                o.slug,
                o.name    AS org_name
            FROM tenant_users tu
            INNER JOIN cp_organizations o ON o.id = tu.org_id
            WHERE tu.is_active = 1
              AND o.status IN ('active','trial')
              AND (
                    tu.email    = :id_email
                 OR tu.username = :id_user
                 OR tu.mobile   = :id_mobile
              )
            ORDER BY tu.id DESC
            LIMIT 1
        ");
        $find->execute([
            ':id_email'  => $identity,
            ':id_user'   => $identity,
            ':id_mobile' => $normMobile,
        ]);
        $row = $find->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $orgId   = (int)$row['org_id'];
            $toEmail = (string)$row['user_email'];
            $orgName = (string)$row['org_name'];

            $ttl = $this->ttlMinutes();
            $tok = bin2hex(random_bytes(32));

            $pdo->prepare("DELETE FROM tenant_password_resets WHERE org_id=? AND email=?")
                ->execute([$orgId, $toEmail]);

            $pdo->prepare("
                INSERT INTO tenant_password_resets (org_id, email, token, expires_at)
                VALUES (?, ?, ?, NOW() + INTERVAL {$ttl} MINUTE)
            ")->execute([$orgId, $toEmail, $tok]);

            $link    = $this->baseUrl()."/tenant/reset?token={$tok}";
            $subject = "KlinFlow — Tenant Password Reset";
            $safeOrg = htmlspecialchars($orgName, ENT_QUOTES);
            $html    = "
                <p>Hello,</p>
                <p>We received a request to reset your tenant password for <strong>{$safeOrg}</strong>.</p>
                <p><a href=\"{$link}\" style=\"background:#228B22;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none\">Reset your password</a></p>
                <p>This link expires in {$ttl} minutes.</p>
                <p>If you didn’t request this, you can safely ignore this email.</p>
            ";

            // 🔌 Send via Resend (or fallback)
            $this->sendEmail([$toEmail], $subject, $html);
        }

        $this->flash('_msg', 'If the email matches an active account, a reset link has been sent.');
        $this->redirect('/tenant/forgot');
    }

    /** GET /tenant/reset?token=... */
    public function showReset(): void {
        $token = $_GET['token'] ?? '';
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->flash('_err', 'Invalid or expired token.');
            $this->redirect('/tenant/forgot');
        }

        $pdo = DB::pdo();
        $q = $pdo->prepare("
            SELECT org_id, email FROM tenant_password_resets
            WHERE token=? AND expires_at >= NOW()
            LIMIT 1
        ");
        $q->execute([$token]);
        $row = $q->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $this->flash('_err', 'Token expired. Please request again.');
            $this->redirect('/tenant/forgot');
        }

        $this->render('reset', [
            'csrf'  => Csrf::token(),
            'token' => $token,
            'error' => $this->take('_err'),
        ]);
    }

    /** POST /tenant/reset */
    public function doReset(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect('/tenant/login');
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired. Please try again.');
            $this->redirect('/tenant/login');
        }

        $token = $_POST['token'] ?? '';
        $pass  = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password_confirm'] ?? '');

        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->flash('_err', 'Invalid or expired token.');
            $this->redirect('/tenant/forgot');
        }
        if ($pass === '' || $pass2 === '') {
            $this->flash('_err', 'All fields are required.');
            $this->redirect('/tenant/reset?token='.$token);
        }
        if ($pass !== $pass2) {
            $this->flash('_err', 'Passwords do not match.');
            $this->redirect('/tenant/reset?token='.$token);
        }
        if (strlen($pass) < 8) {
            $this->flash('_err', 'Password must be at least 8 characters.');
            $this->redirect('/tenant/reset?token='.$token);
        }

        $pdo = DB::pdo();
        $q = $pdo->prepare("
            SELECT org_id, email FROM tenant_password_resets
            WHERE token=? AND expires_at >= NOW()
            LIMIT 1
        ");
        $q->execute([$token]);
        $row = $q->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $this->flash('_err', 'Token expired. Please request again.');
            $this->redirect('/tenant/forgot');
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("
            UPDATE tenant_users 
            SET password_hash=?, updated_at=NOW()
            WHERE org_id=? AND email=? LIMIT 1
        ");
        $upd->execute([$hash, (int)$row['org_id'], $row['email']]);

        $pdo->prepare("DELETE FROM tenant_password_resets WHERE org_id=? AND email=?")
            ->execute([(int)$row['org_id'], $row['email']]);

        $this->flash('_msg', 'Password updated. You can now sign in.');
        $this->redirect('/tenant/login');
    }
}