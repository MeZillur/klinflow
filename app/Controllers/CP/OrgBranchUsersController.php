<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\DB;
use Shared\View;
use Shared\Csrf;
use App\Services\Validation;
use App\Services\Mailer;
use App\Services\Logger;

final class OrgBranchUsersController
{
    /* ---------------------------------------------------------
     * Small helpers
     * ------------------------------------------------------- */

    private function redirect(string $to): void
    {
        header('Location: '.$to, true, 302);
        exit;
    }

    private function flash(string $k, string $v): void
    {
        $_SESSION[$k] = $v;
    }

    private function take(string $k): ?string
    {
        $v = $_SESSION[$k] ?? null;
        unset($_SESSION[$k]);
        return $v;
    }

    private function baseUrl(): string
    {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? (getenv('APP_HOST') ?: 'localhost');
        return $scheme.$host;
    }

    private function orgOr404(int $orgId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM cp_organizations WHERE id=? LIMIT 1");
        $stmt->execute([$orgId]);
        $org = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$org) {
            http_response_code(404);
            echo 'Organization not found.';
            exit;
        }
        return $org;
    }

    /** All branches for this org. */
    private function orgBranches(int $orgId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            SELECT id, code, name, is_active
            FROM cp_org_branches
            WHERE org_id = ?
            ORDER BY is_active DESC, name ASC
        ");
        $stmt->execute([$orgId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Tenant users + aggregated branch names for this org. */
    private function orgTenantUsersWithBranches(int $orgId): array
    {
        $pdo = DB::pdo();
        $sql = "
            SELECT u.*,
                   GROUP_CONCAT(
                       CONCAT(b.name, ' (', b.code, ')')
                       ORDER BY b.name SEPARATOR ', '
                   ) AS branch_labels
            FROM tenant_users u
            LEFT JOIN tenant_user_branches ub
              ON ub.org_id = u.org_id AND ub.user_id = u.id
            LEFT JOIN cp_org_branches b
              ON b.id = ub.branch_id
            WHERE u.org_id = ?
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orgId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /* ---------------------------------------------------------
     * Email helpers for branch user welcome
     * ------------------------------------------------------- */

    private function renderBranchUserWelcomeEmail(array $org, array $user, string $tempPassword, array $branches): string
    {
        $brand   = '#228B22';
        $orgName = htmlspecialchars((string)$org['name'], ENT_QUOTES, 'UTF-8');
        $uName   = htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email   = htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8');
        // Adjust login URL if your tenant login path is different
        $login   = htmlspecialchars($this->baseUrl().'/tenant/login', ENT_QUOTES, 'UTF-8');
        $pass    = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

        $branchLines = '';
        foreach ($branches as $b) {
            $code = htmlspecialchars((string)$b['code'], ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars((string)$b['name'], ENT_QUOTES, 'UTF-8');
            $branchLines .= "<li><strong>{$name}</strong> ({$code})</li>";
        }
        if ($branchLines === '') {
            $branchLines = '<li>Main branch</li>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>You have been added to {$orgName}</title>
</head>
<body style="margin:0;padding:0;background:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7f9;">
    <tr><td align="center" style="padding:24px 12px;">
      <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:100%;background:#ffffff;border-radius:16px;border:1px solid #e5e7eb;overflow:hidden;">
        <tr>
          <td style="padding:24px;background:{$brand};color:#ffffff;font-size:18px;font-weight:600;">
            You’ve been added to {$orgName}
          </td>
        </tr>
        <tr>
          <td style="padding:20px 24px;font-size:14px;color:#0f172a;">
            <p style="margin:0 0 8px 0;">Hello {$uName},</p>
            <p style="margin:0 0 12px 0;">
              You now have access to <strong>{$orgName}</strong> in KlinFlow.
            </p>
            <p style="margin:0 0 12px 0;">Your account details:</p>
            <ul style="margin:0 0 12px 18px;padding:0;">
              <li><strong>Login email:</strong> {$email}</li>
              <li><strong>Temporary password:</strong>
                <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">{$pass}</code>
              </li>
            </ul>
            <p style="margin:0 0 8px 0;">Your branch access:</p>
            <ul style="margin:0 0 12px 18px;padding:0;">
              {$branchLines}
            </ul>
            <p style="margin:0 0 14px 0;">
              You can sign in here:<br>
              <a href="{$login}" style="color:{$brand};font-weight:600;">{$login}</a>
            </p>
            <p style="margin:0;font-size:12px;color:#6b7280;">
              For security, please change your password after your first login.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private function sendBranchUserWelcomeEmail(string $to, array $org, array $user, string $tempPassword, array $branches): void
    {
        $subject = 'Access granted to '.$org['name'].' on KlinFlow';
        $html    = $this->renderBranchUserWelcomeEmail($org, $user, $tempPassword, $branches);
        $from    = 'KlinFlow Access <welcome@mail.klinflow.com>';

        $sent = false;

        try {
            if (class_exists(Mailer::class)) {
                $mailer = new Mailer();
                if (method_exists($mailer, 'send')) {
                    $sent = (bool)$mailer->send([
                        'to'      => [$to],
                        'from'    => $from,
                        'subject' => $subject,
                        'html'    => $html,
                        'tags'    => ['event' => 'branch_user_welcome'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $sent = false;
            if (class_exists(Logger::class) && method_exists(Logger::class, 'error')) {
                Logger::error('Branch user welcome (Mailer) failed: '.$e->getMessage());
            } else {
                error_log('Branch user welcome (Mailer) failed: '.$e->getMessage());
            }
        }

        if (!$sent) {
            // You can add Resend API fallback here later if needed.
            if (class_exists(Logger::class) && method_exists(Logger::class, 'error')) {
                Logger::error('Branch user welcome not sent for '.$to);
            } else {
                error_log('Branch user welcome not sent for '.$to);
            }
        }
    }

    /* =========================================================
     *  LIST: Org branches + tenant users
     *  GET /cp/organizations/{id}/users
     * ======================================================= */

    public function index(array $params): void
    {
        $orgId = (int)($params['id'] ?? 0);
        $org   = $this->orgOr404($orgId);

        $branches = $this->orgBranches($orgId);
        $users    = $this->orgTenantUsersWithBranches($orgId);

        View::render('cp/org_users/index', [
            'layout'   => 'shared/layouts/shell',
            'scope'    => 'cp',
            'title'    => 'Branches & users — '.$org['name'],
            'org'      => $org,
            'branches' => $branches,
            'users'    => $users,
            'csrf'     => Csrf::token(),
            'error'    => $this->take('_err'),
        ]);
    }

    /* =========================================================
     *  CREATE FORM: Branch user
     *  GET /cp/organizations/{id}/users/create
     * ======================================================= */

    public function createForm(array $params): void
    {
        $orgId = (int)($params['id'] ?? 0);
        $org   = $this->orgOr404($orgId);

        $branches = $this->orgBranches($orgId);

        View::render('cp/org_users/create', [
            'layout'   => 'shared/layouts/shell',
            'scope'    => 'cp',
            'title'    => 'Add branch user — '.$org['name'],
            'org'      => $org,
            'branches' => $branches,
            'csrf'     => Csrf::token(),
            'error'    => $this->take('_err'),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
        unset($_SESSION['_old']);
    }

    /* =========================================================
     *  STORE: Branch user + branch assignment
     *  POST /cp/organizations/{id}/users
     * ======================================================= */

    public function store(array $params): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/cp/organizations/'.((int)$params['id']).'/users');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired. Please try again.');
            $this->redirect('/cp/organizations/'.((int)$params['id']).'/users/create');
        }

        $orgId = (int)($params['id'] ?? 0);
        $org   = $this->orgOr404($orgId);

        $name     = trim((string)($_POST['name'] ?? ''));
        $emailRaw = trim((string)($_POST['email'] ?? ''));
        $email    = strtolower($emailRaw);
        $username = trim((string)($_POST['username'] ?? ''));
        $mobile   = preg_replace('/\D+/', '', (string)($_POST['mobile'] ?? ''));
        $role     = trim((string)($_POST['role'] ?? 'member')); // tenant role
        $active   = !empty($_POST['is_active']) ? 1 : 0;
        $branchesPosted = (array)($_POST['branch_ids'] ?? []);

        $_SESSION['_old'] = [
            'name'       => $name,
            'email'      => $emailRaw,
            'username'   => $username,
            'mobile'     => $mobile,
            'role'       => $role,
            'active'     => $active,
            'branch_ids' => $branchesPosted,
        ];

        $v = new Validation();
        $v->required($name, 'Name is required.');
        $v->email($email, 'Valid email is required.');
        if ($username !== '') $v->length($username, 3, 64, 'Username must be 3–64 chars.');
        if ($mobile !== '')   $v->length($mobile, 8, 15, 'Mobile must be 8–15 digits.');
        $v->in($role, ['owner','admin','member','branch_staff'], 'Invalid role.');
        if (empty($branchesPosted)) {
            $v->required(null, 'At least one branch must be selected.');
        }

        if ($v->fails()) {
            $this->flash('_err', implode("\n", $v->errors()));
            $this->redirect('/cp/organizations/'.$orgId.'/users/create');
        }

        $pdo = DB::pdo();

        // Ensure posted branches really belong to this org
        $branches = $this->orgBranches($orgId);
        $byId = [];
        foreach ($branches as $b) $byId[(int)$b['id']] = $b;

        $branchIds = [];
        foreach ($branchesPosted as $bid) {
            $bid = (int)$bid;
            if ($bid > 0 && isset($byId[$bid])) {
                $branchIds[] = $bid;
            }
        }
        $branchIds = array_values(array_unique($branchIds));
        if (!$branchIds) {
            $this->flash('_err', 'Invalid branch selection.');
            $this->redirect('/cp/organizations/'.$orgId.'/users/create');
        }

        // Unique email inside this org's tenant_users
        $q = $pdo->prepare("SELECT 1 FROM tenant_users WHERE org_id=? AND LOWER(email)=LOWER(?) LIMIT 1");
        $q->execute([$orgId, $email]);
        if ($q->fetch()) {
            $this->flash('_err', 'That email is already used by another user in this organization.');
            $this->redirect('/cp/organizations/'.$orgId.'/users/create');
        }

        // Generate username if empty
        if ($username === '') {
            $username = preg_replace('/[^a-z0-9]+/i', '', strtolower(strtok($email, '@')));
            if ($username === '') $username = 'user'.$orgId;
        }

        // Ensure username uniqueness per org
        $checkUser = $pdo->prepare("SELECT 1 FROM tenant_users WHERE org_id=? AND username=? LIMIT 1");
        $base = $username; $n = 1;
        while (true) {
            $checkUser->execute([$orgId, $username]);
            if (!$checkUser->fetch()) break;
            $username = $base.(++$n);
        }

        // Temporary password
        $tempPassword = substr(bin2hex(random_bytes(4)), 0, 8);
        $hash         = password_hash($tempPassword, PASSWORD_BCRYPT);

        $pdo->beginTransaction();
        try {
            // Insert tenant user
            $insU = $pdo->prepare("
                INSERT INTO tenant_users
                    (org_id, name, email, username, role, mobile, password_hash, is_active, created_at, updated_at)
                VALUES
                    (?,?,?,?,?,?,?, ?, NOW(), NOW())
            ");
            $insU->execute([
                $orgId,
                $name,
                $email,
                $username,
                $role,
                $mobile ?: null,
                $hash,
                $active,
            ]);

            $userId = (int)$pdo->lastInsertId();

            // Attach branches
            $insB = $pdo->prepare("
                INSERT IGNORE INTO tenant_user_branches (org_id, user_id, branch_id, is_default, created_at)
                VALUES (?,?,?,?, NOW())
            ");

            $isFirst = 1;
            foreach ($branchIds as $bid) {
                $insB->execute([$orgId, $userId, $bid, $isFirst ? 1 : 0]);
                $isFirst = 0;
            }

            $pdo->commit();

            // Send welcome email
            $userPayload = [
                'name'  => $name,
                'email' => $email,
            ];
            $branchesForMail = [];
            foreach ($branchIds as $bid) {
                if (isset($byId[$bid])) $branchesForMail[] = $byId[$bid];
            }
            $this->sendBranchUserWelcomeEmail($email, $org, $userPayload, $tempPassword, $branchesForMail);

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->flash('_err', 'Failed to create branch user: '.$e->getMessage());
            $this->redirect('/cp/organizations/'.$orgId.'/users/create');
        }

        unset($_SESSION['_old']);
        $this->redirect('/cp/organizations/'.$orgId.'/users');
    }
}