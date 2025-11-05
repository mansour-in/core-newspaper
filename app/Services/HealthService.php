<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Newspaper;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class HealthService
{
    private DateTimeZone $ksaTimezone;

    public function __construct(private readonly PDO $pdo)
    {
        $this->ksaTimezone = new DateTimeZone('Asia/Riyadh');
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $now = new DateTimeImmutable('now', $this->ksaTimezone);
        $newspapers = Newspaper::all($this->pdo);

        $sequencePending = [];
        foreach ($newspapers as $paper) {
            if ($paper->type() === Newspaper::TYPE_SEQUENCE) {
                $last = $paper->lastIncrementKsa();
                if ($last !== null && (int) $last->diff($now)->format('%a') > 1) {
                    $sequencePending[] = $paper->slug();
                }
            }
        }

        return [
            'timestamp' => $now->format(DATE_ATOM),
            'count' => count($newspapers),
            'sequence_pending' => $sequencePending,
            'newspapers' => array_map(static fn (Newspaper $paper): array => $paper->asStatusRow($now), $newspapers),
        ];
    }
}
