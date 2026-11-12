<?php
declare(strict_types=1);

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function post_id(string $key): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if (!$value || $value < 1) {
        throw new PortalActionException('Choose a valid record.');
    }
    return (int) $value;
}

function assert_password_policy(string $password): void
{
    if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new PortalActionException('Passwords must be 12 to 72 bytes long.');
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        throw new PortalActionException('Use an uppercase letter, lowercase letter, number, and symbol.');
    }
}

function login_action(PDO $pdo): never
{
    $email = strtolower(post_string('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $emailHash = hash('sha256', $email);
    $ip = request_ip();

    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
    $limit = $pdo->prepare('SELECT SUM(email_hash = ?) AS email_attempts, SUM(ip_address = ?) AS ip_attempts FROM login_attempts WHERE attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE AND (email_hash = ? OR ip_address = ?)');
    $limit->execute([$emailHash, $ip, $emailHash, $ip]);
    $attemptCounts = $limit->fetch();
    $blocked = (int) ($attemptCounts['email_attempts'] ?? 0) >= 5 || (int) ($attemptCounts['ip_attempts'] ?? 0) >= 50;
    if ($blocked) {
        flash('error', 'Sign-in failed. Check your details or wait 15 minutes before trying again.');
        redirect('/?page=login');
    }

    $statement = $pdo->prepare("SELECT id, name, email, password, role, status, force_password_change, session_version FROM users WHERE email = ? LIMIT 1");
    $statement->execute([$email]);
    $account = $statement->fetch();
    $passwordOk = false;
    if (strlen($password) <= 72 && !str_contains($password, "\0")) {
        $passwordOk = $account
            ? password_verify($password, $account['password'])
            : password_verify($password, '$2y$12$xKBQdGp7fsp6gweol2LbzObaMg3wI17r66TAyaA/LMvJ/MRTTb3Qq');
    }

    if (!$account || !$passwordOk || $account['status'] !== 'active') {
        $attempt = $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_address) VALUES (?, ?)');
        $attempt->execute([$emailHash, $ip]);
        flash('error', 'Sign-in failed. Check your details or wait 15 minutes before trying again.');
        redirect('/?page=login');
    }

    $clear = $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = ?');
    $clear->execute([$emailHash]);
    session_regenerate_id(true);
    $now = time();
    $_SESSION = ['user_id' => (int) $account['id'], 'auth_time' => $now, 'last_activity' => $now, 'auth_version' => (int) $account['session_version']];
    redirect('/');
}

function request_password_reset_action(PDO $pdo): never
{
    $email = strtolower(post_string('email'));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 150) {
        $lookup = $pdo->prepare("SELECT id, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $lookup->execute([$email]);
        $account = $lookup->fetch();
        if ($account) {
            $recent = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
            $recent->execute([(int) $account['id']]);
            if ((int) $recent->fetchColumn() < 3) {
                $pdo->exec('DELETE FROM password_resets WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY OR (used = 1 AND created_at < UTC_TIMESTAMP() - INTERVAL 30 DAY)');
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 30 MINUTE)');
                $insert->execute([(int) $account['id'], $tokenHash]);
                if (!send_password_reset_email($account['email'], $token)) {
                    $delete = $pdo->prepare('DELETE FROM password_resets WHERE token_hash = ?');
                    $delete->execute([$tokenHash]);
                    error_log('A password reset email could not be queued. Check config/mail.php and the PHP mail transport.');
                }
            }
        }
    }
    flash('success', 'If an active account matches that address, a reset link has been sent.');
    redirect('/?page=login');
}

function reset_password_action(PDO $pdo): never
{
    $token = post_string('token');
    $password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
        throw new PortalActionException('This reset link is invalid or expired. Request a new link.');
    }
    if ($password !== $confirm) {
        throw new PortalActionException('The new passwords do not match.');
    }
    assert_password_policy($password);
    $tokenHash = hash('sha256', strtolower($token));

    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare("SELECT pr.id, pr.user_id FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > UTC_TIMESTAMP() AND u.status = 'active' FOR UPDATE");
        $lookup->execute([$tokenHash]);
        $reset = $lookup->fetch();
        if (!$reset) {
            throw new PortalActionException('This reset link is invalid or expired. Request a new link.');
        }
        $updatePassword = $pdo->prepare('UPDATE users SET password = ?, force_password_change = 0, session_version = session_version + 1 WHERE id = ?');
        $updatePassword->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), (int) $reset['user_id']]);
        $markUsed = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0');
        $markUsed->execute([(int) $reset['user_id']]);
        record_audit($pdo, ['id' => (int) $reset['user_id']], 'user.password_reset', 'user', (int) $reset['user_id'], null, ['password_reset' => true]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Password updated. You can now sign in.');
    redirect('/?page=login');
}

function change_password_action(PDO $pdo, array $user): never
{
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if ($new !== $confirm) {
        throw new PortalActionException('The new passwords do not match.');
    }
    assert_password_policy($new);

    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare("SELECT password FROM users WHERE id = ? AND status = 'active' FOR UPDATE");
        $lookup->execute([(int) $user['id']]);
        $stored = $lookup->fetchColumn();
        if (!is_string($stored) || !password_verify($current, $stored)) {
            throw new PortalActionException('Current password is incorrect.');
        }
        if (password_verify($new, $stored)) {
            throw new PortalActionException('Choose a different password.');
        }
        $update = $pdo->prepare('UPDATE users SET password = ?, force_password_change = 0, session_version = session_version + 1 WHERE id = ?');
        $update->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), (int) $user['id']]);
        $versionQuery = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
        $versionQuery->execute([(int) $user['id']]);
        $newAuthVersion = (int) $versionQuery->fetchColumn();
        $invalidateResets = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0');
        $invalidateResets->execute([(int) $user['id']]);
        record_audit($pdo, $user, 'user.password_change', 'user', (int) $user['id'], null, ['password_changed' => true]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    session_regenerate_id(true);
    $_SESSION['auth_version'] = $newAuthVersion;
    $_SESSION['last_activity'] = time();
    flash('success', 'Password updated.');
    redirect('/');
}

function create_user_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $name = post_string('name');
    $email = strtolower(post_string('email'));
    $role = post_string('role');
    $departmentId = post_id('department_id');
    $batchRaw = post_string('batch_id');
    $batchId = $batchRaw === '' ? null : filter_var($batchRaw, FILTER_VALIDATE_INT);
    $registration = post_string('registration_no');
    $phone = post_string('phone');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        throw new PortalActionException('Enter a valid name and email address.');
    }
    if (strlen($registration) > 50 || strlen($phone) > 20) {
        throw new PortalActionException('Registration number or phone number is too long.');
    }
    if (!in_array($role, ['admin', 'faculty', 'student'], true)) {
        throw new PortalActionException('Choose a valid account role.');
    }
    if ($role === 'student' && (!$batchId || $batchId < 1)) {
        throw new PortalActionException('Students must be assigned to a batch.');
    }
    if ($role !== 'student') {
        $batchId = null;
    }
    assert_password_policy($password);

    $department = $pdo->prepare("SELECT 1 FROM departments WHERE id = ? AND status = 'active'");
    $department->execute([$departmentId]);
    if (!$department->fetchColumn()) {
        throw new PortalActionException('Choose an active department.');
    }
    if ($batchId !== null) {
        $batch = $pdo->prepare("SELECT 1 FROM batches WHERE id = ? AND status = 'active'");
        $batch->execute([(int) $batchId]);
        if (!$batch->fetchColumn()) {
            throw new PortalActionException('Choose an active batch.');
        }
    }

    $existingEmail = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $existingEmail->execute([$email]);
    if ($existingEmail->fetchColumn()) {
        throw new PortalActionException('An account with this email address already exists.');
    }

    if ($registration !== '') {
        $existingReg = $pdo->prepare('SELECT 1 FROM users WHERE registration_no = ? LIMIT 1');
        $existingReg->execute([$registration]);
        if ($existingReg->fetchColumn()) {
            throw new PortalActionException('An account with this registration or employee number already exists.');
        }
    }
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO users (name, email, password, role, department_id, batch_id, registration_no, phone, status, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\', 1)');
        $insert->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            $role,
            $departmentId,
            $batchId,
            $registration !== '' ? $registration : null,
            $phone !== '' ? $phone : null,
        ]);
        $id = (int) $pdo->lastInsertId();
        record_status($pdo, 'user', $id, null, 'active', (int) $user['id'], 'Account created.');
        record_audit($pdo, $user, 'user.create', 'user', $id, null, ['email' => $email, 'role' => $role]);
        notify_users($pdo, [$id], 'Your portal account is ready', 'Sign in and change your temporary password to get started.', '/?page=dashboard', 'success');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Account created. The user must change the temporary password at first sign-in.');
    redirect('/?page=dashboard');
}

