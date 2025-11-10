<?php

declare(strict_types=1);

defined('PHPUNIT_RUNNING') || define('PHPUNIT_RUNNING', true);

use App\Controllers\Admin\DashboardController;
use App\Kernel;
use App\Models\Newspaper;
use PHPUnit\Framework\TestCase;

final class UpdateIdTest extends TestCase
{
    private Kernel $kernel;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        $_POST = [];

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE newspapers (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, type TEXT, base_url TEXT, pattern TEXT, local_latest_id INTEGER, provider_latest_id INTEGER, seed_date_ksa TEXT, cutover_hour INTEGER DEFAULT 8, last_increment_ksa TEXT, last_redirect_url TEXT, created_at TEXT, updated_at TEXT)');

        Newspaper::create($this->pdo, [
            'slug' => 'arabnews',
            'type' => Newspaper::TYPE_SEQUENCE,
            'base_url' => 'https://example.com/pdf',
            'local_latest_id' => 10,
        ]);

        $logPath = sys_get_temp_dir() . '/update_id.log';
        if (!is_file($logPath)) {
            touch($logPath);
        }

        $config = [
            'name' => 'Test App',
            'timezone' => 'Asia/Riyadh',
            'env' => 'testing',
            'debug' => true,
        ];

        $this->kernel = new Kernel($this->pdo, $config, [
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
                'rate_limit' => 5,
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

    public function testIncrementAdjustsLocalId(): void
    {
        $controller = new DashboardController($this->kernel);
        $controller->increment('arabnews');
        $paper = Newspaper::findBySlug($this->pdo, 'arabnews');
        self::assertSame(11, $paper?->localLatestId());
        self::assertSame('/admin', $_SERVER['__redirect_url']);
    }

    public function testSetExactUpdatesValue(): void
    {
        $controller = new DashboardController($this->kernel);
        $_POST = ['value' => 42];
        $controller->set('arabnews');
        $paper = Newspaper::findBySlug($this->pdo, 'arabnews');
        self::assertSame(42, $paper?->localLatestId());
    }
}
