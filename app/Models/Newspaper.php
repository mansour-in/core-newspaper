<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

final class Newspaper
{
    public const TYPE_DATE = 'date';
    public const TYPE_SEQUENCE = 'sequence';
    public const TYPE_MONTHLY = 'monthly';

    /**
     * @var array<string, mixed>
     */
    private array $attributes;

    /**
     * @param array<string, mixed> $attributes
     */
    private function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function id(): int
    {
        return (int) $this->attributes['id'];
    }

    public function slug(): string
    {
        return (string) $this->attributes['slug'];
    }

    public function type(): string
    {
        return (string) $this->attributes['type'];
    }

    public function baseUrl(): ?string
    {
        $value = $this->attributes['base_url'];
        return $value !== null ? (string) $value : null;
    }

    public function pattern(): ?string
    {
        $value = $this->attributes['pattern'];
        return $value !== null ? (string) $value : null;
    }

    public function localLatestId(): ?int
    {
        $value = $this->attributes['local_latest_id'];
        return $value !== null ? (int) $value : null;
    }

    public function providerLatestId(): ?int
    {
        $value = $this->attributes['provider_latest_id'];
        return $value !== null ? (int) $value : null;
    }

    public function seedDateKsa(): ?DateTimeImmutable
    {
        $value = $this->attributes['seed_date_ksa'];
        if ($value === null) {
            return null;
        }

        return new DateTimeImmutable((string) $value, new DateTimeZone('Asia/Riyadh'));
    }

    public function cutoverHour(): int
    {
        return (int) $this->attributes['cutover_hour'];
    }

    public function lastIncrementKsa(): ?DateTimeImmutable
    {
        $value = $this->attributes['last_increment_ksa'];
        if ($value === null) {
            return null;
        }

        return new DateTimeImmutable((string) $value, new DateTimeZone('Asia/Riyadh'));
    }

    public function lastRedirectUrl(): ?string
    {
        $value = $this->attributes['last_redirect_url'];
        return $value !== null ? (string) $value : null;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        $value = $this->attributes['updated_at'] ?? null;
        if ($value === null) {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }

    public function setLocalLatestId(PDO $pdo, int $newId): void
    {
        $pdo->beginTransaction();
        try {
            $now = $this->now();
            $statement = $pdo->prepare('UPDATE newspapers SET local_latest_id = :id, updated_at = :updated_at WHERE id = :pk');
            $statement->execute([
                ':id' => $newId,
                ':updated_at' => $now,
                ':pk' => $this->id(),
            ]);
            $pdo->commit();
            $this->attributes['local_latest_id'] = $newId;
            $this->attributes['updated_at'] = $now;
        } catch (PDOException $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function updateLastRedirectUrl(PDO $pdo, string $url): void
    {
        $now = $this->now();
        $statement = $pdo->prepare('UPDATE newspapers SET last_redirect_url = :url, updated_at = :updated_at WHERE id = :pk');
        $statement->execute([
            ':url' => $url,
            ':updated_at' => $now,
            ':pk' => $this->id(),
        ]);
        $this->attributes['last_redirect_url'] = $url;
        $this->attributes['updated_at'] = $now;
    }

    public function refresh(PDO $pdo): self
    {
        $fresh = self::findById($pdo, $this->id());
        if ($fresh === null) {
            throw new RuntimeException('Newspaper not found during refresh.');
        }

        return $fresh;
    }

    public function markIncremented(PDO $pdo, DateTimeImmutable $day, int $newId): void
    {
        $now = $this->now();
        $statement = $pdo->prepare('UPDATE newspapers SET local_latest_id = :id, last_increment_ksa = :day, updated_at = :updated_at WHERE id = :pk');
        $statement->execute([
            ':id' => $newId,
            ':day' => $day->format('Y-m-d'),
            ':updated_at' => $now,
            ':pk' => $this->id(),
        ]);
        $this->attributes['local_latest_id'] = $newId;
        $this->attributes['last_increment_ksa'] = $day->format('Y-m-d');
        $this->attributes['updated_at'] = $now;
    }

    public function rollbackIncrement(PDO $pdo, int $previousId): void
    {
        $now = $this->now();
        $statement = $pdo->prepare('UPDATE newspapers SET local_latest_id = :id, updated_at = :updated_at WHERE id = :pk');
        $statement->execute([
            ':id' => $previousId,
            ':updated_at' => $now,
            ':pk' => $this->id(),
        ]);
        $this->attributes['local_latest_id'] = $previousId;
        $this->attributes['updated_at'] = $now;
    }

    public static function findBySlug(PDO $pdo, string $slug): ?self
    {
        $statement = $pdo->prepare('SELECT * FROM newspapers WHERE slug = :slug LIMIT 1');
        $statement->execute([':slug' => $slug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new self($row);
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $statement = $pdo->prepare('SELECT * FROM newspapers WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new self($row);
    }

    /**
     * @return array<int, Newspaper>
     */
    public static function all(PDO $pdo): array
    {
        $statement = $pdo->query('SELECT * FROM newspapers ORDER BY slug');
        $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        return array_map(static fn (array $row): self => new self($row), $rows);
    }

    public static function create(PDO $pdo, array $data): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format('Y-m-d H:i:s');
        $statement = $pdo->prepare('INSERT INTO newspapers (slug, type, base_url, pattern, local_latest_id, provider_latest_id, seed_date_ksa, cutover_hour, last_increment_ksa, last_redirect_url, created_at, updated_at) VALUES (:slug, :type, :base_url, :pattern, :local_latest_id, :provider_latest_id, :seed_date_ksa, :cutover_hour, :last_increment_ksa, :last_redirect_url, :created_at, :updated_at)');
        $statement->execute([
            ':slug' => $data['slug'],
            ':type' => $data['type'],
            ':base_url' => $data['base_url'] ?? null,
            ':pattern' => $data['pattern'] ?? null,
            ':local_latest_id' => $data['local_latest_id'] ?? null,
            ':provider_latest_id' => $data['provider_latest_id'] ?? null,
            ':seed_date_ksa' => $data['seed_date_ksa'] ?? null,
            ':cutover_hour' => $data['cutover_hour'] ?? 8,
            ':last_increment_ksa' => $data['last_increment_ksa'] ?? null,
            ':last_redirect_url' => $data['last_redirect_url'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function asStatusRow(DateTimeImmutable $now): array
    {
        $lastIncrement = $this->lastIncrementKsa();
        $daysSinceIncrement = null;
        if ($lastIncrement !== null) {
            $daysSinceIncrement = (int) $lastIncrement->diff($now)->format('%a');
        }

        return [
            'slug' => $this->slug(),
            'type' => $this->type(),
            'local_latest_id' => $this->localLatestId(),
            'provider_latest_id' => $this->providerLatestId(),
            'last_increment_ksa' => $lastIncrement?->format('Y-m-d'),
            'days_since_increment' => $daysSinceIncrement,
            'last_redirect_url' => $this->lastRedirectUrl(),
        ];
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format('Y-m-d H:i:s');
    }
}
