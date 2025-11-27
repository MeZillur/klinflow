<?php
declare(strict_types=1);

namespace App\Services;

final class ModuleNav
{
    /**
     * Load module nav from modules/{KEY}/nav.php and auto-augment
     * with simple “index/create” pairs if their views exist.
     * $ctx must include: ['slug'=>..,'module_key'=>..,'module_dir'=>..]
     */
    public static function load(array $ctx): array
    {
        $key   = strtolower((string)($ctx['module_key'] ?? ''));
        $dir   = (string)($ctx['module_dir'] ?? '');
        $base  = "/t/".($ctx['slug'] ?? '')."/apps/$key";

        $tree = [];
        if ($dir && is_file($dir.'/nav.php')) {
            $data = include $dir.'/nav.php';
            if (is_array($data)) $tree = $data;
        }

        // minimal auto-discover: if Views/{section}/index.php exists,
        // add an item when not already present.
        $autoCandidates = [
            ['group'=>'purchase_inventory', 'label'=>'Purchases',      'href'=>"$base/purchase",        'check'=>"$dir/Views/purchase/index.php", 'icon'=>'fa-solid fa-cart-shopping'],
            ['group'=>'purchase_inventory', 'label'=>'New Purchase',   'href'=>"$base/purchase/create", 'check'=>"$dir/Views/purchase/create.php", 'icon'=>'fa-solid fa-plus'],
            ['group'=>'stakeholders_dealers','label'=>'Dealers',       'href'=>"$base/dealers",         'check'=>"$dir/Views/dealers/index.php",   'icon'=>'fa-solid fa-building'],
            ['group'=>'stakeholders_dealers','label'=>'Damage Reports','href'=>"$base/damage",          'check'=>"$dir/Views/damage/index.php",    'icon'=>'fa-solid fa-triangle-exclamation'],
        ];

        // Index by key for quick merge
        $byKey = [];
        foreach ($tree as $g) if (!empty($g['key'])) $byKey[$g['key']] = $g;

        foreach ($autoCandidates as $cand) {
            if (!is_file($cand['check'])) continue;
            $gk = $cand['group'];
            if (!isset($byKey[$gk])) {
                $byKey[$gk] = ['key'=>$gk,'label'=>'','icon'=>'','items'=>[]];
            }
            // de-dup by href
            $exists = false;
            foreach ($byKey[$gk]['items'] as $it) {
                if (($it['href'] ?? '') === $cand['href']) { $exists = true; break; }
            }
            if (!$exists) {
                $byKey[$gk]['items'][] = [
                    'label'=>$cand['label'], 'href'=>$cand['href'], 'icon'=>$cand['icon']
                ];
            }
        }

        // Normalize -> array
        $out = array_values($byKey);
        // Fill :base placeholders if any remain
        foreach ($out as &$g) {
            foreach ($g['items'] as &$it) {
                if (isset($it['href']) && strpos($it['href'], ':base') !== false) {
                    $it['href'] = str_replace(':base', $base, $it['href']);
                }
            }
        }
        return $out;
    }
}