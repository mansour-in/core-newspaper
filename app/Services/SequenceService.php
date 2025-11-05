<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Newspaper;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

final class SequenceService
{
    private DateTimeZone $ksaTimezone;

    public function __construct(private readonly PDO $pdo, private readonly string $logPath)
    {
        $this->ksaTimezone = new DateTimeZone('Asia/Riyadh');
    }

    public function incrementForKsaDay(Newspaper $newspaper, DateTimeImmutable $today): Newspaper
    {
        if ($newspaper->type() !== Newspaper::TYPE_SEQUENCE) {
            return $newspaper;
        }

        $todayKsa = $today->setTimezone($this->ksaTimezone)->setTime(0, 0, 0);

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT * FROM newspapers WHERE id = :id FOR UPDATE');
            $statement->execute([':id' => $newspaper->id()]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new RuntimeException('Newspaper not found while incrementing.');
            }

            $current = new Newspaper($row);
            $lastIncrement = $current->lastIncrementKsa();
            if ($lastIncrement === null) {
                $update = $this->pdo->prepare('UPDATE newspapers SET last_increment_ksa = :today, updated_at = :updated WHERE id = :id');
                $timestamp = (new DateTimeImmutable('now', $this->ksaTimezone))->format('Y-m-d H:i:s');
                $update->execute([
                    ':today' => $todayKsa->format('Y-m-d'),
                    ':updated' => $timestamp,
                    ':id' => $current->id(),
                ]);
                $this->pdo->commit();
                $this->log(sprintf('[%s] Initialized increment marker for %s', $todayKsa->format(DATE_ATOM), $current->slug()));
                return $current->refresh($this->pdo);
            }

            $diff = (int) $lastIncrement->diff($todayKsa)->format('%a');
            if ($diff <= 0) {
                $this->pdo->commit();
                return $current;
            }

            $newId = ((int) $current->localLatestId()) + $diff;

            $update = $this->pdo->prepare('UPDATE newspapers SET local_latest_id = :latest, last_increment_ksa = :today, updated_at = :updated WHERE id = :id');
            $timestamp = (new DateTimeImmutable('now', $this->ksaTimezone))->format('Y-m-d H:i:s');
            $update->execute([
                ':latest' => $newId,
                ':today' => $todayKsa->format('Y-m-d'),
                ':updated' => $timestamp,
                ':id' => $current->id(),
            ]);
            $this->pdo->commit();

            $this->log(sprintf('[%s] Incremented %s by %d to %d', $todayKsa->format(DATE_ATOM), $current->slug(), $diff, $newId));

            return $current->refresh($this->pdo);
        } catch (PDOException | RuntimeException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function applyCutover(Newspaper $newspaper, DateTimeImmutable $now): int
    {
        if ($newspaper->type() !== Newspaper::TYPE_SEQUENCE) {
            throw new RuntimeException('Cutover applicable to sequence newspapers only.');
        }

        $id = $newspaper->localLatestId();
        if ($id === null) {
            throw new RuntimeException('Missing local latest id for sequence newspaper.');
        }

        $hour = (int) $now->setTimezone($this->ksaTimezone)->format('H');
        if ($hour < $newspaper->cutoverHour()) {
            return max(0, $id - 1);
        }

        return $id;
    }

    private function log(string $message): void
    {
        $line = sprintf("%s %s\n", (new DateTimeImmutable('now', $this->ksaTimezone))->format('Y-m-d H:i:s'), $message);
        file_put_contents($this->logPath, $line, FILE_APPEND);
    }
}
