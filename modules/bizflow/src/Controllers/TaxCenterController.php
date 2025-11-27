<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class TaxCenterController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    /** Soft table existence check (so UI works before schema) */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $sql = "SELECT 1
                      FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name   = :t
                     LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute(['t' => $table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Demo VAT/WHT summary — until we wire actual:
     *   - VAT input from purchases/expenses
     *   - VAT output from sales
     *   - WHT from expenses / supplier payments
     */
    private function demoSummary(): array
    {
        return [
            'period_label' => 'Jan–Mar 2025',
            'vat_input'    => 850000.00,   // BDT
            'vat_output'   => 1200000.00,  // BDT
            'wht_payable'  => 210000.00,   // BDT (AIT deducted)
            'returns_filed'=> 2,           // demo
            'returns_due'  => 1,           // demo
        ];
    }

    /** Demo VAT input rows (purchases/expenses) */
    private function demoVatInput(): array
    {
        return [
            [
                'date'         => '2025-01-15',
                'source'       => 'Local purchase',
                'ref'          => 'PUR-2025-0012',
                'party'        => 'ABC Stationery Ltd.',
                'taxable'      => 250000.00,
                'vat_amount'   => 37500.00,
                'form_code'    => 'Mushak 6.3',
            ],
            [
                'date'         => '2025-02-02',
                'source'       => 'Expense voucher',
                'ref'          => 'EXP-2025-0002',
                'party'        => 'DESCO',
                'taxable'      => 40000.00,
                'vat_amount'   => 6000.00,
                'form_code'    => 'Mushak 6.3',
            ],
        ];
    }

    /** Demo VAT output rows (sales) */
    private function demoVatOutput(): array
    {
        return [
            [
                'date'         => '2025-01-20',
                'source'       => 'Sales invoice',
                'ref'          => 'INV-2025-0045',
                'customer'     => 'NGO – Sample Org',
                'taxable'      => 300000.00,
                'vat_amount'   => 45000.00,
                'form_code'    => 'Mushak 6.3',
            ],
            [
                'date'         => '2025-02-11',
                'source'       => 'Sales invoice',
                'ref'          => 'INV-2025-0078',
                'customer'     => 'Corporate Client Ltd.',
                'taxable'      => 500000.00,
                'vat_amount'   => 75000.00,
                'form_code'    => 'Mushak 6.3',
            ],
        ];
    }

    /** Demo WHT rows (AIT deducted at source) */
    private function demoWhtRows(): array
    {
        return [
            [
                'date'         => '2025-01-05',
                'source'       => 'Expense voucher',
                'ref'          => 'EXP-2025-0001',
                'party'        => 'ABC Properties Ltd.',
                'section'      => '53A',
                'base_amount'  => 150000.00,
                'wht_amount'   => 7500.00,
                'challan_no'   => null,
                'challan_date' => null,
            ],
            [
                'date'         => '2025-02-10',
                'source'       => 'Supplier payment',
                'ref'          => 'PAY-2025-009',
                'party'        => 'XYZ Services Ltd.',
                'section'      => '52',
                'base_amount'  => 200000.00,
                'wht_amount'   => 10000.00,
                'challan_no'   => 'CH-2025-0021',
                'challan_date' => '2025-03-07',
            ],
        ];
    }

    /* -------------------------------------------------------------
     * Index — Tax / VAT / WHT corner
     * ----------------------------------------------------------- */

    public function index(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $org = $c['org'] ?? [];
            $pdo = $this->pdo();

            // Later we’ll derive from:
            //  - biz_expenses
            //  - biz_purchases
            //  - biz_sales / biz_invoices
            $hasExpenses  = $this->hasTable($pdo, 'biz_expenses');
            $hasPurchases = $this->hasTable($pdo, 'biz_purchases');
            $hasSales     = $this->hasTable($pdo, 'biz_invoices'); // example

            $summary   = $this->demoSummary();
            $vatInput  = $this->demoVatInput();
            $vatOutput = $this->demoVatOutput();
            $whtRows   = $this->demoWhtRows();

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');

            $this->view('tax/index', [
                'title'        => 'Tax / VAT / WHT center',
                'org'          => $org,
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'summary'      => $summary,
                'vat_input'    => $vatInput,
                'vat_output'   => $vatOutput,
                'wht_rows'     => $whtRows,
                'today'        => $today,
                'has_expenses' => $hasExpenses,
                'has_purchases'=> $hasPurchases,
                'has_sales'    => $hasSales,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Tax center failed', $e);
        }
    }
}