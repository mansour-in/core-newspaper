<?php

declare(strict_types=1);

defined('PHPUNIT_RUNNING') || define('PHPUNIT_RUNNING', true);

use App\Models\Newspaper;
use App\Services\RedirectBuilder;
use PHPUnit\Framework\TestCase;

final class RedirectBuilderTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE newspapers (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, type TEXT, base_url TEXT, pattern TEXT, local_latest_id INTEGER, provider_latest_id INTEGER, seed_date_ksa TEXT, cutover_hour INTEGER DEFAULT 8, last_increment_ksa TEXT, last_redirect_url TEXT, created_at TEXT, updated_at TEXT)');

        Newspaper::create($this->pdo, [
            'slug' => 'okaz',
            'type' => Newspaper::TYPE_DATE,
            'pattern' => 'https://www.okaz.com.sa/digitals/{Y}/{m}/{d}/index.html',
        ]);

        Newspaper::create($this->pdo, [
            'slug' => 'ring',
            'type' => Newspaper::TYPE_MONTHLY,
            'pattern' => 'https://ringmagazine.com/en/magazines/{month_year}/view',
        ]);

        Newspaper::create($this->pdo, [
            'slug' => 'arabnews',
            'type' => Newspaper::TYPE_SEQUENCE,
            'base_url' => 'https://example.com/pdf',
            'local_latest_id' => 10,
        ]);

        Newspaper::create($this->pdo, [
            'slug' => 'aawsat',
            'type' => Newspaper::TYPE_SEQUENCE,
            'base_url' => 'https://aawsat.com/files/pdf/issue',
            'pattern' => 'https://aawsat.com/files/pdf/issue{id}/',
            'local_latest_id' => 200,
        ]);
    }

    public function testBuildDateUrl(): void
    {
        $paper = Newspaper::findBySlug($this->pdo, 'okaz');
        self::assertNotNull($paper);
        $builder = new RedirectBuilder($this->pdo);
        $date = new DateTimeImmutable('2025-01-15');
        $url = $builder->buildFor($paper, $date);
        self::assertSame('https://www.okaz.com.sa/digitals/2025/01/15/index.html', $url);
    }

    public function testBuildMonthlyUrl(): void
    {
        $paper = Newspaper::findBySlug($this->pdo, 'ring');
        self::assertNotNull($paper);
        $builder = new RedirectBuilder($this->pdo);
        $date = new DateTimeImmutable('2025-11-01');
        $url = $builder->buildFor($paper, $date);
        self::assertSame('https://ringmagazine.com/en/magazines/november-2025/view', $url);
    }

    public function testBuildSequenceUrlUpdatesLastRedirect(): void
    {
        $paper = Newspaper::findBySlug($this->pdo, 'arabnews');
        self::assertNotNull($paper);
        $builder = new RedirectBuilder($this->pdo);
        $date = new DateTimeImmutable('now');
        $url = $builder->buildFor($paper, $date, 11);
        self::assertSame('https://example.com/pdf/11/index.html', $url);
        $fresh = Newspaper::findBySlug($this->pdo, 'arabnews');
        self::assertSame($url, $fresh?->lastRedirectUrl());
    }

    public function testSequencePatternReplacesIdPlaceholder(): void
    {
        $paper = Newspaper::findBySlug($this->pdo, 'aawsat');
        self::assertNotNull($paper);
        $builder = new RedirectBuilder($this->pdo);
        $url = $builder->buildFor($paper, new DateTimeImmutable('now'));
        self::assertSame('https://aawsat.com/files/pdf/issue200/', $url);
    }
}
