<?php
declare(strict_types=1);
/**
 * Shared Component: Table
 * Props:
 *  - headers: [['key'=>'name','label'=>'Name'], ...]
 *  - rows:    array of associative arrays
 *  - empty:   empty text (default: 'No records')
 * Optional per-row actions:
 *  - row['__actions'] = [['href'=>'/x','label'=>'Open'], ...]
 */
if (!function_exists('render_table')) {
  function render_table(array $p): string {
    $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $headers = (array)($p['headers'] ?? []);
    $rows    = (array)($p['rows'] ?? []);
    $empty   = (string)($p['empty'] ?? 'No records');

    ob_start();
    ?>
    <div class="overflow-x-auto border rounded-xl dark:border-gray-700">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800/70">
          <tr>
            <?php foreach ($headers as $th): ?>
              <th class="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">
                <?= $h((string)($th['label'] ?? '')) ?>
              </th>
            <?php endforeach; ?>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900">
        <?php if (!$rows): ?>
          <tr>
            <td class="px-3 py-3 text-slate-500" colspan="<?= count($headers)+1 ?>">
              <?= $h($empty) ?>
            </td>
          </tr>
        <?php else: foreach ($rows as $r): ?>
          <tr class="border-t dark:border-gray-800">
            <?php foreach ($headers as $th): $k=(string)($th['key'] ?? ''); ?>
              <td class="px-3 py-2">
                <?= $h((string)($r[$k] ?? '')) ?>
              </td>
            <?php endforeach; ?>
            <td class="px-3 py-2 text-right">
              <?php if (isset($r['__actions']) && is_array($r['__actions'])): ?>
                <div class="inline-flex gap-2">
                  <?php foreach ($r['__actions'] as $a):
                    $href=(string)($a['href'] ?? '#');
                    $label=(string)($a['label'] ?? 'Open'); ?>
                    <a class="btn" href="<?= $h($href) ?>"><?= $h($label) ?></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    return (string)ob_get_clean();
  }
}