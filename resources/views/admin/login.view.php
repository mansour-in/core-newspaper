<?php
/** @var string $appName */
/** @var string $csrfName */
/** @var string $csrfToken */
/** @var array<int, array{type: string, message: string}> $errors */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> &middot; Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.5/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-semibold text-slate-800 mb-6 text-center">Admin Login</h1>
        <?php foreach ($errors as $flash): ?>
            <div class="mb-4 rounded border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endforeach; ?>
        <form method="POST" action="/admin/login" class="space-y-4">
            <input type="hidden" name="<?= htmlspecialchars($csrfName) ?>" value="<?= htmlspecialchars($csrfToken) ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="username">Username</label>
                <input class="mt-1 block w-full rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500" type="text" name="username" id="username" autocomplete="username" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
                <input class="mt-1 block w-full rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500" type="password" name="password" id="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="w-full bg-slate-800 text-white rounded-md py-2 font-semibold hover:bg-slate-700">Sign in</button>
        </form>
    </div>
</body>
</html>
