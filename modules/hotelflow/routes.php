<?php
declare(strict_types=1);

/**
 * HotelFlow — routes.php (POS-style)
 * ---------------------------------------------------------------
 * - Uses $__KF_MODULE__ context from front.php
 * - Normal, POS-like routing
 * - NO early exit for ?_debug=1 (so shell + controllers still run)
 * - Friendly 404 + "coming soon" view fallback
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use Shared\View;

use Modules\hotelflow\Controllers\AccountingController;
use Modules\hotelflow\Controllers\DashboardController;
use Modules\hotelflow\Controllers\FrontdeskController;
use Modules\hotelflow\Controllers\ReservationsController;
use Modules\hotelflow\Controllers\RoomsController;
use Modules\hotelflow\Controllers\HkController;
use Modules\hotelflow\Controllers\BillingController;
use Modules\hotelflow\Controllers\GuestsController;
use Modules\hotelflow\Controllers\ReportsController;

use Modules\hotelflow\Controllers\BiometricController;
use Modules\hotelflow\Controllers\LookupController;
use Modules\hotelflow\Controllers\PreArrivalController;
use Modules\hotelflow\Controllers\RatesController;
use Modules\hotelflow\Controllers\CheckinController;
use Modules\hotelflow\Controllers\PaymentsController;
use Modules\hotelflow\Controllers\FoliosController;
use Modules\hotelflow\Controllers\HousekeepingController;
use Modules\hotelflow\Controllers\SettingsController;

use Modules\hotelflow\Controllers\NightAuditController;

/* ============================================================================
 * 0. Recover context from front.php
 * ========================================================================== */

