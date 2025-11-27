<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use DateTimeImmutable;
use Throwable;

final class FrontdeskController extends BaseController
{
    /* ============================================================
     * 1) ARRIVALS  (/frontdesk, /frontdesk/arrivals)
     *    → NEW FRONTDESK DASHBOARD (frontdesk/index.php)
     * ========================================================== */
    public function arrivals(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $date = $this->parseYmdOrToday(trim((string)($_GET['date'] ?? '')));

        $rows = [];
        $tot  = 0;

        // ---------- org guard ----------
        if ($orgId <= 0) {
            $this->safeView('frontdesk/index', [
                'title' => 'Front Desk — HotelFlow',
                'date'  => $date,
                'rows'  => [],
                'total' => 0,
                'tab'   => 'arrivals',
                'note'  => 'Organization context not resolved.',
            ], $c);
            return;
        }

        // ---------- table guard ----------
        if (!$this->tableExists($pdo, 'hms_reservations')) {
            $this->safeView('frontdesk/index', [
                'title' => 'Front Desk — HotelFlow',
                'date'  => $date,
                'rows'  => [],
                'total' => 0,
                'tab'   => 'arrivals',
                'note'  => 'Table hms_reservations not found.',
            ], $c);
            return;
        }

        // ---------- main arrivals list ----------
        try {
            $sql = "
                SELECT
                    r.id,
                    r.code,
                    COALESCE(r.guest_name, 'Guest') AS guest_name,
                    r.check_in,
                    r.check_out,
                    COALESCE(r.room_no, '')        AS room_no,
                    COALESCE(r.room_type_name, '') AS room_type_name,
                    r.status,
                    COALESCE(r.channel, 'Direct')  AS channel
                FROM hms_reservations r
                WHERE r.org_id = :o
                  AND DATE(r.check_in) = :d
                  AND r.status IN ('booked','confirmed','guaranteed')
                ORDER BY r.check_in ASC, r.id ASC
                LIMIT 200
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':o' => $orgId, ':d' => $date]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st2 = $pdo->prepare("
                SELECT COUNT(*)
                FROM hms_reservations
                WHERE org_id = :o
                  AND DATE(check_in) = :d
                  AND status IN ('booked','confirmed','guaranteed')
            ");
            $st2->execute([':o' => $orgId, ':d' => $date]);
            $tot = (int)$st2->fetchColumn();
        } catch (Throwable $e) {
            $rows = [];
            $tot  = 0;
        }

        // ---------- render NEW DASHBOARD INDEX ----------
        $this->safeView('frontdesk/index', [
            'title' => 'Front Desk — HotelFlow',
            'date'  => $date,
            'rows'  => $rows,
            'total' => $tot,
            'tab'   => 'arrivals',
        ], $c);
    }

