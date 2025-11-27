<?php
declare(strict_types=1);

use Shared\View;
use Modules\medflow\Controllers\{
  DashboardController,
  SalesController,
  InventoryController,
  ReportsController
};

// ---------- trust what front.php gave us ----------
$base   = rtrim((string)($ctx['module_base'] ?? '/apps/medflow'), '/');
$tail   = isset($tail) ? trim((string)$tail, '/') : '';   // DO NOT override
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$segments = ($tail === '') ? [] : explode('/', $tail);
$first    = $segments[0] ?? '';
$second   = $segments[1] ?? '';
$third    = $segments[2] ?? '';

// ---------- quick debugger ----------
if (isset($_GET['_meddbg'])) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "routes.php\n__FILE__ = ".__FILE__."\n";
  echo "base      = {$base}\n";
  echo "uri       = ".(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')."\n";
  echo "tail      = {$tail}\n";
  echo "first     = {$first}\n";
  echo "second    = {$second}\n";
  echo "method    = {$method}\n";
  exit;
}

// ---------- helpers ----------
$soft404 = function(string $msg = 'Not found') use ($base, $ctx): void {
  View::render('shared/errors/404', [
    'scope'       => 'tenant',
    'layout'      => null,
    'title'       => '404 — Not Found',
    'message'     => $msg,
    'module_base' => $base,
    'org'         => $ctx['org'] ?? [],
  ]);
  exit;
};
$bad = function(array $allow): void {
  http_response_code(405);
  header('Allow: ' . implode(', ', array_unique(array_map('strtoupper', $allow))));
  echo 'Method Not Allowed'; exit;
};
$call = function (string $fqcn, string $methodName, array $args = []) use ($ctx, $soft404): void {
  if (!class_exists($fqcn) || !method_exists($fqcn, $methodName)) {
    $soft404('Route exists but screen is not wired yet.');
  }
  (new $fqcn($ctx))->{$methodName}(...$args); exit;
};

// ======================================================================
// BULLETPROOF “it must work” rule (test first):
// /t/{slug}/apps/medflow/sales/new  → SalesController@create
// ======================================================================
if ($tail === 'sales/new') {
  if ($method === 'GET') { $call(SalesController::class, 'create'); }
  $bad(['GET']);
}

// ---------------- Overview ----------------
if ($tail === '' || $first === 'dashboard') {
  $call(DashboardController::class, 'index');
}

/* ---- Sales ---- */
if ($first === 'sales') {
  if ($second === '') {
    if ($method === 'GET')  { $call(\Modules\medflow\Controllers\SalesController::class, 'index'); }
    if ($method === 'POST') { $call(\Modules\medflow\Controllers\SalesController::class, 'store'); }
    $bad(['GET','POST']);
  }

  if ($second === 'new' || $second === 'create') {
    if ($method === 'GET') { $call(\Modules\medflow\Controllers\SalesController::class, 'create'); }
    $bad(['GET']);
  }

  // /sales/123  | /sales/123.html | /sales/123.json
  if (preg_match('/^(\d+)(?:\.(?:html|json))?$/', $second, $m)) {
    $id = (int)$m[1];
    if ($third === '' || $third === null) {
      if ($method === 'GET') { $call(\Modules\medflow\Controllers\SalesController::class, 'show', [$id]); }
      if ($method === 'POST'){ $call(\Modules\medflow\Controllers\SalesController::class, 'update', [$id]); }
      $bad(['GET','POST']);
    }
  }

  $soft404('Unknown sales route.');
}

/* /sales/returns … */
if ($second === 'returns') {
  // /sales/returns
  if ($third === '' && $method === 'GET') {
    $call(\Modules\medflow\Controllers\SalesReturnsController::class, 'index');
  }

  // /sales/returns/new
  if ($third === 'new' && $method === 'GET') {
    $call(\Modules\medflow\Controllers\SalesReturnsController::class, 'create');
  }

  // /sales/returns/{id}
  if (preg_match('/^(\d+)(?:\.(?:html|json))?$/', $third ?? '', $m)) {
    $id = (int)$m[1];
    if ($method === 'GET')  { $call(\Modules\medflow\Controllers\SalesReturnsController::class, 'show',   [$id]); }
    if ($method === 'POST') { $call(\Modules\medflow\Controllers\SalesReturnsController::class, 'update', [$id]); }
  }

  // /sales/returns/{id}/delete
  if (preg_match('/^\d+$/', $third ?? '') && ($segments[3] ?? '') === 'delete' && $method === 'POST') {
    $call(\Modules\medflow\Controllers\SalesReturnsController::class, 'destroy', [(int)$third]);
  }

  // we handled /sales/returns branch
  exit;
}

// ---------------- Inventory (examples) ----
if ($first === 'inventory') {
  if ($second === '' && $method === 'GET') { $call(InventoryController::class, 'items'); }
  if ($second === 'items' && $method === 'GET') { $call(InventoryController::class, 'items'); }
  if ($second === 'batches' && $method === 'GET') { $call(InventoryController::class, 'batches'); }
  $soft404('Unknown inventory route.');
}

// ---------------- Reports (example) -------
if ($first === 'reports') {
  if ($second === '' && $method === 'GET') { $call(ReportsController::class, 'index'); }
  $soft404('Unknown reports route.');
}

// Fallback
$soft404();