$mod         = (array)($__KF_MODULE__ ?? []);
$slug        = (string)($mod['slug'] ?? '');
$tail        = trim((string)($mod['tail'] ?? ''), '/');
$method      = strtoupper((string)($mod['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
$moduleDir   = (string)($mod['module_dir'] ?? __DIR__);
$ctxOrg      = (array)($mod['org'] ?? []);
$manifest    = (array)($mod['manifest'] ?? []);
$manifestLay = (string)($manifest['layout'] ?? 'Views/shared/layouts/shell.php');

/* Normalise HEAD / OPTIONS → GET for simplicity */
if ($method === 'HEAD' || $method === 'OPTIONS') {
    $method = 'GET';
}

/* ============================================================================
 * 1. Build canonical module_base
 * ========================================================================== */

$module_base = $slug !== ''
    ? '/t/' . rawurlencode($slug) . '/apps/hotelflow'
    : '/apps/hotelflow';

/* ============================================================================
 * 2. Resolve shell layout file
 * ========================================================================== */

$layoutFile = null;
$try = rtrim($moduleDir, '/') . '/' . ltrim($manifestLay, '/');
if (is_file($try)) {
    $layoutFile = $try;
}

/* ============================================================================
 * 3. Shared context (passed into controllers & views)
 * ========================================================================== */

$ctx = [
    'slug'        => $slug,
    'org'         => $ctxOrg,
    'org_id'      => (int)($ctxOrg['org_id'] ?? $ctxOrg['id'] ?? 0),
    'module_base' => $module_base,
    'module_dir'  => $moduleDir,
    'layout'      => $layoutFile,
    'scope'       => 'tenant',
    'tail'        => $tail,
    'method'      => $method,
];

/* ============================================================================
 * 4. Utilities: render() + notFound()
 * ========================================================================== */

$render = function (string $rel, array $data = []) use ($ctx, $moduleDir): void {
    $view = rtrim($moduleDir, '/') . '/Views/' . ltrim($rel, '/') . '.php';

    if (is_file($view)) {
        View::render($view, array_merge($ctx, $data), $ctx['layout']);
        return;
    }

    // Friendly placeholder for not-yet-built views
    http_response_code(200);
    $safe = htmlspecialchars($rel, ENT_QUOTES, 'UTF-8');
    $base = $ctx['module_base'] ?? '/apps/hotelflow';

    echo <<<HTML
<!doctype html><meta charset="utf-8">
<style>
body{font-family:system-ui,Inter,Arial;background:#f9fafb;color:#0f172a;padding:40px;margin:0}
.card{max-width:780px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;box-shadow:0 4px 18px rgba(0,0,0,.06)}
h1{margin:0 0 10px;font-size:22px}
p{color:#6b7280;margin:0}
a{display:inline-block;margin-top:20px;background:#228B22;color:#fff;text-decoration:none;padding:8px 14px;border-radius:10px;font-weight:600}
</style>
<div class="card">
  <h1>Coming Soon</h1>
  <p>The view "<code>{$safe}</code>" is not created yet.</p>
  <a href="{$base}/dashboard">← Back to Dashboard</a>
</div>
HTML;
};

$notFound = function (string $msg = 'Route not found.') use ($module_base): void {
    http_response_code(404);
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!doctype html><meta charset="utf-8">
<style>
body{font-family:system-ui,Inter,Arial;background:#fff;color:#111827;padding:40px;margin:0}
h1{font-size:32px;margin:0 0 12px}
p{color:#6b7280;margin:0 0 20px}
a{background:#228B22;color:#fff;padding:8px 14px;border-radius:10px;text-decoration:none;font-weight:600}
</style>
<h1>404 — HotelFlow</h1>
<p>{$safe}</p>
<a href="{$module_base}/dashboard">Go to Dashboard</a>
HTML;
    exit;
};

/* ============================================================================
 * 5. Parse tail segments
 * ========================================================================== */

$parts = $tail === ''
    ? []
    : array_values(array_filter(explode('/', $tail), static fn($x) => $x !== ''));

$seg    = static fn(int $i) => $parts[$i] ?? null;

$first  = (string)($seg(0) ?? '');
$second = (string)($seg(1) ?? '');
$third  = (string)($seg(2) ?? '');
$fourth = (string)($seg(3) ?? '');

/* ============================================================================
 * 6. Helper for controller existence
 * ========================================================================== */

$has = static fn(string $cls): bool => class_exists($cls);

/* ============================================================================
 * 7. Dashboard
 * ========================================================================== */
/**
 * /apps/hotelflow
 * /apps/hotelflow/
 * /apps/hotelflow/dashboard
 */
if ($tail === '' || $first === '' || $first === 'dashboard') {
    if ($has(DashboardController::class)) {
        (new DashboardController())->index($ctx);
        exit;
    }
    $render('dashboard/index', ['title' => 'Dashboard']);
    exit;
}

/* ============================================================================
 * 8. Frontdesk
 * ========================================================================== */

if ($first === 'frontdesk') {
    if (!$has(FrontdeskController::class)) {
        // Fallback: simple arrivals view with zero data
        $render('frontdesk/arrivals', [
            'title' => 'Frontdesk — HotelFlow',
            'date'  => date('Y-m-d'),
            'rows'  => [],
            'total' => 0,
            'tab'   => 'arrivals',
            'note'  => 'Frontdesk controller not loaded.',
        ]);
        exit;
    }

    $ctl = new FrontdeskController();

    // /frontdesk            → arrivals (default)
    if ($second === '' || $second === null) {
        $ctl->arrivals($ctx); exit;
    }

    // /frontdesk/arrivals
    if ($second === 'arrivals') {
        $ctl->arrivals($ctx); exit;
    }

    // /frontdesk/inhouse or /frontdesk/in-house
    if ($second === 'inhouse' || $second === 'in-house') {
        $ctl->inhouse($ctx); exit;
    }

    // /frontdesk/departures
    if ($second === 'departures') {
        $ctl->departures($ctx); exit;
    }

    // /frontdesk/room-status
    if ($second === 'room-status') {
        $ctl->roomStatus($ctx); exit;
    }

    $notFound('Unknown frontdesk route.');
    exit;
}

/* ============================================================================
 * 9. Check-in / Check-out shortcuts
 * ========================================================================== */

if ($first === 'checkin') {
    if (!$has(CheckinController::class)) {
        // Soft fallback: plain view if controller not wired yet
        $render('checkin/index', ['title' => 'Check-in desk']);
        exit;
    }

    $ctl = new CheckinController();

    // GET /checkin → main desk
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        exit;
    }

    // POST /checkin/lookup → resolve reservation
    if ($second === 'lookup' && $method === 'POST') {
        $ctl->lookup($ctx);
        exit;
    }

    // GET /checkin/{id} → check-in for specific reservation
    if ($second !== '' && $second !== null && ctype_digit((string)$second) && $method === 'GET') {
        $ctl->show($ctx, (int)$second);
        exit;
    }

    $notFound('Unknown check-in route.');
    exit;
}

if ($first === 'checkout') {
    if ($has(FrontdeskController::class) && method_exists(FrontdeskController::class, 'checkout')) {
        (new FrontdeskController())->checkout($ctx);
        exit;
    }
    $render('checkout/index', ['title' => 'Check-out']);
    exit;
}

/* ============================================================================
 * 10. Check-out shortcuts  (future-friendly)
 *    /checkout/{id}
 *    /checkout/{id}/confirm (POST)
 * ========================================================================== */

if ($first === 'checkout') {

    if (!$has(ReservationsController::class)) {
        $render('reservations/index', ['title' => 'Check-out']);
        exit;
    }

    $ctl = new ReservationsController();

    // GET /checkout/{id} → show checkout screen (summary, folio, etc.)
    if ($second !== '' && $second !== null
        && ctype_digit((string)$second)
        && ($third === '' || $third === null)
        && $method === 'GET') {
        $ctl->checkoutShow($ctx, (int)$second);
        exit;
    }

    // POST /checkout/{id}/confirm → actually mark as checked-out
    if ($second !== '' && $second !== null
        && ctype_digit((string)$second)
        && $third === 'confirm'
        && $method === 'POST') {
        $ctl->checkoutConfirm($ctx, (int)$second);
        exit;
    }

    $notFound('Unknown check-out route.');
    exit;
}

/* ============================================================================
 * 11. Reservations
 * ========================================================================== */
/**
 * GET  /reservations                       -> index
 * GET  /reservations/create                -> create form
 * POST /reservations                       -> store
 * GET  /reservations/{id}                  -> show
 * GET  /reservations/{id}/verify           -> verify
 * GET  /reservations/create-existing       -> existing guest flow
 *
 * GET  /reservations/walkin                -> walk-in reservation flow
 *
 * Pre-arrival (staff side):
 * GET  /reservations/prearrival-launch     -> pre-arrival launch screen
 * GET|POST /reservations/prearrival/send   -> generate token + mailto link
 * POST /reservations/{id}/confirm-prearrival
 * POST /reservations/{id}/cancel
 */
if ($first === 'reservations') {

    if (!$has(ReservationsController::class)) {
        $render('reservations/index', ['title' => 'Reservations']);
        exit;
    }

    $ctl = new ReservationsController();

    // index
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx); exit;
    }

    // create
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); exit;
    }

    // store
    if (($second === '' || $second === null) && $method === 'POST') {
        $ctl->store($ctx); exit;
    }

    // show
    if ($second !== '' && $second !== null
        && ctype_digit((string)$second)
        && ($third === '' || $third === null)
        && $method === 'GET') {
        $ctl->show($ctx, (int)$second); exit;
    }

    // create-existing
    if ($second === 'create-existing' && $method === 'GET') {
        $ctl->createExisting($ctx); exit;
    }

    // verify
    if ($second !== '' && $second !== null
        && ctype_digit((string)$second)
        && $third === 'verify'
        && $method === 'GET') {
        $ctl->verify($ctx, (int)$second); exit;
    }

    // GET /reservations/walkin
    if ($second === 'walkin' && $method === 'GET') {
        $ctl->walkin($ctx); exit;
    }

    /* -------- Pre-arrival (staff side) -------- */

    // GET /reservations/prearrival-launch
    if ($second === 'prearrival-launch' && $method === 'GET') {
        $pctl = new PreArrivalController();
        $pctl->launch($ctx);
        exit;
    }

    // GET or POST /reservations/prearrival/send
    if ($second === 'prearrival' && $third === 'send') {
        $pctl = new PreArrivalController();

        if ($method === 'POST') {
            $pctl->send($ctx);   // build token + mailto
        } else {
            $pctl->launch($ctx); // fallback to launch screen
        }
        exit;
    }

    // POST /reservations/{id}/confirm-prearrival
    if ($second !== '' && $second !== null
        && ctype_digit((string)$second)
        && $third === 'confirm-prearrival'
        && $method === 'POST') {
        $ctl->confirmPrearrival($ctx, (int)$second);
        exit;
    }

    // POST /reservations/{id}/cancel
    if ($second !== '' && $second !== null
        && ctype_digit((string)$second)
        && $third === 'cancel'
        && $method === 'POST') {
        $ctl->cancel($ctx, (int)$second);
        exit;
    }

    // fallback
    $notFound('Unknown reservations route.');
    exit;
}
    


/* ============================================================================
 * Public Pre-arrival Form (guest-facing, no login)
 *  - GET  /prearrival?token=...  -> form
 *  - POST /prearrival            -> submit
 * ========================================================================== */

if ($first === 'prearrival') {
    $ctl = new \Modules\hotelflow\Controllers\PreArrivalController();

    if ($method === 'POST') {
        $ctl->submit($ctx);
        exit;
    }

    // GET: /t/{slug}/apps/hotelflow/prearrival?token=xxx
    $ctl->form($ctx);
    exit;
}


/* ============================================================================
 * 11. Biometric (webcam/ID upload)
 * ========================================================================== */
/**
 * POST /biometric/upload
 */
if ($first === 'biometric' && $second === 'upload') {
    if ($has(BiometricController::class)) {
        (new BiometricController())->upload($ctx);
        exit;
    }
    http_response_code(404);
    echo 'Biometric controller missing.';
    exit;
}

/* ============================================================================
 * 12. Guests
 * ========================================================================== */
/**
 * GET /guests           -> list
 * GET /guests/{id}      -> profile
 */
if ($first === 'guests') {
    if ($has(GuestsController::class)) {
        $ctl = new GuestsController();

        if (($second === '' || $second === null) && $method === 'GET') {
            $ctl->index($ctx); exit;
        }

        if (ctype_digit($second) && $method === 'GET') {
            $ctl->show($ctx, (int)$second); exit;
        }

        $notFound('Unknown guests route.');
    }

    $render('guests/index', ['title' => 'Guests']);
    exit;
}

/* ========================================================================
 * ROOMS + ROOM TYPES + FLOORS
 * ====================================================================== */
if ($first === 'rooms') {

    if (!$has(RoomsController::class)) {
        // Fallback if controller missing
        if ($second === '' || $second === null) { $render('rooms/index', ['title' => 'Rooms']); exit; }
        if ($second === 'create')              { $render('rooms/form',  ['title' => 'Add Room']); exit; }
        if ($second === 'types')               { $render('rooms/types', ['title' => 'Room Types']); exit; }
        if ($second === 'floors')              { $render('rooms/floors',['title' => 'Floors']); exit; }
        $notFound('Unknown rooms route.');
    }

    $ctl = new RoomsController();

    /* ---------- Subresource: ROOM TYPES ---------- */
    if ($second === 'types') {
        $idPart = $third;

        // GET /rooms/types
        if (($idPart === '' || $idPart === null) && $method === 'GET') {
            $ctl->typesIndex($ctx); exit;
        }

        // POST /rooms/types/store
        if ($idPart === 'store' && $method === 'POST') {
            $ctl->typesStore($ctx); exit;
        }

        // POST /rooms/types/{id}/update
        if (ctype_digit((string)$idPart) && $fourth === 'update' && $method === 'POST') {
            $ctl->typesUpdate($ctx, (int)$idPart); exit;
        }

        // POST /rooms/types/{id}/delete
        if (ctype_digit((string)$idPart) && $fourth === 'delete' && $method === 'POST') {
            $ctl->typesDelete($ctx, (int)$idPart); exit;
        }

        $notFound('Unknown room types route.');
    }

    /* ---------- Subresource: FLOORS ---------- */
    if ($second === 'floors') {
        $idPart = $third;

        // GET /rooms/floors
        if (($idPart === '' || $idPart === null) && $method === 'GET') {
            $ctl->floorsIndex($ctx); exit;
        }

        // POST /rooms/floors/store
        if ($idPart === 'store' && $method === 'POST') {
            $ctl->floorsStore($ctx); exit;
        }

        // POST /rooms/floors/{id}/delete
        if (ctype_digit((string)$idPart) && $fourth === 'delete' && $method === 'POST') {
            $ctl->floorsDelete($ctx, (int)$idPart); exit;
        }

        $notFound('Unknown floors route.');
    }

    /* ---------- Rooms main resource ---------- */

    // GET /rooms
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx); exit;
    }

    // GET /rooms/create
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); exit;
    }

    // POST /rooms   (store)
    if (($second === '' || $second === null) && $method === 'POST') {
        $ctl->store($ctx); exit;
    }

    // GET /rooms/{id}   (edit)
    if (ctype_digit($second) && ($third === '' || $third === null) && $method === 'GET') {
        $ctl->edit($ctx, (int)$second); exit;
    }

    // POST /rooms/{id}  (update)
    if (ctype_digit($second) && ($third === '' || $third === null) && $method === 'POST') {
        $ctl->update($ctx, (int)$second); exit;
    }

    // POST /rooms/{id}/toggle-ooo
    if (ctype_digit($second) && $third === 'toggle-ooo' && $method === 'POST') {
        $ctl->toggleOoo($ctx, (int)$second); exit;
    }

    // POST /rooms/{id}/toggle-oos
    if (ctype_digit($second) && $third === 'toggle-oos' && $method === 'POST') {
        $ctl->toggleOos($ctx, (int)$second); exit;
    }

    $notFound('Unknown rooms route.');
}


