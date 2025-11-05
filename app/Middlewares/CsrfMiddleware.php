<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Kernel;
use RuntimeException;

final class CsrfMiddleware
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function handle(callable $next): mixed
    {
        $tokenName = $this->kernel->csrfTokenName();
        $submitted = $_POST[$tokenName] ?? null;
        if (!$this->kernel->validateCsrfToken(is_string($submitted) ? $submitted : '')) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        return $next();
    }
}
