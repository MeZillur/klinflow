<?php
declare(strict_types=1);
/** @var array $rows @var int $page @var int $pages @var int $total @var string $base @var string $q */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$age = function (?string $created): string {
    if (!$created) return '';
    try {
        $d   = new DateTime($created);
        $now = new DateTime('now');
        $diff = $now->diff($d);

        if ($diff->y >= 1) {
            return $diff->y.'y '.($diff->m).'m';
        }
        if ($diff->m >= 1) {
            return $diff->m.'m '.$diff->d.'d';
        }
        return $diff->d.'d';
    } catch (Throwable $e) {
        return '';
    }
};
?>
<style>
  /* --- Segmented toggle + primary button (matches POS nav style) --- */
  .pos-toggle {
    display: inline-flex;
    align-items: stretch;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in oklab, var(--brand) 70%, transparent);
    overflow: hidden;
    background: var(--layer-1);
    box-shadow: var(--shadow-sm);
  }
  .pos-toggle button {
    border: 0;
    padding: .45rem 1.4rem;
    font-size: var(--fs-sm);
    font-weight: 600;
    cursor: pointer;
    background: transparent;
    color: var(--text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 4rem;
  }
  .pos-toggle button + button {
    border-left: 1px solid color-mix(in oklab, var(--brand) 40%, transparent);
  }
  .pos-toggle button.is-active {
    background: var(--brand);
    color: #fff;
  }
  .pos-toggle button:not(.is-active):hover {
    background: color-mix(in oklab, var(--brand) 6%, white);
  }
  html[data-theme="dark"] .pos-toggle {
    background: var(--layer-2);
  }
  html[data-theme="dark"] .pos-toggle button:not(.is-active):hover {
    background: color-mix(in oklab, var(--brand) 14%, black);
  }

  /* Customer cards for grid view */
  .customer-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    border: 1px solid var(--card-border);
    box-shadow: var(--shadow-sm);
    padding: var(--space-4);
    display: flex;
    flex-direction: column;
    gap: .35rem;
  }
  .customer-card-header {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    align-items: flex-start;
  }
  .customer-chip {
    font-size: var(--fs-xxs);
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted);
  }
  .status-pill {
    display: inline-flex;
    align-items: center;
    padding: .15rem .55rem;
    border-radius: 999px;
    font-size: var(--fs-xxs);
    font-weight: 600;
  }
  .status-pill.active {
    background: color-mix(in oklab, var(--brand) 15%, white);
    color: var(--brand-900);
    border: 1px solid color-mix(in oklab, var(--brand) 55%, transparent);
  }
  .status-pill.inactive {
    background: var(--layer-2);
    color: var(--text-muted);
    border: 1px solid var(--border);
  }

  html[data-theme="dark"] .customer-card {
    box-shadow: var(--shadow-sm);
  }

  /* table tweaks */
  .customers-table th,
  .customers-table td {
    padding: .6rem .75rem;
    font-size: var(--fs-sm);
    white-space: nowrap;
  }
  .customers-table th {
    text-align: left;
    font-weight: 600;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
  }
  .customers-table tbody tr {
    border-bottom: 1px solid var(--border);
  }
  .customers-table tbody tr:hover {
    background: color-mix(in oklab, var(--brand) 4%, white);
  }
  html[data-theme="dark"] .customers-table tbody tr:hover {
    background: color-mix(in oklab, var(--brand) 10%, black);
  }
</style>

<section
  x-data="{ mode: 'grid' }"
  class="max-w-6xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4 select-none"
