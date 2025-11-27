<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use DateTimeImmutable;
use PDO;

final class DashboardController extends BaseController
{
    public function index(array $ctx = []): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)($c['org_id'] ?? 0);
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $pdo   = $this->pdo();

        /* ---------- tiny safe helpers ---------- */
        $scalar = function (string $sql, array $params = []) use ($pdo) {
            try {
                $st = $pdo->prepare($sql);
                foreach ($params as $k => $v) $st->bindValue(is_int($k) ? $k+1 : ":$k", $v);
                $st->execute();
                $v = $st->fetchColumn();
                if ($v === false || $v === null) return 0;
                return is_numeric($v) ? (int)$v : 0;
            } catch (\Throwable $e) { return 0; }
        };
        $row = function (string $sql, array $params = []) use ($pdo): array {
            try {
                $st = $pdo->prepare($sql);
                foreach ($params as $k => $v) $st->bindValue(is_int($k) ? $k+1 : ":$k", $v);
                $st->execute();
                return $st->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) { return []; }
        };

        /* ---------- core counts (all guarded) ---------- */
        $arrivals = $scalar("
            SELECT COUNT(*) FROM hms_reservations
            WHERE org_id=:o AND DATE(check_in)=CURDATE() AND status IN ('confirmed','guaranteed')
        ", ['o'=>$orgId]);

        $inhouse = $scalar("
            SELECT COUNT(*) FROM hms_stays
            WHERE org_id=:o AND status='in_house'
        ", ['o'=>$orgId]);

        $departures = $scalar("
            SELECT COUNT(*) FROM hms_reservations
            WHERE org_id=:o AND DATE(check_out)=CURDATE() AND status IN ('in_house','checked_in','confirmed','guaranteed')
        ", ['o'=>$orgId]);

        $res_30d = $scalar("
            SELECT COUNT(*) FROM hms_reservations
            WHERE org_id=:o AND status IN ('confirmed','guaranteed','in_house','checked_in')
              AND check_in <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND check_out >= CURDATE()
        ", ['o'=>$orgId]);

        $checkin_due_today   = $arrivals;
        $checkout_due_today  = $departures;

        $guests_total = $scalar("SELECT COUNT(*) FROM hms_guests WHERE org_id=:o", ['o'=>$orgId]);

        $room_types   = $scalar("SELECT COUNT(*) FROM hms_room_types WHERE org_id=:o", ['o'=>$orgId]);

        $rooms_total_tbl = $scalar("SELECT COUNT(*) FROM hms_rooms WHERE org_id=:o", ['o'=>$orgId]);

        // inventory snapshot preferred (if exists)
        $inv = $row("SELECT SUM(total_rooms) total, SUM(sold_rooms) sold
                     FROM hms_inventory WHERE org_id=:o AND date=:d",
                     ['o'=>$orgId,'d'=>$today]);
        $inv_total = (int)($inv['total'] ?? 0);
        $inv_sold  = (int)($inv['sold']  ?? 0);

        $rooms_total     = $inv_total > 0 ? $inv_total : $rooms_total_tbl;
        $rooms_sold      = $inv_total > 0 ? $inv_sold  : $inhouse;
        $rooms_available = max(0, $rooms_total - $rooms_sold);

        $occ_pct = ($rooms_total > 0) ? (int)round(100.0 * $rooms_sold / $rooms_total) : 0;

        $hk_dirty = $scalar("SELECT COUNT(*) FROM hms_rooms WHERE org_id=:o AND hk_status IN ('dirty','d')", ['o'=>$orgId]);
        $rooms_ooo = $scalar("SELECT COUNT(*) FROM hms_rooms WHERE org_id=:o AND status IN ('ooo','out_of_order')", ['o'=>$orgId]);

        $folios_open = $scalar("SELECT COUNT(*) FROM hms_folios WHERE org_id=:o AND status IN ('open','in_progress')", ['o'=>$orgId]);

        $payments_today = $scalar("SELECT ROUND(SUM(amount),0) FROM hms_payments WHERE org_id=:o AND DATE(created_at)=CURDATE()", ['o'=>$orgId]);

        $night_audit_runs = $scalar("SELECT COUNT(*) FROM hms_night_audit WHERE org_id=:o", ['o'=>$orgId]);

        // if you have a precomputed reports queue, otherwise just 0
        $reports_ready = $scalar("SELECT COUNT(*) FROM hms_reports WHERE org_id=:o AND status='ready'", ['o'=>$orgId]);

        // POS (optional)
        $pos_open_orders = $scalar("SELECT COUNT(*) FROM pos_orders WHERE org_id=:o AND status IN ('open','kitchen','served')", ['o'=>$orgId]);

        // users
        $settings_users = $scalar("SELECT COUNT(*) FROM org_users WHERE org_id=:o", ['o'=>$orgId]);

        /* ---------- bundle for the view (keys match the view) ---------- */
        $metrics = [
            'arrivals'            => $arrivals,
            'res_30d'             => $res_30d,
            'checkin_due_today'   => $checkin_due_today,
            'checkout_due_today'  => $checkout_due_today,
            'guests_total'        => $guests_total,
            'rooms_available'     => $rooms_available,
            'room_types'          => $room_types,
            'occ_pct'             => $occ_pct,
            'hk_dirty'            => $hk_dirty,
            'rooms_ooo'           => $rooms_ooo,
            'folios_open'         => $folios_open,
            'payments_today'      => $payments_today,
            'night_audit_runs'    => $night_audit_runs,
            'reports_ready'       => $reports_ready,
            'pos_open_orders'     => $pos_open_orders,
            'settings_users'      => $settings_users,
        ];

        // keep org in vars so the headline badge shows org name
        $vars = [
            'title'   => 'HotelFlow — Dashboard',
            'metrics' => $metrics,
            'org'     => $c['org'] ?? [],
            'ctx'     => $c,
        ];

        $this->renderStandaloneFromModuleDir((string)($c['module_dir'] ?? dirname(__DIR__,2)), 'dashboard/index_standalone.php', $vars);
    }

    /**
     * Require a view file directly from module dir (no shell/sidenav).
     */
    private function renderStandaloneFromModuleDir(string $moduleDir, string $relativeViewPath, array $vars = []): void
    {
        $base = rtrim($moduleDir, '/');
        $file = $base . '/Views/' . ltrim($relativeViewPath, '/');
        if (!is_file($file)) {
            // fallbacks just in case of different build paths
            $alt1 = dirname(__DIR__, 2) . '/Views/' . ltrim($relativeViewPath, '/');
            $alt2 = dirname(__DIR__, 1) . '/Views/' . ltrim($relativeViewPath, '/');
            if (is_file($alt1)) $file = $alt1; elseif (is_file($alt2)) $file = $alt2;
        }
        if (!is_file($file)) {
            if (!headers_sent()) header('Content-Type:text/plain; charset=utf-8', true, 500);
            echo "View not found: {$file}";
            return;
        }
        extract($vars, EXTR_SKIP);
        if (!headers_sent()) header('Content-Type:text/html; charset=utf-8');
        require $file;
    }
}