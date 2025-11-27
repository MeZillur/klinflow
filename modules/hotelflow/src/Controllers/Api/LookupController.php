<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers\Api;

use Modules\hotelflow\Controllers\BaseController;
use PDO;

final class LookupController extends BaseController
{
    // GET /api/lookup/room-types
    public function roomTypes(array $ctx): void
    {
        $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();
        try {
            $st = $pdo->prepare("SELECT id, name, code, base_occupancy AS occupancy FROM hms_room_types WHERE org_id=:o ORDER BY name ASC");
            $st->execute([':o'=>$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->json(['ok'=>true,'data'=>$rows]);
        } catch (\Throwable $e) {
            // Soft fallback if table not ready
            $this->json(['ok'=>true,'data'=>[]]);
        }
    }

    // GET /api/lookup/payment-methods
    public function paymentMethods(array $ctx): void
    {
        $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();
        try {
            $st = $pdo->prepare("SELECT id, code, name FROM hms_payment_methods WHERE org_id=:o ORDER BY sort_order ASC, name ASC");
            $st->execute([':o'=>$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->json(['ok'=>true,'data'=>$rows]);
        } catch (\Throwable $e) {
            // Provide a minimal sensible default list for FE forms
            $this->json(['ok'=>true,'data'=>[
                ['id'=>-1,'code'=>'cash','name'=>'Cash'],
                ['id'=>-2,'code'=>'card','name'=>'Card'],
                ['id'=>-3,'code'=>'bank','name'=>'Bank'],
            ]]);
        }
    }
}