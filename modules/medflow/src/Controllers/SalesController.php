<?php
declare(strict_types=1);

namespace Modules\medflow\Controllers;

use Shared\View;
use Shared\DB; // for DB::pdo()

final class SalesController
{
    public function __construct(private array $ctx) {}

    /** List */
    public function index(): void
    {
        $pdo   = DB::pdo();
        $orgId = (int)($this->ctx['org']['id'] ?? $this->ctx['org_id'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT id, invoice_no, customer_name, grand_total, sold_at
            FROM med_sales
            WHERE org_id = :org
            ORDER BY COALESCE(sold_at, created_at) DESC
            LIMIT 200
        ");
        $stmt->execute([':org' => $orgId]);
        $sales = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        View::render('modules/medflow/sales/index', [
            'scope'       => 'tenant',
            'layout'      => null,
            'title'       => 'Sales',
            'module_base' => $this->ctx['module_base'] ?? '/apps/medflow',
            'org'         => $this->ctx['org'] ?? [],
            'sales'       => $sales,
        ]);
    }

    /** New form */
    public function create(): void
    {
        View::render('modules/medflow/sales/create', [
            'scope'       => 'tenant',
            'layout'      => null,
            'title'       => 'Sales — New',
            'module_base' => $this->ctx['module_base'] ?? '/apps/medflow',
            'org'         => $this->ctx['org'] ?? [],
        ]);
    }

    /** Accept POST from the create form (header-only minimal save) */
    // modules/medflow/src/Controllers/SalesController.php

public function store(): void
{
    $pdo   = \Shared\DB::pdo();               // your PDO getter
    $orgId = (int)($this->ctx['org']['id'] ?? 22);

    // Expecting POST fields:
    //  customer_name, items[]: [{name, qty, price, discount}]
    $customer = trim((string)($_POST['customer_name'] ?? ''));
    $items    = $_POST['items'] ?? [];        // array of lines

    // Create sale shell
    $pdo->prepare("
        INSERT INTO med_sales (org_id, invoice_no, customer_name, sale_date, status)
        VALUES (:org, :inv, :cust, NOW(), 'confirmed')
    ")->execute([
        ':org'  => $orgId,
        ':inv'  => 'TEMP-'.date('His'),
        ':cust' => $customer ?: null,
    ]);
    $saleId = (int)$pdo->lastInsertId();

    $subtotal = 0.00;
    $discount_total = 0.00;

    // Insert items
    $insLine = $pdo->prepare("
      INSERT INTO med_sale_items
        (org_id, sale_id, item_id, name, qty, unit_price, discount_amount, line_total, created_at)
      VALUES
        (:org, :sid, :item_id, :name, :qty, :unit, :disc, :lt, NOW())
    ");

    foreach ($items as $it) {
        // tolerate payloads where price is named 'price' or 'unit_price'
        $qty   = max(0, (float)($it['qty'] ?? 0));
        $unit  = round((float)($it['unit_price'] ?? $it['price'] ?? 0), 4);
        $disc  = round((float)($it['discount'] ?? 0), 2);
        $name  = (string)($it['name'] ?? '');
        $itemId = !empty($it['item_id']) ? (int)$it['item_id'] : null; // nullable after FK relaxation

        $line  = round($qty * $unit - $disc, 2);

        $insLine->execute([
            ':org'     => $orgId,
            ':sid'     => $saleId,
            ':item_id' => $itemId,       // can be null (Option A relaxed FK)
            ':name'    => $name,
            ':qty'     => $qty,
            ':unit'    => $unit,
            ':disc'    => $disc,
            ':lt'      => $line,
        ]);

        $subtotal       += round($qty * $unit, 2);
        $discount_total += $disc;
    }

    $grand_total = round($subtotal - $discount_total, 2);

    // Update sale totals
    $pdo->prepare("
      UPDATE med_sales
         SET subtotal       = :sub,
             discount_total = :disc,
             grand_total    = :grand,
             sold_at        = NOW()
       WHERE id = :id AND org_id = :org
    ")->execute([
        ':sub'   => $subtotal,
        ':disc'  => $discount_total,
        ':grand' => $grand_total,
        ':id'    => $saleId,
        ':org'   => $orgId,
    ]);

    // redirect json or 200
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'sale_id' => $saleId]);
    exit;
}

    /** Show one invoice (HTML for modal, JSON when asked) */
    public function show(int $id): void
{
    $pdo   = \Shared\DB::pdo();
    $orgId = (int)($this->ctx['org']['id'] ?? 22);

    // --- fetch sale header
    $sth = $pdo->prepare(
        "SELECT id, invoice_no, customer_name,
                COALESCE(subtotal,0)        AS subtotal,
                COALESCE(discount_total,0)  AS discount_total,
                COALESCE(grand_total,0)     AS grand_total,
                COALESCE(sold_at, sale_date) AS sold_at
           FROM med_sales
          WHERE id = :id AND org_id = :org
          LIMIT 1"
    );
    $sth->execute([':id' => $id, ':org' => $orgId]);
    $sale = $sth->fetch(\PDO::FETCH_ASSOC);

    if (!$sale) {
        http_response_code(404);
        echo 'Sale not found';
        return;
    }

    // --- fetch sale items
    $sti = $pdo->prepare(
        "SELECT name,
                qty,
                COALESCE(unit_price,0)      AS unit_price,
                COALESCE(discount_amount,0) AS discount_amount,
                COALESCE(line_total,0)      AS line_total
           FROM med_sale_items
          WHERE sale_id = :sid AND org_id = :org
          ORDER BY id"
    );
    $sti->execute([':sid' => $id, ':org' => $orgId]);
    $items = $sti->fetchAll(\PDO::FETCH_ASSOC);

    \Shared\View::render('modules/medflow/sales/partials/invoice', [
        'scope'       => 'tenant',
        'layout'      => false, // modal fragment
        'sale'        => $sale,
        'items'       => $items,
        'module_base' => $this->ctx['module_base'] ?? '/apps/medflow',
        'org'         => $this->ctx['org'] ?? [],
    ]);
}
    
}