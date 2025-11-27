<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class NightAuditController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
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

    private function resolveOrgId(array $ctx): int
    {
        $orgId = (int)($ctx['org_id'] ?? 0);
        if ($orgId > 0) {
            return $orgId;
        }

        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $org   = $_SESSION['tenant_org'] ?? null;
        return (int)($org['id'] ?? 0);
    }

    /* -------------------------------------------------------------
     * GET /night-audit  → Tonight desk
     * ----------------------------------------------------------- */
    public function index(array $ctx): void
    {
        $c      = $this->ctx($ctx);
        $pdo    = $this->pdo();
        $orgId  = $this->resolveOrgId($c);
        $today  = date('Y-m-d');

        $metrics = [
            'arrivals'      => 0,
            'departures'    => 0,
            'inhouse'       => 0,
            'openFolios'    => 0,
            'folioBalance'  => 0.0,
            'dirtyRooms'    => 0,
            'warnings'      => [],
        ];

        if ($orgId > 0) {
            /* -------- Reservations snapshot (arrivals / departures / in-house) -------- */
            if ($this->tableExists($pdo, 'hms_reservations')) {
                try {
                    // today’s arrivals
                    $st = $pdo->prepare("
                        SELECT
                            SUM(CASE WHEN check_in  = :d AND status IN ('booked','confirmed','guaranteed','pending_confirmation') THEN 1 ELSE 0 END) AS arrivals,
                            SUM(CASE WHEN check_out = :d AND status IN ('in_house','inhouse','checked_out') THEN 1 ELSE 0 END) AS departures,
                            SUM(CASE WHEN status IN ('in_house','inhouse') THEN 1 ELSE 0 END) AS inhouse
                        FROM hms_reservations
                        WHERE org_id = :o
                    ");
                    $st->execute([':o' => $orgId, ':d' => $today]);
                    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                    $metrics['arrivals']   = (int)($row['arrivals']   ?? 0);
                    $metrics['departures'] = (int)($row['departures'] ?? 0);
                    $metrics['inhouse']    = (int)($row['inhouse']    ?? 0);
                } catch (Throwable $e) {
                    // ignore, keep zeros
                }
            }

            /* -------- Folios snapshot (open & outstanding) -------- */
            if ($this->tableExists($pdo, 'hms_folios')) {
                try {
                    $fs = $pdo->prepare("
                        SELECT
                            SUM(CASE WHEN status IN ('open','in_house','inhouse') THEN 1 ELSE 0 END) AS open_folios,
                            SUM(CASE WHEN status IN ('open','in_house','inhouse') THEN balance_due ELSE 0 END) AS balance
                        FROM hms_folios
                        WHERE org_id = :o
                    ");
                    $fs->execute([':o' => $orgId]);
                    $frow = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
                    $metrics['openFolios']   = (int)($frow['open_folios'] ?? 0);
                    $metrics['folioBalance'] = (float)($frow['balance'] ?? 0.0);
                } catch (Throwable $e) {
                    // ignore, keep zeros
                }
            }

            /* -------- Housekeeping snapshot (dirty rooms) -------- */
            // Very defensive: we don't know exact schema; try hms_rooms.status or hms_rooms.housekeeping_status
            if ($this->tableExists($pdo, 'hms_rooms')) {
                try {
                    $rs = $pdo->prepare("
                        SELECT
                            SUM(
                                CASE
                                    WHEN (status = 'dirty'
                                       OR housekeeping_status = 'dirty'
                                       OR status = 'soiled') THEN 1
                                    ELSE 0
                                END
                            ) AS dirty_rooms
                        FROM hms_rooms
                        WHERE org_id = :o
                    ");
                    $rs->execute([':o' => $orgId]);
                    $rrow = $rs->fetch(PDO::FETCH_ASSOC) ?: [];
                    $metrics['dirtyRooms'] = (int)($rrow['dirty_rooms'] ?? 0);
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }

        /* -------- Build warnings based on metrics -------- */
        $warnings = [];
        if ($metrics['openFolios'] > 0 && $metrics['folioBalance'] > 0.0) {
            $warnings[] = 'There are open folios with outstanding balance — review before closing the day.';
        }
        if ($metrics['dirtyRooms'] > 0) {
            $warnings[] = 'Some rooms are still marked dirty — coordinate with housekeeping.';
        }
        if ($metrics['departures'] > 0 && $metrics['openFolios'] > 0) {
            $warnings[] = 'Guests departing today still have open folios — double-check before check-out.';
        }
        if (!$warnings) {
            $warnings[] = 'No critical issues detected. You can proceed with night audit once totals are verified.';
        }
        $metrics['warnings'] = $warnings;

        $this->view('night-audit/index', [
            'title'       => 'Night audit',
            'today'       => $today,
            'metrics'     => $metrics,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }

    /* -------------------------------------------------------------
     * GET /night-audit/history → previous days (stub)
     * ----------------------------------------------------------- */
    public function history(array $ctx): void
    {
        $c      = $this->ctx($ctx);
        $pdo    = $this->pdo();
        $orgId  = $this->resolveOrgId($c);

        $runs = [];

        if ($orgId > 0 && $this->tableExists($pdo, 'hms_night_audit_runs')) {
            try {
                $st = $pdo->prepare("
                    SELECT *
                      FROM hms_night_audit_runs
                     WHERE org_id = :o
                     ORDER BY run_date DESC, id DESC
                     LIMIT 30
                ");
                $st->execute([':o' => $orgId]);
                $runs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $runs = [];
            }
        }

        $this->view('night-audit/history', [
            'title'       => 'Night audit history',
            'runs'        => $runs,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }
}