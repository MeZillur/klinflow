<?php
declare(strict_types=1);

/**
 * API helpers for JSON responses and lookup formatting.
 * Save as: app/Support/api_helpers.php
 *
 * Usage:
 *   require_once __DIR__ . '/api_helpers.php';
 *   // get rows from DB or other source then:
 *   respond_lookup($rows);
 */

if (!function_exists('json_response')) {
    function json_response($data, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('api_ok')) {
    function api_ok(array $data = []): void {
        json_response(['ok' => true, 'data' => $data], 200);
    }
}

if (!function_exists('api_error')) {
    function api_error(string $message = 'error', int $status = 400, array $extra = []): void {
        $payload = ['ok' => false, 'error' => $message];
        if ($extra) $payload['extra'] = $extra;
        json_response($payload, $status);
    }
}

/**
 * Standardize a raw DB row/object into the lookup item shape:
 *  { id, label, name, code, sku, price?, meta:{...} }
 */
if (!function_exists('format_lookup_item')) {
    function format_lookup_item($row): array {
        $r = is_array($row) ? $row : (is_object($row) ? (array)$row : ['value' => $row]);
        $id = $r['id'] ?? $r['value'] ?? null;
        $label = $r['label'] ?? $r['name'] ?? $r['title'] ?? ($r['code'] ?? (string)$id);
        $name = $r['name'] ?? $r['label'] ?? '';
        $code = $r['code'] ?? $r['sku'] ?? '';
        $item = [
            'id' => $id,
            'label' => (string)$label,
            'name' => (string)$name,
            'code' => (string)$code,
        ];
        if (isset($r['price'])) $item['price'] = $r['price'];
        // meta: copy over remaining keys except id/label/name/code/sku/price
        $meta = [];
        foreach ($r as $k => $v) {
            if (in_array($k, ['id','label','name','code','sku','price'], true)) continue;
            $meta[$k] = $v;
        }
        $item['meta'] = $meta;
        return $item;
    }
}

/**
 * Respond with lookup payload:
 * { items: [ {id,label,name,code,price?,meta:{}}, ... ], meta: { total?, q? } }
 */
if (!function_exists('respond_lookup')) {
    function respond_lookup(array $rows, array $meta = []): void {
        $items = [];
        foreach ($rows as $r) $items[] = format_lookup_item($r);
        $payload = ['items' => array_values($items)];
        if ($meta) $payload['meta'] = $meta;
        json_response($payload, 200);
    }
}

/**
 * Simple helper to read a query param with default
 */
if (!function_exists('qp')) {
    function qp(string $key, $default = null) {
        if (isset($_GET[$key])) return $_GET[$key];
        if (isset($_POST[$key])) return $_POST[$key];
        return $default;
    }
}