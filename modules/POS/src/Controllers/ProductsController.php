<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class ProductsController extends BaseController
{
    /* ------------------------------------------------------------------
     * Context helper (same idea as Customers)
     * ------------------------------------------------------------------ */
    private function ctxOk(array $ctx): array
    {
        $c   = $this->ctx($ctx);
        $org = $c['org'] ?? ($_SESSION['tenant_org'] ?? []);

        if (!isset($c['org']) && $org) {
            $c['org'] = $org;
        }

        if (empty($c['org_id']) && isset($org['id'])) {
            $c['org_id'] = (int)$org['id'];
        }
        if (empty($c['slug']) && isset($org['slug'])) {
            $c['slug'] = (string)$org['slug'];
        }
        if (empty($c['module_base'])) {
            $slug = (string)($c['slug'] ?? '');
            $c['module_base'] = $slug !== ''
                ? '/t/' . rawurlencode($slug) . '/apps/pos'
                : '/apps/pos';
        }
        if (empty($c['module_dir'])) {
            // modules/POS
            $c['module_dir'] = dirname(__DIR__, 2);
        }

        return $c;
    }

    /* ------------------------------------------------------------------
     * Small helpers
     * ------------------------------------------------------------------ */

    /** cache of table → column list (lower-cased) */
    private array $colsCache = [];

    private function cols(string $table): array
    {
        if (isset($this->colsCache[$table])) {
            return $this->colsCache[$table];
        }

        $st = $this->pdo()->prepare(
            "SELECT column_name
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name   = :t"
        );
        $st->execute([':t' => $table]);

        return $this->colsCache[$table] =
            array_map('strtolower',
                array_column($st->fetchAll(PDO::FETCH_ASSOC), 'column_name')
            );
    }

    private function pickOptional(string $table, array $candidates): ?string
    {
        $cols = $this->cols($table);
        foreach ($candidates as $c) {
            if (in_array(strtolower($c), $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    private function pickRequired(string $table, array $candidates): string
    {
        $col = $this->pickOptional($table, $candidates);
        if ($col) {
            return $col;
        }
        throw new \RuntimeException(
            "Required column missing on {$table}: " . implode('|', $candidates)
        );
    }

    private function toStoreAmount(float $amount, ?string $colName)
    {
        return ($colName && str_ends_with($colName, '_cents'))
            ? (int)round($amount * 100)
            : $amount;
    }

    /* ------------------------------------------------------------------
     * Image helper
     * ------------------------------------------------------------------ */
    /**
     * Store uploaded product image and return relative path
     *   e.g. cat_3/org_12/p_SAMS-2025-12345_1700000000.jpg
     */
    private function storeProductImage(int $orgId, int $categoryId, array $file): ?string
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return null; // invalid type
        }

        // 2 MB max
        if (!empty($file['size']) && $file['size'] > 2 * 1024 * 1024) {
            return null;
        }

        $catKey = $categoryId > 0 ? $categoryId : 0;

        // Absolute base: /modules/POS/Assets/products
        $baseDir = dirname(__DIR__, 2) . '/Assets/products';
        $relDir  = 'cat_' . $catKey . '/org_' . $orgId;
        $fullDir = $baseDir . '/' . $relDir;

        if (!is_dir($fullDir) && !mkdir($fullDir, 0775, true) && !is_dir($fullDir)) {
            return null; // cannot create directory
        }

        $rawSku   = (string)($_POST['sku'] ?? 'sku');
        $safeSku  = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rawSku);
        $fileName = 'p_' . $safeSku . '_' . time() . '.' . $ext;
        $dest     = $fullDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }

        // relative path stored in DB
        return $relDir . '/' . $fileName;
    }

    /* ------------------------------------------------------------------
     * LIST
     * ------------------------------------------------------------------ */
    /** GET /products */
    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);
            $q     = trim((string)($_GET['q'] ?? ''));
            $page  = max(1, (int)($_GET['page'] ?? 1));
            $per   = 20;
            $off   = ($page - 1) * $per;

            $where = 'p.org_id = :org AND p.is_active = 1';
            $bind  = [':org' => $orgId];

            if ($q !== '') {
                $where .= ' AND (p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q)';
                $bind[':q'] = "%{$q}%";
            }

            $total = (int)($this->row(
                "SELECT COUNT(*) AS c
                   FROM pos_products p
                  WHERE {$where}",
                $bind
            )['c'] ?? 0);

            $pages = max(1, (int)ceil($total / $per));

            $rows = $this->rows(
                "SELECT
                    p.id,
                    p.sku,
                    p.barcode,
                    p.name,
                    p.sale_price  AS price_like,
                    p.cost_price  AS cost_like,
                    p.is_active   AS is_active_like,
                    c.name        AS category,
                    b.name        AS brand_name
                 FROM pos_products p
                 LEFT JOIN pos_categories c
                        ON c.id = p.category_id
                       AND c.org_id = p.org_id
                 LEFT JOIN pos_brands b
                        ON b.id = p.brand_id
                       AND b.org_id = p.org_id
                 WHERE {$where}
                 ORDER BY p.id DESC
                 LIMIT {$per} OFFSET {$off}",
                $bind
            );

            $this->view($c['module_dir'] . '/Views/products/index.php', [
                'title' => 'Products',
                'base'  => $c['module_base'],
                'q'     => $q,
                'rows'  => $rows,
                'page'  => $page,
                'pages' => $pages,
                'total' => $total,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Products index failed', $e);
        }
    }

    /* ------------------------------------------------------------------
     * CREATE FORM
     * ------------------------------------------------------------------ */
    /** GET /products/create */
    public function create(array $ctx = []): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $categories = $this->rows(
                "SELECT id,name FROM pos_categories
                  WHERE org_id=:o AND is_active=1
                  ORDER BY name",
                [':o' => $orgId]
            );
            $brands     = $this->rows(
                "SELECT id,name FROM pos_brands
                  WHERE org_id=:o AND is_active=1
                  ORDER BY name",
                [':o' => $orgId]
            );
            $uoms       = $this->rows(
                "SELECT id,name,code FROM pos_uoms
                  WHERE org_id=:o AND is_active=1
                  ORDER BY name",
                [':o' => $orgId]
            );
            $taxes      = $this->rows(
                "SELECT id,name,rate FROM pos_taxes
                  WHERE org_id=:o AND is_active=1
                  ORDER BY name",
                [':o' => $orgId]
            );
            $suppliers  = $this->rows(
                "SELECT id,name FROM pos_suppliers
                  WHERE org_id=:o AND is_active=1
                  ORDER BY name",
                [':o' => $orgId]
            );

            $this->view($c['module_dir'] . '/Views/products/create.php', [
                'title'      => 'New Product',
                'base'       => $c['module_base'],
                'categories' => $categories,
                'brands'     => $brands,
                'uoms'       => $uoms,
                'taxes'      => $taxes,
                'suppliers'  => $suppliers,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Products create failed', $e);
        }
    }

    /* ------------------------------------------------------------------
     * STORE
     * ------------------------------------------------------------------ */
       
    /** POST /products */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $in = fn($k,$d='') => trim((string)($_POST[$k] ?? $d));

            $sku     = $in('sku');
            $name    = $in('name');
            $cat     = (int)($_POST['category_id'] ?? 0);
            $brand   = (int)($_POST['brand_id'] ?? 0);
            $bar     = $in('barcode');
            $unit    = $in('unit','pcs');

            $taxIn   = (float)($_POST['tax_rate'] ?? 0);
            $costIn  = (float)($_POST['cost_price'] ?? ($_POST['purchase_price'] ?? 0));
            $priceIn = (float)($_POST['sale_price'] ?? ($_POST['price'] ?? 0));

            $active  = isset($_POST['is_active']) ? 1 : 1;   // default ACTIVE=1
            $track   = isset($_POST['track_stock']) ? 1 : 0;
            $lowTh   = (int)($_POST['low_stock_threshold'] ?? 0);

            $primarySupplier = (int)($_POST['primary_supplier_id'] ?? 0);
            $supplier        = (int)($_POST['supplier_id'] ?? 0);

            // basic validation
            $errors = [];
            if ($sku === '')  $errors['sku']        = 'SKU is required';
            if ($name === '') $errors['name']       = 'Name is required';
            if ($priceIn < 0) $errors['sale_price'] = 'Price cannot be negative';

            if ($errors) {
                $_SESSION['pos_errors'] = $errors;
                $_SESSION['pos_old']    = $_POST;
                $this->redirect($c['module_base'].'/products/create');
            }

            // ensure SKU unique within org
            $exists = $this->row(
                "SELECT id FROM pos_products WHERE org_id=:o AND sku=:s LIMIT 1",
                [':o'=>$orgId, ':s'=>$sku]
            );
            if ($exists) {
                $_SESSION['pos_errors'] = ['sku'=>'SKU already exists'];
                $_SESSION['pos_old']    = $_POST;
                $this->redirect($c['module_base'].'/products/create');
            }

            // optional image column
            $imgCol = $this->pickOptional('pos_products', ['image_path','image','photo','picture']);
            $imgRelPath = null;
            if ($imgCol && !empty($_FILES['image']) && is_array($_FILES['image'])) {
                $imgRelPath = $this->storeProductImage($orgId, $cat, $_FILES['image']);
            }

            // build INSERT explicitly for your schema
            $cols = [
                'org_id',
                'category_id',
                'brand_id',
                'primary_supplier_id',
                'supplier_id',
                'sku',
                'barcode',
                'name',
                'unit',
                'tax_rate',
                'cost_price',
                'sale_price',
                'low_stock_threshold',
                'track_stock',
                'is_active',
                'created_at',
                'updated_at',
            ];
            $vals = [
                ':org',
                ':cat',
                ':brand',
                ':psup',
                ':sup',
                ':sku',
                ':barcode',
                ':name',
                ':unit',
                ':tax',
                ':cost',
                ':price',
                ':low',
                ':track',
                ':active',
                'NOW()',
                'NOW()',
            ];
            $args = [
                ':org'     => $orgId,
                ':cat'     => $cat ?: null,
                ':brand'   => $brand ?: null,
                ':psup'    => $primarySupplier ?: null,
                ':sup'     => $supplier ?: null,
                ':sku'     => $sku,
                ':barcode' => $bar ?: null,
                ':name'    => $name,
                ':unit'    => $unit,
                ':tax'     => $taxIn,
                ':cost'    => $costIn,
                ':price'   => $priceIn,
                ':low'     => $lowTh,
                ':track'   => $track,
                ':active'  => $active,
            ];

            if ($imgCol && $imgRelPath) {
                $cols[] = $imgCol;
                $vals[] = ':img';
                $args[':img'] = $imgRelPath;
            }

            $colList = implode(', ', array_map(fn($c)=>"`{$c}`",$cols));
            $valList = implode(', ', $vals);

            $sql = "INSERT INTO pos_products ({$colList}) VALUES ({$valList})";
            $this->pdo()->prepare($sql)->execute($args);

            $this->redirect($c['module_base'].'/products');
        } catch (Throwable $e) {
            $this->oops('Products store failed', $e);
        }
    }

    /* ------------------------------------------------------------------
     * EDIT FORM
     * ------------------------------------------------------------------ */
    /** GET /products/{id}/edit */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);
            $id    = (int)$id;

            $prod = $this->row(
                "SELECT * FROM pos_products
                  WHERE org_id=:o AND id=:id",
                [':o' => $orgId, ':id' => $id]
            );
            if (!$prod) {
                http_response_code(404);
                echo 'Not found';
                return;
            }

            $categories = $this->rows("SELECT id,name FROM pos_categories WHERE org_id=:o ORDER BY name", [':o' => $orgId]);
            $brands     = $this->rows("SELECT id,name FROM pos_brands     WHERE org_id=:o ORDER BY name", [':o' => $orgId]);
            $uoms       = $this->rows("SELECT id,name,code FROM pos_uoms  WHERE org_id=:o ORDER BY name", [':o' => $orgId]);
            $taxes      = $this->rows("SELECT id,name,rate FROM pos_taxes WHERE org_id=:o ORDER BY name", [':o' => $orgId]);
            $suppliers  = $this->rows("SELECT id,name FROM pos_suppliers  WHERE org_id=:o ORDER BY name", [':o' => $orgId]);

            $this->view($c['module_dir'] . '/Views/products/edit.php', [
                'title'      => 'Edit Product',
                'base'       => $c['module_base'],
                'prod'       => $prod,
                'categories' => $categories,
                'brands'     => $brands,
                'uoms'       => $uoms,
                'taxes'      => $taxes,
                'suppliers'  => $suppliers,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Products edit failed', $e);
        }
    }

    /* ------------------------------------------------------------------
     * UPDATE
     * ------------------------------------------------------------------ */
  
    /** POST /products/{id} */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);
            $id    = (int)$id;

            $in    = fn($k,$d='') => trim((string)($_POST[$k] ?? $d));

            $sku   = $in('sku');
            $name  = $in('name');
            $cat   = (int)($_POST['category_id'] ?? 0);
            $brand = (int)($_POST['brand_id'] ?? 0);
            $bar   = $in('barcode');
            $unit  = $in('unit','pcs');

            $tax   = (float)($_POST['tax_rate'] ?? 0);
            $cost  = (float)($_POST['cost_price'] ?? 0);
            $price = (float)($_POST['sale_price'] ?? 0);

            $active= isset($_POST['is_active']) ? 1 : 0;
            $track = isset($_POST['track_stock']) ? 1 : 0;
            $lowTh = (int)($_POST['low_stock_threshold'] ?? 0);

            $primarySupplier = (int)($_POST['primary_supplier_id'] ?? 0);
            $supplier        = (int)($_POST['supplier_id'] ?? 0);

            $errors = [];
            if ($sku==='')  $errors['sku']='SKU is required';
            if ($name==='') $errors['name']='Name is required';
            if ($price<0)   $errors['sale_price']='Price cannot be negative';

            if ($errors) {
                $_SESSION['pos_errors'] = $errors;
                $_SESSION['pos_old']    = $_POST;
                $this->redirect($c['module_base'].'/products/'.$id.'/edit');
            }

            // ensure unique SKU within org
            $exists = $this->row(
                "SELECT id FROM pos_products WHERE org_id=:o AND sku=:s AND id<>:id LIMIT 1",
                [':o'=>$orgId, ':s'=>$sku, ':id'=>$id]
            );
            if ($exists) {
                $_SESSION['pos_errors'] = ['sku'=>'SKU already exists'];
                $_SESSION['pos_old']    = $_POST;
                $this->redirect($c['module_base'].'/products/'.$id.'/edit');
            }

            // optional image column
            $imgCol = $this->pickOptional('pos_products', ['image_path','image','photo','picture']);
            $imgRelPath = null;
            if ($imgCol && !empty($_FILES['image']) && is_array($_FILES['image'])) {
                $imgRelPath = $this->storeProductImage($orgId, $cat, $_FILES['image']);
            }

            $set = [
                'category_id          = :cat',
                'brand_id             = :brand',
                'primary_supplier_id  = :psup',
                'supplier_id          = :sup',
                'sku                  = :sku',
                'barcode              = :barcode',
                'name                 = :name',
                'unit                 = :unit',
                'tax_rate             = :tax',
                'cost_price           = :cost',
                'sale_price           = :price',
                'low_stock_threshold  = :low',
                'track_stock          = :track',
                'is_active            = :active',
                'updated_at           = NOW()',
            ];

            $args = [
                ':cat'    => $cat ?: null,
                ':brand'  => $brand ?: null,
                ':psup'   => $primarySupplier ?: null,
                ':sup'    => $supplier ?: null,
                ':sku'    => $sku,
                ':barcode'=> $bar ?: null,
                ':name'   => $name,
                ':unit'   => $unit,
                ':tax'    => $tax,
                ':cost'   => $cost,
                ':price'  => $price,
                ':low'    => $lowTh,
                ':track'  => $track,
                ':active' => $active,
                ':o'      => $orgId,
                ':id'     => $id,
            ];

            if ($imgCol && $imgRelPath) {
                $set[] = "`{$imgCol}` = :img";
                $args[':img'] = $imgRelPath;
            }

            $sql = "UPDATE pos_products
                    SET ".implode(', ', $set)."
                    WHERE org_id = :o AND id = :id";

            $this->pdo()->prepare($sql)->execute($args);

            $this->redirect($c['module_base'].'/products/'.$id);
        } catch (Throwable $e) {
            $this->oops('Products update failed', $e);
        }
    }

    /* ------------------------------------------------------------------
     * EXPORT
     * ------------------------------------------------------------------ */
    public function export(array $ctx = []): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            if ($orgId <= 0) {
                http_response_code(400);
                echo 'Missing org id';
                return;
            }

            $format = strtolower((string)($_GET['format'] ?? 'csv'));
            if (!in_array($format, ['csv', 'xlsx'], true)) {
                $format = 'csv';
            }

            $rows = $this->rows(
                "SELECT
                    p.id,
                    p.name,
                    p.sku,
                    p.sale_price,
                    p.cost_price,
                    p.is_active,
                    c.name AS category,
                    b.name AS brand
                 FROM pos_products p
                 LEFT JOIN pos_categories c
                        ON c.id = p.category_id AND c.org_id = p.org_id
                 LEFT JOIN pos_brands b
                        ON b.id = p.brand_id     AND b.org_id = p.org_id
                 WHERE p.org_id = :org
                 ORDER BY p.id DESC",
                [':org' => $orgId]
            );

            $filename = 'products-' . date('Ymd-His') . '.csv';

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'ID',
                'Name',
                'SKU',
                'Category',
                'Brand',
                'Sale Price',
                'Cost Price',
                'Active',
            ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    (int)($r['id'] ?? 0),
                    (string)($r['name'] ?? ''),
                    (string)($r['sku'] ?? ''),
                    (string)($r['category'] ?? ''),
                    (string)($r['brand'] ?? ''),
                    (string)($r['sale_price'] ?? '0'),
                    (string)($r['cost_price'] ?? '0'),
                    ((int)($r['is_active'] ?? 1) === 1 ? 'Yes' : 'No'),
                ]);
            }

            fclose($out);
            exit;
        } catch (Throwable $e) {
            $this->oops('Products export failed', $e);
        }
    }

    /* ------------------------------------------------------------------
     * SHOW
     * ------------------------------------------------------------------ */
    /** GET /products/{id} */
    public function showOne(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $prod = $this->row(
                "SELECT p.*, c.name AS category_name, b.name AS brand_name
                   FROM pos_products p
              LEFT JOIN pos_categories c
                         ON c.id=p.category_id AND c.org_id=p.org_id
              LEFT JOIN pos_brands     b
                         ON b.id=p.brand_id   AND b.org_id=p.org_id
                  WHERE p.org_id=:o AND p.id=:id",
                [':o' => $orgId, ':id' => (int)$id]
            );
            if (!$prod) {
                http_response_code(404);
                echo 'Not found';
                return;
            }

            $this->view($c['module_dir'] . '/Views/products/show.php', [
                'title' => 'Product Details',
                'base'  => $c['module_base'],
                'prod'  => $prod,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Products show failed', $e);
        }
    }
}