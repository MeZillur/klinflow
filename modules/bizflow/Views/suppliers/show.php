<?php
/**
 * Supplier detail (show) page
 *
 * Expected variables (controller should pass):
 * - array  $supplier          Main supplier row
 * - array  $org               Current organisation
 * - string $module_base       /t/{slug}/apps/bizflow
 * - array  $stats             (optional) aggregated numbers
 * - array  $quotes            (optional) quote rows
 * - array  $orders            (optional) order rows
 * - array  $purchases         (optional) purchase rows
 * - array  $invoices          (optional) invoice rows
 * - array  $tenders           (optional) tender rows
 * - array  $payments          (optional) payment rows
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = (string)($org['name'] ?? '');

$supplier    = $supplier ?? [];
$stats       = $stats ?? [];
$quotes      = $quotes ?? [];
$orders      = $orders ?? [];
$purchases   = $purchases ?? [];
$invoices    = $invoices ?? [];
$tenders     = $tenders ?? [];
$payments    = $payments ?? [];

/* Core fields */
$id        = (int)($supplier['id'] ?? 0);
$code      = (string)($supplier['code'] ?? ('SUP-'.$id));
$name      = (string)($supplier['name'] ?? 'Unknown supplier');
$type      = (string)($supplier['type'] ?? 'local');
$isActive  = (int)($supplier['is_active'] ?? 1) === 1;
$contact   = (string)($supplier['contact_name'] ?? '');
$email     = (string)($supplier['email'] ?? '');
$phone     = (string)($supplier['phone'] ?? '');
$altPhone  = (string)($supplier['alt_phone'] ?? '');
$terms     = (string)($supplier['payment_terms'] ?? '');
$credit    = $supplier['credit_limit'] ?? null;
$taxReg    = (string)($supplier['tax_reg_no'] ?? '');

$addr1     = (string)($supplier['address_line1'] ?? '');
$addr2     = (string)($supplier['address_line2'] ?? '');
$city      = (string)($supplier['city'] ?? '');
$district  = (string)($supplier['district'] ?? '');
$country   = (string)($supplier['country'] ?? '');
$createdAt = (string)($supplier['created_at'] ?? '');
$updatedAt = (string)($supplier['updated_at'] ?? '');
$lastTxn   = (string)($stats['last_txn_date'] ?? '');
$firstTxn  = (string)($stats['first_txn_date'] ?? '');

/* Aggregates (BDT) */
$totPurch   = (float)($stats['total_purchases_bdt'] ?? 0);
$totInv     = (float)($stats['total_invoices_bdt'] ?? 0);
$totPay     = (float)($stats['total_payments_bdt'] ?? 0);
$outstanding= (float)($stats['outstanding_bdt'] ?? ($totInv - $totPay));

$openQuotes = (int)($stats['open_quotes'] ?? 0);
$openOrders = (int)($stats['open_orders'] ?? 0);
$openBills  = (int)($stats['open_purchases'] ?? 0);

