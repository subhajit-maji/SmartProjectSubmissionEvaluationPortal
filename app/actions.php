<?php
declare(strict_types=1);

function handle_action(PDO $pdo): never
{
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'login':
            login_user($pdo);
        case 'logout':
            unset($_SESSION['user_id'], $_SESSION['csrf_token']);
            session_regenerate_id(true);
            set_flash('You have signed out.');
            redirect_to('?');
        case 'create_user':
            create_user($pdo);
        case 'create_project':
            create_project($pdo);
        case 'decide_project':
            decide_project($pdo);
        case 'submit_report':
            submit_report($pdo);
        case 'review_submission':
            review_submission($pdo);
        default:
            throw new AppException('That action is not available.');
    }
}

function login_user(PDO $pdo): never
{
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $statement = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $statement->execute([$email]);
    $account = $statement->fetch();

    if (!$account || !password_verify($password, $account['password_hash'])) {
        throw new AppException('Email or password is incorrect.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $account['id'];
    unset($_SESSION['csrf_token']);
    set_flash('Welcome back.');
    redirect_to('?');
}

function create_user(PDO $pdo): never
{
    require_role($pdo, 'admin');
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $role = (string) ($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || mb_strlen($name) > 120 || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AppException('Enter a name of up to 120 characters and a valid email address.');
    }
    if (!in_array($role, ['faculty', 'student'], true)) {
        throw new AppException('Choose a student or faculty account.');
    }
    if (strlen($password) < 8) {
        throw new AppException('Use a temporary password with at least 8 characters.');
    }

    $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    try {
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            throw new AppException('That email address is already in use.');
        }
        throw $error;
    }

    set_flash('Account created. Give the temporary password to its owner securely.');
    redirect_to('?');
}

function create_project(PDO $pdo): never
{
    $student = require_role($pdo, 'student');
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    if (mb_strlen($title) < 5 || mb_strlen($title) > 160 || $description === '') {
        throw new AppException('Enter a project title between 5 and 160 characters and a short description.');
    }

    $statement = $pdo->prepare('INSERT INTO projects (student_id, title, description) VALUES (?, ?, ?)');
    $statement->execute([(int) $student['id'], $title, $description]);
    set_flash('Your proposal has been sent for review.');
    redirect_to('?');
}

function decide_project(PDO $pdo): never
{
    require_role($pdo, 'admin');
    $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');
    $facultyId = filter_input(INPUT_POST, 'faculty_id', FILTER_VALIDATE_INT);
    if (!$projectId || !in_array($decision, ['approved', 'rejected'], true)) {
        throw new AppException('Choose a valid proposal decision.');
    }

    if ($decision === 'approved') {
        if (!$facultyId) {
            throw new AppException('Choose a faculty guide before approving the proposal.');
        }
        $facultyCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'faculty'");
        $facultyCheck->execute([$facultyId]);
        if (!$facultyCheck->fetch()) {
            throw new AppException('The selected account is not a faculty member.');
        }
    } else {
        $facultyId = null;
    }

    $statement = $pdo->prepare('UPDATE projects SET status = ?, faculty_id = ? WHERE id = ? AND status = \'pending\'');
    $statement->execute([$decision, $facultyId, $projectId]);
    if ($statement->rowCount() !== 1) {
        throw new AppException('That proposal is no longer waiting for review.');
    }
    set_flash('Proposal decision saved.');
    redirect_to('?');
}

function submit_report(PDO $pdo): never
{
    $student = require_role($pdo, 'student');
    $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    if (!$projectId) {
        throw new AppException('Choose a valid project.');
    }

    $projectQuery = $pdo->prepare("SELECT id, status FROM projects WHERE id = ? AND student_id = ?");
    $projectQuery->execute([$projectId, (int) $student['id']]);
    $project = $projectQuery->fetch();
    if (!$project || $project['status'] !== 'approved') {
        throw new AppException('You can submit a report after your proposal is approved.');
    }

    $existingQuery = $pdo->prepare('SELECT id, stored_name, status FROM submissions WHERE project_id = ?');
    $existingQuery->execute([$projectId]);
    $existing = $existingQuery->fetch();
    if ($existing && $existing['status'] !== 'revision') {
        throw new AppException('A report is already waiting for review or has been approved.');
    }

    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    if (mb_strlen($remarks) > 1000) {
        throw new AppException('Keep submission remarks under 1,000 characters.');
    }
    $file = save_pdf($_FILES['report'] ?? []);
    try {
        if ($existing) {
            $statement = $pdo->prepare("UPDATE submissions SET original_name = ?, stored_name = ?, remarks = ?, status = 'submitted', submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $statement->execute([$file['original_name'], $file['stored_name'], $remarks, (int) $existing['id']]);
        } else {
            $statement = $pdo->prepare("INSERT INTO submissions (project_id, original_name, stored_name, remarks, status) VALUES (?, ?, ?, ?, 'submitted')");
            $statement->execute([$projectId, $file['original_name'], $file['stored_name'], $remarks]);
        }
    } catch (Throwable $error) {
        @unlink(UPLOAD_DIRECTORY . '/' . $file['stored_name']);
        throw $error;
    }

    if ($existing) {
        @unlink(UPLOAD_DIRECTORY . '/' . basename((string) $existing['stored_name']));
    }
    set_flash('Your report has been submitted to your faculty guide.');
    redirect_to('?');
}

function review_submission(PDO $pdo): never
{
    $faculty = require_role($pdo, 'faculty');
    $submissionId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');
    $comment = trim((string) ($_POST['comment'] ?? ''));
    if (!$submissionId || !in_array($decision, ['revision', 'approved'], true) || $comment === '') {
        throw new AppException('Choose a decision and enter feedback for the student.');
    }
    if (mb_strlen($comment) > 4000) {
        throw new AppException('Keep feedback under 4,000 characters.');
    }

    $check = $pdo->prepare("SELECT s.id FROM submissions s JOIN projects p ON p.id = s.project_id WHERE s.id = ? AND p.faculty_id = ? AND s.status = 'submitted'");
    $check->execute([$submissionId, (int) $faculty['id']]);
    if (!$check->fetch()) {
        throw new AppException('That submission is not available for review.');
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE submissions SET status = ? WHERE id = ?');
        $update->execute([$decision, $submissionId]);
        $feedback = $pdo->prepare('INSERT INTO feedback (submission_id, faculty_id, comment) VALUES (?, ?, ?)');
        $feedback->execute([$submissionId, (int) $faculty['id'], $comment]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    set_flash('Review saved and student feedback recorded.');
    redirect_to('?');
}

function download_submission(PDO $pdo, int $submissionId): never
{
    $user = current_user($pdo);
    if (!$user || $submissionId < 1) {
        http_response_code(404);
        exit('File not found.');
    }

    $statement = $pdo->prepare('SELECT s.original_name, s.stored_name, p.student_id, p.faculty_id FROM submissions s JOIN projects p ON p.id = s.project_id WHERE s.id = ?');
    $statement->execute([$submissionId]);
    $file = $statement->fetch();
    $allowed = $file && ($user['role'] === 'admin'
        || ((int) $file['student_id'] === (int) $user['id'])
        || ($user['role'] === 'faculty' && (int) $file['faculty_id'] === (int) $user['id']));
    if (!$allowed) {
        http_response_code(404);
        exit('File not found.');
    }

    $storedName = basename((string) $file['stored_name']);
    $path = UPLOAD_DIRECTORY . '/' . $storedName;
    if (!is_file($path)) {
        http_response_code(404);
        exit('File not found.');
    }

    $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['original_name']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . ($downloadName ?: 'report.pdf') . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
