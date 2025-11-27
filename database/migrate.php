<?php
// File: database/migrate.php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/bootstrap/Kernel.php';

use Shared\DB;

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure migrations table
$pdo->exec("
  CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$cmd = $argv[1] ?? 'up'; // up|status|down
$dir = BASE_PATH.'/database/migrations';
if (!is_dir($dir)) { fwrite(STDERR, "No migrations directory at $dir\n"); exit(1); }

function appliedSet(PDO $pdo): array {
  $applied = [];
  foreach ($pdo->query("SELECT filename FROM schema_migrations") as $r) {
    $applied[$r['filename']] = true;
  }
  return $applied;
}

function runSql(PDO $pdo, string $sql): void {
  foreach (array_filter(array_map('trim', preg_split('/;[\r\n]+/',$sql))) as $chunk) {
    if ($chunk !== '') $pdo->exec($chunk);
  }
}

$files = glob($dir.'/*');
sort($files, SORT_NATURAL);
$applied = appliedSet($pdo);

switch ($cmd) {
  case 'status':
    foreach ($files as $f) {
      $base = basename($f);
      $mark = isset($applied[$base]) ? '[X]' : '[ ]';
      echo "{$mark} {$base}\n";
    }
    exit(0);

  case 'down':
    // Roll back the LAST applied migration (if it has a .down handler)
    $stmt = $pdo->query("SELECT filename FROM schema_migrations ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if (!$last) { echo "Nothing to rollback.\n"; exit(0); }
    $path = $dir.'/'.$last;
    if (!is_file($path)) { echo "Missing migration file: {$last}\n"; exit(1); }

    if (str_ends_with($path, '.php')) {
      $mig = require $path;
      $down = $mig['down'] ?? null;
      if (!$down) { echo "No down() for {$last}\n"; exit(1); }
      $pdo->beginTransaction();
      try {
        is_callable($down) ? $down($pdo) : runSql($pdo, (string)$down);
        $del = $pdo->prepare("DELETE FROM schema_migrations WHERE filename=?");
        $del->execute([$last]);
        $pdo->commit();
        echo "Rolled back: {$last}\n";
      } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    } elseif (preg_match('/\.up\.sql$/', $path)) {
      $downSql = substr($path, 0, -7).'.down.sql';
      if (!is_file($downSql)) { echo "Missing {$downSql}\n"; exit(1); }
      $pdo->beginTransaction();
      try {
        runSql($pdo, file_get_contents($downSql));
        $del = $pdo->prepare("DELETE FROM schema_migrations WHERE filename=?");
        $del->execute([$last]);
        $pdo->commit();
        echo "Rolled back: {$last}\n";
      } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    } else {
      echo "Unsupported migration type: {$last}\n"; exit(1);
    }
    exit(0);

  case 'up':
  default:
    foreach ($files as $path) {
      $base = basename($path);
      if (isset($applied[$base])) continue;

      echo "Applying: {$base} ... ";
      if (str_ends_with($path, '.php')) {
        $mig = require $path;
        $up = $mig['up'] ?? null;
        if (!$up) { echo "SKIP (no up)\n"; continue; }

        $pdo->beginTransaction();
        try {
          is_callable($up) ? $up($pdo) : runSql($pdo, (string)$up);
          $ins = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
          $ins->execute([$base]);
          $pdo->commit();
          echo "OK\n";
        } catch (Throwable $e) { $pdo->rollBack(); echo "ERROR: ".$e->getMessage()."\n"; exit(1); }
      } elseif (preg_match('/\.up\.sql$/', $path)) {
        $pdo->beginTransaction();
        try {
          runSql($pdo, file_get_contents($path));
          $ins = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
          $ins->execute([$base]);
          $pdo->commit();
          echo "OK\n";
        } catch (Throwable $e) { $pdo->rollBack(); echo "ERROR: ".$e->getMessage()."\n"; exit(1); }
      } else {
        echo "SKIP (unsupported)\n";
      }
    }
    echo "Done.\n";
    exit(0);
}