function create_department_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $name = post_string('name');
    $code = strtoupper(post_string('code'));
    if ($name === '' || strlen($name) > 100 || !preg_match('/\A[A-Z0-9-]{2,20}\z/', $code)) {
        throw new PortalActionException('Enter a department name and a 2–20 character code.');
    }
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO departments (name, code, status) VALUES (?, ?, 'active')");
        $insert->execute([$name, $code]);
        $id = (int) $pdo->lastInsertId();
        record_status($pdo, 'department', $id, null, 'active', (int) $user['id'], 'Department created.');
        record_audit($pdo, $user, 'department.create', 'department', $id, null, ['name' => $name, 'code' => $code]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Department created.');
    redirect('/?page=dashboard');
}

function update_department_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $id = post_id('department_id');
    $name = post_string('name');
    $code = strtoupper(post_string('code'));
    if ($name === '' || strlen($name) > 100 || !preg_match('/\A[A-Z0-9-]{2,20}\z/', $code)) {
        throw new PortalActionException('Enter a department name and a 2–20 character code.');
    }
    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare('SELECT name, code FROM departments WHERE id = ? FOR UPDATE');
        $lookup->execute([$id]);
        $old = $lookup->fetch();
        if (!$old) {
            throw new PortalActionException('Department not found.');
        }
        $update = $pdo->prepare('UPDATE departments SET name = ?, code = ? WHERE id = ?');
        $update->execute([$name, $code, $id]);
        record_audit($pdo, $user, 'department.update', 'department', $id, $old, ['name' => $name, 'code' => $code]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Department updated.');
    redirect('/?page=dashboard');
}

function create_batch_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $name = post_string('name');
    $year = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT);
    if ($name === '' || strlen($name) > 100 || !$year || $year < 1901 || $year > 2155) {
        throw new PortalActionException('Enter a batch name and a year from 1901 to 2155.');
    }
    $duplicate = $pdo->prepare('SELECT 1 FROM batches WHERE name = ? AND year = ? LIMIT 1');
    $duplicate->execute([$name, $year]);
    if ($duplicate->fetchColumn()) {
        throw new PortalActionException('That batch already exists.');
    }
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO batches (name, year, status) VALUES (?, ?, 'active')");
        $insert->execute([$name, $year]);
        $id = (int) $pdo->lastInsertId();
        record_status($pdo, 'batch', $id, null, 'active', (int) $user['id'], 'Batch created.');
        record_audit($pdo, $user, 'batch.create', 'batch', $id, null, ['name' => $name, 'year' => $year]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Batch created.');
    redirect('/?page=dashboard');
}

