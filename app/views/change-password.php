<section class="narrow">
    <p class="eyebrow">Account security</p>
    <h1>Change your temporary password</h1>
    <p class="muted">Set a new password before using the portal.</p>
    <form class="panel form-grid" method="post" action="/">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <label class="span-2">
            Current password
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label>
            New password
            <input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required>
        </label>
        <label>
            Confirm new password
            <input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="72" required>
        </label>
        <p class="small muted span-2">Use 12–72 bytes with uppercase and lowercase letters, a number, and a symbol.</p>
        <div class="span-2">
            <button class="button button-primary" type="submit">Save password</button>
        </div>
    </form>
</section>