    /* ============================================================
     * 2) IN-HOUSE (/frontdesk/inhouse)
     * ========================================================== */
    public function inhouse(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $date = $this->parseYmdOrToday(trim((string)($_GET['date'] ?? '')));

        if ($orgId <= 0) {
            $this->safeView('frontdesk/inhouse', [
                'title' => 'In-house — HotelFlow',
                'date'  => $date,
                'rows'  => [],
                'total' => 0,
                'tab'   => 'inhouse',
                'note'  => 'Organization context not resolved.',
            ], $c);
            return;
        }

        if (!$this->tableExists($pdo, 'hms_stays')) {
            $this->safeView('frontdesk/inhouse', [
                'title' => 'In-house — HotelFlow',
                'date'  => $date,
                'rows'  => [],
                'total' => 0,
                'tab'   => 'inhouse',
                'note'  => 'Table hms_stays not found.',
            ], $c);
            return;
        }

        $rows = [];
        $tot  = 0;

        try {
            $sql = "
                SELECT
                    s.id,
                    s.reservation_id,
                    COALESCE(s.room_no, '') AS room_no,
                    s.check_in_at,
                    s.planned_check_out,
                    s.actual_check_out,
                    s.status,
                    COALESCE(g.full_name, r.guest_name, 'Guest') AS guest_name,
                    COALESCE(r.room_type_name, '') AS room_type_name
                FROM hms_stays s
                LEFT JOIN hms_guests g
                       ON g.id = s.guest_id AND g.org_id = s.org_id
                LEFT JOIN hms_reservations r
                       ON r.id = s.reservation_id AND r.org_id = s.org_id
                WHERE s.org_id = :o
                  AND DATE(s.check_in_at) <= :d
                  AND DATE(COALESCE(s.actual_check_out, s.planned_check_out)) >= :d
                ORDER BY s.check_in_at DESC, s.id DESC
                LIMIT 200
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':o' => $orgId, ':d' => $date]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st2 = $pdo->prepare("
                SELECT COUNT(*)
                FROM hms_stays
                WHERE org_id = :o
                  AND DATE(check_in_at) <= :d
                  AND DATE(COALESCE(actual_check_out, planned_check_out)) >= :d
            ");
            $st2->execute([':o' => $orgId, ':d' => $date]);
            $tot = (int)$st2->fetchColumn();
        } catch (Throwable $e) {
            $rows = [];
            $tot  = 0;
        }

        $this->safeView('frontdesk/inhouse', [
            'title' => 'In-house — HotelFlow',
            'date'  => $date,
            'rows'  => $rows,
            'total' => $tot,
            'tab'   => 'inhouse',
        ], $c);
    }

    /* ============================================================
     * 3) DEPARTURES (/frontdesk/departures)
     * ========================================================== */
    public function departures(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $date = $this->parseYmdOrToday(trim((string)($_GET['date'] ?? '')));

        if ($orgId <= 0) {
            $this->safeView('frontdesk/departures', [
                'title' => 'Departures — HotelFlow',
                'date'  => $date,
                'rows'  => [],
                'total' => 0,
                'tab'   => 'departures',
                'note'  => 'Organization context not resolved.',
            ], $c);
            return;
        }

        if (!$this->tableExists($pdo, 'hms_reservations')) {
            $this->safeView('frontdesk/departures', [
                'title' => 'Departures — HotelFlow',
                'date'  => $date,
                'rows'  => [],
                'total' => 0,
                'tab'   => 'departures',
                'note'  => 'Table hms_reservations not found.',
            ], $c);
            return;
        }

        $rows = [];
        $tot  = 0;

        try {
            $sql = "
                SELECT
                    r.id,
                    r.code,
                    COALESCE(g.full_name, r.guest_name, 'Guest') AS guest_name,
                    r.check_in,
                    r.check_out,
                    COALESCE(r.room_no, '')        AS room_no,
                    COALESCE(r.room_type_name, '') AS room_type_name,
                    r.status
                FROM hms_reservations r
                LEFT JOIN hms_guests g
                       ON g.id = r.guest_id AND g.org_id = r.org_id
                WHERE r.org_id = :o
                  AND DATE(r.check_out) = :d
                  AND r.status IN ('in_house','checked_in','confirmed','guaranteed')
                ORDER BY r.check_out ASC, r.id ASC
                LIMIT 200
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':o' => $orgId, ':d' => $date]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st2 = $pdo->prepare("
                SELECT COUNT(*)
                FROM hms_reservations
                WHERE org_id = :o
                  AND DATE(check_out) = :d
                  AND status IN ('in_house','checked_in','confirmed','guaranteed')
            ");
            $st2->execute([':o' => $orgId, ':d' => $date]);
            $tot = (int)$st2->fetchColumn();
        } catch (Throwable $e) {
            $rows = [];
            $tot  = 0;
        }

        $this->safeView('frontdesk/departures', [
            'title' => 'Departures — HotelFlow',
            'date'  => $date,
            'rows'  => $rows,
            'total' => $tot,
            'tab'   => 'departures',
        ], $c);
    }

