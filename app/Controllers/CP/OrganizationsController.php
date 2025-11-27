<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\DB;
use Shared\View;
use Shared\Csrf;
use App\Services\Validation;
use App\Services\Mailer;
use App\Services\Logger;

final class OrganizationsController
{
    /* ======================= Small helpers / plumbing ======================= */

    private function redirect(string $to): void { header('Location: '.$to, true, 302); exit; }
    private function flash(string $k, string $v): void { $_SESSION[$k] = $v; }
    private function take(string $k): ?string { $v = $_SESSION[$k] ?? null; unset($_SESSION[$k]); return $v; }
    private function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

    private function basePath(): string { return dirname(__DIR__, 3); }

    private function baseUrl(): string {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? (getenv('APP_HOST') ?: 'localhost');
        return $scheme.$host;
    }

    /** Slug from FIRST word of name (ascii, dashed) */
    private function slugFromFirstWord(string $name): string
    {
        $first = preg_split('/\s+/', trim($name), 2)[0] ?? $name;
        $slug  = strtolower(preg_replace('/[^a-z0-9]+/', '-', $first));
        $slug  = trim($slug, '-');
        return $slug !== '' ? $slug : 'org';
    }

    /** Ensure slug is unique (append -2, -3, ...). Optionally exclude current ID. */
    private function ensureUniqueSlug(\PDO $pdo, string $slug, ?int $exceptId = null): string
    {
        $base = $slug; $n = 1;
        if ($exceptId) {
            $q = $pdo->prepare("SELECT 1 FROM cp_organizations WHERE slug=? AND id<>? LIMIT 1");
            while (true) { $q->execute([$slug, $exceptId]); if (!$q->fetch()) break; $slug = $base.'-'.(++$n); }
        } else {
            $q = $pdo->prepare("SELECT 1 FROM cp_organizations WHERE slug=? LIMIT 1");
            while (true) { $q->execute([$slug]); if (!$q->fetch()) break; $slug = $base.'-'.(++$n); }
        }
        return $slug;
    }

    /* =================== Email template resolution / render ================== */

    /** Prefer templates in apps/Emails; fall back to legacy path. */
    private function resolveWelcomeTemplate(): ?string
    {
        $root = $this->basePath();
        $try  = [
            $root.'/apps/Emails/org_welcome.php',
            $root.'/apps/Public/emails/org_welcome.php',
        ];
        foreach ($try as $p) if (is_file($p)) return $p;
        return null;
    }

