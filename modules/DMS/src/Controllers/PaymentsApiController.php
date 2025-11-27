<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

/**
 * Payments API for typeaheads. Kept legacy method names, but
 * "dealers" now queries suppliers to match the new model.
 */
final class PaymentsApiController extends BaseController
{
    // Legacy: return arrays so older router lines with json_encode(...) work.

    /** GET /api/payments/customers?q=&limit= */
    public function customers(array $ctx): array
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min((int)($_GET['limit'] ?? 20), 50));

        $sql = "
            SELECT id, code, name, phone, email
            FROM dms_customers
            WHERE org_id = :org
              AND (:q = '' OR name LIKE :like OR phone LIKE :like OR code LIKE :like)
            ORDER BY name
            LIMIT {$lim}";
        $st = $pdo->prepare($sql);
        $like = "%{$q}%";
        $st->execute([':org'=>$orgId, ':q'=>$q, ':like'=>$like]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['ok'=>true, 'items'=>$rows];
    }

    /**
     * GET /api/payments/dealers?q=&limit=
     * Backwards compatible endpoint name, but it queries suppliers.
     */
    public function dealers(array $ctx): array
    {
        return $this->suppliers($ctx);
    }

    /** GET /api/payments/suppliers?q=&limit= */
    public function suppliers(array $ctx): array
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min((int)($_GET['limit'] ?? 20), 50));

        $sql = "
            SELECT id, code, name, phone, email
            FROM dms_suppliers
            WHERE org_id = :org
              AND (:q = '' OR name LIKE :like OR phone LIKE :like OR code LIKE :like)
            ORDER BY name
            LIMIT {$lim}";
        $st = $pdo->prepare($sql);
        $like = "%{$q}%";
        $st->execute([':org'=>$orgId, ':q'=>$q, ':like'=>$like]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['ok'=>true, 'items'=>$rows];
    }
}