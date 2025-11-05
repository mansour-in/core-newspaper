<?php

declare(strict_types=1);

namespace App;

use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Services\HealthService;
use App\Services\RedirectBuilder;
use App\Services\SequenceService;
use PDO;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

final class Kernel
{
    private array $routes = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, string>
     */
    private array $middlewareMap = [
        'auth' => AuthMiddleware::class,
        'csrf' => CsrfMiddleware::class,
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config,
        private readonly array $security,
        private readonly string $logPath
    ) {
        $this->instances[PDO::class] = $pdo;
        $this->instances[self::class] = $this;
        $this->instances[RedirectBuilder::class] = new RedirectBuilder($pdo);
        $this->instances[SequenceService::class] = new SequenceService($pdo, $logPath);
        $this->instances[HealthService::class] = new HealthService($pdo);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function security(string $key, mixed $default = null): mixed
    {
        return $this->security[$key] ?? $default;
    }

    public function register(string $method, string $pattern, string|callable $action, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $this->compilePattern($pattern),
            'action' => $action,
            'middleware' => $middleware,
        ];
    }

    public function handle(string $uri, string $method): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) === 1) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }

                $this->dispatch($route['action'], $params, $route['middleware']);
                return;
            }
        }

        http_response_code(404);
        echo 'Not Found';
    }

    public function view(string $view, array $data = []): void
    {
        $path = __DIR__ . '/../resources/views/' . $view . '.view.php';
        if (!is_file($path)) {
            throw new RuntimeException("View {$view} not found");
        }

        extract($data, EXTR_SKIP);
        include $path;
    }

    public function render(string $view, array $data = []): string
    {
        ob_start();
        $this->view($view, $data);
        return (string) ob_get_clean();
    }

    public function make(string $class): object
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $exception) {
            throw new RuntimeException("Unable to resolve {$class}", 0, $exception);
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            $instance = new $class();
        } else {
            $parameters = [];
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type === null) {
                    throw new RuntimeException('Unable to resolve untyped dependency.');
                }
                $parameters[] = $this->make($type->getName());
            }
            $instance = $reflection->newInstanceArgs($parameters);
        }

        $this->instances[$class] = $instance;
        return $instance;
    }

    public function csrfTokenName(): string
    {
        return $this->security['csrf']['token_name'];
    }

    public function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf'][$token] = time();
        $this->purgeOldTokens();
        return $token;
    }

    public function validateCsrfToken(string $token): bool
    {
        $this->purgeOldTokens();
        if (isset($_SESSION['csrf'][$token])) {
            unset($_SESSION['csrf'][$token]);
            return true;
        }

        return false;
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public function consumeFlash(): array
    {
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }

    private function dispatch(string|callable $action, array $params, array $middleware): void
    {
        $handler = function () use ($action, $params): void {
            if (is_callable($action)) {
                $action(...array_values($params));
                return;
            }

            if (is_string($action) && str_contains($action, '@')) {
                [$class, $method] = explode('@', $action, 2);
                $controller = $this->make($class);
                $controller->{$method}(...array_values($params));
                return;
            }

            throw new RuntimeException('Invalid route action.');
        };

        $pipeline = array_reverse($middleware);
        $next = $handler;
        foreach ($pipeline as $name) {
            $middlewareInstance = $this->make($this->middlewareMap[$name] ?? $name);
            $next = fn () => $middlewareInstance->handle($next);
        }

        $next();
    }

    private function compilePattern(string $pattern): string
    {
        $regex = preg_replace('#\{([^}/]+)\}#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function purgeOldTokens(): void
    {
        $ttl = $this->security['csrf']['ttl'];
        $now = time();
        $tokens = $_SESSION['csrf'] ?? [];
        foreach ($tokens as $token => $timestamp) {
            if (!is_int($timestamp) || ($now - $timestamp) > $ttl) {
                unset($tokens[$token]);
            }
        }
        $_SESSION['csrf'] = $tokens;
    }
}