    /** Render Welcome email (ultra-modern, brand-themed) */
private function renderWelcomeEmail(string $orgName, string $ownerEmail, string $tempPassword): string
{
    $vars = [
        'org_name'     => $orgName,
        'owner_email'  => $ownerEmail,
        'login_url'    => $this->baseUrl().'/cp/login',
        'tempPassword' => $tempPassword,
    ];

    // If you have a file template, use it first.
    if ($tpl = $this->resolveWelcomeTemplate()) {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $tpl;
        return (string)ob_get_clean();
    }

    // ── Inline fallback (fully branded) ──────────────────────────────────────
    $brand     = '#228B22'; // KlinFlow green
    $bg        = '#0b1220'; // dark bg safety for gradients
    $o         = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');
    $e         = htmlspecialchars($ownerEmail, ENT_QUOTES, 'UTF-8');
    $u         = htmlspecialchars($vars['login_url'], ENT_QUOTES, 'UTF-8');
    $p         = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
    $ownerName = htmlspecialchars(ucfirst(strtok($ownerEmail, '@')), ENT_QUOTES, 'UTF-8');
    $logoUrl   = $this->baseUrl().'/assets/brand/logo.png';

    return <<<HTML
<!DOCTYPE html>
<html lang="en" style="background:#f6f7f9;">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>Welcome to KlinFlow</title>

  <!-- Preheader (hidden preview text) -->
  <style>
    .preheader { display:none!important; visibility:hidden; opacity:0; color:transparent; mso-hide:all; height:0; width:0; overflow:hidden; }
    /* Inter fallback */
    @font-face {
      font-family: Inter;
      font-style: normal;
      font-weight: 400;
      src: local('Inter'), local('Inter-Regular');
    }
    :root { color-scheme: light dark; supported-color-schemes: light dark; }
    /* Dark mode tweaks */
    @media (prefers-color-scheme: dark) {
      body, .wrapper { background:#0d1117 !important; }
      .card { background:#111827 !important; border-color:#222 !important; }
      .text { color:#e5e7eb !important; }
      .muted { color:#9ca3af !important; }
      .divider { border-color:#1f2937 !important; }
      .btn { color:#ffffff !important; }
    }
    /* Mobile */
    @media (max-width:600px){
      .container{ width:100% !important; border-radius:0 !important; }
      .p24{ padding:20px !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background:#f6f7f9; font-family: Inter, -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Apple Color Emoji','Segoe UI Emoji';">
  <div class="preheader">Your organization {$o} is ready. Your temporary password is included inside.</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7f9;">
    <tr><td align="center" style="padding:32px 16px;">
      <!-- Card -->
      <table role="presentation" class="container card" width="640" cellpadding="0" cellspacing="0" style="width:640px; max-width:100%; background:#ffffff; border:1px solid #eef0f2; border-radius:16px; overflow:hidden;">
        <!-- Header / Brand -->
        <tr>
          <td style="padding:28px 28px 0 28px; background:linear-gradient(135deg, {$brand} 0%, #46c46a 100%);">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="left" style="padding-bottom:20px;">
                  <img src="{$logoUrl}" width="140" height="auto" alt="KlinFlow" style="display:block; border:0; max-width:140px; filter: drop-shadow(0 1px 0 rgba(0,0,0,.05));">
                </td>
              </tr>
              <tr>
                <td style="background:#ffffff; border-radius:12px 12px 0 0; padding:24px;">
                  <h1 class="text" style="margin:0; font-size:22px; line-height:1.35; color:#0f172a;">
                    Welcome to <span style="color:{$brand};">KlinFlow</span>
                  </h1>
                  <p class="muted" style="margin:8px 0 0 0; color:#475569; font-size:14px;">
                    Hi {$ownerName}, your organization <strong>{$o}</strong> is ready to go.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td class="p24" style="padding:0 28px 28px 28px; background:#ffffff;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;">
              <tr>
                <td style="padding:18px 24px; border:1px solid #eef0f2; border-radius:12px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size:14px; color:#0f172a; padding-bottom:10px;">Owner Email</td>
                      <td align="right" style="font-size:14px; color:#0f172a; font-weight:600;">{$e}</td>
                    </tr>
                    <tr>
                      <td colspan="2" class="divider" style="border-top:1px solid #eef0f2; line-height:0; height:12px;"></td>
                    </tr>
                    <tr>
                      <td style="font-size:14px; color:#0f172a; padding-top:8px;">Temporary Password</td>
                      <td align="right" style="padding-top:8px;">
                        <span style="display:inline-block; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:13px; letter-spacing:.3px; background:#f1f5f9; color:#0f172a; padding:6px 10px; border-radius:8px;">{$p}</span>
                      </td>
                    </tr>
                  </table>
                  <p class="muted" style="margin:12px 0 0 0; font-size:12px; color:#64748b;">
                    Tip: You can change this password right after your first sign-in.
                  </p>
                </td>
              </tr>

              <!-- CTA Button -->
              <tr>
                <td align="center" style="padding:22px 0 6px 0;">
                  <a href="{$u}" class="btn"
                     style="background:{$brand}; color:#ffffff; text-decoration:none; display:inline-block; padding:12px 22px; border-radius:12px; font-weight:600; font-size:14px; letter-spacing:.2px;">
                    Open Control Panel
                  </a>
                </td>
              </tr>

              <!-- Secondary link -->
              <tr>
                <td align="center" class="muted" style="padding:4px 0 0 0; font-size:12px; color:#64748b;">
                  or paste into your browser: <span style="color:#0f172a;">{$u}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:18px 28px 28px 28px; background:#ffffff;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td class="muted" style="font-size:12px; color:#64748b;">
                  You’re receiving this because an organization was created for <strong>{$o}</strong> at KlinFlow.
                </td>
              </tr>
              <tr>
                <td class="muted" style="font-size:12px; color:#94a3b8; padding-top:6px;">
                  © KlinFlow • Secure Business Operations Suite
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
      <!-- /Card -->
    </td></tr>
  </table>
</body>
</html>
HTML;
}

  
   /** Send Welcome email with Resend & robust fallbacks */
private function sendWelcomeEmail(string $to, string $orgName, string $tempPassword): void
{
    $subject = 'Welcome to KlinFlow — '.$orgName;
    $html    = $this->renderWelcomeEmail($orgName, $to, $tempPassword);

    // Verified sender at Resend domain
    $from    = 'KlinFlow Welcome <welcome@mail.klinflow.com>';
    $replyTo = 'support@mail.klinflow.com';

    $sent = false;

    // --- Try App\Services\Mailer (array payload) ---
    try {
        if (class_exists(\App\Services\Mailer::class)) {
            $mailer = new \App\Services\Mailer();
            if (method_exists($mailer, 'send')) {
                $sent = (bool)$mailer->send([
                    'to'       => [$to],
                    'from'     => $from,
                    'reply_to' => $replyTo,     // Resend supports reply_to
                    'subject'  => $subject,
                    'html'     => $html,
                    'tags'     => ['event' => 'org_welcome'],
                ]);
            }
        }
    } catch (\Throwable $e) {
        error_log('[OrgWelcome] Mailer(array) failed: '.$e->getMessage());
        $sent = false;
    }

    // --- Try App\Services\Mailer (positional signature) ---
    if (!$sent) {
        try {
            if (class_exists(\App\Services\Mailer::class) && method_exists(\App\Services\Mailer::class, 'send')) {
                /** @phpstan-ignore-next-line */
                $sent = (bool)\App\Services\Mailer::send($to, $subject, $html, $from);
            }
        } catch (\Throwable $e) {
            error_log('[OrgWelcome] Mailer(positional) failed: '.$e->getMessage());
            $sent = false;
        }
    }

    // --- Direct Resend fallback (authoritative) ---
    if (!$sent) {
        $apiKey = getenv('RESEND_API_KEY');
        if (!$apiKey && class_exists('\\Config') && method_exists('\\Config', 'get')) {
            $apiKey = (string)(\Config::get('mail.api_key') ?? '');
        }
        if (!$apiKey && function_exists('config')) {
            $cfg = (array)config('mail', []);
            $apiKey = (string)($cfg['api_key'] ?? '');
        }

        if ($apiKey) {
            $payload = json_encode([
                'from'     => $from,
                'to'       => [$to],
                'subject'  => $subject,
                'html'     => $html,
                'reply_to' => $replyTo,
                'tags'     => [['name' => 'event', 'value' => 'org_welcome']],
            ], JSON_UNESCAPED_SLASHES);

            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer '.$apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log('[OrgWelcome] Resend cURL error: '.$err);
            }
            if ($code >= 200 && $code < 300) {
                $sent = true;
            } else {
                error_log('[OrgWelcome] Resend HTTP '.$code.' body: '.$body);
            }
        } else {
            error_log('[OrgWelcome] Missing RESEND_API_KEY for fallback.');
        }
    }

    if (!$sent) {
        // Avoid PHP mail(); it will DMARC-fail. We just log.
        error_log('[OrgWelcome] All transports failed for '.$to);
    }
}

   /* ================================ LIST ================================= */

/** GET /cp/organizations */
public function index(): void
{
    $pdo  = DB::pdo();
    $q    = trim((string)($_GET['q'] ?? ''));
    $plan = trim((string)($_GET['plan'] ?? ''));
    $st   = trim((string)($_GET['status'] ?? ''));

    $sql = "SELECT 
                o.id,
                o.name,
                o.slug,
                o.plan,
                o.status,
                o.owner_email,
                o.owner_mobile,
                o.company_address,
                o.monthly_price,
                o.created_at,
                o.trial_start,
                o.trial_end,
                DATEDIFF(o.trial_end, CURRENT_DATE()) AS trial_days_left,
                GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') AS modules
            FROM cp_organizations o
            LEFT JOIN cp_org_modules om ON om.org_id = o.id
            LEFT JOIN cp_modules m      ON m.id = om.module_id";

    $w = [];
    $p = [];

    if ($q !== '') {
        $w[]  = "(o.name LIKE ? OR o.slug LIKE ? OR o.owner_email LIKE ?)";
        $like = "%{$q}%";
        $p[]  = $like;
        $p[]  = $like;
        $p[]  = $like;
    }
    if ($plan !== '') {
        $w[] = "o.plan = ?";
        $p[] = $plan;
    }
    if ($st !== '') {
        $w[] = "o.status = ?";
        $p[] = $st;
    }

    if ($w) {
        $sql .= " WHERE " . implode(' AND ', $w);
    }

    $sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Use existing view ID + CP shell layout (no sidenav)
    $layout = \BASE_PATH . '/apps/CP/Views/shared/layouts/shell.php';

    View::render('cp/organizations/index', [
        'scope'  => 'cp',
        'title'  => 'Organizations',
        'csrf'   => Csrf::token(),
        'rows'   => $rows,
        'q'      => $q,
        'plan'   => $plan,
        'status' => $st,
    ], $layout);
}

/* =============================== CREATE ================================ */

/** GET /cp/organizations/create */
public function createForm(): void
{
    $pdo = DB::pdo();

    // 0) Self-heal the module registry from filesystem manifests (safe & idempotent)
    if (class_exists('\\App\\Services\\ModulesCatalog')) {
        \App\Services\ModulesCatalog::syncSafe();
    }

    // 1) Read active modules strictly from DB (no config merge)
    $modules = $pdo->query("
        SELECT id, name, module_key
        FROM cp_modules
        WHERE is_active = 1
        GROUP BY module_key
        ORDER BY name
    ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // 2) If still nothing (first boot or sync unavailable), try legacy helper once
    if (!$modules && class_exists('\\Shared\\Helpers\\ModuleSync')) {
        try {
            \Shared\Helpers\ModuleSync::syncFromFilesystem();

            $modules = $pdo->query("
                SELECT id, name, module_key
                FROM cp_modules
                WHERE is_active = 1
                GROUP BY module_key
                ORDER BY name
            ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (class_exists(\App\Services\Logger::class) && method_exists(\App\Services\Logger::class, 'error')) {
                \App\Services\Logger::error('Module sync (legacy) failed on createForm(): '.$e->getMessage());
            } else {
                error_log('Module sync (legacy) failed on createForm(): '.$e->getMessage());
            }
        }
    }

    // 3) Final guard: if fewer than expected, run catalog sync again and re-query.
    if (count($modules) < 4 && class_exists('\\App\\Services\\ModulesCatalog')) {
        \App\Services\ModulesCatalog::syncSafe();

        $modules = $pdo->query("
            SELECT id, name, module_key
            FROM cp_modules
            WHERE is_active = 1
            GROUP BY module_key
            ORDER BY name
        ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // Plan defaults (kept for future use)
    $planDefaults = [
        'trial'      => [],
        'starter'    => [],
        'growth'     => [],
        'enterprise' => [],
    ];

    $layout = \BASE_PATH . '/apps/CP/Views/shared/layouts/shell.php';

    View::render('cp/organizations/create', [
        'scope'        => 'cp',
        'title'        => 'Create Organization',
        'csrf'         => Csrf::token(),
        'error'        => $this->take('_err'),
        'old'          => $_SESSION['_old'] ?? [],
        'modules'      => $modules,      // DB-backed, deduped by module_key
        'planDefaults' => $planDefaults,
        'openTrial'    => (($_SESSION['_old']['plan'] ?? '') === 'trial'),
    ], $layout);

    unset($_SESSION['_old']);
}

/* ================================ EDIT ================================= */

/** GET /cp/organizations/{id}/edit */
public function editForm(array $params): void
{
    $id  = (int)($params['id'] ?? 0);
    $pdo = DB::pdo();

    $orgStmt = $pdo->prepare("SELECT * FROM cp_organizations WHERE id=? LIMIT 1");
    $orgStmt->execute([$id]);
    $org = $orgStmt->fetch(\PDO::FETCH_ASSOC);
    if (!$org) {
        $this->redirect('/cp/organizations');
    }

    $mods = $pdo->query("
        SELECT id, name, module_key
        FROM cp_modules
        WHERE is_active = 1
        GROUP BY module_key
        ORDER BY name
    ")->fetchAll(\PDO::FETCH_ASSOC);

    $sel = $pdo->prepare("SELECT module_id FROM cp_org_modules WHERE org_id=?");
    $sel->execute([$id]);
    $selected = array_map('intval', array_column($sel->fetchAll(\PDO::FETCH_ASSOC), 'module_id'));

    $layout = \BASE_PATH . '/apps/CP/Views/shared/layouts/shell.php';

    View::render('cp/organizations/edit', [
        'scope'     => 'cp',
        'title'     => 'Edit Organization',
        'csrf'      => Csrf::token(),
        'error'     => $this->take('_err'),
        'org'       => $org,
        'modules'   => $mods,
        'selected'  => $selected,
        'openTrial' => ($org['plan'] ?? '') === 'trial',
    ], $layout);
}
    /** POST /cp/organizations */
    public function store(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired.');
            $this->redirect('/cp/organizations/create');
        }

        // -------- gather + validate input (kept) --------
        $name   = trim((string)($_POST['name'] ?? ''));
        $slug   = trim((string)($_POST['slug'] ?? ''));
        $plan   = trim((string)($_POST['plan'] ?? 'starter'));
        $status = trim((string)($_POST['status'] ?? 'active'));
        $email  = trim((string)($_POST['owner_email'] ?? ''));
        $mobile = trim((string)($_POST['owner_mobile'] ?? ''));
        $addr   = trim((string)($_POST['company_address'] ?? ''));
        $price  = (float)($_POST['monthly_price'] ?? 0);
        $trial_start = $_POST['trial_start'] ?? null;
        $trial_end   = $_POST['trial_end'] ?? null;
        $postedModules = (array)($_POST['modules'] ?? []);

        $_SESSION['_old'] = compact(
            'name','slug','plan','status','email','mobile','addr','price','trial_start','trial_end'
        ) + ['modules'=>$postedModules];

        $v = new Validation();
        $v->required($name, 'Organization name is required.');
        $v->email($email, 'Valid owner email required.');
        $v->in($plan,   ['trial','starter','growth','enterprise'], 'Invalid plan.');
        $v->in($status, ['active','trial','suspended','cancelled'], 'Invalid status.');
        if ($plan === 'trial') { $v->required($trial_start, 'Trial start required.'); $v->required($trial_end, 'Trial end required.'); }
        if ($v->fails()) { $this->flash('_err', implode("\n", $v->errors())); $this->redirect('/cp/organizations/create'); }

        $pdo = DB::pdo();

        // owner email must be globally unique across orgs
        $chk = $pdo->prepare("SELECT id FROM cp_organizations WHERE LOWER(owner_email)=LOWER(?) LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $this->flash('_err', 'That owner email is already used by another organization. Please use a different email.');
            $this->redirect('/cp/organizations/create');
        }

        // org slug uniqueness
        if ($slug === '') $slug = $this->slugFromFirstWord($name);
        $slug = $this->ensureUniqueSlug($pdo, $slug, null);

        // ensure tenant_users table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_users (
              id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id           INT(10) UNSIGNED NOT NULL,
              name             VARCHAR(160) DEFAULT NULL,
              email            VARCHAR(190) NOT NULL,
              username         VARCHAR(120) DEFAULT NULL,
              role             ENUM('owner','admin','member') NOT NULL DEFAULT 'owner',
              mobile           VARCHAR(32) DEFAULT NULL,
              password_hash    VARCHAR(255) NOT NULL,
              is_active        TINYINT(1) NOT NULL DEFAULT 1,
              last_login_at    DATETIME DEFAULT NULL,
              last_login_ip    VARBINARY(16) DEFAULT NULL,
              failed_attempts  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              locked_until     DATETIME DEFAULT NULL,
              created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              CONSTRAINT uq_tu_org_email UNIQUE (org_id, email),
              CONSTRAINT uq_tu_org_user  UNIQUE (org_id, username),
              KEY idx_tu_org (org_id),
              KEY idx_tu_email (email),
              KEY idx_tu_username (username),
              CONSTRAINT fk_tu_org FOREIGN KEY (org_id) REFERENCES cp_organizations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // determine available module key columns (mixed support kept)
        $hasModuleKey = (int)$pdo->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cp_modules' AND COLUMN_NAME='module_key'
        ")->fetchColumn() > 0;

        $hasSlug = (int)$pdo->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cp_modules' AND COLUMN_NAME='slug'
        ")->fetchColumn() > 0;

        $lookupCol = $hasModuleKey ? 'module_key' : 'slug';

        // ensure module exists (returns id)
        $ensureModuleId = function (string $key, ?string $displayName = null) use ($pdo, $lookupCol, $hasModuleKey, $hasSlug): int {
            $key = trim($key);
            if ($key === '') return 0;

            $s = $pdo->prepare("SELECT id FROM cp_modules WHERE {$lookupCol}=? LIMIT 1");
            $s->execute([$key]);
            $id = (int)$s->fetchColumn();
            if ($id > 0) return $id;

            $cols = ['name','is_active','created_at','updated_at'];
            $vals = ['?','1','NOW()','NOW()'];
            $args = [ ($displayName ?: ucfirst($key)) ];

            if ($hasModuleKey) { array_unshift($cols,'module_key'); array_unshift($vals,'?'); array_unshift($args,$key); }
            if ($hasSlug)      { array_unshift($cols,'slug');        array_unshift($vals,'?'); array_unshift($args,$key); }

            $sql = "INSERT INTO cp_modules (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            $ins = $pdo->prepare($sql);
            $ins->execute($args);
            return (int)$pdo->lastInsertId();
        };

        // load config (only used to get display names if a slug arrives)
        $cfg = [];
        try {
            if (class_exists('\\Config') && method_exists('\\Config', 'get')) {
                $cfg = (array) \Config::get('app');
            } elseif (function_exists('config')) {
                $cfg = (array) config('app', []);
            } else {
                $path = dirname(__DIR__, 3) . '/config/app.php';
                if (is_file($path)) $cfg = (array) require $path;
            }
        } catch (\Throwable $e) { $cfg = []; }

        $apps     = (array)($cfg['apps'] ?? []);
        $autoApps = (array)($cfg['auto_enable_apps'] ?? []);

        // build final module ids from mixed input
        $finalModuleIds = [];

        foreach ($postedModules as $m) {
            if (is_numeric($m)) {
                $finalModuleIds[] = (int)$m;
            } else {
                $key = (string)$m;
                if ($key === '') continue;
                $nameForMod = (string)($apps[$key]['title'] ?? $apps[$key]['name'] ?? ucfirst($key));
                $finalModuleIds[] = $ensureModuleId($key, $nameForMod);
            }
        }
        foreach ($autoApps as $key) {
            $key = (string)$key;
            if ($key === '') continue;
            $nameForMod = (string)($apps[$key]['title'] ?? $apps[$key]['name'] ?? ucfirst($key));
            $finalModuleIds[] = $ensureModuleId($key, $nameForMod);
        }

        $finalModuleIds = array_values(array_unique(array_map('intval', $finalModuleIds)));

        // create org + attach modules + owner
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO cp_organizations
               (name, slug, plan, status, owner_email, owner_mobile, company_address, monthly_price,
                created_at, updated_at, trial_start, trial_end)
             VALUES (?,?,?,?,?,?,?,?,NOW(),NOW(),?,?)");
            $ins->execute([$name,$slug,$plan,$status,$email,$mobile,$addr,$price,$trial_start ?: null,$trial_end ?: null]);
            $orgId = (int)$pdo->lastInsertId();

            if ($finalModuleIds) {
                $insm = $pdo->prepare("INSERT IGNORE INTO cp_org_modules (org_id, module_id, created_at) VALUES (?,?,NOW())");
                foreach ($finalModuleIds as $mid) if ($mid > 0) $insm->execute([$orgId, $mid]);
            }

            $tempPassword = substr(bin2hex(random_bytes(4)), 0, 8);
            $ownerName    = $_POST['owner_name'] ?? null;
            if (!$ownerName) $ownerName = ucfirst(strtok($email, '@'));

            $username = preg_replace('/[^a-z0-9]+/i', '', strtolower(strtok($email, '@')));
            if ($username === '') $username = $slug;

            $uChk = $pdo->prepare("SELECT 1 FROM tenant_users WHERE org_id=? AND username=? LIMIT 1");
            $base = $username; $n=1;
            while (true) { $uChk->execute([$orgId, $username]); if (!$uChk->fetch()) break; $username = $base.(++$n); }

            $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
            $insU = $pdo->prepare("
                INSERT INTO tenant_users
                    (org_id, name, email, username, role, mobile, password_hash, is_active, created_at, updated_at)
                VALUES
                    (?,?,?,?, 'owner', ?, ?, 1, NOW(), NOW())
            ");
            $insU->execute([$orgId, $ownerName, $email, $username, $mobile ?: null, $hash]);

            $pdo->commit();

            try { $this->sendWelcomeEmail($email, $name, $tempPassword); } catch (\Throwable $e) {
                if (class_exists(Logger::class) && method_exists(Logger::class, 'error')) {
                    Logger::error('Welcome email send() failed post-create: '.$e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('_err', 'Failed to create organization: '.$e->getMessage());
            $this->redirect('/cp/organizations/create');
        }

        unset($_SESSION['_old']);
        $this->redirect('/cp/organizations');
    }

   
    
    /** POST /cp/organizations/{id}/delete */
public function destroy(array $params): void
{
    if (!Csrf::verify($_POST['_csrf'] ?? '')) { $this->redirect('/cp/organizations'); }
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) { $this->redirect('/cp/organizations'); }

    $pdo = DB::pdo();
    try {
        $pdo->beginTransaction();
        // remove module links first (FK may already cascade, this is safe)
        $pdo->prepare("DELETE FROM cp_org_modules WHERE org_id=?")->execute([$id]);
        // delete the org (tenant_users has FK ON DELETE CASCADE per your schema)
        $pdo->prepare("DELETE FROM cp_organizations WHERE id=? LIMIT 1")->execute([$id]);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['_err'] = 'Failed to delete organization.';
    }
    $this->redirect('/cp/organizations');
}
    

    /** POST /cp/organizations/{id} */
    public function update(array $params): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired.'); $this->redirect('/cp/organizations');
        }

        $id     = (int)($params['id'] ?? 0);
        $pdo    = DB::pdo();

        $name   = trim((string)($_POST['name'] ?? ''));
        $slug   = trim((string)($_POST['slug'] ?? ''));
        $plan   = trim((string)($_POST['plan'] ?? 'starter'));
        $status = trim((string)($_POST['status'] ?? 'active'));
        $email  = trim((string)($_POST['owner_email'] ?? ''));
        $mobile = trim((string)($_POST['owner_mobile'] ?? ''));
        $addr   = trim((string)($_POST['company_address'] ?? ''));
        $price  = (float)($_POST['monthly_price'] ?? 0);
        $trial_start = $_POST['trial_start'] ?? null;
        $trial_end   = $_POST['trial_end'] ?? null;
        $modules     = (array)($_POST['modules'] ?? []);

        $v = new Validation();
        $v->required($name, 'Organization name is required.');
        $v->email($email, 'Valid owner email required.');
        $v->in($plan,   ['trial','starter','growth','enterprise'], 'Invalid plan.');
        $v->in($status, ['active','trial','suspended','cancelled'], 'Invalid status.');
        if ($plan === 'trial') { $v->required($trial_start, 'Trial start required.'); $v->required($trial_end, 'Trial end required.'); }
        if ($v->fails()) { $this->flash('_err', implode("\n",$v->errors())); $this->redirect("/cp/organizations/{$id}/edit"); }

        if ($slug === '') $slug = $this->slugFromFirstWord($name);
        $slug = $this->ensureUniqueSlug($pdo, $slug, $id);

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE cp_organizations
                SET name=?, slug=?, plan=?, status=?, owner_email=?, owner_mobile=?, company_address=?,
                    monthly_price=?, trial_start=?, trial_end=?, updated_at=NOW()
                WHERE id=? LIMIT 1");
            $upd->execute([$name,$slug,$plan,$status,$email,$mobile,$addr,$price,$trial_start ?: null,$trial_end ?: null,$id]);

            // Replace modules atomically
            $pdo->prepare("DELETE FROM cp_org_modules WHERE org_id=?")->execute([$id]);
            if ($modules) {
                $ins = $pdo->prepare("INSERT IGNORE INTO cp_org_modules (org_id, module_id, created_at) VALUES (?,?,NOW())");
                foreach ($modules as $mid) $ins->execute([$id, (int)$mid]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('_err', 'Failed to update: '.$e->getMessage());
            $this->redirect("/cp/organizations/{$id}/edit");
        }

        $this->redirect('/cp/organizations');
    }
}