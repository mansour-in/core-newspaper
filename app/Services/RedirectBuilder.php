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
            Newspaper::TYPE_DATE => $this->buildDateUrl($this->requirePattern($newspaper), $ksaNow),
            Newspaper::TYPE_MONTHLY => $this->buildMonthlyUrl($this->requirePattern($newspaper), $ksaNow),
            Newspaper::TYPE_SEQUENCE => $this->buildSequenceUrl(
                $newspaper->baseUrl(),
                $this->requireSequenceId($newspaper, $sequenceId),
                $newspaper->pattern()
            ),
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

    public function buildSequenceUrl(?string $baseUrl, int $localLatestId, ?string $pattern = null): string
    {
        if ($pattern !== null && str_contains($pattern, '{id}')) {
            return strtr($pattern, ['{id}' => (string) $localLatestId]);
        }

        if ($baseUrl === null || $baseUrl === '') {
            throw new RuntimeException('Sequence newspapers require a base URL or pattern.');
        }

        $normalized = rtrim($baseUrl, '/');
        return sprintf('%s/%d/index.html', $normalized, $localLatestId);
    }

    private function requirePattern(Newspaper $newspaper): string
    {
        $pattern = $newspaper->pattern();
        if ($pattern === null || $pattern === '') {
            throw new RuntimeException(sprintf('Newspaper %s is missing a pattern.', $newspaper->slug()));
        }

        return $pattern;
    }

    private function requireSequenceId(Newspaper $newspaper, ?int $sequenceId): int
    {
        if ($sequenceId !== null) {
            return $sequenceId;
        }

        $id = $newspaper->localLatestId();
        if ($id === null) {
            throw new RuntimeException(sprintf('Newspaper %s is missing a local latest ID.', $newspaper->slug()));
        }

        return $id;
    }
}
