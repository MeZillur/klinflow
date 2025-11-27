<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\DB;
use Shared\Csrf;
use Shared\Mailer;

final class PasswordController
{
    private function ttlMinutes(): int {
        $m = (int)(getenv('RESET_TTL_MIN') ?: 60);
        return max(10, min(1440, $m));
    }

    private function flash(string $k, string $v): void { $_SESSION[$k] = $v; }
    private function take(string $k): ?string { $v = $_SESSION[$k] ?? null; unset($_SESSION[$k]); return $v; }
    private function redirect(string $p): void { header('Location: '.$p, true, 302); exit; }

    private function baseUrl(): string {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private function render(string $name, array $data = []): void {
        if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 3));
        $name = ltrim($name, "/\\");
        if (str_starts_with($name, 'cp/')) $name = substr($name, 3);
        $file = BASE_PATH . '/apps/CP/Views/cp/' . $name . '.php';
        if (!is_file($file)) { http_response_code(500); echo "View not found at: apps/CP/Views/cp/{$name}.php"; return; }
        extract($data, EXTR_SKIP);
        include $file;
    }

    public function showForgot(): void {
        if (!empty($_SESSION['cp_user'])) $this->redirect('/cp/dashboard');
        $this->render('forgot', [
            'csrf'  => Csrf::token(),
            'msg'   => $this->take('_msg'),
            'error' => $this->take('_err')
        ]);
    }

    public function sendReset(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect('/cp/forgot');
        if (!Csrf::verify($_POST['_csrf'] ?? '')) { $this->flash('_err','Session expired. Please try again.'); $this->redirect('/cp/forgot'); }

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email !== '') {
            $pdo = DB::pdo();

            // Store tokens (idempotent)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cp_password_resets (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(190) NOT NULL,
                    token CHAR(64) NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 60 MINUTE),
                    UNIQUE KEY token (token),
                    INDEX email (email),
                    INDEX created_at (created_at),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Only send to active CP users
            $q = $pdo->prepare("SELECT id,is_active FROM cp_users WHERE email=? LIMIT 1");
            $q->execute([$email]);
            $u = $q->fetch();

            if ($u && (int)$u['is_active'] === 1) {
                $ttl = $this->ttlMinutes();
                $tok = bin2hex(random_bytes(32));

                // Replace any prior token for this email
                $pdo->prepare("DELETE FROM cp_password_resets WHERE email=?")->execute([$email]);
                $pdo->prepare("
                    INSERT INTO cp_password_resets (email, token, expires_at)
                    VALUES (?, ?, NOW() + INTERVAL {$ttl} MINUTE)
                ")->execute([$email, $tok]);

                $link    = $this->baseUrl()."/cp/reset?token=".$tok;
                $subject = 'KlinFlow CP — Password Reset';
                $brand   = '#228B22';
                $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
  <body style="margin:0;padding:0;background:#f7f9fb">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f9fb">
      <tr>
        <td align="center" style="padding:32px 16px">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(15,23,42,0.08);font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif">
            <tr>
              <td style="background:{$brand};padding:20px 24px;color:#fff">
                <div style="display:flex;align-items:center;gap:10px">
                  <img src="{$this->baseUrl()}/assets/brand/logo.png" alt="KlinFlow" height="24" style="display:block"/>
                  <div style="font-weight:700;font-size:16px;letter-spacing:.2px">KlinFlow</div>
                </div>
              </td>
            </tr>
            <tr>
              <td style="padding:28px 24px 10px 24px">
                <h2 style="margin:0 0 8px 0;font-size:20px;color:#0f172a;font-weight:700">Password reset</h2>
                <p style="margin:0 0 12px 0;color:#334155;font-size:14px;line-height:1.6">
                  You requested a password reset for your <strong>KlinFlow Control Panel</strong> account.
                </p>
                <p style="margin:0 0 16px 0;color:#334155;font-size:14px;line-height:1.6">
                  <strong>This link will expire in {$ttl} minutes.</strong>
                </p>
              </td>
            </tr>
            <tr>
              <td style="padding:0 24px 24px 24px">
                <a href="{$link}" style="background:{$brand};color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;display:inline-block;font-weight:600">
                  Reset your password
                </a>
                <div style="margin-top:16px;font-size:12px;color:#64748b">
                  Or paste this link into your browser:<br>
                  <span style="word-break:break-all;color:#0f172a">{$link}</span>
                </div>
              </td>
            </tr>
            <tr>
              <td style="padding:0 24px 28px 24px;color:#64748b;font-size:12px;line-height:1.6">
                If you didn’t request this, you can safely ignore this email.
              </td>
            </tr>
            <tr>
              <td style="background:#f8fafc;padding:16px 24px;color:#64748b;font-size:12px">
                Sent from <strong>KlinFlow CP</strong> • <a href="{$this->baseUrl()}" style="color:#0f172a;text-decoration:none">klinflow.com</a>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

                // EXACT sender identity (Resend verified)
                $from    = 'KlinFlow Password Reset <pass-reset@mail.klinflow.com>';
                $replyTo = 'support@mail.klinflow.com';
                $sent    = false;

                // Prefer your Shared\Mailer (Resend driver). Try array payload first.
                if (class_exists(Mailer::class)) {
                    try {
                        $mailer = new Mailer();

                        // Preferred payload (new Mailer): array with meta
                        if (method_exists($mailer, 'send')) {
                            $sent = (bool)$mailer->send([
                                'type'      => 'pass_reset', // lets Mailer pick the right sender if configured
                                'from'      => $from,
                                'reply_to'  => $replyTo,
                                'to'        => [$email],
                                'subject'   => $subject,
                                'html'      => $html,
                                'tags'      => ['event' => 'cp_password_reset'],
                            ]);
                        }

                        // Legacy static signature fallback
                        if (!$sent && method_exists(Mailer::class, 'send')) {
                            /** @phpstan-ignore-next-line */
                            $sent = (bool)Mailer::send($email, $subject, $html, $from);
                        }
                    } catch (\Throwable $e) {
                        error_log('Password reset mailer error: '.$e->getMessage());
                        $sent = false;
                    }
                }

                // Last-resort fallback
                if (!$sent) {
                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: {$from}\r\n";
                    $headers .= "Reply-To: {$replyTo}\r\n";
                    @mail($email, $subject, $html, $headers);
                }
            }
        }

        $this->flash('_msg','If that email exists, a password reset link has been sent from pass-reset@mail.klinflow.com.');
        $this->redirect('/cp/forgot');
    }

    public function showReset(): void {
        if (!empty($_SESSION['cp_user'])) $this->redirect('/cp/dashboard');
        $token = $_GET['token'] ?? '';
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->flash('_err','Invalid or expired token.'); $this->redirect('/cp/forgot');
        }
        $pdo = DB::pdo();
        $q = $pdo->prepare("SELECT email FROM cp_password_resets WHERE token=? AND expires_at >= NOW() LIMIT 1");
        $q->execute([$token]);
        if (!$q->fetch()) { $this->flash('_err','Token expired. Please request a new one.'); $this->redirect('/cp/forgot'); }
        $this->render('reset', ['csrf'=>Csrf::token(),'token'=>$token,'error'=>$this->take('_err')]);
    }

    public function doReset(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect('/cp/login');
        if (!Csrf::verify($_POST['_csrf'] ?? '')) { $this->flash('_err','Session expired. Please try again.'); $this->redirect('/cp/login'); }

        $token = $_POST['token'] ?? '';
        $pass  = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password_confirm'] ?? '');

        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) { $this->flash('_err','Invalid or expired token.'); $this->redirect('/cp/login'); }
        if ($pass === '' || $pass2 === '') { $this->flash('_err','All fields are required.'); $this->redirect('/cp/reset?token='.$token); }
        if ($pass !== $pass2) { $this->flash('_err','Passwords do not match.'); $this->redirect('/cp/reset?token='.$token); }
        if (strlen($pass) < 8) { $this->flash('_err','Password must be at least 8 characters.'); $this->redirect('/cp/reset?token='.$token); }

        $pdo = DB::pdo();
        $q   = $pdo->prepare("SELECT email FROM cp_password_resets WHERE token=? AND expires_at >= NOW() LIMIT 1");
        $q->execute([$token]);
        $row = $q->fetch();
        if (!$row) { $this->flash('_err','Token expired. Please request a new one.'); $this->redirect('/cp/forgot'); }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE cp_users SET password_hash=?, updated_at=NOW() WHERE email=? LIMIT 1")
            ->execute([$hash, $row['email']]);
        $pdo->prepare("DELETE FROM cp_password_resets WHERE email=?")->execute([$row['email']]);

        $this->flash('_err','Password updated. Please sign in.');
        $this->redirect('/cp/login');
    }
}