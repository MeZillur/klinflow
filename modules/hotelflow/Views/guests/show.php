<?php
/** @var array $guest */
/** @var array $reservations */
/** @var string $module_base */
/** @var array $org */

$h   = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$fullName = (string)($guest['full_name'] ?? '');
$code     = (string)($guest['code'] ?? '');
$country  = (string)($guest['country'] ?? '');
$mobile   = (string)($guest['mobile'] ?? '');
$email    = (string)($guest['email'] ?? '');
$city     = (string)($guest['city'] ?? '');
$lastStay = (string)($guest['last_stay_at'] ?? '');

$idFront  = (string)($guest['bio_id_front_path'] ?? '');
$idBack   = (string)($guest['bio_id_back_path'] ?? '');
$facePath = (string)($guest['bio_face_path'] ?? '');

$verAt    = (string)($guest['bio_last_verified_at'] ?? '');
$verScore = (string)($guest['bio_last_verified_score'] ?? '');
$verSrc   = (string)($guest['bio_last_verified_source'] ?? '');
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-extrabold"><?= $h($fullName ?: 'Guest Profile') ?></h1>
        <?php if ($code !== ''): ?>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs border bg-slate-50">
            CODE: <?= $h($code) ?>
          </span>
        <?php endif; ?>
      </div>
      <p class="text-sm text-slate-500 mt-1">
        View guest details, photo ID and biometric history. Useful for any incident investigation or verification.
      </p>
    </div>
    <a href="<?= $h($base) ?>/guests"
       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm hover:bg-slate-50">
      <i class="fa fa-arrow-left"></i><span>Back to Guests</span>
    </a>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)] gap-6">

    <!-- Left: Guest info + reservations -->
    <div class="space-y-4">
      <!-- Guest info card -->
      <section class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-base font-semibold">Guest details</h2>
          <?php if ($lastStay !== ''): ?>
            <span class="text-xs text-slate-500">
              Last stay: <?= $h($lastStay) ?>
            </span>
          <?php endif; ?>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div>
            <dt class="text-slate-500">Full name</dt>
            <dd class="font-medium"><?= $h($fullName) ?></dd>
          </div>
          <div>
            <dt class="text-slate-500">Mobile</dt>
            <dd class="font-medium"><?= $h($mobile) ?: '—' ?></dd>
          </div>
          <div>
            <dt class="text-slate-500">Email</dt>
            <dd class="font-medium"><?= $h($email) ?: '—' ?></dd>
          </div>
          <div>
            <dt class="text-slate-500">Country / City</dt>
            <dd class="font-medium">
              <?= $h($country ?: '—') ?>
              <?php if ($city !== ''): ?> · <?= $h($city) ?><?php endif; ?>
            </dd>
          </div>
        </dl>
      </section>

      <!-- Reservations card -->
      <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-semibold">Recent stays</h2>
          <span class="text-xs text-slate-500">
            <?= count($reservations) ?> record(s)
          </span>
        </div>

        <?php if (!$reservations): ?>
          <p class="text-sm text-slate-500">No reservations found for this guest yet.</p>
        <?php else: ?>
          <div class="overflow-x-auto -mx-3 sm:mx-0">
            <table class="min-w-full text-sm border-separate border-spacing-y-1">
              <thead>
              <tr class="text-xs uppercase text-slate-500">
                <th class="text-left px-3 py-1.5">Code</th>
                <th class="text-left px-3 py-1.5">Room</th>
                <th class="text-left px-3 py-1.5">Check-in</th>
                <th class="text-left px-3 py-1.5">Check-out</th>
                <th class="text-left px-3 py-1.5">Status</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($reservations as $r): ?>
                <tr class="bg-slate-50">
                  <td class="px-3 py-1.5 font-mono text-xs">
                    <?= $h((string)$r['code']) ?>
                  </td>
                  <td class="px-3 py-1.5">
                    <?= $h((string)($r['room_no'] ?? '')) ?>
                    <?php if (!empty($r['room_type_name'])): ?>
                      <span class="text-xs text-slate-500">
                        (<?= $h((string)$r['room_type_name']) ?>)
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-1.5"><?= $h((string)$r['check_in']) ?></td>
                  <td class="px-3 py-1.5"><?= $h((string)$r['check_out']) ?></td>
                  <td class="px-3 py-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs
                                  <?= $r['status']==='checked_in' ? 'bg-emerald-50 text-emerald-700' :
                                      ($r['status']==='cancelled' ? 'bg-rose-50 text-rose-700'
                                                                  : 'bg-slate-100 text-slate-700') ?>">
                      <?= $h((string)$r['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <!-- Right: Biometric / photo & ID -->
    <aside class="space-y-4">
      <!-- Face -->
      <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-semibold">Guest photo (biometric)</h2>
          <?php if ($facePath !== ''): ?>
            <a href="<?= $h($base.'/'.$facePath) ?>" target="_blank"
               class="text-xs text-emerald-700 hover:underline">
              View full size
            </a>
          <?php endif; ?>
        </div>
        <?php if ($facePath !== ''): ?>
          <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
            <img src="<?= $h($base.'/'.$facePath) ?>"
                 alt="Guest face"
                 class="w-full object-cover">
          </div>
        <?php else: ?>
          <p class="text-sm text-slate-500">
            No biometric face capture stored yet.
          </p>
        <?php endif; ?>

        <?php if ($verAt !== '' || $verScore !== '' || $verSrc !== ''): ?>
          <dl class="mt-3 text-xs text-slate-500 space-y-1">
            <?php if ($verAt !== ''): ?>
              <div><span class="font-medium">Last verification:</span> <?= $h($verAt) ?></div>
            <?php endif; ?>
            <?php if ($verScore !== ''): ?>
              <div><span class="font-medium">Match score:</span> <?= $h($verScore) ?></div>
            <?php endif; ?>
            <?php if ($verSrc !== ''): ?>
              <div><span class="font-medium">Source:</span> <?= $h($verSrc) ?></div>
            <?php endif; ?>
          </dl>
        <?php endif; ?>
      </section>

      <!-- ID front -->
      <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-semibold">ID card — front</h2>
          <?php if ($idFront !== ''): ?>
            <a href="<?= $h($base.'/'.$idFront) ?>" target="_blank"
               class="text-xs text-emerald-700 hover:underline">
              View full size
            </a>
          <?php endif; ?>
        </div>
        <?php if ($idFront !== ''): ?>
          <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
            <img src="<?= $h($base.'/'.$idFront) ?>"
                 alt="Guest ID front"
                 class="w-full object-cover">
          </div>
        <?php else: ?>
          <p class="text-sm text-slate-500">
            No front side ID image stored.
          </p>
        <?php endif; ?>
      </section>

      <!-- ID back -->
      <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-semibold">ID card — back</h2>
          <?php if ($idBack !== ''): ?>
            <a href="<?= $h($base.'/'.$idBack) ?>" target="_blank"
               class="text-xs text-emerald-700 hover:underline">
              View full size
            </a>
          <?php endif; ?>
        </div>
        <?php if ($idBack !== ''): ?>
          <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
            <img src="<?= $h($base.'/'.$idBack) ?>"
                 alt="Guest ID back"
                 class="w-full object-cover">
          </div>
        <?php else: ?>
          <p class="text-sm text-slate-500">
            No back side ID image stored.
          </p>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</div>