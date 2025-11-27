<?php
declare(strict_types=1);

namespace Shared;

final class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function redirect(string $path, int $status = 302): void
    {
        header('Location: ' . $path, true, $status);
        exit;
    }
}