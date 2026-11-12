<section class="page-heading"><div><p class="eyebrow">Administration</p><h1>Stage rubrics</h1><p class="muted">Active rubric weights must total 100 for each stage.</p><?php if ($stages): ?><span class="status status-<?= e($stageStatus) ?>"><?= e($stageStatus) ?></span><?php endif; ?></div><div class="button-row"><a class="button button-quiet" href="/?page=deadlines">Manage deadlines</a><a class="button button-quiet" href="/">Dashboard</a><?php if ($stages): ?><form method="post" action="/"><?= csrf_field() ?><input type="hidden" name="action" value="set_stage_status"><input type="hidden" name="stage_id" value="<?= e($stageId) ?>"><input type="hidden" name="status" value="<?= $stageStatus === 'active' ? 'inactive' : 'active' ?>"><button class="button button-secondary" type="submit"><?= $stageStatus === 'active' ? 'Deactivate stage' : 'Activate stage' ?></button></form><?php endif; ?></div></section>
<form class="stage-picker" method="get" action="/">
    <input type="hidden" name="page" value="rubrics">
    <label>Stage<select name="stage_id" required><?php foreach ($stages as $stage): ?><option value="<?= e($stage['id']) ?>" <?= (int) $stage['id'] === (int) $stageId ? 'selected' : '' ?>><?= e($stage['name']) ?><?= $stage['status'] === 'inactive' ? ' (inactive)' : '' ?></option><?php endforeach; ?></select></label>
    <button class="button button-secondary" type="submit">Load stage</button>
</form>
<?php if (!$stages): ?>
    <div class="empty-state">Create a submission stage before adding its rubric.</div>
<?php else: ?>
    <form class="panel form-grid stage-editor" method="post" action="/">
        <h2 class="span-2">Stage settings</h2>
        <?= csrf_field() ?><input type="hidden" name="action" value="update_stage"><input type="hidden" name="stage_id" value="<?= e($stageId) ?>">
        <label>Name<input name="name" maxlength="100" value="<?= e($stage['name']) ?>" required></label>
        <label>Sort order<input type="number" name="sort_order" min="0" value="<?= e($stage['sort_order']) ?>" required></label>
        <label>Default start (UTC)<input type="datetime-local" name="default_start_date" value="<?= e(datetime_local_value($stage['default_start_date'])) ?>"></label>
        <label>Default deadline (UTC)<input type="datetime-local" name="default_deadline" value="<?= e(datetime_local_value($stage['default_deadline'])) ?>"></label>
        <label>Required<select name="is_required"><option value="1" <?= (int) $stage['is_required'] === 1 ? 'selected' : '' ?>>Required</option><option value="0" <?= (int) $stage['is_required'] === 0 ? 'selected' : '' ?>>Optional</option></select></label>
        <label class="span-2">Description<textarea name="description" rows="2" maxlength="5000"><?= e($stage['description']) ?></textarea></label>
        <div class="span-2"><button class="button button-secondary" type="submit">Save stage settings</button></div>
    </form>
    <form class="panel rubric-editor" method="post" action="/">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_rubric"><input type="hidden" name="stage_id" value="<?= e($stageId) ?>">
        <div class="table-wrap"><table><thead><tr><th>Criterion</th><th>Description</th><th>Max marks</th><th>Weight (%)</th><th>Remove</th></tr></thead><tbody>
            <?php $rows = array_merge($criteria, array_fill(0, max(2, 4 - count($criteria)), null)); foreach ($rows as $index => $criterion): ?>
                <tr>
                    <td><input type="hidden" name="criteria[<?= e($index) ?>][id]" value="<?= e($criterion['id'] ?? '') ?>"><input name="criteria[<?= e($index) ?>][name]" value="<?= e($criterion['name'] ?? '') ?>" maxlength="150" placeholder="Criterion name" <?= $index < count($criteria) ? 'required' : '' ?>></td>
                    <td><input name="criteria[<?= e($index) ?>][description]" value="<?= e($criterion['description'] ?? '') ?>" maxlength="1000" placeholder="Optional guidance"></td>
                    <td><input type="number" name="criteria[<?= e($index) ?>][max_marks]" value="<?= e($criterion['max_marks'] ?? '') ?>" min="0.01" max="999.99" step="0.01" <?= $index < count($criteria) ? 'required' : '' ?>></td>
                    <td><input type="number" name="criteria[<?= e($index) ?>][weight]" value="<?= e($criterion['weight'] ?? '') ?>" min="0.01" max="100" step="0.01" <?= $index < count($criteria) ? 'required' : '' ?>></td>
                    <td><?php if ($criterion): ?><label class="check-label"><input type="checkbox" name="criteria[<?= e($index) ?>][remove]" value="1"> Deactivate</label><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <p class="small muted">Leaving a new row empty ignores it. Removed criteria stay in the database so prior evaluations keep their references.</p>
        <button class="button button-primary" type="submit">Save rubric</button>
    </form>
<?php endif; ?>
