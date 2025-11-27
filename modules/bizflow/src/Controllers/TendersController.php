<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow — Tenders / RFQs / RFPs
 *
 * Routes (see routes.php):
 *   GET  /tenders              → index()
 *   POST /tenders              → store()
 *   GET  /tenders/create       → create()
 *   GET  /tenders/{id}         → show()
 *   GET  /tenders/{id}/edit    → edit()
 *   POST /tenders/{id}         → update()
 *
 * Notes:
 * - This controller assumes a future table `biz_tenders`
 *   with columns matching the fields used below.
 * - If the table does NOT exist yet, index() will simply
 *   show an empty list; store()/update()/show() will emit
 *   a clear error message instead of cryptic SQL.
 */
final class TendersController extends BaseController
{
    /* ============================================================
     * 1) Index — list all tenders / RFQs for org
     * ========================================================== */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $q        = trim((string)($_GET['q']        ?? ''));
            $status   = trim((string)($_GET['status']   ?? ''));
            $type     = trim((string)($_GET['type']     ?? ''));
            $dueFrom  = trim((string)($_GET['due_from'] ?? ''));
            $dueTo    = trim((string)($_GET['due_to']   ?? ''));

            $tenders  = [];

            if ($this->hasTable($pdo, 'biz_tenders')) {
                $sql = "
                    SELECT
                        t.*,
                        c.name AS customer_name,
                        u.name AS owner_name
                    FROM biz_tenders t
                    LEFT JOIN biz_customers c
                           ON c.id = t.customer_id
                          AND c.org_id = t.org_id
                    LEFT JOIN cp_users u
                           ON u.id = t.owner_id
                    WHERE t.org_id = :org_id
                ";
                $params = ['org_id' => $orgId];

                if ($q !== '') {
                    $sql .= " AND (
                        t.code           LIKE :q
                        OR t.title       LIKE :q
                        OR t.subject     LIKE :q
                        OR t.customer_ref LIKE :q
                    )";
                    $params['q'] = '%'.$q.'%';
                }

                if ($status !== '') {
                    $sql .= " AND t.status = :status";
                    $params['status'] = $status;
                }

                if ($type !== '') {
                    $sql .= " AND t.type = :type";
                    $params['type'] = $type;
                }

                if ($dueFrom !== '') {
                    $sql .= " AND t.due_date >= :due_from";
                    $params['due_from'] = $dueFrom;
                }

                if ($dueTo !== '') {
                    $sql .= " AND t.due_date <= :due_to";
                    $params['due_to'] = $dueTo;
                }

                $sql .= "
                    ORDER BY
                        t.due_date IS NULL,
                        t.due_date ASC,
                        t.id DESC
                    LIMIT 500
                ";

