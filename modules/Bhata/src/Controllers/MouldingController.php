<?php
declare(strict_types=1);

namespace Modules\Bhata\Controllers\Production;

use Modules\Bhata\Controllers\BaseController;
use PDO;
use Throwable;

/**
 * Production\MouldingController
 * - Shows and stores daily green-brick moulding entries.
 * - Uses bf_moulding_entries table.
 */
final class MouldingController extends BaseController
{
    /** GET /t/{slug}/apps/bhata/production/moulding */
    public function index(array $ctx = []): void
    {
        $c   = $this->ctx($ctx);
        $pdo = $this->tenantPdo();
        $org = (int)$c['org_id'];

        $rows = [];
        try {
            $sql = "SELECT id, entry_date, gang, qty_green, remarks
                    FROM bf_moulding_entries
                    WHERE org_id = :o
                    ORDER BY entry_date DESC, id DESC
                    LIMIT 50";
            $st = $pdo->prepare($sql);
            $st->execute([':o' => $org]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }

        $this->renderStandaloneFromModuleDir((string)$c['module_dir'], 'production/moulding/index.php', [
            'title' => 'Moulding Entries',
            'rows'  => $rows,
            'ctx'   => $c,
            'org'   => $c['org'],
            'base'  => $c['module_base'],
        ]);
    }

    /** POST /t/{slug}/apps/bhata/production/moulding */
    public function store(array $ctx = []): void
    {
        $c   = $this->ctx($ctx);
        $pdo = $this->tenantPdo();
        $org = (int)$c['org_id'];

        $date    = trim((string)($_POST['entry_date'] ?? date('Y-m-d')));
        $gang    = trim((string)($_POST['gang'] ?? ''));
        $qty     = (int)($_POST['qty_green'] ?? 0);
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        if ($org <= 0 || $qty <= 0) {
            $this->abort500('Invalid input.');
        }

        try {
            $pdo->prepare("INSERT INTO bf_moulding_entries (org_id, entry_date, gang, qty_green, remarks)
                           VALUES (:o, :d, :g, :q, :r)")
                ->execute([':o' => $org, ':d' => $date, ':g' => $gang, ':q' => $qty, ':r' => $remarks]);
        } catch (Throwable $e) {
            $this->abort500($e->getMessage());
        }

        $this->redirect($c['module_base'] . '/production/moulding');
    }
}