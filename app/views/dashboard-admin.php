<section class="page-heading">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Portal overview</h1>
        <p class="muted">Review proposals, assign guides, and manage accounts.</p>
    </div>
    <div class="button-row"><a class="button button-secondary" href="/?page=rubrics">Manage rubrics</a><a class="button button-secondary" href="/?page=deadlines">Manage deadlines</a></div>
</section>

<section class="stats-grid">
    <div class="stat">
        <span>Awaiting approval</span>
        <strong><?= e($counts['pending'] ?? 0) ?></strong>
    </div>
    <div class="stat">
        <span>Projects</span>
        <strong><?= e($counts['projects'] ?? 0) ?></strong>
    </div>
    <div class="stat">
        <span>Active accounts</span>
        <strong><?= e($counts['active_users'] ?? 0) ?></strong>
    </div>
</section>

<section class="section">
    <div class="section-heading"><div><p class="eyebrow">Student appeals</p><h2>Appeals awaiting review</h2></div></div>
    <?php if (!$pendingAppeals): ?>
        <div class="empty-state">No appeals are waiting for a decision.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($pendingAppeals as $appeal): ?>
                <article class="panel project-card">
                    <div><p class="eyebrow"><?= e($appeal['project_title']) ?> · <?= e($appeal['stage_name']) ?> · Version <?= e($appeal['version']) ?></p><h3><?= e($appeal['student_name']) ?></h3><p><?= nl2br(e($appeal['appeal_reason'])) ?></p></div>
                    <form class="appeal-decision" method="post" action="/">
                        <?= csrf_field() ?><input type="hidden" name="action" value="decide_appeal"><input type="hidden" name="appeal_id" value="<?= e($appeal['id']) ?>">
                        <label>Decision<select name="decision" required><option value="approved">Approve special revision</option><option value="rejected">Reject appeal</option></select></label>
                        <label>Decision note<textarea name="decision_reason" rows="2" maxlength="5000"></textarea></label>
                        <button class="button button-primary" type="submit">Save decision</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Workflow</p>
            <h2>Project proposals</h2>
        </div>
    </div>
    <?php if (!$pending): ?>
        <div class="empty-state">No project proposals are waiting for approval.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($pending as $project): ?>
                <article class="panel project-card">
                    <div>
                        <div class="status status-pending_approval">Pending approval</div>
                        <h3><?= e($project['title']) ?></h3>
                        <p><?= nl2br(e($project['abstract'])) ?></p>
                        <p class="small muted">
                            <?= e($project['student_name']) ?> · <?= e($project['department_name']) ?> · <?= e($project['batch_name']) ?>
                        </p>
                    </div>
                    <div class="button-row">
                        <?php foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $decision => $label): ?>
                            <form method="post" action="/">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="decide_project">
                                <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                                <input type="hidden" name="decision" value="<?= e($decision) ?>">
                                <button class="button <?= $decision === 'approved' ? 'button-primary' : 'button-danger' ?>" type="submit">
                                    <?= e($label) ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Assignments</p>
            <h2>Primary guides</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Department</th>
                    <th>Current guide</th>
                    <th>Assign</th>
                    <th>Completion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allProjects as $project): ?>
                    <tr>
                        <td>
                            <strong><?= e($project['title']) ?></strong>
                            <small class="status status-<?= e($project['status']) ?>">
                                <?= e(str_replace('_', ' ', $project['status'])) ?>
                            </small>
                        </td>
                        <td><?= e($project['department_name']) ?></td>
                        <td><?= e($project['primary_guide_name'] ?? 'Unassigned') ?></td>
                        <td>
                            <?php if (in_array($project['status'], ['pending_approval', 'approved', 'in_progress'], true)): ?>
                            <form class="inline-form" method="post" action="/">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="assign_guide">
                                <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                                <select name="faculty_id" aria-label="Primary guide for <?= e($project['title']) ?>" required>
                                    <option value="">Choose faculty</option>
                                    <?php foreach ($faculty as $person): ?>
                                        <?php if ((int) $person['department_id'] === (int) $project['department_id']): ?>
                                            <option value="<?= e($person['id']) ?>" <?= (int) ($project['primary_guide_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>>
                                                <?= e($person['name']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button-secondary" type="submit">Save</button>
                            </form>
                            <?php else: ?>
                                <span class="muted">Unavailable</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array($project['status'], ['approved', 'in_progress'], true)): ?>
                                <form method="post" action="/">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="complete_project"><input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                                    <button class="button button-quiet" type="submit">Sign off</button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$allProjects): ?>
                    <tr>
                        <td colspan="5" class="muted">No projects yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section two-column">
    <div>
        <div class="section-heading">
            <div>
                <p class="eyebrow">People</p>
                <h2>Create an account</h2>
            </div>
        </div>
        <form class="panel form-grid" method="post" action="/">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">
            <label>
                Name
                <input name="name" maxlength="100" required>
            </label>
            <label>
                Email
                <input type="email" name="email" maxlength="150" required>
            </label>
            <label>
                Role
                <select name="role" required>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <label>
                Department
                <select name="department_id" required>
                    <option value="">Choose department</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= e($department['id']) ?>"><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Batch (students only)
                <select name="batch_id">
                    <option value="">Not applicable</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?= e($batch['id']) ?>"><?= e($batch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Registration / employee number
                <input name="registration_no" maxlength="50">
            </label>
            <label>
                Phone
                <input name="phone" maxlength="20">
            </label>
            <label>
                Temporary password
                <input type="password" name="password" minlength="12" maxlength="72" required>
            </label>
            <p class="small muted span-2">The new account must change this password at first sign-in.</p>
            <div class="span-2">
                <button class="button button-primary" type="submit">Create account</button>
            </div>
        </form>
    </div>
    <div>
        <div class="section-heading">
            <div>
                <p class="eyebrow">Directory</p>
                <h2>Recent accounts</h2>
            </div>
        </div>
        <div class="panel">
            <ul class="user-list">
                <?php foreach ($users as $person): ?>
                    <li>
                        <span>
                            <strong><?= e($person['name']) ?></strong>
                            <small><?= e($person['email']) ?></small>
                        </span>
                        <span class="role-pill"><?= e($person['role']) ?> · <?= e($person['status']) ?></span>
                        <?php if ((int) $person['id'] !== (int) $user['id']): ?>
                            <form method="post" action="/">
                                <?= csrf_field() ?><input type="hidden" name="action" value="set_record_status"><input type="hidden" name="entity" value="user"><input type="hidden" name="record_id" value="<?= e($person['id']) ?>">
                                <input type="hidden" name="status" value="<?= $person['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button class="button button-quiet" type="submit"><?= $person['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-heading"><div><p class="eyebrow">Organization</p><h2>Departments and batches</h2></div></div>
    <div class="two-column">
        <div class="panel">
            <h3>Create department</h3>
            <form class="inline-form" method="post" action="/">
                <?= csrf_field() ?><input type="hidden" name="action" value="create_department"><label>Name<input name="name" maxlength="100" required></label><label>Code<input name="code" maxlength="20" pattern="[A-Za-z0-9-]{2,20}" required></label><button class="button button-primary" type="submit">Add</button>
            </form>
            <ul class="user-list">
                <?php foreach ($departmentsAll as $department): ?>
                    <li>
                        <form class="organization-form" method="post" action="/">
                            <?= csrf_field() ?><input type="hidden" name="action" value="update_department"><input type="hidden" name="department_id" value="<?= e($department['id']) ?>">
                            <label>Name<input name="name" maxlength="100" value="<?= e($department['name']) ?>" required></label>
                            <label>Code<input name="code" maxlength="20" value="<?= e($department['code']) ?>" required></label>
                            <button class="button button-secondary" type="submit">Save</button>
                        </form>
                        <span class="role-pill"><?= e($department['status']) ?></span>
                        <form method="post" action="/">
                            <?= csrf_field() ?><input type="hidden" name="action" value="set_record_status"><input type="hidden" name="entity" value="department"><input type="hidden" name="record_id" value="<?= e($department['id']) ?>"><input type="hidden" name="status" value="<?= $department['status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button class="button button-quiet" type="submit"><?= $department['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="panel">
            <h3>Create batch</h3>
            <form class="inline-form" method="post" action="/">
                <?= csrf_field() ?><input type="hidden" name="action" value="create_batch"><label>Name<input name="name" maxlength="100" placeholder="2027-28" required></label><label>Start year<input type="number" name="year" min="1901" max="2155" required></label><button class="button button-primary" type="submit">Add</button>
            </form>
            <ul class="user-list">
                <?php foreach ($batchesAll as $batch): ?>
                    <li>
                        <form class="organization-form" method="post" action="/">
                            <?= csrf_field() ?><input type="hidden" name="action" value="update_batch"><input type="hidden" name="batch_id" value="<?= e($batch['id']) ?>">
                            <label>Name<input name="name" maxlength="100" value="<?= e($batch['name']) ?>" required></label>
                            <label>Year<input type="number" name="year" min="1901" max="2155" value="<?= e($batch['year']) ?>" required></label>
                            <button class="button button-secondary" type="submit">Save</button>
                        </form>
                        <span class="role-pill"><?= e($batch['status']) ?></span>
                        <form method="post" action="/">
                            <?= csrf_field() ?><input type="hidden" name="action" value="set_record_status"><input type="hidden" name="entity" value="batch"><input type="hidden" name="record_id" value="<?= e($batch['id']) ?>"><input type="hidden" name="status" value="<?= $batch['status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button class="button button-quiet" type="submit"><?= $batch['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