$locationParts = array_filter([$addr1, $addr2, $city, $district, $country]);
?>
<div class="space-y-6">

    <!-- HEADER: title + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    <?= $h($name) ?>
                </h1>
                <?php if ($isActive): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-[3px] text-[11px] font-medium text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Active
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-[3px] text-[11px] font-medium text-slate-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                        Inactive
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-sm text-slate-500">
                Supplier master data for <?= $h($orgName ?: 'your organisation') ?> —
                all quotes, orders, purchases, invoices, tenders and payments in one place.
            </p>
            <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-[2px] font-mono">
                    Code: <?= $h($code) ?>
                </span>
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-[2px]">
                    <?= $type === 'international' ? 'International supplier' : 'Local supplier' ?>
                </span>
            </div>
        </div>
      
      <!--Quick Menu---->
        <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
            <a href="<?= $h($module_base.'/suppliers') ?>"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] font-medium text-slate-700 hover:bg-slate-50">
                <i class="fa fa-arrow-left text-[10px]"></i>
                <span>Back to list</span>
            </a>
            <a href="<?= $h($module_base.'/suppliers/'.$id.'/edit') ?>"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-800 shadow-sm hover:bg-slate-50">
                <i class="fa-regular fa-pen-to-square text-[10px]"></i>
                <span>Edit</span>
            </a>
            <a href="<?= $h($module_base.'/purchases/create').'?supplier_id='.$id ?>"
               class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-[12px] font-medium text-white shadow-sm hover:bg-emerald-700">
                <i class="fa fa-file-invoice-dollar text-[10px]"></i>
                <span>New purchase</span>
            </a>
        </div>
      
    </header>

    <!-- TOP STRIP: actions + quick summary -->
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap gap-2 text-xs md:text-sm">
            <?php if ($lastTxn !== ''): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-3 py-1 text-[11px] font-medium text-white">
                    <i class="fa-regular fa-clock text-[10px]"></i>
                    Last activity: <?= $h($lastTxn) ?>
                </span>
            <?php endif; ?>
            <?php if ($firstTxn !== '' && $firstTxn !== $lastTxn): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-medium text-slate-700">
                    <i class="fa-regular fa-calendar text-[10px]"></i>
                    Since: <?= $h($firstTxn) ?>
                </span>
            <?php endif; ?>
        </div>

    </section>

    <!-- MAIN GRID: profile + numbers + history -->
    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr),minmax(0,2fr)]">
        <!-- LEFT COLUMN: profile + finance -->
        <div class="space-y-4">
            <!-- Profile card -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h2 class="text-sm font-semibold text-slate-800">Profile</h2>
                        <p class="text-xs text-slate-500">
                            Master data coming from the supplier register.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-[3px] text-[11px] font-mono text-slate-700">
                        ID: <?= $id ?>
                    </span>
                </div>

                <div class="mt-3 space-y-2 text-sm text-slate-800">
                    <?php if ($contact !== ''): ?>
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-user text-slate-400 text-xs w-4"></i>
                            <span><?= $h($contact) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($email !== ''): ?>
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-envelope text-slate-400 text-xs w-4"></i>
                            <a href="mailto:<?= $h($email) ?>" class="text-emerald-700 hover:underline">
                                <?= $h($email) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if ($phone !== ''): ?>
                        <div class="flex items-center gap-2">
                            <i class="fa fa-phone text-slate-400 text-xs w-4"></i>
                            <span><?= $h($phone) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($altPhone !== ''): ?>
                        <div class="flex items-center gap-2 text-[13px] text-slate-600">
                            <i class="fa fa-phone-flip text-slate-400 text-[11px] w-4"></i>
                            <span><?= $h($altPhone) ?> <span class="text-slate-400">(alt.)</span></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($locationParts)): ?>
                        <div class="mt-2 flex items-start gap-2 text-[13px] text-slate-700">
                            <i class="fa-regular fa-map text-slate-400 text-xs w-4 mt-0.5"></i>
                            <p class="whitespace-pre-line">
                                <?= $h(implode(", ", array_filter([$addr1, $addr2]))) ?>
                                <?php if ($city || $district || $country): ?>
                                    <br>
                                    <?= $h(implode(", ", array_filter([$city, $district, $country]))) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="mt-2 flex items-start gap-2 text-[13px] text-slate-500">
                            <i class="fa-regular fa-map text-slate-400 text-xs w-4 mt-0.5"></i>
                            <p>Address details not set yet.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($taxReg !== ''): ?>
                        <div class="mt-2 flex items-center gap-2 text-[12px] text-slate-700">
                            <i class="fa-solid fa-file-invoice text-slate-400 text-[11px] w-4"></i>
                            <span>Tax / BIN / VAT reg: <span class="font-mono"><?= $h($taxReg) ?></span></span>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3 grid gap-2 text-[11px] text-slate-500">
                        <?php if ($createdAt !== ''): ?>
                            <div>Created at: <?= $h($createdAt) ?></div>
                        <?php endif; ?>
                        <?php if ($updatedAt !== ''): ?>
                            <div>Last updated: <?= $h($updatedAt) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Finance snapshot -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-800">Financial snapshot (BDT)</h2>
                    <?php if ($credit !== null && $credit !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-[3px] text-[11px] font-medium text-emerald-800">
                            Credit limit: <?= $h(number_format((float)$credit, 2)) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                        <dt class="text-[11px] font-medium text-slate-500">Total purchases</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                            <?= $h(number_format($totPurch, 2)) ?> BDT
                        </dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                        <dt class="text-[11px] font-medium text-slate-500">Total invoices</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                            <?= $h(number_format($totInv, 2)) ?> BDT
                        </dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                        <dt class="text-[11px] font-medium text-slate-500">Total payments</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                            <?= $h(number_format($totPay, 2)) ?> BDT
                        </dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 px-3 py-2
                                <?= $outstanding > 0 ? 'bg-amber-50' : 'bg-emerald-50' ?>">
                        <dt class="text-[11px] font-medium text-slate-500">
                            <?= $outstanding > 0 ? 'Outstanding payable' : 'Net position' ?>
                        </dt>
                        <dd class="mt-1 text-sm font-semibold
                                   <?= $outstanding > 0 ? 'text-amber-800' : 'text-emerald-800' ?>">
                            <?= $h(number_format($outstanding, 2)) ?> BDT
                        </dd>
                    </div>
                </dl>

                <div class="mt-2 grid gap-2 text-[11px] text-slate-600 sm:grid-cols-3">
                    <div class="inline-flex items-center gap-1 rounded-lg border border-slate-100 bg-white px-2 py-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <span>Open quotes: <?= $h($openQuotes) ?></span>
                    </div>
                    <div class="inline-flex items-center gap-1 rounded-lg border border-slate-100 bg-white px-2 py-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <span>Open orders: <?= $h($openOrders) ?></span>
                    </div>
                    <div class="inline-flex items-center gap-1 rounded-lg border border-slate-100 bg-white px-2 py-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                        <span>Open purchases/bills: <?= $h($openBills) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: history tables -->
        <div class="space-y-4">

            <!-- Purchases & invoices history -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Purchases & invoices history</h2>
                        <p class="text-[11px] text-slate-500">
                            Last documents for this supplier. All amounts in BDT.
                        </p>
                    </div>
                    <a href="<?= $h($module_base.'/purchases').'?supplier_id='.$id ?>"
                       class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50">
                        <i class="fa fa-arrow-circle-right text-[10px]"></i>
                        <span>View all</span>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-left">Doc no.</th>
                            <th class="px-3 py-2 text-right">Total (BDT)</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white text-xs">
                        <?php
                        // Merge purchases + invoices into one list if controller didn't precompute it
                        $rows = [];
                        foreach ($purchases as $r) {
                            $r['_kind'] = 'Purchase';
                            $rows[] = $r;
                        }
                        foreach ($invoices as $r) {
                            $r['_kind'] = 'Invoice';
                            $rows[] = $r;
                        }
                        // Optional: if controller already gave $history, you can ignore this and just use it.
                        if (!empty($rows)):
                            // show at most 15 rows
                            $rows = array_slice($rows, 0, 15);
                            foreach ($rows as $r):
                                $kind   = (string)($r['_kind'] ?? 'Doc');
                                $date   = (string)($r['date'] ?? $r['doc_date'] ?? '');
                                $no     = (string)($r['doc_no'] ?? $r['purchase_no'] ?? $r['invoice_no'] ?? ('#'.$r['id'] ?? ''));
                                $status = (string)($r['status'] ?? '');
                                $total  = (float)($r['grand_total'] ?? $r['total'] ?? 0);
                        ?>
                            <tr class="hover:bg-emerald-50/40">
                                <td class="px-3 py-1.5 whitespace-nowrap"><?= $h($date ?: '—') ?></td>
                                <td class="px-3 py-1.5"><?= $h($kind) ?></td>
                                <td class="px-3 py-1.5 whitespace-nowrap font-mono text-[11px]"><?= $h($no) ?></td>
                                <td class="px-3 py-1.5 text-right"><?= $h(number_format($total, 2)) ?></td>
                                <td class="px-3 py-1.5">
                                    <?php if ($status !== ''): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-[2px] text-[10px] font-medium text-slate-700">
                                            <?= $h(ucfirst($status)) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-[13px] text-slate-500">
                                    No purchases or invoices recorded for this supplier yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quotes & orders -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Quotes & orders</h2>
                        <p class="text-[11px] text-slate-500">
                            Commercial pipeline documents linked to this supplier.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-left">Doc no.</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-right">Amount (BDT)</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white text-xs">
                        <?php
                        $pipe = [];
                        foreach ($quotes as $r) {
                            $r['_kind'] = 'Quote';
                            $pipe[] = $r;
                        }
                        foreach ($orders as $r) {
                            $r['_kind'] = 'Order';
                            $pipe[] = $r;
                        }
                        if (!empty($tenders)) {
                            foreach ($tenders as $r) {
                                $r['_kind'] = 'Tender';
                                $pipe[] = $r;
                            }
                        }
                        if (!empty($pipe)):
                            $pipe = array_slice($pipe, 0, 15);
                            foreach ($pipe as $r):
                                $kind   = (string)($r['_kind'] ?? 'Doc');
                                $date   = (string)($r['date'] ?? '');
                                $no     = (string)($r['doc_no'] ?? $r['quote_no'] ?? $r['order_no'] ?? $r['tender_no'] ?? ('#'.$r['id'] ?? ''));
                                $status = (string)($r['status'] ?? '');
                                $total  = (float)($r['grand_total'] ?? $r['total'] ?? 0);
                        ?>
                            <tr class="hover:bg-emerald-50/40">
                                <td class="px-3 py-1.5 whitespace-nowrap"><?= $h($date ?: '—') ?></td>
                                <td class="px-3 py-1.5"><?= $h($kind) ?></td>
                                <td class="px-3 py-1.5 whitespace-nowrap font-mono text-[11px]"><?= $h($no) ?></td>
                                <td class="px-3 py-1.5">
                                    <?php if ($status !== ''): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-[2px] text-[10px] font-medium text-slate-700">
                                            <?= $h(ucfirst($status)) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-1.5 text-right"><?= $h(number_format($total, 2)) ?></td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-[13px] text-slate-500">
                                    No quotes or orders recorded for this supplier yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments timeline -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Payment history</h2>
                        <p class="text-[11px] text-slate-500">
                            Cash / bank payments made to this supplier.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Ref</th>
                            <th class="px-3 py-2 text-left">Method</th>
                            <th class="px-3 py-2 text-left">Note</th>
                            <th class="px-3 py-2 text-right">Amount (BDT)</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white text-xs">
                        <?php if (!empty($payments)): ?>
                            <?php foreach (array_slice($payments, 0, 20) as $r): ?>
                                <?php
                                $date   = (string)($r['date'] ?? '');
                                $ref    = (string)($r['ref_no'] ?? $r['payment_no'] ?? ('#'.$r['id'] ?? ''));
                                $method = (string)($r['method'] ?? $r['channel'] ?? '');
                                $note   = (string)($r['note'] ?? $r['memo'] ?? '');
                                $amt    = (float)($r['amount'] ?? $r['total'] ?? 0);
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-3 py-1.5 whitespace-nowrap"><?= $h($date ?: '—') ?></td>
                                    <td class="px-3 py-1.5 whitespace-nowrap font-mono text-[11px]"><?= $h($ref) ?></td>
                                    <td class="px-3 py-1.5"><?= $h($method ?: '—') ?></td>
                                    <td class="px-3 py-1.5 max-w-[260px] truncate"><?= $h($note ?: '—') ?></td>
                                    <td class="px-3 py-1.5 text-right"><?= $h(number_format($amt, 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-[13px] text-slate-500">
                                    No payments recorded for this supplier yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW TO USE -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Use this page as the <strong>single source of truth</strong> for one supplier – profile, finance, and all documents.</li>
            <li>Check the <strong>Financial snapshot</strong> to see total purchases, invoices, payments and outstanding BDT balance.</li>
            <li>Scroll through <strong>Purchases & invoices</strong> and <strong>Quotes & orders</strong> tables for historical documents; click doc numbers from future versions once linking is wired.</li>
            <li>Review the <strong>Payment history</strong> timeline when reconciling ledgers or resolving disputes.</li>
            <li>Use <strong>Edit</strong> on top to update contact, address, or terms – changes will apply to all new documents going forward.</li>
        </ul>
    </section>
</div>