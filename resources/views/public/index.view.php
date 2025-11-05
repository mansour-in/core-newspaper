<?php
/** @var string $appName */
/** @var array<int, App\Models\Newspaper> $newspapers */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.5/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 min-h-screen">
    <header class="bg-white shadow">
        <div class="max-w-4xl mx-auto px-4 py-6">
            <h1 class="text-3xl font-semibold text-slate-800"><?= htmlspecialchars($appName) ?></h1>
            <p class="text-slate-500 mt-2">Always-on redirects to the latest newspaper issues.</p>
        </div>
    </header>
    <main class="max-w-4xl mx-auto px-4 py-10">
        <div class="grid gap-6 sm:grid-cols-2">
            <?php foreach ($newspapers as $paper): ?>
                <a href="/<?= htmlspecialchars($paper->slug()) ?>/" class="block bg-white shadow hover:shadow-lg transition rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-slate-800 capitalize"><?= htmlspecialchars($paper->slug()) ?></h2>
                        <span class="px-3 py-1 text-sm rounded-full bg-slate-100 text-slate-600 uppercase"><?= htmlspecialchars($paper->type()) ?></span>
                    </div>
                    <dl class="mt-4 space-y-1 text-sm text-slate-500">
                        <div class="flex justify-between">
                            <dt>Last redirect</dt>
                            <dd class="text-right truncate max-w-[12rem]" title="<?= htmlspecialchars((string) $paper->lastRedirectUrl()) ?>">
                                <?= htmlspecialchars((string) $paper->lastRedirectUrl() ?: '—') ?>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Cutover hour</dt>
                            <dd><?= htmlspecialchars((string) $paper->cutoverHour()) ?>:00</dd>
                        </div>
                    </dl>
                </a>
            <?php endforeach; ?>
        </div>
    </main>
    <footer class="text-center text-xs text-slate-500 py-6">
        &copy; <?= date('Y') ?> Core Life. All rights reserved.
    </footer>
</body>
</html>
