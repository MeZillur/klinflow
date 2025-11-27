<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class PayrollController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    private function flash(string $msg): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['bizflow_payroll_flash'] = $msg;
    }

    /**
     * Lightweight "does table exist?" helper
     *  - Local only; no dependency on BaseController internals.
     */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $sql = "SELECT 1
                      FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME   = ?
                     LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([$table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Demo rows for when biz_payroll_runs / biz_payroll_lines do not exist yet */
    private function demoSheet(string $period): array
    {
        // period = YYYY-MM (for display only)
        return [
            [
                'employee_id' => 1,
                'emp_code'    => 'EMP-001',
                'emp_name'    => 'Demo Employee A',
                'department'  => 'Accounts',
                'designation' => 'Senior Officer',
                'gross'       => 65000.00,
                'basic'       => 39000.00,
                'house'       => 19500.00,
                'medical'     => 4000.00,
                'other_allow' => 2500.00,
                'ot_amount'   => 0.00,
                'deductions'  => 3000.00,
                'net_pay'     => 62000.00, // 65000 - 3000
                'status'      => 'draft',
            ],
            [
                'employee_id' => 2,
                'emp_code'    => 'EMP-002',
                'emp_name'    => 'Demo Employee B',
                'department'  => 'Supply Chain',
                'designation' => 'Executive',
                'gross'       => 48000.00,
                'basic'       => 28800.00,
                'house'       => 14400.00,
                'medical'     => 3000.00,
                'other_allow' => 800.00,
                'ot_amount'   => 1200.00,
                'deductions'  => 2200.00,
                // 48000 - 2200 + 1200 = 47000
                'net_pay'     => 47000.00,
                'status'      => 'draft',
            ],
        ];
    }

    /* -------------------------------------------------------------
     * 1) Index — Payroll sheet
     * ----------------------------------------------------------- */

    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            // Filters
            $period     = trim((string)($_GET['period']     ?? ''));
            $department = trim((string)($_GET['department'] ?? ''));
            $status     = trim((string)($_GET['status']     ?? ''));
            $q          = trim((string)($_GET['q']          ?? ''));

            if ($period === '') {
                $period = (new DateTimeImmutable('now'))->format('Y-m');
            }

            // Flash
            $flash = null;
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            if (!empty($_SESSION['bizflow_payroll_flash'])) {
                $flash = (string)$_SESSION['bizflow_payroll_flash'];
                unset($_SESSION['bizflow_payroll_flash']);
            }

            // Check if real tables exist
            $storageReady = $this->hasTable($pdo, 'biz_payroll_runs')
                           && $this->hasTable($pdo, 'biz_payroll_lines');

            $sheet   = [];
            $metrics = [
                'employees'     => 0,
                'total_gross'   => 0.0,
                'total_net'     => 0.0,
                'total_deduct'  => 0.0,
                'today'         => (new DateTimeImmutable('now'))->format('Y-m-d'),
            ];

            if ($storageReady) {
                // Find latest run for this period
                $runSql = "SELECT * FROM biz_payroll_runs
                           WHERE org_id = :org_id AND period = :period
                           ORDER BY id DESC
                           LIMIT 1";
                $runStmt = $pdo->prepare($runSql);
                $runStmt->execute([
                    'org_id' => $orgId,
                    'period' => $period,
                ]);
                $run = $runStmt->fetch(PDO::FETCH_ASSOC);

                if ($run) {
                    $sql = "SELECT l.*,
                                   e.code        AS emp_code,
                                   e.name        AS emp_name,
                                   e.department  AS department,
                                   e.designation AS designation
                              FROM biz_payroll_lines l
                         LEFT JOIN biz_employees e
                                ON e.org_id = l.org_id
                               AND e.id     = l.employee_id
                             WHERE l.org_id = :org_id
                               AND l.run_id = :run_id";
                    $params = [
                        'org_id' => $orgId,
                        'run_id' => $run['id'],
                    ];

                    if ($department !== '') {
                        $sql .= " AND (e.department LIKE :dept)";
                        $params['dept'] = '%'.$department.'%';
                    }

                    if ($status !== '') {
                        $sql .= " AND l.status = :status";
                        $params['status'] = $status;
                    }

                    if ($q !== '') {
                        $sql .= " AND (
                                   e.name   LIKE :q
                                OR e.code   LIKE :q
                                OR e.email  LIKE :q
                                OR e.mobile LIKE :q
                              )";
                        $params['q'] = '%'.$q.'%';
                    }

                    $sql .= " ORDER BY e.name ASC, e.code ASC";

                    $st = $pdo->prepare($sql);
                    $st->execute($params);
                    $sheet = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            } else {
                // Demo data only
                $sheet = $this->demoSheet($period);
            }

            // Metrics calculation
            foreach ($sheet as $row) {
                $metrics['employees']++;
                $gross      = (float)($row['gross'] ?? 0);
                $deductions = (float)($row['deductions'] ?? 0);
                $net        = (float)($row['net_pay'] ?? 0);

                $metrics['total_gross']  += $gross;
                $metrics['total_deduct'] += $deductions;
                $metrics['total_net']    += $net;
            }

            $this->view('payroll/index', [
                'title'         => 'Payroll sheet',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'sheet'         => $sheet,
                'metrics'       => $metrics,
                'period'        => $period,
                'filters'       => [
                    'period'     => $period,
                    'department' => $department,
                    'status'     => $status,
                    'q'          => $q,
                ],
                'storage_ready' => $storageReady,
                'flash'         => $flash,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Payroll index failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 2) Recalculate sheet (preview only)
     * ----------------------------------------------------------- */

    public function recalc(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c      = $this->ctx($ctx ?? []);
            $base   = $c['module_base'] ?? '/apps/bizflow';
            $period = trim((string)($_POST['period'] ?? ''));

            if ($period === '') {
                $period = (new DateTimeImmutable('now'))->format('Y-m');
            }

            $this->flash('Payroll recalc requested (preview only). Engine will be wired after schema is final.');

            if (!headers_sent()) {
                header('Location: '.$base.'/payroll?period='.urlencode($period));
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Payroll recalc failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 3) Lock & post (preview only)
     * ----------------------------------------------------------- */

    public function lock(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c      = $this->ctx($ctx ?? []);
            $base   = $c['module_base'] ?? '/apps/bizflow';
            $period = trim((string)($_POST['period'] ?? ''));

            if ($period === '') {
                $period = (new DateTimeImmutable('now'))->format('Y-m');
            }

            $this->flash('Payroll lock/post requested (preview only). GL posting will be wired after schema is final.');

            if (!headers_sent()) {
                header('Location: '.$base.'/payroll?period='.urlencode($period));
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Payroll lock failed', $e);
        }
    }
}