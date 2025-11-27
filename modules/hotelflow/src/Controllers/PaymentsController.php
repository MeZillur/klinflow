<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class PaymentsController extends BaseController
{
    /* ---------------------------------------------------------
     * Small helpers (schema-safe)
     * ------------------------------------------------------- */

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

    private function colExists(PDO $pdo, string $table, string $col): bool
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
            $st->execute([':t' => $table, ':c' => $col]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function orgIdFromCtxOrSession(array $c): int
    {
        $orgId = (int)($c['org_id'] ?? 0);
        if ($orgId > 0) return $orgId;

        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $org = $_SESSION['tenant_org'] ?? null;
        return (int)($org['id'] ?? 0);
    }

    private function moduleBaseFromCtx(array $c): string
    {
        $base = (string)($c['module_base'] ?? '/apps/hotelflow');
        return rtrim($base, '/');
    }

    /* ---------------------------------------------------------
     * GET /payments → Payments dashboard + list
     * ------------------------------------------------------- */
    public function index(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgIdFromCtxOrSession($c);

        if ($orgId <= 0) {
            http_response_code(400);
            echo 'Organisation context missing for payments.';
            return;
        }

        $today = date('Y-m-d');

        // Filters
        $from   = trim((string)($_GET['from']   ?? $today));
        $to     = trim((string)($_GET['to']     ?? $today));
        $method = trim((string)($_GET['method'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $q      = trim((string)($_GET['q']      ?? ''));

        $stats = [
            'today_total'   => 0.0,
            'month_total'   => 0.0,
            'refunds_today' => 0.0,
            'pending_total' => 0.0,
        ];
        $rows  = [];

        $table = 'hms_payments';

        if (!$this->tableExists($pdo, $table)) {
            // Table missing → render UI with zeros
            $this->view('payments/index', [
                'title' => 'Payments',
                'today' => $today,
                'stats' => $stats,
                'rows'  => $rows,
            ], $c);
            return;
        }

        // Column checks
        $hasCreatedAt = $this->colExists($pdo, $table, 'created_at');
        $hasPaidAt    = $this->colExists($pdo, $table, 'paid_at');
        $hasAmount    = $this->colExists($pdo, $table, 'amount');
        $hasCurrency  = $this->colExists($pdo, $table, 'currency');
        $hasStatus    = $this->colExists($pdo, $table, 'status');
        $hasMethod    = $this->colExists($pdo, $table, 'method');
        $hasRef       = $this->colExists($pdo, $table, 'reference');
        $hasResId     = $this->colExists($pdo, $table, 'reservation_id');

        $dateExpr = null;
        if ($hasPaidAt) {
            $dateExpr = 'DATE(p.paid_at)';
        } elseif ($hasCreatedAt) {
            $dateExpr = 'DATE(p.created_at)';
        }

        /* ---------- 1) Stats ---------- */
        if ($hasAmount && $dateExpr !== null) {
            try {
                // Today totals
                $st = $pdo->prepare("
                    SELECT
                        SUM(CASE WHEN status <> 'refunded' THEN amount ELSE 0 END) AS today_total,
                        SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END)   AS refunds_today
                    FROM {$table} p
                    WHERE p.org_id = :o
                      AND {$dateExpr} = :d
                ");
                $st->execute([
                    ':o' => $orgId,
                    ':d' => $today,
                ]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $stats['today_total']   = (float)($row['today_total']   ?? 0);
                $stats['refunds_today'] = (float)($row['refunds_today'] ?? 0);

                // Month + pending
                $monthStart = date('Y-m-01');
                $st2 = $pdo->prepare("
                    SELECT
                        SUM(CASE WHEN status = 'posted'  THEN amount ELSE 0 END) AS month_total,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) AS pending_total
                    FROM {$table} p
                    WHERE p.org_id = :o
                      AND {$dateExpr} >= :m
                      AND {$dateExpr} <= :d
                ");
                $st2->execute([
                    ':o' => $orgId,
                    ':m' => $monthStart,
                    ':d' => $today,
                ]);
                $row2 = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
                $stats['month_total']   = (float)($row2['month_total']   ?? 0);
                $stats['pending_total'] = (float)($row2['pending_total'] ?? 0);
            } catch (Throwable $e) {
                // ignore
            }
        }

        /* ---------- 2) List query ---------- */
        $select = ['p.id'];

        if ($dateExpr !== null) {
            $select[] = "{$dateExpr} AS pay_date";
        }
        if ($hasCreatedAt) {
            $select[] = 'TIME(p.created_at) AS pay_time';
        }
        if ($hasAmount) {
            $select[] = 'p.amount';
        }
        if ($hasCurrency) {
            $select[] = 'p.currency';
        }
        if ($hasMethod) {
            $select[] = 'p.method';
        }
        if ($hasStatus) {
            $select[] = 'p.status';
        }
        if ($hasRef) {
            $select[] = 'p.reference';
        }
        if ($hasResId) {
            $select[] = 'p.reservation_id';
        }

        // joined info
        $select[] = 'r.code      AS reservation_code';
        $select[] = 'r.room_no   AS room_no';
        $select[] = 'r.channel   AS res_channel';
        $select[] = 'g.full_name AS guest_name';

        $sql  = "SELECT " . implode(', ', $select) . "
                 FROM {$table} p
                 LEFT JOIN hms_reservations r
                        ON r.id     = p.reservation_id
                       AND r.org_id = p.org_id
                 LEFT JOIN hms_guests g
                        ON g.id     = r.guest_id
                       AND g.org_id = r.org_id
                WHERE p.org_id = :o";
        $bind = [':o' => $orgId];

        // Date filter
        if ($dateExpr !== null && $from !== '' && $to !== '') {
            $sql          .= " AND {$dateExpr} BETWEEN :from AND :to";
            $bind[':from'] = $from;
            $bind[':to']   = $to;
        }

        if ($method !== '' && $hasMethod) {
            $sql            .= " AND p.method = :method";
            $bind[':method'] = $method;
        }

        if ($status !== '' && $hasStatus) {
            $sql            .= " AND p.status = :status";
            $bind[':status'] = $status;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= " AND (
                        r.code      LIKE :q
                     OR g.full_name LIKE :q
                     OR p.reference LIKE :q
                    )";
            $bind[':q'] = $like;
        }

        if ($hasCreatedAt) {
            $sql .= " ORDER BY p.created_at DESC";
        } else {
            $sql .= " ORDER BY p.id DESC";
        }
        $sql .= " LIMIT 200";

        try {
            $st  = $pdo->prepare($sql);
            $st->execute($bind);
            $raw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $raw = [];
        }

        foreach ($raw as $r) {
            $rows[] = [
                'id'               => (int)($r['id'] ?? 0),
                'date'             => (string)($r['pay_date'] ?? ''),
                'time'             => (string)($r['pay_time'] ?? ''),
                'reservation_id'   => isset($r['reservation_id']) ? (int)$r['reservation_id'] : 0,
                'reservation_code' => (string)($r['reservation_code'] ?? ''),
                'room_no'          => (string)($r['room_no'] ?? ''),
                'guest_name'       => (string)($r['guest_name'] ?? ''),
                'channel'          => (string)($r['res_channel'] ?? ''),
                'method'           => (string)($r['method'] ?? ''),
                'currency'         => (string)($r['currency'] ?? 'BDT'),
                'amount'           => (float)($r['amount'] ?? 0),
                'reference'        => (string)($r['reference'] ?? ''),
                'status'           => (string)($r['status'] ?? 'posted'),
            ];
        }

        $this->view('payments/index', [
            'title' => 'Payments',
            'today' => $today,
            'stats' => $stats,
            'rows'  => $rows,
        ], $c);
    }

    /* ---------------------------------------------------------
     * GET /payments/receive → Receive payment form
     * ------------------------------------------------------- */
    public function receiveForm(array $ctx): void
    {
        $c   = $this->ctx($ctx);
        $now = time();

        $this->view('payments/receive', [
            'title'   => 'Receive payment',
            'today'   => date('Y-m-d', $now),
            'nowTime' => date('H:i', $now),
        ], $c);
    }

    /* ---------------------------------------------------------
     * POST /payments/receive → Store payment
     * ------------------------------------------------------- */
    public function receiveStore(array $ctx): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('payments/receive', $ctx);
            return;
        }

        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgIdFromCtxOrSession($c);
        $base  = $this->moduleBaseFromCtx($c);

        if ($orgId <= 0) {
            http_response_code(400);
            echo 'Organisation context missing for payments.';
            return;
        }

        $table = 'hms_payments';
        if (!$this->tableExists($pdo, $table)) {
            http_response_code(500);
            echo 'Payments table (hms_payments) is missing.';
            return;
        }

        // ------------ read form ------------
        $resInput = trim((string)($_POST['reservation_lookup'] ?? ''));
        $amount   = (float)($_POST['amount'] ?? 0);
        $method   = trim((string)($_POST['method'] ?? 'Cash'));
        $status   = trim((string)($_POST['status'] ?? 'posted'));
        $ref      = trim((string)($_POST['reference'] ?? ''));
        $note     = trim((string)($_POST['note'] ?? ''));
        $payDate  = trim((string)($_POST['pay_date'] ?? date('Y-m-d')));
        $payTime  = trim((string)($_POST['pay_time'] ?? date('H:i')));

        if ($resInput === '' || $amount <= 0) {
            http_response_code(400);
            echo '<div style="padding:16px;font-family:system-ui">'
               . '<h2 style="margin:0 0 8px;color:#b91c1c">Payment not saved</h2>'
               . '<div style="color:#4b5563;font-size:14px;">'
               . 'Reservation and positive amount are required.'
               . '</div></div>';
            return;
        }

        // ------------ resolve reservation ------------
        $reservation = null;
        try {
            $st = $pdo->prepare("
                SELECT id, code, guest_id
                  FROM hms_reservations
                 WHERE org_id = :o
                   AND (
                         id   = :id
                      OR code = :code
                 )
                 ORDER BY id DESC
                 LIMIT 1
            ");
            $idGuess = ctype_digit($resInput) ? (int)$resInput : 0;
            $st->execute([
                ':o'    => $orgId,
                ':id'   => $idGuess,
                ':code' => $resInput,
            ]);
            $reservation = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $reservation = null;
        }

        if (!$reservation) {
            http_response_code(404);
            echo '<div style="padding:16px;font-family:system-ui">'
               . '<h2 style="margin:0 0 8px;color:#b91c1c">Reservation not found</h2>'
               . '<div style="color:#4b5563;font-size:14px;">'
               . 'Please check the reservation ID / code.'
               . '</div></div>';
            return;
        }

        $resId  = (int)$reservation['id'];
        $resCode = (string)($reservation['code'] ?? '');

        $paidAt = $payDate.' '.$payTime.':00';
        $nowTs  = date('Y-m-d H:i:s');

        // Ensure minimal columns exist for insert
        $hasCurrency = $this->colExists($pdo, $table, 'currency');
        $hasNote     = $this->colExists($pdo, $table, 'note');

        try {
            $cols  = ['org_id','reservation_id','amount','method','status','reference','paid_at','created_at'];
            $place = [':o',':r',':amount',':method',':status',':ref',':paid_at',':created_at'];
            $bind  = [
                ':o'          => $orgId,
                ':r'          => $resId,
                ':amount'     => $amount,
                ':method'     => $method !== '' ? $method : 'Cash',
                ':status'     => $status !== '' ? $status : 'posted',
                ':ref'        => $ref !== '' ? $ref : $resCode,
                ':paid_at'    => $paidAt,
                ':created_at' => $nowTs,
            ];

            if ($hasCurrency) {
                $cols[]   = 'currency';
                $place[]  = ':currency';
                $bind[':currency'] = 'BDT';
            }
            if ($hasNote) {
                $cols[]   = 'note';
                $place[]  = ':note';
                $bind[':note'] = $note !== '' ? $note : null;
            }

            $sql = "INSERT INTO {$table} (".implode(',', $cols).")
                    VALUES (".implode(',', $place).")";

            $st = $pdo->prepare($sql);
            $st->execute($bind);
            $paymentId = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            http_response_code(500);
            echo '<div style="padding:16px;font-family:system-ui">'
               . '<h2 style="margin:0 0 8px;color:#b91c1c">Failed to save payment</h2>'
               . '<div style="color:#4b5563;font-size:14px;">'
               . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
               . '</div></div>';
            return;
        }

        // Redirect back to reservation page (main flow) or payments list
        $target = $base.'/reservations/'.$resId;
        if ($paymentId <= 0) {
            $target = $base.'/payments';
        }

        header('Location: '.$target, true, 302);
        exit;
    }

    /* ---------------------------------------------------------
     * GET /payments/{id} → Payment details
     * ------------------------------------------------------- */
    public function show(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgIdFromCtxOrSession($c);

        if ($orgId <= 0) {
            http_response_code(400);
            echo 'Organisation context missing for payments.';
            return;
        }

        $table = 'hms_payments';
        if (!$this->tableExists($pdo, $table)) {
            $this->notFound('Payments table missing.');
            return;
        }

        $row = null;
        try {
            $st = $pdo->prepare("
                SELECT
                    p.*,
                    r.code      AS reservation_code,
                    r.room_no   AS room_no,
                    g.full_name AS guest_name
                  FROM {$table} p
             LEFT JOIN hms_reservations r
                    ON r.id     = p.reservation_id
                   AND r.org_id = p.org_id
             LEFT JOIN hms_guests g
                    ON g.id     = r.guest_id
                   AND g.org_id = r.org_id
                 WHERE p.org_id = :o
                   AND p.id     = :id
                 LIMIT 1
            ");
            $st->execute([
                ':o'  => $orgId,
                ':id' => $id,
            ]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $row = null;
        }

        if (!$row) {
            $this->notFound('Payment not found.');
            return;
        }

        $this->view('payments/show', [
            'title'   => 'Payment #'.$row['id'],
            'payment' => $row,
        ], $c);
    }
}