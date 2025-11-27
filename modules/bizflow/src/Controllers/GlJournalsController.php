<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class GlJournalsController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    private function flash(string $msg): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['bizflow_journal_flash'] = $msg;
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function demoJournals(): array
    {
        return [
            [
                'id'           => 1,
                'journal_no'   => 'JV-2025-0001',
                'journal_date' => '2025-11-01',
                'description'  => 'Sales invoice posting',
                'reference'    => 'INV-2025-001',
                'total_debit'  => 150000.00,
                'total_credit' => 150000.00,
                'posted_by'    => 'System',
            ],
            [
                'id'           => 2,
                'journal_no'   => 'JV-2025-0002',
                'journal_date' => '2025-11-02',
                'description'  => 'Salary accrual',
                'reference'    => 'PAY-2025-11',
                'total_debit'  => 280000.00,
                'total_credit' => 280000.00,
                'posted_by'    => 'System',
            ],
        ];
    }

    private function findDemoJournal(int $id): ?array
    {
        foreach ($this->demoJournals() as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    /* -------------------------------------------------------------
     * Index
     * ----------------------------------------------------------- */

    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $from = trim((string)($_GET['from'] ?? ''));
            $to   = trim((string)($_GET['to']   ?? ''));
            $q    = trim((string)($_GET['q']    ?? ''));

            if ($from === '' || $to === '') {
                $today = new DateTimeImmutable('now');
                $from  = $today->modify('first day of this month')->format('Y-m-d');
                $to    = $today->format('Y-m-d');
            }

            $flash = null;
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            if (!empty($_SESSION['bizflow_journal_flash'])) {
                $flash = (string)$_SESSION['bizflow_journal_flash'];
                unset($_SESSION['bizflow_journal_flash']);
            }

            $storageReady = $this->hasTable($pdo, 'biz_gl_journals');
            $journals     = [];

            if ($storageReady) {
                $sql = "SELECT *
                        FROM biz_gl_journals
                        WHERE org_id = :org_id
                          AND journal_date >= :from
                          AND journal_date <= :to";
                $params = [
                    'org_id' => $orgId,
                    'from'   => $from,
                    'to'     => $to,
                ];

                if ($q !== '') {
                    $sql .= " AND (
                        journal_no  LIKE :q
                        OR reference LIKE :q
                        OR description LIKE :q
                    )";
                    $params['q'] = '%'.$q.'%';
                }

                $sql .= " ORDER BY journal_date DESC, id DESC LIMIT 500";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $journals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $journals = $this->demoJournals();
            }

            $this->view('gl_journals/index', [
                'title'        => 'GL journals',
                'org'          => $org,
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'journals'     => $journals,
                'filters'      => [
                    'from' => $from,
                    'to'   => $to,
                    'q'    => $q,
                ],
                'flash'        => $flash,
                'storage_ready'=> $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Journal index failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Create + Edit + Show (preview only)
     * ----------------------------------------------------------- */

    public function create(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $org = $c['org'] ?? [];

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');

            $journal = [
                'journal_no'   => '',
                'journal_date' => $today,
                'description'  => '',
                'reference'    => '',
                'lines'        => [
                    [
                        'account_code' => '',
                        'account_name' => '',
                        'description'  => '',
                        'debit'        => '',
                        'credit'       => '',
                    ],
                    [
                        'account_code' => '',
                        'account_name' => '',
                        'description'  => '',
                        'debit'        => '',
                        'credit'       => '',
                    ],
                ],
            ];

            $this->view('gl_journals/create', [
                'title'       => 'New journal',
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'journal'     => $journal,
                'mode'        => 'create',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Journal create failed', $e);
        }
    }

    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_gl_journals');
            $journal = null;

            if ($storageReady) {
                $journal = $this->row(
                    "SELECT * FROM biz_gl_journals WHERE org_id = ? AND id = ? LIMIT 1",
                    [$orgId, $id]
                );
            } else {
                $journal = $this->findDemoJournal($id);
            }

            if (!$journal) {
                http_response_code(404);
                echo 'Journal not found.';
                return;
            }

            // Lines would normally come from biz_gl_journal_lines;
            // for preview we just show empty two rows.
            $journal['lines'] = $journal['lines'] ?? [
                [
                    'account_code' => '',
                    'account_name' => '',
                    'description'  => '',
                    'debit'        => '',
                    'credit'       => '',
                ],
                [
                    'account_code' => '',
                    'account_name' => '',
                    'description'  => '',
                    'debit'        => '',
                    'credit'       => '',
                ],
            ];

            $this->view('gl_journals/edit', [
                'title'       => 'Edit journal',
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'journal'     => $journal,
                'mode'        => 'edit',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Journal edit failed', $e);
        }
    }

    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_gl_journals');
            $journal = null;

            if ($storageReady) {
                $journal = $this->row(
                    "SELECT * FROM biz_gl_journals WHERE org_id = ? AND id = ? LIMIT 1",
                    [$orgId, $id]
                );
            } else {
                $journal = $this->findDemoJournal($id);
            }

            if (!$journal) {
                http_response_code(404);
                echo 'Journal not found.';
                return;
            }

            // For now, lines are empty demo lines.
            $journal['lines'] = $journal['lines'] ?? [];

            $this->view('gl_journals/show', [
                'title'       => 'Journal details',
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'journal'     => $journal,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Journal show failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Store / Update (preview only)
     * ----------------------------------------------------------- */

    public function store(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c    = $this->ctx($ctx ?? []);
            $base = $c['module_base'] ?? '/apps/bizflow';

            $this->flash('Journal submitted (preview only). GL posting will be enabled after schema is locked.');

            if (!headers_sent()) {
                header('Location: '.$base.'/journals');
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Journal store failed', $e);
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

            $c    = $this->ctx($ctx ?? []);
            $base = $c['module_base'] ?? '/apps/bizflow';

            $this->flash('Journal update received (preview only). Changes will persist once GL posting is implemented.');

            if (!headers_sent()) {
                header('Location: '.$base.'/journals/'.$id);
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Journal update failed', $e);
        }
    }
}