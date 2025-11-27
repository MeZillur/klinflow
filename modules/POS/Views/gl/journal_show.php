<?php
/** @var array $journal */
/** @var array $entries */
/** @var callable $money */

$base = rtrim($base ?? '/apps/pos', '/');
$pk   = isset($journal['journal_id']) ? 'journal_id' : (isset($journal['id']) ? 'id' : null);
?>
<div class="px-6 py-4 space-y-4">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Journal #<?php echo htmlspecialchars((string)($journal['jno'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a href="<?php echo htmlspecialchars($base.'/gl/journals', ENT_QUOTES, 'UTF-8'); ?>"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-gray-700 text-white hover:bg-gray-800">
            Back to Journals
        </a>
    </div>

    <!-- Header -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm bg-white border border-gray-200 rounded-md p-3">
        <div>
            <div class="text-gray-500">Date</div>
            <div class="font-medium">
                <?php echo htmlspecialchars((string)($journal['jdate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
        <div>
            <div class="text-gray-500">Type</div>
            <div class="font-medium">
                <?php echo htmlspecialchars((string)($journal['jtype'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
        <div>
            <div class="text-gray-500">Branch</div>
            <div class="font-medium">
                <?php echo htmlspecialchars((string)($journal['branch_name'] ?? ($journal['branch_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>

        <div class="md:col-span-3">
            <div class="text-gray-500">Memo</div>
            <div class="font-medium">
                <?php echo htmlspecialchars((string)($journal['memo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>

        <div>
            <div class="text-gray-500">Source</div>
            <div class="font-medium text-xs">
                <?php
                $parts = [];
                if (!empty($journal['source_module'])) $parts[] = $journal['source_module'];
                if (!empty($journal['source_table']))  $parts[] = $journal['source_table'];
                if (!empty($journal['source_id']))     $parts[] = '#'.$journal['source_id'];
                echo htmlspecialchars(implode(' · ', $parts), ENT_QUOTES, 'UTF-8');
                ?>
            </div>
        </div>
    </div>

    <!-- Lines -->
    <div class="overflow-x-auto bg-white border border-gray-200 rounded-md">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100">
            <tr>
                <th class="px-3 py-2 text-left">Account</th>
                <th class="px-3 py-2 text-left">Name</th>
                <th class="px-3 py-2 text-left">Line Memo</th>
                <th class="px-3 py-2 text-right">Debit</th>
                <th class="px-3 py-2 text-right">Credit</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="5" class="px-3 py-4 text-center text-gray-500">
                        No entries found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($entries as $line): ?>
                    <?php
                    $code = $line['account_code'] ?? '';
                    $name = $line['account_name'] ?? '';
                    $memo = $line['memo'] ?? ($line['line_memo'] ?? '');
                    $dr   = (float)($line['dr'] ?? 0);
                    $cr   = (float)($line['cr'] ?? 0);
                    ?>
                    <tr class="border-t">
                        <td class="px-3 py-2 font-mono"><?php echo htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="px-3 py-2 text-xs text-gray-700">
                            <?php echo htmlspecialchars((string)$memo, ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <?php echo $dr ? '৳'.$money($dr) : ''; ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <?php echo $cr ? '৳'.$money($cr) : ''; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- Totals -->
                <tr class="border-t bg-gray-50 font-semibold">
                    <td colspan="3" class="px-3 py-2 text-right">Totals</td>
                    <td class="px-3 py-2 text-right">
                        ৳<?php echo $money((float)$totalDr); ?>
                    </td>
                    <td class="px-3 py-2 text-right">
                        ৳<?php echo $money((float)$totalCr); ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>