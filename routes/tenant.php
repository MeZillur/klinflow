<?php
declare(strict_types=1);

use App\Controllers\Tenant\AuthController      as TAuth;
use App\Controllers\Tenant\PasswordController  as TPass;
use App\Controllers\Tenant\DashboardController as TDash;
use App\Controllers\Tenant\UsersController     as TUsers;
use App\Controllers\Tenant\SettingsController  as TSettings;
use App\Controllers\Tenant\MeController;

use App\Middleware\TenantResolver;
use Shared\DB;

/**
 * KlinFlow tenant routes
 *
 * Conventions:
 * - Use the same placeholder style everywhere → `{slug:[A-Za-z0-9_-]+}`
 * - Provide trailing-slash aliases for user-facing GET routes
 * - Keep everything inside the returned array; nothing after `return`
 */
return [

    /* ============================ Auth (slugless) ============================ */
    ['GET',  '/tenant/login',                 [TAuth::class, 'showLogin']],
    ['POST', '/tenant/login',                 [TAuth::class, 'login'],   ['csrf']],
    ['POST', '/tenant/logout',                [TAuth::class, 'logout']],

    /* ========================= Password (slugless) ========================== */
    ['GET',  '/tenant/forgot',                [TPass::class, 'showForgot']],
    ['POST', '/tenant/forgot',                [TPass::class, 'sendReset'], ['csrf']],
    ['GET',  '/tenant/reset',                 [TPass::class, 'showReset']],
    ['POST', '/tenant/reset',                 [TPass::class, 'doReset'],   ['csrf']],

    /* ========================= Global profile (/me) =========================
       Uses MeController, works for "current tenant user" based on session.
       This is slugless so you can open /me from any module.
       ====================================================================== */
    ['GET',  '/me',                           [MeController::class, 'index']],
    ['POST', '/me',                           [MeController::class, 'update'], ['csrf']],

    /* =========================== Health / debug ============================= */
    ['GET',  '/t/ping',                       fn() => print('tenant ok')],
    ['GET',  '/t/test/dashboard',             fn() => print('matched test dashboard')],

    /* =========== Slug root → redirect to tenant dashboard =========== */
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}',      function(array $p){
        header('Location: /t/'.$p['slug'].'/dashboard', true, 302); exit;
    }],
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/',     function(array $p){
        header('Location: /t/'.$p['slug'].'/dashboard', true, 302); exit;
    }],

    /* ============================== Dashboard =============================== */
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/dashboard',   [TDash::class, 'index']],
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/dashboard/',  [TDash::class, 'index']],

    /* ================================ Users =================================
       Owner-gated inside controller.
       - List users
       - Invite user (GET form + POST action)
       - Me (profile GET + POST) → slugful /t/{slug}/users/me
       ====================================================================== */
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/users',               [TUsers::class, 'index']],
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/users/',              [TUsers::class, 'index']],

    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/users/invite',        [TUsers::class, 'inviteForm']],
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/users/invite/',       [TUsers::class, 'inviteForm']],
    ['POST', '/t/{slug:[A-Za-z0-9_-]+}/users/invite',        [TUsers::class, 'sendInvite'], ['csrf']],

    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/users/me',            [TUsers::class, 'profile']],
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/users/me/',           [TUsers::class, 'profile']],
    ['POST', '/t/{slug:[A-Za-z0-9_-]+}/users/me',            [TUsers::class, 'updateProfile'], ['csrf']],

    /* ============== Invite acceptance (public, slugless link) ============== */
    ['GET',  '/tenant/invite/accept',         [TUsers::class, 'acceptForm']],
    ['POST', '/tenant/invite/accept',         [TUsers::class, 'accept'], ['csrf']],

    /* =============================== Settings =============================== */
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/settings',            [TSettings::class, 'index']],
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/settings/',           [TSettings::class, 'index']],
    ['POST', '/t/{slug:[A-Za-z0-9_-]+}/settings',            [TSettings::class, 'update'], ['csrf']],

    /* =========================== Tenant DB check ============================ */
    ['GET',  '/t/{slug:[A-Za-z0-9_-]+}/_dbcheck', function(array $p) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            TenantResolver::applyFromSlug($p['slug'] ?? null);
            $ctx   = TenantResolver::ctx();
            $pdo   = DB::tenant();
            $global= DB::pdo();

            $dbName = 'unknown';
            try { $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn() ?: 'n/a'; } catch (\Throwable $e) {}

            echo json_encode([
                'org_id'     => $ctx['org_id'] ?? null,
                'slug'       => $ctx['slug'] ?? null,
                'timezone'   => $ctx['timezone'] ?? null,
                'plan'       => $ctx['plan'] ?? null,
                'database'   => $dbName,
                'is_tenant'  => ($pdo !== $global) ? 'yes (tenant DB active)' : 'no (using global DB)',
                'dsn_status' => $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) ?? 'n/a',
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Tenant DB check failed', 'message' => $e->getMessage()]);
        }
    }],
];