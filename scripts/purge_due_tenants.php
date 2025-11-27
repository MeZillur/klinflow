<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/bootstrap/Kernel.php';

use Shared\DB;

$pdo = DB::pdo(); // or DB::tenant() if that’s your shared connection

$ENABLED = getenv('APP_PURGE_ENABLED') === '1';
if (!$ENABLED) {
  fwrite(STDERR, "[purge] Disabled (APP_PURGE_ENABLED!=1)\n");
  exit(0);
}

// Pick one pending job due for purge (simple queue)
$pdo->beginTransaction();
$job = $pdo->query("
  SELECT * FROM purge_jobs
   WHERE status='pending' AND due_at <= UTC_TIMESTAMP()
   ORDER BY id
   LIMIT 1
  FOR UPDATE
")->fetch(PDO::FETCH_ASSOC);

if (!$job) {
  $pdo->commit();
  echo "[purge] No due jobs.\n";
  exit(0);
}

// Mark running
$pdo->prepare("UPDATE purge_jobs SET status='running', started_at=UTC_TIMESTAMP(), attempts=attempts+1 WHERE id=?")
    ->execute([$job['id']]);
$pdo->commit();

$orgId  = (int)$job['org_id'];
$maxPer = 20000;   // rows per table per run (batched)
$chunk  = 2000;    // delete batch size
$totalDeleted = 0;
$errors = [];

echo "[purge] Start org_id={$orgId}\n";

// 1) If you have ON DELETE CASCADE rooted at `organizations.id`
//    you can simply delete the org row at the end and most child rows vanish.
//    But we still sweep tables explicitly so mixed schemas are fine.

$tables = $pdo->query("SELECT table_name, pk_name, org_column
                         FROM tenant_tables
                        WHERE active=1
                        ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);

foreach ($tables as $t) {
  $tbl = preg_replace('/[^A-Za-z0-9_]/','', $t['table_name']);
  $pk  = preg_replace('/[^A-Za-z0-9_]/','', $t['pk_name']);
  $col = preg_replace('/[^A-Za-z0-9_]/','', $t['org_column']);

  $deleted = 0;
  try {
    // loop in chunks to avoid long locks
    while (true) {
      $stmt = $pdo->prepare("SELECT {$pk} FROM {$tbl} WHERE {$col}=? LIMIT {$chunk}");
      $stmt->execute([$orgId]);
      $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
      if (!$ids) break;

      $in  = implode(',', array_fill(0, count($ids), '?'));
      $del = $pdo->prepare("DELETE FROM {$tbl} WHERE {$pk} IN ({$in})");
      $del->execute($ids);

      $deleted += $del->rowCount();
      $totalDeleted += $del->rowCount();

      if ($deleted >= $maxPer) { // safety stop per table per run
        echo "[purge] {$tbl}: hit per-table limit {$maxPer}, will continue next run.\n";
        break;
      }
      // brief pause to be nice to the server
      usleep(50000);
    }
    if ($deleted) echo "[purge] {$tbl}: deleted {$deleted}\n";
  } catch (Throwable $e) {
    $errors[] = "{$tbl}: ".$e->getMessage();
  }
}

// 2) Finally remove the organization row itself.
//    If you rely on ON DELETE CASCADE relationships, this will clean the rest.
try {
  $stmt = $pdo->prepare("DELETE FROM organizations WHERE id=?");
  $stmt->execute([$orgId]);
  echo "[purge] organizations: deleted row for org {$orgId}\n";
} catch (Throwable $e) {
  $errors[] = "organizations: ".$e->getMessage();
}

if ($errors) {
  $msg = implode("\n", $errors);
  $pdo->prepare("UPDATE purge_jobs SET status='error', last_error=?, finished_at=UTC_TIMESTAMP() WHERE id=?")
      ->execute([$msg, $job['id']]);
  fwrite(STDERR, "[purge] ERROR:\n$msg\n");
  exit(2);
}

$pdo->prepare("UPDATE purge_jobs SET status='done', finished_at=UTC_TIMESTAMP() WHERE id=?")
    ->execute([$job['id']]);

echo "[purge] Done. Total rows deleted ~ {$totalDeleted}\n";