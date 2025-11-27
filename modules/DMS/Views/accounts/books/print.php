<?php $f = $filters ?? []; ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title ?? 'Book Print') ?></title>
<link href="https://rsms.me/inter/inter.css" rel="stylesheet">
<style>
  html,body{font-family:'Inter var','Inter',system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
  table{border-collapse:collapse;width:100%} th,td{padding:6px 8px;border-bottom:1px solid #e5e7eb} th{text-align:left;background:#f8fafc}
  .tr{text-align:right}
  @media print { @page{size:A4 portrait;margin:12mm} body{margin:0} }
</style>
</head>
<body onload="setTimeout(()=>window.print(),50)">
  <h2 style="margin:0 0 8px"><?= htmlspecialchars($title ?? 'Book') ?></h2>
  <div style="margin:0 0 12px;color:#334155;font-size:12px">
    Period: <?= htmlspecialchars($f['from'] ?? '') ?> → <?= htmlspecialchars($f['to'] ?? '') ?>
  </div>
  <table>
    <thead><tr><th>Date</th><th>Ref</th><th>Memo</th><th class="tr">Dr</th><th class="tr">Cr</th><th class="tr">Δ</th><th class="tr">Running</th><th>Cleared</th></tr></thead>
    <tbody>
      <?php $run=(float)($opening??0); foreach (($rows ?? []) as $r): $d=((float)($r['dr']??0)-(float)($r['cr']??0)); $run+=$d; ?>
      <tr>
        <td><?= htmlspecialchars($r['jdate'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['jno'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['memo'] ?? '') ?></td>
        <td class="tr"><?= number_format((float)($r['dr'] ?? 0),2) ?></td>
        <td class="tr"><?= number_format((float)($r['cr'] ?? 0),2) ?></td>
        <td class="tr"><?= number_format($d,2) ?></td>
        <td class="tr"><?= number_format($run,2) ?></td>
        <td><?= !empty($r['is_cleared'])?'Yes':'No' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>