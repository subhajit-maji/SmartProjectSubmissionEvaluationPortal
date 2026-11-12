<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Project Portal</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">Project Portal<span>Education</span></a>
    <?php if ($user): ?>
        <div class="account">
            <a class="notification-link" href="/?page=notifications">Notifications<?php if ($unreadCount > 0): ?> <span class="notification-count"><?= e($unreadCount) ?></span><?php endif; ?></a>
            <div>
                <strong><?= e($user['name']) ?></strong>
                <small><?= e(ucfirst($user['role'])) ?></small>
            </div>
            <form method="post" action="/">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="button button-quiet" type="submit">Sign out</button>
            </form>
        </div>
    <?php endif; ?>
</header>
<main class="container">
    <?php foreach ($flashes as $notice): ?>
        <div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="footer">Smart Project Submission &amp; Evaluation Portal</footer>
</body>
</html>
