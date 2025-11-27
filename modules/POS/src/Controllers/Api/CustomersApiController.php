<?php
declare(strict_types=1);

namespace Modules\POS\Controllers\Api;

use Shared\DB;

final class CustomersApiController
{
    /* ---------- helpers ---------- */
    private function pdo() { return method_exists(DB::class,'tenant') ? DB::tenant() : DB::pdo(); }
    private function orgId(array $p): int { return (int)($p['org_id'] ?? 0); }
    private function json($data, int $code=200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /t/{org}/apps/pos/api/customers/search?q=
     * Returns: [{id,name,email,phone,address, reward_balance_bdt}]
     */
    public function search(array $ctx): void
    {
        $orgId = $this->orgId($ctx);
        if ($orgId <= 0) return $this->json([], 403);

        $q = trim((string)($_GET['q'] ?? ''));
        $pdo = $this->pdo();

        // Support both legacy `customers` and module `pos_customers`
        $table = DB::fetch("SHOW TABLES LIKE 'pos_customers'") ? 'pos_customers' : 'customers';

        $stmt = $pdo->prepare("
            SELECT id, name,
                   COALESCE(email,'')  AS email,
                   COALESCE(phone,'')  AS phone,
                   COALESCE(address,'') AS address,
                   COALESCE(loyalty_points,0) AS lp
              FROM {$table}
             WHERE ".($table==='pos_customers' ? "org_id = ? AND " : "")."(name LIKE ? OR email LIKE ? OR phone LIKE ?)
             ORDER BY name ASC
             LIMIT 20
        ");
        $bind = ($table==='pos_customers')
            ? [$orgId, "%{$q}%", "%{$q}%", "%{$q}%"]
            : ["%{$q}%", "%{$q}%", "%{$q}%"];
        $stmt->execute($bind);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r['reward_balance_bdt'] = round(((int)($r['lp'] ?? 0)) / 100, 2);
            unset($r['lp']);
        }
        unset($r);

        $this->json($rows);
    }
}