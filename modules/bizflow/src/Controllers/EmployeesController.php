<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class EmployeesController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    private function flash(string $msg): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['bizflow_employees_flash'] = $msg;
    }

    private function consumeFlash(): ?string
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        if (!empty($_SESSION['bizflow_employees_flash'])) {
            $msg = (string)$_SESSION['bizflow_employees_flash'];
            unset($_SESSION['bizflow_employees_flash']);
            return $msg;
        }
        return null;
    }

    /** Small safety helper: check if a table exists. */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $sql = 'SHOW TABLES LIKE '.$pdo->quote($table);
            $stmt = $pdo->query($sql);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // if anything goes wrong, just say "no"
            return false;
        }
    }

    /** Demo rows if biz_employees does not exist yet */
    private function demoEmployees(): array
    {
        return [
            [
                'id'               => 1,
                'emp_code'         => 'EMP-001',
                'name'             => 'Demo Employee One',
                'status'           => 'active',
                'department'       => 'Accounts',
                'designation'      => 'Senior Executive',
                'joining_date'     => '2024-01-10',
                'mobile'           => '01700000001',
                'email'            => 'demo1@example.com',
                'national_id'      => '1234567890',
                'gross_salary'     => 55000.00,
                'basic_salary'     => 30000.00,
                'house_rent'       => 15000.00,
                'other_allowances' => 10000.00,
                'bank_name'        => 'Demo Bank Ltd.',
                'bank_account_no'  => '1234567890123',
                'notes'            => 'Sample employee record for preview.',
            ],
            [
                'id'               => 2,
                'emp_code'         => 'EMP-002',
                'name'             => 'Demo Employee Two',
                'status'           => 'inactive',
                'department'       => 'Sales',
                'designation'      => 'Executive',
                'joining_date'     => '2023-07-01',
                'mobile'           => '01700000002',
                'email'            => 'demo2@example.com',
                'national_id'      => '9876543210',
                'gross_salary'     => 42000.00,
                'basic_salary'     => 23000.00,
                'house_rent'       => 12000.00,
                'other_allowances' => 7000.00,
                'bank_name'        => 'Demo Bank Ltd.',
                'bank_account_no'  => '9876543210987',
                'notes'            => 'Second sample employee.',
            ],
        ];
    }

    private function findDemoEmployee(int $id): ?array
    {
        foreach ($this->demoEmployees() as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    /* -------------------------------------------------------------
     * 1) Index
     * ----------------------------------------------------------- */

    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $q          = trim((string)($_GET['q']          ?? ''));
            $status     = trim((string)($_GET['status']     ?? ''));
            $department = trim((string)($_GET['department'] ?? ''));

            $storageReady = $this->hasTable($pdo, 'biz_employees');
            $employees    = [];

            if ($storageReady) {
                $sql = "SELECT * FROM biz_employees WHERE org_id = :org_id";
                $params = ['org_id' => $orgId];

                if ($q !== '') {
                    $sql .= " AND (
                        emp_code LIKE :q
                        OR name   LIKE :q
                        OR mobile LIKE :q
                        OR email  LIKE :q
                    )";
                    $params['q'] = '%'.$q.'%';
                }

                if ($status !== '') {
                    $sql .= " AND status = :status";
                    $params['status'] = $status;
                }

                if ($department !== '') {
                    $sql .= " AND department LIKE :dept";
                    $params['dept'] = '%'.$department.'%';
                }

                $sql .= " ORDER BY name ASC, id ASC LIMIT 500";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $employees = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $employees = $this->demoEmployees();
            }

            // Metrics
            $today = (new DateTimeImmutable('now'))->format('Y-m-d');
            $metrics = [
                'total'    => 0,
                'active'   => 0,
                'inactive' => 0,
                'today'    => $today,
            ];

            foreach ($employees as $row) {
                $metrics['total']++;
                $st = strtolower((string)($row['status'] ?? ''));
                if (in_array($st, ['active','probation'], true)) {
                    $metrics['active']++;
                } else {
                    $metrics['inactive']++;
                }
            }

            $this->view('employees/index', [
                'title'        => 'Employees',
                'org'          => $org,
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'employees'    => $employees,
                'metrics'      => $metrics,
                'filters'      => [
                    'q'          => $q,
                    'status'     => $status,
                    'department' => $department,
                ],
                'storage_ready'=> $storageReady,
                'flash'        => $this->consumeFlash(),
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Employees index failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 2) Create / Edit
     * ----------------------------------------------------------- */

    public function create(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $org = $c['org'] ?? [];
            $pdo = $this->pdo();

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');

            $employee = [
                'emp_code'         => '',
                'name'             => '',
                'status'           => 'active',
                'department'       => '',
                'designation'      => '',
                'joining_date'     => $today,
                'mobile'           => '',
                'email'            => '',
                'national_id'      => '',
                'gross_salary'     => '',
                'basic_salary'     => '',
                'house_rent'       => '',
                'other_allowances' => '',
                'bank_name'        => '',
                'bank_account_no'  => '',
                'notes'            => '',
            ];

            $this->view('employees/create', [
                'title'        => 'New employee',
                'org'          => $org,
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'employee'     => $employee,
                'mode'         => 'create',
                'storage_ready'=> $this->hasTable($pdo, 'biz_employees'),
                'flash'        => $this->consumeFlash(),
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Employees create failed', $e);
        }
    }

    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_employees');

            if ($storageReady) {
                $employee = $this->row(
                    "SELECT * FROM biz_employees WHERE org_id = ? AND id = ? LIMIT 1",
                    [$orgId, $id]
                );
            } else {
                $employee = $this->findDemoEmployee($id);
            }

            if (!$employee) {
                http_response_code(404);
                echo 'Employee not found.';
                return;
            }

            $this->view('employees/create', [
                'title'        => 'Edit employee',
                'org'          => $org,
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'employee'     => $employee,
                'mode'         => 'edit',
                'storage_ready'=> $storageReady,
                'flash'        => $this->consumeFlash(),
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Employees edit failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 3) Show
     * ----------------------------------------------------------- */

    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_employees');

            if ($storageReady) {
                $employee = $this->row(
                    "SELECT * FROM biz_employees WHERE org_id = ? AND id = ? LIMIT 1",
                    [$orgId, $id]
                );
            } else {
                $employee = $this->findDemoEmployee($id);
            }

            if (!$employee) {
                http_response_code(404);
                echo 'Employee not found.';
                return;
            }

            $this->view('employees/show', [
                'title'        => 'Employee details',
                'org'          => $org,
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'employee'     => $employee,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Employees show failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 4) Store / Update
     * ----------------------------------------------------------- */

    public function store(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();
            $base  = $c['module_base'] ?? '/apps/bizflow';

            $storageReady = $this->hasTable($pdo, 'biz_employees');
            if (!$storageReady) {
                $this->flash('Preview only: biz_employees table not created yet.');
                if (!headers_sent()) {
                    header('Location: '.$base.'/employees');
                }
                exit;
            }

            $in = static function(string $key, string $default = ''): string {
                return trim((string)($_POST[$key] ?? $default));
            };

            $gross  = (float)$in('gross_salary', '0');
            $basic  = $in('basic_salary') !== '' ? (float)$in('basic_salary') : null;
            $house  = $in('house_rent') !== '' ? (float)$in('house_rent') : null;
            $other  = $in('other_allowances') !== '' ? (float)$in('other_allowances') : null;

            $sql = "INSERT INTO biz_employees
                (org_id, emp_code, name, status, department, designation,
                 joining_date, mobile, email, national_id,
                 gross_salary, basic_salary, house_rent, other_allowances,
                 bank_name, bank_account_no, notes, created_at, updated_at)
                 VALUES
                (:org_id, :emp_code, :name, :status, :department, :designation,
                 :joining_date, :mobile, :email, :national_id,
                 :gross_salary, :basic_salary, :house_rent, :other_allowances,
                 :bank_name, :bank_account_no, :notes, NOW(), NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'org_id'          => $orgId,
                'emp_code'        => $in('emp_code'),
                'name'            => $in('name'),
                'status'          => $in('status', 'active'),
                'department'      => $in('department'),
                'designation'     => $in('designation'),
                'joining_date'    => $in('joining_date'),
                'mobile'          => $in('mobile'),
                'email'           => $in('email'),
                'national_id'     => $in('national_id'),
                'gross_salary'    => $gross,
                'basic_salary'    => $basic,
                'house_rent'      => $house,
                'other_allowances'=> $other,
                'bank_name'       => $in('bank_name'),
                'bank_account_no' => $in('bank_account_no'),
                'notes'           => $in('notes'),
            ]);

            $id = (int)$pdo->lastInsertId();
            $this->flash('Employee created successfully.');

            if (!headers_sent()) {
                header('Location: '.$base.'/employees/'.$id);
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Employees store failed', $e);
        }
    }

    public function update(?array $ctx, int $id): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();
            $base  = $c['module_base'] ?? '/apps/bizflow';

            $storageReady = $this->hasTable($pdo, 'biz_employees');
            if (!$storageReady) {
                $this->flash('Preview only: biz_employees table not created yet.');
                if (!headers_sent()) {
                    header('Location: '.$base.'/employees');
                }
                exit;
            }

            $in = static function(string $key, string $default = ''): string {
                return trim((string)($_POST[$key] ?? $default));
            };

            $gross  = (float)$in('gross_salary', '0');
            $basic  = $in('basic_salary') !== '' ? (float)$in('basic_salary') : null;
            $house  = $in('house_rent') !== '' ? (float)$in('house_rent') : null;
            $other  = $in('other_allowances') !== '' ? (float)$in('other_allowances') : null;

            $sql = "UPDATE biz_employees
                    SET emp_code = :emp_code,
                        name = :name,
                        status = :status,
                        department = :department,
                        designation = :designation,
                        joining_date = :joining_date,
                        mobile = :mobile,
                        email = :email,
                        national_id = :national_id,
                        gross_salary = :gross_salary,
                        basic_salary = :basic_salary,
                        house_rent = :house_rent,
                        other_allowances = :other_allowances,
                        bank_name = :bank_name,
                        bank_account_no = :bank_account_no,
                        notes = :notes,
                        updated_at = NOW()
                    WHERE org_id = :org_id AND id = :id
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'emp_code'        => $in('emp_code'),
                'name'            => $in('name'),
                'status'          => $in('status', 'active'),
                'department'      => $in('department'),
                'designation'     => $in('designation'),
                'joining_date'    => $in('joining_date'),
                'mobile'          => $in('mobile'),
                'email'           => $in('email'),
                'national_id'     => $in('national_id'),
                'gross_salary'    => $gross,
                'basic_salary'    => $basic,
                'house_rent'      => $house,
                'other_allowances'=> $other,
                'bank_name'       => $in('bank_name'),
                'bank_account_no' => $in('bank_account_no'),
                'notes'           => $in('notes'),
                'org_id'          => $orgId,
                'id'              => $id,
            ]);

            $this->flash('Employee updated successfully.');

            if (!headers_sent()) {
                header('Location: '.$base.'/employees/'.$id);
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Employees update failed', $e);
        }
    }
}