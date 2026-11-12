<section class="narrow">
    <p class="eyebrow">Account recovery</p>
    <?php if (!$validToken): ?>
        <div class="panel"><h1>This reset link expired</h1><p class="muted">Request a new link to continue.</p><a class="button button-primary" href="/?page=forgot">Request a new link</a></div>
    <?php else: ?>
        <h1>Choose a new password</h1>
        <p class="muted">Use 12–72 bytes with uppercase and lowercase letters, a number, and a symbol.</p>
        <form class="panel form-grid" method="post" action="/">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="token" value="<?= e($token) ?>">
            <label class="span-2">New password<input type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <label class="span-2">Confirm password<input type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <div class="span-2"><button class="button button-primary" type="submit">Update password</button></div>
        </form>
    <?php endif; ?>
</section>