/* ============================================================================
 * Rates (ARI: Availability, Rate Plans, Restrictions, Allotments, Yield)
 * ========================================================================== */

if ($first === 'rates') {

    if ($has(RatesController::class)) {
        $ctl = new RatesController();

        // /rates  → ARI home
        if (($second === '' || $second === null) && $method === 'GET') {
            $ctl->index($ctx);
            exit;
        }

        // /rates/availability  → Grid calendar by room type
        if ($second === 'availability' && $method === 'GET') {
            $ctl->availability($ctx);
            exit;
        }

        // /rates/rate-plans  → BAR / corporate / OTA plans
        if ($second === 'rate-plans' && $method === 'GET') {
            $ctl->ratePlans($ctx);
            exit;
        }

        // /rates/overrides  → Seasons / promo overrides
        if ($second === 'overrides' && $method === 'GET') {
            $ctl->overrides($ctx);
            exit;
        }

        // /rates/restrictions  → CTA / CTD / LOS rules
        if ($second === 'restrictions' && $method === 'GET') {
            $ctl->restrictions($ctx);
            exit;
        }

        // /rates/allotments  → Partner / contract allotments
        if ($second === 'allotments' && $method === 'GET') {
            $ctl->allotments($ctx);
            exit;
        }

        // /rates/yield-rules  → Dynamic pricing rules (future)
        if ($second === 'yield-rules' && $method === 'GET') {
            $ctl->yieldRules($ctx);
            exit;
        }

        // Anything else under /rates → 404 inside module
        $notFound('Unknown rates route.');
    }

    // Fallback: if controller class is missing, show legacy availability view
    $render('rates/availability', ['title' => 'Rates & Availability']);
    exit;
}

