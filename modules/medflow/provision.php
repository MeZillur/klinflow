<?php
declare(strict_types=1);

namespace Modules\medflow;

/**
 * Minimal MedFlow tenant schema.
 * Call: \Modules\medflow\provision($tenantPdo);
 */
function provision(\PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS med_inventory (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sku           VARCHAR(64)  NOT NULL,
            name          VARCHAR(190) NOT NULL,
            unit          VARCHAR(32)  DEFAULT 'pcs',
            unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
            qty_on_hand   DECIMAL(14,3) NOT NULL DEFAULT 0,
            low_stock_at  DECIMAL(14,3) DEFAULT NULL,
            is_active     TINYINT(1) NOT NULL DEFAULT 1,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_med_inventory_sku (sku),
            KEY idx_med_inventory_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS med_sales (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sale_date    DATE NOT NULL,
            patient_name VARCHAR(190) DEFAULT NULL,
            notes        TEXT NULL,
            subtotal     DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount     DECIMAL(12,2) NOT NULL DEFAULT 0,
            tax          DECIMAL(12,2) NOT NULL DEFAULT 0,
            total        DECIMAL(12,2) NOT NULL DEFAULT 0,
            status       ENUM('draft','posted','void') NOT NULL DEFAULT 'posted',
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_med_sales_date (sale_date),
            KEY idx_med_sales_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS med_sale_items (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sale_id       BIGINT UNSIGNED NOT NULL,
            item_id       BIGINT UNSIGNED NOT NULL,
            description   VARCHAR(190) DEFAULT NULL,
            qty           DECIMAL(14,3) NOT NULL DEFAULT 0,
            unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total    DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_med_sale_items_sale (sale_id),
            KEY idx_med_sale_items_item (item_id),
            CONSTRAINT fk_med_sale_items_sale
                FOREIGN KEY (sale_id) REFERENCES med_sales(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_med_sale_items_item
                FOREIGN KEY (item_id) REFERENCES med_inventory(id)
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}