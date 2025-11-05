<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Kernel;
use App\Helpers\Http;

final class AuthMiddleware
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function handle(callable $next): mixed
    {
        if (empty($_SESSION['admin_authenticated'])) {
            Http::redirect('/admin/login');
        }

        return $next();
    }
}
