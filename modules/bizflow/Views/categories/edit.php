<?php
/** @var string $module_base */
/** @var array  $org */
/** @var array  $category */
/** @var string $csrf */

$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName = trim((string)($org['name'] ?? ''));

$id   = (int)($category['id'] ?? 0);
?>
<div class="kf-page kf-page-items-categories-edit">

    <header class="flex items-center justify-between mb-6">
        <div>
            <div class="inline-flex items-center text-xs font-semibold px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                ITEMS WORKSPACE
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">
                Edit category
            </h1>
            <?php if ($orgName !== ''): ?>
                <p class="mt-1 text-sm text-gray-500">
                    Organisation: <?= $h($orgName) ?>
                </p>
            <?php endif; ?>
        </div>

        <nav class="flex gap-2">
            <a href="<?= $h($base . '/items/categories') ?>"
               class="px-3 py-1 rounded-md border bg-white hover:bg-gray-50 text-sm">
                Back to categories
            </a>
        </nav>
    </header>

    <form method="post"
          action="<?= $h($base . '/items/categories/' . $id) ?>"
          class="bg-white border rounded-lg p-4 max-w-xl space-y-4">
        <?php if (!empty($csrf)): ?>
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
                   class="w-full border rounded-md px-3 py-2 text-sm"
                   value="<?= $h($category['name'] ?? '') ?>" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Code
            </label>
            <input type="text"
                   name="code"
                   maxlength="64"
                   class="w-full border rounded-md px-3 py-2 text-sm uppercase"
                   value="<?= $h($category['code'] ?? '') ?>" />
            <p class="mt-1 text-xs text-gray-500">
                Optional, but should remain unique per organisation.
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>
            <textarea name="description"
                      rows="3"
                      class="w-full border rounded-md px-3 py-2 text-sm"><?= $h($category['description'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1"
                   <?= !empty($category['is_active']) ? 'checked' : '' ?> />
            <span class="text-sm text-gray-700">Active</span>
        </div>

        <div class="pt-2 flex items-center gap-2">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                Update category
            </button>
            <a href="<?= $h($base . '/items/categories') ?>"
               class="text-sm text-gray-600 hover:underline">
                Cancel
            </a>
        </div>
    </form>

    <!-- How to use this page -->
    <section class="mt-6 text-sm text-gray-700 bg-gray-50 border rounded-lg p-4 max-w-xl">
        <h2 class="font-semibold mb-2">How to use this page</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li>Use this screen to <strong>rename</strong> categories or tweak their codes and descriptions.</li>
            <li>Instead of deleting, make a category <strong>Inactive</strong> once it should no longer be used for new items.</li>
            <li>Existing items that already point to this category will keep working; only new selections should avoid inactive ones.</li>
        </ul>
    </section>
</div>