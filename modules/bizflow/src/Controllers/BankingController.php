<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow — Banking
 *
 * - Frontend-first controller for bank accounts + basic activity
 * - Safe even before tables exist (everything is guarded by hasTable()).
 *
 * Expected tables later (optional, not required right now):
 *   - biz_bank_accounts
 *   - biz_bank_transactions
 */
final class BankingController extends BaseController
{
    /* ============================================================
     * 1) Index — list bank accounts
     *    GET /apps/bizflow/banking
     *    GET /t/{slug}/apps/bizflow/banking
     * ========================================================== */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $search   = trim((string)($_GET['q'] ?? ''));
            $type     = trim((string)($_GET['type'] ?? '')); // bank / cash / mobile, etc.
            $currency = trim((string)($_GET['currency'] ?? ''));

            $storageReady = $this->hasTable($pdo, 'biz_bank_accounts');
            $accounts     = [];

            if ($storageReady) {
                $sql = "SELECT * FROM biz_bank_accounts WHERE org_id = ?";
                $bind = [$orgId];

                if ($search !== '') {
                    $sql .= " AND (name LIKE ? OR code LIKE ? OR account_no LIKE ?)";
                    $like   = '%'.$search.'%';
                    $bind[] = $like;
                    $bind[] = $like;
                    $bind[] = $like;
                }

                if ($type !== '') {
                    $sql .= " AND type = ?";
                    $bind[] = $type;
                }

                if ($currency !== '') {
                    $sql .= " AND currency = ?";
                    $bind[] = $currency;
                }

                $sql .= " ORDER BY name ASC, id ASC";

                $accounts = $this->rows($sql, $bind);
            }

            // Very small metrics for header cards (all soft)
            $metrics = [
                'total'     => count($accounts),
                'bank'      => 0,
                'cash'      => 0,
                'mobile'    => 0,
                'storage_ready' => $storageReady,
            ];

            foreach ($accounts as $a) {
                $t = strtolower((string)($a['type'] ?? ''));
                if ($t === 'cash') {
                    $metrics['cash']++;
                } elseif (in_array($t, ['wallet', 'mobile'], true)) {
                    $metrics['mobile']++;
                } else {
                    $metrics['bank']++;
                }
            }

            $this->view('banking/index', [
                'title'        => 'Banking',
                'org'          => $c['org'] ?? [],
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'accounts'     => $accounts,
                'metrics'      => $metrics,
                'search'       => $search,
                'filter_type'  => $type,
                'filter_curr'  => $currency,
                'storage_ready'=> $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Banking index failed', $e);
        }
    }

    /* ============================================================
     * 2) Create — blank form
     *    GET /apps/bizflow/banking/create
     * ========================================================== */
    public function create(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $this->requireOrg(); // only to enforce tenant session

            $today = (new \DateTimeImmutable('now'))->format('Y-m-d');

            $this->view('banking/create', [
                'title'       => 'New bank account',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'account'     => [],       // so view never sees undefined variable
                'mode'        => 'create',
                'today'       => $today,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Banking create failed', $e);
        }
    }

    /* ============================================================
     * 3) Show — account + recent transactions
     *    GET /apps/bizflow/banking/{id}
     * ========================================================== */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $hasAccounts     = $this->hasTable($pdo, 'biz_bank_accounts');
            $hasTransactions = $this->hasTable($pdo, 'biz_bank_transactions');

            if (!$hasAccounts) {
                http_response_code(500);
                echo 'Banking storage not ready yet (biz_bank_accounts table missing).';
                return;
            }

            $account = $this->row(
                "SELECT * FROM biz_bank_accounts WHERE org_id = ? AND id = ? LIMIT 1",
                [$orgId, $id]
            );

            if (!$account) {
                http_response_code(404);
                echo 'Bank account not found.';
                return;
            }

            $transactions = [];
            if ($hasTransactions) {
                $transactions = $this->rows(
                    "SELECT *
                       FROM biz_bank_transactions
                      WHERE org_id      = ?
                        AND account_id  = ?
                      ORDER BY date DESC, id DESC
                      LIMIT 200",
                    [$orgId, $id]
                );
            }

            $this->view('banking/show', [
                'title'        => 'Bank account — '.($account['name'] ?? 'Detail'),
                'org'          => $c['org'] ?? [],
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'account'      => $account,
                'transactions' => $transactions,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Banking show failed', $e);
        }
    }

    /* ============================================================
     * 4) Edit — reuse create view
     *    GET /apps/bizflow/banking/{id}/edit
     * ========================================================== */
    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_bank_accounts')) {
                http_response_code(500);
                echo 'Banking storage not ready yet (biz_bank_accounts table missing).';
                return;
            }

            $account = $this->row(
                "SELECT * FROM biz_bank_accounts WHERE org_id = ? AND id = ? LIMIT 1",
                [$orgId, $id]
            );

            if (!$account) {
                http_response_code(404);
                echo 'Bank account not found.';
                return;
            }

            $today = (new \DateTimeImmutable('now'))->format('Y-m-d');

            $this->view('banking/create', [
                'title'       => 'Edit bank account',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'account'     => $account,
                'mode'        => 'edit',
                'today'       => $today,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Banking edit failed', $e);
        }
    }

    /* ============================================================
     * 5) Store — POST /banking  (stub for now, safe 501)
     * ========================================================== */
    public function store(?array $ctx = null): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'POST only';
            return;
        }

        try {
            // We keep this as a stub so routes don’t 404 / fatal
            http_response_code(501);
            echo 'Bank account create (store) is not implemented yet. Once schema is final we will wire inserts here.';
        } catch (Throwable $e) {
            $this->oops('Banking store failed', $e);
        }
    }

    /* ============================================================
     * 6) Update — POST /banking/{id} (stub for now)
     * ========================================================== */
    public function update(?array $ctx, int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'POST only';
            return;
        }

        try {
            http_response_code(501);
            echo 'Bank account update is not implemented yet. Once schema is final we will wire updates here.';
        } catch (Throwable $e) {
            $this->oops('Banking update failed', $e);
        }
    }

    /* ============================================================
     * Local helper — hasTable (avoid depending on BaseController)
     * ========================================================== */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}