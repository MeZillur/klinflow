<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class SuppliersController extends BaseController
{
    /**
     * Optional columns (excluding timestamps – we handle those separately)
     */
    private const ALLOW_COLS = [
        'code','contact','phone','email','address','city','state','country','postal_code',
        'tax_no','is_active','notes','address_line1','address_line2',
    ];

    /* ============================ Infra ============================ */

    /** create pos_suppliers only if it doesn’t exist (minimal, compatible) */
    private function ensureSuppliersTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_suppliers (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id       BIGINT UNSIGNED NOT NULL,
              code         VARCHAR(64)  NULL,
              name         VARCHAR(200) NOT NULL,
              phone        VARCHAR(40)  NULL,
              email        VARCHAR(140) NULL,
              address      VARCHAR(200) NULL,
              address_line1 VARCHAR(200) NULL,
              address_line2 VARCHAR(200) NULL,
              city         VARCHAR(120) NULL,
              state        VARCHAR(120) NULL,
              postal_code  VARCHAR(24)  NULL,
              country      VARCHAR(120) NULL,
              is_active    TINYINT(1) NOT NULL DEFAULT 1,
              created_at   TIMESTAMP NULL,
              updated_at   TIMESTAMP NULL,
              created_by   BIGINT UNSIGNED NULL,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_pos_suppliers_org_name (org_id, name),
              KEY idx_pos_suppliers_org_phone (org_id, phone),
              UNIQUE KEY uq_pos_suppliers_org_code (org_id, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /** normalize one row for views/json */
    private function norm(array $r): array
    {
        return [
            'id'           => (int)($r['id'] ?? 0),
            'name'         => (string)($r['name'] ?? ''),
            'code'         => $r['code'] ?? null,
            'contact'      => $r['contact'] ?? null,
            'phone'        => $r['phone'] ?? null,
            'email'        => $r['email'] ?? null,
            'address'      => $r['address'] ?? ($r['address_line1'] ?? null),
            'address_line1'=> $r['address_line1'] ?? null,
            'address_line2'=> $r['address_line2'] ?? null,
            'city'         => $r['city'] ?? null,
            'state'        => $r['state'] ?? null,
            'country'      => $r['country'] ?? null,
            'postal_code'  => $r['postal_code'] ?? null,
            'tax_no'       => $r['tax_no'] ?? null,
            'notes'        => $r['notes'] ?? null,
            'is_active'    => isset($r['is_active']) ? (int)$r['is_active'] : 1,
            'created_at'   => $r['created_at'] ?? null,
            'updated_at'   => $r['updated_at'] ?? null,
        ];
    }

    /* ============================ Screens ============================ */

    // GET /suppliers
    public function index(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSuppliersTable($pdo);

            $org = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $q   = trim((string)($_GET['q'] ?? ''));

            // figure out which optional columns exist for this table
            $haveCols = [];
            $checkCols = array_merge(
                self::ALLOW_COLS,
                ['created_at','updated_at']
            );
            foreach ($checkCols as $col) {
                if ($this->hasCol($pdo, 'pos_suppliers', $col)) {
                    $haveCols[] = $col;
                }
            }

            // projection based on existing columns
            $cols = ['id','name'];
            foreach (['code','contact','phone','email','is_active','created_at'] as $k) {
                if (in_array($k, $haveCols, true)) {
                    $cols[] = $k;
                }
            }
            $sel = implode(',', array_map(fn($k)=>"`$k`", $cols));

            $sql  = "SELECT {$sel} FROM pos_suppliers WHERE org_id=:o";
            $bind = [':o'=>$org];

            if ($q !== '') {
                $likeCols = ['name'];
                foreach (['code','contact','phone','email'] as $k) {
                    if (in_array($k, $haveCols, true)) $likeCols[] = $k;
                }
                $likes = implode(' OR ', array_map(fn($k)=>"$k LIKE :q", $likeCols));
                $sql  .= " AND ({$likes})";
                $bind[':q'] = "%{$q}%";
            }
            $sql .= " ORDER BY name";

            $rows  = $this->rows($sql, $bind);
            $items = array_map([$this,'norm'], $rows);

            $this->view($c['module_dir'].'/Views/suppliers/index.php', [
                'title'    => 'Suppliers',
                'items'    => $items,
                'q'        => $q,
                'haveCols' => $haveCols,
                'base'     => $c['module_base'],
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Suppliers index failed', $e);
        }
    }

    // GET /suppliers/api/list
    public function apiList(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSuppliersTable($pdo);

            $org  = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $cols = ['id','name'];
            foreach (['code','contact','phone','email','is_active'] as $k) {
                if ($this->hasCol($pdo,'pos_suppliers',$k)) $cols[] = $k;
            }
            $sel  = implode(',', array_map(fn($k)=>"`$k`", $cols));
            $rows = $this->rows(
                "SELECT {$sel} FROM pos_suppliers WHERE org_id=:o ORDER BY name",
                [':o'=>$org]
            );

            $this->json(array_map([$this,'norm'], $rows));
        } catch (Throwable $e) {
            $this->json(['ok'=>false,'error'=>'suppliers list failed'], 500);
        }
    }

    // GET /suppliers/create
    public function create(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSuppliersTable($pdo);

            // same detection logic as index
            $haveCols = [];
            $checkCols = array_merge(self::ALLOW_COLS, ['created_at','updated_at']);
            foreach ($checkCols as $col) {
                if ($this->hasCol($pdo, 'pos_suppliers', $col)) {
                    $haveCols[] = $col;
                }
            }

            $this->view($c['module_dir'].'/Views/suppliers/create.php', [
                'title'    => 'New Supplier',
                'base'     => $c['module_base'],
                'haveCols' => $haveCols,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Supplier create form failed', $e);
        }
    }

    // POST /suppliers
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSuppliersTable($pdo);

            $org  = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new \RuntimeException('Name is required');

            $fields = ['org_id'=>$org, 'name'=>$name];

            foreach (self::ALLOW_COLS as $k) {
                if (!$this->hasCol($pdo,'pos_suppliers',$k)) continue;

                if ($k === 'is_active') {
                    $fields[$k] = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 1;
                } else {
                    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : null;
                    $fields[$k] = ($v === '') ? null : $v;
                }
            }

            // timestamps: if columns exist, set them explicitly so NOT NULL is satisfied
            $now = date('Y-m-d H:i:s');
            if ($this->hasCol($pdo,'pos_suppliers','created_at')) {
                $fields['created_at'] = $now;
            }
            if ($this->hasCol($pdo,'pos_suppliers','updated_at')) {
                $fields['updated_at'] = $now;
            }

            $cols  = array_keys($fields);
            $place = array_map(fn($k)=>":$k", $cols);
            $sql   = "INSERT INTO pos_suppliers (".implode(',',$cols).") VALUES (".implode(',',$place).")";
            $this->exec($sql, $fields);

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                http_response_code(204);
                return;
            }
            $this->redirect($c['module_base'].'/suppliers');
        } catch (Throwable $e) {
            $this->oops('Supplier create failed', $e);
        }
    }

    // GET /suppliers/{id}/edit
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSuppliersTable($pdo);

            $org  = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $cols = ['id','name'];
            foreach (array_merge(self::ALLOW_COLS, ['created_at','updated_at']) as $k) {
                if ($this->hasCol($pdo,'pos_suppliers',$k)) $cols[] = $k;
            }
            $sel = implode(',', array_map(fn($k)=>"`$k`", $cols));

            $row = $this->row(
                "SELECT {$sel} FROM pos_suppliers WHERE org_id=:o AND id=:i",
                [':o'=>$org,':i'=>$id]
            );
            if (!$row) {
                http_response_code(404);
                echo 'Supplier not found';
                return;
            }

            $this->view($c['module_dir'].'/Views/suppliers/edit.php', [
                'title'=>'Edit Supplier',
                'sup'  => $this->norm($row),
                'base' => $c['module_base'],
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Supplier edit failed', $e);
        }
    }

    // POST /suppliers/{id}
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSuppliersTable($pdo);

            $org  = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new \RuntimeException('Name is required');

            $sets = ['name = :name'];
            $bind = [':name'=>$name, ':o'=>$org, ':i'=>$id];

            foreach (self::ALLOW_COLS as $k) {
                if (!$this->hasCol($pdo,'pos_suppliers',$k)) continue;
                $sets[] = "$k = :$k";
                if ($k === 'is_active') {
                    $bind[":$k"] = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0;
                } else {
                    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : null;
                    $bind[":$k"] = ($v === '') ? null : $v;
                }
            }

            if ($this->hasCol($pdo,'pos_suppliers','updated_at')) {
                $sets[] = "updated_at = NOW()";
            }

            $sql = "UPDATE pos_suppliers SET ".implode(', ',$sets)."
                     WHERE org_id=:o AND id=:i";
            $this->exec($sql, $bind);

            $this->redirect($c['module_base'].'/suppliers');
        } catch (Throwable $e) {
            $this->oops('Supplier update failed', $e);
        }
    }

    // POST /suppliers/{id}/delete
    public function destroy(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            $c   = $this->ctx($ctx);
            $org = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $this->exec(
                "DELETE FROM pos_suppliers WHERE org_id=:o AND id=:i",
                [':o'=>$org, ':i'=>$id]
            );

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                http_response_code(204);
                return;
            }
            $this->redirect($c['module_base'].'/suppliers');
        } catch (Throwable $e) {
            $this->oops('Supplier delete failed', $e);
        }
    }

    /* ============================ API: Metrics ============================ */

    // GET /suppliers/api/metrics?ids=1,2,3
    public function apiMetrics(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $org = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);

            // Parse supplier ids
            $ids = array_values(array_filter(
                array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))
            ));
            if (!$ids) {
                $this->json([]);
                return;
            }

            // Decide which supplier column to use on pos_products
            $col = null;
            if ($this->hasCol($pdo,'pos_products','primary_supplier_id')) $col = 'primary_supplier_id';
            elseif ($this->hasCol($pdo,'pos_products','supplier_id'))    $col = 'supplier_id';
            elseif ($this->hasCol($pdo,'pos_products','vendor_id'))      $col = 'vendor_id';

            if ($col === null) {
                $this->json(array_fill_keys($ids, ['products'=>0,'sold_30d'=>0,'stock'=>0]));
                return;
            }

            // Build "IN" placeholders
            $in   = implode(',', array_fill(0, count($ids), '?'));
            $bind = array_merge([$org], $ids); // [ org, ...ids ]

            /* 1) product count by supplier */
            $products = [];
            $sql = "SELECT {$col} AS sid, COUNT(*) AS n
                      FROM pos_products
                     WHERE org_id = ? AND {$col} IN ($in)
                     GROUP BY {$col}";
            foreach ($this->rows($sql, $bind) as $r) {
                $products[(int)$r['sid']] = (int)$r['n'];
            }

            /* 2) sold qty (30d) per supplier — only if sale_items & product_id exist */
            $sold = [];
            if (
                $this->hasCol($pdo,'pos_sale_items','product_id') &&
                $this->hasCol($pdo,'pos_sale_items','qty') &&
                $this->hasCol($pdo,'pos_sale_items','created_at')
            ) {
                $sql = "SELECT p.{$col} AS sid, SUM(si.qty) AS q
                          FROM pos_sale_items si
                          JOIN pos_products p
                            ON p.id = si.product_id
                           AND p.org_id = si.org_id
                         WHERE si.org_id = ?
                           AND p.{$col} IN ($in)
                           AND si.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                         GROUP BY p.{$col}";
                foreach ($this->rows($sql, $bind) as $r) {
                    $sold[(int)$r['sid']] = (float)$r['q'];
                }
            }

            /* 3) stock on hand per supplier (stock_on_hand or stock) */
            $stock = [];
            $stockCol = $this->hasCol($pdo,'pos_products','stock_on_hand')
                       ? 'stock_on_hand'
                       : ($this->hasCol($pdo,'pos_products','stock') ? 'stock' : null);
            if ($stockCol) {
                $sql = "SELECT {$col} AS sid, SUM(COALESCE({$stockCol},0)) AS s
                          FROM pos_products
                         WHERE org_id = ? AND {$col} IN ($in)
                         GROUP BY {$col}";
                foreach ($this->rows($sql, $bind) as $r) {
                    $stock[(int)$r['sid']] = (float)$r['s'];
                }
            }

            // Combine per id in the same order requested
            $out = [];
            foreach ($ids as $id) {
                $out[$id] = [
                    'products' => $products[$id] ?? 0,
                    'sold_30d' => $sold[$id] ?? 0,
                    'stock'    => $stock[$id] ?? 0,
                ];
            }
            $this->json($out);
        } catch (Throwable $e) {
            // fail-soft (don’t break the page)
            $this->json([], 200);
        }
    }
}