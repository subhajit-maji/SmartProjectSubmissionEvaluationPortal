<?php
declare(strict_types=1);

http_response_code(404);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found · Smart Project Submission &amp; Evaluation Portal</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">
<div class="auth-shell">
    <header class="auth-header">
        <a class="brand" href="/">
            <span class="brand-copy"><strong>Smart Project Submission &amp; Evaluation Portal</strong><small>Project Portal</small></span>
        </a>
    </header>
    <main class="container error-page">
        <section class="card error-card" aria-labelledby="error-title">
            <p class="error-code">404 · Page not found</p>
            <h1 id="error-title">This page isn’t available.</h1>
            <p class="muted">The address may be incorrect, or the page may have moved.</p>
            <a class="button" href="/">Return to the portal</a>
        </section>
    </main>
    <footer class="footer">Smart Project Submission &amp; Evaluation Portal</footer>
</div>
</body>
</html>
