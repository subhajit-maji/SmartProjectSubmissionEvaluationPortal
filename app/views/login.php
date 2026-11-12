<section class="login-shell">
    <div class="intro-panel">
        <p class="eyebrow">Education workspace</p>
        <h1>Move projects from proposal to presentation.</h1>
        <p>Submit project work, follow review stages, and keep feedback and evaluation in one place.</p>
    </div>
    <form class="panel login-panel" method="post" action="/">
        <h2>Sign in</h2>
        <p class="muted">Use the account provided by your institution.</p>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label>
            Email address
            <input type="email" name="email" autocomplete="username" maxlength="150" required autofocus>
        </label>
        <label>
            Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="button button-primary button-wide" type="submit">Continue</button>
        <p class="small"><a href="/?page=forgot">Forgot your password?</a></p>
        <p class="small muted">Need help signing in? Contact your portal administrator.</p>
    </form>
</section>
