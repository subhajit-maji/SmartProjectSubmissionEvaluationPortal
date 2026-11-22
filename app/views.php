<?php
declare(strict_types=1);

function page_start(string $title, ?array $user): void
{
    $notice = take_flash();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
        <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body class="<?= $user ? 'portal-body' : 'auth-body' ?>">
    <?php if ($user): ?>
        <header class="topbar">
            <a class="brand" href="?"><?= e(APP_NAME) ?></a>
            <div class="account">
                <span><?= e($user['name']) ?> · <?= e(ucfirst($user['role'])) ?></span>
                <form method="post" action="">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button class="button button-quiet" type="submit">Sign out</button>
                </form>
            </div>
        </header>
        <main class="container">
    <?php else: ?>
        <div class="auth-shell">
            <header class="auth-header">
                <a class="brand" href="?">
                    <span class="brand-copy"><strong>Project Portal</strong><small>Education</small></span>
                </a>
            </header>
            <main class="container">
    <?php endif; ?>
        <?php if ($notice): ?><p class="notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php
}

function page_end(bool $authenticated): void
{
    ?>
    </main>
    <footer class="footer"><?= e(APP_NAME) ?></footer>
    <?php if (!$authenticated): ?></div><?php endif; ?>
    </body>
    </html>
    <?php
}

function render_page(PDO $pdo): void
{
    $user = current_user($pdo);
    if ($user === null) {
        render_login();
        return;
    }

    page_start('Dashboard', $user);
    if ($user['role'] === 'admin') {
        render_admin_dashboard($pdo);
    } elseif ($user['role'] === 'faculty') {
        render_faculty_dashboard($pdo, $user);
    } else {
        render_student_dashboard($pdo, $user);
    }
    page_end(true);
}

function render_login(): void
{
    page_start('Sign in', null);
    ?>
    <section class="login-shell">
        <div class="intro-panel">
            <p class="eyebrow">Project workspace</p>
            <h1>Move your projects from proposal to presentation.</h1>
            <p>Students submit project proposals and reports. Administrators assign faculty guides, and faculty return feedback or approve reports.</p>
        </div>
        <section class="card login-panel">
            <h2>Sign in to your account</h2>
            <p class="muted">Use the account provided by your portal administrator.</p>
            <form method="post" action="" class="form-stack">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="login">
                <label>Email address<input type="email" name="email" autocomplete="username" maxlength="190" required autofocus></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
                <button class="button button-wide" type="submit">Continue</button>
                <p class="small muted">Need help signing in? Contact your portal administrator.</p>
            </form>
        </section>
    </section>
    <?php
    page_end(false);
}

