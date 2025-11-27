<?php
/** @var array $res @var array $rooms @var array $charges @var array $payments @var array $events @var string $module_base */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

/**
 * Normalise stored biometric path into a web URL.
 * - If it already starts with http/https or '/', use as-is.
 * - Otherwise assume it's relative to hotelflow Assets/storage.
 */
$photoUrl = function (?string $path): ?string {
    $p = trim((string)$path);
    if ($p === '') return null;
    if (preg_match('#^https?://#i', $p)) return $p;
    if ($p[0] === '/') return $p;
    // relative path stored, prefix module Assets dir
    return '/modules/hotelflow/Assets/storage/' . ltrim($p, '/');
};

// NOTE: controller SELECT already aliases these columns from hms_guests
$faceUrl    = $photoUrl($res['bio_face_path']      ?? null);
$idFrontUrl = $photoUrl($res['bio_id_front_path']  ?? null);
$idBackUrl  = $photoUrl($res['bio_id_back_path']   ?? null);
?>
<div class="max-w-[1100px] mx-auto space-y-6">
  <div class="flex items-start justify-between gap-4">
    <div>
      <div class="text-xs text-slate-500">Reservation</div>
      <h1 class="text-2xl font-extrabold"><?= $h((string)$res['code']) ?></h1>
      <div class="text-sm text-slate-500"><?= $h((string)($res['guest_name'] ?? 'Guest')) ?></div>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="post" action="<?= $h($base) ?>/reservations/<?= (int)$res['id'] ?>/check-in">
        <button class="px-3 py-2 rounded-lg border hover:bg-slate-50 text-sm">
          <i class="fa-solid fa-door-open mr-1"></i>Check-in
        </button>
      </form>
      <form method="post" action="<?= $h($base) ?>/reservations/<?= (int)$res['id'] ?>/check-out">
        <button class="px-3 py-2 rounded-lg border hover:bg-slate-50 text-sm">
          <i class="fa-solid fa-door-closed mr-1"></i>Check-out
        </button>
      </form>
      <form method="post"
            action="<?= $h($base) ?>/reservations/<?= (int)$res['id'] ?>/cancel"
            onsubmit="return confirm('Cancel this reservation?')">
        <button class="px-3 py-2 rounded-lg border border-red-300 text-red-700 hover:bg-red-50 text-sm">
          <i class="fa-solid fa-ban mr-1"></i>Cancel
        </button>
      </form>
    </div>
  </div>

  <?php $tab='reservations'; include __DIR__.'/../frontdesk/_tabs.php'; ?>

  <!-- Top summary -->
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
    <div class="rounded-xl border border-slate-200 p-3">
      <div class="text-xs text-slate-500">Status</div>
      <div class="font-semibold"><?= $h((string)$res['status']) ?></div>
    </div>
    <div class="rounded-xl border border-slate-200 p-3">
      <div class="text-xs text-slate-500">Dates</div>
      <div class="font-semibold">
        <?= $h((string)$res['check_in']) ?> → <?= $h((string)$res['check_out']) ?>
      </div>
    </div>
    <div class="rounded-xl border border-slate-200 p-3">
      <div class="text-xs text-slate-500">Channel</div>
      <div class="font-semibold"><?= $h((string)($res['channel'] ?? 'Direct')) ?></div>
    </div>
    <div class="rounded-xl border border-slate-200 p-3 text-right">
      <div class="text-xs text-slate-500">Balance</div>
      <div class="font-semibold">
        <?= isset($res['balance_due']) ? number_format((float)$res['balance_due'], 2) : '—' ?>
        <?php if (!empty($res['currency'])): ?>
          <span class="text-xs text-slate-500"><?= $h((string)$res['currency']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- LEFT: rooms + charges + payments -->
    <div class="lg:col-span-2 space-y-4">
      <!-- Rooms -->
      <div class="rounded-xl border border-slate-200 p-4">
        <div class="font-semibold mb-2">Rooms</div>
        <ul class="divide-y divide-slate-200">
          <?php if (!$rooms): ?>
            <li class="py-2 text-sm text-slate-500">No room components.</li>
          <?php endif; ?>
          <?php foreach ($rooms as $rr): ?>
            <li class="py-2 flex items-center justify-between">
              <div>
                <div class="font-medium">
                  <?= $h((string)($rr['room_type'] ?? 'Room')) ?>
                </div>
                <div class="text-xs text-slate-500">
                  <?= $h((string)($rr['rate_plan'] ?? 'Rate')) ?>
                </div>
              </div>
              <div class="text-sm text-slate-600">
                <?= (int)($rr['adults'] ?? 0) ?> adult(s),
                <?= (int)($rr['children'] ?? 0) ?> child(ren)
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Charges -->
      <div class="rounded-xl border border-slate-200 p-4">
        <div class="font-semibold mb-2">Charges</div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="p-2 text-left">Date</th>
              <th class="p-2 text-left">Code</th>
              <th class="p-2 text-left">Description</th>
              <th class="p-2 text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
          <?php if (!$charges): ?>
            <tr>
              <td colspan="4" class="p-3 text-slate-500">No charges yet.</td>
            </tr>
          <?php endif; ?>
          <?php foreach ($charges as $ln): ?>
            <tr>
              <td class="p-2"><?= $h((string)($ln['service_date'] ?? '')) ?></td>
              <td class="p-2"><?= $h((string)($ln['code'] ?? '')) ?></td>
              <td class="p-2"><?= $h((string)($ln['description'] ?? '')) ?></td>
              <td class="p-2 text-right">
                <?= number_format((float)($ln['amount'] ?? 0), 2) ?>
                <?php if (!empty($ln['currency'])): ?>
                  <span class="text-xs text-slate-500">
                    <?= $h((string)$ln['currency']) ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Payments -->
      <div class="rounded-xl border border-slate-200 p-4">
        <div class="font-semibold mb-2">Payments</div>
        <ul class="divide-y divide-slate-200 text-sm">
          <?php if (!$payments): ?>
            <li class="py-2 text-slate-500">No payments yet.</li>
          <?php endif; ?>
          <?php foreach ($payments as $p): ?>
            <li class="py-2 flex items-center justify-between">
              <div>
                <div class="font-medium">
                  <?= number_format((float)($p['amount'] ?? 0), 2) ?>
                  <?php if (!empty($p['currency'])): ?>
                    <span class="text-xs text-slate-500">
                      <?= $h((string)$p['currency']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="text-xs text-slate-500">
                  <?= $h((string)($p['created_at'] ?? '')) ?>
                  <?php if (!empty($p['note'])): ?>
                    · <?= $h((string)$p['note']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- RIGHT: Photo & ID + notes + activity -->
    <div class="space-y-4">
      <!-- Photo & ID -->
      <div class="rounded-xl border border-slate-200 p-4">
        <div class="font-semibold mb-3">Photo &amp; ID</div>
        <div class="grid grid-cols-3 gap-3 text-xs">
          <!-- Face -->
          <div class="border border-dashed border-slate-300 rounded-lg p-2 flex flex-col items-center justify-center text-center min-h-[96px]">
            <div class="font-semibold mb-1">Face</div>
            <?php if ($faceUrl): ?>
              <img src="<?= $h($faceUrl) ?>"
                   alt="Guest face"
                   class="w-full h-24 object-cover rounded-md border border-slate-200">
            <?php else: ?>
              <div class="text-slate-500">No face capture yet.</div>
            <?php endif; ?>
          </div>

          <!-- ID front -->
          <div class="border border-dashed border-slate-300 rounded-lg p-2 flex flex-col items-center justify-center text-center min-h-[96px]">
            <div class="font-semibold mb-1">ID Front</div>
            <?php if ($idFrontUrl): ?>
              <img src="<?= $h($idFrontUrl) ?>"
                   alt="ID front"
                   class="w-full h-24 object-cover rounded-md border border-slate-200">
            <?php else: ?>
              <div class="text-slate-500">No ID front captured.</div>
            <?php endif; ?>
          </div>

          <!-- ID back -->
          <div class="border border-dashed border-slate-300 rounded-lg p-2 flex flex-col items-center justify-center text-center min-h-[96px]">
            <div class="font-semibold mb-1">ID Back</div>
            <?php if ($idBackUrl): ?>
              <img src="<?= $h($idBackUrl) ?>"
                   alt="ID back"
                   class="w-full h-24 object-cover rounded-md border border-slate-200">
            <?php else: ?>
              <div class="text-slate-500">No ID back captured.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="rounded-xl border border-slate-200 p-4">
        <div class="font-semibold mb-2">Notes</div>
        <form method="post" action="<?= $h($base) ?>/reservations/<?= (int)$res['id'] ?>">
          <textarea name="notes" rows="6"
                    class="w-full px-3 py-2 rounded-lg border border-slate-300"><?= $h((string)($res['notes'] ?? '')) ?></textarea>
          <div class="mt-2 text-right">
            <button class="px-3 py-2 rounded-lg text-white text-sm"
                    style="background:var(--brand)">
              Save Notes
            </button>
          </div>
        </form>
      </div>

      <!-- Activity -->
      <div class="rounded-xl border border-slate-200 p-4">
        <div class="font-semibold mb-2">Activity</div>
        <ul class="divide-y divide-slate-200 text-sm">
          <?php if (!$events): ?>
            <li class="py-2 text-slate-500">No activity yet.</li>
          <?php endif; ?>
          <?php foreach ($events as $e): ?>
            <li class="py-2">
              <div class="font-medium"><?= $h((string)($e['event_code'] ?? '')) ?></div>
              <div class="text-xs text-slate-500">
                <?= $h((string)($e['created_at'] ?? '')) ?>
              </div>
              <?php if (!empty($e['note'])): ?>
                <div class="text-xs mt-1">
                  <?= $h((string)$e['note']) ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>