    /* ============================================================
     * 4) ROOM STATUS (/frontdesk/room-status)
     * ========================================================== */
    public function roomStatus(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $status = trim((string)($_GET['status'] ?? ''));
        $rtId   = (int)($_GET['room_type_id'] ?? 0);
        $floor  = strlen((string)($_GET['floor'] ?? '')) ? (int)$_GET['floor'] : null;
        $search = trim((string)($_GET['q'] ?? ''));

        $rooms     = [];
        $roomTypes = [];
        $floors    = [];
        $note      = null;

        if ($orgId <= 0) {
            $this->safeView('frontdesk/room-status', [
                'title'     => 'Room Status — HotelFlow',
                'tab'       => 'room-status',
                'rooms'     => [],
                'roomTypes' => [],
                'floors'    => [],
                'filters'   => [
                    'status'       => $status,
                    'room_type_id' => $rtId,
                    'floor'        => $floor,
                    'q'            => $search,
                ],
                'note'      => 'Organization context not resolved.',
            ], $c);
            return;
        }

        // Lookups
        if ($this->tableExists($pdo, 'hms_room_types')) {
            try {
                $st = $pdo->prepare("
                    SELECT id, name
                    FROM hms_room_types
                    WHERE org_id = :o
                    ORDER BY name
                ");
                $st->execute([':o' => $orgId]);
                $roomTypes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {}
        }

        if ($this->tableExists($pdo, 'hms_rooms')) {
            try {
                $st = $pdo->prepare("
                    SELECT DISTINCT floor_no
                    FROM hms_rooms
                    WHERE org_id = :o AND floor_no IS NOT NULL
                    ORDER BY floor_no
                ");
                $st->execute([':o' => $orgId]);
                $floors = array_values(array_filter(
                    array_map(
                        fn($r) => $r['floor_no'] ?? null,
                        $st->fetchAll(PDO::FETCH_ASSOC) ?: []
                    ),
                    fn($v) => $v !== null
                ));
            } catch (Throwable $e) {}
        }

        if (!$this->tableExists($pdo, 'hms_rooms')) {
            $note = 'Table hms_rooms not found.';
        } else {
            $hasRt = $this->tableExists($pdo, 'hms_room_types');
            $hasHk = $this->tableExists($pdo, 'hms_housekeeping_status');
            $hasSt = $this->tableExists($pdo, 'hms_stays');
            $hasG  = $this->tableExists($pdo, 'hms_guests');

            $sql = "
                SELECT
                    r.id,
                    COALESCE(r.room_no, r.number, CONCAT('R', r.id)) AS room_no,
                    r.floor_no,
                    " . ($hasRt
                        ? "COALESCE(rt.name, r.room_type, r.room_type_name, '')"
                        : "COALESCE(r.room_type, r.room_type_name, '')"
                    ) . " AS room_type,
                    COALESCE(r.status, 'vacant') AS room_status" .
                    ($hasHk
                        ? ", COALESCE(hk.status, 'unknown') AS hk_status"
                        : ", 'unknown' AS hk_status") .
                    ($hasSt
                        ? ", st.id AS stay_id, st.reservation_id, st.planned_check_out"
                        : ", NULL AS stay_id, NULL AS reservation_id, NULL AS planned_check_out") .
                    ($hasG
                        ? ", COALESCE(g.full_name, '') AS guest_name"
                        : ", '' AS guest_name") . "
                FROM hms_rooms r
            ";

            if ($hasRt) {
                $sql .= " LEFT JOIN hms_room_types rt ON rt.id = r.room_type_id ";
            }
            if ($hasHk) {
                $sql .= " LEFT JOIN hms_housekeeping_status hk ON hk.room_id = r.id ";
            }
            if ($hasSt) {
                $sql .= " LEFT JOIN hms_stays st
                            ON st.room_id = r.id
                           AND st.status IN ('in_house','checked_in') ";
                if ($hasG) {
                    $sql .= " LEFT JOIN hms_guests g
                                ON g.id = st.guest_id
                               AND g.org_id = st.org_id ";
                }
            }

            $sql  .= " WHERE r.org_id = :o ";
            $bind = [':o' => $orgId];

            if ($status !== '') {
                switch (strtolower($status)) {
                    case 'occupied':
                        $sql .= " AND " . ($hasSt ? "st.id IS NOT NULL" : "1=0") . " ";
                        break;
                    case 'vacant':
                        $sql .= " AND " . ($hasSt ? "st.id IS NULL AND " : "") .
                                "COALESCE(r.status,'vacant') NOT IN ('out_of_order','out_of_service') ";
                        break;
                    case 'ooo':
                        $sql .= " AND COALESCE(r.status,'') = 'out_of_order' ";
                        break;
                    case 'oos':
                        $sql .= " AND COALESCE(r.status,'') = 'out_of_service' ";
                        break;
                    default:
                        $sql .= " AND COALESCE(r.status,'') = :st_any ";
                        $bind[':st_any'] = $status;
                        break;
                }
            }

            if ($rtId > 0) {
                $sql .= " AND r.room_type_id = :rt ";
                $bind[':rt'] = $rtId;
            }

            if ($floor !== null) {
                $sql .= " AND r.floor_no = :fl ";
                $bind[':fl'] = $floor;
            }

            if ($search !== '') {
                $sql .= " AND (r.room_no LIKE :q OR r.number LIKE :q) ";
                $bind[':q'] = '%'.$search.'%';
            }

            $sql .= " ORDER BY r.floor_no ASC, room_no ASC LIMIT 500 ";

            try {
                $st = $pdo->prepare($sql);
                $st->execute($bind);
                $rooms = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $rooms = [];
                $note  = 'Failed to load room list.';
            }
        }

        $this->safeView('frontdesk/room-status', [
            'title'     => 'Room Status — HotelFlow',
            'tab'       => 'room-status',
            'rooms'     => $rooms,
            'roomTypes' => $roomTypes,
            'floors'    => $floors,
            'filters'   => [
                'status'       => $status,
                'room_type_id' => $rtId,
                'floor'        => $floor,
                'q'            => $search,
            ],
            'note'      => $note,
        ], $c);
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    private function parseYmdOrToday(string $s): string
    {
        if ($s !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        return (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $q = $pdo->prepare("
                SELECT 1
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :t
            ");
            $q->execute([':t' => $table]);
            return (bool)$q->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Safe view wrapper: if view missing, show inline card.
     */
    private function safeView(string $rel, array $data, array $c): void
    {
        $moduleDir = isset($c['module_dir']) && is_string($c['module_dir'])
            ? rtrim($c['module_dir'], '/')
            : rtrim((string)realpath(\dirname(__DIR__, 2)), '/');

        $viewPath = $moduleDir . '/Views/' . ltrim($rel, '/') . '.php';

        if (is_file($viewPath)) {
            $this->view($rel, $data, $c);
            return;
        }

        http_response_code(200);

        $title = htmlspecialchars((string)($data['title'] ?? 'HotelFlow'), ENT_QUOTES, 'UTF-8');
        $note  = htmlspecialchars((string)($data['note']  ?? 'This page is not ready yet.'), ENT_QUOTES, 'UTF-8');
        $dash  = isset($c['module_base']) ? (string)$c['module_base'] . '/dashboard' : '/';
        $dashH = htmlspecialchars($dash, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!doctype html><meta charset="utf-8">
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Inter,Arial;background:#f9fafb;color:#0f172a;margin:0;padding:32px}
.card{max-width:880px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 24px;box-shadow:0 4px 14px rgba(0,0,0,.06)}
h1{margin:0 0 8px;font-size:20px}
p{margin:6px 0 0;color:#64748b}
a{display:inline-block;margin-top:14px;background:#228B22;color:#fff;text-decoration:none;padding:8px 12px;border-radius:10px;font-weight:600}
</style>
<div class="card">
  <h1>{$title}</h1>
  <p>{$note}</p>
  <a href="{$dashH}">← Back to Dashboard</a>
</div>
HTML;
    }
}