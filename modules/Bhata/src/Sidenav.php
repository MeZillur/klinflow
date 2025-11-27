<?php
// modules/BhataFlow/src/Sidenav.php
namespace Modules\BhataFlow\src;

class Sidenav {
  public static function items(): array {
    return [
      ['label' => 'Dashboard', 'icon' => 'gauge', 'href' => '/t/{org}/bhata'],
      ['label' => 'Production', 'icon' => 'factory', 'children' => [
        ['label' => 'Green Batches', 'href' => '/t/{org}/bhata/production/batches'],
        ['label' => 'Kiln Cycles',   'href' => '/t/{org}/bhata/production/kiln-cycles'],
        ['label' => 'Fired Stock',   'href' => '/t/{org}/bhata/production/fired-stock'],
      ]],
      ['label' => 'Purchasing', 'icon' => 'cart', 'children' => [
        ['label' => 'Vendors',  'href' => '/t/{org}/bhata/purchasing/vendors'],
        ['label' => 'POs',      'href' => '/t/{org}/bhata/purchasing/po'],
        ['label' => 'GRN',      'href' => '/t/{org}/bhata/purchasing/grn'],
      ]],
      ['label' => 'Sales', 'icon' => 'truck', 'children' => [
        ['label' => 'Customers', 'href' => '/t/{org}/bhata/sales/customers'],
        ['label' => 'Dispatch',  'href' => '/t/{org}/bhata/sales/dispatch'],
        ['label' => 'Invoices',  'href' => '/t/{org}/bhata/sales/invoices'],
      ]],
      ['label' => 'HRM', 'icon' => 'users', 'children' => [
        ['label' => 'Labor',   'href' => '/t/{org}/bhata/hrm/labor'],
        ['label' => 'Payroll', 'href' => '/t/{org}/bhata/hrm/payroll'],
      ]],
      ['label' => 'Accounting', 'icon' => 'book-open', 'href' => '/t/{org}/bhata/accounting/ledger'],
      ['label' => 'Reports',    'icon' => 'bar-chart', 'href' => '/t/{org}/bhata/reports'],
    ];
  }
}