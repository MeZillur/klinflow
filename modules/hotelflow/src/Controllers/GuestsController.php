<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class GuestsController extends BaseController
{
    /** GET /guests */
    public function index(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)($c['org_id'] ?? 0);
        $pdo   = $this->pdo();

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $q       = trim((string)($_GET['q'] ?? ''));
        $country = trim((string)($_GET['country'] ?? ''));
        $filters = [
            'q'       => $q,
            'country' => $country,
        ];

        $rows  = [];
        $total = 0;
        $note  = null;

        if ($orgId <= 0) {
            $note = 'Organization context not resolved.';
        } elseif (!$this->tableExists($pdo, 'hms_guests')) {
            $note = 'Table hms_guests not found.';
        } else {
            try {
                // ----- main list -----
                $where = 'g.org_id = :o';
                $bind  = [':o' => $orgId];

                if ($q !== '') {
                    $where .= ' AND (g.name LIKE :q OR g.phone LIKE :q OR g.email LIKE :q OR g.doc_no LIKE :q)';
                    $bind[':q'] = '%'.$q.'%';
                }
                if ($country !== '') {
                    $where .= ' AND g.country = :country';
                    $bind[':country'] = $country;
                }

                $sql = "
                    SELECT
                      g.id,
                      g.id AS code,                         -- no more g.code bug
                      g.name,
                      COALESCE(g.phone,'')      AS phone,
                      COALESCE(g.email,'')      AS email,
                      COALESCE(g.country,'')    AS country,
                      COALESCE(g.city,'')       AS city,
                      COALESCE(g.doc_type,'')   AS doc_type,
                      COALESCE(g.doc_no,'')     AS doc_no,
                      COALESCE(g.created_at,
                               g.created_on,
                               g.inserted_at)   AS created_at
                    FROM hms_guests g
                    WHERE {$where}
                    ORDER BY g.id DESC
                    LIMIT {$limit} OFFSET {$offset}
                ";
                $st = $pdo->prepare($sql);
                $st->execute($bind);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // ----- total count -----
                $st2 = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM hms_guests g
                    WHERE {$where}
                ");
                $st2->execute($bind);
                $total = (int)$st2->fetchColumn();
            } catch (Throwable $e) {
                // soft-fail: show empty list but no routes failure
                $rows  = [];
                $total = 0;
                $note  = 'Failed to load guests list.';
            }
        }

        $this->view('guests/index', [
            'title'   => 'Guests — HotelFlow',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'filters' => $filters,
            'note'    => $note,
        ], $c);
        return;
    }

    /* -------------------------------------------------------------
     * Tiny helper (same style as FrontdeskController)
     * ----------------------------------------------------------- */
    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $q = $pdo->prepare("
                SELECT 1
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :t
            ");
            $q->execute([':t' => $table]);
            return (bool)$q->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}