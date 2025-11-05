<?php
/** @var string $appName */
/** @var string $csrfName */
/** @var string $csrfToken */
/** @var array<int, array{type: string, message: string}> $flashes */
/** @var array<int, App\Models\Newspaper> $newspapers */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> &middot; Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.5/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-white shadow">
    <div class="max-w-6xl mx-auto px-4 py-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-semibold text-slate-800">Admin Dashboard</h1>
            <p class="text-slate-500 mt-1">Manage redirect targets and monitor health.</p>
        </div>
        <form method="POST" action="/admin/logout">
            <input type="hidden" name="<?= htmlspecialchars($csrfName) ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" class="inline-flex items-center bg-slate-800 text-white px-4 py-2 rounded-md hover:bg-slate-700">Logout</button>
        </form>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-10 space-y-6">
    <?php foreach ($flashes as $flash): ?>
        <div class="rounded-md px-4 py-3 text-sm <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endforeach; ?>
    <div class="grid gap-6 md:grid-cols-2">
        <?php foreach ($newspapers as $paper): ?>
            <section class="bg-white shadow rounded-lg p-6">
                <header class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-800 capitalize"><?= htmlspecialchars($paper->slug()) ?></h2>
                        <p class="text-sm text-slate-500">Type: <?= htmlspecialchars($paper->type()) ?></p>
                    </div>
                    <span class="px-3 py-1 text-sm rounded-full bg-slate-100 text-slate-600 uppercase">ID <?= htmlspecialchars((string) $paper->localLatestId() ?: '—') ?></span>
                </header>
                <dl class="mt-4 space-y-2 text-sm text-slate-600">
                    <div class="flex justify-between">
                        <dt>Base / Pattern</dt>
                        <dd class="text-right max-w-[14rem] truncate" title="<?= htmlspecialchars((string) ($paper->baseUrl() ?? $paper->pattern())) ?>"><?= htmlspecialchars((string) ($paper->baseUrl() ?? $paper->pattern())) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Provider latest</dt>
                        <dd><?= htmlspecialchars((string) ($paper->providerLatestId() ?? '—')) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Last increment</dt>
                        <dd><?= htmlspecialchars((string) ($paper->lastIncrementKsa()?->format('Y-m-d') ?? '—')) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Last redirect URL</dt>
                        <dd class="text-right max-w-[14rem] truncate" title="<?= htmlspecialchars((string) $paper->lastRedirectUrl()) ?>"><?= htmlspecialchars((string) ($paper->lastRedirectUrl() ?? '—')) ?></dd>
                    </div>
                </dl>
                <?php if ($paper->type() === App\Models\Newspaper::TYPE_SEQUENCE): ?>
                    <div class="mt-6 space-y-2">
                        <form method="POST" action="/admin/newspapers/<?= htmlspecialchars($paper->slug()) ?>/increment" class="inline">
                            <input type="hidden" name="<?= htmlspecialchars($csrfName) ?>" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-md" type="submit">+1</button>
                        </form>
                        <form method="POST" action="/admin/newspapers/<?= htmlspecialchars($paper->slug()) ?>/decrement" class="inline">
                            <input type="hidden" name="<?= htmlspecialchars($csrfName) ?>" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button class="bg-amber-500 hover:bg-amber-400 text-white px-4 py-2 rounded-md" type="submit">-1</button>
                        </form>
                        <form method="POST" action="/admin/newspapers/<?= htmlspecialchars($paper->slug()) ?>/set" class="mt-2 flex items-center gap-2">
                            <input type="hidden" name="<?= htmlspecialchars($csrfName) ?>" value="<?= htmlspecialchars($csrfToken) ?>">
                            <label class="text-sm text-slate-600" for="value-<?= htmlspecialchars($paper->slug()) ?>">Set exact</label>
                            <input class="w-24 rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500" type="number" min="0" name="value" id="value-<?= htmlspecialchars($paper->slug()) ?>" value="<?= htmlspecialchars((string) $paper->localLatestId()) ?>">
                            <button class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-md" type="submit">Update</button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
