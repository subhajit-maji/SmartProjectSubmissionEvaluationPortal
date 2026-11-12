<?php
declare(strict_types=1);

final class PortalActionException extends RuntimeException
{
}

const PORTAL_SESSION_IDLE_TIMEOUT = 1800;
const PORTAL_SESSION_ABSOLUTE_TIMEOUT = 43200;

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        throw new PortalActionException('Your form expired. Reload the page and try again.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return is_array($flashes) ? $flashes : [];
}

function redirect(string $location = '/'): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        return null;
    }
    $now = time();
    $authTime = filter_var($_SESSION['auth_time'] ?? null, FILTER_VALIDATE_INT);
    $lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
    $authVersion = filter_var($_SESSION['auth_version'] ?? null, FILTER_VALIDATE_INT);
    if (!$authTime || !$lastActivity || $authVersion === false || $now - $lastActivity > PORTAL_SESSION_IDLE_TIMEOUT || $now - $authTime > PORTAL_SESSION_ABSOLUTE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $_SESSION['last_activity'] = $now;
    $statement = db()->prepare('SELECT id, name, email, role, department_id, batch_id, status, force_password_change, session_version FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row || $row['status'] !== 'active' || (int) $row['session_version'] !== $authVersion) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $user = $row;
    return $user;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('/?page=login');
    }
    return $user;
}

function require_role(array $user, string ...$roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        throw new PortalActionException('You do not have permission to do that.');
    }
}

function request_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

function record_status(PDO $pdo, string $entity, int $entityId, ?string $old, string $new, int $userId, string $remarks = ''): void
{
    $statement = $pdo->prepare(
        'INSERT INTO status_history (entity_type, entity_id, old_status, new_status, changed_by, is_system, remarks) VALUES (?, ?, ?, ?, ?, 0, ?)'
    );
    $statement->execute([$entity, $entityId, $old, $new, $userId, $remarks !== '' ? $remarks : null]);
}

function record_audit(PDO $pdo, array $user, string $action, string $entity, ?int $entityId, ?array $old, ?array $new): void
{
    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, is_system, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([
        (int) $user['id'],
        $action,
        $entity,
        $entityId,
        $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        $new === null ? null : json_encode($new, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        request_ip(),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
    ]);
}

function notify_users(PDO $pdo, array $userIds, string $title, string $message, string $link, string $type = 'info'): void
{
    if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) {
        $type = 'info';
    }
    $statement = $pdo->prepare('INSERT INTO notifications (user_id, title, message, link, type) VALUES (?, ?, ?, ?, ?)');
    foreach (array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0))) as $userId) {
        $statement->execute([$userId, substr($title, 0, 255), $message, substr($link, 0, 500), $type]);
    }
}

function notification_data(PDO $pdo, int $userId): array
{
    $count = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $count->execute([$userId]);
    $rows = $pdo->prepare('SELECT id, title, message, link, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
    $rows->execute([$userId]);
    return ['unreadCount' => (int) $count->fetchColumn(), 'notifications' => $rows->fetchAll()];
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    $count = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $count->execute([$userId]);
    return (int) $count->fetchColumn();
}

function feedback_by_submission(PDO $pdo, array $submissionIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $submissionIds), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare("SELECT f.id, f.submission_id, f.parent_id, f.message, f.created_at, u.name AS user_name, u.role AS user_role FROM feedback f JOIN users u ON u.id = f.user_id WHERE f.submission_id IN ($marks) ORDER BY f.created_at, f.id");
    $query->execute($ids);
    $result = [];
    foreach ($query->fetchAll() as $row) {
        $result[(int) $row['submission_id']][] = $row;
    }
    return $result;
}

function safe_local_link(?string $link): string
{
    if (!is_string($link) || $link === '' || $link[0] !== '/' || str_starts_with($link, '//') || str_contains($link, "\r") || str_contains($link, "\n")) {
        return '/';
    }
    return $link;
}

function render_page(string $title, string $view, array $data = []): void
{
    $user = current_user();
    $unreadCount = $user ? unread_notification_count(db(), (int) $user['id']) : 0;
    $flashes = take_flashes();
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/app/views/' . $view . '.php';
    $content = (string) ob_get_clean();
    require APP_ROOT . '/app/views/layout.php';
}

function format_utc(?string $datetime): string
{
    if (!$datetime) {
        return 'No deadline set';
    }
    try {
        return (new DateTimeImmutable($datetime, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('M j, Y g:i A') . ' UTC';
    } catch (Throwable) {
        return 'Invalid date';
    }
}

function require_project_access(PDO $pdo, array $user, int $projectId): array
{
    if ($user['role'] === 'admin') {
        $query = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
        $query->execute([$projectId]);
    } elseif ($user['role'] === 'student') {
        $query = $pdo->prepare('SELECT p.* FROM projects p JOIN project_members pm ON pm.project_id = p.id WHERE p.id = ? AND pm.student_id = ?');
        $query->execute([$projectId, $user['id']]);
    } else {
        $query = $pdo->prepare("SELECT p.* FROM projects p JOIN project_guides pg ON pg.project_id = p.id WHERE p.id = ? AND pg.faculty_id = ? AND pg.status = 'active'");
        $query->execute([$projectId, $user['id']]);
    }
    $project = $query->fetch();
    if (!$project) {
        http_response_code(404);
        throw new PortalActionException('Project not found.');
    }
    return $project;
}
