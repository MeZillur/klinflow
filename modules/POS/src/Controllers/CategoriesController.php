<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use Throwable;
use PDO;

final class CategoriesController extends BaseController
{
    /* -------------------------------------------------------------
     * org / context helpers
     * ------------------------------------------------------------- */
    private function orgId(array $c): int
    {
        $id = (int)($c['org_id'] ?? $c['orgId'] ?? 0);
        if ($id > 0) return $id;
        if (!empty($c['org']['id'])) return (int)$c['org']['id'];
        if (!empty($_SESSION['org_id'])) return (int)$_SESSION['org_id'];

        $slug = (string)($c['slug'] ?? $_SESSION['tenant_slug'] ?? '');
        if ($slug) {
            $row = $this->row("SELECT id FROM orgs WHERE slug=:s LIMIT 1", [':s' => $slug]);
            if (!empty($row['id'])) return (int)$row['id'];
        }
        // last-resort fallback; ideally never hit
        return 7;
    }

    /* -------------------------------------------------------------
     * Auto ensure DB table
     * ------------------------------------------------------------- */
    private function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_categories (
              id INT AUTO_INCREMENT PRIMARY KEY,
              org_id INT NOT NULL,
              parent_id INT NULL,
              name VARCHAR(255) NOT NULL,
              code VARCHAR(50) NULL,
              is_active TINYINT(1) DEFAULT 1,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NULL,
              INDEX(org_id),
              INDEX(parent_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /* -------------------------------------------------------------
     * Generate code (AAA-PPP-####)
     * ------------------------------------------------------------- */
    private function genCode(PDO $pdo, int $orgId, string $name, ?string $parentName): string
    {
        $cat3 = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) ?: 'XXX';
        $par3 = $parentName
            ? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $parentName), 0, 3))
            : 'GEN';

        $prefix = "{$cat3}-{$par3}-";

        $row = $this->row(
            "SELECT code FROM pos_categories
             WHERE org_id = :o AND code LIKE :p
             ORDER BY code DESC LIMIT 1",
            [':o' => $orgId, ':p' => $prefix.'%']
        );

        $seq = 1;
        if ($row && preg_match('/(\d{4})$/', $row['code'], $m)) {
            $seq = ((int)$m[1]) + 1;
        }

        return $prefix . sprintf('%04d', $seq);
    }

    /* -------------------------------------------------------------
     * Index
     * ------------------------------------------------------------- */
    public function index(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $org  = $this->orgId($c);
            $q    = trim((string)($_GET['q'] ?? ''));
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per  = 20;
            $off  = ($page - 1) * $per;

            $where = 'org_id = :o';
            $bind  = [':o' => $org];

            if ($q !== '') {
                $where .= " AND (name LIKE :q OR code LIKE :q)";
                $bind[':q'] = "%{$q}%";
            }

            $totalRow = $this->row(
                "SELECT COUNT(*) AS c FROM pos_categories WHERE {$where}",
                $bind
            );
            $total = (int)($totalRow['c'] ?? 0);
            $pages = max(1, (int)ceil($total / $per));

            $rows = $this->rows("
                SELECT id, name, code, parent_id, is_active
                FROM pos_categories
                WHERE {$where}
                ORDER BY name ASC
                LIMIT {$per} OFFSET {$off}
            ", $bind);

            $this->view(
                $c['module_dir'].'/Views/categories/index.php',
                [
                    'title' => 'Categories',
                    'base'  => $c['module_base'],
                    'ctx'   => $c,
                    'rows'  => $rows,
                    'page'  => $page,
                    'pages' => $pages,
                    'total' => $total,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Categories index failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Create form
     * ------------------------------------------------------------- */
    public function create(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);
            $org = $this->orgId($c);

            $parents = $this->rows(
                "SELECT id, name
                 FROM pos_categories
                 WHERE org_id = :o
                 ORDER BY name",
                [':o' => $org]
            );

            $this->view(
                $c['module_dir'].'/Views/categories/create.php',
                [
                    'title'   => 'New Category',
                    'base'    => $c['module_base'],
                    'ctx'     => $c,
                    'parents' => $parents,
                    'hasParent' => true,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Categories create failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Store (POST)
     * ------------------------------------------------------------- */
    public function store(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);
            $org = $this->orgId($c);

            $name     = trim((string)($_POST['name'] ?? ''));
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $active   = isset($_POST['is_active']) ? 1 : 1;

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'Name is required';
            }

            if ($errors) {
                $_SESSION['pos_errors'] = $errors;
                $_SESSION['pos_old']    = $_POST;
                header('Location: '.$c['module_base'].'/categories/create');
                return;
            }

            $parentName = '';
            if ($parentId > 0) {
                $row = $this->row(
                    "SELECT name FROM pos_categories WHERE id = :id AND org_id = :o",
                    [':id' => $parentId, ':o' => $org]
                );
                $parentName = $row['name'] ?? '';
            }

            $code = $this->genCode($pdo, $org, $name, $parentName);

            $pdo->prepare("
                INSERT INTO pos_categories (org_id, parent_id, name, code, is_active, created_at)
                VALUES (:o, :pid, :n, :c, :a, NOW())
            ")->execute([
                ':o'   => $org,
                ':pid' => $parentId ?: null,
                ':n'   => $name,
                ':c'   => $code,
                ':a'   => $active,
            ]);

            header('Location: '.$c['module_base'].'/categories');
        } catch (Throwable $e) {
            $this->oops('Categories store failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Edit form
     * ------------------------------------------------------------- */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);
            $org = $this->orgId($c);

            $cat = $this->row(
                "SELECT * FROM pos_categories WHERE id = :id AND org_id = :o",
                [':id' => $id, ':o' => $org]
            );
            if (!$cat) {
                http_response_code(404);
                echo 'Not found';
                return;
            }

            $parents = $this->rows(
                "SELECT id, name
                 FROM pos_categories
                 WHERE org_id = :o AND id <> :id
                 ORDER BY name",
                [':o' => $org, ':id' => $id]
            );

            $parentName = '';
            if (!empty($cat['parent_id'])) {
                $p = $this->row(
                    "SELECT name FROM pos_categories WHERE id = :pid",
                    [':pid' => $cat['parent_id']]
                );
                $parentName = $p['name'] ?? '';
            }
            $cat['parent_name'] = $parentName;

            $this->view(
                $c['module_dir'].'/Views/categories/edit.php',
                [
                    'title'    => 'Edit Category',
                    'base'     => $c['module_base'],
                    'ctx'      => $c,
                    'cat'      => $cat,
                    'parents'  => $parents,
                    'hasParent'=> true,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Categories edit failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Update
     * ------------------------------------------------------------- */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);
            $org = $this->orgId($c);

            $name     = trim((string)($_POST['name'] ?? ''));
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $active   = isset($_POST['is_active']) ? 1 : 0;

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'Name is required';
            }

            if ($errors) {
                $_SESSION['pos_errors'] = $errors;
                $_SESSION['pos_old']    = $_POST;
                header('Location: '.$c['module_base']."/categories/{$id}/edit");
                return;
            }

            $pdo->prepare("
                UPDATE pos_categories
                SET name = :n,
                    parent_id = :pid,
                    is_active = :a,
                    updated_at = NOW()
                WHERE org_id = :o AND id = :id
            ")->execute([
                ':n'   => $name,
                ':pid' => $parentId ?: null,
                ':a'   => $active,
                ':o'   => $org,
                ':id'  => $id,
            ]);

            header('Location: '.$c['module_base'].'/categories');
        } catch (Throwable $e) {
            $this->oops('Categories update failed', $e);
        }
    }
}