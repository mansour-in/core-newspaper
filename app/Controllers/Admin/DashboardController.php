<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Http;
use App\Kernel;
use App\Models\Newspaper;
use PDO;

final class DashboardController
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): void
    {
        $token = $this->kernel->generateCsrfToken();
        $papers = Newspaper::all($this->kernel->getPdo());
        echo $this->kernel->render('admin/dashboard', [
            'csrfName' => $this->kernel->csrfTokenName(),
            'csrfToken' => $token,
            'appName' => $this->kernel->config('name'),
            'flashes' => $this->kernel->consumeFlash(),
            'newspapers' => $papers,
        ]);
    }

    public function increment(string $slug): void
    {
        $this->adjust($slug, 1);
    }

    public function decrement(string $slug): void
    {
        $this->adjust($slug, -1);
    }

    public function set(string $slug): void
    {
        $value = (int) ($_POST['value'] ?? 0);
        $paper = $this->resolveSequencePaper($slug);
        if ($paper === null) {
            return;
        }

        $paper->setLocalLatestId($this->kernel->getPdo(), max(0, $value));
        $this->kernel->flash('success', 'Latest ID updated.');
        Http::redirect('/admin');
    }

    private function adjust(string $slug, int $delta): void
    {
        $paper = $this->resolveSequencePaper($slug);
        if ($paper === null) {
            return;
        }

        $current = (int) $paper->localLatestId();
        $updated = max(0, $current + $delta);
        $paper->setLocalLatestId($this->kernel->getPdo(), $updated);
        $this->kernel->flash('success', 'Latest ID adjusted.');
        Http::redirect('/admin');
    }

    private function resolveSequencePaper(string $slug): ?Newspaper
    {
        $paper = Newspaper::findBySlug($this->kernel->getPdo(), $slug);
        if ($paper === null || $paper->type() !== Newspaper::TYPE_SEQUENCE) {
            $this->kernel->flash('error', 'Newspaper not found or not adjustable.');
            Http::redirect('/admin');
            return null;
        }

        return $paper;
    }
}