                $tenders = $this->rows($sql, $params);
            }

            $this->view('tenders/index', [
                'title'       => 'Tenders & RFQs',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'tenders'     => $tenders,
                'search'      => $q,
                'status'      => $status,
                'type'        => $type,
                'due_from'    => $dueFrom,
                'due_to'      => $dueTo,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Tenders index failed', $e);
        }
    }

    /* ============================================================
     * 2) Create — show blank form
     * ========================================================== */
    public function create(?array $ctx = null): void
    {
        try {
            $c      = $this->ctx($ctx ?? []);
            $today  = (new \DateTimeImmutable('now'))->format('Y-m-d');

            $this->view('tenders/create', [
                'title'       => 'New tender / RFQ',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'tender'      => [],
                'mode'        => 'create',
                'next_no'     => null,      // we can wire a counter later
                'today'       => $today,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Tenders create failed', $e);
        }
    }

    /* ============================================================
     * 3) Edit — load existing row and reuse create view
     * ========================================================== */
    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_tenders')) {
                http_response_code(500);
                echo 'Tenders storage not ready (biz_tenders table missing).';
                return;
            }

            $tender = $this->row(
                "SELECT t.*, c.name AS customer_name
                 FROM biz_tenders t
                 LEFT JOIN biz_customers c
                        ON c.id = t.customer_id
                       AND c.org_id = t.org_id
                 WHERE t.org_id = ?
                   AND t.id = ?
                 LIMIT 1",
                [$orgId, $id]
            );

            if (!$tender) {
                http_response_code(404);
                echo 'Tender not found.';
                return;
            }

            $today = (new \DateTimeImmutable('now'))->format('Y-m-d');

            $this->view('tenders/create', [
                'title'       => 'Edit tender',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'tender'      => $tender,
                'mode'        => 'edit',
                'next_no'     => null,
                'today'       => $today,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Tenders edit failed', $e);
        }
    }

    /* ============================================================
     * 4) Show — full history timeline view
     * ========================================================== */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_tenders')) {
                http_response_code(500);
                echo 'Tenders storage not ready (biz_tenders table missing).';
                return;
            }

            $tender = $this->row(
                "SELECT
                     t.*,
                     c.name AS customer_name,
                     u.name AS owner_name
                 FROM biz_tenders t
                 LEFT JOIN biz_customers c
                        ON c.id = t.customer_id
                       AND c.org_id = t.org_id
                 LEFT JOIN cp_users u
                        ON u.id = t.owner_id
                 WHERE t.org_id = ?
                   AND t.id     = ?
                 LIMIT 1",
                [$orgId, $id]
            );

            if (!$tender) {
                http_response_code(404);
                echo 'Tender not found.';
                return;
            }

            // Placeholders for future related data
            $bids  = [];
            $tasks = [];
            $files = [];

            $this->view('tenders/show', [
                'title'       => 'Tender details',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'tender'      => $tender,
                'bids'        => $bids,
                'tasks'       => $tasks,
                'files'       => $files,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Tenders show failed', $e);
        }
    }

    /* ============================================================
     * 5) Store — basic create handler
     * ========================================================== */
    public function store(?array $ctx = null): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'POST only';
            return;
        }

        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_tenders')) {
                http_response_code(500);
                echo 'Tenders storage not ready (biz_tenders table missing).';
                return;
            }

            if (method_exists($this, 'csrfVerifyPostTenant')
                && !$this->csrfVerifyPostTenant()) {
                http_response_code(419);
                echo 'CSRF token mismatch.';
                return;
            }

            $in = fn(string $key, string $default = ''): string =>
                trim((string)($_POST[$key] ?? $default));

            $code        = $in('code');
            $type        = strtolower($in('type', 'rfq'));
            $title       = $in('title');
            $status      = strtolower($in('status', 'draft'));
            $customerId  = (int)($_POST['customer_id'] ?? 0) ?: null;
            $customerRef = $in('customer_ref');
            $channel     = $in('channel');
            $publishDate = $in('publish_date');
            $dueDate     = $in('due_date');
            $openingDate = $in('opening_date');
            $currency    = $in('currency', 'BDT');
            $budget      = $in('estimated_value');
            $location    = $in('location');
            $country     = $in('country');
            $scope       = (string)($_POST['scope'] ?? '');
            $notes       = (string)($_POST['internal_notes'] ?? '');

            if ($title === '' || $dueDate === '') {
                http_response_code(422);
                echo 'Title and due date are required.';
                return;
            }

            $sql = "
                INSERT INTO biz_tenders (
                    org_id,
                    code,
                    type,
                    title,
                    status,
                    customer_id,
                    customer_ref,
                    channel,
                    publish_date,
                    due_date,
                    opening_date,
                    currency,
                    estimated_value,
                    location,
                    country,
                    scope,
                    internal_notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :org_id,
                    :code,
                    :type,
                    :title,
                    :status,
                    :customer_id,
                    :customer_ref,
                    :channel,
                    :publish_date,
                    :due_date,
                    :opening_date,
                    :currency,
                    :estimated_value,
                    :location,
                    :country,
                    :scope,
                    :internal_notes,
                    NOW(),
                    NOW()
                )
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'org_id'          => $orgId,
                'code'            => $code !== '' ? $code : null,
                'type'            => $type,
                'title'           => $title,
                'status'          => $status,
                'customer_id'     => $customerId,
                'customer_ref'    => $customerRef !== '' ? $customerRef : null,
                'channel'         => $channel !== '' ? $channel : null,
                'publish_date'    => $publishDate !== '' ? $publishDate : null,
                'due_date'        => $dueDate,
                'opening_date'    => $openingDate !== '' ? $openingDate : null,
                'currency'        => $currency !== '' ? $currency : 'BDT',
                'estimated_value' => $budget !== '' ? (float)$budget : null,
                'location'        => $location !== '' ? $location : null,
                'country'         => $country !== '' ? $country : null,
                'scope'           => $scope !== '' ? $scope : null,
                'internal_notes'  => $notes !== '' ? $notes : null,
            ]);

            $id   = (int)$pdo->lastInsertId();
            $base = $c['module_base'] ?? '/apps/bizflow';
            $to   = rtrim($base, '/').'/tenders/'.$id;

            if (!headers_sent()) {
                header('Location: '.$to, true, 302);
            }
            echo 'Redirecting...';

        } catch (Throwable $e) {
            $this->oops('Tenders store failed', $e);
        }
    }

    /* ============================================================
     * 6) Update — basic edit handler
     * ========================================================== */
    public function update(?array $ctx, int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'POST only';
            return;
        }

        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_tenders')) {
                http_response_code(500);
                echo 'Tenders storage not ready (biz_tenders table missing).';
                return;
            }

            if (method_exists($this, 'csrfVerifyPostTenant')
                && !$this->csrfVerifyPostTenant()) {
                http_response_code(419);
                echo 'CSRF token mismatch.';
                return;
            }

            $in = fn(string $key, string $default = ''): string =>
                trim((string)($_POST[$key] ?? $default));

            $code        = $in('code');
            $type        = strtolower($in('type', 'rfq'));
            $title       = $in('title');
            $status      = strtolower($in('status', 'draft'));
            $customerId  = (int)($_POST['customer_id'] ?? 0) ?: null;
            $customerRef = $in('customer_ref');
            $channel     = $in('channel');
            $publishDate = $in('publish_date');
            $dueDate     = $in('due_date');
            $openingDate = $in('opening_date');
            $currency    = $in('currency', 'BDT');
            $budget      = $in('estimated_value');
            $location    = $in('location');
            $country     = $in('country');
            $scope       = (string)($_POST['scope'] ?? '');
            $notes       = (string)($_POST['internal_notes'] ?? '');

            if ($title === '' || $dueDate === '') {
                http_response_code(422);
                echo 'Title and due date are required.';
                return;
            }

            $sql = "
                UPDATE biz_tenders
                   SET code            = :code,
                       type            = :type,
                       title           = :title,
                       status          = :status,
                       customer_id     = :customer_id,
                       customer_ref    = :customer_ref,
                       channel         = :channel,
                       publish_date    = :publish_date,
                       due_date        = :due_date,
                       opening_date    = :opening_date,
                       currency        = :currency,
                       estimated_value = :estimated_value,
                       location        = :location,
                       country         = :country,
                       scope           = :scope,
                       internal_notes  = :internal_notes,
                       updated_at      = NOW()
                 WHERE org_id         = :org_id
                   AND id             = :id
                 LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'code'            => $code !== '' ? $code : null,
                'type'            => $type,
                'title'           => $title,
                'status'          => $status,
                'customer_id'     => $customerId,
                'customer_ref'    => $customerRef !== '' ? $customerRef : null,
                'channel'         => $channel !== '' ? $channel : null,
                'publish_date'    => $publishDate !== '' ? $publishDate : null,
                'due_date'        => $dueDate,
                'opening_date'    => $openingDate !== '' ? $openingDate : null,
                'currency'        => $currency !== '' ? $currency : 'BDT',
                'estimated_value' => $budget !== '' ? (float)$budget : null,
                'location'        => $location !== '' ? $location : null,
                'country'         => $country !== '' ? $country : null,
                'scope'           => $scope !== '' ? $scope : null,
                'internal_notes'  => $notes !== '' ? $notes : null,
                'org_id'          => $orgId,
                'id'              => $id,
            ]);

            $base = $c['module_base'] ?? '/apps/bizflow';
            $to   = rtrim($base, '/').'/tenders/'.$id;

            if (!headers_sent()) {
                header('Location: '.$to, true, 302);
            }
            echo 'Redirecting...';

        } catch (Throwable $e) {
            $this->oops('Tenders update failed', $e);
        }
    }

    /* ============================================================
     * 7) Local helper: check if table exists
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