function update_batch_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $id = post_id('batch_id');
    $name = post_string('name');
    $year = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT);
    if ($name === '' || strlen($name) > 100 || !$year || $year < 1901 || $year > 2155) {
        throw new PortalActionException('Enter a batch name and a year from 1901 to 2155.');
    }
    $pdo->beginTransaction();
    try {
        $duplicate = $pdo->prepare('SELECT 1 FROM batches WHERE name = ? AND year = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$name, $year, $id]);
        if ($duplicate->fetchColumn()) {
            throw new PortalActionException('That batch already exists.');
        }
        $lookup = $pdo->prepare('SELECT name, year FROM batches WHERE id = ? FOR UPDATE');
        $lookup->execute([$id]);
        $old = $lookup->fetch();
        if (!$old) {
            throw new PortalActionException('Batch not found.');
        }
        $update = $pdo->prepare('UPDATE batches SET name = ?, year = ? WHERE id = ?');
        $update->execute([$name, $year, $id]);
        record_audit($pdo, $user, 'batch.update', 'batch', $id, $old, ['name' => $name, 'year' => $year]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Batch updated.');
    redirect('/?page=dashboard');
}

function create_stage_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $name = post_string('name');
    $description = post_string('description');
    $start = parse_utc_datetime_input(post_string('default_start_date'));
    $deadline = parse_utc_datetime_input(post_string('default_deadline'));
    $required = post_string('is_required');
    if ($name === '' || strlen($name) > 100 || strlen($description) > 5000 || !in_array($required, ['0', '1'], true)) {
        throw new PortalActionException('Enter a stage name, description, and required setting.');
    }
    if (($start === null) !== ($deadline === null) || ($start !== null && $deadline !== null && $start >= $deadline)) {
        throw new PortalActionException('Set both default dates with the deadline after the start, or leave both blank.');
    }
    $sortQuery = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM submission_stages');
    $sortOrder = (int) $sortQuery->fetchColumn();
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO submission_stages (name, description, default_start_date, default_deadline, is_required, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 'inactive')");
        $insert->execute([$name, $description !== '' ? $description : null, $start, $deadline, (int) $required, $sortOrder]);
        $stageId = (int) $pdo->lastInsertId();
        record_status($pdo, 'submission_stage', $stageId, null, 'inactive', (int) $user['id'], 'Stage created inactive.');
        record_audit($pdo, $user, 'stage.create', 'submission_stage', $stageId, null, ['name' => $name, 'is_required' => (int) $required]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Stage created inactive. Add a 100% rubric, then activate it.');
    redirect('/?page=rubrics&stage_id=' . $stageId);
}

function update_stage_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $stageId = post_id('stage_id');
    $name = post_string('name');
    $description = post_string('description');
    $start = parse_utc_datetime_input(post_string('default_start_date'));
    $deadline = parse_utc_datetime_input(post_string('default_deadline'));
    $required = post_string('is_required');
    $sortOrder = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);
    if ($name === '' || strlen($name) > 100 || strlen($description) > 5000 || !in_array($required, ['0', '1'], true) || $sortOrder === false || $sortOrder < 0) {
        throw new PortalActionException('Enter a valid stage name, description, required setting, and order.');
    }
    if (($start === null) !== ($deadline === null) || ($start !== null && $deadline !== null && $start >= $deadline)) {
        throw new PortalActionException('Set both default dates with the deadline after the start, or leave both blank.');
    }
    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare('SELECT name, description, default_start_date, default_deadline, is_required, sort_order FROM submission_stages WHERE id = ? FOR UPDATE');
        $lookup->execute([$stageId]);
        $old = $lookup->fetch();
        if (!$old) {
            throw new PortalActionException('Stage not found.');
        }
        $update = $pdo->prepare('UPDATE submission_stages SET name = ?, description = ?, default_start_date = ?, default_deadline = ?, is_required = ?, sort_order = ? WHERE id = ?');
        $update->execute([$name, $description !== '' ? $description : null, $start, $deadline, (int) $required, (int) $sortOrder, $stageId]);
        record_audit($pdo, $user, 'stage.update', 'submission_stage', $stageId, $old, ['name' => $name, 'description' => $description, 'default_start_date' => $start, 'default_deadline' => $deadline, 'is_required' => (int) $required, 'sort_order' => (int) $sortOrder]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Stage settings saved.');
    redirect('/?page=rubrics&stage_id=' . $stageId);
}

function set_stage_status_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $stageId = post_id('stage_id');
    $status = post_string('status');
    if (!in_array($status, ['active', 'inactive'], true)) {
        throw new PortalActionException('Choose a valid stage status.');
    }
    $pdo->beginTransaction();
    try {
        $stageQuery = $pdo->prepare('SELECT id, name, status FROM submission_stages WHERE id = ? FOR UPDATE');
        $stageQuery->execute([$stageId]);
        $stage = $stageQuery->fetch();
        if (!$stage) {
            throw new PortalActionException('Stage not found.');
        }
        if ($status === 'active') {
            $rubric = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(weight), 0) AS weight_total FROM rubric_criteria WHERE stage_id = ? AND status = 'active'");
            $rubric->execute([$stageId]);
            $values = $rubric->fetch();
            if ((int) $values['total'] === 0 || abs((float) $values['weight_total'] - 100.0) > 0.01) {
                throw new PortalActionException('Create an active rubric totaling 100 before activating this stage.');
            }
        }
        $update = $pdo->prepare('UPDATE submission_stages SET status = ? WHERE id = ?');
        $update->execute([$status, $stageId]);
        if ($stage['status'] !== $status) {
            record_status($pdo, 'submission_stage', $stageId, (string) $stage['status'], $status, (int) $user['id'], 'Stage status changed.');
        }
        record_audit($pdo, $user, 'stage.status', 'submission_stage', $stageId, ['status' => $stage['status']], ['status' => $status]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', $stage['name'] . ' is now ' . $status . '.');
    redirect('/?page=rubrics&stage_id=' . $stageId);
}

function set_record_status_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $entity = post_string('entity');
    $id = post_id('record_id');
    $status = post_string('status');
    if (!in_array($status, ['active', 'inactive'], true)) {
        throw new PortalActionException('Choose a valid account or record status.');
    }
    $queries = [
        'user' => ['SELECT id, name, role, status FROM users WHERE id = ? FOR UPDATE', 'UPDATE users SET status = ?, session_version = session_version + 1 WHERE id = ?'],
        'department' => ['SELECT id, name, status FROM departments WHERE id = ? FOR UPDATE', 'UPDATE departments SET status = ? WHERE id = ?'],
        'batch' => ['SELECT id, name, status FROM batches WHERE id = ? FOR UPDATE', 'UPDATE batches SET status = ? WHERE id = ?'],
    ];
    if (!isset($queries[$entity])) {
        throw new PortalActionException('Choose a valid record type.');
    }

    $pdo->beginTransaction();
    try {
        $activeAdminIds = [];
        if ($entity === 'user' && $status === 'inactive') {
            $activeAdmins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id FOR UPDATE");
            $activeAdminIds = array_map('intval', $activeAdmins->fetchAll(PDO::FETCH_COLUMN));
        }
        $select = $pdo->prepare($queries[$entity][0]);
        $select->execute([$id]);
        $record = $select->fetch();
        if (!$record) {
            throw new PortalActionException('Record not found.');
        }
        if ($entity === 'user' && $id === (int) $user['id'] && $status === 'inactive') {
            throw new PortalActionException('You cannot deactivate your own account.');
        }
        if ($entity === 'user' && $record['role'] === 'admin' && $record['status'] === 'active' && $status === 'inactive' && count($activeAdminIds) <= 1) {
            throw new PortalActionException('You cannot deactivate the last active administrator.');
        }
        if ($record['status'] === $status) {
            $pdo->commit();
            flash('success', ucfirst($entity) . ' already has that status.');
            redirect('/?page=dashboard');
        }
        $update = $pdo->prepare($queries[$entity][1]);
        $update->execute([$status, $id]);
        if ($entity === 'user' && $status === 'inactive') {
            $invalidateResets = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0');
            $invalidateResets->execute([$id]);
        }
        if ($entity === 'user' && $record['role'] === 'faculty' && $status === 'inactive') {
            $activeGuides = $pdo->prepare("SELECT id FROM project_guides WHERE faculty_id = ? AND status = 'active' FOR UPDATE");
            $activeGuides->execute([$id]);
            $guideIds = array_map('intval', $activeGuides->fetchAll(PDO::FETCH_COLUMN));
            $deactivateGuides = $pdo->prepare("UPDATE project_guides SET status = 'inactive' WHERE faculty_id = ? AND status = 'active'");
            $deactivateGuides->execute([$id]);
            foreach ($guideIds as $guideId) {
                record_status($pdo, 'project_guide', $guideId, 'active', 'inactive', (int) $user['id'], 'Assigned faculty account deactivated.');
            }
        }
        record_status($pdo, $entity, $id, (string) $record['status'], $status, (int) $user['id'], 'Administrator changed status.');
        record_audit($pdo, $user, $entity . '.status', $entity, $id, ['status' => $record['status']], ['status' => $status]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', ucfirst($entity) . ' status updated.');
    redirect('/?page=dashboard');
}

function create_project_action(PDO $pdo, array $user): never
{
    require_role($user, 'student');
    if (empty($user['batch_id'])) {
        throw new PortalActionException('Your account needs a batch assignment before you can create a project.');
    }
    $placement = $pdo->prepare("SELECT 1 FROM departments d JOIN batches b ON b.id = ? WHERE d.id = ? AND d.status = 'active' AND b.status = 'active'");
    $placement->execute([(int) $user['batch_id'], (int) $user['department_id']]);
    if (!$placement->fetchColumn()) {
        throw new PortalActionException('Your department and batch must both be active to create a project.');
    }
    $title = post_string('title');
    $abstract = post_string('abstract');
    if ($title === '' || strlen($title) > 200 || $abstract === '' || strlen($abstract) > 20000) {
        throw new PortalActionException('Enter a project title and an abstract no longer than 20000 bytes.');
    }
    $pdo->beginTransaction();
    try {
        $lockUser = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lockUser->execute([(int) $user['id']]);
        $existing = $pdo->prepare("SELECT 1 FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.student_id = ? AND p.status <> 'rejected' LIMIT 1");
        $existing->execute([(int) $user['id']]);
        if ($existing->fetchColumn()) {
            throw new PortalActionException('You already belong to a project. Ask an administrator to update your team.');
        }
        $insert = $pdo->prepare("INSERT INTO projects (title, abstract, department_id, batch_id, status, created_by) VALUES (?, ?, ?, ?, 'pending_approval', ?)");
        $insert->execute([$title, $abstract, (int) $user['department_id'], (int) $user['batch_id'], (int) $user['id']]);
        $projectId = (int) $pdo->lastInsertId();
        $member = $pdo->prepare("INSERT INTO project_members (project_id, student_id, role) VALUES (?, ?, 'leader')");
        $member->execute([$projectId, (int) $user['id']]);
        record_status($pdo, 'project', $projectId, null, 'pending_approval', (int) $user['id'], 'Proposal submitted.');
        record_audit($pdo, $user, 'project.create', 'project', $projectId, null, ['title' => $title]);
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        notify_users($pdo, array_map('intval', $admins), 'Project proposal submitted', $title . ' is waiting for approval.', '/?page=dashboard', 'info');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Project proposal submitted for approval.');
    redirect('/?page=dashboard');
}

function update_project_proposal_action(PDO $pdo, array $user): never
{
    require_role($user, 'student');
    $projectId = post_id('project_id');
    $title = post_string('title');
    $abstract = post_string('abstract');
    if ($title === '' || strlen($title) > 200 || $abstract === '' || strlen($abstract) > 20000) {
        throw new PortalActionException('Enter a project title and an abstract no longer than 20000 bytes.');
    }
    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare("SELECT p.id, p.title, p.abstract, p.status FROM projects p JOIN project_members pm ON pm.project_id = p.id WHERE p.id = ? AND pm.student_id = ? AND pm.role = 'leader' FOR UPDATE");
        $lookup->execute([$projectId, (int) $user['id']]);
        $project = $lookup->fetch();
        if (!$project || !in_array($project['status'], ['pending_approval', 'rejected'], true)) {
            throw new PortalActionException('Only a pending or rejected proposal can be edited.');
        }
        $old = ['title' => $project['title'], 'abstract' => $project['abstract'], 'status' => $project['status']];
        $newStatus = 'pending_approval';
        $update = $pdo->prepare('UPDATE projects SET title = ?, abstract = ?, status = ?, approved_at = NULL WHERE id = ?');
        $update->execute([$title, $abstract, $newStatus, $projectId]);
        if ($project['status'] !== $newStatus) {
            record_status($pdo, 'project', $projectId, (string) $project['status'], $newStatus, (int) $user['id'], 'Revised proposal resubmitted.');
        }
        record_audit($pdo, $user, 'project.update_proposal', 'project', $projectId, $old, ['title' => $title, 'abstract' => $abstract, 'status' => $newStatus]);
        if ($project['status'] === 'rejected') {
            $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            notify_users($pdo, array_map('intval', $admins), 'Revised project proposal', $title . ' was resubmitted for approval.', '/?page=dashboard', 'info');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Proposal updated.');
    redirect('/?page=dashboard');
}

function add_project_member_action(PDO $pdo, array $user): never
{
    require_role($user, 'student');
    $projectId = post_id('project_id');
    $registration = post_string('registration_no');
    if ($registration === '' || strlen($registration) > 50) {
        throw new PortalActionException('Enter the student registration number.');
    }
    $pdo->beginTransaction();
    try {
        $projectQuery = $pdo->prepare("SELECT p.id, p.department_id, p.batch_id, p.status FROM projects p JOIN project_members pm ON pm.project_id = p.id WHERE p.id = ? AND pm.student_id = ? AND pm.role = 'leader' FOR UPDATE");
        $projectQuery->execute([$projectId, (int) $user['id']]);
        $project = $projectQuery->fetch();
        if (!$project || !in_array($project['status'], ['pending_approval', 'approved', 'in_progress'], true)) {
            throw new PortalActionException('Only the leader of an active project can manage its team.');
        }
        $studentQuery = $pdo->prepare("SELECT id FROM users WHERE registration_no = ? AND role = 'student' AND status = 'active' AND department_id = ? AND batch_id = ? FOR UPDATE");
        $studentQuery->execute([$registration, (int) $project['department_id'], (int) $project['batch_id']]);
        $studentId = (int) ($studentQuery->fetchColumn() ?: 0);
        if (!$studentId || $studentId === (int) $user['id']) {
            throw new PortalActionException('No eligible student with that registration number was found.');
        }
        $existing = $pdo->prepare("SELECT 1 FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.student_id = ? AND p.status <> 'rejected' LIMIT 1");
        $existing->execute([$studentId]);
        if ($existing->fetchColumn()) {
            throw new PortalActionException('That student already belongs to an active project.');
        }
        $insert = $pdo->prepare("INSERT INTO project_members (project_id, student_id, role) VALUES (?, ?, 'member')");
        $insert->execute([$projectId, $studentId]);
        record_audit($pdo, $user, 'project.member_add', 'project', $projectId, null, ['student_id' => $studentId]);
        notify_users($pdo, [$studentId], 'You joined a project team', 'You were added to project ' . $projectId . '.', '/?page=dashboard', 'info');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Student added to the project team.');
    redirect('/?page=dashboard');
}

function remove_project_member_action(PDO $pdo, array $user): never
{
    require_role($user, 'student');
    $projectId = post_id('project_id');
    $studentId = post_id('student_id');
    if ($studentId === (int) $user['id']) {
        throw new PortalActionException('The team leader cannot remove themselves.');
    }
    $pdo->beginTransaction();
    try {
        $projectQuery = $pdo->prepare("SELECT p.id, p.status FROM projects p JOIN project_members pm ON pm.project_id = p.id WHERE p.id = ? AND pm.student_id = ? AND pm.role = 'leader' FOR UPDATE");
        $projectQuery->execute([$projectId, (int) $user['id']]);
        $project = $projectQuery->fetch();
        if (!$project || !in_array($project['status'], ['pending_approval', 'approved', 'in_progress', 'rejected'], true)) {
            throw new PortalActionException('Only the team leader can update an unfinished project team.');
        }
        $member = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND student_id = ? AND role = 'member' FOR UPDATE");
        $member->execute([$projectId, $studentId]);
        if (!$member->fetchColumn()) {
            throw new PortalActionException('That student is not a removable team member.');
        }
        $delete = $pdo->prepare('DELETE FROM project_members WHERE project_id = ? AND student_id = ? AND role = \'member\'');
        $delete->execute([$projectId, $studentId]);
        record_audit($pdo, $user, 'project.member_remove', 'project', $projectId, ['student_id' => $studentId], null);
        notify_users($pdo, [$studentId], 'You were removed from a project team', 'You no longer have access to project ' . $projectId . '.', '/?page=dashboard', 'warning');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Student removed from the team.');
    redirect('/?page=dashboard');
}

function decide_project_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $projectId = post_id('project_id');
    $decision = post_string('decision');
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new PortalActionException('Choose approve or reject.');
    }
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT id, status, title FROM projects WHERE id = ? FOR UPDATE');
        $query->execute([$projectId]);
        $project = $query->fetch();
        if (!$project || $project['status'] !== 'pending_approval') {
            throw new PortalActionException('This proposal is no longer awaiting a decision.');
        }
        if ($decision === 'approved') {
            $update = $pdo->prepare("UPDATE projects SET status = 'approved', approved_at = UTC_TIMESTAMP() WHERE id = ?");
        } else {
            $update = $pdo->prepare("UPDATE projects SET status = 'rejected' WHERE id = ?");
        }
        $update->execute([$projectId]);
        record_status($pdo, 'project', $projectId, 'pending_approval', $decision, (int) $user['id']);
        record_audit($pdo, $user, 'project.' . $decision, 'project', $projectId, ['status' => 'pending_approval'], ['status' => $decision]);
        $members = $pdo->prepare('SELECT student_id FROM project_members WHERE project_id = ?');
        $members->execute([$projectId]);
        notify_users($pdo, array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN)), 'Project proposal ' . $decision, $project['title'] . ' was ' . $decision . '.', '/?page=dashboard', $decision === 'approved' ? 'success' : 'warning');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Project proposal ' . $decision . '.');
    redirect('/?page=dashboard');
}

function assign_guide_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $projectId = post_id('project_id');
    $facultyId = post_id('faculty_id');
    $projectQuery = $pdo->prepare('SELECT id, department_id, status FROM projects WHERE id = ?');
    $projectQuery->execute([$projectId]);
    $project = $projectQuery->fetch();
    $facultyQuery = $pdo->prepare("SELECT id, department_id FROM users WHERE id = ? AND role = 'faculty' AND status = 'active'");
    $facultyQuery->execute([$facultyId]);
    $faculty = $facultyQuery->fetch();
    if (!$project || !in_array($project['status'], ['pending_approval', 'approved', 'in_progress'], true) || !$faculty || (int) $faculty['department_id'] !== (int) $project['department_id']) {
        throw new PortalActionException('Choose an active faculty member from the project department.');
    }

    $pdo->beginTransaction();
    try {
        $projectLock = $pdo->prepare('SELECT id, department_id, status FROM projects WHERE id = ? FOR UPDATE');
        $projectLock->execute([$projectId]);
        $lockedProject = $projectLock->fetch();
        if (!$lockedProject || !in_array($lockedProject['status'], ['pending_approval', 'approved', 'in_progress'], true)) {
            throw new PortalActionException('Project not found.');
        }
        $facultyCheck = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'faculty' AND status = 'active' AND department_id = ? FOR UPDATE");
        $facultyCheck->execute([$facultyId, (int) $lockedProject['department_id']]);
        if (!$facultyCheck->fetchColumn()) {
            throw new PortalActionException('Choose an active faculty member from the project department.');
        }
        $primaryQuery = $pdo->prepare("SELECT id, faculty_id FROM project_guides WHERE project_id = ? AND role = 'primary_guide' AND status = 'active' ORDER BY id FOR UPDATE");
        $primaryQuery->execute([$projectId]);
        $primaryGuides = $primaryQuery->fetchAll();
        if (count($primaryGuides) === 1 && (int) $primaryGuides[0]['faculty_id'] === $facultyId) {
            $pdo->commit();
            flash('success', 'That faculty member is already the primary guide.');
            redirect('/?page=dashboard');
        }
        $deactivate = $pdo->prepare("UPDATE project_guides SET status = 'inactive' WHERE id = ? AND status = 'active'");
        foreach ($primaryGuides as $primaryGuide) {
            $deactivate->execute([(int) $primaryGuide['id']]);
            record_status($pdo, 'project_guide', (int) $primaryGuide['id'], 'active', 'inactive', (int) $user['id'], 'Primary guide reassigned.');
        }
        $existingGuideQuery = $pdo->prepare('SELECT id, role, status FROM project_guides WHERE project_id = ? AND faculty_id = ? FOR UPDATE');
        $existingGuideQuery->execute([$projectId, $facultyId]);
        $existingGuide = $existingGuideQuery->fetch();
        $assign = $pdo->prepare("INSERT INTO project_guides (project_id, faculty_id, role, status) VALUES (?, ?, 'primary_guide', 'active') ON DUPLICATE KEY UPDATE role = 'primary_guide', status = 'active', assigned_at = UTC_TIMESTAMP()");
        $assign->execute([$projectId, $facultyId]);
        if (!$existingGuide) {
            record_status($pdo, 'project_guide', (int) $pdo->lastInsertId(), null, 'active', (int) $user['id'], 'Primary guide assigned.');
        } elseif ($existingGuide['status'] !== 'active') {
            record_status($pdo, 'project_guide', (int) $existingGuide['id'], (string) $existingGuide['status'], 'active', (int) $user['id'], 'Primary guide assigned.');
        }
        record_audit($pdo, $user, 'project.assign_primary_guide', 'project', $projectId, ['primary_guide_ids' => array_map('intval', array_column($primaryGuides, 'faculty_id'))], ['faculty_id' => $facultyId]);
        $members = $pdo->prepare('SELECT student_id FROM project_members WHERE project_id = ?');
        $members->execute([$projectId]);
        notify_users($pdo, array_merge([$facultyId], array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN))), 'Project guide assigned', 'A primary faculty guide was assigned to project ' . $projectId . '.', '/?page=dashboard', 'info');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Primary guide assigned.');
    redirect('/?page=dashboard');
}

