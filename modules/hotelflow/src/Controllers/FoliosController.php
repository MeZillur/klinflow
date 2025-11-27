<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class FoliosController extends BaseController
{
    /* -------------------------------------------------------------
     * Small helpers (schema-safe, multi-tenant friendly)
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

    /* -------------------------------------------------------------
     * GET /folios   → list folios
     * ----------------------------------------------------------- */
    public function index(array $ctx): void
    {
        $c   = $this->ctx($ctx);
        $pdo = $this->pdo();

        // org resolve (same style as other HotelFlow controllers)
        $orgId = (int)($c['org_id'] ?? 0);
        if ($orgId <= 0) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $org   = $_SESSION['tenant_org'] ?? null;
            $orgId = (int)($org['id'] ?? 0);
        }

        $folios  = [];
        $summary = [
            'open'    => 0,
            'closed'  => 0,
            'balance' => 0.0,
        ];

        if ($orgId > 0 && $this->tableExists($pdo, 'hms_folios')) {
            try {
                // Very defensive: select * so unknown columns don’t break
                $st = $pdo->prepare("
                    SELECT
                        f.*,
                        r.code       AS reservation_code,
                        g.full_name  AS guest_name
                    FROM hms_folios f
                    LEFT JOIN hms_reservations r
                           ON r.id     = f.reservation_id
                          AND r.org_id = f.org_id
                    LEFT JOIN hms_guests g
                           ON g.id     = f.guest_id
                          AND g.org_id = f.org_id
                    WHERE f.org_id = :o
                    ORDER BY f.id DESC
                    LIMIT 200
                ");
                $st->execute([':o' => $orgId]);
                $raw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($raw as $row) {
                    $id   = (int)($row['id'] ?? 0);
                    $code = (string)($row['code'] ?? ($row['folio_code'] ?? ('FOLIO-' . $id)));
                    $status = strtolower((string)($row['status'] ?? 'open'));
                    $balance = (float)($row['balance_due'] ?? 0);

                    if ($status === 'closed') {
                        $summary['closed']++;
                    } else {
                        $summary['open']++;
                    }
                    $summary['balance'] += $balance;

                    $folios[] = [
                        'id'               => $id,
                        'code'             => $code,
                        'status'           => $status,
                        'guest_name'       => (string)($row['guest_name'] ?? ''),
                        'reservation_code' => (string)($row['reservation_code'] ?? ''),
                        'currency'         => (string)($row['currency'] ?? 'BDT'),
                        'balance_due'      => $balance,
                        'opened_at'        => (string)($row['opened_at'] ?? ($row['created_at'] ?? '')),
                        'closed_at'        => (string)($row['closed_at'] ?? ''),
                    ];
                }
            } catch (Throwable $e) {
                // If schema is different, we just fall back to empty list
                $folios  = [];
                $summary = ['open' => 0, 'closed' => 0, 'balance' => 0.0];
            }
        }

        $this->view('folios/index', [
            'title'       => 'Folios & guest bills',
            'folios'      => $folios,
            'summary'     => $summary,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }

    /* -------------------------------------------------------------
     * GET /folios/{id} → single folio
     * ----------------------------------------------------------- */
    public function show(array $ctx, int $id): void
    {
        $c   = $this->ctx($ctx);
        $pdo = $this->pdo();

        $orgId = (int)($c['org_id'] ?? 0);
        if ($orgId <= 0) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $org   = $_SESSION['tenant_org'] ?? null;
            $orgId = (int)($org['id'] ?? 0);
        }

        $folio    = null;
        $lines    = [];
        $payments = [];

        if ($orgId > 0 && $this->tableExists($pdo, 'hms_folios')) {
            try {
                // Main folio + related reservation / guest snapshot
                $fs = $pdo->prepare("
                    SELECT
                        f.*,
                        r.code       AS reservation_code,
                        r.check_in,
                        r.check_out,
                        g.full_name  AS guest_name,
                        g.mobile     AS guest_mobile
                    FROM hms_folios f
                    LEFT JOIN hms_reservations r
                           ON r.id     = f.reservation_id
                          AND r.org_id = f.org_id
                    LEFT JOIN hms_guests g
                           ON g.id     = f.guest_id
                          AND g.org_id = f.org_id
                    WHERE f.org_id = :o
                      AND f.id     = :id
                    LIMIT 1
                ");
                $fs->execute([':o' => $orgId, ':id' => $id]);
                $folio = $fs->fetch(PDO::FETCH_ASSOC) ?: null;

                // Line items (charges/payments) – optional, very defensive
                if ($folio && $this->tableExists($pdo, 'hms_folio_lines')) {
                    try {
                        $ls = $pdo->prepare("
                            SELECT *
                              FROM hms_folio_lines
                             WHERE org_id  = :o
                               AND folio_id = :id
                             ORDER BY line_date, id
                        ");
                        $ls->execute([':o' => $orgId, ':id' => $id]);
                        $lines = $ls->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Throwable $ignored) {
                        $lines = [];
                    }
                }

                // Payments table (if you create it later)
                if ($folio && $this->tableExists($pdo, 'hms_payments')) {
                    try {
                        $ps = $pdo->prepare("
                            SELECT *
                              FROM hms_payments
                             WHERE org_id   = :o
                               AND folio_id = :id
                             ORDER BY paid_at, id
                        ");
                        $ps->execute([':o' => $orgId, ':id' => $id]);
                        $payments = $ps->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Throwable $ignored) {
                        $payments = [];
                    }
                }
            } catch (Throwable $e) {
                $folio    = null;
                $lines    = [];
                $payments = [];
            }
        }

        if (!$folio) {
            $this->notFound('Folio not found for this organisation.');
            return;
        }

        $this->view('folios/show', [
            'title'       => 'Folio ' . ((string)($folio['code'] ?? ('#'.$id))),
            'folio'       => $folio,
            'lines'       => $lines,
            'payments'    => $payments,
            'module_base' => $this->moduleBase($c),
        ], $c);
    }
}