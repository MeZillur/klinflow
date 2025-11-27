<?php
/**
 * GL Ledger view
 *
 * Expects:
 * - $title
 * - $base
 * - $rows           (summary by account)
 * - $entries        (optional detail for one account)
 * - $entryTotals    ['dr' => float, 'cr' => float]
 * - $accountCode    (string, from ?account=...)
 * - $from, $to      (date filters)
 * - $page, $pages   (pagination for accounts)
 * - $totalAccounts
 * - $money          (callable float -> string)
 */
?>
<div class="container" style="max-width:1100px;margin:0 auto;padding:16px;">
    <h1 style="margin:0 0 16px;font-size:22px;">
        <?= htmlspecialchars($title ?? 'General Ledger', ENT_QUOTES, 'UTF-8') ?>
    </h1>

    <!-- Filters -->
    <form method="get"
          style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:16px;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label for="from" style="font-size:12px;color:#4b5563;">From</label>
            <input type="date"
                   id="from"
                   name="from"
                   value="<?= htmlspecialchars($from ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   style="padding:6px 8px;border-radius:4px;border:1px solid #d1d5db;min-width:150px;">
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;">
            <label for="to" style="font-size:12px;color:#4b5563;">To</label>
            <input type="date"
                   id="to"
                   name="to"
                   value="<?= htmlspecialchars($to ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   style="padding:6px 8px;border-radius:4px;border:1px solid #d1d5db;min-width:150px;">
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;">
            <label for="account" style="font-size:12px;color:#4b5563;">Account code (starts with)</label>
            <input type="text"
                   id="account"
                   name="account"
                   placeholder="e.g. 4000"
                   value="<?= htmlspecialchars($accountCode ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   style="padding:6px 8px;border-radius:4px;border:1px solid #d1d5db;min-width:160px;">
        </div>

        <div style="display:flex;gap:8px;margin-top:18px;">
            <button type="submit"
                    style="padding:6px 12px;border-radius:4px;border:1px solid #2563eb;background:#2563eb;color:#fff;font-size:13px;cursor:pointer;">
                Apply
            </button>
            <a href="<?= htmlspecialchars($base.'/gl/ledger', ENT_QUOTES, 'UTF-8') ?>"
               style="padding:6px 12px;border-radius:4px;border:1px solid #d1d5db;background:#fff;color:#374151;font-size:13px;text-decoration:none;">
                Reset
            </a>
        </div>
    </form>

    <!-- Summary info -->
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;font-size:13px;color:#4b5563;">
        <div><strong>Total accounts:</strong> <?= (int)($totalAccounts ?? 0) ?></div>
        <?php if (!empty($from) || !empty($to)): ?>
            <div>
                <strong>Period:</strong>
                <?= $from ? htmlspecialchars($from, ENT_QUOTES, 'UTF-8') : '…' ?>
                →
                <?= $to ? htmlspecialchars($to, ENT_QUOTES, 'UTF-8') : '…' ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($accountCode)): ?>
            <div><strong>Selected account:</strong>
                <?= htmlspecialchars($accountCode, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Account summary table -->
    <div style="overflow-x:auto;margin-bottom:24px;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:600px;">
            <thead>
            <tr style="background:#f3f4f6;">
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Account code</th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Account name</th>
                <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb;">Debit</th>
                <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb;">Credit</th>
                <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb;">Balance (Dr - Cr)</th>
                <th style="text-align:center;padding:8px;border-bottom:1px solid #e5e7eb;">Details</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($rows)): ?>
                <?php
                $grandDr = 0.0;
                $grandCr = 0.0;
                foreach ($rows as $row):
                    $code = (string)($row['account_code'] ?? '');
                    $name = (string)($row['account_name'] ?? '');
                    $dr   = (float)($row['dr_total'] ?? 0);
                    $cr   = (float)($row['cr_total'] ?? 0);
                    $bal  = $dr - $cr;
                    $grandDr += $dr;
                    $grandCr += $cr;

                    // build detail link, preserving date filters
                    $qs = [
                        'account' => $code,
                    ];
                    if (!empty($from)) { $qs['from'] = $from; }
                    if (!empty($to))   { $qs['to']   = $to;   }
                    $detailUrl = $base.'/gl/ledger?'.http_build_query($qs);
                ?>
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
                            <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"
                               style="color:#2563eb;text-decoration:none;">
                                <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
                            <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right;">
                            <?= $money($dr) ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right;">
                            <?= $money($cr) ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right;
                                   color:<?= $bal >= 0 ? '#065f46' : '#b91c1c' ?>;">
                            <?= $money($bal) ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:center;">
                            <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"
                               style="font-size:12px;color:#2563eb;text-decoration:none;">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <!-- grand total row -->
                <tr style="background:#f9fafb;font-weight:600;">
                    <td colspan="2" style="padding:8px;border-top:1px solid #e5e7eb;text-align:right;">
                        Totals:
                    </td>
                    <td style="padding:8px;border-top:1px solid #e5e7eb;text-align:right;">
                        <?= $money($grandDr) ?>
                    </td>
                    <td style="padding:8px;border-top:1px solid #e5e7eb;text-align:right;">
                        <?= $money($grandCr) ?>
                    </td>
                    <td style="padding:8px;border-top:1px solid #e5e7eb;text-align:right;">
                        <?= $money($grandDr - $grandCr) ?>
                    </td>
                    <td style="padding:8px;border-top:1px solid #e5e7eb;"></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="padding:12px;text-align:center;color:#6b7280;">
                        No ledger data found for this period.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (($pages ?? 1) > 1): ?>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:24px;font-size:13px;">
            <?php
            $buildPageUrl = function(int $p) use ($base, $from, $to, $accountCode) {
                $qs = ['page' => $p];
                if (!empty($from))       { $qs['from']    = $from; }
                if (!empty($to))         { $qs['to']      = $to; }
                if (!empty($accountCode)){ $qs['account'] = $accountCode; }
                return $base.'/gl/ledger?'.http_build_query($qs);
            };
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($buildPageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>"
                   style="padding:4px 10px;border-radius:4px;border:1px solid #d1d5db;text-decoration:none;color:#374151;">
                    ‹ Prev
                </a>
            <?php endif; ?>

            <span style="align-self:center;color:#4b5563;">
                Page <?= (int)$page ?> of <?= (int)$pages ?>
            </span>

            <?php if ($page < $pages): ?>
                <a href="<?= htmlspecialchars($buildPageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>"
                   style="padding:4px 10px;border-radius:4px;border:1px solid #d1d5db;text-decoration:none;color:#374151;">
                    Next ›
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Detail section for a single account -->
    <?php if (!empty($accountCode) && !empty($entries)): ?>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0 12px;">

        <h2 style="margin:0 0 8px;font-size:18px;">
            Account detail: <?= htmlspecialchars($accountCode, ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <div style="font-size:13px;color:#4b5563;margin-bottom:8px;">
            Period:
            <?= $from ? htmlspecialchars($from, ENT_QUOTES, 'UTF-8') : '…' ?>
            →
            <?= $to ? htmlspecialchars($to, ENT_QUOTES, 'UTF-8') : '…' ?>
        </div>

        <div style="overflow-x:auto;margin-bottom:16px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:600px;">
                <thead>
                <tr style="background:#f3f4f6;">
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Date</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Entry no.</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Journal memo</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Line memo</th>
                    <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb;">Debit</th>
                    <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb;">Credit</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $line): ?>
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
                            <?= htmlspecialchars($line['entry_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
                            <?= htmlspecialchars($line['entry_no'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
                            <?= htmlspecialchars($line['journal_memo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
                            <?= htmlspecialchars($line['line_memo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right;">
                            <?= $money((float)($line['dr'] ?? 0)) ?>
                        </td>
                        <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right;">
                            <?= $money((float)($line['cr'] ?? 0)) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#f9fafb;font-weight:600;">
                    <td colspan="4" style="padding:8px;border-top:1px solid #e5e7eb;text-align:right;">
                        Totals:
                    </td>
                    <td style="padding:8px;border-top:1px solid #e5e7eb;text-align:right;">
                        <?= $money((float)($entryTotals['dr'] ?? 0)) ?>
                    </td>
                    <td style="padding:8px;border-top:1px solid #e5e7eb;text-align:right;">
                        <?= $money((float)($entryTotals['cr'] ?? 0)) ?>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    <?php elseif (!empty($accountCode)): ?>
        <p style="font-size:13px;color:#6b7280;margin-top:12px;">
            No entries found for this account in the selected period.
        </p>
    <?php endif; ?>
</div>