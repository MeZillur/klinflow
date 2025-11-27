<?php
/** @var string $module_base */
/** @var array  $org */
/** @var array  $uoms */
/** @var string $search */
/** @var bool   $only_active */

$h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base   = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName = trim((string)($org['name'] ?? ''));
?>
<div class="kf-page kf-page-uoms-index">

    <!-- Header -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <div class="inline-flex items-center text-[11px] font-semibold px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                SETUP WORKSPACE
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">
                Units of measure
            </h1>
            <?php if ($orgName !== ''): ?>
                <p class="mt-1 text-sm text-gray-500">
                    Organisation: <?= $h($orgName) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= $h($base . '/items') ?>"
               class="hidden sm:inline-flex px-3 py-2 rounded-md border text-sm bg-white hover:bg-gray-50">
                Back to items
            </a>
            <a href="<?= $h($base . '/uoms/create') ?>"
               class="inline-flex items-center px-3 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                New unit
            </a>
        </div>
    </header>

    <!-- Layout: left = list, right = guidance -->
    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)] items-start">

        <!-- LEFT: filter + list -->
        <section class="space-y-4">

            <!-- Filters -->
            <form method="get" class="flex flex-wrap gap-3 items-center bg-white border rounded-lg px-3 py-2">
                <div class="flex-1 min-w-[200px]">
                    <label class="sr-only">Search</label>
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           class="w-full border rounded-md px-3 py-1.5 text-sm"
                           placeholder="Search by name or code">
                </div>

                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="active" value="1"
                           <?= $only_active ? 'checked' : '' ?>>
                    Active only
                </label>

                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-800 text-white text-xs font-semibold hover:bg-black">
                    Apply
                </button>
            </form>

            <!-- List -->
            <div class="bg-white border rounded-lg overflow-hidden">
                <?php if (empty($uoms)): ?>
                    <div class="p-6 text-sm text-gray-600 text-center">
                        <p class="mb-3">No units of measure have been created yet for this organisation.</p>
                        <a href="<?= $h($base . '/uoms/create') ?>"
                           class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                            Create the first unit
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
                                <tr>
                                    <th class="px-3 py-2 text-left w-[30%]">Name</th>
                                    <th class="px-3 py-2 text-left w-[15%]">Code</th>
                                    <th class="px-3 py-2 text-left w-[10%]">Decimals</th>
                                    <th class="px-3 py-2 text-left w-[10%]">Active</th>
                                    <th class="px-3 py-2 text-left">Notes</th>
                                    <th class="px-3 py-2 text-right w-[80px]">Save</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($uoms as $u): ?>
                                    <?php
                                        $id        = (int)($u['id'] ?? 0);
                                        $nameRow   = (string)($u['name'] ?? '');
                                        $codeRow   = (string)($u['code'] ?? '');
                                        $decimals  = (int)($u['decimals'] ?? 0);
                                        $isActive  = (int)($u['is_active'] ?? 1) === 1;
                                        $notesRow  = (string)($u['notes'] ?? '');
                                    ?>
                                    <tr class="hover:bg-gray-50/60">
                                        <form method="post" action="<?= $h($base . '/uoms/' . $id) ?>">
                                            <td class="px-3 py-2 align-middle">
                                                <input type="text"
                                                       name="name"
                                                       value="<?= $h($nameRow) ?>"
                                                       required
                                                       class="w-full border rounded-md px-2 py-1 text-sm">
                                            </td>
                                            <td class="px-3 py-2 align-middle">
                                                <input type="text"
                                                       name="code"
                                                       value="<?= $h($codeRow) ?>"
                                                       required
                                                       class="w-full border rounded-md px-2 py-1 text-sm uppercase">
                                            </td>
                                            <td class="px-3 py-2 align-middle">
                                                <input type="number"
                                                       name="decimals"
                                                       min="0" max="6"
                                                       value="<?= $h((string)$decimals) ?>"
                                                       class="w-16 border rounded-md px-2 py-1 text-sm text-right">
                                            </td>
                                            <td class="px-3 py-2 align-middle">
                                                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                                                    <input type="checkbox"
                                                           name="is_active"
                                                           value="1"
                                                           <?= $isActive ? 'checked' : '' ?>>
                                                    Active
                                                </label>
                                            </td>
                                            <td class="px-3 py-2 align-middle">
                                                <input type="text"
                                                       name="notes"
                                                       value="<?= $h($notesRow) ?>"
                                                       class="w-full border rounded-md px-2 py-1 text-sm"
                                                       placeholder="Optional notes">
                                            </td>
                                            <td class="px-3 py-2 align-middle text-right">
                                                <button type="submit"
                                                        class="inline-flex items-center px-3 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">
                                                    Save
                                                </button>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- RIGHT: How to use this page -->
        <section class="bg-gray-50 border rounded-lg p-4 text-sm text-gray-700">
            <h2 class="font-semibold mb-2">How to use this page</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>
                    Use a <strong>short, human-friendly Name</strong> – users will see this in dropdowns
                    and item forms.
                </li>
                <li>
                    Keep the <strong>Code</strong> consistent (e.g. <code>PCS</code>, <code>BOX</code>,
                    <code>KG</code>) so imports, exports and integrations are stable.
                </li>
                <li>
                    Set <strong>Decimals</strong> to 0 for whole units (pcs, box) and higher values
                    (2–3) for weights or volumes (kg, litre).
                </li>
                <li>
                    Use the <strong>Active</strong> toggle instead of deleting – this keeps history
                    for old items and documents.
                </li>
                <li>
                    Once UoMs are set up, the <strong>Item create</strong> screen will restrict choices
                    to these values, preventing spelling mistakes (pcs vs piece, kg vs kilo, etc.).
                </li>
            </ul>
        </section>
    </div>
</div>