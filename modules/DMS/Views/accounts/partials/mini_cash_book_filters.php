<?php
/** expects: $filters = [
 *   'account_id' => null|int,
 *   'from' => 'Y-m-d',
 *   'to' => 'Y-m-d',
 *   'cleared' => 'all'|'cleared'|'uncleared',
 *   'q' => 'memo/ref search'
 * ];
 * and $accounts = [ ['id'=>..., 'code'=>'1020', 'name'=>'Bank ABC'], ... ]
 */
$filters = $filters ?? [];
$accounts = $accounts ?? [];
$fmtOpt = function($id,$label,$selected){ return '<option value="'.htmlspecialchars((string)$id).'" '.($selected?'selected':'').'>'.htmlspecialchars($label).'</option>'; };
?>
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin:0 0 12px">
  <label class="muted">Account<br>
    <select name="account_id" style="padding:8px;border:1px solid #e5e7eb;border-radius:8px;min-width:240px">
      <option value="">All cash/bank</option>
      <?php foreach($accounts as $a):
        $lbl = trim(($a['code']??'').' — '.($a['name']??''));
        echo $fmtOpt((string)$a['id'], $lbl, (string)($filters['account_id']??'')===(string)$a['id']);
      endforeach; ?>
    </select>
  </label>

  <label class="muted">From<br>
    <input type="date" name="from" value="<?=htmlspecialchars((string)($filters['from'] ?? date('Y-m-01')))?>" style="padding:8px;border:1px solid #e5e7eb;border-radius:8px">
  </label>
  <label class="muted">To<br>
    <input type="date" name="to" value="<?=htmlspecialchars((string)($filters['to'] ?? date('Y-m-d')))?>" style="padding:8px;border:1px solid #e5e7eb;border-radius:8px">
  </label>

  <label class="muted">Clearing<br>
    <select name="cleared" style="padding:8px;border:1px solid #e5e7eb;border-radius:8px">
      <?php
        $val = (string)($filters['cleared'] ?? 'all');
        foreach(['all'=>'All','cleared'=>'Cleared','uncleared'=>'Uncleared'] as $k=>$v){
          echo $fmtOpt($k,$v,$k===$val);
        }
      ?>
    </select>
  </label>

  <label class="muted">Search<br>
    <input type="text" name="q" value="<?=htmlspecialchars((string)($filters['q'] ?? ''))?>" placeholder="memo / ref / jno" style="padding:8px;border:1px solid #e5e7eb;border-radius:8px;min-width:220px">
  </label>

  <div style="display:flex;gap:8px">
    <button type="submit" style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;background:#111827;color:#fff">Filter</button>
    <a href="?<?=http_build_query(['from'=>date('Y-m-01'),'to'=>date('Y-m-d'),'cleared'=>'all'])?>" class="no-print"
       style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;background:#fff;color:#111827">Reset</a>
    <a href="?<?=htmlspecialchars(http_build_query(array_merge($_GET,['print'=>1])))?>" class="no-print"
       style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;background:#10b981;color:#fff">Print</a>
  </div>
</form>
<?php if (($_GET['print'] ?? '') === '1'): ?>
<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),150));</script>
<?php endif; ?>