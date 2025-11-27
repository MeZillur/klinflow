<?php
$slug = $_SESSION['tenant_ctx']['slug'] ?? '';
$orgId= $_SESSION['tenant_ctx']['org_id'] ?? 0;

$pdo = class_exists(\Shared\DB::class) ? \Shared\DB::pdo() : null;
$hasDMS = false;
if ($pdo) {
  $st = $pdo->prepare("SELECT 1 FROM cp_org_modules WHERE org_id=? AND module_key='DMS' AND enabled=1 LIMIT 1");
  $st->execute([(int)$orgId]);
  $hasDMS = (bool)$st->fetchColumn();
}

if ($hasDMS) {
  $nav = require BASE_PATH.'/modules/DMS/nav.php';
  // render $nav['items'] ...
}
?>