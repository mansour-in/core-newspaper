<?php

declare(strict_types=1);

defined('PHPUNIT_RUNNING') || define('PHPUNIT_RUNNING', true);

use App\Models\Newspaper;
use App\Services\SequenceService;
use PHPUnit\Framework\TestCase;

final class SequenceServiceTest extends TestCase
{
    private PDO $pdo;
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE newspapers (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, type TEXT, base_url TEXT, pattern TEXT, local_latest_id INTEGER, provider_latest_id INTEGER, seed_date_ksa TEXT, cutover_hour INTEGER DEFAULT 8, last_increment_ksa TEXT, last_redirect_url TEXT, created_at TEXT, updated_at TEXT)');

        $this->logPath = sys_get_temp_dir() . '/sequence_test.log';
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        Newspaper::create($this->pdo, [
            'slug' => 'arabnews',
            'type' => Newspaper::TYPE_SEQUENCE,
            'base_url' => 'https://example.com/pdf',
            'local_latest_id' => 100,
            'last_increment_ksa' => '2025-01-01',
        ]);
    }

    public function testIncrementForNewDay(): void
    {
        $service = new SequenceService($this->pdo, $this->logPath);
        $paper = Newspaper::findBySlug($this->pdo, 'arabnews');
        self::assertNotNull($paper);

        $today = new DateTimeImmutable('2025-01-02', new DateTimeZone('Asia/Riyadh'));
        $updated = $service->incrementForKsaDay($paper, $today);
        self::assertSame(101, $updated->localLatestId());
        self::assertSame('2025-01-02', $updated->lastIncrementKsa()?->format('Y-m-d'));
    }

    public function testIncrementSkipsWhenAlreadyUpdated(): void
    {
        $service = new SequenceService($this->pdo, $this->logPath);
        $paper = Newspaper::findBySlug($this->pdo, 'arabnews');
        self::assertNotNull($paper);

        $today = new DateTimeImmutable('2025-01-01', new DateTimeZone('Asia/Riyadh'));
        $updated = $service->incrementForKsaDay($paper, $today);
        self::assertSame(100, $updated->localLatestId());
    }

    public function testCutoverReturnsPreviousIdBeforeThreshold(): void
    {
        $service = new SequenceService($this->pdo, $this->logPath);
        $paper = Newspaper::findBySlug($this->pdo, 'arabnews');
        self::assertNotNull($paper);

        $beforeCutover = new DateTimeImmutable('2025-01-02 06:00:00', new DateTimeZone('Asia/Riyadh'));
        self::assertSame(99, $service->applyCutover($paper, $beforeCutover));

        $afterCutover = new DateTimeImmutable('2025-01-02 10:00:00', new DateTimeZone('Asia/Riyadh'));
        self::assertSame(100, $service->applyCutover($paper, $afterCutover));
    }
}
