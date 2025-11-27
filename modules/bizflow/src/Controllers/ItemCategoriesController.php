<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow ItemCategoriesController
 * --------------------------------
 * 2035-style category section:
 *   /apps/bizflow/categories
 *   /t/{slug}/apps/bizflow/categories
 *
 * Categories live in:  biz_item_categories
 * Items (products + services) live in: biz_items
 * using item_type = 'goods' | 'service' | 'mixed' etc.
 */
final class ItemCategoriesController extends BaseController
{
    /* ---------------------------------------------------------
     * Local DB helpers (BizFlow BaseController doesn't have them)
     * --------------------------------------------------------- */

    protected function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            "SELECT 1
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = ?
              LIMIT 1"
        );
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    protected function hasCol(PDO $pdo, string $table, string $col): bool
    {
        $st = $pdo->prepare(
            "SELECT 1
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_name = ?
              LIMIT 1"
        );
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    }

    /* ---------------------------------------------------------
     * Index
     * --------------------------------------------------------- */

    /**
     * GET /apps/bizflow/categories
     * GET /t/{slug}/apps/bizflow/categories
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $search     = trim((string)($_GET['q'] ?? ''));
            $onlyActive = (($_GET['active'] ?? '') === '1');

            $categories = [];

            if ($this->hasTable($pdo, 'biz_item_categories')) {
                $where = ['c.org_id = ?'];
                $bind  = [$orgId];

                if ($search !== '') {
                    $where[] = '(c.name LIKE ? OR c.code LIKE ?)';
                    $like = '%'.$search.'%';
                    $bind[] = $like;
                    $bind[] = $like;
                }

                if ($onlyActive && $this->hasCol($pdo, 'biz_item_categories', 'is_active')) {
                    $where[] = '(c.is_active = 1)';
                }

                $whereSql = implode(' AND ', $where);

                $hasItemsTable = $this->hasTable($pdo, 'biz_items');
                $hasCatCol     = $hasItemsTable && $this->hasCol($pdo, 'biz_items', 'category_id');

                // If biz_items + category_id exist, include item_count
                if ($hasCatCol) {
                    $sql = "
                        SELECT
                            c.*,
                            COALESCE(ic.item_count, 0) AS item_count
                        FROM biz_item_categories c
                        LEFT JOIN (
                            SELECT category_id, COUNT(*) AS item_count
                              FROM biz_items
                             WHERE org_id = ?
                             GROUP BY category_id
                        ) ic ON ic.category_id = c.id
                        WHERE {$whereSql}
                        ORDER BY c.name ASC, c.id ASC
                        LIMIT 1000
                    ";

                    // First ? in subselect is org_id, then all others
                    array_unshift($bind, $orgId);
                } else {
                    $sql = "
                        SELECT c.*
                          FROM biz_item_categories c
                         WHERE {$whereSql}
                         ORDER BY c.name ASC, c.id ASC
                         LIMIT 1000
                    ";
                }

                $categories = $this->rows($sql, $bind);
            }

            $this->view('categories/index', [
                'title'       => 'Item categories',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'categories'  => $categories,
                'search'      => $search,
                'only_active' => $onlyActive,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Item categories index failed', $e);
        }
    }

    /* ---------------------------------------------------------
     * Create form
     * --------------------------------------------------------- */

    /**
     * GET /apps/bizflow/categories/create
     * GET /t/{slug}/apps/bizflow/categories/create
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c = $this->ctx($ctx ?? []);
            $this->requireOrg();

            $this->view('categories/create', [
                'title'       => 'New category',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Item categories create failed', $e);
        }
    }

    /* ---------------------------------------------------------
     * Store
     * --------------------------------------------------------- */

    /**
     * POST /apps/bizflow/categories
     * POST /t/{slug}/apps/bizflow/categories
     */
    public function store(?array $ctx = null): void
    {
        try {
            $this->postOnly();

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_item_categories')) {
                http_response_code(500);
                echo 'biz_item_categories table missing.';
                return;
            }

            $name   = trim((string)($_POST['name'] ?? ''));
            $code   = trim((string)($_POST['code'] ?? ''));
            $notes  = trim((string)($_POST['notes'] ?? ''));
            $active = isset($_POST['is_active']) && $_POST['is_active'] === '0' ? 0 : 1;

            if ($name === '') {
                http_response_code(422);
                echo 'Category name is required.';
                return;
            }

            // Build columns dynamically to avoid unknown-column crashes
            $cols = ['org_id', 'name'];
            $vals = ['?', '?'];
            $bind = [$orgId, $name];

            if ($this->hasCol($pdo, 'biz_item_categories', 'code')) {
                $cols[] = 'code';
                $vals[] = 'NULLIF(?, \'\')';
                $bind[] = $code;
            }

            if ($this->hasCol($pdo, 'biz_item_categories', 'is_active')) {
                $cols[] = 'is_active';
                $vals[] = '?';
                $bind[] = $active;
            }

            if ($notes !== '') {
                if ($this->hasCol($pdo, 'biz_item_categories', 'notes')) {
                    $cols[] = 'notes';
                    $vals[] = 'NULLIF(?, \'\')';
                    $bind[] = $notes;
                } elseif ($this->hasCol($pdo, 'biz_item_categories', 'description')) {
                    $cols[] = 'description';
                    $vals[] = 'NULLIF(?, \'\')';
                    $bind[] = $notes;
                }
            }

            $sql = "INSERT INTO biz_item_categories ("
                 . implode(',', $cols)
                 . ") VALUES ("
                 . implode(',', $vals)
                 . ")";

            $this->exec($sql, $bind);

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/') . '/categories');

        } catch (Throwable $e) {
            $this->oops('Item categories store failed', $e);
        }
    }

    /* ---------------------------------------------------------
     * Edit
     * --------------------------------------------------------- */

    /**
     * GET /apps/bizflow/categories/{id}/edit
     * GET /t/{slug}/apps/bizflow/categories/{id}/edit
     *
     * $ctx first (may be null/array) so PHP 8 doesn’t warn.
     */
    public function edit($ctx, int $id): void
    {
        try {
            $c     = $this->ctx(is_array($ctx) ? $ctx : []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_item_categories')) {
                http_response_code(500);
                echo 'biz_item_categories table missing.';
                return;
            }

            $category = $this->row(
                "SELECT *
                   FROM biz_item_categories
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$category) {
                http_response_code(404);
                echo 'Category not found.';
                return;
            }

            $this->view('categories/edit', [
                'title'       => 'Edit category',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'category'    => $category,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Item categories edit failed', $e);
        }
    }

    /* ---------------------------------------------------------
     * Update
     * --------------------------------------------------------- */

    /**
     * POST /apps/bizflow/categories/{id}
     * POST /t/{slug}/apps/bizflow/categories/{id}
     */
    public function update($ctx, int $id): void
    {
        try {
            $this->postOnly();

            $c     = $this->ctx(is_array($ctx) ? $ctx : []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_item_categories')) {
                http_response_code(500);
                echo 'biz_item_categories table missing.';
                return;
            }

            $existing = $this->row(
                "SELECT id
                   FROM biz_item_categories
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$existing) {
                http_response_code(404);
                echo 'Category not found.';
                return;
            }

            $name   = trim((string)($_POST['name'] ?? ''));
            $code   = trim((string)($_POST['code'] ?? ''));
            $notes  = trim((string)($_POST['notes'] ?? ''));
            $active = isset($_POST['is_active']) && $_POST['is_active'] === '0' ? 0 : 1;

            if ($name === '') {
                http_response_code(422);
                echo 'Category name is required.';
                return;
            }

            $sets = ['name = ?'];
            $bind = [$name];

            if ($this->hasCol($pdo, 'biz_item_categories', 'code')) {
                $sets[] = 'code = NULLIF(?, \'\')';
                $bind[] = $code;
            }

            if ($this->hasCol($pdo, 'biz_item_categories', 'is_active')) {
                $sets[] = 'is_active = ?';
                $bind[] = $active;
            }

            if ($this->hasCol($pdo, 'biz_item_categories', 'notes')) {
                $sets[] = 'notes = NULLIF(?, \'\')';
                $bind[] = $notes;
            } elseif ($this->hasCol($pdo, 'biz_item_categories', 'description')) {
                $sets[] = 'description = NULLIF(?, \'\')';
                $bind[] = $notes;
            }

            $bind[] = $orgId;
            $bind[] = $id;

            $sql = "UPDATE biz_item_categories
                       SET " . implode(', ', $sets) . "
                     WHERE org_id = ? AND id = ?
                     LIMIT 1";

            $this->exec($sql, $bind);

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/') . '/categories');

        } catch (Throwable $e) {
            $this->oops('Item categories update failed', $e);
        }
    }
}