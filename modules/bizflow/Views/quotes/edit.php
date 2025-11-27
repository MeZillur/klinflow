<?php
declare(strict_types=1);

/**
 * BizFlow — Edit quote
 *
 * For now we deliberately reuse the create screen so that
 * the layout, JS and behaviour stay identical.
 *
 * QuotesController::edit() already passes:
 *  - $org
 *  - $module_base
 *  - $quote
 *  - $lines
 *  - $mode = 'edit'
 *
 * In future we can teach create.php to pre-fill fields when
 * $mode === 'edit' and $quote/$lines are present.
 */

/** @var array  $org */
/** @var string $module_base */
/** @var array  $quote */
/** @var array  $lines */
/** @var string $title */

// Ensure $mode is explicitly set to 'edit' for the included view.
$mode  = 'edit';
$title = $title ?? 'Edit quote';

// Reuse the create view (same layout + JS)
require __DIR__ . '/create.php';