<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Http;
use App\Kernel;
use JsonException;

final class AuthController
{
    private string $cacheFile;

    public function __construct(private readonly Kernel $kernel)
    {
        $this->cacheFile = __DIR__ . '/../../../storage/cache/login_attempts.json';
    }

    public function showLogin(): void
    {
        $token = $this->kernel->generateCsrfToken();
        echo $this->kernel->render('admin/login', [
            'csrfName' => $this->kernel->csrfTokenName(),
            'csrfToken' => $token,
            'appName' => $this->kernel->config('name'),
            'errors' => $this->kernel->consumeFlash(),
        ]);
    }

    public function login(): void
    {
        $username = (string) ($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($this->isRateLimited($username)) {
            $this->kernel->flash('error', 'Too many login attempts. Please try again later.');
            Http::redirect('/admin/login');
            return;
        }

        $expectedUser = $this->kernel->security('admin')['username'];
        $expectedHash = $this->kernel->security('admin')['password_hash'];

        if (hash_equals($expectedUser, $username) && password_verify($password, (string) $expectedHash)) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_username'] = $username;
            $this->clearAttempts($username);
            Http::redirect('/admin');
            return;
        }

        $this->recordAttempt($username);
        $this->kernel->flash('error', 'Invalid credentials.');
        Http::redirect('/admin/login');
        return;
    }

    public function logout(): void
    {
        unset($_SESSION['admin_authenticated'], $_SESSION['admin_username']);
        session_regenerate_id(true);
        Http::redirect('/admin/login');
        return;
    }

    private function isRateLimited(string $username): bool
    {
        $attempts = $this->readAttempts();
        $key = $this->attemptKey($username);
        if (!isset($attempts[$key])) {
            return false;
        }

        [$count, $expires] = $attempts[$key];
        if (time() > $expires) {
            unset($attempts[$key]);
            $this->writeAttempts($attempts);
            return false;
        }

        $limit = (int) $this->kernel->security('login')['rate_limit'];
        return $count >= $limit;
    }

    private function recordAttempt(string $username): void
    {
        $attempts = $this->readAttempts();
        $key = $this->attemptKey($username);
        $window = (int) $this->kernel->security('login')['window'];
        $expires = time() + $window;
        if (!isset($attempts[$key]) || time() > (int) $attempts[$key][1]) {
            $attempts[$key] = [1, $expires];
        } else {
            $attempts[$key][0] = ((int) $attempts[$key][0]) + 1;
            $attempts[$key][1] = $expires;
        }
        $this->writeAttempts($attempts);
    }

    private function clearAttempts(string $username): void
    {
        $attempts = $this->readAttempts();
        $key = $this->attemptKey($username);
        unset($attempts[$key]);
        $this->writeAttempts($attempts);
    }

    private function attemptKey(string $username): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return hash('sha256', $username . '|' . $ip);
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    private function readAttempts(): array
    {
        if (!is_file($this->cacheFile)) {
            return [];
        }

        $content = file_get_contents($this->cacheFile);
        if ($content === false || trim($content) === '') {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array{0: int, 1: int}> $attempts
     */
    private function writeAttempts(array $attempts): void
    {
        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0755, true);
        }
        file_put_contents($this->cacheFile, json_encode($attempts));
    }
}