function submission_upload_info(): array
{
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new PortalActionException('Choose one document to upload.');
    }
    if (!is_uploaded_file((string) $file['tmp_name']) || (int) $file['size'] < 1 || (int) $file['size'] > 9 * 1024 * 1024) {
        throw new PortalActionException('The upload must be a non-empty file no larger than 9 MiB.');
    }
    $original = basename((string) $file['name']);
    $original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?? 'submission';
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
    $allowed = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
        throw new PortalActionException('Allowed file types are PDF, DOCX, and ZIP.');
    }
    return [$file, substr($original, 0, 255), $extension, $mime];
}

function submit_stage_action(PDO $pdo, array $user): never
{
    require_role($user, 'student');
    $projectId = post_id('project_id');
    $stageId = post_id('stage_id');
    $remarks = post_string('remarks');
    if (strlen($remarks) > 5000) {
        throw new PortalActionException('Submission notes must be no more than 5000 bytes.');
    }
    $projectQuery = $pdo->prepare("SELECT p.* FROM projects p JOIN project_members pm ON pm.project_id = p.id WHERE p.id = ? AND pm.student_id = ? AND pm.role = 'leader'");
    $projectQuery->execute([$projectId, (int) $user['id']]);
    $project = $projectQuery->fetch();
    if (!$project || !in_array($project['status'], ['approved', 'in_progress'], true)) {
        throw new PortalActionException('Only the leader of an approved project can submit work.');
    }
    $stageQuery = $pdo->prepare("SELECT s.id, s.name, s.status, COALESCE(bsd.start_date, s.default_start_date) AS starts_at, COALESCE(bsd.deadline, s.default_deadline) AS deadline_at FROM submission_stages s LEFT JOIN batch_stage_deadlines bsd ON bsd.stage_id = s.id AND bsd.batch_id = ? WHERE s.id = ?");
    $stageQuery->execute([(int) $project['batch_id'], $stageId]);
    $stage = $stageQuery->fetch();
    if (!$stage || $stage['status'] !== 'active') {
        throw new PortalActionException('This submission stage is unavailable.');
    }
    $latestQuery = $pdo->prepare('SELECT id, status, is_special_revision FROM submissions WHERE project_id = ? AND stage_id = ? ORDER BY version DESC LIMIT 1');
    $latestQuery->execute([$projectId, $stageId]);
    $latestBeforeUpload = $latestQuery->fetch();
    $specialDraft = $latestBeforeUpload && (int) $latestBeforeUpload['is_special_revision'] === 1 && $latestBeforeUpload['status'] === 'draft';
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if (!$specialDraft && $stage['starts_at'] && $now < new DateTimeImmutable($stage['starts_at'], new DateTimeZone('UTC'))) {
        throw new PortalActionException('This stage has not opened yet.');
    }
    if (!$specialDraft && $stage['deadline_at'] && $now > new DateTimeImmutable($stage['deadline_at'], new DateTimeZone('UTC'))) {
        throw new PortalActionException('The deadline for this stage has passed.');
    }
    [$file, $original, $extension, $mime] = submission_upload_info();
    $stored = bin2hex(random_bytes(24)) . '.' . $extension;
    $directory = APP_ROOT . '/storage/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new PortalActionException('Private upload storage is unavailable.');
    }
    $absolute = $directory . '/' . $stored;
    $relative = 'storage/uploads/' . $stored;
    if (!move_uploaded_file((string) $file['tmp_name'], $absolute)) {
        throw new PortalActionException('The uploaded file could not be stored.');
    }

    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT id, status, batch_id FROM projects WHERE id = ? FOR UPDATE');
        $lock->execute([$projectId]);
        $lockedProject = $lock->fetch();
        if (!$lockedProject || !in_array($lockedProject['status'], ['approved', 'in_progress'], true)) {
            throw new PortalActionException('Only the leader of an approved project can submit work.');
        }
        $leader = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND student_id = ? AND role = 'leader' FOR UPDATE");
        $leader->execute([$projectId, (int) $user['id']]);
        if (!$leader->fetchColumn()) {
            throw new PortalActionException('Only the project leader can submit work.');
        }
        $lockedStageQuery = $pdo->prepare('SELECT s.name, s.status, COALESCE(bsd.start_date, s.default_start_date) AS starts_at, COALESCE(bsd.deadline, s.default_deadline) AS deadline_at FROM submission_stages s LEFT JOIN batch_stage_deadlines bsd ON bsd.stage_id = s.id AND bsd.batch_id = ? WHERE s.id = ? FOR UPDATE');
        $lockedStageQuery->execute([(int) $lockedProject['batch_id'], $stageId]);
        $lockedStage = $lockedStageQuery->fetch();
        if (!$lockedStage || $lockedStage['status'] !== 'active') {
            throw new PortalActionException('This submission stage is unavailable.');
        }
        $latest = $pdo->prepare('SELECT id, status, is_special_revision FROM submissions WHERE project_id = ? AND stage_id = ? ORDER BY version DESC LIMIT 1 FOR UPDATE');
        $latest->execute([$projectId, $stageId]);
        $latestRow = $latest->fetch();
        $lockedSpecialDraft = $latestRow && (int) $latestRow['is_special_revision'] === 1 && $latestRow['status'] === 'draft';
        if (!$lockedSpecialDraft) {
            $lockedNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if ($lockedStage['starts_at'] && $lockedNow < new DateTimeImmutable($lockedStage['starts_at'], new DateTimeZone('UTC'))) {
                throw new PortalActionException('This stage has not opened yet.');
            }
            if ($lockedStage['deadline_at'] && $lockedNow > new DateTimeImmutable($lockedStage['deadline_at'], new DateTimeZone('UTC'))) {
                throw new PortalActionException('The deadline for this stage has passed.');
            }
        }
        if ($latestRow && (int) $latestRow['is_special_revision'] === 1 && $latestRow['status'] === 'draft') {
            $existingFile = $pdo->prepare('SELECT 1 FROM submission_files WHERE submission_id = ? LIMIT 1');
            $existingFile->execute([(int) $latestRow['id']]);
            if ($existingFile->fetchColumn()) {
                throw new PortalActionException('The special revision has already been submitted.');
            }
            $updateSpecial = $pdo->prepare("UPDATE submissions SET status = 'submitted', submitted_at = UTC_TIMESTAMP(), submitted_by = ?, remarks = ? WHERE id = ? AND status = 'draft' AND is_special_revision = 1");
            $updateSpecial->execute([(int) $user['id'], $remarks !== '' ? $remarks : null, (int) $latestRow['id']]);
            $submissionId = (int) $latestRow['id'];
            record_status($pdo, 'submission', $submissionId, 'draft', 'submitted', (int) $user['id'], 'Special revision submitted.');
        } else {
            if ($latestRow && ($latestRow['status'] !== 'revision_required' || (int) $latestRow['is_special_revision'] === 1)) {
                throw new PortalActionException('A new version is allowed only after a revision request.');
            }
            $versions = $pdo->prepare('SELECT COALESCE(MAX(version), 0) FROM submissions WHERE project_id = ? AND stage_id = ?');
            $versions->execute([$projectId, $stageId]);
            $version = (int) $versions->fetchColumn() + 1;
            $insert = $pdo->prepare("INSERT INTO submissions (project_id, stage_id, version, submitted_by, status, submitted_at, is_late, remarks) VALUES (?, ?, ?, ?, 'submitted', UTC_TIMESTAMP(), 0, ?)");
            $insert->execute([$projectId, $stageId, $version, (int) $user['id'], $remarks !== '' ? $remarks : null]);
            $submissionId = (int) $pdo->lastInsertId();
            record_status($pdo, 'submission', $submissionId, null, 'submitted', (int) $user['id'], 'Submitted for review.');
        }
        $addFile = $pdo->prepare('INSERT INTO submission_files (submission_id, original_name, stored_name, file_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
        $addFile->execute([$submissionId, $original, $stored, $relative, $mime, (int) $file['size']]);
        $guides = $pdo->prepare("SELECT faculty_id FROM project_guides WHERE project_id = ? AND status = 'active'");
        $guides->execute([$projectId]);
        $recipients = array_map('intval', $guides->fetchAll(PDO::FETCH_COLUMN));
        if (!$recipients) {
            $recipients = array_map('intval', $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN));
        }
        notify_users($pdo, $recipients, 'New project submission', $stage['name'] . ' is ready for review.', '/?page=dashboard', 'info');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_file($absolute)) {
            unlink($absolute);
        }
        throw $exception;
    }
    flash('success', 'Your ' . $lockedStage['name'] . ' submission was received.');
    redirect('/?page=dashboard');
}