>
  <!-- Header row -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h1 class="text-xl sm:text-2xl font-semibold">Customers</h1>
      <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
        Total customers: <strong><?= (int)$total ?></strong>
      </p>
    </div>

    <div class="flex items-center gap-3">
      <!-- Grid/List toggle -->
      <div class="pos-toggle">
        <button
          type="button"
          @click="mode = 'grid'"
          :class="mode === 'grid' ? 'is-active' : ''"
        >Grid</button>
        <button
          type="button"
          @click="mode = 'list'"
          :class="mode === 'list' ? 'is-active' : ''"
        >List</button>
      </div>

      <!-- New Customer (same green style as global primary) -->
      <a href="<?= $h($base) ?>/customers/create"
         class="btn btn-primary">
        New Customer
      </a>
    </div>
  </div>

  <!-- Search -->
  <form method="get" action="" class="flex flex-col sm:flex-row gap-2 sm:items-center">
    <div class="flex-1 min-w-0">
      <input
        name="q"
        value="<?= $h($q ?? '') ?>"
        placeholder="Search by name, phone, email, or code"
        class="input w-full"
      >
    </div>
    <button class="btn btn-outline whitespace-nowrap">
      Search
    </button>
  </form>

  <!-- Grid view -->
  <div x-show="mode === 'grid'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($rows as $r): ?>
      <?php
        $id     = (int)($r['id'] ?? 0);
        $code   = $r['code'] ?? '';
        $name   = $r['name'] ?? '';
        $phone  = $r['phone'] ?? '';
        $email  = $r['email'] ?? '';
        $active = (int)($r['is_active'] ?? 1) === 1;
        $orders = (int)($r['order_count'] ?? 0);
        $points = (float)($r['reward_points'] ?? 0);
        $ageStr = $age($r['created_at'] ?? null);
      ?>
      <article class="customer-card">
        <div class="customer-card-header">
          <div>
            <?php if ($code): ?>
              <div class="customer-chip mb-1"><?= $h($code) ?></div>
            <?php endif; ?>
            <a href="<?= $h($base) ?>/customers/<?= $id ?>"
               class="text-base font-semibold link">
              <?= $h($name) ?>
            </a>
            <?php if ($phone || $email): ?>
              <div class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                <?= $h($phone ?: $email) ?>
              </div>
            <?php endif; ?>
          </div>
          <span class="status-pill <?= $active ? 'active' : 'inactive' ?>">
            <?= $active ? 'Active' : 'Inactive' ?>
          </span>
        </div>

        <!-- Metrics row -->
        <dl class="mt-2 grid grid-cols-3 gap-2 text-xs">
          <div>
            <dt class="text-gray-500 dark:text-slate-400">Orders</dt>
            <dd class="font-semibold"><?= $orders ?></dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-slate-400">Rewards</dt>
            <dd class="font-semibold"><?= number_format($points, 0) ?></dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-slate-400">Customer age</dt>
            <dd class="font-semibold"><?= $h($ageStr ?: '—') ?></dd>
          </div>
        </dl>

        <!-- Actions -->
        <div class="mt-3 flex justify-end gap-2 text-xs">
          <a href="<?= $h($base) ?>/customers/<?= $id ?>" class="btn btn-outline">
            View
          </a>
          <a href="<?= $h($base) ?>/customers/<?= $id ?>/edit" class="btn">
            Edit
          </a>
        </div>
      </article>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
      <div class="col-span-full text-center text-sm text-gray-500 dark:text-slate-400 py-8">
        No customers found.
      </div>
    <?php endif; ?>
  </div>

  <!-- List view -->
  <div x-show="mode === 'list'" x-cloak class="border border-gray-200 dark:border-slate-700 rounded-xl overflow-x-auto">
    <table class="min-w-full customers-table">
      <thead class="bg-gray-50 dark:bg-slate-900/40">
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Status</th>
          <th>Orders</th>
          <th>Rewards</th>
          <th>Age</th>
          <th class="text-right pr-4">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $id     = (int)($r['id'] ?? 0);
            $active = (int)($r['is_active'] ?? 1) === 1;
            $orders = (int)($r['order_count'] ?? 0);
            $points = (float)($r['reward_points'] ?? 0);
            $ageStr = $age($r['created_at'] ?? null);
          ?>
          <tr>
            <td><?= $h($r['code'] ?? '') ?></td>
            <td>
              <a href="<?= $h($base) ?>/customers/<?= $id ?>" class="link">
                <?= $h($r['name'] ?? '') ?>
              </a>
            </td>
            <td><?= $h($r['phone'] ?? '') ?></td>
            <td><?= $h($r['email'] ?? '') ?></td>
            <td>
              <span class="status-pill <?= $active ? 'active' : 'inactive' ?>">
                <?= $active ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td><?= $orders ?></td>
            <td><?= number_format($points, 0) ?></td>
            <td><?= $h($ageStr ?: '—') ?></td>
            <td class="text-right pr-4">
              <div class="inline-flex gap-2">
                <a href="<?= $h($base) ?>/customers/<?= $id ?>" class="btn btn-outline">
                  View
                </a>
                <a href="<?= $h($base) ?>/customers/<?= $id ?>/edit" class="btn">
                  Edit
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <tr>
            <td colspan="9" class="text-center text-sm text-gray-500 dark:text-slate-400 py-6">
              No customers found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <div class="flex items-center justify-center gap-2 pt-3">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?q=<?= $h($q ?? '') ?>&page=<?= $i ?>"
           class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</section>