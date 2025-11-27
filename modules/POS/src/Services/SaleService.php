<?php
namespace Modules\POS\Services;

interface SaleService
{
    /**
     * @param int   $org_id
     * @param int   $cashier_user_id
     * @param array $cart {lines: [{product_id?, sku, name, qty, price_cents, discount_cents, tax_cents}], order_discount_cents, tax_cents, subtotal_cents, total_cents}
     * @param array $payments [{method: 'cash'|'card'|'other', amount_cents, ref?}]
     * @param int|null $customer_id
     * @param array $options {notes?, sale_prefix?}
     * @return array {sale_id, sale_no}
     */
    public function checkout(
        int $org_id,
        int $cashier_user_id,
        array $cart,
        array $payments,
        ?int $customer_id = null,
        array $options = []
    ): array;
}