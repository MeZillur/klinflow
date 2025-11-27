<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class AccountingController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    /** Check if a table exists in current database */
    private function hasTable(PDO $pdo, string $table): bool
    {
        $sql = "SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute(['t' => $table]);
        return (bool)$st->fetchColumn();
    }

    /** Check if a column exists on a table (for safety around posted_at) */
    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $sql = "SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                  AND column_name = :c
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute(['t' => $table, 'c' => $column]);
        return (bool)$st->fetchColumn();
    }

    /** Ensure org + return org_id */
    private function orgId(): int
    {
        return $this->requireOrg();
    }

    /* -------------------------------------------------------------
     * GET /accounting — overview
     * ----------------------------------------------------------- */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->orgId();
            $pdo   = $this->pdo();

            // Current month window (if we can actually filter by a date column)
            $now        = new DateTimeImmutable('now');
            $monthLabel = $now->format('F Y');
            $from       = $now->modify('first day of this month')->format('Y-m-01 00:00:00');
            $to         = $now->modify('last day of this month')->format('Y-m-t 23:59:59');

            // --- GL tables detection -----------------
            $haveJournals = $this->hasTable($pdo, 'biz_gl_journals');
            $haveLines    = $this->hasTable($pdo, 'biz_gl_lines');
            $haveAccounts = $this->hasTable($pdo, 'biz_gl_accounts');

            $storageReady = $haveJournals && $haveLines && $haveAccounts;

            // Default metrics
            $metrics = [
                'month'          => $monthLabel,
                'journals_count' => 0,
                'debit_month'    => 0.0,
                'credit_month'   => 0.0,
            ];

            if ($storageReady) {
                // See if we actually have posted_at; if not, don't filter by date
                $hasPostedAt = $this->hasColumn($pdo, 'biz_gl_journals', 'posted_at');

                // 1) Count journals
                if ($hasPostedAt) {
                    $q1 = "
                        SELECT COUNT(*) AS cnt
                        FROM biz_gl_journals
                        WHERE org_id = :org_id
                          AND posted_at BETWEEN :from AND :to
                    ";
                    $st1 = $pdo->prepare($q1);
                    $st1->execute([
                        'org_id' => $orgId,
                        'from'   => $from,
                        'to'     => $to,
                    ]);
                    $metrics['month'] = $monthLabel;
                } else {
                    // No posted_at column – count all journals for this org (all periods)
                    $q1 = "
                        SELECT COUNT(*) AS cnt
                        FROM biz_gl_journals
                        WHERE org_id = :org_id
                    ";
                    $st1 = $pdo->prepare($q1);
                    $st1->execute([
                        'org_id' => $orgId,
                    ]);
                    $metrics['month'] = 'All periods';
                }
                $metrics['journals_count'] = (int)($st1->fetchColumn() ?: 0);

                // 2) Sum debits / credits
                if ($hasPostedAt) {
                    $q2 = "
                        SELECT
                            COALESCE(SUM(CASE WHEN l.dr_cr = 'D' THEN l.amount ELSE 0 END),0) AS debit_total,
                            COALESCE(SUM(CASE WHEN l.dr_cr = 'C' THEN l.amount ELSE 0 END),0) AS credit_total
                        FROM biz_gl_lines l
                        JOIN biz_gl_journals j ON j.id = l.journal_id
                        WHERE j.org_id = :org_id
                          AND j.posted_at BETWEEN :from AND :to
                    ";
                    $st2 = $pdo->prepare($q2);
                    $st2->execute([
                        'org_id' => $orgId,
                        'from'   => $from,
                        'to'     => $to,
                    ]);
                } else {
                    $q2 = "
                        SELECT
                            COALESCE(SUM(CASE WHEN l.dr_cr = 'D' THEN l.amount ELSE 0 END),0) AS debit_total,
                            COALESCE(SUM(CASE WHEN l.dr_cr = 'C' THEN l.amount ELSE 0 END),0) AS credit_total
                        FROM biz_gl_lines l
                        JOIN biz_gl_journals j ON j.id = l.journal_id
                        WHERE j.org_id = :org_id
                    ";
                    $st2 = $pdo->prepare($q2);
                    $st2->execute([
                        'org_id' => $orgId,
                    ]);
                }

                $row = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
                $metrics['debit_month']  = (float)($row['debit_total']  ?? 0);
                $metrics['credit_month'] = (float)($row['credit_total'] ?? 0);
            } else {
                // Demo numbers until GL really wired
                $metrics['journals_count'] = 8;
                $metrics['debit_month']    = 1250000.00;
                $metrics['credit_month']   = 1250000.00;
            }

            $this->view('accounting/index', [
                'title'         => 'Accounting overview',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'metrics'       => $metrics,
                'storage_ready' => $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Accounting index failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Trial balance / Balance sheet / Bank reco
     * (UI-first stubs – real posting later)
     * ----------------------------------------------------------- */

    public function trialBalance(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $pdo = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_gl_journals')
                && $this->hasTable($pdo, 'biz_gl_lines')
                && $this->hasTable($pdo, 'biz_gl_accounts');

            $this->view('accounting/trial-balance', [
                'title'         => 'Trial balance',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'storage_ready' => $storageReady,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Trial balance failed', $e);
        }
    }

    public function balanceSheet(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $pdo = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_gl_journals')
                && $this->hasTable($pdo, 'biz_gl_lines')
                && $this->hasTable($pdo, 'biz_gl_accounts');

            $this->view('accounting/balance-sheet', [
                'title'         => 'Balance sheet',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'storage_ready' => $storageReady,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Balance sheet failed', $e);
        }
    }

    public function bankReco(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $pdo = $this->pdo();

            // Just a flag so the view can show "demo vs real"
            $storageReady = $this->hasTable($pdo, 'biz_gl_journals')
                && $this->hasTable($pdo, 'biz_gl_lines')
                && $this->hasTable($pdo, 'biz_gl_accounts')
                && $this->hasTable($pdo, 'biz_bank_accounts');

            $this->view('accounting/bank-reco', [
                'title'         => 'Bank reconciliation',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'storage_ready' => $storageReady,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Bank reconciliation failed', $e);
        }
    }
}