/* ============================================================================
 * Housekeeping
 *   /housekeeping
 *   /housekeeping/tasks
 *   /housekeeping/lost-found
 * ========================================================================== */
if ($first === 'housekeeping') {

    // Controller thakle use kori, na thakle soft fallback
    if (!$has(HkController::class)) {
        $render('hk/index', [
            'title'   => 'Housekeeping',
            'rooms'   => [],
            'schema'  => ['rooms' => false],
            'filters' => ['status' => '', 'floor' => ''],
        ]);
        exit;
    }

    $ctl = new HkController();

    /* ---------------- /housekeeping (main board) ---------------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        exit;
    }

    /* -------- /housekeeping/rooms/{id}/hk-status (POST) -------- */
    if ($second === 'rooms'
        && ctype_digit((string)$third)
        && $fourth === 'hk-status'
        && $method === 'POST') {

        $ctl->roomSetHkStatus($ctx, (int)$third);
        exit;
    }

    /* ------------------------ Tasks ----------------------------- */
    // /housekeeping/tasks
    // /housekeeping/tasks/{id}/done
    // /housekeeping/tasks/{id}/delete
    if ($second === 'tasks') {

        // GET /housekeeping/tasks
        if (($third === '' || $third === null) && $method === 'GET') {
            $ctl->tasksIndex($ctx);
            exit;
        }

        // POST /housekeeping/tasks
        if (($third === '' || $third === null) && $method === 'POST') {
            $ctl->tasksStore($ctx);
            exit;
        }

        // POST /housekeeping/tasks/{id}/done
        if (ctype_digit((string)$third) && $fourth === 'done' && $method === 'POST') {
            $ctl->tasksDone($ctx, (int)$third);
            exit;
        }

        // POST /housekeeping/tasks/{id}/delete
        if (ctype_digit((string)$third) && $fourth === 'delete' && $method === 'POST') {
            $ctl->tasksDelete($ctx, (int)$third);
            exit;
        }

        $notFound('Unknown housekeeping tasks route.');
        exit;
    }

    /* ---------------------- Lost & Found ------------------------ */
    // /housekeeping/lost-found
    // /housekeeping/lost-found/{id}/status
    // /housekeeping/lost-found/{id}/delete
    if ($second === 'lost-found') {

        // GET /housekeeping/lost-found
        if (($third === '' || $third === null) && $method === 'GET') {
            $ctl->lostFoundIndex($ctx);
            exit;
        }

        // POST /housekeeping/lost-found
        if (($third === '' || $third === null) && $method === 'POST') {
            $ctl->lostFoundStore($ctx);
            exit;
        }

        // POST /housekeeping/lost-found/{id}/status
        if (ctype_digit((string)$third) && $fourth === 'status' && $method === 'POST') {
            $ctl->lostFoundSetStatus($ctx, (int)$third);
            exit;
        }

        // POST /housekeeping/lost-found/{id}/delete
        if (ctype_digit((string)$third) && $fourth === 'delete' && $method === 'POST') {
            $ctl->lostFoundDelete($ctx, (int)$third);
            exit;
        }

        $notFound('Unknown housekeeping lost-found route.');
        exit;
    }

    /* ------------------ Catch-all under /housekeeping ----------- */
    $notFound('Unknown housekeeping route.');
    exit;
}


