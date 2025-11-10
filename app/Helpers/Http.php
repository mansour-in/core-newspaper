<?php

declare(strict_types=1);

namespace App\Helpers;

final class Http
{
    public static function redirect(string $url, int $status = 302): void
    {
        $runningTests = defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true;

        if (!$runningTests && PHP_SAPI !== 'cli') {
            header('Location: ' . $url, true, $status);
        }

        if ($runningTests) {
            $_SERVER['__redirect_url'] = $url;
            $_SERVER['__redirect_status'] = $status;
            return;
        }
        exit;
    }

    public static function json(array $data, int $status = 200): void
    {
        $runningTests = defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true;

        if (!$runningTests && PHP_SAPI !== 'cli') {
            http_response_code($status);
            header('Content-Type: application/json');
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload !== false) {
            echo $payload;
        }
        if ($runningTests) {
            $_SERVER['__json_payload'] = $payload;
            $_SERVER['__json_status'] = $status;
            return;
        }
        exit;
    }
}
