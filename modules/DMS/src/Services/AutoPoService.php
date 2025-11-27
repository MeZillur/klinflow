<?php
declare(strict_types=1);

namespace Modules\DMS\Services;

use PDO;

final class AutoPoService
{
    /**
     * @return array<int,array{supplier_name:string,items:array<int,array>}>
     *         supplier_id => ['supplier_name'=>..., 'items'=>[rows from cache]]
     */
    public static function groupBySupplier(PDO $pdo, int $orgId, array $cacheRows): array {
        $supNames = self::supplierNames($pdo, $orgId);
        $out = [];
        foreach ($cacheRows as $r) {
            $qty = (float)($r['suggested_qty'] ?? 0);
            if ($qty <= 0) continue;
            $sid = (int)($r['supplier_id'] ?? 0);
            if (!isset($out[$sid])) {
                $out[$sid] = [
                    'supplier_name' => $supNames[$sid] ?? ($sid ? ('Supplier #'.$sid) : 'No Supplier'),
                    'items' => []
                ];
            }
            $out[$sid]['items'][] = $r;
        }
        ksort($out);
        return $out;
    }

    private static function supplierNames(PDO $pdo, int $orgId): array {
        $names = [];
        try {
            $q = $pdo->prepare("SELECT id, name FROM suppliers WHERE org_id=?");
            $q->execute([$orgId]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $names[(int)$r['id']] = (string)$r['name'];
            }
        } catch (\Throwable) {}
        return $names;
    }
}