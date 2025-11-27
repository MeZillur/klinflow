<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\DB;
use Shared\View;
use Shared\Csrf;
use App\Services\Validation;

final class UsersController
{
    /* ------------------------- small helpers ------------------------- */
    private function flash(string $k, string $v): void { $_SESSION[$k] = $v; }
    private function take(string $k): ?string { $v = $_SESSION[$k] ?? null; unset($_SESSION[$k]); return $v; }
    private function redirect(string $p): void { header('Location: '.$p, true, 302); exit; }

    /** Extract numeric id from router (supports scalar, ['id'=>], or varargs). */
    private function extractId(array $args): int
    {
        if (isset($args[0]) && is_array($args[0]) && isset($args[0]['id'])) return (int)$args[0]['id'];
        foreach ($args as $a) {
            if (is_array($a) && isset($a['id'])) return (int)$a['id'];
            if (is_scalar($a)) return (int)$a;
        }
        return 0;
    }

    /* ------------------------- list ------------------------- */
    /** GET /cp/users */
    public function index(): void
    {
        $pdo  = DB::pdo();
        $q    = trim((string)($_GET['q'] ?? ''));
        $role = trim((string)($_GET['role'] ?? ''));

        $sql = "SELECT id,name,email,username,mobile,role,is_active,last_login_at,created_at
                  FROM cp_users";
        $where = []; $params = [];
        if ($q !== '') {
            $where[] = "(name LIKE ? OR email LIKE ? OR username LIKE ? OR mobile LIKE ?)";
            $like = "%{$q}%"; array_push($params, $like, $like, $like, $like);
        }
        if ($role !== '') { $where[] = "role = ?"; $params[] = $role; }
        if ($where) $sql .= " WHERE ".implode(' AND ', $where);
        $sql .= " ORDER BY created_at DESC";

        $stmt  = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        View::render('cp/users/index', [
            'layout' => 'shared/layouts/shell',
            'scope'  => 'cp',
            'title'  => 'CP Users',
            'csrf'   => Csrf::token(),
            'users'  => $users,
            'q'      => $q,
            'role'   => $role,
        ]);
    }

    /* ------------------------- create ------------------------- */
    /** GET /cp/users/new */
    public function createForm(): void
    {
        View::render('cp/users/create', [
            'layout'=> 'shared/layouts/shell',
            'scope' => 'cp',
            'title' => 'New CP User',
            'csrf'  => Csrf::token(),
            'error' => $this->take('_err'),
            'old'   => $_SESSION['_old'] ?? [],
        ]);
        unset($_SESSION['_old']);
    }

    /** POST /cp/users */
    public function store(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect('/cp/users/new');
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired. Please try again.');
            $this->redirect('/cp/users/new');
        }

        $name     = trim((string)($_POST['name'] ?? ''));
        $emailRaw = trim((string)($_POST['email'] ?? ''));
        $email    = strtolower($emailRaw); // normalize email for case-insensitive uniqueness
        $username = trim((string)($_POST['username'] ?? ''));
        $mobile   = preg_replace('/\D+/', '', (string)($_POST['mobile'] ?? ''));
        $role     = trim((string)($_POST['role'] ?? 'staff'));
        $active   = !empty($_POST['is_active']) ? 1 : 0;
        $pass     = (string)($_POST['password'] ?? '');
        $pass2    = (string)($_POST['password_confirm'] ?? '');

        $_SESSION['_old'] = [
            'name'     => $name,
            'email'    => $emailRaw,
            'username' => $username,
            'mobile'   => $mobile,
            'role'     => $role,
            'active'   => $active,
        ];

        $v = new Validation();
        $v->required($name, 'Name is required.');
        $v->email($email, 'Valid email is required.');
        if ($username !== '') $v->length($username, 3, 64, 'Username must be 3–64 chars.');
        if ($mobile !== '')   $v->length($mobile, 8, 15, 'Mobile must be 8–15 digits.');
        $v->in($role, ['superadmin','admin','staff'], 'Invalid role.');
        $v->length($pass, 8, 255, 'Password must be at least 8 chars.');
        $v->equals($pass, $pass2, 'Passwords do not match.');
        if ($v->fails()) { $this->flash('_err', implode("\n", $v->errors())); $this->redirect('/cp/users/new'); }

        $pdo = DB::pdo();

        // Unique checks (case-insensitive email)
        $exists = $pdo->prepare("SELECT 1 FROM cp_users WHERE LOWER(email)=LOWER(?) LIMIT 1");
        $exists->execute([$email]);
        if ($exists->fetch()) { $this->flash('_err','Email already exists.'); $this->redirect('/cp/users/new'); }

