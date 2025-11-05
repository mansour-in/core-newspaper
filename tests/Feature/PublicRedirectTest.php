<?php

declare(strict_types=1);

defined('PHPUNIT_RUNNING') || define('PHPUNIT_RUNNING', true);

use App\Controllers\PublicController;
use App\Kernel;
use App\Models\Newspaper;
use PDO;
use PHPUnit\Framework\TestCase;

final class PublicRedirectTest extends TestCase
{
    private Kernel $kernel;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        date_default_timezone_set('Asia/Riyadh');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        $_SERVER['__redirect_url'] = null;

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE newspapers (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, type TEXT, base_url TEXT, pattern TEXT, local_latest_id INTEGER, provider_latest_id INTEGER, seed_date_ksa TEXT, cutover_hour INTEGER DEFAULT 8, last_increment_ksa TEXT, last_redirect_url TEXT, created_at TEXT, updated_at TEXT)');

        Newspaper::create($this->pdo, [
            'slug' => 'okaz',
            'type' => Newspaper::TYPE_DATE,
            'pattern' => 'https://www.okaz.com.sa/digitals/{Y}/{m}/{d}/index.html',
        ]);

        Newspaper::create($this->pdo, [
            'slug' => 'arabnews',
            'type' => Newspaper::TYPE_SEQUENCE,
            'base_url' => 'https://example.com/pdf',
            'local_latest_id' => 5,
        ]);
        $this->pdo->exec("UPDATE newspapers SET cutover_hour = 23 WHERE slug = 'arabnews'");

        $logPath = sys_get_temp_dir() . '/public_redirect.log';
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

    public function testOkazRedirectsToCurrentDate(): void
    {
        $controller = $this->kernel->make(PublicController::class);
        $controller->redirect('okaz');
        $expected = 'https://www.okaz.com.sa/digitals/' . date('Y/m/d') . '/index.html';
        self::assertSame($expected, $_SERVER['__redirect_url']);
    }

    public function testSequenceAppliesCutover(): void
    {
        $controller = $this->kernel->make(PublicController::class);
        $controller->redirect('arabnews');
        $base = 'https://example.com/pdf';
        $expected = $base . '/4/index.html';
        self::assertSame($expected, $_SERVER['__redirect_url']);
    }
}
