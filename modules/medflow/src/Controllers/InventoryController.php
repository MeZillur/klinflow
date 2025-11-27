<?php
declare(strict_types=1);

namespace Modules\medflow\Controllers;

use Shared\View;

final class InventoryController
{
    public function __construct(private array $ctx) {}

    /* ---------- tiny helpers ---------- */
    private function base(): string  { return $this->ctx['module_base'] ?? '/apps/medflow'; }
    private function org(): array    { return $this->ctx['org'] ?? []; }

    /** Reusable stub renderer so empty pages don’t 404 */
    private function stub(string $title, string $message, array $actions = []): void
    {
        View::render('modules/medflow/_stubs/coming', [
            'scope'         => 'tenant',
            'layout'        => null,
            'title'         => $title,
            'message'       => $message,
            'actions'       => $actions,
            'module_base'   => $this->base(),
            'org'           => $this->org(),
            'moduleSidenav' => __DIR__ . '/../../Views/shared/partials/sidenav.php',
        ]);
    }

    /* ==================== ROUTE TARGETS (by sidenav) ==================== */

    /** GET /inventory or /inventory/items */
    public function items(): void
    {
        // If you later create a real view, keep this path form (no “Views/”):
        // View::render('modules/medflow/inventory/items', [...]);
        $this->stub('Inventory — Items', 'Items listing UI is coming shortly.', [
            ['href' => $this->base(),               'icon' => 'fa-house', 'label' => 'Dashboard'],
            ['href' => $this->base().'/inventory',  'icon' => 'fa-box',   'label' => 'Inventory'],
        ]);
    }

    /** GET /inventory/batches */
    public function batches(): void
    {
        $this->stub('Inventory — Batches', 'Batch ledger UI is coming shortly.', [
            ['href' => $this->base().'/inventory', 'icon' => 'fa-box',  'label' => 'Inventory'],
        ]);
    }

    /** GET /inventory/stock-moves */
    public function moves(): void
    {
        $this->stub('Inventory — Stock Moves', 'Stock movement register is coming shortly.', [
            ['href' => $this->base().'/inventory', 'icon' => 'fa-box', 'label' => 'Inventory'],
        ]);
    }

    /** GET /inventory/adjustments */
    public function adjustments(): void
    {
        $this->stub('Inventory — Adjustments', 'Adjustment screen is coming shortly.', [
            ['href' => $this->base().'/inventory', 'icon' => 'fa-box', 'label' => 'Inventory'],
        ]);
    }

    /** GET /inventory/transfers */
    public function transfers(): void
    {
        $this->stub('Inventory — Transfers', 'Store transfers screen is coming shortly.', [
            ['href' => $this->base().'/inventory', 'icon' => 'fa-box', 'label' => 'Inventory'],
        ]);
    }

    /** GET /inventory/stock-count */
    public function stockCount(): void
    {
        $this->stub('Inventory — Stock Count', 'Cycle count / stocktake is coming shortly.', [
            ['href' => $this->base().'/inventory', 'icon' => 'fa-box', 'label' => 'Inventory'],
        ]);
    }

    /** GET /inventory/low-stock */
    public function lowStock(): void
    {
        $this->stub('Inventory — Low Stock', 'Low stock alert list is coming shortly.', [
            ['href' => $this->base().'/inventory', 'icon' => 'fa-box', 'label' => 'Inventory'],
        ]);
    }

    /** GET /inventory/near-expiry */
    public function nearExpiry(): void
    {
        $this->stub('Inventory — Near Expiry', 'Near-expiry items list is coming shortly.', [
            ['href' => $this->base().'/inventory', 'icon' => 'fa-box', 'label' => 'Inventory'],
        ]);
    }
}