/* ============================================================================
 * Night Audit
 *  - GET /night-audit           → dashboard for tonight
 *  - GET /night-audit/history   → previous runs
 * ========================================================================== */
if ($first === 'night-audit') {

    if (!$has(\Modules\hotelflow\Controllers\NightAuditController::class)) {
        // Soft fallback: show UI with zero data
        $render('night-audit/index', [
            'title'       => 'Night audit',
            'today'       => date('Y-m-d'),
            'metrics'     => [
                'arrivals'      => 0,
                'departures'    => 0,
                'inhouse'       => 0,
                'openFolios'    => 0,
                'folioBalance'  => 0.0,
                'dirtyRooms'    => 0,
                'warnings'      => [],
            ],
            'module_base' => $ctx['module_base'] ?? '/apps/hotelflow',
        ]);
        exit;
    }

    $ctl = new \Modules\hotelflow\Controllers\NightAuditController();

    // GET /night-audit
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        exit;
    }

    // GET /night-audit/history
    if ($second === 'history' && $method === 'GET') {
        $ctl->history($ctx);
        exit;
    }

    $notFound('Unknown night-audit route.');
}

/* ============================================================================
 * 11. Payments
 * URL patterns:
 *  GET  /payments              → index (dashboard + list)
 *  GET  /payments/receive      → receive payment form
 *  POST /payments/receive      → store payment
 *  GET  /payments/{id}         → show payment details
 * ========================================================================== */

