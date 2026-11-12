<section class="narrow">
    <p class="eyebrow">Account recovery</p>
    <h1>Reset your password</h1>
    <p class="muted">Enter your account email. If it matches an active account, we’ll send a reset link.</p>
    <form class="panel form-grid" method="post" action="/">
        <?= csrf_field() ?><input type="hidden" name="action" value="request_password_reset">
        <label class="span-2">Email address<input type="email" name="email" maxlength="150" autocomplete="email" required></label>
        <div class="span-2"><button class="button button-primary" type="submit">Send reset link</button> <a class="button button-quiet" href="/?page=login">Back to sign in</a></div>
    </form>
</section>
