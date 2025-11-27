<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;

final class HomeController extends BaseController
{
    public function index(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        // ---------- helpers that never explode during phased DB build ----------
        $exists = function(string $table) use ($pdo): bool {
            try {
                $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
                $q->execute([':t'=>$table]);
                return (bool)$q->fetchColumn();
            } catch (\Throwable $e) { return false; }
        };
        $count = function(string $sql, array $params = []) use ($pdo): int {
            try {
                $st = $pdo->prepare($sql);
                foreach ($params as $k=>$v) $st->bindValue(is_int($k)?$k+1:":$k", $v);
                $st->execute();
                return (int)$st->fetchColumn();
            } catch (\Throwable $e) { return 0; }
        };

        // ---------- checklist items (safe) ----------
        $hasProperties   = $exists('hms_properties');
        $hasRoomTypes    = $exists('hms_room_types');
        $hasRooms        = $exists('hms_rooms');
        $hasRatePlans    = $exists('hms_rate_plans');
        $hasInventory    = $exists('hms_inventory');
        $hasReservations = $exists('hms_reservations');
        $hasFolios       = $exists('hms_folios');
        $hasPayments     = $exists('hms_payments');

        $stats = [
            'properties'   => $hasProperties   ? $count("SELECT COUNT(*) FROM hms_properties WHERE org_id=:o", ['o'=>$orgId]) : 0,
            'room_types'   => $hasRoomTypes    ? $count("SELECT COUNT(*) FROM hms_room_types WHERE org_id=:o", ['o'=>$orgId]) : 0,
            'rooms'        => $hasRooms        ? $count("SELECT COUNT(*) FROM hms_rooms WHERE org_id=:o", ['o'=>$orgId]) : 0,
            'rate_plans'   => $hasRatePlans    ? $count("SELECT COUNT(*) FROM hms_rate_plans WHERE org_id=:o", ['o'=>$orgId]) : 0,
            'inventory'    => $hasInventory    ? $count("SELECT COUNT(*) FROM hms_inventory WHERE org_id=:o AND date>=CURDATE()", ['o'=>$orgId]) : 0,
            'reservations' => $hasReservations ? $count("SELECT COUNT(*) FROM hms_reservations WHERE org_id=:o", ['o'=>$orgId]) : 0,
            'folios'       => $hasFolios       ? $count("SELECT COUNT(*) FROM hms_folios WHERE org_id=:o", ['o'=>$orgId]) : 0,
            'payments'     => $hasPayments     ? $count("SELECT COUNT(*) FROM hms_payments WHERE org_id=:o", ['o'=>$orgId]) : 0,
        ];

        // Compute a simple progress % of “core ready”
        $readyBits = 0;
        $readyBits += (int)$hasProperties;
        $readyBits += (int)$hasRoomTypes;
        $readyBits += (int)$hasRooms;
        $readyBits += (int)$hasRatePlans;
        $readyBits += (int)$hasInventory;
        $readyBits += (int)$hasReservations;
        $readyBits += (int)$hasFolios;
        $readyBits += (int)$hasPayments;
        $progress = (int)round(($readyBits/8)*100);

        $this->view('home/index', [
            'title'    => 'HotelFlow — Getting Started',
            'stats'    => $stats,
            'flags'    => [
                'properties'   => $hasProperties,
                'room_types'   => $hasRoomTypes,
                'rooms'        => $hasRooms,
                'rate_plans'   => $hasRatePlans,
                'inventory'    => $hasInventory,
                'reservations' => $hasReservations,
                'folios'       => $hasFolios,
                'payments'     => $hasPayments,
            ],
            'progress' => $progress,
        ], $c);
    }
}