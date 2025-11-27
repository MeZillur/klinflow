<?php
declare(strict_types=1);

/**
 * asset helper: read Vite manifest and return asset path or tag
 *
 * Save as: app/Support/asset_helper.php
 */

function _load_asset_manifest(): ?array {
    static $manifest = null;
    if ($manifest !== null) return $manifest;
    // Adjust path if your project layout differs
    $manifestPath = __DIR__ . '/../../public/assets/dist/manifest.json';
    if (!is_file($manifestPath)) {
        $manifest = null;
        return null;
    }
    $json = json_decode((string)@file_get_contents($manifestPath), true);
    if (!is_array($json)) { $manifest = null; return null; }
    $manifest = $json;
    return $manifest;
}

/**
 * Get asset URL from manifest by entry key (e.g. 'kf-solid').
 * Returns path like '/assets/dist/kf-solid.abc123.js' or null if missing.
 */
function asset_from_manifest(string $key): ?string {
    $m = _load_asset_manifest();
    if (!$m || !isset($m[$key])) return null;
    $file = $m[$key]['file'] ?? null;
    if (!$file) return null;
    return '/assets/dist/' . ltrim((string)$file, '/');
}

/**
 * Print script tag (defer) for a manifest entry.
 * Example: echo asset_script_tag('kf-solid');
 */
function asset_script_tag(string $key, bool $defer = true): string {
    $url = asset_from_manifest($key);
    if (!$url) return '';
    return '<script' . ($defer ? ' defer' : '') . ' src="' . htmlspecialchars($url, ENT_QUOTES) . '"></script>';
}

/**
 * Print CSS link tag for a manifest entry (if manifest contains css array)
 * Example: echo asset_css_tag('kf-solid');
 */
function asset_css_tag(string $key): string {
    $m = _load_asset_manifest();
    if (!$m || !isset($m[$key])) return '';
    $cssList = $m[$key]['css'] ?? [];
    $out = '';
    foreach ($cssList as $css) {
        $url = '/assets/dist/' . ltrim($css, '/');
        $out .= '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES) . '">';
    }
    return $out;
}