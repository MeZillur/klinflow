<?php
/** @var array $rows */
/** @var callable $money */
/** @var array $branches */
/** @var array $summary */

$base      = rtrim($base ?? '/apps/pos', '/');
$selfUrl   = htmlspecialchars($base.'/gl/journals', ENT_QUOTES, 'UTF-8');
?>
<div class="px-6 py-4 space-y-4">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">GL Journals</h1>
        <a href="<?php echo htmlspecialchars($base.'/accounting', ENT_QUOTES, 'UTF-8'); ?>"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-green-700 text-white hover:bg-green-800">
            Back to Accounting
        </a>
    </div>

    <!-- Filters -->
    <form method="get" action="<?php echo $selfUrl; ?>" class="grid grid-cols-1 md:grid-cols-5 gap-3 text-sm bg-gray-50 p-3 rounded-md border border-gray-200">
        <div>
            <label class="block mb-1 text-gray-600">Search</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border rounded px-2 py-1" placeholder="Jno / memo">
        </div>

        <div>
            <label class="block mb-1 text-gray-600">Type</label>
            <input type="text" name="jtype" value="<?php echo htmlspecialchars($jtype ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border rounded px-2 py-1" placeholder="EXP / PAY / RCPT">
        </div>

        <div>
            <label class="block mb-1 text-gray-600">From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border rounded px-2 py-1">
        </div>

        <div>
            <label class="block mb-1 text-gray-600">To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   class="w-full border rounded px-2 py-1">
        </div>

        <div class="flex items-end gap-2">
            <button type="submit"
                    class="px-3 py-1.5 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                Filter
            </button>
            <a href="<?php echo $selfUrl; ?>" class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-700">
                Reset
            </a>
        </div>
    </form>

    <!-- Summary cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
        <div class="p-3 rounded-lg bg-white border border-gray-200">
            <div class="text-gray-500">Total Journals</div>
            <div class="text-lg font-semibold"><?php echo (int)($total ?? 0); ?></div>
        </div>
        <div class="p-3 rounded-lg bg-white border border-gray-200">
            <div class="text-gray-500">Total Debit (all)</div>
            <div class="text-lg font-semibold">
                ৳<?php echo $money((float)($summary['dr'] ?? 0)); ?>
            </div>
        </div>
        <div class="p-3 rounded-lg bg-white border border-gray-200">
            <div class="text-gray-500">Total Credit (all)</div>
            <div class="text-lg font-semibold">
                ৳<?php echo $money((float)($summary['cr'] ?? 0)); ?>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-white border border-gray-200 rounded-md">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100">
            <tr>
                <th class="px-3 py-2 text-left">Date</th>
                <th class="px-3 py-2 text-left">J.No</th>
                <th class="px-3 py-2 text-left">Type</th>
                <th class="px-3 py-2 text-left">Memo</th>
                <th class="px-3 py-2 text-right">Debit</th>
                <th class="px-3 py-2 text-right">Credit</th>
                <th class="px-3 py-2 text-left">Branch</th>
                <th class="px-3 py-2 text-left">Status</th>
                <th class="px-3 py-2 text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="9" class="px-3 py-4 text-center text-gray-500">
                        No journals found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $jid   = (int)$row['journal_id'];
                    $jdate = $row['jdate'] ?? '';
                    $jno   = $row['jno']   ?? '';
                    $jtype = $row['jtype'] ?? '';
                    $memo  = $row['memo']  ?? '';
                    $dr    = (float)($row['dr_total'] ?? 0);
                    $cr    = (float)($row['cr_total'] ?? 0);
                    $branchName = $row['branch_name'] ?? ($row['branch_id'] ?? '');
                    $statusVal  = $row['status'] ?? '';
                    ?>
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-3 py-2"><?php echo htmlspecialchars((string)$jdate, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="px-3 py-2 font-mono">
                            <?php echo htmlspecialchars((string)$jno, ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php echo htmlspecialchars((string)$jtype, ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php echo htmlspecialchars((string)$memo, ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            ৳<?php echo $money($dr); ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            ৳<?php echo $money($cr); ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php echo htmlspecialchars((string)$branchName, ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php echo htmlspecialchars((string)$statusVal, ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="<?php echo htmlspecialchars($base.'/gl/journals/'.$jid, ENT_QUOTES, 'UTF-8'); ?>"
                               class="inline-flex items-center px-2 py-1 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-100">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (($pages ?? 1) > 1): ?>
        <div class="flex justify-between items-center text-sm text-gray-600 mt-2">
            <div>Page <?php echo (int)$page; ?> of <?php echo (int)$pages; ?></div>
            <div class="space-x-2">
                <?php
                $buildLink = function (int $p) use ($selfUrl, $q, $jtype, $from, $to, $branchId, $status) {
                    $params = [
                        'page'      => $p,
                        'q'         => $q,
                        'jtype'     => $jtype,
                        'from'      => $from,
                        'to'        => $to,
                        'branch_id' => $branchId,
                        'status'    => $status,
                    ];
                    return $selfUrl.'?'.http_build_query($params);
                };
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars($buildLink($page-1), ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a href="<?php echo htmlspecialchars($buildLink($page+1), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>