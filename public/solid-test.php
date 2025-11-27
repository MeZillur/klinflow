<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/solid_asset.php';

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>KlinFlow Solid Test</title>
  </head>
  <body>
    <div id="app"></div>

    <!-- Solid bundle injected via helper -->
    <script type="module" src="<?= h(solid_asset()) ?>"></script>
  </body>
</html>