function render_admin_dashboard(PDO $pdo): void
{
    $pending = $pdo->query(
        "SELECT p.id, p.title, p.description, p.created_at, u.name AS student_name
        FROM projects p
        JOIN users u ON u.id = p.student_id
        WHERE p.status = 'pending'
        ORDER BY p.created_at"
    )->fetchAll();
    $faculty = $pdo->query(
        "SELECT id, name
        FROM users
        WHERE role = 'faculty'
        ORDER BY name"
    )->fetchAll();
    $projects = $pdo->query(
        "SELECT p.title, p.status, s.name AS student_name, f.name AS faculty_name
        FROM projects p
        JOIN users s ON s.id = p.student_id
        LEFT JOIN users f ON f.id = p.faculty_id
        ORDER BY p.created_at DESC
        LIMIT 20"
    )->fetchAll();
    ?>
    <div class="page-heading">
        <p class="eyebrow">Administrator</p>
        <h1>Manage project proposals</h1>
        <p>Approve a proposal, assign its faculty guide, and create student or faculty accounts.</p>
    </div>
    <div class="two-column">
        <section class="card">
            <h2>Create an account</h2>
            <form method="post" class="form-stack">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create_user">
                <label>Name<input name="name" maxlength="120" required></label>
                <label>Email<input type="email" name="email" maxlength="190" required></label>
                <label>Role<select name="role" required><option value="student">Student</option><option value="faculty">Faculty</option></select></label>
                <label>Temporary password<input type="password" name="password" minlength="8" required></label>
                <button class="button" type="submit">Create account</button>
            </form>
        </section>
        <section class="card">
            <h2>Proposals waiting for review</h2>
            <?php if (!$pending): ?>
                <p class="muted">No proposals are waiting.</p>
            <?php endif; ?>
            <?php foreach ($pending as $project): ?>
                <article class="record">
                    <p class="eyebrow"><?= e($project['student_name']) ?></p>
                    <h3><?= e($project['title']) ?></h3>
                    <p><?= nl2br(e($project['description'])) ?></p>
                    <form method="post" class="form-stack">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="decide_project">
                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                        <label>
                            Faculty guide
                            <select name="faculty_id">
                                <option value="">Choose faculty</option>
                                <?php foreach ($faculty as $member): ?>
                                    <option value="<?= (int) $member['id'] ?>"><?= e($member['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="button-row">
                            <button class="button" name="decision" value="approved" type="submit">Approve</button>
                            <button class="button button-secondary" name="decision" value="rejected" type="submit">Reject</button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
    <section class="card section-gap">
        <h2>Recent projects</h2>
        <?php if (!$projects): ?>
            <p class="muted">No projects have been submitted yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Project</th><th>Student</th><th>Guide</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?= e($project['title']) ?></td>
                                <td><?= e($project['student_name']) ?></td>
                                <td><?= e($project['faculty_name'] ?? 'Not assigned') ?></td>
                                <td><span class="badge"><?= e(ucfirst($project['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function render_student_dashboard(PDO $pdo, array $user): void
{
    $projectQuery = $pdo->prepare(
        'SELECT p.*, f.name AS faculty_name
        FROM projects p
        LEFT JOIN users f ON f.id = p.faculty_id
        WHERE p.student_id = ?
        ORDER BY p.created_at DESC'
    );
    $projectQuery->execute([(int) $user['id']]);
    $projects = $projectQuery->fetchAll();
    ?>
    <div class="page-heading">
        <p class="eyebrow">Student workspace</p>
        <h1>Your projects</h1>
        <p>Submit a proposal, then upload one report after your guide is assigned and the proposal is approved.</p>
    </div>
    <section class="card">
        <h2>New project proposal</h2>
        <form method="post" class="form-stack">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="create_project">
            <label>Project title<input name="title" minlength="5" maxlength="160" required></label>
            <label>Short description<textarea name="description" rows="4" required></textarea></label>
            <button class="button" type="submit">Send proposal</button>
        </form>
    </section>
    <?php foreach ($projects as $project): ?>
        <?php render_student_project($pdo, $project); ?>
    <?php endforeach; ?>
    <?php if (!$projects): ?><p class="muted section-gap">Your proposals will appear here.</p><?php endif; ?>
    <?php
}

function render_student_project(PDO $pdo, array $project): void
{
    $submissionQuery = $pdo->prepare('SELECT * FROM submissions WHERE project_id = ?');
    $submissionQuery->execute([(int) $project['id']]);
    $submission = $submissionQuery->fetch();
    $feedback = [];
    if ($submission) {
        $feedbackQuery = $pdo->prepare(
            'SELECT f.comment, f.created_at, u.name AS faculty_name
            FROM feedback f
            JOIN users u ON u.id = f.faculty_id
            WHERE f.submission_id = ?
            ORDER BY f.created_at DESC'
        );
        $feedbackQuery->execute([(int) $submission['id']]);
        $feedback = $feedbackQuery->fetchAll();
    }
    ?>
    <section class="card section-gap">
        <div class="record-heading">
            <div>
                <p class="eyebrow">Project</p>
                <h2><?= e($project['title']) ?></h2>
            </div>
            <span class="badge"><?= e(ucfirst($project['status'])) ?></span>
        </div>
        <p><?= nl2br(e($project['description'])) ?></p>
        <p class="muted">Faculty guide: <?= e($project['faculty_name'] ?? 'Not assigned') ?></p>
        <?php if ($project['status'] === 'approved' && (!$submission || $submission['status'] === 'revision')): ?>
            <hr>
            <h3><?= $submission ? 'Upload revised report' : 'Submit your report' ?></h3>
            <form method="post" enctype="multipart/form-data" class="form-stack">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="submit_report">
                <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                <label>Report PDF<input type="file" name="report" accept="application/pdf,.pdf" required></label>
                <label>Remarks<textarea name="remarks" maxlength="1000" rows="3"></textarea></label>
                <button class="button" type="submit">Submit report</button>
            </form>
        <?php elseif ($submission): ?>
            <p>Report status: <span class="badge"><?= e(ucfirst($submission['status'])) ?></span></p>
            <p><a class="button button-secondary" href="?download=<?= (int) $submission['id'] ?>">Download your report</a></p>
        <?php elseif ($project['status'] === 'pending'): ?>
            <p class="muted">Your proposal is waiting for an administrator’s decision.</p>
        <?php else: ?>
            <p class="muted">This proposal was not approved. You can submit a new proposal above.</p>
        <?php endif; ?>
        <?php if ($feedback): ?>
            <div class="feedback-list">
                <h3>Faculty feedback</h3>
                <?php foreach ($feedback as $item): ?>
                    <article>
                        <p><?= nl2br(e($item['comment'])) ?></p>
                        <small><?= e($item['faculty_name']) ?> · <?= e($item['created_at']) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function render_faculty_dashboard(PDO $pdo, array $user): void
{
    $statement = $pdo->prepare(
        "SELECT p.id AS project_id, p.title, p.description, p.status AS project_status,
            s.id AS submission_id, s.original_name, s.remarks,
            s.status AS submission_status, s.submitted_at, u.name AS student_name
        FROM projects p
        JOIN users u ON u.id = p.student_id
        LEFT JOIN submissions s ON s.project_id = p.id
        WHERE p.faculty_id = ?
        ORDER BY p.created_at DESC"
    );
    $statement->execute([(int) $user['id']]);
    $projects = $statement->fetchAll();
    ?>
    <div class="page-heading">
        <p class="eyebrow">Faculty workspace</p>
        <h1>Assigned projects</h1>
        <p>Review student reports and leave one clear decision with feedback.</p>
    </div>
    <?php if (!$projects): ?>
        <section class="card">
            <p class="muted">No projects have been assigned to you.</p>
        </section>
    <?php endif; ?>
    <?php foreach ($projects as $project): ?>
        <section class="card section-gap">
            <p class="eyebrow">
                <?= e($project['student_name']) ?> · <?= e(ucfirst($project['project_status'])) ?>
            </p>
            <h2><?= e($project['title']) ?></h2>
            <p><?= nl2br(e($project['description'])) ?></p>
            <?php if ($project['submission_id']): ?>
                <hr>
                <div class="record-heading">
                    <h3><?= e($project['original_name']) ?></h3>
                    <span class="badge"><?= e(ucfirst($project['submission_status'])) ?></span>
                </div>
                <p><?= nl2br(e($project['remarks'])) ?></p>
                <p><a class="button button-secondary" href="?download=<?= (int) $project['submission_id'] ?>">Download report</a></p>
                <?php if ($project['submission_status'] === 'submitted'): ?>
                    <form method="post" class="form-stack">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="review_submission">
                        <input type="hidden" name="submission_id" value="<?= (int) $project['submission_id'] ?>">
                        <label>Feedback<textarea name="comment" rows="4" maxlength="4000" required></textarea></label>
                        <div class="button-row">
                            <button class="button button-secondary" name="decision" value="revision" type="submit">Request revision</button>
                            <button class="button" name="decision" value="approved" type="submit">Approve report</button>
                        </div>
                    </form>
                <?php endif; ?>
                <?php render_feedback_list($pdo, (int) $project['submission_id']); ?>
            <?php else: ?>
                <p class="muted">The student has not submitted a report yet.</p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <?php
}

function render_feedback_list(PDO $pdo, int $submissionId): void
{
    $statement = $pdo->prepare('SELECT comment, created_at FROM feedback WHERE submission_id = ? ORDER BY created_at DESC');
    $statement->execute([$submissionId]);
    $items = $statement->fetchAll();
    if (!$items) {
        return;
    }
    ?>
    <div class="feedback-list">
        <h3>Previous feedback</h3>
        <?php foreach ($items as $item): ?>
            <article>
                <p><?= nl2br(e($item['comment'])) ?></p>
                <small><?= e($item['created_at']) ?></small>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

