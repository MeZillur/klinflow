<?php
declare(strict_types=1);

namespace App\Services;

final class EmailRenderer
{
    /** Render an email template to HTML string. Templates live under apps/Emails/*.php */
    public static function render(string $name, array $data = []): string
    {
        $ROOT = dirname(__DIR__, 2); // project root
        $file = $ROOT . '/apps/Emails/' . $name . '.php';
        if (!is_file($file)) return '<p>Email template missing.</p>';
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}