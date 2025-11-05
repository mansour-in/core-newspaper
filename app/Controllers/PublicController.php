<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Http;
use App\Kernel;
use App\Models\Newspaper;
use App\Services\RedirectBuilder;
use App\Services\SequenceService;
use DateTimeImmutable;
use DateTimeZone;

final class PublicController
{
    private DateTimeZone $ksaTimezone;

    public function __construct(
        private readonly Kernel $kernel,
        private readonly RedirectBuilder $redirectBuilder,
        private readonly SequenceService $sequenceService
    ) {
        $this->ksaTimezone = new DateTimeZone('Asia/Riyadh');
    }

    public function index(): void
    {
        $papers = Newspaper::all($this->kernel->getPdo());
        echo $this->kernel->render('public/index', [
            'appName' => $this->kernel->config('name'),
            'newspapers' => $papers,
        ]);
    }

    public function redirect(string $slug): void
    {
        $paper = Newspaper::findBySlug($this->kernel->getPdo(), $slug);
        if ($paper === null) {
            http_response_code(404);
            echo 'Newspaper not found';
            return;
        }

        $now = new DateTimeImmutable('now', $this->ksaTimezone);

        $sequenceId = null;
        if ($paper->type() === Newspaper::TYPE_SEQUENCE) {
            $sequenceId = $this->sequenceService->applyCutover($paper, $now);
        }

        $url = $this->redirectBuilder->buildFor($paper, $now, $sequenceId);
        Http::redirect($url);
    }
}
