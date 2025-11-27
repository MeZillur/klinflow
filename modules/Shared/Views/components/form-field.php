<?php
declare(strict_types=1);
/**
 * Component: Form Field
 */
if (!function_exists('render_form_field')) {
  function render_form_field(array $p): string {
    $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $name = (string)($p['name'] ?? '');
    if ($name==='') return '';
    $id   = $h($p['id'] ?? $name);
    $label= (string)($p['label'] ?? '');
    $type = (string)($p['type'] ?? 'text');
    $val  = (string)($p['value'] ?? '');
    $req  = !empty($p['required']);
    $help = (string)($p['help'] ?? '');
    $err  = (string)($p['error'] ?? '');
    $attrs= (array) ($p['attrs'] ?? []);
    $opts = (array) ($p['options'] ?? []);

    $cls = 'block w-full rounded-md border border-gray-300 px-3 py-2 text-sm';
    $html = '<div class="mb-3">';
    if ($label !== '') $html .= '<label class="mb-1 block text-sm font-medium" for="'.$id.'">'.$h($label).($req?' *':'').'</label>';

    $attrStr = '';
    foreach ($attrs as $k=>$v) { $attrStr .= ' '.$h($k).'="'.$h((string)$v).'"'; }

    if ($type === 'textarea') {
      $html .= '<textarea id="'.$id.'" name="'.$h($name).'" class="'.$cls.'"'.($req?' required':'').$attrStr.'>'.$h($val).'</textarea>';
    } elseif ($type === 'select') {
      $html .= '<select id="'.$id.'" name="'.$h($name).'" class="'.$cls.'" data-choices'.($req?' required':'').$attrStr.'>';
      foreach ($opts as $o) {
        $ov = (string)($o['value'] ?? ''); $ol=(string)($o['label'] ?? $ov);
        $sel = ($ov === $val) ? ' selected' : '';
        $html .= '<option value="'.$h($ov).'"'.$sel.'>'.$h($ol).'</option>';
      }
      $html .= '</select>';
    } else {
      $html .= '<input id="'.$id.'" type="'.$h($type).'" name="'.$h($name).'" value="'.$h($val).'" class="'.$cls.'"'.($req?' required':'').$attrStr.'/>';
    }

    if ($help !== '') $html .= '<div class="mt-1 text-xs text-slate-500">'.$h($help).'</div>';
    if ($err  !== '') $html .= '<div class="mt-1 text-xs text-red-600">'.$h($err).'</div>';
    $html .= '</div>';
    return $html;
  }
}