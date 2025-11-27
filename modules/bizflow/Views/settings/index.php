<?php
/** @var array  $org */
/** @var int    $org_id */
/** @var string $module_base */
/** @var array  $logo */
/** @var array  $documents */
/** @var array  $identity */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? 'your organisation';
$brand       = '#228B22';

// From controller (optional)
$logoUrl  = $logo['url']   ?? null;
$docFiles = $documents['files'] ?? [];

// Identity values (fall back to $org)
$idName    = trim((string)($identity['name']    ?? ($org['name']    ?? '')));
$idAddress = trim((string)($identity['address'] ?? ($org['address'] ?? '')));
$idPhone   = trim((string)($identity['phone']   ?? ($org['phone']   ?? '')));
$idEmail   = trim((string)($identity['email']   ?? ($org['email']   ?? '')));

// ---- Fallback logo URL via assets route ----
if (!$logoUrl) {
    // This will hit /t/{slug}/apps/bizflow/assets/logo (route above)
    $logoUrl = $module_base . '/assets/logo';
}
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'BizFlow settings') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Tenant-level configuration for <?= $h($orgName) ?> — identity, branding, document storage and more.
            </p>
        </div>

        <!-- Right-aligned app tabs (BizFlow convention) -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
            $tabs = [
                ['Items',      $module_base.'/items'],
                ['Quotes',     $module_base.'/quotes'],
                ['Orders',     $module_base.'/orders'],
                ['Tenders',    $module_base.'/tenders'],
                ['Inventory',  $module_base.'/inventory'],
                ['Reports',    $module_base.'/reports'],
                ['Banking',    $module_base.'/banking'],
            ];
            $current = $module_base.'/settings';
            foreach ($tabs as [$label, $url]):
                $active = $url === $current;
            ?>
                <a href="<?= $h($url) ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border text-xs md:text-[13px]
                          <?= $active
                               ? 'border-emerald-600 bg-emerald-50 text-emerald-700 font-semibold'
                               : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <span><?= $h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- Organisation identity -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">Organisation identity</h2>
                <p class="text-xs text-slate-500">
                    Name, address and contacts used on the BizFlow landing, invoices, quotes and PDFs.
                </p>
            </div>
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                <i class="fa fa-id-card"></i>
            </span>
        </div>

        <div class="grid gap-4 px-4 py-4 md:grid-cols-[1.4fr,1fr] md:items-start">
            <!-- Identity form -->
            <form method="post"
                  action="<?= $h($module_base . '/settings') ?>"
                  class="space-y-4">
                <input type="hidden" name="section" value="identity">

                <div class="space-y-1">
                    <label class="block text-xs font-medium text-slate-700">
                        Organisation name
                    </label>
                    <input type="text"
                           name="org_name"
                           value="<?= $h($idName) ?>"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-600 focus:ring-emerald-600" />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-medium text-slate-700">
                        Address
                    </label>
                    <textarea name="address"
                              rows="3"
                              class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-600 focus:ring-emerald-600"><?= $h($idAddress) ?></textarea>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-slate-700">
                            Phone
                        </label>
                        <input type="text"
                               name="phone"
                               value="<?= $h($idPhone) ?>"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-600 focus:ring-emerald-600" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-slate-700">
                            Email
                        </label>
                        <input type="email"
                               name="email"
                               value="<?= $h($idEmail) ?>"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-600 focus:ring-emerald-600" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                        <i class="fa fa-floppy-disk text-[11px]"></i>
                        <span>Save details</span>
                    </button>
                </div>
            </form>

            <!-- Identity preview -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-xs text-slate-700">
                <h3 class="mb-2 text-xs font-semibold text-slate-800">Preview on documents</h3>
                <div class="space-y-1">
                    <div class="font-semibold text-sm">
                        <?= $h($idName !== '' ? $idName : 'your organisation') ?>
                    </div>
                    <?php if ($idAddress !== ''): ?>
                        <div class="whitespace-pre-line">
                            <?= $h($idAddress) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-slate-500">
                            Fill in your details on the left; BizFlow will reuse them on PDFs and headers.
                        </div>
                    <?php endif; ?>

                    <div class="mt-2 space-y-0.5 text-slate-600">
                        <?php if ($idPhone !== ''): ?>
                            <div><span class="font-semibold">Phone:</span> <?= $h($idPhone) ?></div>
                        <?php endif; ?>
                        <?php if ($idEmail !== ''): ?>
                            <div><span class="font-semibold">Email:</span> <?= $h($idEmail) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Grid layout -->
    <div class="grid gap-6 lg:grid-cols-[2fr,1.3fr]">

        <!-- LEFT COLUMN: branding + documents -->
        <div class="space-y-6">

            <!-- Branding card -->
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Branding</h2>
                        <p class="text-xs text-slate-500">
                            Upload a tenant-specific logo and tune how BizFlow appears to your users.
                        </p>
                    </div>
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        <i class="fa fa-palette"></i>
                    </span>
                </div>

                <div class="grid gap-4 px-4 py-4 md:grid-cols-[1.2fr,1fr] md:items-start">
                    <!-- Form -->
                    <form method="post"
                          action="<?= $h($module_base.'/settings') ?>"
                          enctype="multipart/form-data"
                          class="space-y-4">
                        <input type="hidden" name="section" value="branding">

                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-slate-700">
                                Organisation logo
                            </label>
                            <p class="text-[11px] text-slate-500">
                                Recommended: PNG, SVG or WebP, square or horizontal.
                                This will be used on BizFlow headers and PDFs later.
                            </p>
                            <input type="file"
                                   name="org_logo"
                                   accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                   class="mt-2 block w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-600 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-emerald-700" />
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                                <i class="fa fa-floppy-disk text-[11px]"></i>
                                <span>Save branding</span>
                            </button>
                        </div>
                    </form>

                    <!-- Preview -->
                    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs font-medium text-slate-600 mb-1">
                            Current logo preview
                        </div>

                        <?php if ($logoUrl): ?>
                            <div class="flex h-24 w-40 items-center justify-center rounded-lg bg-white shadow-sm overflow-hidden">
                                <img src="<?= $h($logoUrl) ?>"
                                     alt="Organisation logo"
                                     class="max-h-20 max-w-[150px] object-contain" />
                            </div>
                            <p class="text-[11px] text-slate-500 text-center">
                                This logo will appear on BizFlow headers and PDF outputs for this tenant.
                            </p>
                        <?php else: ?>
                            <div class="flex h-24 w-40 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white text-[11px] text-slate-400">
                                No logo uploaded yet.
                            </div>
                            <p class="text-[11px] text-slate-500 text-center">
                                After upload, the logo will be stored for this tenant and reused across the suite.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Documents / statements -->
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Documents &amp; statements</h2>
                        <p class="text-xs text-slate-500">
                            Upload sample or recurring bank statements. Each tenant gets its own secure bucket.
                        </p>
                    </div>
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-sky-500 text-[11px] text-white">
                        <i class="fa fa-file-invoice"></i>
                    </span>
                </div>

                <div class="grid gap-4 px-4 py-4 md:grid-cols-[1.3fr,1fr] md:items-start">
                    <!-- Upload form -->
                    <form method="post"
                          action="<?= $h($module_base.'/settings') ?>"
                          enctype="multipart/form-data"
                          class="space-y-4">
                        <input type="hidden" name="section" value="documents">

                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-slate-700">
                                Upload statement or template
                            </label>
                            <p class="text-[11px] text-slate-500">
                                Supported now: PDF, XLSX, CSV. Later we will map these to bank accounts and auto-reconcile.
                            </p>
                            <input type="file"
                                   name="statement_file"
                                   accept=".pdf,.xlsx,.csv"
                                   class="mt-2 block w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-sky-500 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-sky-600" />
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                                <i class="fa fa-upload text-[11px]"></i>
                                <span>Upload document</span>
                            </button>
                        </div>
                    </form>

                    <!-- File list -->
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 max-h-56 overflow-y-auto">
                        <h3 class="mb-2 text-xs font-semibold text-slate-700 flex items-center justify-between">
                            <span>Existing files</span>
                            <span class="text-[11px] font-normal text-slate-500">
                                <?= $h((string)count($docFiles)) ?> file(s)
                            </span>
                        </h3>

                        <?php if (!empty($docFiles)): ?>
                            <ul class="space-y-1 text-[11px] text-slate-600">
                                <?php foreach ($docFiles as $file): ?>
                                    <?php
                                    $name  = $file['name'] ?? '';
                                    $size  = (int)($file['size'] ?? 0);
                                    $mtime = $file['mtime'] ?? null;
                                    $kb    = $size > 0 ? number_format($size / 1024, 1) . ' KB' : '—';
                                    $when  = $mtime ? date('Y-m-d H:i', $mtime) : '—';
                                    ?>
                                    <li class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1">
                                        <div class="flex items-center gap-2 truncate">
                                            <i class="fa fa-file-lines text-[10px] text-slate-400"></i>
                                            <span class="truncate"><?= $h($name) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] text-slate-400">
                                            <span><?= $h($kb) ?></span>
                                            <span><?= $h($when) ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-[11px] text-slate-500">
                                No documents uploaded yet. Use the form on the left to add your first statement.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        </div>

        <!-- RIGHT COLUMN: quick toggles / future options -->
        <aside class="space-y-4">

            <!-- Feature toggles (frontend-only for now) -->
            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                x-data="{ inv: true, multi: false, paperless: true }"
            >
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Module options</h2>
                <p class="mb-3 text-xs text-slate-500">
                    These toggles define how BizFlow behaves for this tenant. Right now they are visual only;
                    later we will persist them in the engine.
                </p>

                <ul class="space-y-2 text-xs">
                    <!-- Inventory tracking -->
                    <li class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium text-slate-800">Inventory tracking</div>
                            <div class="text-[11px] text-slate-500">
                                When enabled, purchases and invoices will post stock to BizFlow Inventory.
                            </div>
                        </div>
                        <button type="button"
                                @click="inv = !inv"
                                :class="inv
                                    ? 'inline-flex h-5 w-9 items-center rounded-full bg-emerald-600/90 px-0.5 text-[10px] text-white'
                                    : 'inline-flex h-5 w-9 items-center rounded-full bg-slate-200 px-0.5 text-[10px] text-slate-600'">
                            <span :class="inv ? 'ml-auto inline-block h-4 w-4 rounded-full bg-white shadow' : 'inline-block h-4 w-4 rounded-full bg-white shadow'"></span>
                        </button>
                    </li>

                    <!-- Multi-currency -->
                    <li class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium text-slate-800">Multi-currency</div>
                            <div class="text-[11px] text-slate-500">
                                Plan for future FX support. Base currency is BDT for now.
                            </div>
                        </div>
                        <button type="button"
                                @click="multi = !multi"
                                :class="multi
                                    ? 'inline-flex h-5 w-9 items-center rounded-full bg-emerald-600/90 px-0.5 text-[10px] text-white'
                                    : 'inline-flex h-5 w-9 items-center rounded-full bg-slate-200 px-0.5 text-[10px] text-slate-600'">
                            <span :class="multi ? 'ml-auto inline-block h-4 w-4 rounded-full bg-white shadow' : 'inline-block h-4 w-4 rounded-full bg-white shadow'"></span>
                        </button>
                    </li>

                    <!-- Paperless documents -->
                    <li class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium text-slate-800">Paperless documents</div>
                            <div class="text-[11px] text-slate-500">
                                Drive everything through PDF/email; no physical copies required.
                            </div>
                        </div>
                        <button type="button"
                                @click="paperless = !paperless"
                                :class="paperless
                                    ? 'inline-flex h-5 w-9 items-center rounded-full bg-emerald-600/90 px-0.5 text-[10px] text-white'
                                    : 'inline-flex h-5 w-9 items-center rounded-full bg-slate-200 px-0.5 text-[10px] text-slate-600'">
                            <span :class="paperless ? 'ml-auto inline-block h-4 w-4 rounded-full bg-white shadow' : 'inline-block h-4 w-4 rounded-full bg-white shadow'"></span>
                        </button>
                    </li>
                </ul>
            </section>

            <!-- How to use this page -->
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Set your <strong>organisation identity</strong>; BizFlow will reuse it on landing, PDFs and headers.</li>
                    <li>Upload a <strong>tenant-specific logo</strong>; BizFlow keeps it in a secure area per organisation.</li>
                    <li>Use the <strong>Documents</strong> section to keep reusable bank statements or templates.</li>
                    <li>Use the <strong>Module options</strong> panel to see which features are planned for this tenant.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>