        if ($username !== '') {
            $q = $pdo->prepare("SELECT 1 FROM cp_users WHERE username=? LIMIT 1");
            $q->execute([$username]);
            if ($q->fetch()) { $this->flash('_err','Username already taken.'); $this->redirect('/cp/users/new'); }
        }
        if ($mobile !== '') {
            $q = $pdo->prepare("SELECT 1 FROM cp_users WHERE mobile=? LIMIT 1");
            $q->execute([$mobile]);
            if ($q->fetch()) { $this->flash('_err','Mobile already used.'); $this->redirect('/cp/users/new'); }
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $ins = $pdo->prepare("
            INSERT INTO cp_users (email, username, mobile, password_hash, name, role, is_active, is_superadmin, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([
            $email,                          // normalized email
            $username ?: null,
            $mobile   ?: null,
            $hash,
            $name,
            $role,
            $active,
            $role === 'superadmin' ? 1 : 0
        ]);

        unset($_SESSION['_old']);
        $this->redirect('/cp/users');
    }

    /* ------------------------- show ------------------------- */
    /** GET /cp/users/{id} */
    public function show(...$args): void
    {
        $id = $this->extractId($args);
        if ($id <= 0) { http_response_code(404); echo 'User not found.'; return; }

        $pdo  = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM cp_users WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) { http_response_code(404); echo 'User not found.'; return; }

        View::render('cp/users/view', [
            'layout' => 'shared/layouts/shell',
            'scope'  => 'cp',
            'title'  => 'View User',
            'user'   => $user,
        ]);
    }

    /* ------------------------- edit ------------------------- */
    /** GET /cp/users/{id}/edit */
    public function edit(...$args): void
    {
        $id = $this->extractId($args);
        if ($id <= 0) { http_response_code(404); echo 'User not found.'; return; }

        $pdo  = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM cp_users WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) { http_response_code(404); echo 'User not found.'; return; }

        View::render('cp/users/edit', [
            'layout' => 'shared/layouts/shell',
            'scope'  => 'cp',
            'title'  => 'Edit User',
            'user'   => $user,
            'csrf'   => Csrf::token(),
            'error'  => $this->take('_err'),
        ]);
    }

    /* ------------------------- update ------------------------- */
    /** POST /cp/users/{id}/update */
    public function update(...$args): void
    {
        $id = $this->extractId($args);
        if ($id <= 0) $this->redirect('/cp/users');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->redirect("/cp/users/{$id}/edit");
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired. Please try again.');
            $this->redirect("/cp/users/{$id}/edit");
        }

        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? ''))); // normalize
        $username = trim((string)($_POST['username'] ?? ''));
        $mobile   = preg_replace('/\D+/', '', (string)($_POST['mobile'] ?? ''));
        $role     = trim((string)($_POST['role'] ?? 'staff'));
        $active   = !empty($_POST['is_active']) ? 1 : 0;

        $pass     = (string)($_POST['password'] ?? '');
        $pass2    = (string)($_POST['password_confirm'] ?? '');

        $v = new Validation();
        $v->required($name, 'Name is required.');
        $v->email($email, 'Valid email is required.');
        if ($username !== '') $v->length($username, 3, 64, 'Username must be 3–64 chars.');
        if ($mobile !== '')   $v->length($mobile, 8, 15, 'Mobile must be 8–15 digits.');
        $v->in($role, ['superadmin','admin','staff'], 'Invalid role.');
        if ($pass !== '' || $pass2 !== '') {
            $v->length($pass, 8, 255, 'Password must be at least 8 chars.');
            $v->equals($pass, $pass2, 'Passwords do not match.');
        }
        if ($v->fails()) { $this->flash('_err', implode("\n", $v->errors())); $this->redirect("/cp/users/{$id}/edit"); }

        $pdo = DB::pdo();

        // Uniqueness with current record excluded (case-insensitive email)
        $q = $pdo->prepare("SELECT 1 FROM cp_users WHERE LOWER(email)=LOWER(?) AND id<>? LIMIT 1");
        $q->execute([$email, $id]);
        if ($q->fetch()) { $this->flash('_err', 'Email already in use.'); $this->redirect("/cp/users/{$id}/edit"); }

        if ($username !== '') {
            $q = $pdo->prepare("SELECT 1 FROM cp_users WHERE username=? AND id<>? LIMIT 1");
            $q->execute([$username, $id]);
            if ($q->fetch()) { $this->flash('_err', 'Username already taken.'); $this->redirect("/cp/users/{$id}/edit"); }
        }
        if ($mobile !== '') {
            $q = $pdo->prepare("SELECT 1 FROM cp_users WHERE mobile=? AND id<>? LIMIT 1");
            $q->execute([$mobile, $id]);
            if ($q->fetch()) { $this->flash('_err', 'Mobile already used.'); $this->redirect("/cp/users/{$id}/edit"); }
        }

        if ($pass !== '') {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $sql  = "UPDATE cp_users
                        SET name=?, email=?, username=?, mobile=?, role=?, is_active=?, password_hash=?, updated_at=NOW()
                      WHERE id=? LIMIT 1";
            $pdo->prepare($sql)->execute([
                $name, $email,
                $username ?: null, $mobile ?: null,
                $role, $active, $hash, $id
            ]);
        } else {
            $sql  = "UPDATE cp_users
                        SET name=?, email=?, username=?, mobile=?, role=?, is_active=?, updated_at=NOW()
                      WHERE id=? LIMIT 1";
            $pdo->prepare($sql)->execute([
                $name, $email,
                $username ?: null, $mobile ?: null,
                $role, $active, $id
            ]);
        }

        $this->redirect("/cp/users/{$id}");
    }
}