<?php
declare(strict_types=1);

namespace Modules\POS\Support;

use Shared\DB;
use PDO;

final class Installer
{
    public static function run(): void
    {
        $pdo = method_exists(DB::class, 'tenant') ? DB::tenant() : DB::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1) products --------------------------------------------------------
        self::createProducts($pdo);
        self::ensureColumn($pdo, 'pos_products', 'sku',     "VARCHAR(64) DEFAULT NULL");
        self::ensureColumn($pdo, 'pos_products', 'name',    "VARCHAR(160) NOT NULL");
        self::ensureColumn($pdo, 'pos_products', 'unit',     "VARCHAR(32) DEFAULT NULL");   // <— add this line
        self::ensureColumn($pdo, 'pos_products', 'category',"VARCHAR(120) DEFAULT NULL");
        self::ensureColumn($pdo, 'pos_products', 'price',   "DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        self::ensureColumn($pdo, 'pos_products', 'cost',    "DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        self::ensureColumn($pdo, 'pos_products', 'stock',   "DECIMAL(12,3) NOT NULL DEFAULT 0.000");

        self::ensureIndex($pdo, 'pos_products', 'idx_org',  "KEY idx_org (org_id)");
        self::ensureIndex($pdo, 'pos_products', 'idx_name', "KEY idx_name (name)");

        // 2) customers (baseline) -------------------------------------------
        self::createCustomers($pdo);

        // 3) sales + sale_items + cash tables (baseline for dashboard) ------
        self::createSales($pdo);
        self::createSaleItems($pdo);
        self::createCashShifts($pdo);
        self::createCashEntries($pdo);
        self::createCategories($pdo);
        self::ensureProductStockColumns($pdo);
        self::createStockMovements($pdo);
    }

