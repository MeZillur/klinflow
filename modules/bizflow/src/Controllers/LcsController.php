<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class LcsController extends BaseController
{
    /* -------------------------------------------------------------
     * Small helpers (self-contained, no BaseController dependency)
     * ----------------------------------------------------------- */

    /** Simple flash helper for LC pages */
    private function flash(string $msg): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['bizflow_lc_flash'] = $msg;
    }

    /** Local hasTable() so we don't depend on BaseController */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT 1
                   FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name   = :t
                  LIMIT 1"
            );
            $stmt->execute(['t' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Fetch single row, or null */
    private function fetchRow(PDO $pdo, string $sql, array $params): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Demo rows for when biz_lcs table does not exist yet */
    private function demoLcs(): array
    {
        return [
            [
                'id'                 => 1,
                'lc_no'              => 'LC-2025-0001',
                'contract_no'        => 'CNT-2025-001',
                'pi_no'              => 'PI-2401',
                'applicant_name'     => 'Demo Importer Ltd.',
                'beneficiary_name'   => 'Shanghai Machinery Co.',
                'issuing_bank'       => 'Demo Bank Ltd.',
                'advising_bank'      => 'Bank of Demo (Hong Kong)',
                'currency'           => 'USD',
                'lc_amount'          => 125000.00,
                'status'             => 'open',
                'stage'              => 'documents_pending',
                'opened_at'          => '2025-01-10',
                'expiry_date'        => '2025-04-30',
                'last_shipment_date' => '2025-03-31',
                'port_of_loading'    => 'Shanghai',
                'port_of_discharge'  => 'Chattogram',
            ],
            [
                'id'                 => 2,
                'lc_no'              => 'LC-2024-0097',
                'contract_no'        => 'CNT-2024-097',
                'pi_no'              => 'PI-2390',
                'applicant_name'     => 'Demo Importer Ltd.',
                'beneficiary_name'   => 'New Delhi Textiles',
                'issuing_bank'       => 'Demo Bank Ltd.',
                'advising_bank'      => 'Demo Bank (India)',
                'currency'           => 'USD',
                'lc_amount'          => 54000.00,
                'status'             => 'retired',
                'stage'              => 'retired',
                'opened_at'          => '2024-06-01',
                'expiry_date'        => '2024-09-30',
                'last_shipment_date' => '2024-08-31',
                'port_of_loading'    => 'Nhava Sheva',
                'port_of_discharge'  => 'Chattogram',
            ],
        ];
    }

    private function findDemoLc(int $id): ?array
    {
        foreach ($this->demoLcs() as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    /** Normalize scalar POST field */
    private function post(string $key, ?string $default = null): ?string
    {
        return isset($_POST[$key])
            ? trim((string)$_POST[$key])
            : $default;
    }

    /** Normalize date (Y-m-d or empty → null) */
    private function postDate(string $key): ?string
    {
        $v = $this->post($key, '');
        if ($v === '') {
            return null;
        }
        // dumb trust for now; later we can validate
        return $v;
    }

    /** Normalize decimal numeric */
    private function postDecimal(string $key): ?string
    {
        $v = $this->post($key, '');
        if ($v === '') {
            return null;
        }
        // remove commas etc.
        $v = str_replace([',', ' '], '', $v);
        if (!is_numeric($v)) {
            return null;
        }
        return $v;
    }

    /* -------------------------------------------------------------
     * 1) Index — LC register
     * ----------------------------------------------------------- */

    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $q      = trim((string)($_GET['q']      ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));
            $bank   = trim((string)($_GET['bank']   ?? ''));
            $from   = trim((string)($_GET['from']   ?? ''));
            $to     = trim((string)($_GET['to']     ?? ''));
            $stage  = trim((string)($_GET['stage']  ?? ''));

            $storageReady = $this->hasTable($pdo, 'biz_lcs');
            $lcs          = [];

            if ($storageReady) {
                $sql = "SELECT * FROM biz_lcs WHERE org_id = :org_id";
                $params = ['org_id' => $orgId];

                if ($q !== '') {
                    $sql .= " AND (
                        lc_no             LIKE :q
                        OR contract_no    LIKE :q
                        OR pi_no          LIKE :q
                        OR applicant_name LIKE :q
                        OR beneficiary_name LIKE :q
                    )";
                    $params['q'] = '%'.$q.'%';
                }

                if ($status !== '') {
                    $sql .= " AND status = :status";
                    $params['status'] = $status;
                }

                if ($stage !== '') {
                    $sql .= " AND stage = :stage";
                    $params['stage'] = $stage;
                }

                if ($bank !== '') {
                    $sql .= " AND (issuing_bank LIKE :bank OR advising_bank LIKE :bank)";
                    $params['bank'] = '%'.$bank.'%';
                }

                if ($from !== '') {
                    $sql .= " AND opened_at >= :from";
                    $params['from'] = $from;
                }
                if ($to !== '') {
                    $sql .= " AND opened_at <= :to";
                    $params['to'] = $to;
                }

                $sql .= " ORDER BY opened_at DESC, id DESC LIMIT 500";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $lcs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $lcs = $this->demoLcs();
            }

            // Metrics
            $today = (new DateTimeImmutable('now'))->format('Y-m-d');
            $metrics = [
                'total'     => 0,
                'open'      => 0,
                'retired'   => 0,
                'expired'   => 0,
                'high_risk' => 0, // expired but not retired
                'today'     => $today,
            ];

            foreach ($lcs as $row) {
                $metrics['total']++;
                $st   = strtolower((string)($row['status'] ?? ''));
                $exp  = (string)($row['expiry_date'] ?? '');
                $open = (string)($row['opened_at'] ?? '');

                if (in_array($st, ['open','active','documents_pending'], true)) {
                    $metrics['open']++;
                } elseif ($st === 'retired') {
                    $metrics['retired']++;
                }

                if ($exp !== '' && $exp < $today && $st !== 'retired') {
                    $metrics['expired']++;
                    $metrics['high_risk']++;
                }

                if ($open !== '' && $exp !== '' && $today > $exp && $st !== 'retired') {
                    $metrics['high_risk']++;
                }
            }

            $this->view('lcs/index', [
                'title'         => 'Import LCs',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'lcs'           => $lcs,
                'metrics'       => $metrics,
                'filters'       => [
                    'q'      => $q,
                    'status' => $status,
                    'bank'   => $bank,
                    'from'   => $from,
                    'to'     => $to,
                    'stage'  => $stage,
                ],
                'storage_ready' => $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LC index failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 2) Create / Edit — same form, different mode
     * ----------------------------------------------------------- */

    public function create(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $org = $c['org'] ?? [];

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');

            $lc = [
                'lc_no'              => '',
                'contract_no'        => '',
                'pi_no'              => '',
                'pi_date'            => $today,
                'applicant_name'     => $org['name'] ?? '',
                'beneficiary_name'   => '',
                'issuing_bank'       => '',
                'advising_bank'      => '',
                'currency'           => 'USD',
                'lc_amount'          => '',
                'margin_percent'     => '',
                'margin_amount'      => '',
                'opened_at'          => $today,
                'expiry_date'        => '',
                'last_shipment_date' => '',
                'port_of_loading'    => '',
                'port_of_discharge'  => '',
                'incoterm'           => '',
                'status'             => 'open',
                'stage'              => 'contract',
                'lca_no'             => '',
                'insurance_policy_no'=> '',
                'notes'              => '',
            ];

            $this->view('lcs/create', [
                'title'       => 'Open new LC',
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'lc'          => $lc,
                'mode'        => 'create',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LC create failed', $e);
        }
    }

    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_lcs');
            $lc = null;

            if ($storageReady) {
                $lc = $this->fetchRow(
                    $pdo,
                    "SELECT * FROM biz_lcs WHERE org_id = ? AND id = ? LIMIT 1",
                    [$orgId, $id]
                );
            } else {
                $lc = $this->findDemoLc($id);
            }

            if (!$lc) {
                http_response_code(404);
                echo 'LC not found.';
                return;
            }

            $this->view('lcs/edit', [
                'title'       => 'Edit LC',
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'lc'          => $lc,
                'mode'        => 'edit',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LC edit failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 3) Show — LC lifecycle
     * ----------------------------------------------------------- */

    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_lcs');
            $lc = null;

            if ($storageReady) {
                $lc = $this->fetchRow(
                    $pdo,
                    "SELECT * FROM biz_lcs WHERE org_id = ? AND id = ? LIMIT 1",
                    [$orgId, $id]
                );
            } else {
                $lc = $this->findDemoLc($id);
            }

            if (!$lc) {
                http_response_code(404);
                echo 'LC not found.';
                return;
            }

            // Timeline events (contract → opened → shipment → docs → maturity → retired)
            $history = [];
            $push = static function(array &$hist, ?string $ts, string $kind, string $text): void {
                if ($ts && $ts !== '') {
                    $hist[] = ['ts' => $ts, 'kind' => $kind, 'text' => $text];
                }
            };

            $push($history, $lc['contract_date']        ?? null, 'contract',  'Sales contract agreed');
            $push($history, $lc['pi_date']              ?? null, 'pi',        'Proforma invoice / indent confirmed');
            $push($history, $lc['opened_at']            ?? null, 'opened',    'LC opened by issuing bank');
            $push($history, $lc['last_shipment_date']   ?? null, 'shipment',  'Latest shipment date');
            $push($history, $lc['docs_received_at']     ?? null, 'docs',      'Shipping documents received');
            $push($history, $lc['maturity_date']        ?? null, 'maturity',  'LC maturity date');
            $push($history, $lc['retired_at']           ?? null, 'retired',   'LC retired / documents fully paid');

            usort($history, static function(array $a, array $b): int {
                return strcmp((string)$a['ts'], (string)$b['ts']);
            });

            $this->view('lcs/show', [
                'title'         => 'LC details',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'lc'            => $lc,
                'history'       => $history,
                'storage_ready' => $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LC show failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * 4) Store / Update — now REAL DB writes (if table exists)
     * ----------------------------------------------------------- */

    public function store(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c      = $this->ctx($ctx ?? []);
            $orgId  = $this->requireOrg();
            $pdo    = $this->pdo();
            $base   = $c['module_base'] ?? '/apps/bizflow';

            $storageReady = $this->hasTable($pdo, 'biz_lcs');
            if (!$storageReady) {
                $this->flash('LC form received but biz_lcs table is not ready yet. Please run migrations first.');
                if (!headers_sent()) {
                    header('Location: ' . $base . '/lcs');
                }
                exit;
            }

            // Collect fields from POST
            $data = [
                'org_id'             => $orgId,
                'lc_no'              => $this->post('lc_no'),
                'contract_no'        => $this->post('contract_no'),
                'pi_no'              => $this->post('pi_no'),
                'pi_date'            => $this->postDate('pi_date'),
                'applicant_name'     => $this->post('applicant_name'),
                'beneficiary_name'   => $this->post('beneficiary_name'),
                'issuing_bank'       => $this->post('issuing_bank'),
                'advising_bank'      => $this->post('advising_bank'),
                'currency'           => $this->post('currency') ?: 'USD',
                'lc_amount'          => $this->postDecimal('lc_amount'),
                'margin_percent'     => $this->postDecimal('margin_percent'),
                'margin_amount'      => $this->postDecimal('margin_amount'),
                'opened_at'          => $this->postDate('opened_at'),
                'expiry_date'        => $this->postDate('expiry_date'),
                'last_shipment_date' => $this->postDate('last_shipment_date'),
                'port_of_loading'    => $this->post('port_of_loading'),
                'port_of_discharge'  => $this->post('port_of_discharge'),
                'incoterm'           => $this->post('incoterm'),
                'status'             => $this->post('status') ?: 'open',
                'stage'              => $this->post('stage')  ?: 'contract',
                'lca_no'             => $this->post('lca_no'),
                'insurance_policy_no'=> $this->post('insurance_policy_no'),
                'notes'              => $this->post('notes'),
            ];

            // Minimal validation
            if ($data['lc_no'] === '' || $data['lc_no'] === null) {
                $this->flash('LC no is required.');
                if (!headers_sent()) {
                    header('Location: ' . $base . '/lcs/create');
                }
                exit;
            }

            // Insert
            $sql = "INSERT INTO biz_lcs (
                        org_id,
                        lc_no,
                        contract_no,
                        pi_no,
                        pi_date,
                        applicant_name,
                        beneficiary_name,
                        issuing_bank,
                        advising_bank,
                        currency,
                        lc_amount,
                        margin_percent,
                        margin_amount,
                        opened_at,
                        expiry_date,
                        last_shipment_date,
                        port_of_loading,
                        port_of_discharge,
                        incoterm,
                        status,
                        stage,
                        lca_no,
                        insurance_policy_no,
                        notes
                    ) VALUES (
                        :org_id,
                        :lc_no,
                        :contract_no,
                        :pi_no,
                        :pi_date,
                        :applicant_name,
                        :beneficiary_name,
                        :issuing_bank,
                        :advising_bank,
                        :currency,
                        :lc_amount,
                        :margin_percent,
                        :margin_amount,
                        :opened_at,
                        :expiry_date,
                        :last_shipment_date,
                        :port_of_loading,
                        :port_of_discharge,
                        :incoterm,
                        :status,
                        :stage,
                        :lca_no,
                        :insurance_policy_no,
                        :notes
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            $id = (int)$pdo->lastInsertId();

            $this->flash('LC created successfully.');
            if (!headers_sent()) {
                header('Location: ' . $base . '/lcs/' . $id);
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('LC store failed', $e);
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

            $c      = $this->ctx($ctx ?? []);
            $orgId  = $this->requireOrg();
            $pdo    = $this->pdo();
            $base   = $c['module_base'] ?? '/apps/bizflow';

            $storageReady = $this->hasTable($pdo, 'biz_lcs');
            if (!$storageReady) {
                $this->flash('LC update received but biz_lcs table is not ready yet. Please run migrations first.');
                if (!headers_sent()) {
                    header('Location: ' . $base . '/lcs');
                }
                exit;
            }

            // Exists?
            $existing = $this->fetchRow(
                $pdo,
                "SELECT id FROM biz_lcs WHERE org_id = ? AND id = ? LIMIT 1",
                [$orgId, $id]
            );
            if (!$existing) {
                http_response_code(404);
                echo 'LC not found.';
                return;
            }

            $data = [
                'org_id'             => $orgId,
                'id'                 => $id,
                'lc_no'              => $this->post('lc_no'),
                'contract_no'        => $this->post('contract_no'),
                'pi_no'              => $this->post('pi_no'),
                'pi_date'            => $this->postDate('pi_date'),
                'applicant_name'     => $this->post('applicant_name'),
                'beneficiary_name'   => $this->post('beneficiary_name'),
                'issuing_bank'       => $this->post('issuing_bank'),
                'advising_bank'      => $this->post('advising_bank'),
                'currency'           => $this->post('currency') ?: 'USD',
                'lc_amount'          => $this->postDecimal('lc_amount'),
                'margin_percent'     => $this->postDecimal('margin_percent'),
                'margin_amount'      => $this->postDecimal('margin_amount'),
                'opened_at'          => $this->postDate('opened_at'),
                'expiry_date'        => $this->postDate('expiry_date'),
                'last_shipment_date' => $this->postDate('last_shipment_date'),
                'port_of_loading'    => $this->post('port_of_loading'),
                'port_of_discharge'  => $this->post('port_of_discharge'),
                'incoterm'           => $this->post('incoterm'),
                'status'             => $this->post('status') ?: 'open',
                'stage'              => $this->post('stage')  ?: 'contract',
                'lca_no'             => $this->post('lca_no'),
                'insurance_policy_no'=> $this->post('insurance_policy_no'),
                'notes'              => $this->post('notes'),
            ];

            if ($data['lc_no'] === '' || $data['lc_no'] === null) {
                $this->flash('LC no is required.');
                if (!headers_sent()) {
                    header('Location: ' . $base . '/lcs/'.$id.'/edit');
                }
                exit;
            }

            $sql = "UPDATE biz_lcs
                       SET lc_no              = :lc_no,
                           contract_no        = :contract_no,
                           pi_no              = :pi_no,
                           pi_date            = :pi_date,
                           applicant_name     = :applicant_name,
                           beneficiary_name   = :beneficiary_name,
                           issuing_bank       = :issuing_bank,
                           advising_bank      = :advising_bank,
                           currency           = :currency,
                           lc_amount          = :lc_amount,
                           margin_percent     = :margin_percent,
                           margin_amount      = :margin_amount,
                           opened_at          = :opened_at,
                           expiry_date        = :expiry_date,
                           last_shipment_date = :last_shipment_date,
                           port_of_loading    = :port_of_loading,
                           port_of_discharge  = :port_of_discharge,
                           incoterm           = :incoterm,
                           status             = :status,
                           stage              = :stage,
                           lca_no             = :lca_no,
                           insurance_policy_no = :insurance_policy_no,
                           notes              = :notes
                     WHERE org_id = :org_id
                       AND id     = :id
                     LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);

            $this->flash('LC updated successfully.');
            if (!headers_sent()) {
                header('Location: ' . $base . '/lcs/' . $id);
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('LC update failed', $e);
        }
    }
}