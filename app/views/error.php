<section class="narrow">
    <div class="panel">
        <p class="eyebrow">Portal message</p>
        <h1>Something went wrong</h1>
        <p><?= e($message ?? 'The request could not be completed.') ?></p>
        <a class="button button-primary" href="/">Return to dashboard</a>
    </div>
</section>