if ($first === 'payments') {

    if (!$has(PaymentsController::class)) {
        // Soft fallback if controller not autoloaded
        $render('payments/index', [
            'title' => 'Payments',
            'today' => date('Y-m-d'),
            'stats' => [
                'today_total'   => 0,
                'month_total'   => 0,
                'refunds_today' => 0,
                'pending_total' => 0,
            ],
            'rows'  => [],
        ]);
        exit;
    }

    $ctl = new PaymentsController();

    // GET /payments  → dashboard + list
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx); exit;
    }

    // GET /payments/receive → receive payment form
    if ($second === 'receive' && $method === 'GET') {
        $ctl->receiveForm($ctx); exit;
    }

    // POST /payments/receive → store payment
    if ($second === 'receive' && $method === 'POST') {
        $ctl->receiveStore($ctx); exit;
    }

    // GET /payments/{id} → payment details
    if ($second !== '' && $second !== null && ctype_digit((string)$second) && $method === 'GET') {
        $ctl->show($ctx, (int)$second); exit;
    }

    $notFound('Unknown payments route.');
    exit;
}


/* ============================================================================
 * Accounting
 *  - GET /accounting          → dashboard
 * ========================================================================== */
if ($first === 'accounting') {

    // Use controller if available
    if ($has(\Modules\hotelflow\Controllers\AccountingController::class)) {
        $ctl = new \Modules\hotelflow\Controllers\AccountingController();

        if (($second === '' || $second === null) && $method === 'GET') {
            $ctl->index($ctx);   // Accounting dashboard
            exit;
        }

        $notFound('Unknown accounting route.');
    }

    // Soft fallback: simple view only
    $render('accounting/index', [
        'title'       => 'Accounting — HotelFlow',
        'module_base' => $ctx['module_base'] ?? '/apps/hotelflow',
        'metrics'     => [],
        'recent'      => [],
    ]);
    exit;
}

