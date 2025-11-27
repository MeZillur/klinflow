<?php
/** @var string $module_base */
/** @var array  $org */
/** @var array  $uom */
/** @var array  $errors */
/** @var string $mode */

$h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base     = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName  = trim((string)($org['name'] ?? ''));
$isEdit   = ($mode ?? 'create') === 'edit';
$action   = $isEdit
    ? $base . '/uoms/' . (int)($uom['id'] ?? 0)
    : $base . '/uoms';
?>
<div class="kf-page kf-page-uoms-create">

    <header class="flex items-center justify-between mb-6">
        <div>
            <div class="inline-flex items-center text-xs font-semibold px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                ITEMS WORKSPACE
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">
                <?= $isEdit ? 'Edit unit of measure' : 'New unit of measure' ?>
            </h1>
            <?php if ($orgName !== ''): ?>
                <p class="mt-1 text-sm text-gray-500">
                    Organisation: <?= $h($orgName) ?>
                </p>
            <?php endif; ?>
        </div>

        <nav class="flex gap-2">
            <a href="<?= $h($base . '/uoms') ?>"
               class="px-3 py-1 rounded-md border bg-white hover:bg-gray-50 text-sm">
                Back to units
            </a>
        </nav>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Left: form -->
        <form method="post"
              action="<?= $h($action) ?>"
              class="bg-white border rounded-lg p-4 lg:col-span-2 space-y-4">
            <?php if (!empty($csrf ?? null)): ?>
                <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Name <span class="text-red-600">*</span>
                </label>
                <input type="text"
                       name="name"
                       required
                       maxlength="190"
                       value="<?= $h($uom['name'] ?? '') ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm <?= isset($errors['name']) ? 'border-red-500' : '' ?>"
                       placeholder="Example: Piece, Kilogram, Litre, Hour" />
                <?php if (!empty($errors['name'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?= $h($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Code <span class="text-red-600">*</span>
                </label>
                <input type="text"
                       name="code"
                       required
                       maxlength="32"
                       value="<?= $h($uom['code'] ?? '') ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm uppercase font-mono <?= isset($errors['code']) ? 'border-red-500' : '' ?>"
                       placeholder="Example: PCS, BOX, KG, L" />
                <p class="mt-1 text-xs text-gray-500">
                    Short technical identifier (A–Z, 0–9, dot, dash, underscore). Used in imports and integrations.
                </p>
                <?php if (!empty($errors['code'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?= $h($errors['code']) ?></p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Decimal places
                    </label>
                    <input type="number"
                           name="decimals"
                           min="0" max="6" step="1"
                           value="<?= $h((string)($uom['decimals'] ?? 0)) ?>"
                           class="w-full border rounded-md px-3 py-2 text-sm <?= isset($errors['decimals']) ? 'border-red-500' : '' ?>" />
                    <p class="mt-1 text-xs text-gray-500">
                        0 for whole units (PCS, BOX), 3 for weights like 0.001 KG.
                    </p>
                    <?php if (!empty($errors['decimals'])): ?>
                        <p class="mt-1 text-xs text-red-600"><?= $h($errors['decimals']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="flex items-center mt-6 sm:mt-0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1"
                               <?= !empty($uom['is_active']) ? 'checked' : '' ?> />
                        Active
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Notes (optional)
                </label>
                <textarea name="notes"
                          rows="3"
                          class="w-full border rounded-md px-3 py-2 text-sm"
                          placeholder="Optional internal notes about how this unit is used."><?= $h($uom['notes'] ?? '') ?></textarea>
            </div>

            <div class="pt-2 flex items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                    <?= $isEdit ? 'Save changes' : 'Save unit' ?>
                </button>
                <a href="<?= $h($base . '/uoms') ?>"
                   class="text-sm text-gray-600 hover:underline">
                    Cancel
                </a>
            </div>
        </form>

        <!-- Right: guidance (same block, horizontal layout) -->
        <section class="bg-gray-50 border rounded-lg p-4 text-sm text-gray-700">
            <h2 class="font-semibold mb-2">How to use this page</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>Think long-term: choose codes like <strong>PCS, BOX, KG, L</strong> that will still make sense in 2035.</li>
                <li>Use <strong>Name</strong> for human-friendly labels users see in dropdowns.</li>
                <li><strong>Code</strong> should be unique per organisation and never change once you start using it in items or imports.</li>
                <li>Set <strong>Decimal places</strong> according to how you sell: 0 for whole pieces, 3 for precise weights or liquids.</li>
                <li>Deactivate a unit instead of deleting it, so historical quotes, orders and invoices remain consistent.</li>
            </ul>
        </section>
    </div>
</div>