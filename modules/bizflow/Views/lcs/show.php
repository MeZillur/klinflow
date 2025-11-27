<?php
declare(strict_types=1);

/**
 * @var array  $org
 * @var string $module_base
 * @var array  $lc
 * @var array  $history
 * @var string $title
 * @var bool   $storage_ready
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
$brand       = '#228B22';

$id      = (int)($lc['id'] ?? 0);
$lcNo    = trim((string)($lc['lc_no'] ?? ''));
$status  = strtolower((string)($lc['status'] ?? 'open'));
$stage   = strtolower((string)($lc['stage'] ?? 'contract'));
$amount  = (float)($lc['lc_amount'] ?? 0);
$currency= (string)($lc['currency'] ?? 'USD');

$badgeClass = 'bg-slate-100 text-slate-700 border border-slate-200';
if (in_array($status, ['open','active','documents_pending'], true)) {
    $badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
} elseif ($status === 'retired') {
    $badgeClass = 'bg-sky-50 text-sky-700 border border-sky-100';
} elseif (in_array($status, ['cancelled','expired'], true)) {
    $badgeClass = 'bg-rose-50 text-rose-700 border border-rose-100';
}
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'LC details') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Lifecycle view for LC <?= $h($lcNo ?: ('#'.$id)) ?> — applicant, beneficiary, dates and shipment status.
            </p>
        </div>

        <?php
        $tabs = [
            ['LC register', $module_base.'/lcs',        false],
            ['New LC',      $module_base.'/lcs/create', false],
            ['This LC',     $module_base.'/lcs/'.$id,   true],
        ];
        ?>
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php foreach ($tabs as [$label, $url, $active]): ?>
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

    <?php if (!$storage_ready): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            This LC is coming from <strong>demo data</strong>. Once the <code>biz_lcs</code> table is live, all details will be stored there.
        </div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: Key summary + timeline + sections -->
        <section class="space-y-4">

            <!-- Summary card -->
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-xs font-medium text-slate-500">LC no</div>
                        <div class="mt-0.5 text-lg font-semibold text-slate-900">
                            <?= $h($lcNo ?: 'Not set') ?>
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500">
                            Contract: <?= $h($lc['contract_no'] ?? '—') ?> · PI: <?= $h($lc['pi_no'] ?? '—') ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-medium text-slate-500">LC amount</div>
                        <div class="mt-0.5 text-lg font-semibold text-slate-900">
                            <?= $h(number_format($amount, 2)) ?> <span class="text-xs text-slate-500"><?= $h($currency) ?></span>
                        </div>
                        <div class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] <?= $badgeClass ?>">
                                <?= $h(ucfirst($status ?: 'open')) ?>
                                <?php if ($stage): ?>
                                    <span class="mx-1 text-slate-300">•</span>
                                    <span class="text-slate-600"><?= $h(ucwords(str_replace('_', ' ', $stage))) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 text-[11px] text-slate-600 md:grid-cols-3">
                    <div>
                        <div class="font-medium text-slate-700">Applicant</div>
                        <div><?= $h($lc['applicant_name'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="font-medium text-slate-700">Beneficiary</div>
                        <div><?= $h($lc['beneficiary_name'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="font-medium text-slate-700">Issuing bank</div>
                        <div><?= $h($lc['issuing_bank'] ?? '—') ?></div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">
                    LC lifecycle timeline
                </h2>
                <?php if (!empty($history)): ?>
                    <ol class="relative ml-3 border-l border-slate-200 text-xs text-slate-700">
                        <?php
                        $labels = [
                            'contract'  => 'Sales contract agreed',
                            'pi'        => 'Proforma invoice / indent confirmed',
                            'opened'    => 'LC opened by issuing bank',
                            'shipment'  => 'Latest shipment date',
                            'docs'      => 'Shipping documents received',
                            'maturity'  => 'LC maturity date',
                            'retired'   => 'LC retired / documents fully paid',
                        ];
                        ?>
                        <?php foreach ($history as $event): ?>
                            <?php
                            $ts   = (string)($event['ts'] ?? '');
                            $kind = (string)($event['kind'] ?? '');
                            $txt  = $event['text'] ?? ($labels[$kind] ?? 'Event');
                            ?>
                            <li class="mb-3 ml-2">
                                <div class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border border-white bg-emerald-500"></div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                        <?= $h(strtoupper($kind ?: 'event')) ?>
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700">
                                        <?= $h($ts) ?>
                                    </span>
                                </div>
                                <p class="mt-1 text-[12px] text-slate-800">
                                    <?= $h($txt) ?>
                                </p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="text-xs text-slate-500">
                        No lifecycle events recorded yet. Once you fill dates (contract, PI, opened, shipment, docs, maturity, retired),
                        the timeline will populate automatically.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Detail sections: contract + shipment + compliance -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Contract & goods -->
                <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm text-xs">
                    <h2 class="mb-2 text-sm font-semibold text-slate-800">Contract &amp; goods</h2>
                    <dl class="space-y-1">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Contract no</dt>
                            <dd class="font-medium text-slate-800"><?= $h($lc['contract_no'] ?? '—') ?></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">PI / Indent</dt>
                            <dd class="font-medium text-slate-800"><?= $h($lc['pi_no'] ?? '—') ?></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">PI date</dt>
                            <dd class="font-medium text-slate-800"><?= $h($lc['pi_date'] ?? '—') ?></dd>
                        </div>
                        <div class="mt-2">
                            <dt class="text-slate-500">Goods description</dt>
                            <dd class="font-medium text-slate-800">
                                <?= $h($lc['goods_short'] ?? '—') ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">HS code(s)</dt>
                            <dd class="font-medium text-slate-800">
                                <?= $h($lc['hs_codes'] ?? '—') ?>
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- Shipment & ports -->
                <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm text-xs">
                    <h2 class="mb-2 text-sm font-semibold text-slate-800">Shipment &amp; ports</h2>
                    <dl class="space-y-1">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Last shipment date</dt>
                            <dd class="font-medium text-slate-800"><?= $h($lc['last_shipment_date'] ?? '—') ?></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Port of loading</dt>
                            <dd class="font-medium text-slate-800"><?= $h($lc['port_of_loading'] ?? '—') ?></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Port of discharge</dt>
                            <dd class="font-medium text-slate-800"><?= $h($lc['port_of_discharge'] ?? '—') ?></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Incoterm</dt>
                            <dd class="font-medium text-slate-800"><?= $h($lc['incoterm'] ?? '—') ?></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Partial shipment</dt>
                            <dd class="font-medium text-slate-800">
                                <?php
                                $ps = $lc['partial_shipment'] ?? '';
                                echo $h($ps === 'allowed' ? 'Allowed' : ($ps === 'prohibited' ? 'Prohibited' : 'As per LC'));
                                ?>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Transshipment</dt>
                            <dd class="font-medium text-slate-800">
                                <?php
                                $ts = $lc['transshipment'] ?? '';
                                echo $h($ts === 'allowed' ? 'Allowed' : ($ts === 'prohibited' ? 'Prohibited' : 'As per LC'));
                                ?>
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- Compliance / banking -->
            <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm text-xs">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Compliance &amp; banking details</h2>
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <div class="text-slate-500">LCA no</div>
                        <div class="font-medium text-slate-800"><?= $h($lc['lca_no'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500">Country of origin</div>
                        <div class="font-medium text-slate-800"><?= $h($lc['country_of_origin'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500">Insurance policy</div>
                        <div class="font-medium text-slate-800"><?= $h($lc['insurance_policy_no'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <div>
                        <div class="text-slate-500">Opened at</div>
                        <div class="font-medium text-slate-800"><?= $h($lc['opened_at'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500">Expiry date</div>
                        <div class="font-medium text-slate-800"><?= $h($lc['expiry_date'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="text-slate-500">Maturity date</div>
                        <div class="font-medium text-slate-800"><?= $h($lc['maturity_date'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <div>
                        <div class="text-slate-500">Cash margin %</div>
                        <div class="font-medium text-slate-800">
                            <?= $h($lc['margin_percent'] ?? '—') ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-500">Margin amount</div>
                        <div class="font-medium text-slate-800">
                            <?= $h($lc['margin_amount'] ?? '—') ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-500">Docs received at bank</div>
                        <div class="font-medium text-slate-800">
                            <?= $h($lc['docs_received_at'] ?? '—') ?>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="text-slate-500">Internal reference / tag</div>
                    <div class="font-medium text-slate-800">
                        <?= $h($lc['internal_ref'] ?? '—') ?>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="text-slate-500">Internal notes</div>
                    <div class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-700">
                        <?= $h($lc['notes'] ?? 'No internal notes recorded yet.') ?>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                    <div class="flex gap-2 text-[11px] text-slate-500">
                        <span>LC type: <strong><?= $h($lc['lc_type'] ?? '—') ?></strong></span>
                        <span class="text-slate-300">•</span>
                        <span>Engine: <?= $storage_ready ? 'Live schema' : 'Demo schema' ?></span>
                    </div>
                    <div class="flex gap-2">
                        <a href="<?= $h($module_base.'/lcs') ?>"
                           class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                            Back to register
                        </a>
                        <a href="<?= $h($module_base.'/lcs/'.$id.'/edit') ?>"
                           class="rounded-lg bg-emerald-600 px-4 py-1.5 text-[11px] font-semibold text-white shadow-sm hover:bg-emerald-700">
                            Edit LC
                        </a>
                    </div>
                </div>
            </section>
        </section>

        <!-- RIGHT: How to use / guidance -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Use this screen as the <strong>single source of truth</strong> for each LC — contract, PI, banks, shipment and maturity.</li>
                    <li>Follow the <strong>timeline</strong> to confirm you didn’t miss any critical event (shipment, docs, maturity, retirement).</li>
                    <li>Keep an eye on <strong>status</strong> and <strong>expiry</strong> — expired but unretired LCs are high risk for your FX exposure.</li>
                    <li>Internal notes help you remember <strong>Bangladesh Bank margins, branch instructions and special approvals</strong>.</li>
                    <li>When your schema goes live, this page will link to <strong>LC-related imports, bills of entry and supplier payments</strong> across BizFlow.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>