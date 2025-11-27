<?php
namespace Modules\POS\Services;

interface PricingService
{
    /**
     * Compute totals (line + order discounts, tax).
     * Returns normalized cart with computed fields and totals in cents.
     */
    public function priceCart(int $org_id, array $cart, array $settings): array;
}