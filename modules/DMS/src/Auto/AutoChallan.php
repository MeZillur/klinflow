<?php
declare(strict_types=1);

namespace Modules\dms\Auto;

use PDO;
use Shared\DB;
use Modules\dms\Services\ChallanService;

final class AutoChallan
{
    /**
     * Call this right after an invoice is finalized/issued.
     * It is idempotent per (org_id, invoice_id).
     */
    public static function onInvoiceIssued(int $orgId, int $invoiceId, int $actorId): int
    {
        $pdo = DB::pdo();
        return ChallanService::generateForInvoice($pdo, $orgId, $invoiceId, $actorId);
    }
}