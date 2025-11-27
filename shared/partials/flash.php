<?php
declare(strict_types=1);

/**
 * Render a stylish toast/alert from $_SESSION['_toast'].
 * Example structure:
 * $_SESSION['_toast'] = [
 *   'type'  => 'success'|'error'|'info',
 *   'title' => 'Reset link sent',
 *   'body'  => 'Check your inbox (and spam) to continue.'
 * ];
 */
function render_toast_once(): void
{
    $t = $_SESSION['_toast'] ?? null;
    if (!$t) return;
    unset($_SESSION['_toast']);

    $type  = $t['type']  ?? 'info';
    $title = $t['title'] ?? '';
    $body  = $t['body']  ?? '';

    // Colors (brand green = #228B22)
    $palette = [
        'success' => ['bg' => 'rgba(34,139,34,.08)', 'bd' => '#14532d', 'ink' => '#14532d', 'chip' => '#228B22'],
        'error'   => ['bg' => 'rgba(220,38,38,.08)',  'bd' => '#7f1d1d', 'ink' => '#7f1d1d', 'chip' => '#dc2626'],
        'info'    => ['bg' => 'rgba(2,132,199,.08)',  'bd' => '#0c4a6e', 'ink' => '#0c4a6e', 'chip' => '#0284c7'],
    ];
    $p = $palette[$type] ?? $palette['info'];

    $esc = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    ?>
    <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;
                border-radius:12px;background:<?= $p['bg'] ?>;border:1px solid <?= $p['bd'] ?>;
                color:<?= $p['ink'] ?>;font:14px/1.5 ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial;">
      <span style="width:10px;height:10px;margin-top:3px;border-radius:9999px;background:<?= $p['chip'] ?>;"></span>
      <span>
        <?php if ($title!==''): ?><strong><?= $esc($title) ?></strong><?php endif; ?>
        <?php if ($body!==''): ?><div><?= $esc($body) ?></div><?php endif; ?>
      </span>
    </div>
    <?php
}