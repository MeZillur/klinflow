<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\DB;
use Shared\View;
use PDO;

final class DashboardController
{
    public function index(): void
    {
        $pdo = DB::pdo();

        /* ---------------- Filters & paging (sanitized) ---------------- */
        $filters = [
            'q'      => trim((string)($_GET['q'] ?? '')),
            'status' => trim((string)($_GET['status'] ?? '')),
            'plan'   => trim((string)($_GET['plan'] ?? '')),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per'] ?? 10)));
        $offset  = ($page - 1) * $perPage;

        /* ---------------- WHERE builder ---------------- */
        $where  = [];
        $params = [];

        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $where[] = '(o.name LIKE ? OR o.slug LIKE ? OR o.owner_email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if ($filters['status'] !== '') { $where[] = 'o.status = ?'; $params[] = $filters['status']; }
        if ($filters['plan']   !== '') { $where[] = 'o.plan   = ?'; $params[] = $filters['plan'];   }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        /* ---------------- KPIs (best-effort) ---------------- */
        $kpi = ['active_orgs'=>0,'past_due'=>0,'new_7d'=>0,'mrr'=>0.0];
        try {
            $kpi['active_orgs'] = (int)$pdo->query("SELECT COUNT(*) FROM cp_organizations WHERE status='active'")->fetchColumn();
            $kpi['past_due']    = (int)$pdo->query("SELECT COUNT(*) FROM cp_organizations WHERE status IN ('suspended','past_due')")->fetchColumn();
            $kpi['new_7d']      = (int)$pdo->query("SELECT COUNT(*) FROM cp_organizations WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
            $kpi['mrr']         = (float)$pdo->query("SELECT COALESCE(SUM(monthly_price),0) FROM cp_organizations WHERE status IN ('active','trial')")->fetchColumn();
        } catch (\Throwable $e) {
            // ignore – some installs may miss these columns
        }

        /* ---------------- Trend (last 8 weeks) ---------------- */
        $trendLabels = [];
        $trendValues = [];
        try {
            $sql = "
                SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL n.w WEEK), '%d %b') AS label,
                       (
                          SELECT COUNT(*) FROM cp_organizations o
                          WHERE YEARWEEK(o.created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL n.w WEEK), 1)
                       ) AS cnt
                FROM (
                  SELECT 0 AS w UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
                  UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
                ) AS n
                ORDER BY n.w ASC
            ";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $trendLabels[] = (string)$r['label'];
                $trendValues[] = (int)$r['cnt'];
            }
        } catch (\Throwable $e) {
            // ignore if created_at or table missing
        }

        /* ---------------- Orgs list (paged) ---------------- */
        $orgs       = [];
        $total      = 0;
        $totalPages = 1;

        try {
            // total count
            $countSql = "SELECT COUNT(*) FROM cp_organizations o {$whereSql}";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            $totalPages = max(1, (int)ceil($total / $perPage));

            // base columns (all exist)
            $baseCols = "o.id, o.name, o.slug, o.plan, o.status, o.owner_email, o.monthly_price, o.created_at";

            $listSql = "
                SELECT {$baseCols}
                FROM cp_organizations o
                {$whereSql}
                ORDER BY o.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}
            ";

            $stmt = $pdo->prepare($listSql);
            $stmt->execute($params);
            $orgs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // ignore listing failure
        }

        /* ---------------- Hydrate modules from cp_org_modules + cp_modules ----
           Schema (from OrganizationsController):
             cp_org_modules: org_id, module_id, created_at, ...
             cp_modules    : id, name, module_key, is_active, ...
           We want per-org list of module *keys* (pos, dms, hotelflow, ...)
           which the view already knows how to prettify.
        ----------------------------------------------------------------------- */
        if ($orgs) {
            $ids = [];
            foreach ($orgs as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) { $ids[$id] = $id; }
            }

            if ($ids) {
                $idList = implode(',', array_fill(0, count($ids), '?'));
                $byOrg  = [];

                try {
                    $sql = "
                        SELECT om.org_id,
                               m.module_key,
                               m.slug,
                               m.name
                        FROM cp_org_modules om
                        INNER JOIN cp_modules m ON m.id = om.module_id
                        WHERE om.org_id IN ($idList)
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($ids));
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    foreach ($rows as $r) {
                        $orgId = (int)($r['org_id'] ?? 0);
                        if ($orgId <= 0) continue;

                        // prefer module_key, then slug, then name
                        $key = trim((string)($r['module_key'] ?? ''));
                        if ($key === '') {
                            $key = trim((string)($r['slug'] ?? ''));
                        }
                        if ($key === '') {
                            $key = trim((string)($r['name'] ?? ''));
                        }
                        if ($key === '') continue;

                        $byOrg[$orgId][] = $key;
                    }

                    if ($byOrg) {
                        foreach ($orgs as &$org) {
                            $oid = (int)($org['id'] ?? 0);
                            if ($oid > 0 && isset($byOrg[$oid])) {
                                // the dashboard view helper expects something in $org['modules']
                                $org['modules'] = $byOrg[$oid];
                            }
                        }
                        unset($org);
                    }
                } catch (\Throwable $e) {
                    // table/columns might not exist on very old installs – ignore
                }
            }
        }

        /* ---------------- Absolute paths (no alias troubles) ---------------- */
        $view   = \BASE_PATH . '/apps/CP/Views/cp/dashboard/index.php';
        $layout = \BASE_PATH . '/apps/CP/Views/shared/layouts/shell.php';

        if (!is_file($view)) {
            http_response_code(500);
            echo "<pre>CP dashboard view missing:\n{$view}</pre>";
            return;
        }
        if (!is_file($layout)) {
            http_response_code(500);
            echo "<pre>CP shell layout missing:\n{$layout}</pre>";
            return;
        }

        /* ---------------- Render ---------------- */
        View::render($view, [
            'title'        => 'Control Panel — Dashboard',
            'scope'        => 'cp',
            'kpi'          => $kpi,
            'trendLabels'  => $trendLabels,
            'trendValues'  => $trendValues,
            'orgs'         => $orgs,
            'filters'      => $filters,
            'pagination'   => [
                'page'       => $page,
                'perPage'    => $perPage,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
            'currentUser'  => $_SESSION['cp_user'] ?? ['name' => 'CP User', 'email' => ''],
        ], $layout);
    }
}