function can_evaluate(PDO $pdo, array $user, int $projectId): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }
    $query = $pdo->prepare("SELECT 1 FROM project_guides WHERE project_id = ? AND faculty_id = ? AND role = 'primary_guide' AND status = 'active' LIMIT 1");
    $query->execute([$projectId, (int) $user['id']]);
    return (bool) $query->fetchColumn();
}

function evaluate_submission_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin', 'faculty');
    $submissionId = post_id('submission_id');
    $decision = post_string('decision');
    $comments = post_string('comments');
    if (strlen($comments) > 5000) {
        throw new PortalActionException('Evaluation feedback must be no more than 5000 bytes.');
    }
    if (!in_array($decision, ['approved', 'revision_required', 'rejected'], true)) {
        throw new PortalActionException('Choose a valid submission decision.');
    }
    $submissionQuery = $pdo->prepare("SELECT s.id, s.status, s.stage_id, s.is_special_revision, p.id AS project_id FROM submissions s JOIN projects p ON p.id = s.project_id WHERE s.id = ?");
    $submissionQuery->execute([$submissionId]);
    $submission = $submissionQuery->fetch();
    if (!$submission || !can_evaluate($pdo, $user, (int) $submission['project_id'])) {
        http_response_code(403);
        throw new PortalActionException('You are not assigned to evaluate this project.');
    }
    if (!in_array($submission['status'], ['submitted', 'resubmitted', 'under_review'], true)) {
        throw new PortalActionException('This submission is not open for evaluation.');
    }
    if ((int) $submission['is_special_revision'] === 1 && $decision === 'revision_required') {
        throw new PortalActionException('A special revision is final and can only be approved or rejected.');
    }
    $existing = $pdo->prepare('SELECT id FROM evaluations WHERE submission_id = ?');
    $existing->execute([$submissionId]);
    if ($existing->fetchColumn()) {
        throw new PortalActionException('This submission already has an evaluation.');
    }
    $criteriaQuery = $pdo->prepare("SELECT id, name, max_marks, weight FROM rubric_criteria WHERE stage_id = ? AND status = 'active' ORDER BY sort_order, id");
    $criteriaQuery->execute([(int) $submission['stage_id']]);
    $criteria = $criteriaQuery->fetchAll();
    if (!$criteria) {
        throw new PortalActionException('No active rubric is configured for this stage.');
    }
    $weightTotal = array_sum(array_map(static fn(array $row): float => (float) $row['weight'], $criteria));
    if (abs($weightTotal - 100.0) > 0.01) {
        throw new PortalActionException('The active rubric weights must total 100 before evaluation.');
    }
    $marks = $_POST['marks'] ?? [];
    if (!is_array($marks)) {
        throw new PortalActionException('Enter a mark for each rubric item.');
    }
    $weighted = 0.0;
    foreach ($criteria as $criterion) {
        $key = (string) $criterion['id'];
        $value = $marks[$key] ?? null;
        if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $value)) {
            throw new PortalActionException('Enter a numeric mark for every rubric item.');
        }
        $score = round((float) $value, 2);
        $max = (float) $criterion['max_marks'];
        if ($score < 0 || $score > $max) {
            throw new PortalActionException('Marks must be between zero and each criterion maximum.');
        }
        $weighted += ($score / $max) * (float) $criterion['weight'];
    }
    $percentage = round($weighted, 2);
    $rawTotal = array_sum(array_map(static fn(array $row): float => (float) $marks[(string) $row['id']], $criteria));
    $rawMaximum = array_sum(array_map(static fn(array $row): float => (float) $row['max_marks'], $criteria));
    $grade = $percentage >= 90 ? 'A+' : ($percentage >= 80 ? 'A' : ($percentage >= 70 ? 'B+' : ($percentage >= 60 ? 'B' : ($percentage >= 50 ? 'C' : 'F'))));

    $pdo->beginTransaction();
    try {
        $projectLock = $pdo->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
        $projectLock->execute([(int) $submission['project_id']]);
        if (!$projectLock->fetchColumn()) {
            throw new PortalActionException('Project not found.');
        }
        $submissionLock = $pdo->prepare('SELECT status FROM submissions WHERE id = ? FOR UPDATE');
        $submissionLock->execute([$submissionId]);
        $lockedStatus = $submissionLock->fetchColumn();
        if (!in_array($lockedStatus, ['submitted', 'resubmitted', 'under_review'], true)) {
            throw new PortalActionException('This submission is no longer open for evaluation.');
        }
        if ($user['role'] === 'faculty') {
            $guideLock = $pdo->prepare("SELECT id FROM project_guides WHERE project_id = ? AND faculty_id = ? AND role = 'primary_guide' AND status = 'active' FOR UPDATE");
            $guideLock->execute([(int) $submission['project_id'], (int) $user['id']]);
            if (!$guideLock->fetchColumn()) {
                throw new PortalActionException('You are no longer assigned as the primary guide.');
            }
        }
        $existingLock = $pdo->prepare('SELECT id FROM evaluations WHERE submission_id = ? FOR UPDATE');
        $existingLock->execute([$submissionId]);
        if ($existingLock->fetchColumn()) {
            throw new PortalActionException('This submission already has an evaluation.');
        }
        $insert = $pdo->prepare("INSERT INTO evaluations (submission_id, faculty_id, status, is_admin_override, total_obtained, total_max, percentage, grade, comments) VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?)");
        $insert->execute([$submissionId, (int) $user['id'], $user['role'] === 'admin' ? 1 : 0, $rawTotal, $rawMaximum, $percentage, $grade, $comments !== '' ? $comments : null]);
        $evaluationId = (int) $pdo->lastInsertId();
        record_status($pdo, 'evaluation', $evaluationId, null, 'draft', (int) $user['id'], 'Evaluation created.');
        $scoreInsert = $pdo->prepare('INSERT INTO evaluation_scores (evaluation_id, criteria_id, marks_obtained, criteria_name_snapshot, max_marks_snapshot, weight_snapshot) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($criteria as $criterion) {
            $scoreInsert->execute([
                $evaluationId,
                (int) $criterion['id'],
                (float) $marks[(string) $criterion['id']],
                $criterion['name'],
                (float) $criterion['max_marks'],
                (float) $criterion['weight'],
            ]);
        }
        $finalize = $pdo->prepare("UPDATE evaluations SET status = 'finalized', evaluated_at = UTC_TIMESTAMP() WHERE id = ?");
        $finalize->execute([$evaluationId]);
        record_status($pdo, 'evaluation', $evaluationId, 'draft', 'finalized', (int) $user['id'], 'Evaluation finalized.');
        $updateSubmission = $pdo->prepare('UPDATE submissions SET status = ? WHERE id = ?');
        $updateSubmission->execute([$decision, $submissionId]);
        record_status($pdo, 'submission', $submissionId, (string) $lockedStatus, $decision, (int) $user['id'], 'Evaluation finalized.');
        $members = $pdo->prepare('SELECT pm.student_id FROM project_members pm WHERE pm.project_id = ?');
        $members->execute([(int) $submission['project_id']]);
        notify_users($pdo, array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN)), 'Evaluation finalized', 'A submission received ' . number_format($percentage, 2) . '% (' . $grade . ').', '/?page=dashboard', $decision === 'approved' ? 'success' : 'warning');
        if ($user['role'] === 'admin') {
            record_audit($pdo, $user, 'evaluation.admin_finalize', 'submission', $submissionId, ['status' => $submission['status']], ['status' => $decision, 'percentage' => $percentage]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Evaluation finalized at ' . number_format($percentage, 2) . '% (' . $grade . ').');
    redirect('/?page=dashboard');
}

function mark_notification_read_action(PDO $pdo, array $user): never
{
    $notificationId = post_id('notification_id');
    $update = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $update->execute([$notificationId, (int) $user['id']]);
    redirect('/?page=notifications');
}

function request_submission_appeal_action(PDO $pdo, array $user): never
{
    require_role($user, 'student');
    $submissionId = post_id('submission_id');
    $reason = post_string('appeal_reason');
    if (strlen($reason) < 10 || strlen($reason) > 5000) {
        throw new PortalActionException('Explain the appeal in 10 to 5000 bytes.');
    }
    $projectLookup = $pdo->prepare('SELECT project_id FROM submissions WHERE id = ?');
    $projectLookup->execute([$submissionId]);
    $projectId = (int) ($projectLookup->fetchColumn() ?: 0);
    if (!$projectId) {
        throw new PortalActionException('Submission not found.');
    }
    $pdo->beginTransaction();
    try {
        $projectLock = $pdo->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
        $projectLock->execute([$projectId]);
        if (!$projectLock->fetchColumn()) {
            throw new PortalActionException('Project not found.');
        }
        $submissionQuery = $pdo->prepare("SELECT s.id, s.project_id, s.stage_id, s.version, s.status, s.is_special_revision, p.status AS project_status FROM submissions s JOIN projects p ON p.id = s.project_id JOIN project_members pm ON pm.project_id = p.id WHERE s.id = ? AND pm.student_id = ? AND pm.role = 'leader' FOR UPDATE");
        $submissionQuery->execute([$submissionId, (int) $user['id']]);
        $submission = $submissionQuery->fetch();
        if (!$submission || $submission['status'] !== 'rejected' || (int) $submission['is_special_revision'] === 1 || !in_array($submission['project_status'], ['approved', 'in_progress'], true)) {
            throw new PortalActionException('Only the team leader can appeal a rejected standard submission.');
        }
        $latest = $pdo->prepare('SELECT MAX(version) FROM submissions WHERE project_id = ? AND stage_id = ?');
        $latest->execute([(int) $submission['project_id'], (int) $submission['stage_id']]);
        if ((int) $latest->fetchColumn() !== (int) $submission['version']) {
            throw new PortalActionException('Only the latest rejected submission can be appealed.');
        }
        $existing = $pdo->prepare('SELECT id FROM submission_appeals WHERE submission_id = ?');
        $existing->execute([$submissionId]);
        if ($existing->fetchColumn()) {
            throw new PortalActionException('An appeal has already been filed for this submission.');
        }
        $insert = $pdo->prepare("INSERT INTO submission_appeals (submission_id, requested_by, appeal_reason, status) VALUES (?, ?, ?, 'pending')");
        $insert->execute([$submissionId, (int) $user['id'], $reason]);
        $appealId = (int) $pdo->lastInsertId();
        record_status($pdo, 'submission_appeal', $appealId, null, 'pending', (int) $user['id'], 'Appeal submitted.');
        record_audit($pdo, $user, 'submission.appeal', 'submission', $submissionId, null, ['appeal_id' => $appealId]);
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        notify_users($pdo, array_map('intval', $admins), 'Submission appeal received', 'A student appealed submission ' . $submissionId . '.', '/?page=dashboard', 'warning');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Your appeal was sent to the administrator.');
    redirect('/?page=dashboard');
}

function decide_appeal_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $appealId = post_id('appeal_id');
    $decision = post_string('decision');
    $reason = post_string('decision_reason');
    if (strlen($reason) > 5000) {
        throw new PortalActionException('The appeal decision note must be no more than 5000 bytes.');
    }
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new PortalActionException('Choose approve or reject.');
    }
    $lookup = $pdo->prepare('SELECT s.project_id FROM submission_appeals a JOIN submissions s ON s.id = a.submission_id WHERE a.id = ?');
    $lookup->execute([$appealId]);
    $projectId = (int) ($lookup->fetchColumn() ?: 0);
    if (!$projectId) {
        throw new PortalActionException('Appeal not found.');
    }

    $pdo->beginTransaction();
    try {
        $projectLock = $pdo->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
        $projectLock->execute([$projectId]);
        $appealQuery = $pdo->prepare("SELECT a.id, a.submission_id, a.requested_by, a.status, s.stage_id, s.version, s.is_special_revision, s.status AS submission_status FROM submission_appeals a JOIN submissions s ON s.id = a.submission_id WHERE a.id = ? FOR UPDATE");
        $appealQuery->execute([$appealId]);
        $appeal = $appealQuery->fetch();
        if (!$projectLock->fetchColumn() || !$appeal || $appeal['status'] !== 'pending' || $appeal['submission_status'] !== 'rejected' || (int) $appeal['is_special_revision'] === 1) {
            throw new PortalActionException('This appeal is no longer available for a decision.');
        }
        $latest = $pdo->prepare('SELECT MAX(version) FROM submissions WHERE project_id = ? AND stage_id = ?');
        $latest->execute([$projectId, (int) $appeal['stage_id']]);
        if ((int) $latest->fetchColumn() !== (int) $appeal['version']) {
            throw new PortalActionException('The appealed submission is no longer the latest version.');
        }

        $resultingSubmissionId = null;
        if ($decision === 'approved') {
            $versionQuery = $pdo->prepare('SELECT COALESCE(MAX(version), 0) FROM submissions WHERE project_id = ? AND stage_id = ?');
            $versionQuery->execute([$projectId, (int) $appeal['stage_id']]);
            $version = (int) $versionQuery->fetchColumn() + 1;
            $insert = $pdo->prepare("INSERT INTO submissions (project_id, stage_id, version, is_special_revision, submitted_by, status, remarks) VALUES (?, ?, ?, 1, ?, 'draft', 'Approved special revision')");
            $insert->execute([$projectId, (int) $appeal['stage_id'], $version, (int) $appeal['requested_by']]);
            $resultingSubmissionId = (int) $pdo->lastInsertId();
            record_status($pdo, 'submission', $resultingSubmissionId, null, 'draft', (int) $user['id'], 'Special revision granted by appeal.');
        }
        $update = $pdo->prepare('UPDATE submission_appeals SET status = ?, reviewed_by = ?, decision_reason = ?, reviewed_at = UTC_TIMESTAMP(), resulting_submission_id = ? WHERE id = ?');
        $update->execute([$decision, (int) $user['id'], $reason !== '' ? $reason : null, $resultingSubmissionId, $appealId]);
        record_status($pdo, 'submission_appeal', $appealId, 'pending', $decision, (int) $user['id'], $reason);
        record_audit($pdo, $user, 'submission.appeal_' . $decision, 'submission', (int) $appeal['submission_id'], ['appeal_id' => $appealId, 'status' => 'pending'], ['status' => $decision, 'resulting_submission_id' => $resultingSubmissionId]);
        $members = $pdo->prepare('SELECT student_id FROM project_members WHERE project_id = ?');
        $members->execute([$projectId]);
        $message = $decision === 'approved' ? 'A special revision was granted. Submit it from the project dashboard.' : 'The appeal was rejected.';
        notify_users($pdo, array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN)), 'Appeal ' . $decision, $message, '/?page=dashboard', $decision === 'approved' ? 'success' : 'warning');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Appeal ' . $decision . '.');
    redirect('/?page=dashboard');
}

