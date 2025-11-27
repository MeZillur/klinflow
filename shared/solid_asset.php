<?php
declare(strict_types=1);

/**
 * Resolve Solid/Vite built bundle from manifest.
 * Usage: <script type="module" src="<?= h(solid_asset()) ?>"></script>
 */
if (!function_exists('solid_asset')) {
    function solid_asset(string $entry = 'index.html'): string
    {
        static $map = null;

        if ($map === null) {
            // Adjust path if you move dist
            $manifestPath = __DIR__ . '/../public/assets/dist/.vite/manifest.json';
            if (!is_file($manifestPath)) {
                return '';
            }
            $json = json_decode((string)file_get_contents($manifestPath), true) ?: [];

            // Build simple map of entry -> file
            $map = [];
            foreach ($json as $name => $info) {
                if (!empty($info['isEntry']) && !empty($info['file'])) {
                    $map[$name] = '/assets/dist/' . ltrim($info['file'], '/');
                }
            }
        }

        // Our only entry is index.html for now
        if ($entry === '' || $entry === 'default') {
            // pick first entry
            if (!empty($map)) {
                return reset($map);
            }
        }

        return $map[$entry] ?? '';
    }
}