/* ============================================================================
 * 15. Billing
 * ========================================================================== */

if ($first === 'billing') {
    if ($has(BillingController::class)) {
        (new BillingController())->index($ctx); exit;
    }
    $render('billing/index', ['title' => 'Billing']); exit;
}

/* ============================================================================
 * Reports
 *  - GET /reports          → index (catalog of reports)
 *  - GET /reports/{key}    → show stub for specific report
 * ========================================================================== */
if ($first === 'reports') {

    if ($has(\Modules\hotelflow\Controllers\ReportsController::class)) {
        $ctl = new \Modules\hotelflow\Controllers\ReportsController();

        // GET /reports → index (catalog)
        if (($second === '' || $second === null) && $method === 'GET') {
            $ctl->index($ctx);
            exit;
        }

        // GET /reports/{key} → report stub screen
        if ($second !== '' && $second !== null && $method === 'GET') {
            $ctl->show($ctx, (string)$second);
            exit;
        }

        $notFound('Unknown reports route.');
    }

    // Controller missing → soft fallback catalog without dynamic config
    $render('reports/index', [
        'title'   => 'Reports — HotelFlow',
        'ctx'     => $ctx,
        'groups'  => [],
        'today'   => date('Y-m-d'),
    ]);
    exit;
}

/* ============================================================================
 * Folios (guest bills)
 *  - GET  /folios           → index (list of folios)
 *  - GET  /folios/{id}      → show one folio
 * ========================================================================== */
