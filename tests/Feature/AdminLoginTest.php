<?php

declare(strict_types=1);

defined('PHPUNIT_RUNNING') || define('PHPUNIT_RUNNING', true);

use App\Controllers\Admin\AuthController;
use App\Kernel;
use PHPUnit\Framework\TestCase;

final class AdminLoginTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE newspapers (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, type TEXT, base_url TEXT, pattern TEXT, local_latest_id INTEGER, provider_latest_id INTEGER, seed_date_ksa TEXT, cutover_hour INTEGER DEFAULT 8, last_increment_ksa TEXT, last_redirect_url TEXT, created_at TEXT, updated_at TEXT)');

        $logPath = sys_get_temp_dir() . '/admin_login.log';
        if (!is_file($logPath)) {
            touch($logPath);
        }
        if (!is_dir(dirname(__DIR__, 2) . '/storage/cache')) {
            mkdir(dirname(__DIR__, 2) . '/storage/cache', 0755, true);
        }

        $config = [
            'name' => 'Test App',
            'timezone' => 'Asia/Riyadh',
            'env' => 'testing',
            'debug' => true,
        ];

        $this->kernel = new Kernel($pdo, $config, [
            'session' => [
                'name' => 'test',
                'lifetime' => 3600,
                'secure' => false,
                'httponly' => true,
                'same_site' => 'Lax',
            ],
            'csrf' => [
                'ttl' => 3600,
                'token_name' => '_csrf',
            ],
            'login' => [
                'rate_limit' => 1,
                'window' => 600,
            ],
            'admin' => [
                'username' => 'admin',
                'password_hash' => password_hash('secret', PASSWORD_BCRYPT),
            ],
            'logging' => [
                'path' => $logPath,
                'cron_path' => $logPath,
            ],
        ], $logPath);
    }

    public function testSuccessfulLoginSetsSession(): void
    {
        $controller = new AuthController($this->kernel);
        $_POST = ['username' => 'admin', 'password' => 'secret'];
        $controller->login();
        self::assertTrue($_SESSION['admin_authenticated']);
        self::assertSame('/admin', $_SERVER['__redirect_url']);
    }

    public function testRateLimitBlocksAfterThreshold(): void
    {
        $controller = new AuthController($this->kernel);
        $_POST = ['username' => 'admin', 'password' => 'wrong'];
        $controller->login();
        $_POST = ['username' => 'admin', 'password' => 'wrong'];
        $controller->login();

        ob_start();
        $controller->showLogin();
        $output = ob_get_clean();
        self::assertStringContainsString('Too many login attempts', (string) $output);
    }
}
