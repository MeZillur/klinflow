<?php
declare(strict_types=1);
/**
 * Template: Generic List
 * Expects variables:
 *  - $title
 *  - $headers, $rows (table.php props)
 *  - $createUrl (optional)
 *  - $empty (optional)
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . '/../components/button.php';
require_once __DIR__ . '/../components/table.php';

$title     = (string)($title ?? 'List');
$createUrl = (string)($createUrl ?? '');

echo '<div class="flex items-center justify-between mb-4">';
echo '<h1 class="text-xl font-bold">'.$h($title).'</h1>';
if ($createUrl) {
  echo render_button(['label'=>'Create','variant'=>'primary','href'=>$createUrl,'icon'=>'fa fa-plus']);
}
echo '</div>';

echo render_table([
  'headers' => $headers ?? [],
  'rows'    => $rows ?? [],
  'empty'   => $empty ?? 'No records',
]);