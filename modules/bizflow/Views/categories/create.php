<?php
/** @var string $module_base */
/** @var array  $org */
/** @var string $csrf */

$h       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base    = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName = trim((string)($org['name'] ?? ''));
?>
<div class="kf-page kf-page-categories-create">

    <!-- Header -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
        <div>
            <div class="inline-flex items-center text-xs font-semibold px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                ITEMS &amp; SERVICES WORKSPACE
            </div>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">
                New category
            </h1>
            <?php if ($orgName !== ''): ?>
                <p class="mt-1 text-sm text-gray-500">
                    Organisation: <?= $h($orgName) ?>
                </p>
            <?php endif; ?>
        </div>

        <nav class="flex gap-2">
            <a href="<?= $h($base . '/categories') ?>"
               class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 bg-white text-sm text-gray-700 hover:bg-gray-50">
                Back to categories
            </a>
        </nav>
    </header>

    <!-- Main content: left = form, right = guidance -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Left: form -->
        <form method="post"
              action="<?= $h($base . '/categories') ?>"
              class="bg-white border border-gray-200 rounded-lg p-4 lg:p-5 shadow-sm space-y-4 lg:col-span-2">

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
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                       placeholder="Example: Laptops, Printing Services" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Code
                </label>
                <input type="text"
                       name="code"
                       maxlength="64"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                       placeholder="EXAMPLE: LAPTOP, PRINT-SVC" />
                <p class="mt-1 text-xs text-gray-500">
                    Optional but recommended. Must be unique per organisation. Will be upper-cased automatically.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Description
                </label>
                <textarea name="description"
                          rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                          placeholder="Short internal notes about what belongs in this category."></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input id="cat-active" type="checkbox" name="is_active" value="1" checked
                       class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                <label for="cat-active" class="text-sm text-gray-700">Active</label>
            </div>

            <div class="pt-2 flex flex-wrap items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1">
                    Save category
                </button>
                <a href="<?= $h($base . '/categories') ?>"
                   class="text-sm text-gray-600 hover:underline">
                    Cancel
                </a>
            </div>
        </form>

        <!-- Right: guidance (stacks under form on small screens) -->
        <section class="bg-gray-50 border border-gray-200 rounded-lg p-4 lg:p-5 text-sm text-gray-700">
            <h2 class="font-semibold mb-2 text-gray-900">How to use this page</h2>
            <ul class="list-disc pl-5 space-y-1.5">
                <li>Give each category a <strong>clear, human-friendly name</strong>; this is what users see in dropdowns.</li>
                <li>Use the <strong>Code</strong> for short technical identifiers used in imports, exports and integrations.</li>
                <li>Keep categories <strong>Active</strong> by default; later you can edit and uncheck instead of deleting.</li>
                <li>Once categories are set up, they appear in the <strong>Item create</strong> screen for consistent tagging.</li>
                <li>Use separate categories for products vs services when it helps reporting (e.g. “IT Hardware”, “Consulting”).</li>
            </ul>
        </section>
    </div>
</div>