    /* ------------------------ helpers ------------------------ */

/** Check if a table exists in the current database */
public static function tableExists(PDO $pdo, string $table): bool
{
    $q = $pdo->prepare(
        "SELECT 1
           FROM information_schema.tables
          WHERE table_schema = DATABASE()
            AND table_name   = ?
          LIMIT 1"
    );
    $q->execute([$table]);
    return (bool) $q->fetchColumn();
}

/** Check if ALL given tables exist (convenience for controllers) */
public static function tablesExist(PDO $pdo, array $tables): bool
{
    foreach ($tables as $t) {
        if (!self::tableExists($pdo, $t)) return false;
    }
    return true;
}

/** Check if a column exists on a table */
private static function columnExists(PDO $pdo, string $table, string $col): bool
{
    $q = $pdo->prepare(
        "SELECT 1
           FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name   = ?
            AND column_name  = ?
          LIMIT 1"
    );
    $q->execute([$table, $col]);
    return (bool) $q->fetchColumn();
}

/** Check if an index exists on a table (by index name) */
private static function indexExists(PDO $pdo, string $table, string $index): bool
{
    $q = $pdo->prepare(
        "SELECT 1
           FROM information_schema.statistics
          WHERE table_schema = DATABASE()
            AND table_name   = ?
            AND index_name   = ?
          LIMIT 1"
    );
    $q->execute([$table, $index]);
    return (bool) $q->fetchColumn();
}

/** Add a column if it does not exist (definition is raw SQL fragment) */
private static function ensureColumn(PDO $pdo, string $table, string $col, string $definition): void
{
    if (!self::columnExists($pdo, $table, $col)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
    }
}

/** Add an index if it does not exist (definition is e.g. "INDEX idx_x (col)", "UNIQUE KEY uk_y (col)") */
private static function ensureIndex(PDO $pdo, string $table, string $index, string $definition): void
{
    if (!self::indexExists($pdo, $table, $index)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD {$definition}");
    }
}

private static function ensureProductStockColumns(\PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'pos_products', 'stock')) {
            $pdo->exec("ALTER TABLE pos_products ADD COLUMN stock DECIMAL(12,3) NOT NULL DEFAULT 0.000");
        }
        if (!self::columnExists($pdo, 'pos_products', 'min_stock')) {
            $pdo->exec("ALTER TABLE pos_products ADD COLUMN min_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000");
        }
        if (!self::columnExists($pdo, 'pos_products', 'max_stock')) {
            $pdo->exec("ALTER TABLE pos_products ADD COLUMN max_stock DECIMAL(12,3) DEFAULT NULL");
        }
        if (!self::columnExists($pdo, 'pos_products', 'unit')) {
            $pdo->exec("ALTER TABLE pos_products ADD COLUMN unit VARCHAR(16) NOT NULL DEFAULT 'pcs'");
        }
    }

    private static function createStockMovements(\PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pos_stock_movements')) return;
        $pdo->exec("
            CREATE TABLE pos_stock_movements (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED NOT NULL,
              change_qty DECIMAL(12,3) NOT NULL,
              reason VARCHAR(120) DEFAULT NULL,
              ref_type VARCHAR(40) DEFAULT NULL,
              ref_id BIGINT UNSIGNED DEFAULT NULL,
              user_id BIGINT UNSIGNED DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_org (org_id),
              KEY idx_prod (product_id),
              KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }




    /* -------------------- create DDL -------------------- */

    private static function createProducts(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pos_products')) return;
        $pdo->exec("
            CREATE TABLE pos_products (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              sku VARCHAR(64) DEFAULT NULL,
              name VARCHAR(160) NOT NULL,
              category VARCHAR(120) DEFAULT NULL,
              price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              cost  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              stock DECIMAL(12,3) NOT NULL DEFAULT 0.000,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_org (org_id),
              KEY idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private static function createCustomers(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pos_customers')) return;
        $pdo->exec("
            CREATE TABLE pos_customers (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              name VARCHAR(160) NOT NULL,
              email VARCHAR(190) DEFAULT NULL,
              phone VARCHAR(40) DEFAULT NULL,
              address TEXT DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_org (org_id),
              KEY idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private static function createSales(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pos_sales')) return;
        $pdo->exec("
            CREATE TABLE pos_sales (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              cashier_id BIGINT UNSIGNED DEFAULT NULL,
              customer_id BIGINT UNSIGNED DEFAULT NULL,
              subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              tax      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              total    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              paid     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              method   ENUM('cash','card','mobile','mixed') NOT NULL DEFAULT 'cash',
              note     VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_org (org_id),
              KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    private static function createCategories(\PDO $pdo): void
{
    if (self::tableExists($pdo, 'pos_categories')) return;

    $pdo->exec("
        CREATE TABLE pos_categories (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          org_id INT UNSIGNED NOT NULL,
          name VARCHAR(191) NOT NULL,
          slug VARCHAR(191) NOT NULL,
          code VARCHAR(10) DEFAULT NULL,
          parent_id BIGINT UNSIGNED DEFAULT NULL,
          description TEXT DEFAULT NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uq_org_name (org_id, name),
          KEY idx_org (org_id),
          KEY idx_parent (parent_id),
          KEY idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

    private static function createSaleItems(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pos_sale_items')) return;
        $pdo->exec("
            CREATE TABLE pos_sale_items (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              sale_id BIGINT UNSIGNED NOT NULL,
              org_id INT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED DEFAULT NULL,
              sku VARCHAR(64) DEFAULT NULL,
              name VARCHAR(160) NOT NULL,
              qty  DECIMAL(12,3) NOT NULL DEFAULT 0.000,
              price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_sale (sale_id),
              KEY idx_org (org_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private static function createCashShifts(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pos_cash_shifts')) return;
        $pdo->exec("
            CREATE TABLE pos_cash_shifts (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              cashier_id BIGINT UNSIGNED DEFAULT NULL,
              opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              closed_at DATETIME DEFAULT NULL,
              opening_float DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              closing_cash DECIMAL(12,2) DEFAULT NULL,
              note VARCHAR(255) DEFAULT NULL,
              KEY idx_org (org_id),
              KEY idx_open (opened_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    
    private static function ensureProductStockColumns(\PDO $pdo): void
{
    // Core stock field
    if (!self::columnExists($pdo, 'pos_products', 'stock')) {
        $pdo->exec("ALTER TABLE pos_products ADD COLUMN stock DECIMAL(12,3) NOT NULL DEFAULT 0.000");
    }
    // Min/Max thresholds
    if (!self::columnExists($pdo, 'pos_products', 'min_stock')) {
        $pdo->exec("ALTER TABLE pos_products ADD COLUMN min_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000");
    }
    if (!self::columnExists($pdo, 'pos_products', 'max_stock')) {
        $pdo->exec("ALTER TABLE pos_products ADD COLUMN max_stock DECIMAL(12,3) DEFAULT NULL");
    }
    // Optional unit
    if (!self::columnExists($pdo, 'pos_products', 'unit')) {
        $pdo->exec("ALTER TABLE pos_products ADD COLUMN unit VARCHAR(16) NOT NULL DEFAULT 'pcs'");
    }
}

private static function createStockMovements(\PDO $pdo): void
{
    if (self::tableExists($pdo, 'pos_stock_movements')) return;
    $pdo->exec("
        CREATE TABLE pos_stock_movements (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          org_id INT UNSIGNED NOT NULL,
          product_id BIGINT UNSIGNED NOT NULL,
          change_qty DECIMAL(12,3) NOT NULL,
          reason VARCHAR(120) DEFAULT NULL,
          ref_type VARCHAR(40) DEFAULT NULL,
          ref_id BIGINT UNSIGNED DEFAULT NULL,
          user_id BIGINT UNSIGNED DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_org (org_id),
          KEY idx_prod (product_id),
          KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
    

    private static function createCashEntries(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'pos_cash_entries')) return;
        $pdo->exec("
            CREATE TABLE pos_cash_entries (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              shift_id BIGINT UNSIGNED NOT NULL,
              type ENUM('sale','payout','deposit','adjustment') NOT NULL DEFAULT 'sale',
              amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              note VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_org (org_id),
              KEY idx_shift (shift_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}