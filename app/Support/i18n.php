<?php
declare(strict_types=1);

/**
 * Simple i18n helper
 * - language files live at: resources/lang/{locale}.php
 * - locale is read from session dms_locale or cookie 'dms_locale', default 'en'
 *
 * Save as: app/Support/i18n.php
 */

function current_locale(): string {
    return (string)($_SESSION['dms_locale'] ?? $_COOKIE['dms_locale'] ?? 'en');
}

function _lang_path(string $locale): string {
    // resources/lang location relative to this file (app/Support -> project_root/resources/lang)
    return __DIR__ . '/../../resources/lang/' . $locale . '.php';
}

function load_lang(string $locale): array {
    static $cache = [];
    if (isset($cache[$locale])) return $cache[$locale];
    $path = _lang_path($locale);
    if (!is_file($path)) {
        $cache[$locale] = [];
        return $cache[$locale];
    }
    $arr = (array) include $path;
    $cache[$locale] = $arr;
    return $arr;
}

/**
 * Translate helper
 * Usage: __('My Profile')   -> returns translated string if available, else the key
 * Supports simple :variable interpolation via $vars.
 */
function __($key, array $vars = []): string {
    $locale = current_locale();
    $lang = load_lang($locale);
    $txt = $lang[$key] ?? $key;
    if ($vars) {
        foreach ($vars as $k => $v) {
            $txt = str_replace(':' . $k, (string)$v, $txt);
        }
    }
    return (string)$txt;
}