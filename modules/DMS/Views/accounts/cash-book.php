<?php
/**
 * Expected:
 * - $title: 'Cash Book' | 'Bank Book' | 'Mobile Bank Book'
 * - $from, $to: date filters
 * - $rows: book lines
 */
$title = $title ?? 'Cash Book';
$from = $from ?? '';
$to   = $to   ?? '';
$rows = $rows ?? [];
?>
<div class="card">
  <div class="card-header flex items-center justify-between">
    <h1><?= htmlspecialchars($title) ?></h1>
    <?php
      $filters = ['from'=>$from,'to'=>$to];
      $applyAction = $_SERVER['REQUEST_URI'] ?? '';
      $extras = [];
      include __DIR__ . '/partials/filters_bar.php';
    ?>
  </div>

  <div class="p-3">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:120px">Date</th>
          <th style="width:120px">JNO</th>
          <th>Memo</th>
          <th style="width:120px" class="text-right">Debit</th>
          <th style="width:120px" class="text-right">Credit</th>
          <th style="width:120px" class="text-right">Δ</th>
          <th style="width:140px" class="text-right">Balance</th>
          <th style="width:120px">Cleared</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['jdate']) ?></td>
            <td><?= htmlspecialchars($r['jno'] ?? '') ?></td>
            <td class="text-sm"><?= htmlspecialchars($r['memo'] ?? '') ?></td>
            <td class="text-right"><?= number_format((float)($r['dr'] ?? 0),2) ?></td>
            <td class="text-right"><?= number_format((float)($r['cr'] ?? 0),2) ?></td>
            <td class="text-right"><?= number_format((float)($r['delta'] ?? 0),2) ?></td>
            <td class="text-right"><?= number_format((float)($r['running_balance'] ?? 0),2) ?></td>
            <td class="text-sm opacity-80">
              <?= !empty($r['is_cleared']) ? 'Yes' : 'No' ?>
              <?php if (!empty($r['cleared_at'])): ?>
                <span class="opacity-60"> (<?= htmlspecialchars($r['cleared_at']) ?>)</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>