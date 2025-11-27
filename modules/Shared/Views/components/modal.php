<?php
declare(strict_types=1);
/**
 * Shared Component: Modal (Alpine.js)
 * Usage example:
 *   require __DIR__.'/modal.php';
 *   echo render_modal([
 *     'id'     => 'confirmDelete',
 *     'title'  => 'Delete record',
 *     'body'   => '<p>Are you sure?</p>',
 *     'confirm'=> ['label'=>'Delete','attrs'=>['@click'=>'onConfirm()','class'=>'btn btn-primary']],
 *     'cancel' => ['label'=>'Cancel','attrs'=>['@click'=>'open=false','class'=>'btn']],
 *     'attrs'  => ['x-data'=>'{open:false}'] // initial Alpine scope
 *   ]);
 */
if (!function_exists('render_modal')) {
  function render_modal(array $p): string {
    $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $id     = (string)($p['id'] ?? ('m'.bin2hex(random_bytes(4))));
    $title  = (string)($p['title'] ?? '');
    $body   = (string)($p['body'] ?? '');
    $attrs  = (array)($p['attrs'] ?? []);
    $confirm= (array)($p['confirm'] ?? []);
    $cancel = (array)($p['cancel'] ?? []);

    // container attrs
    $attrStr = ' x-data="{open:false}"';
    if ($attrs) {
      $attrStr = '';
      foreach ($attrs as $k=>$v) {
        $attrStr .= ' '.$h($k).'="'.$h((string)$v).'"';
      }
    }

    $btn = function(array $b, string $defaultClass='btn'): string use ($h) {
      $label = (string)($b['label'] ?? 'OK');
      $attrs = (array)($b['attrs'] ?? []);
      $hasClass = false;
      $as = '';
      foreach ($attrs as $k=>$v) {
        if ($k === 'class') $hasClass = true;
        $as .= ' '.$h($k).'="'.$h((string)$v).'"';
      }
      if (!$hasClass) $as .= ' class="'.$h($defaultClass).'"';
      return '<button'.$as.'>'.$h($label).'</button>';
    };

    return <<<HTML
<div{$attrStr} id="{$h($id)}" aria-modal="true" role="dialog">
  <div class="fixed inset-0 z-40 bg-black/50" x-show="open" x-transition.opacity @click="open=false"></div>
  <div class="fixed inset-0 z-50 grid place-items-center p-4" x-show="open" x-transition>
    <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-lg dark:bg-gray-900 dark:border-gray-700">
      <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div class="font-semibold">{$h($title)}</div>
        <button class="btn" @click="open=false" aria-label="Close"><i class="fa fa-times"></i></button>
      </div>
      <div class="p-4">{$body}</div>
      <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
        {$btn($cancel, 'btn')}
        {$btn($confirm, 'btn btn-primary')}
      </div>
    </div>
  </div>
</div>
HTML;
  }
}