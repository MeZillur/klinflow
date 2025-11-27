<?php
namespace Apps\CP\Controllers;

use Shared\View;
use Shared\DB;
use Shared\Csrf;

final class ProfileController
{
    private function me(): array { return $_SESSION['cp_user'] ?? []; }
    private function uid(): int { return (int)($this->me()['id'] ?? 0); }
    private function flash(string $k, string $v){ $_SESSION[$k]=$v; }
    private function take(string $k){ $v=$_SESSION[$k]??null; unset($_SESSION[$k]); return $v; }
    private function redirect(string $p){ header('Location: '.$p, true, 302); exit; }
    private function normMobile(string $m): string { return preg_replace('/\D+/', '', $m); }
    private function validUsername(string $u): bool { return (bool)preg_match('/^[a-z0-9_]{3,32}$/i', $u); }

    public function show(): void
    {
        $id = $this->uid();
        if ($id <= 0) { $this->redirect('/cp/login'); }

        $pdo = DB::pdo();
        $q = $pdo->prepare("SELECT id,name,role,email,username,mobile FROM cp_users WHERE id=? LIMIT 1");
        $q->execute([$id]);
        $u = $q->fetch();

        View::render('cp/profile', [
            'csrf'  => Csrf::token(),
            'u'     => $u,
            'msg'   => $this->take('_msg'),
            'error' => $this->take('_err'),
        ]);
    }

    public function update(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect('/cp/profile');
        if (!Csrf::verify($_POST['_csrf'] ?? '')) { $this->flash('_err','Session expired. Try again.'); $this->redirect('/cp/profile'); }

        $id      = $this->uid();
        if ($id <= 0) $this->redirect('/cp/login');

        $name    = trim((string)($_POST['name'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $usern   = trim((string)($_POST['username'] ?? ''));
        $mobile  = $this->normMobile((string)($_POST['mobile'] ?? ''));
        $curPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $newPass2= (string)($_POST['new_password_confirm'] ?? '');

        if ($name==='' || $email==='') { $this->flash('_err','Name and email are required.'); $this->redirect('/cp/profile'); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->flash('_err','Invalid email.'); $this->redirect('/cp/profile'); }
        if ($usern !== '' && !$this->validUsername($usern)) { $this->flash('_err','Username must be 3–32 letters/numbers/_ only.'); $this->redirect('/cp/profile'); }
        if ($mobile !== '' && (strlen($mobile) < 8 || strlen($mobile) > 15)) { $this->flash('_err','Mobile must be 8–15 digits.'); $this->redirect('/cp/profile'); }

        $pdo = DB::pdo();

        // Uniqueness checks (exclude myself)
        $chk = $pdo->prepare("SELECT id FROM cp_users
                              WHERE (email=? OR (username IS NOT NULL AND username=?) OR (mobile IS NOT NULL AND mobile=?))
                              AND id<>? LIMIT 1");
        $chk->execute([$email, $usern ?: null, $mobile ?: null, $id]);
        if ($chk->fetch()) { $this->flash('_err','Email/Username/Mobile already in use by another user.'); $this->redirect('/cp/profile'); }

        // Load current for password check if needed
        $cur = $pdo->prepare("SELECT password_hash FROM cp_users WHERE id=? LIMIT 1");
        $cur->execute([$id]);
        $row = $cur->fetch();

        $fields = ['name=?','email=?','username=?','mobile=?'];
        $args   = [$name, $email, $usern ?: null, $mobile ?: null];

        // Password change (optional) — require current password
        if ($newPass !== '' || $newPass2 !== '') {
            if ($newPass !== $newPass2) { $this->flash('_err','New passwords do not match.'); $this->redirect('/cp/profile'); }
            if (strlen($newPass) < 8) { $this->flash('_err','New password must be at least 8 characters.'); $this->redirect('/cp/profile'); }
            if (empty($curPass) || !$row || !password_verify($curPass, $row['password_hash'])) {
                $this->flash('_err','Current password is incorrect.');
                $this->redirect('/cp/profile');
            }
            $fields[] = 'password_hash=?';
            $args[]   = password_hash($newPass, PASSWORD_BCRYPT);
        }

        $sql = "UPDATE cp_users SET ".implode(',', $fields).", updated_at=NOW() WHERE id=? LIMIT 1";
        $args[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);

        // Refresh session cache (name/email)
        $_SESSION['cp_user']['name']  = $name;
        $_SESSION['cp_user']['email'] = $email;

        $this->flash('_msg','Profile updated successfully.');
        $this->redirect('/cp/profile');
    }
}