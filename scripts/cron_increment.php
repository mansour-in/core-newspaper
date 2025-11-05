<?php

declare(strict_types=1);

use App\Models\Newspaper;
use App\Services\RedirectBuilder;
use App\Services\SequenceService;
use DateTimeImmutable;
use DateTimeZone;

$kernel = require __DIR__ . '/../bootstrap/app.php';

$pdo = $kernel->getPdo();
/** @var SequenceService $sequenceService */
$sequenceService = $kernel->make(SequenceService::class);
/** @var RedirectBuilder $redirectBuilder */
$redirectBuilder = $kernel->make(RedirectBuilder::class);

$logging = $kernel->security('logging') ?? [];
$cronLog = is_array($logging) && isset($logging['cron_path'])
    ? (string) $logging['cron_path']
    : __DIR__ . '/../storage/logs/cron.log';

$cronDirectory = dirname($cronLog);
if (!is_dir($cronDirectory)) {
    mkdir($cronDirectory, 0755, true);
}
if (!is_file($cronLog)) {
    touch($cronLog);
}

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh'));

$papers = Newspaper::all($pdo);
foreach ($papers as $paper) {
    if ($paper->type() !== Newspaper::TYPE_SEQUENCE) {
        continue;
    }

    $beforeId = (int) $paper->localLatestId();
    $updated = $sequenceService->incrementForKsaDay($paper, $now);
    $afterId = (int) $updated->localLatestId();

    if ($afterId === $beforeId) {
        continue;
    }

    $url = $redirectBuilder->buildSequenceUrl($updated->baseUrl(), $afterId, $updated->pattern());
    if (!headRequest($url)) {
        $updated->rollbackIncrement($pdo, $beforeId);
        error_log(
            sprintf('Rollback applied for %s after failing HEAD check.', $updated->slug()),
            3,
            $cronLog
        );
        continue;
    }

    $updated->updateLastRedirectUrl($pdo, $url);
}

function headRequest(string $url): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $stream = @fopen($url, 'r', false, $context);
    if ($stream === false) {
        return false;
    }
    $meta = stream_get_meta_data($stream);
    fclose($stream);
    if (!isset($meta['wrapper_data']) || !is_array($meta['wrapper_data'])) {
        return false;
    }
    foreach ($meta['wrapper_data'] as $header) {
        if (preg_match('#HTTP/\d\.\d\s+(\d{3})#', (string) $header, $matches) === 1) {
            $code = (int) $matches[1];
            return $code < 400;
        }
    }
    return false;
}
