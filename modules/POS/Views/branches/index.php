<?php
/** @var string $base */
/** @var array  $branches */

$h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

$hasAny   = !empty($branches);
$first    = $hasAny ? $branches[0] : [];
$hasCode  = $hasAny && array_key_exists('code', $first);
$hasAddr  = $hasAny && array_key_exists('address', $first);
$hasPhone = $hasAny && array_key_exists('phone', $first);
$hasEmail = $hasAny && array_key_exists('email', $first);
$hasActive= $hasAny && array_key_exists('is_active', $first);
$hasMain  = $hasAny && array_key_exists('is_main', $first);
?>

<style>
/* Scoped styling for branches page */
.pos-branches-root { max-width: 1120px; margin: 0 auto; }

/* Dark mode toggle */
body.pos-dark {
    background-color: #020617;
    color: #e5e7eb;
}
body.pos-dark .pos-card,
body.pos-dark .pos-table-wrapper {
    background-color: #020617;
    border-color: #1e293b;
}
body.pos-dark .pos-card-header {
    border-color: #1e293b;
}
body.pos-dark .pos-muted {
    color: #9ca3af;
}
body.pos-dark .pos-chip {
    background-color: #111827;
    color: #e5e7eb;
}
body.pos-dark .pos-chip--success {
    background-color: #065f46;
}
body.pos-dark .pos-chip--danger {
    background-color: #7f1d1d;
}
body.pos-dark .pos-nav a {
    color: #9ca3af;
}
body.pos-dark .pos-nav a.pos-nav-active {
    color: #e5e7eb;
    border-bottom-color: #4f46e5;
}
body.pos-dark .table thead th {
    background-color: #020617;
}

/* Generic bits */
.pos-card {
    background: #ffffff;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px rgba(15,23,42,0.04);
}
.pos-card-header {
    border-bottom: 1px solid #e5e7eb;
}
.pos-section-title {
    font-size: 1.25rem;
    font-weight: 700;
}
.pos-muted {
    font-size: 0.8rem;
    color: #6b7280;
}
.pos-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    background-color: #f3f4f6;
    color: #374151;
}
.pos-chip svg {
    width: 0.75rem;
    height: 0.75rem;
}
.pos-chip--success {
    background-color: #dcfce7;
    color: #166534;
}
.pos-chip--danger {
    background-color: #fee2e2;
    color: #b91c1c;
}

.pos-toggle-group button {
    border-radius: 999px;
}

.pos-table-wrapper {
    background: #ffffff;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    padding: 1rem;
}

.pos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
}

/* Simple icon buttons */
.pos-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem;
    border-radius: 0.5rem;
    border: 1px solid transparent;
}
.pos-icon-btn svg {
    width: 1rem;
    height: 1rem;
}
</style>

