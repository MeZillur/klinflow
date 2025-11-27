<?php
declare(strict_types=1);

/**
 * DMS module navigation — dynamic
 */

$base = $module_base ?? '{base}'; // fallback if called statically

return [

  [
    'key'   => 'sales',
    'icon'  => 'fa-file-invoice-dollar',
    'label' => 'Sales & Invoicing',
    'children' => [
      ['label' => 'Invoices',    'href' => "$base/sales"],
      ['label' => 'New Invoice', 'href' => "$base/sales/create"],
      ['label' => 'Payments',    'href' => "$base/payments"],
    ],
  ],

  [
    'key'   => 'orders',
    'icon'  => 'fa-bag-shopping',
    'label' => 'Orders',
    'children' => [
      ['label' => 'All Orders',  'href' => "$base/orders"],
      ['label' => 'New Order',   'href' => "$base/orders/create"],
    ],
  ],

  [
    'key'   => 'products-purchases',
    'icon'  => 'fa-cart-shopping',
    'label' => 'Products & Purchases',
    'children' => [
      ['label' => 'Products',     'href' => "$base/products"],
      ['label' => 'New Product',  'href' => "$base/products/create"],
      ['label' => 'Categories',   'href' => "$base/categories"],
      ['label' => 'New Category', 'href' => "$base/categories/create"],
      ['label' => 'Purchases',    'href' => "$base/purchases"],
      ['label' => 'New Purchase', 'href' => "$base/purchases/create"],
      ['label' => 'Dealers',      'href' => "$base/dealers"],
      ['label' => 'New Dealer',   'href' => "$base/dealers/create"],
    ],
  ],

  [
    'key'   => 'inventory',
    'icon'  => 'fa-warehouse',
    'label' => 'Inventory',
    'children' => [
      ['label' => 'Overview',       'href' => "$base/inventory"],
      ['label' => 'Adjustments',    'href' => "$base/inventory/adjust"],
      ['label' => 'Damage Reports', 'href' => "$base/inventory/damage"],
      ['label' => 'Aging',          'href' => "$base/inventory/aging"],
      ['label' => 'Free — Receive', 'href' => "$base/free/receive"],
      ['label' => 'Free — Issue',   'href' => "$base/free/issue"],
      ['label' => 'Free — Moves',   'href' => "$base/free/movements"],
      ['label' => 'Free — Stock',   'href' => "$base/free/inventory"],
    ],
  ],

  [
    'key'   => 'accounts',
    'icon'  => 'fa-chart-line',
    'label' => 'Accounting & Cash',
    'children' => [
      ['label' => 'Accounting Dashboard', 'href' => "$base/accounts"],
      ['label' => 'Cash Book',            'href' => "$base/accounts/cash-book"],
      ['label' => 'Bank Book',            'href' => "$base/accounts/bank-book"],
      ['label' => 'Mobile Bank Book',     'href' => "$base/accounts/mobile-bank-book"],
      ['label' => 'Bank Accounts',        'href' => "$base/bank-accounts"],
      ['label' => 'Transactions',         'href' => "$base/transactions"],
      ['label' => 'Expenses',             'href' => "$base/expenses"],
      ['label' => 'Reports',              'href' => "$base/reports"],
    ],
  ],

  [
    'key'   => 'customers',
    'icon'  => 'fa-user-group',
    'label' => 'Customers',
    'children' => [
      ['label' => 'Customers',      'href' => "$base/customers"],
      ['label' => 'New Customer',   'href' => "$base/customers/create"],
      ['label' => 'Credit Summary', 'href' => "$base/customers/credit-summary"],
    ],
  ],

  [
    'key'   => 'stakeholders',
    'icon'  => 'fa-people-group',
    'label' => 'Stakeholders',
    'children' => [
      ['label' => 'Overview',     'href' => "$base/stakeholders"],
      ['label' => 'Performance',  'href' => "$base/stakeholders/performance"],
      ['label' => 'SR — List',    'href' => "$base/stakeholders/sr"],
      ['label' => 'SR — Create',  'href' => "$base/stakeholders/sr/create"],
    ],
  ],

];