<section class="page-heading"><div><p class="eyebrow">Administration</p><h1>Batch stage deadlines</h1><p class="muted">All dates are entered and stored in UTC. Empty dates inherit the stage defaults.</p></div><a class="button button-quiet" href="/">Back to dashboard</a></section>
<section class="panel stage-create">
    <h2>Create a stage</h2><p class="small muted">New stages start inactive. Add a rubric totaling 100, then activate the stage in Rubric settings.</p>
    <form class="form-grid" method="post" action="/">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_stage">
        <label>Stage name<input name="name" maxlength="100" required></label><label>Required<select name="is_required"><option value="1">Required</option><option value="0">Optional</option></select></label>
        <label>Default start (UTC)<input type="datetime-local" name="default_start_date"></label><label>Default deadline (UTC)<input type="datetime-local" name="default_deadline"></label>
        <label class="span-2">Description<textarea name="description" rows="2" maxlength="5000"></textarea></label>
        <div class="span-2"><button class="button button-primary" type="submit">Create inactive stage</button></div>
    </form>
</section>
<?php if (!$batches || !$stages): ?>
    <div class="empty-state">Add an active batch and at least one stage to manage deadlines.</div>
<?php else: ?>
    <div class="deadline-list">
        <?php foreach ($batches as $batch): ?>
            <section class="section"><div class="section-heading"><div><p class="eyebrow">Batch</p><h2><?= e($batch['name']) ?></h2></div></div>
                <div class="card-list">
                    <?php foreach ($stages as $stage): $key = (int) $batch['id'] . ':' . (int) $stage['id']; $override = $byKey[$key] ?? null; ?>
                        <form class="panel deadline-form" method="post" action="/">
                            <?= csrf_field() ?><input type="hidden" name="action" value="save_deadline"><input type="hidden" name="batch_id" value="<?= e($batch['id']) ?>"><input type="hidden" name="stage_id" value="<?= e($stage['id']) ?>">
                            <div><h3><?= e($stage['name']) ?></h3><p class="small muted"><?= e($stage['status'] ?? 'active') ?> · Default: <?= e(format_utc($stage['default_start_date'])) ?> → <?= e(format_utc($stage['default_deadline'])) ?></p></div>
                            <label>Start (UTC)<input type="datetime-local" name="start_date" value="<?= e(datetime_local_value($override['start_date'] ?? null)) ?>"></label>
                            <label>Deadline (UTC)<input type="datetime-local" name="deadline" value="<?= e(datetime_local_value($override['deadline'] ?? null)) ?>"></label>
                            <label>Required<select name="is_required"><option value="">Inherit (<?= (int) $stage['is_required'] === 1 ? 'required' : 'optional' ?>)</option><option value="1" <?= isset($override['is_required']) && (int) $override['is_required'] === 1 ? 'selected' : '' ?>>Required</option><option value="0" <?= isset($override['is_required']) && (int) $override['is_required'] === 0 ? 'selected' : '' ?>>Optional</option></select></label>
                            <button class="button button-secondary" type="submit">Save</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
