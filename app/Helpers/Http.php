<?php

declare(strict_types=1);

namespace App\Helpers;

final class Http
{
    public static function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            $_SERVER['__redirect_url'] = $url;
            $_SERVER['__redirect_status'] = $status;
            return;
        }
        exit;
    }

    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload !== false) {
            echo $payload;
        }
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            $_SERVER['__json_payload'] = $payload;
            $_SERVER['__json_status'] = $status;
            return;
        }
        exit;
    }
}
