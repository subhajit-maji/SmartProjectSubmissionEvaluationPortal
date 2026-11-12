<section class="page-heading">
    <div>
        <p class="eyebrow">Student workspace</p>
        <h1>Your project</h1>
        <p class="muted">Track proposal approval, milestones, and submissions.</p>
    </div>
</section>

<?php
$activeProjects = array_values(array_filter($projects, static fn(array $p): bool => $p['status'] !== 'rejected'));
$hasActiveProject = count($activeProjects) > 0;
?>

<?php if (!$hasActiveProject): ?>
    <?php if ($projects): ?>
        <div class="notice notice-warning">
            Your previous project proposal was rejected. You may submit a new project proposal below.
        </div>
    <?php endif; ?>

    <section class="narrow">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Start here</p>
                <h2>Submit a project proposal</h2>
            </div>
        </div>
        <form class="panel form-grid" method="post" action="/">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_project">
            <label class="span-2">
                Project title
                <input name="title" maxlength="200" placeholder="e.g. Autonomous Campus Navigation System" required>
            </label>
            <label class="span-2">
                Abstract
                <textarea name="abstract" rows="6" maxlength="20000" placeholder="Describe the problem, objectives, and proposed approach..." required></textarea>
            </label>
            <div class="span-2">
                <button class="button button-primary" type="submit">Send for approval</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php foreach ($projects as $project): ?>
    <section class="panel project-summary">
        <div class="project-title">
            <div>
                <div class="status status-<?= e($project['status']) ?>">
                    <?= e(str_replace('_', ' ', $project['status'])) ?>
                </div>
                <h2><?= e($project['title']) ?></h2>
            </div>
            <span class="role-pill"><?= e($project['member_role']) ?></span>
        </div>

        <p><?= nl2br(e($project['abstract'])) ?></p>
        <?php if ($project['status'] === 'completed'): ?><p class="evaluation-result">Project completed <?= e(format_utc($project['completed_at'])) ?><?php if ($project['completion_remarks']): ?><br><?= nl2br(e($project['completion_remarks'])) ?><?php endif; ?></p><?php endif; ?>
        <?php if ($project['member_role'] === 'leader' && in_array($project['status'], ['pending_approval', 'rejected'], true)): ?>
            <details class="proposal-editor">
                <summary><?= $project['status'] === 'rejected' ? 'Revise and resubmit proposal' : 'Edit pending proposal' ?></summary>
                <form class="form-grid" method="post" action="/">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_project_proposal"><input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                    <label class="span-2">Project title<input name="title" maxlength="200" value="<?= e($project['title']) ?>" required></label>
                    <label class="span-2">Abstract<textarea name="abstract" rows="5" maxlength="20000" required><?= e($project['abstract']) ?></textarea></label>
                    <div class="span-2"><button class="button button-secondary" type="submit"><?= $project['status'] === 'rejected' ? 'Resubmit proposal' : 'Save proposal' ?></button></div>
                </form>
            </details>
        <?php endif; ?>

        <h3>Team</h3>
        <div class="member-row">
            <?php foreach ($project['members'] as $member): ?>
                <span class="team-member">
                    <?= e($member['name']) ?>
                    <small><?= e($member['role']) ?></small>
                    <?php if ($project['member_role'] === 'leader' && $member['role'] === 'member' && $project['status'] !== 'completed'): ?>
                        <form method="post" action="/">
                            <?= csrf_field() ?><input type="hidden" name="action" value="remove_project_member"><input type="hidden" name="project_id" value="<?= e($project['id']) ?>"><input type="hidden" name="student_id" value="<?= e($member['student_id']) ?>">
                            <button class="button button-quiet" type="submit">Remove</button>
                        </form>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>

        <?php if ($project['member_role'] === 'leader' && in_array($project['status'], ['pending_approval', 'approved', 'in_progress'], true)): ?>
            <form class="inline-form member-form" method="post" action="/">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_project_member">
                <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                <label>
                    Add student by registration number
                    <input name="registration_no" maxlength="50" placeholder="Registration number" required>
                </label>
                <button class="button button-secondary" type="submit">Add member</button>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($project['status'] !== 'rejected'): ?>
        <section class="section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Milestones</p>
                    <h2>Submission stages</h2>
                </div>
            </div>
            <div class="stage-grid">
                <?php foreach ($project['stages'] as $stage):
                    $stageSubmissions = array_values(array_filter(
                        $project['submissions'],
                        static fn(array $s): bool => (int) $s['stage_id'] === (int) $stage['id']
                    ));
                    $latest = $stageSubmissions[0] ?? null;
                    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                    $specialDraft = $latest && (int) $latest['is_special_revision'] === 1 && $latest['status'] === 'draft';
                    $opened = $specialDraft || !$stage['starts_at'] || $now >= new DateTimeImmutable($stage['starts_at'], new DateTimeZone('UTC'));
                    $beforeDeadline = $specialDraft || !$stage['deadline_at'] || $now <= new DateTimeImmutable($stage['deadline_at'], new DateTimeZone('UTC'));
                    $revisionAllowed = !$latest || $latest['status'] === 'revision_required' || $specialDraft;
                    $canSubmit = $project['member_role'] === 'leader'
                        && in_array($project['status'], ['approved', 'in_progress'], true)
                        && $opened
                        && $beforeDeadline
                        && $revisionAllowed;
                ?>
                    <article class="panel stage-card">
                        <div class="stage-top">
                            <span class="stage-number"><?= e($stage['sort_order']) ?></span>
                            <span class="status <?= $latest ? 'status-' . e($latest['status']) : 'status-empty' ?>">
                                <?= $latest ? e(str_replace('_', ' ', $latest['status'])) : 'Not submitted' ?>
                            </span>
                        </div>

                        <h3><?= e($stage['name']) ?></h3>
                        <p><?= e($stage['description']) ?></p>

                        <p class="small muted">
                            Opens: <?= e(format_utc($stage['starts_at'])) ?><br>
                            Deadline: <?= e(format_utc($stage['deadline_at'])) ?>
                        </p>

                        <?php if ($latest): ?>
                            <p class="small">
                                Version <?= e($latest['version']) ?> · <?= e(format_utc($latest['submitted_at'])) ?>
                                <?php if ($latest['grade']): ?>
                                    <br><strong>Grade <?= e($latest['grade']) ?></strong> · <?= e($latest['percentage']) ?>%
                                <?php endif; ?>
                            </p>
                            <?php if ($latest['evaluation_comments']): ?>
                                <p class="review-note">
                                    <strong>Faculty feedback</strong><br>
                                    <?= nl2br(e($latest['evaluation_comments'])) ?>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($canSubmit): ?>
                            <form class="upload-form" method="post" action="/" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="submit_stage">
                                <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                                <input type="hidden" name="stage_id" value="<?= e($stage['id']) ?>">
                                <label>
                                    <?= $specialDraft ? 'Special revision document' : ($latest ? 'Revision document' : 'Document') ?>
                                    <input type="file" name="file" accept=".pdf,.docx,.zip" required>
                                </label>
                                <label>
                                    Note (optional)
                                    <textarea name="remarks" rows="2" maxlength="5000" placeholder="Brief summary of submitted work..."></textarea>
                                </label>
                                <button class="button button-primary" type="submit">
                                    <?= $specialDraft ? 'Submit special revision' : ($latest ? 'Submit revision' : 'Submit stage') ?>
                                </button>
                            </form>
                        <?php elseif ($project['member_role'] !== 'leader'): ?>
                            <p class="small muted">Only the team leader can upload stage submissions.</p>
                        <?php endif; ?>

                        <?php if ($stageSubmissions): ?>
                            <div class="submission-list">
                                <h4>Submission history</h4>
                                <?php foreach ($stageSubmissions as $submission): ?>
                                    <p>
                                        <?php if ($submission['file_id']): ?><a href="/?download=<?= e($submission['file_id']) ?>">Version <?= e($submission['version']) ?></a><?php else: ?>Version <?= e($submission['version']) ?> · waiting for upload<?php endif; ?>
                                        <?= (int) $submission['is_special_revision'] === 1 ? '<span class="role-pill">Special revision</span>' : '' ?>
                                        · <span class="status status-<?= e($submission['status']) ?>"><?= e(str_replace('_', ' ', $submission['status'])) ?></span>
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
                                    <?php if ($submission['appeal_status']): ?>
                                        <p class="small muted">Appeal <?= e($submission['appeal_status']) ?>. <?= e($submission['appeal_decision_reason'] ?? '') ?></p>
                                    <?php elseif ($project['member_role'] === 'leader' && $latest && (int) $latest['id'] === (int) $submission['id'] && $submission['status'] === 'rejected' && (int) $submission['is_special_revision'] === 0): ?>
                                        <form class="feedback-form" method="post" action="/">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="request_submission_appeal"><input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                                            <label>Appeal reason<textarea name="appeal_reason" rows="3" minlength="10" maxlength="5000" required></textarea></label><button class="button button-secondary" type="submit">Appeal this decision</button>
                                        </form>
                                    <?php endif; ?>
                                    <form class="feedback-form" method="post" action="/">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="send_feedback"><input type="hidden" name="submission_id" value="<?= e($submission['id']) ?>">
                                        <label>Comment<textarea name="message" rows="2" maxlength="5000" required></textarea></label><button class="button button-quiet" type="submit">Add comment</button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>
