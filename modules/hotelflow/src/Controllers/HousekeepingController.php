<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class HousekeepingController extends BaseController
{
    /* -------------------------------------------------------------
     * Small schema helpers (like RoomsController)
     * ----------------------------------------------------------- */
    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare("
                SELECT 1
                  FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :t
                 LIMIT 1
            ");
            $st->execute([':t' => $table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function colExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $st = $pdo->prepare("
                SELECT 1
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :t
                   AND COLUMN_NAME  = :c
                 LIMIT 1
            ");
            $st->execute([':t' => $table, ':c' => $column]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /* =============================================================
     * 1) Main board: GET /housekeeping
     * =========================================================== */
    public function index(array $ctx): void
    {
        $c   = $this->ctx($ctx);
        $pdo = $this->pdo();

        // org resolve (same style as other controllers)
        $orgId = (int)($c['org_id'] ?? 0);
        if ($orgId <= 0) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $org   = $_SESSION['tenant_org'] ?? null;
            $orgId = (int)($org['id'] ?? 0);
        }

        $rooms = [];
        $summary = [
            'dirty'          => 0,
            'in_progress'    => 0,
            'clean'          => 0,
            'inspected'      => 0,
            'out_of_service' => 0,
        ];

        if ($orgId > 0 && $this->tableExists($pdo, 'hms_rooms')) {
            $cols = ['id', 'org_id'];

            $hasRoomNo     = $this->colExists($pdo, 'hms_rooms', 'room_no');
            $hasFloor      = $this->colExists($pdo, 'hms_rooms', 'floor');
            $hasRtId       = $this->colExists($pdo, 'hms_rooms', 'room_type_id');
            $hasHk         = $this->colExists($pdo, 'hms_rooms', 'hk_status');
            $hasRoomStatus = $this->colExists($pdo, 'hms_rooms', 'room_status');
            $hasNotes      = $this->colExists($pdo, 'hms_rooms', 'notes');

            if ($hasRoomNo)     $cols[] = 'room_no';
            if ($hasFloor)      $cols[] = 'floor';
            if ($hasRtId)       $cols[] = 'room_type_id';
            if ($hasHk)         $cols[] = 'hk_status';
            if ($hasRoomStatus) $cols[] = 'room_status';
            if ($hasNotes)      $cols[] = 'notes';

            $joinRt   = $this->tableExists($pdo, 'hms_room_types') && $hasRtId;
            $rtName   = false;
            if ($joinRt && $this->colExists($pdo, 'hms_room_types', 'name')) {
                $rtName = true;
            }

            $sql = "SELECT ".implode(',', $cols);
            if ($rtName) {
                $sql .= ", rt.name AS room_type";
            }
            $sql .= " FROM hms_rooms r";
            if ($rtName) {
                $sql .= " LEFT JOIN hms_room_types rt
                             ON rt.id = r.room_type_id
                            AND rt.org_id = r.org_id";
            }
            $sql .= " WHERE r.org_id = :o
                      ORDER BY ".($hasRoomNo ? 'r.room_no' : 'r.id')." ASC
                      LIMIT 300";

            try {
                $st = $pdo->prepare($sql);
                $st->execute([':o' => $orgId]);
                $raw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($raw as $row) {
                    // normalize / derive fields expected by the view
                    $room = [
                        'id'        => (int)($row['id'] ?? 0),
                        'room_no'   => (string)($row['room_no'] ?? ''),
                        'floor'     => (string)($row['floor'] ?? ''),
                        'room_type' => (string)($row['room_type'] ?? ''),
                        'note'      => (string)($row['notes'] ?? ''),
                    ];

                    $hk  = trim((string)($row['hk_status'] ?? ''));
                    $rs  = trim((string)($row['room_status'] ?? ''));

                    // translate raw hk / room status → unified board status
                    $status = 'dirty';

                    if ($hk !== '') {
                        $lk = strtolower($hk);
                        if (in_array($lk, ['clean', 'cleaned'], true)) {
                            $status = 'clean';
                        } elseif (in_array($lk, ['inspected', 'ready'], true)) {
                            $status = 'inspected';
                        } elseif (in_array($lk, ['in_progress', 'cleaning'], true)) {
                            $status = 'in_progress';
                        } else {
                            $status = 'dirty';
                        }
                    } elseif ($rs !== '') {
                        $lr = strtolower($rs);
                        if (in_array($lr, ['ooo', 'oos', 'out_of_order', 'out_of_service'], true)) {
                            $status = 'out_of_service';
                        }
                    }

                    // Default priority based on status
                    $priority = 'normal';
                    if ($status === 'dirty') {
                        $priority = 'high';
                    } elseif ($status === 'in_progress') {
                        $priority = 'medium';
                    } elseif (\in_array($status, ['clean', 'inspected'], true)) {
                        $priority = 'low';
                    }

                    $room['status']    = $status;
                    $room['priority']  = $priority;
                    $room['attendant'] = '';      // later can come from hk assignments
                    $room['shift']     = '';      // later from assignments

                    if (isset($summary[$status])) {
                        $summary[$status]++;
                    }

                    $rooms[] = $room;
                }
            } catch (Throwable $e) {
                // on error, keep rooms empty, summary stays 0
                $rooms = [];
            }
        }

        $this->view('housekeeping/index', [
            'title'       => 'Housekeeping board',
            'rooms'       => $rooms,
            'summary'     => $summary,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }

    /* =============================================================
     * 2) Tasks board: GET /housekeeping/tasks
     *    (2035 UI – backend can be wired later)
     * =========================================================== */
    public function tasks(array $ctx): void
    {
        $c   = $this->ctx($ctx);

        // For now, no heavy DB; later we can pull from hms_hk_tasks
        $tasks = [];   // keep empty → UI will show nice empty-state

        $this->view('housekeeping/tasks', [
            'title'       => 'Housekeeping tasks',
            'tasks'       => $tasks,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }

    /* =============================================================
     * 3) Staff view: GET /housekeeping/staff
     * =========================================================== */
    public function staff(array $ctx): void
    {
        $c   = $this->ctx($ctx);
        $staff = [];   // placeholder; can wire hms_hk_staff later

        $this->view('housekeeping/staff', [
            'title'       => 'Housekeeping staff',
            'staff'       => $staff,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }

    /* =============================================================
     * 4) History view: GET /housekeeping/history
     * =========================================================== */
    public function history(array $ctx): void
    {
        $c    = $this->ctx($ctx);
        $logs = [];   // later: hms_hk_history or similar

        $this->view('housekeeping/history', [
            'title'       => 'Housekeeping history',
            'logs'        => $logs,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }
}