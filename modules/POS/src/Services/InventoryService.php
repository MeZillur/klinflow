<?php
namespace Modules\POS\Services;

interface InventoryService
{
    public function decrementForSale(int $org_id, array $lineItems): void;
    public function adjust(int $org_id, int $product_id, string $direction, string $reason, float $qty, ?int $sale_id = null, ?string $note = null): void;
}