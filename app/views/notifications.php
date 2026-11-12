<section class="page-heading">
    <div><p class="eyebrow">Updates</p><h1>Notifications</h1><p class="muted">Project activity and evaluation updates for your account.</p></div>
    <?php if ($unreadCount > 0): ?>
        <form method="post" action="/"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_notifications_read"><button class="button button-secondary" type="submit">Mark all read</button></form>
    <?php endif; ?>
</section>
<?php if (!$notifications): ?>
    <div class="empty-state">You have no notifications.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($notifications as $notification): ?>
            <article class="panel notification-card <?= (int) $notification['is_read'] === 1 ? 'notification-read' : 'notification-unread' ?>">
                <div>
                    <div class="status status-<?= e($notification['type']) ?>"><?= (int) $notification['is_read'] === 1 ? 'Read' : 'New' ?></div>
                    <h2><?= e($notification['title']) ?></h2>
                    <p><?= nl2br(e($notification['message'])) ?></p>
                    <p class="small muted"><?= e(format_utc($notification['created_at'])) ?></p>
                    <a class="button button-secondary" href="<?= e(safe_local_link($notification['link'])) ?>">Open</a>
                </div>
                <?php if ((int) $notification['is_read'] === 0): ?>
                    <form method="post" action="/"><?= csrf_field() ?><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?= e($notification['id']) ?>"><button class="button button-quiet" type="submit">Mark read</button></form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
