<?php

declare(strict_types=1);

use App\Models\Newspaper;
use PDO;

final class NewspaperSeeder
{
    public static function run(PDO $pdo): void
    {
        $records = [
            [
                'slug' => 'arabnews',
                'type' => Newspaper::TYPE_SEQUENCE,
                'base_url' => 'https://www.arabnews.com/sites/default/files/pdf',
                'local_latest_id' => 1000,
            ],
            [
                'slug' => 'aawsat',
                'type' => Newspaper::TYPE_SEQUENCE,
                'base_url' => 'https://aawsat.com/files/pdf/issue',
                'local_latest_id' => 1000,
            ],
            [
                'slug' => 'okaz',
                'type' => Newspaper::TYPE_DATE,
                'pattern' => 'https://www.okaz.com.sa/digitals/{Y}/{m}/{d}/index.html',
            ],
            [
                'slug' => 'ring',
                'type' => Newspaper::TYPE_MONTHLY,
                'pattern' => 'https://ringmagazine.com/en/magazines/{month_year}/view',
            ],
        ];

        foreach ($records as $record) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM newspapers WHERE slug = :slug');
            $exists->execute([':slug' => $record['slug']]);
            if ((int) $exists->fetchColumn() === 0) {
                Newspaper::create($pdo, $record);
            }
        }
    }
}
