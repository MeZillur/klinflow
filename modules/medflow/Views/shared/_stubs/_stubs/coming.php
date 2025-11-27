<?php
declare(strict_types=1);
/**
 * Shared “Coming Soon” stub.
 * Accepts:
 *  - $title    (string)   Page title
 *  - $message  (string)   Short description
 *  - $actions  (array[])  [['href'=>'/path','icon'=>'fa-...','label'=>'Text'], ...]
 *  - $brandColor (string) CSS color fallback
 *
 * Uses the module shell if called from a module (layout is already applied by manifest).
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$title      = $title   ?? 'Coming soon';
$message    = $message ?? 'This section is under active development.';
$actions    = is_array($actions ?? null) ? $actions : [];
$brandColor = $brandColor ?? '#228B22';
?>
<section class="max-w-4xl mx-auto p-6">
  <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
    <div class="flex items-start gap-4">
      <div class="flex-none w-12 h-12 rounded-full grid place-items-center text-white"
           style="background: <?= $h($brandColor) ?>;">
        <i class="fa-solid fa-person-digging text-xl" aria-hidden="true"></i>
      </div>
      <div class="min-w-0">
        <h1 class="text-xl font-semibold mb-1"><?= $h($title) ?></h1>
        <p class="text-gray-600 dark:text-gray-300"><?= $h($message) ?></p>

        <?php if ($actions): ?>
          <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($actions as $a):
              $href  = $a['href']  ?? '#';
              $icon  = $a['icon']  ?? 'fa-arrow-left';
              $label = $a['label'] ?? 'Back';
            ?>
              <a href="<?= $h($href) ?>"
                 class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fa-solid <?= $h($icon) ?>"></i>
                <span><?= $h($label) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="mt-5 text-xs text-gray-500">
          Tip: if you see this often, the controller can temporarily point to this stub while we ship the real UI.
        </div>
      </div>
    </div>
  </div>
</section>