if ($first === 'folios') {
    if (!$has(\Modules\hotelflow\Controllers\FoliosController::class)) {
        // Soft fallback: empty list but UI still loads
        $render('folios/index', [
            'title'       => 'Folios & guest bills',
            'folios'      => [],
            'summary'     => ['open' => 0, 'closed' => 0, 'balance' => 0],
            'module_base' => $ctx['module_base'] ?? '/apps/hotelflow',
        ]);
        exit;
    }

    $ctl = new \Modules\hotelflow\Controllers\FoliosController();

    // GET /folios
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        exit;
    }

    // GET /folios/{id}
    if ($second !== '' && $second !== null && ctype_digit((string)$second) && $method === 'GET') {
        $ctl->show($ctx, (int)$second);
        exit;
    }

    $notFound('Unknown folios route.');
}

/* ============================================================================
 * Restaurant & F&B — Coming Soon
 *  - GET /restaurant          → coming soon page
 * ========================================================================== */
if ($first === 'restaurant') {

    // If one day you add a RestaurantController, this will use that.
    if ($has(\Modules\hotelflow\Controllers\RestaurantController::class)) {
        $ctl = new \Modules\hotelflow\Controllers\RestaurantController();

        if (($second === '' || $second === null) && $method === 'GET') {
            $ctl->index($ctx);
            exit;
        }

        $notFound('Unknown restaurant route.');
    }

    // For now: simple coming-soon page using view only
    $render('restaurant/index', [
        'title'       => 'Restaurant & F&B — Coming soon',
        'module_base' => $ctx['module_base'] ?? '/apps/hotelflow',
    ]);
    exit;
}


/* ============================================================================
 * Settings / Branding  (HotelFlow)
 *  - GET  /settings  → branding form
 *  - POST /settings  → save branding
 * ========================================================================== */
if ($first === 'settings') {

    if ($has(\Modules\hotelflow\Controllers\SettingsController::class)) {
        $ctl = new \Modules\hotelflow\Controllers\SettingsController();

        if ($method === 'GET') {
            $ctl->index($ctx);
            exit;
        }

        if ($method === 'POST') {
            $ctl->save($ctx);
            exit;
        }

        $notFound('Unknown settings route.');
    }

    // Fallback if controller missing
    $render('settings/index', [
        'title'    => 'Hotel Branding',
        'branding' => [
            'business_name' => 'Your Hotel Name',
            'address'       => '',
            'phone'         => '',
            'email'         => '',
            'website'       => '',
            'logo_path'     => '',
        ],
        'module_base' => $ctx['module_base'] ?? '/apps/hotelflow',
    ]);
    exit;
}


/* ============================================================================
 * API Lookup (Unified, used by KF.lookup)
 *   GET /api/lookup/{entity}?q=...&limit=...
 *   entity ∈ guests|rooms|reservations|staff|folios
 * ========================================================================== */
if ($first === 'api' && $second === 'lookup') {

    if (!$has(\Modules\hotelflow\Controllers\LookupController::class)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['items' => []]);
        exit;
    }

    $entity = (string)($third ?? '');
    if ($entity === '') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
        }
        echo json_encode(['items' => []]);
        exit;
    }

    $ctl = new \Modules\hotelflow\Controllers\LookupController();
    $ctl->handle($ctx, $entity);
    exit;
}



/* ============================================================================
 * API Lookup
 * ========================================================================== */


if ($first === 'api' && $second === 'lookup') {
    $ctl = new LookupController();

    // GET /apps/hotelflow/api/lookup
    if ($third === '' || $third === null) {
        $ctl->index($ctx); exit;
    }

    // GET /apps/hotelflow/api/lookup/{entity}
    $entity = $third;
    $ctl->handle($ctx, $entity); exit;
}



/* ============================================================================
 * 18. Final fallback
 * ========================================================================== */

$notFound("Unknown route: /{$tail}");