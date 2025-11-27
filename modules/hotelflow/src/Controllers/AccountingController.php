<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class AccountingController extends BaseController
{
    /* -------------------------------------------------------------
     * GET /accounting — main dashboard
     * ----------------------------------------------------------- */
    public function index(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)($c['org_id'] ?? 0);

        if ($orgId <= 0) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $org   = $_SESSION['tenant_org'] ?? null;
            $orgId = (int)($org['id'] ?? 0);
        }

        if ($orgId <= 0) {
            http_response_code(400);
            echo 'Organisation context missing for accounting dashboard.';
            return;
        }

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        // Default metrics
        $metrics = [
            'today_revenue'     => 0.0,
            'month_revenue'     => 0.0,
            'open_folios'       => 0,
            'overdue_balance'   => 0.0,
            'payments_today'    => 0.0,
            'refunds_today'     => 0.0,
        ];

        $recent = [
            'payments' => [],
            'folios'   => [],
        ];

        try {
            // ---- Revenue (from folios, if available) ----
            if ($this->tableExists($pdo, 'hms_folios')) {
                // today revenue (closed folios today OR charges posted today)
                $st = $pdo->prepare("
                    SELECT COALESCE(SUM(total_amount),0) AS total
                    FROM hms_folios
                    WHERE org_id = :o
                      AND DATE(updated_at) = :d
                ");
                $st->execute([':o' => $orgId, ':d' => $today]);
                $metrics['today_revenue'] = (float)($st->fetchColumn() ?: 0);

                // month revenue
                $st = $pdo->prepare("
                    SELECT COALESCE(SUM(total_amount),0) AS total
                    FROM hms_folios
                    WHERE org_id = :o
                      AND DATE(updated_at) BETWEEN :m AND :d
                ");
                $st->execute([':o' => $orgId, ':m' => $monthStart, ':d' => $today]);
                $metrics['month_revenue'] = (float)($st->fetchColumn() ?: 0);

                // open folios + overdue balance
                $st = $pdo->prepare("
                    SELECT
                        COUNT(*) AS open_count,
                        COALESCE(SUM(balance_due),0) AS overdue
                    FROM hms_folios
                    WHERE org_id = :o
                      AND status IN ('open','in_house','pending')
                ");
                $st->execute([':o' => $orgId]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['open_count' => 0, 'overdue' => 0];
                $metrics['open_folios']     = (int)($row['open_count'] ?? 0);
                $metrics['overdue_balance'] = (float)($row['overdue'] ?? 0);

                // recent folios list (latest 8)
                $st = $pdo->prepare("
                    SELECT id, folio_no, guest_name, room_no, balance_due, status, updated_at
                    FROM hms_folios
                    WHERE org_id = :o
                    ORDER BY updated_at DESC
                    LIMIT 8
                ");
                $st->execute([':o' => $orgId]);
                $recent['folios'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            // ---- Payments (if table exists) ----
            if ($this->tableExists($pdo, 'hms_payments')) {
                // payments/ refunds today
                $st = $pdo->prepare("
                    SELECT
                        COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END),0) AS paid,
                        COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END),0) AS refunded
                    FROM hms_payments
                    WHERE org_id = :o
                      AND DATE(paid_at) = :d
                ");
                $st->execute([':o' => $orgId, ':d' => $today]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['paid' => 0, 'refunded' => 0];
                $metrics['payments_today'] = (float)($row['paid'] ?? 0);
                $metrics['refunds_today']  = (float)abs((float)($row['refunded'] ?? 0));

                // recent payments list
                $st = $pdo->prepare("
                    SELECT id, folio_id, method, amount, currency, paid_at, reference
                    FROM hms_payments
                    WHERE org_id = :o
                    ORDER BY paid_at DESC
                    LIMIT 8
                ");
                $st->execute([':o' => $orgId]);
                $recent['payments'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Throwable $e) {
            // Soft-fail: show empty metrics instead of error page
        }

        $this->view('accounting/index', [
            'title'       => 'Accounting',
            'metrics'     => $metrics,
            'recent'      => $recent,
            'today'       => $today,
            'monthStart'  => $monthStart,
        ], $c);
    }

    /* -------------------------------------------------------------
     * Helpers: schema-safe checks
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
}