<div class="pos-branches-root space-y-4">
    <!-- Top header -->
    <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <!-- tiny building icon -->
                <span class="inline-flex items-center justify-center rounded-full bg-indigo-50 p-1.5">
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="w-4 h-4 text-indigo-600">
                        <path fill="currentColor"
                              d="M4 20h3v-2h2v2h6v-2h2v2h3V9L13 4 4 9v11Zm3-4v-3h2v3H7Zm4 0v-3h2v3h-2Zm4 0v-3h2v3h-2ZM6.2 9 13 5.7 19.8 9H6.2Z"/>
                    </svg>
                </span>
                <h1 class="pos-section-title text-gray-900">Branches</h1>
            </div>
            <p class="pos-muted">
                Manage outlets, head office, and active branches for the POS.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Dark mode toggle -->
            <button type="button"
                    id="pos-branch-dark-toggle"
                    class="pos-icon-btn border-gray-200 bg-white hover:bg-gray-50"
                    title="Toggle dark mode">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor"
                          d="M21 12.79A9 9 0 0 1 11.21 3 7 7 0 1 0 21 12.79Z"/>
                </svg>
            </button>

            <a href="<?= $h($base) ?>/branches/create"
               class="btn btn-primary inline-flex items-center gap-2">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="w-4 h-4">
                    <path fill="currentColor"
                          d="M19 11H13V5h-2v6H5v2h6v6h2v-6h6v-2Z"/>
                </svg>
                <span>Add Branch</span>
            </a>
        </div>
    </div>

    <!-- Section nav -->
    <div class="pos-nav flex items-center gap-4 text-xs border-b border-gray-100 pb-2">
        <a href="<?= $h($base) ?>/sales" class="py-1 border-b-2 border-transparent hover:border-indigo-200">
            Sales
        </a>
        <a href="<?= $h($base) ?>/inventory" class="py-1 border-b-2 border-transparent hover:border-indigo-200">
            Inventory
        </a>
        <a href="<?= $h($base) ?>/categories" class="py-1 border-b-2 border-transparent hover:border-indigo-200">
            Categories
        </a>
        <a href="<?= $h($base) ?>/branches"
           class="py-1 border-b-2 border-indigo-500 font-semibold pos-nav-active">
            Branches
        </a>
        <a href="<?= $h($base) ?>/expenses" class="py-1 border-b-2 border-transparent hover:border-indigo-200">
            Expenses
        </a>
    </div>

    <!-- View mode + stats -->
    <div class="flex items-center justify-between gap-4">
        <div class="text-xs text-gray-500">
            <?php if ($hasAny): ?>
                <span><?= count($branches) ?> branch<?= count($branches) !== 1 ? 'es' : '' ?> total</span>
            <?php else: ?>
                <span>No branches yet. Create your first outlet to begin.</span>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-2 pos-toggle-group">
            <button type="button"
                    data-view-mode="grid"
                    class="pos-icon-btn bg-gray-100 border-gray-200"
                    id="pos-view-grid-btn"
                    title="Grid view">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor"
                          d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/>
                </svg>
            </button>
            <button type="button"
                    data-view-mode="list"
                    class="pos-icon-btn border-gray-200 bg-white"
                    id="pos-view-list-btn"
                    title="List view">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor"
                          d="M4 6h16v2H4V6Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z"/>
                </svg>
            </button>
        </div>
    </div>

    <?php if (!$hasAny): ?>
        <!-- Empty state -->
        <div class="pos-card p-8 flex flex-col items-center justify-center text-center gap-3">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="w-10 h-10 text-gray-300">
                <path fill="currentColor"
                      d="M4 20h16V8l-6-4H4v16Zm2-2V6h6v3h6v9H6Z"/>
            </svg>
            <div class="text-sm text-gray-600">
                No branches have been created yet.
            </div>
            <a href="<?= $h($base) ?>/branches/create"
               class="btn btn-primary inline-flex items-center gap-2 mt-1">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="w-4 h-4">
                    <path fill="currentColor"
                          d="M19 11H13V5h-2v6H5v2h6v6h2v-6h6v-2Z"/>
                </svg>
                <span>Create first branch</span>
            </a>
        </div>
    <?php else: ?>

        <!-- GRID VIEW -->
        <div id="pos-branches-grid" class="pos-grid">
            <?php foreach ($branches as $b): ?>
                <?php
                $id    = (int)($b['id'] ?? 0);
                $name  = $b['name'] ?? '';
                $code  = $hasCode  ? (string)($b['code'] ?? '') : '';
                $addr  = $hasAddr  ? (string)($b['address'] ?? '') : '';
                $phone = $hasPhone ? (string)($b['phone'] ?? '') : '';
                $email = $hasEmail ? (string)($b['email'] ?? '') : '';
                $active= $hasActive ? (int)$b['is_active'] === 1 : true;
                $main  = $hasMain   ? (int)$b['is_main'] === 1 : false;
                ?>
                <div class="pos-card overflow-hidden flex flex-col">
                    <div class="pos-card-header px-4 py-3 flex items-center justify-between gap-2">
                        <div class="truncate">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-900 truncate"><?= $h($name) ?></span>
                                <?php if ($main): ?>
                                    <span class="pos-chip pos-chip--success">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path fill="currentColor"
                                                  d="M10 17.5L5.5 13l1.4-1.4L10 14.7l7.1-7.1L18.5 9l-8.5 8.5Z"/>
                                        </svg>
                                        Main
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($code !== ''): ?>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    Code: <span class="font-mono tracking-wide"><?= $h($code) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col items-end gap-1">
                            <?php if ($hasActive): ?>
                                <span class="pos-chip <?= $active ? 'pos-chip--success' : 'pos-chip--danger' ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="12" r="4" fill="currentColor"></circle>
                                    </svg>
                                    <?= $active ? 'Active' : 'Inactive' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="px-4 py-3 text-sm flex-1 space-y-2">
                        <?php if ($addr !== ''): ?>
                            <div class="flex items-start gap-2">
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="w-4 h-4 mt-0.5 text-gray-400">
                                    <path fill="currentColor"
                                          d="M12 2a7 7 0 0 0-7 7c0 4.25 5.16 9.35 6.46 10.6.29.28.79.28 1.08 0C13.84 18.35 19 13.25 19 9a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5Z"/>
                                </svg>
                                <p class="text-gray-700 leading-snug">
                                    <?= nl2br($h($addr)) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 gap-2 text-xs text-gray-600">
                            <?php if ($phone !== ''): ?>
                                <div class="flex items-center gap-2">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" class="w-3.5 h-3.5 text-gray-400">
                                        <path fill="currentColor"
                                              d="M6.6 10.8a15.05 15.05 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1.02-.24 11.4 11.4 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .57 3.58 1 1 0 0 1-.24 1.02l-2.23 2.2Z"/>
                                    </svg>
                                    <span><?= $h($phone) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($email !== ''): ?>
                                <div class="flex items-center gap-2">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" class="w-3.5 h-3.5 text-gray-400">
                                        <path fill="currentColor"
                                              d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v.01L12 12l8-5.99V6H4Zm0 2.24V18h16V8.24l-7.4 5.54a2 2 0 0 1-2.2 0L4 8.24Z"/>
                                    </svg>
                                    <span><?= $h($email) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-xs">
                        <a href="<?= $h($base) ?>/branches/<?= $id ?>/edit"
                           class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="w-3.5 h-3.5">
                                <path fill="currentColor"
                                      d="M4 21h4l11-11-4-4L4 17v4Zm15.7-12.3a1 1 0 0 0 0-1.4l-2-2a1 1 0 0 0-1.4 0L14 7.6l3.4 3.4 2.3-2.3Z"/>
                            </svg>
                            <span>Edit</span>
                        </a>

                        <!-- Quick switch button could be wired later -->
                        <form method="post" action="<?= $h($base) ?>/branches/switch" class="m-0 p-0">
                            <input type="hidden" name="branch_id" value="<?= $id ?>">
                            <button type="submit"
                                    class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-indigo-600">
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="w-3.5 h-3.5">
                                    <path fill="currentColor"
                                          d="M7 7h14v2H7l3.5 3.5L9 13l-6-6 6-6 1.5 1.5L7 7Zm10 10H3v-2h14l-3.5-3.5L15 11l6 6-6 6-1.5-1.5L17 17Z"/>
                                </svg>
                                <span>Use in POS</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- LIST VIEW -->
        <div id="pos-branches-list" class="pos-table-wrapper mt-2" style="display:none;">
            <div class="overflow-x-auto">
                <table class="table w-full text-sm">
                    <thead>
                    <tr>
                        <th class="text-left">Name</th>
                        <?php if ($hasCode): ?><th>Code</th><?php endif; ?>
                        <?php if ($hasAddr): ?><th>Address</th><?php endif; ?>
                        <?php if ($hasPhone): ?><th>Phone</th><?php endif; ?>
                        <?php if ($hasEmail): ?><th>Email</th><?php endif; ?>
                        <?php if ($hasActive): ?><th>Status</th><?php endif; ?>
                        <?php if ($hasMain): ?><th>Main</th><?php endif; ?>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($branches as $b): ?>
                        <?php
                        $id    = (int)($b['id'] ?? 0);
                        $name  = $b['name'] ?? '';
                        $code  = $hasCode  ? (string)($b['code'] ?? '') : '';
                        $addr  = $hasAddr  ? (string)($b['address'] ?? '') : '';
                        $phone = $hasPhone ? (string)($b['phone'] ?? '') : '';
                        $email = $hasEmail ? (string)($b['email'] ?? '') : '';
                        $active= $hasActive ? (int)$b['is_active'] === 1 : true;
                        $main  = $hasMain   ? (int)$b['is_main'] === 1 : false;
                        ?>
                        <tr>
                            <td class="font-medium text-gray-900">
                                <?= $h($name) ?>
                            </td>
                            <?php if ($hasCode): ?>
                                <td class="font-mono text-xs text-gray-600">
                                    <?= $h($code) ?>
                                </td>
                            <?php endif; ?>
                            <?php if ($hasAddr): ?>
                                <td><?= nl2br($h($addr)) ?></td>
                            <?php endif; ?>
                            <?php if ($hasPhone): ?>
                                <td><?= $h($phone) ?></td>
                            <?php endif; ?>
                            <?php if ($hasEmail): ?>
                                <td><?= $h($email) ?></td>
                            <?php endif; ?>
                            <?php if ($hasActive): ?>
                                <td>
                                    <span class="pos-chip <?= $active ? 'pos-chip--success' : 'pos-chip--danger' ?>">
                                        <?= $active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <?php if ($hasMain): ?>
                                <td>
                                    <?php if ($main): ?>
                                        <span class="pos-chip pos-chip--success">
                                            Main
                                        </span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="<?= $h($base) ?>/branches/<?= $id ?>/edit"
                                       class="btn btn-muted btn-xs">
                                        Edit
                                    </a>
                                    <form method="post" action="<?= $h($base) ?>/branches/switch" class="inline">
                                        <input type="hidden" name="branch_id" value="<?= $id ?>">
                                        <button type="submit"
                                                class="btn btn-muted btn-xs">
                                            Use in POS
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
// View mode toggle
(function () {
    const grid = document.getElementById('pos-branches-grid');
    const list = document.getElementById('pos-branches-list');
    const btnGrid = document.getElementById('pos-view-grid-btn');
    const btnList = document.getElementById('pos-view-list-btn');

    if (!grid || !list || !btnGrid || !btnList) return;

    function setMode(mode) {
        const isGrid = mode === 'grid';
        grid.style.display = isGrid ? '' : 'none';
        list.style.display = isGrid ? 'none' : '';
        btnGrid.classList.toggle('bg-gray-100', isGrid);
        btnGrid.classList.toggle('bg-white', !isGrid);
        btnList.classList.toggle('bg-gray-100', !isGrid);
        btnList.classList.toggle('bg-white', isGrid);
        try { localStorage.setItem('pos_branch_view', mode); } catch(e) {}
    }

    btnGrid.addEventListener('click', () => setMode('grid'));
    btnList.addEventListener('click', () => setMode('list'));

    // restore preference
    let initial = 'grid';
    try {
        const stored = localStorage.getItem('pos_branch_view');
        if (stored === 'list') initial = 'list';
    } catch (e) {}
    setMode(initial);
})();

// Dark mode toggle
(function () {
    const btn = document.getElementById('pos-branch-dark-toggle');
    if (!btn) return;

    function applyDark(on) {
        document.body.classList.toggle('pos-dark', on);
        try { localStorage.setItem('pos_branch_dark', on ? '1' : '0'); } catch (e) {}
    }

    btn.addEventListener('click', () => {
        const on = !document.body.classList.contains('pos-dark');
        applyDark(on);
    });

    try {
        const stored = localStorage.getItem('pos_branch_dark');
        if (stored === '1') applyDark(true);
    } catch (e) {}
})();
</script>