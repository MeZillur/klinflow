<?php
declare(strict_types=1);
/**
 * Component: Button
 * echo render_button(['label'=>'Save','variant'=>'primary','href'=>'/path']);
 */
if (!function_exists('render_button')) {
  function render_button(array $p): string {
    $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $label   = (string)($p['label'] ?? 'Button');
    $variant = (string)($p['variant'] ?? 'default');
    $href    = (string)($p['href'] ?? '');
    $type    = (string)($p['type'] ?? 'button');
    $icon    = (string)($p['icon'] ?? '');
    $attrs   = (array) ($p['attrs'] ?? []);

    $cls = 'btn';
    if ($variant === 'primary') $cls .= ' btn-primary';
    if ($variant === 'danger')  $cls .= ' text-white';

    $attrStr = '';
    foreach ($attrs as $k=>$v) { $attrStr .= ' '.$h($k).'="'.$h((string)$v).'"'; }

    $inner = ($icon ? '<i class="'.$h($icon).'"></i> ' : '') . $h($label);

    if ($href !== '') {
      return '<a class="'.$h($cls).'" href="'.$h($href).'"'.$attrStr.'>'.$inner.'</a>';
    }
    return '<button class="'.$h($cls).'" type="'.$h($type).'"'.$attrStr.'>'.$inner.'</button>';
  }
}