<?php
/** @var array  $categories */
/** @var string $module_base */
/** @var array  $org */
/** @var string $search */
/** @var bool   $only_active */

$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName = trim((string)($org['name'] ?? ''));
?>
<div class="kf-page kf-page-items-categories">

    <!-- Top header -->
    <header class="flex items-center justify-between mb-6">
        <div>
            <div class="inline-flex items-center text-xs font-semibold px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                ITEMS WORKSPACE
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">
                Item categories
            </h1>
            <?php if ($orgName !== ''): ?>
                <p class="mt-1 text-sm text-gray-500">
                    Organisation: <?= $h($orgName) ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- BizFlow main tabs (right-aligned) -->
        <nav class="flex gap-2">
            <a href="<?= $h($base . '/quotes') ?>" class="px-3 py-1 text-sm border rounded-md bg-white hover:bg-gray-50">Quotes</a>
            <a href="<?= $h($base . '/customers') ?>" class="px-3 py-1 text-sm border rounded-md bg-white hover:bg-gray-50">Customers</a>
            <a href="<?= $h($base . '/orders') ?>" class="px-3 py-1 text-sm border rounded-md bg-white hover:bg-gray-50">Orders</a>
            <a href="<?= $h($base . '/reports') ?>" class="px-3 py-1 text-sm border rounded-md bg-white hover:bg-gray-50">Reports</a>
            <a href="<?= $h($base . '/settings') ?>" class="px-3 py-1 text-sm border rounded-md bg-white hover:bg-gray-50">Settings</a>
        </nav>
    </header>

    <!-- Items sub-tabs -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex gap-1 text-sm">
            <a href="<?= $h($base . '/items') ?>"
               class="px-3 py-1 rounded-md border bg-white hover:bg-gray-50">
                Items
            </a>
            <span
               class="px-3 py-1 rounded-md border border-emerald-600 bg-emerald-50 text-emerald-700 font-semibold">
                Categories
            </span>
        </div>

        <a href="<?= $h($base . '/categories/create') ?>"
           class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700">
            + New category
        </a>
    </div>

    <!-- Filters -->
    <form method="get" class="flex flex-wrap items-center gap-3 mb-4 text-sm">
        <input
            type="text"
            name="q"
            value="<?= $h($search ?? '') ?>"
            placeholder="Search by name or code"
            class="border rounded-md px-2 py-1 min-w-[220px]"
        />

        <label class="inline-flex items-center gap-1">
            <input type="checkbox" name="active" value="1" <?= !empty($only_active) ? 'checked' : '' ?> />
            <span>Active only</span>
        </label>

        <button type="submit"
                class="px-3 py-1 rounded-md border bg-white hover:bg-gray-50">
            Apply
        </button>
    </form>

    <!-- Table -->
    <div class="bg-white border rounded-lg overflow-hidden">
        <?php if (empty($categories)): ?>
            <div class="p-6 text-sm text-gray-600 text-center">
                <p class="mb-3">No categories have been created yet for this organisation.</p>
                <a href="<?= $h($base . '/categories/create') ?>"
                   class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                    Create the first category
                </a>
            </div>
        <?php else: ?>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700">Code</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700">Name</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700">Description</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700">Status</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700">Updated</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr class="border-b last:border-b-0 hover:bg-gray-50">
                        <td class="px-3 py-2 align-top text-gray-800">
                            <?= $h($cat['code'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 align-top text-gray-900 font-semibold">
                            <?= $h($cat['name'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 align-top text-gray-600">
                            <?= $h($cat['description'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <?php if (!empty($cat['is_active'])): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 align-top text-gray-500 text-xs">
                            <?= $h($cat['updated_at'] ?? $cat['created_at'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 align-top text-right">
                            <a href="<?= $h($base . '/items/categories/' . (int)($cat['id'] ?? 0) . '/edit') ?>"
                               class="text-emerald-700 hover:underline text-sm">
                                Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- How to use this page -->
    <section class="mt-6 text-sm text-gray-700 bg-gray-50 border rounded-lg p-4">
        <h2 class="font-semibold mb-2">How to use this page</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li>Use the <strong>Items / Categories</strong> tabs to switch between the item list and category management.</li>
            <li>Search by <strong>name or code</strong> to quickly find a category. Tick <strong>Active only</strong> to hide archived ones.</li>
            <li>Each category should represent a logical group (e.g. “Laptops”, “Printing Services”) that you will reuse across quotes, orders and invoices.</li>
            <li>Keep codes short and unique per organisation (for example <code>LAPTOP</code>, <code>PRINT-SVC</code>). They are helpful for reporting and import/export.</li>
            <li>Use the <strong>Active / Inactive</strong> status to softly retire old categories without breaking existing documents.</li>
        </ul>
    </section>
</div>