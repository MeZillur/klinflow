<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} $base=rtrim((string)($module_base??''),'/'); ?>
<div class="flex items-center justify-between mb-3">
  <h2 class="text-xl font-semibold">Bookings</h2>
  <a href="<?= h($base.'/bookings/create') ?>" class="px-3 py-2 rounded bg-emerald-600 text-white">New Booking</a>
</div>
<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-slate-50"><tr><th class="px-3 py-2">Booking</th><th class="px-3 py-2">Guest</th><th class="px-3 py-2">Room</th><th class="px-3 py-2">Check-in</th><th class="px-3 py-2">Check-out</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-right">Actions</th></tr></thead>
    <tbody>
      <?php if(empty($rows)): ?><tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">No bookings.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= h($r['booking_no'] ?? ('#'.$r['id'])) ?></td>
          <td class="px-3 py-2"><?= h($r['customer_name'] ?? '') ?></td>
          <td class="px-3 py-2"><?= h($r['room_no'] ?? '') ?></td>
          <td class="px-3 py-2"><?= h(substr((string)($r['checkin_date'] ?? ''),0,10)) ?></td>
          <td class="px-3 py-2"><?= h(substr((string)($r['checkout_date'] ?? ''),0,10)) ?></td>
          <td class="px-3 py-2"><?= h(ucfirst((string)($r['status'] ?? ''))) ?></td>
          <td class="px-3 py-2 text-right">
            <a class="text-emerald-700 hover:underline" href="<?= h($base.'/bookings/'.(int)$r['id']) ?>">view</a> ·
            <a class="text-blue-700 hover:underline"    href="<?= h($base.'/bookings/'.(int)$r['id'].'/edit') ?>">edit</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>