<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Newspaper;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class RedirectBuilder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buildFor(Newspaper $newspaper, DateTimeImmutable $ksaNow, ?int $sequenceId = null): string
    {
        $url = match ($newspaper->type()) {
            Newspaper::TYPE_DATE => $this->buildDateUrl((string) $newspaper->pattern(), $ksaNow),
            Newspaper::TYPE_MONTHLY => $this->buildMonthlyUrl((string) $newspaper->pattern(), $ksaNow),
            Newspaper::TYPE_SEQUENCE => $this->buildSequenceUrl((string) $newspaper->baseUrl(), $sequenceId ?? (int) $newspaper->localLatestId()),
            default => throw new RuntimeException('Unsupported newspaper type.'),
        };

        $newspaper->updateLastRedirectUrl($this->pdo, $url);

        return $url;
    }

    public function buildDateUrl(string $pattern, DateTimeImmutable $ksaNow): string
    {
        $replacements = [
            '{Y}' => $ksaNow->format('Y'),
            '{m}' => $ksaNow->format('m'),
            '{d}' => $ksaNow->format('d'),
        ];

        return strtr($pattern, $replacements);
    }

    public function buildMonthlyUrl(string $pattern, DateTimeImmutable $ksaNow): string
    {
        $monthYear = strtolower($ksaNow->format('F-Y'));
        $monthYear = str_replace(' ', '-', $monthYear);

        return strtr($pattern, ['{month_year}' => $monthYear]);
    }

    public function buildSequenceUrl(string $baseUrl, int $localLatestId): string
    {
        $normalized = rtrim($baseUrl, '/');
        return sprintf('%s/%d/index.html', $normalized, $localLatestId);
    }
}