function mark_all_notifications_read_action(PDO $pdo, array $user): never
{
    $update = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $update->execute([(int) $user['id']]);
    redirect('/?page=notifications');
}

function send_feedback_action(PDO $pdo, array $user): never
{
    $submissionId = post_id('submission_id');
    $message = post_string('message');
    if ($message === '' || strlen($message) > 5000) {
        throw new PortalActionException('Feedback must be between 1 and 5000 bytes.');
    }
    $submissionQuery = $pdo->prepare('SELECT id, project_id FROM submissions WHERE id = ?');
    $submissionQuery->execute([$submissionId]);
    $submission = $submissionQuery->fetch();
    if (!$submission) {
        throw new PortalActionException('Submission not found.');
    }

    $parentRaw = post_string('parent_id');
    $parentId = null;
    if ($parentRaw !== '') {
        $parentId = filter_var($parentRaw, FILTER_VALIDATE_INT);
        if (!$parentId || $parentId < 1) {
            throw new PortalActionException('Choose a valid feedback message to reply to.');
        }
        $parentQuery = $pdo->prepare('SELECT id FROM feedback WHERE id = ? AND submission_id = ?');
        $parentQuery->execute([(int) $parentId, $submissionId]);
        if (!$parentQuery->fetchColumn()) {
            throw new PortalActionException('That reply belongs to a different submission.');
        }
    }
    $pdo->beginTransaction();
    try {
        $projectLock = $pdo->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
        $projectLock->execute([(int) $submission['project_id']]);
        if (!$projectLock->fetchColumn()) {
            throw new PortalActionException('Project not found.');
        }
        require_project_access($pdo, $user, (int) $submission['project_id']);
        $insert = $pdo->prepare('INSERT INTO feedback (submission_id, user_id, message, parent_id) VALUES (?, ?, ?, ?)');
        $insert->execute([$submissionId, (int) $user['id'], $message, $parentId]);

        if ($user['role'] === 'student') {
            $recipientQuery = $pdo->prepare("SELECT faculty_id FROM project_guides WHERE project_id = ? AND status = 'active'");
        } else {
            $recipientQuery = $pdo->prepare('SELECT student_id FROM project_members WHERE project_id = ?');
        }
        $recipientQuery->execute([(int) $submission['project_id']]);
        $recipients = array_map('intval', $recipientQuery->fetchAll(PDO::FETCH_COLUMN));
        if ($parentId) {
            $parentAuthor = $pdo->prepare('SELECT user_id FROM feedback WHERE id = ?');
            $parentAuthor->execute([(int) $parentId]);
            $recipients[] = (int) $parentAuthor->fetchColumn();
        }
        notify_users($pdo, array_values(array_diff($recipients, [(int) $user['id']])), 'New feedback', 'A new comment was added to a project submission.', '/?page=dashboard', 'info');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Feedback added.');
    redirect('/?page=dashboard');
}

function parse_utc_datetime_input(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d\\TH:i') !== $value) {
        throw new PortalActionException('Enter a valid UTC date and time.');
    }
    return $date->format('Y-m-d H:i:s');
}

