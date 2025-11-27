<?php
declare(strict_types=1);

namespace Modules\Shared\Controllers;

use PDO;

trait LookupTrait
{
    /** Clamp & sanitize pagination size */
    protected function clampLimit(mixed $v, int $min=1, int $max=50, int $def=30): int {
        $n = (int)($v ?? $def);
        return max($min, min($max, $n));
    }

    /** Normalized search text and like parameter */
    protected function normalizeSearch(?string $q): array {
        $q = trim((string)$q);
        return [$q, '%'.$q.'%'];
    }

    /** Like filter with explicit collation to avoid “Illegal mix of collations” */
    protected function likeClause(string $col, string $param = ':like', string $collation = 'utf8mb4_unicode_ci'): string {
        // Both column and param coerced to same collation
        return "CONVERT($col USING utf8mb4) COLLATE $collation LIKE CONVERT($param USING utf8mb4) COLLATE $collation";
    }

    /** Small helper to emit JSON (using BaseController::json) */
    protected function jsonItems(array $rows, ?string $nextCursor = null): void {
        $payload = ['items' => $rows];
        if ($nextCursor !== null && $nextCursor !== '') $payload['next_cursor'] = $nextCursor;
        $this->json($payload);
    }

    /** Optional opaque cursor encode/decode (base64 json) */
    protected function encodeCursor(array $c): string { return rtrim(strtr(base64_encode(json_encode($c)), '+/', '-_'), '='); }
    protected function decodeCursor(?string $s): array {
        if (!$s) return [];
        $js = json_decode(base64_decode(strtr($s, '-_', '+/')), true);
        return is_array($js) ? $js : [];
    }
}