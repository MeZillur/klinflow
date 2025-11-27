<?php
declare(strict_types=1);

/**
 * KlinFlow — Control Panel Sidenav
 * - Segmented groups (Overview, Management, People, System)
 * - Keeps your original links as-is
 * - Active route highlighting + auto-expand on current
 * - Uses CSS var --brand (fallback #228B22) for icons
 */

$h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$path     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$iconCSS  = 'color: var(--brand, #228B22);';

/** Exact or subpath match */
$active = function (string $href) use ($path): bool {
  if ($href === '/') return $path === '/';
  $base = rtrim($href, '/');
  return $path === $href || ($base !== '' && str_starts_with($path, $base . '/'));
};

/** Any item in list active */
$anyActive = function (array $items) use (&$active): bool {
  foreach ($items as $it) {
    if (!empty($it['href']) && $active($it['href'])) return true;
  }
  return false;
};

/** NAV data: keep your links, just grouped */
$sections = [
  [
    'title' => 'OVERVIEW',
    'items' => [
      ['icon' => 'fa-gauge',  'text' => 'Dashboard', 'href' => '/cp/dashboard'],
    ],
  ],
  [
    'title' => 'MANAGEMENT',
    'items' => [
      [
        'icon' => 'fa-building', 'text' => 'Organizations', 'href' => '/cp/organizations', 'children' => [
          ['text' => 'All Organizations', 'href' => '/cp/organizations'],
          ['text' => 'Create New',       'href' => '/cp/organizations/new'],
          ['text' => 'Trials',           'href' => '/cp/organizations?status=trial'],
          ['text' => 'Suspended',        'href' => '/cp/organizations?status=suspended'],
        ],
      ],
      [
        'icon' => 'fa-cubes', 'text' => 'Modules', 'href' => '/cp/modules', 'children' => [
          ['text' => 'Registry',     'href' => '/cp/modules'],
          ['text' => 'Add Module',   'href' => '/cp/modules/new'],
          ['text' => 'Updates',      'href' => '/cp/modules/updates'],
          ['text' => 'Assignments',  'href' => '/cp/modules/assignments'],
        ],
      ],
    ],
  ],
  [
    'title' => 'PEOPLE',
    'items' => [
      [
        'icon' => 'fa-users', 'text' => 'CP Users', 'href' => '/cp/users', 'children' => [
          ['text' => 'All Users',   'href' => '/cp/users'],
          ['text' => 'Create New',  'href' => '/cp/users/new'],
          ['text' => 'Owners',      'href' => '/cp/users?role=owner'],
          ['text' => 'Admins',      'href' => '/cp/users?role=admin'],
        ],
      ],
    ],
  ],
  [
    'title' => 'SYSTEM',
    'items' => [
      [
        'icon' => 'fa-clipboard-list', 'text' => 'Audit Logs', 'href' => '/cp/audit', 'children' => [
          ['text' => 'All Events',   'href' => '/cp/audit'],
          ['text' => 'Security',     'href' => '/cp/audit?type=security'],
          ['text' => 'Auth',         'href' => '/cp/audit?type=auth'],
          ['text' => 'Org Changes',  'href' => '/cp/audit?type=org'],
        ],
      ],
      [
        'icon' => 'fa-gear', 'text' => 'Settings', 'href' => '/cp/settings', 'children' => [
          ['text' => 'General',       'href' => '/cp/settings'],
          ['text' => 'Branding',      'href' => '/cp/settings/branding'],
          ['text' => 'SMTP / Mail',   'href' => '/cp/settings/smtp'],
          ['text' => 'Security',      'href' => '/cp/settings/security'],
          ['text' => 'Backups',       'href' => '/cp/settings/backups'],
          ['text' => 'System Health', 'href' => '/cp/settings/health'],
        ],
      ],
    ],
  ],
];

$rowBase = 'flex items-center gap-3 w-full px-3 py-2 rounded-lg transition';
$onCls   = 'bg-gray-100 dark:bg-gray-700/40 text-gray-900 dark:text-gray-100';
$offCls  = 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';
$linkSub = 'block px-3 py-2 rounded-lg text-sm transition';
?>
<nav class="p-4">
  <?php foreach ($sections as $sec): ?>
    <div class="px-2 pt-2 pb-3 text-[11px] tracking-wide text-gray-500 dark:text-gray-400">
      <?= $h($sec['title']) ?>
    </div>
    <ul class="space-y-1">
      <?php foreach ($sec['items'] as $item): ?>
        <?php
          $hasKids = !empty($item['children']);
          $isHere  = !empty($item['href']) && $active($item['href']);
          $opened  = $isHere || ($hasKids && $anyActive($item['children']));
        ?>
        <li x-data="{open: <?= $opened ? 'true' : 'false' ?>}">
          <?php if ($hasKids): ?>
            <button type="button"
              class="<?= $rowBase . ' ' . ($opened ? $onCls : $offCls) ?>"
              @click="open=!open" :aria-expanded="open.toString()">
              <i class="fa-solid <?= $h($item['icon']) ?> w-5" style="<?= $iconCSS ?>"></i>
              <span class="truncate flex-1 text-left"><?= $h($item['text']) ?></span>
              <i class="fa-solid fa-chevron-right text-xs transition-transform" :class="open ? 'rotate-90' : ''"></i>
            </button>
            <ul class="mt-1 pl-4 space-y-1" x-show="open" x-cloak x-transition.origin.top.left>
              <?php foreach ($item['children'] as $ch):
                $isChild = !empty($ch['href']) && $active($ch['href']); ?>
                <li>
                  <a href="<?= $h($ch['href']) ?>"
                     class="<?= $linkSub . ' ' . ($isChild
                       ? 'bg-gray-100 dark:bg-gray-700/60 text-gray-900 dark:text-gray-100'
                       : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700') ?>">
                    <?= $h($ch['text']) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <a href="<?= $h($item['href'] ?? '#') ?>" class="<?= $rowBase . ' ' . ($isHere ? $onCls : $offCls) ?>">
              <i class="fa-solid <?= $h($item['icon']) ?> w-5" style="<?= $iconCSS ?>"></i>
              <span class="truncate"><?= $h($item['text']) ?></span>
            </a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>

  <div class="mt-6 px-3 text-xs text-gray-400 dark:text-gray-500">
    v1.0 · <?= date('Y') ?>
  </div>
</nav>