<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow PaymentsController
 * - Frontend-first 2035 UI for:
 *   - Index list
 *   - Create form
 *   - Detail (show)
 * - All DB usage is guarded by hasTable() so it is safe BEFORE schema exists.
 */
final class PaymentsController extends BaseController
{
    /* ============================================================
     * Index — /apps/bizflow/payments
     * ========================================================== */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $search       = trim((string)($_GET['q']      ?? ''));
            $filterMethod = trim((string)($_GET['method'] ?? ''));
            $dateFrom     = trim((string)($_GET['from']   ?? ''));
            $dateTo       = trim((string)($_GET['to']     ?? ''));

            $storageReady = $this->hasTable($pdo, 'biz_payments');
            $payments     = [];

            if ($storageReady) {
                $sql = "
                    SELECT
                        p.*,
                        c.name AS customer_name,
                        c.code AS customer_code
                    FROM biz_payments p
                    LEFT JOIN biz_customers c
                           ON c.id = p.customer_id
                          AND c.org_id = p.org_id
                    WHERE p.org_id = ?
                ";

                $params = [$orgId];

                if ($search !== '') {
                    $sql .= " AND (
                                  c.name       LIKE ?
                               OR c.code       LIKE ?
                               OR p.reference  LIKE ?
                               OR p.notes      LIKE ?
                             )";
                    $like = '%'.$search.'%';
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }

                if ($filterMethod !== '') {
                    $sql .= " AND p.method = ?";
                    $params[] = $filterMethod;
                }

                if ($dateFrom !== '') {
                    $sql .= " AND p.date >= ?";
                    $params[] = $dateFrom;
                }

                if ($dateTo !== '') {
                    $sql .= " AND p.date <= ?";
                    $params[] = $dateTo;
                }

                $sql .= " ORDER BY p.date DESC, p.id DESC LIMIT 300";

                $payments = $this->rows($sql, $params);
            }

            $this->view('payments/index', [
                'title'         => 'Payments',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'payments'      => $payments,
                'search'        => $search,
                'filter_method' => $filterMethod,
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'storage_ready' => $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Payments index failed', $e);
        }
    }

    /* ============================================================
     * Create — GET /payments/create
     * ========================================================== */
    public function create(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            // Optional compact customer list (even before schema we guard it)
            $customers = [];
            if ($this->hasTable($pdo, 'biz_customers')) {
                $customers = $this->rows(
                    "SELECT id, name, code
                       FROM biz_customers
                      WHERE org_id = ?
                      ORDER BY name
                      LIMIT 200",
                    [$orgId]
                );
            }

            $today = (new \DateTimeImmutable('now'))->format('Y-m-d');

            $this->view('payments/create', [
                'title'       => 'Record payment',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'customers'   => $customers,
                'today'       => $today,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Payments create failed', $e);
        }
    }

    /* ============================================================
     * Show — GET /payments/{id}
     *      (Safe even before tables exist)
     * ========================================================== */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $hasPayments    = $this->hasTable($pdo, 'biz_payments');
            $hasAllocations = $this->hasTable($pdo, 'biz_payment_allocations');

            $storageReady = $hasPayments;

            $payment     = null;
            $allocations = [];

            if ($hasPayments) {
                // Try real data
                $payment = $this->row(
                    "SELECT
                         p.*,
                         c.name AS customer_name,
                         c.code AS customer_code
                     FROM biz_payments p
                LEFT JOIN biz_customers c
                       ON c.id = p.customer_id
                      AND c.org_id = p.org_id
                    WHERE p.org_id = ?
                      AND p.id     = ?
                    LIMIT 1",
                    [$orgId, $id]
                );
            }

            // If storage not ready OR record not found -> soft placeholder
            if (!$payment) {
                $payment = [
                    'id'            => $id,
                    'date'          => (new \DateTimeImmutable('now'))->format('Y-m-d'),
                    'amount'        => 0,
                    'currency'      => 'BDT',
                    'method'        => 'cash',
                    'reference'     => 'DEMO-'.$id,
                    'notes'         => 'Payments storage not ready yet — this is a preview shell.',
                    'customer_id'   => null,
                    'customer_name' => 'Demo customer',
                    'customer_code' => null,
                ];
            }

            if ($hasAllocations) {
                $allocations = $this->rows(
                    "SELECT pa.*, i.invoice_no, i.date AS invoice_date
                       FROM biz_payment_allocations pa
                  LEFT JOIN biz_invoices i
                         ON i.id = pa.invoice_id
                        AND i.org_id = pa.org_id
                      WHERE pa.org_id    = ?
                        AND pa.payment_id = ?
                      ORDER BY pa.id",
                    [$orgId, $id]
                );
            }

            $this->view('payments/show', [
                'title'         => 'Payment detail',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'payment'       => $payment,
                'allocations'   => $allocations,
                'storage_ready' => $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Payment view failed', $e);
        }
    }

    /* ============================================================
     * Store / Update — to be wired AFTER schema & posting engine
     * ========================================================== */
    public function store(?array $ctx = null): void
    {
        http_response_code(501);
        echo 'Payments store() is not implemented yet. We will wire this after BizFlow payments schema is final.';
    }

    public function update(?array $ctx, int $id): void
    {
        http_response_code(501);
        echo 'Payments update() is not implemented yet. We will wire this after BizFlow payments schema is final.';
    }

    /* ============================================================
     * Local helper: check if a table exists
     * ========================================================== */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}