function save_deadline_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $batchId = post_id('batch_id');
    $stageId = post_id('stage_id');
    $start = parse_utc_datetime_input(post_string('start_date'));
    $deadline = parse_utc_datetime_input(post_string('deadline'));
    $requiredRaw = post_string('is_required');
    if ($requiredRaw !== '' && !in_array($requiredRaw, ['0', '1'], true)) {
        throw new PortalActionException('Choose a valid required-stage setting.');
    }
    if (($start === null) !== ($deadline === null)) {
        throw new PortalActionException('Set both dates or leave both dates blank to inherit the default window.');
    }
    if ($start !== null && $deadline !== null && $start >= $deadline) {
        throw new PortalActionException('The deadline must be after the stage start.');
    }
    $pdo->beginTransaction();
    try {
        $check = $pdo->prepare("SELECT 1 FROM batches b JOIN submission_stages s ON s.id = ? WHERE b.id = ? AND b.status = 'active'");
        $check->execute([$stageId, $batchId]);
        if (!$check->fetchColumn()) {
            throw new PortalActionException('Choose an active batch and a valid stage.');
        }
        $existing = $pdo->prepare('SELECT id FROM batch_stage_deadlines WHERE batch_id = ? AND stage_id = ? FOR UPDATE');
        $existing->execute([$batchId, $stageId]);
        $id = $existing->fetchColumn();
        $oldQuery = $pdo->prepare('SELECT start_date, deadline, is_required FROM batch_stage_deadlines WHERE batch_id = ? AND stage_id = ?');
        $oldQuery->execute([$batchId, $stageId]);
        $oldValues = $oldQuery->fetch();
        if ($id) {
            $update = $pdo->prepare('UPDATE batch_stage_deadlines SET start_date = ?, deadline = ?, is_required = ? WHERE id = ?');
            $update->execute([$start, $deadline, $requiredRaw === '' ? null : (int) $requiredRaw, (int) $id]);
        } else {
            $insert = $pdo->prepare('INSERT INTO batch_stage_deadlines (batch_id, stage_id, start_date, deadline, is_required) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$batchId, $stageId, $start, $deadline, $requiredRaw === '' ? null : (int) $requiredRaw]);
        }
        record_audit($pdo, $user, 'deadline.update', 'batch_stage_deadline', (int) ($id ?: $pdo->lastInsertId()), $oldValues ?: null, ['batch_id' => $batchId, 'stage_id' => $stageId, 'start_date' => $start, 'deadline' => $deadline, 'is_required' => $requiredRaw]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Stage deadline settings saved.');
    redirect('/?page=deadlines');
}

function save_rubric_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin');
    $stageId = post_id('stage_id');
    $submittedRows = $_POST['criteria'] ?? [];
    if (!is_array($submittedRows)) {
        throw new PortalActionException('Enter rubric criteria.');
    }
    $rows = [];
    $weightTotal = 0.0;
    $seenIds = [];
    foreach ($submittedRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
        if (($row['remove'] ?? '') === '1') {
            if (!$id || $id < 1) {
                throw new PortalActionException('Only saved criteria can be deactivated.');
            }
            continue;
        }
        $name = is_string($row['name'] ?? null) ? trim($row['name']) : '';
        $description = is_string($row['description'] ?? null) ? trim($row['description']) : '';
        $maxRaw = is_scalar($row['max_marks'] ?? null) ? trim((string) $row['max_marks']) : '';
        $weightRaw = is_scalar($row['weight'] ?? null) ? trim((string) $row['weight']) : '';
        if ($name === '' && $maxRaw === '' && $weightRaw === '' && $description === '') {
            continue;
        }
        if ($name === '' || strlen($name) > 150 || strlen($description) > 5000 ||
            !preg_match('/\A\d{1,3}(?:\.\d{1,2})?\z/', $maxRaw) ||
            !preg_match('/\A\d{1,3}(?:\.\d{1,2})?\z/', $weightRaw)) {
            throw new PortalActionException('Each rubric row needs a name, positive maximum, and valid weight.');
        }
        $max = (float) $maxRaw;
        $weight = (float) $weightRaw;
        if ($max <= 0 || $max > 999.99 || $weight <= 0 || $weight > 100) {
            throw new PortalActionException('Maximum marks must be positive; each weight must be between 0 and 100.');
        }
        $id = $id && $id > 0 ? (int) $id : null;
        if ($id !== null && isset($seenIds[$id])) {
            throw new PortalActionException('A rubric criterion was included more than once.');
        }
        if ($id !== null) {
            $seenIds[$id] = true;
        }
        $weightTotal += $weight;
        $rows[] = ['id' => $id, 'name' => $name, 'description' => $description, 'max' => $max, 'weight' => $weight];
    }
    if (!$rows || abs($weightTotal - 100.0) > 0.01) {
        throw new PortalActionException('A rubric must contain criteria whose weights total exactly 100.');
    }

    $pdo->beginTransaction();
    try {
        $stage = $pdo->prepare('SELECT id FROM submission_stages WHERE id = ? FOR UPDATE');
        $stage->execute([$stageId]);
        if (!$stage->fetchColumn()) {
            throw new PortalActionException('Stage not found.');
        }
        $oldCriteriaQuery = $pdo->prepare("SELECT id, name, description, max_marks, weight FROM rubric_criteria WHERE stage_id = ? AND status = 'active' ORDER BY sort_order, id");
        $oldCriteriaQuery->execute([$stageId]);
        $oldCriteria = $oldCriteriaQuery->fetchAll();
        $deactivate = $pdo->prepare("UPDATE rubric_criteria SET status = 'inactive' WHERE stage_id = ? AND status = 'active'");
        $deactivate->execute([$stageId]);
        $update = $pdo->prepare("UPDATE rubric_criteria SET name = ?, description = ?, max_marks = ?, weight = ?, sort_order = ?, status = 'active' WHERE id = ? AND stage_id = ?");
        $insert = $pdo->prepare("INSERT INTO rubric_criteria (stage_id, name, description, max_marks, weight, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        foreach ($rows as $order => $row) {
            if ($row['id'] !== null) {
                $update->execute([$row['name'], $row['description'] !== '' ? $row['description'] : null, $row['max'], $row['weight'], $order + 1, $row['id'], $stageId]);
                if ($update->rowCount() === 0) {
                    throw new PortalActionException('A rubric criterion no longer belongs to this stage.');
                }
            } else {
                $insert->execute([$stageId, $row['name'], $row['description'] !== '' ? $row['description'] : null, $row['max'], $row['weight'], $order + 1]);
            }
        }
        record_audit($pdo, $user, 'rubric.update', 'submission_stage', $stageId, ['criteria' => $oldCriteria], ['criteria' => $rows, 'weight_total' => $weightTotal]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Rubric saved.');
    redirect('/?page=rubrics&stage_id=' . $stageId);
}

function complete_project_action(PDO $pdo, array $user): never
{
    require_role($user, 'admin', 'faculty');
    $projectId = post_id('project_id');
    $remarks = post_string('completion_remarks');
    if (strlen($remarks) > 5000) {
        throw new PortalActionException('Completion notes must be no more than 5000 bytes.');
    }
    $pdo->beginTransaction();
    try {
        $projectQuery = $pdo->prepare('SELECT id, batch_id, status FROM projects WHERE id = ? FOR UPDATE');
        $projectQuery->execute([$projectId]);
        $project = $projectQuery->fetch();
        if (!$project || !in_array($project['status'], ['approved', 'in_progress'], true)) {
            throw new PortalActionException('Only an active approved project can be completed.');
        }
        if (!can_evaluate($pdo, $user, $projectId)) {
            throw new PortalActionException('Only the active primary guide or an administrator can sign off this project.');
        }
        $stages = $pdo->prepare("SELECT s.id, s.name FROM submission_stages s LEFT JOIN batch_stage_deadlines bsd ON bsd.stage_id = s.id AND bsd.batch_id = ? WHERE s.status = 'active' AND COALESCE(bsd.is_required, s.is_required) = 1 ORDER BY s.sort_order, s.id");
        $stages->execute([(int) $project['batch_id']]);
        $requiredStages = $stages->fetchAll();
        if (!$requiredStages) {
            throw new PortalActionException('At least one active required stage must be configured before project sign-off.');
        }
        $latestQuery = $pdo->prepare('SELECT s.status, e.status AS evaluation_status FROM submissions s LEFT JOIN evaluations e ON e.submission_id = s.id WHERE s.project_id = ? AND s.stage_id = ? ORDER BY s.version DESC LIMIT 1');
        foreach ($requiredStages as $stage) {
            $latestQuery->execute([$projectId, (int) $stage['id']]);
            $latest = $latestQuery->fetch();
            if (!$latest || $latest['status'] !== 'approved' || $latest['evaluation_status'] !== 'finalized') {
                throw new PortalActionException('The required stage “' . $stage['name'] . '” still needs an approved finalized submission.');
            }
        }
        $update = $pdo->prepare("UPDATE projects SET status = 'completed', completed_at = UTC_TIMESTAMP(), completed_by = ?, completion_remarks = ? WHERE id = ?");
        $update->execute([(int) $user['id'], $remarks !== '' ? $remarks : null, $projectId]);
        record_status($pdo, 'project', $projectId, (string) $project['status'], 'completed', (int) $user['id'], $remarks);
        record_audit($pdo, $user, 'project.complete', 'project', $projectId, ['status' => $project['status']], ['status' => 'completed', 'remarks' => $remarks]);
        $members = $pdo->prepare('SELECT student_id FROM project_members WHERE project_id = ?');
        $members->execute([$projectId]);
        notify_users($pdo, array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN)), 'Project completed', 'Your project has received its final sign-off.', '/?page=dashboard', 'success');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    flash('success', 'Project completed.');
    redirect('/?page=dashboard');
}

function process_action(PDO $pdo, ?array $user): never
{
    verify_csrf();
    $action = post_string('action');
    if ($action === 'login' && !$user) {
        login_action($pdo);
    }
    if (!$user) {
        if ($action === 'request_password_reset') {
            request_password_reset_action($pdo);
        }
        if ($action === 'reset_password') {
            reset_password_action($pdo);
        }
        redirect('/?page=login');
    }
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('/?page=login');
    }
    if ($action === 'change_password') {
        change_password_action($pdo, $user);
    }
    if ((int) $user['force_password_change'] === 1) {
        redirect('/?page=password');
    }
    match ($action) {
        'create_user' => create_user_action($pdo, $user),
        'create_department' => create_department_action($pdo, $user),
        'update_department' => update_department_action($pdo, $user),
        'create_batch' => create_batch_action($pdo, $user),
        'update_batch' => update_batch_action($pdo, $user),
        'create_stage' => create_stage_action($pdo, $user),
        'update_stage' => update_stage_action($pdo, $user),
        'set_stage_status' => set_stage_status_action($pdo, $user),
        'set_record_status' => set_record_status_action($pdo, $user),
        'create_project' => create_project_action($pdo, $user),
        'update_project_proposal' => update_project_proposal_action($pdo, $user),
        'add_project_member' => add_project_member_action($pdo, $user),
        'remove_project_member' => remove_project_member_action($pdo, $user),
        'decide_project' => decide_project_action($pdo, $user),
        'assign_guide' => assign_guide_action($pdo, $user),
        'submit_stage' => submit_stage_action($pdo, $user),
        'evaluate_submission' => evaluate_submission_action($pdo, $user),
        'request_submission_appeal' => request_submission_appeal_action($pdo, $user),
        'decide_appeal' => decide_appeal_action($pdo, $user),
        'mark_notification_read' => mark_notification_read_action($pdo, $user),
        'mark_all_notifications_read' => mark_all_notifications_read_action($pdo, $user),
        'send_feedback' => send_feedback_action($pdo, $user),
        'save_deadline' => save_deadline_action($pdo, $user),
        'save_rubric' => save_rubric_action($pdo, $user),
        'complete_project' => complete_project_action($pdo, $user),
        default => throw new RuntimeException('Unknown action.'),
    };
}
