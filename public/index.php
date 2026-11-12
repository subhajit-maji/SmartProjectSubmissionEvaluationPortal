<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/app/functions.php';
require APP_ROOT . '/app/actions.php';
require APP_ROOT . '/app/data.php';
require APP_ROOT . '/app/mailer.php';

$user = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        process_action($pdo, $user);
    } catch (Throwable $exception) {
        $isActionError = $exception instanceof PortalActionException;
        if (!$isActionError) {
            error_log('Portal request failed: ' . $exception->getMessage());
        }
        flash('error', $isActionError ? $exception->getMessage() : 'The request could not be completed. Check the application log.');
        redirect($user ? '/?page=dashboard' : '/?page=login');
    }
}

$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'dashboard';
if (!$user) {
    if ($page === 'forgot') {
        render_page('Reset password', 'forgot-password');
    } elseif ($page === 'reset') {
        $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
        $tokenHash = preg_match('/\A[a-f0-9]{64}\z/i', $token) ? hash('sha256', strtolower($token)) : '';
        $query = $pdo->prepare('SELECT 1 FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > UTC_TIMESTAMP()');
        $query->execute([$tokenHash]);
        render_page('Choose a new password', 'reset-password', ['token' => $token, 'validToken' => (bool) $query->fetchColumn()]);
    } else {
        render_page('Sign in', 'login');
    }
    exit;
}

if ((int) $user['force_password_change'] === 1 && $page !== 'password') {
    redirect('/?page=password');
}
if ($page === 'password') {
    render_page('Change password', 'change-password');
    exit;
}
if ($page === 'notifications') {
    render_page('Notifications', 'notifications', notification_data($pdo, (int) $user['id']));
    exit;
}
if (in_array($page, ['rubrics', 'deadlines'], true)) {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        render_page('Access denied', 'error', ['message' => 'Only administrators can manage rubrics and deadlines.']);
        exit;
    }
    if ($page === 'rubrics') {
        $stageId = filter_var($_GET['stage_id'] ?? null, FILTER_VALIDATE_INT);
        render_page('Rubric settings', 'rubrics', rubric_admin_data($pdo, $stageId && $stageId > 0 ? (int) $stageId : 0));
    } else {
        render_page('Stage deadlines', 'deadlines', deadline_admin_data($pdo));
    }
    exit;
}

if (isset($_GET['download'])) {
    $fileId = filter_var($_GET['download'], FILTER_VALIDATE_INT);
    if (!$fileId || $fileId < 1) {
        http_response_code(404);
        exit('File not found.');
    }
    try {
        download_file($pdo, $user, (int) $fileId);
    } catch (Throwable) {
        http_response_code(404);
        exit('File not found.');
    }
}

try {
    $data = dashboard_data($pdo, $user);
    $view = match ($user['role']) {
        'admin' => 'dashboard-admin',
        'faculty' => 'dashboard-faculty',
        'student' => 'dashboard-student',
        default => throw new RuntimeException('This account role is not supported.'),
    };
    render_page('Dashboard', $view, $data);
} catch (Throwable $exception) {
    error_log('Portal dashboard failed: ' . $exception->getMessage());
    http_response_code(500);
    render_page('Portal error', 'error', ['message' => 'The dashboard could not be loaded. Check the application log.']);
}
