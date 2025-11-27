<?php
/** @var string $base */
/** @var string $title */
$h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

// Simple "old" value helper (if you ever set $_SESSION['pos_old'])
$old = $_SESSION['pos_old'] ?? [];
$val = function (string $key, string $default = '') use ($old, $h) {
    return isset($old[$key]) ? $h($old[$key]) : $h($default);
};
?>
<div class="max-w-4xl mx-auto">
    <!-- Page header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-indigo-50 text-indigo-600 text-lg">
                    🏬
                </span>
                <span><?= $h($title ?? 'Add Branch') ?></span>
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Create a new branch / outlet and link it to this organization.
            </p>
        </div>

        <a href="<?= $h($base) ?>/branches"
           class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-200 bg-white hover:bg-gray-50">
            ← Back to branches
        </a>
    </div>

    <!-- Form card -->
    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-800">Branch details</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Basic information used on sales, invoices and reports.
                </p>
            </div>
        </div>

        <form method="post"
              action="<?= $h($base) ?>/branches"
              class="px-5 pb-5 pt-4 space-y-6">

            <input type="hidden" name="_return" value="<?= $h($base) ?>/branches">

            <!-- Top row: name + code + status -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Name -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Branch name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        required
                        value="<?= $val('name') ?>"
                        placeholder="e.g. Main Branch, Gulshan Outlet"
                        class="sr-input w-full"
                    >
                </div>

                <!-- Code -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Branch code
                    </label>
                    <input
                        type="text"
                        name="code"
                        value="<?= $val('code') ?>"
                        placeholder="Auto if left blank"
                        class="sr-input w-full text-sm tracking-wide uppercase"
                    >
                    <p class="text-[11px] text-gray-400 mt-1">
                        Short identifier for invoices &amp; reports.
                    </p>
                </div>
            </div>

            <!-- Address -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">
                    Address
                </label>
                <textarea
                    name="address"
                    rows="3"
                    placeholder="Street, area, city"
                    class="sr-input w-full resize-y"
                ><?= $val('address') ?></textarea>
            </div>

            <!-- Contact info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Phone
                    </label>
                    <input
                        type="text"
                        name="phone"
                        value="<?= $val('phone') ?>"
                        placeholder="+8801XXXXXXXXX"
                        class="sr-input w-full"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Email
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="<?= $val('email') ?>"
                        placeholder="branch@example.com"
                        class="sr-input w-full"
                    >
                </div>
            </div>

            <!-- Toggles -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                <!-- Active -->
                <label class="inline-flex items-start gap-2 text-sm text-gray-700 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        class="mt-0.5"
                        checked
                    >
                    <span>
                        <span class="font-medium">Branch is active</span><br>
                        <span class="text-xs text-gray-500">
                            Inactive branches will be hidden from POS &amp; reports.
                        </span>
                    </span>
                </label>

                <!-- Main branch flag (controller will honor it if a column exists) -->
                <label class="inline-flex items-start gap-2 text-sm text-gray-700 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="is_main"
                        value="1"
                        class="mt-0.5"
                    >
                    <span>
                        <span class="font-medium">Set as main / head office</span><br>
                        <span class="text-xs text-gray-500">
                            Used as the default branch for stock transfers &amp; reporting.
                        </span>
                    </span>
                </label>
            </div>

            <!-- Footer buttons -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-100 mt-2">
                <div class="text-[11px] text-gray-400">
                    Tip: you can switch the active branch from the POS header anytime.
                </div>

                <div class="flex items-center gap-2">
                    <a href="<?= $h($base) ?>/branches"
                       class="btn btn-muted">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Save branch
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>