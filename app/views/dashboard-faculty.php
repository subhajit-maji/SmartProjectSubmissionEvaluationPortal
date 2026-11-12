<section class="page-heading">
    <div>
        <p class="eyebrow">Faculty workspace</p>
        <h1>Assigned projects</h1>
        <p class="muted">Review submitted work and record rubric evaluations.</p>
    </div>
</section>

<section class="section">
    <?php if (!$projects): ?>
        <div class="empty-state">No projects are currently assigned to you.</div>
    <?php else: ?>
        <div class="project-chip-list">
            <?php foreach ($projects as $project): ?>
            <div class="project-chip">
                    <strong><?= e($project['title']) ?></strong>
                    <small><?= e(str_replace('_', ' ', $project['project_status'])) ?> · <?= e($project['role']) ?></small>
                <?php if ($project['role'] === 'primary_guide' && in_array($project['project_status'], ['approved', 'in_progress'], true)): ?>
                    <form class="mini-action" method="post" action="/">
                        <?= csrf_field() ?><input type="hidden" name="action" value="complete_project"><input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                        <button class="button button-quiet" type="submit">Final sign-off</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Review queue</p>
            <h2>Submissions</h2>
        </div>
    </div>
    <?php if (!$submissions): ?>
        <div class="empty-state">There are no submissions ready for review.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($submissions as $submission): ?>
                <article class="panel review-card">
                    <div class="review-head">
                        <div>
                            <p class="eyebrow"><?= e($submission['project_title']) ?></p>
                            <h3><?= e($submission['stage_name']) ?> · Version <?= e($submission['version']) ?></h3>
                        </div>
                        <span class="status status-<?= e($submission['status']) ?>">
                            <?= e(str_replace('_', ' ', $submission['status'])) ?>
                        </span>
                    </div>

                    <p class="small muted">Submitted <?= e(format_utc($submission['submitted_at'])) ?></p>
                    <p><?= e($submission['remarks'] ?: 'No submission note.') ?></p>
                    <p>
                        <a class="button button-secondary" href="/?download=<?= e($submission['file_id']) ?>">
                            Download submission
                        </a>
                    </p>

                    <?php if (!empty($submission['feedback'])): ?>
                        <div class="feedback-thread">
                            <?php foreach ($submission['feedback'] as $comment): ?>
                                <div class="feedback-entry <?= $comment['parent_id'] ? 'feedback-reply' : '' ?>">
                                    <strong><?= e($comment['user_name']) ?></strong> <small><?= e($comment['user_role']) ?> · <?= e(format_utc($comment['created_at'])) ?></small>
                                    <p><?= nl2br(e($comment['message'])) ?></p>
                                    <form class="feedback-reply-form" method="post" action="/">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="send_feedback"><input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>"><input type="hidden" name="parent_id" value="<?= e($comment['id']) ?>">
                                        <label>Reply<textarea name="message" rows="1" maxlength="5000" required></textarea></label><button class="button button-quiet" type="submit">Reply</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form class="feedback-form" method="post" action="/">
                        <?= csrf_field() ?><input type="hidden" name="action" value="send_feedback"><input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                        <label>Comment<textarea name="message" rows="2" maxlength="5000" required></textarea></label><button class="button button-quiet" type="submit">Add comment</button>
                    </form>

                    <?php if ($submission['evaluation_id']): ?>
                        <div class="evaluation-result">
                            Evaluation recorded: <strong><?= e($submission['percentage']) ?>%</strong> · Grade <?= e($submission['grade']) ?>
                        </div>
                    <?php elseif ($submission['guide_role'] !== 'primary_guide'): ?>
                        <p class="small muted">Only the active primary guide can finalize an evaluation.</p>
                    <?php elseif (in_array($submission['status'], ['submitted', 'resubmitted', 'under_review'], true)): ?>
                        <?php $criteria = $criteriaByStage[(int) $submission['stage_id']] ?? []; ?>
                        <?php if (!$criteria): ?>
                            <p class="notice notice-warning">No active rubric is configured for this stage.</p>
                        <?php else: ?>
                            <form class="evaluation-form" method="post" action="/">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="evaluate_submission">
                                <input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                                <h4>Rubric evaluation</h4>
                                <?php foreach ($criteria as $criterion): ?>
                                    <label class="score-row">
                                        <span>
                                            <?= e($criterion['name']) ?>
                                            <small>Weight <?= e($criterion['weight']) ?>% · Max <?= e($criterion['max_marks']) ?></small>
                                        </span>
                                        <input
                                            type="number"
                                            name="marks[<?= e($criterion['id']) ?>]"
                                            min="0"
                                            max="<?= e($criterion['max_marks']) ?>"
                                            step="0.01"
                                            required
                                        >
                                    </label>
                                <?php endforeach; ?>
                                <label>
                                    Feedback
                                    <textarea name="comments" rows="3" maxlength="5000" placeholder="Provide constructive feedback for the students..."></textarea>
                                </label>
                                <label>
                                    Decision
                                    <select name="decision" required>
                                        <option value="approved">Approve</option>
                                        <?php if ((int) $submission['is_special_revision'] !== 1): ?><option value="revision_required">Request revision</option><?php endif; ?>
                                        <option value="rejected">Reject</option>
                                    </select>
                                </label>
                                <button class="button button-primary" type="submit">Finalize evaluation</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
