<?php
declare(strict_types=1);
/**
 * Template: Generic Show (key/value)
 * Expects:
 *  - $title
 *  - $record: assoc array
 *  - $fields: [['key'=>'name','label'=>'Name'], ...]
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$title  = (string)($title ?? 'Details');
$record = (array)($record ?? []);
$fields = (array)($fields ?? []);

echo '<h1 class="text-xl font-bold mb-4">'.$h($title).'</h1>';
echo '<div class="grid gap-3 max-w-3xl">';

foreach ($fields as $f) {
  $k = (string)($f['key'] ?? '');
  $label = (string)($f['label'] ?? $k);
  $val = (string)($record[$k] ?? '');
  echo '<div class="p-3 rounded-lg border bg-white dark:bg-gray-900 dark:border-gray-700">';
  echo '<div class="text-xs text-slate-500">'.$h($label).'</div>';
  echo '<div class="font-semibold">'.$h($val).'</div>';
  echo '</div>';
}

echo '</div>';