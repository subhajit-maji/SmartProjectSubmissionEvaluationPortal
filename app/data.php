<?php
declare(strict_types=1);

function dashboard_data(PDO $pdo, array $user): array
{
    if ($user['role'] === 'admin') {
        $pending = $pdo->query("SELECT p.id, p.title, p.abstract, p.created_at, u.name AS student_name, d.name AS department_name, b.name AS batch_name FROM projects p JOIN users u ON u.id = p.created_by JOIN departments d ON d.id = p.department_id JOIN batches b ON b.id = p.batch_id WHERE p.status = 'pending_approval' ORDER BY p.created_at")->fetchAll();
        $pendingAppeals = $pdo->query("SELECT a.id, a.submission_id, a.appeal_reason, a.created_at, u.name AS student_name, p.title AS project_title, s.version, st.name AS stage_name FROM submission_appeals a JOIN submissions s ON s.id = a.submission_id JOIN projects p ON p.id = s.project_id JOIN submission_stages st ON st.id = s.stage_id JOIN users u ON u.id = a.requested_by WHERE a.status = 'pending' ORDER BY a.created_at")->fetchAll();
        $allProjects = $pdo->query("SELECT p.id, p.title, p.status, p.department_id, d.name AS department_name, pg.faculty_id AS primary_guide_id, fu.name AS primary_guide_name FROM projects p JOIN departments d ON d.id = p.department_id LEFT JOIN project_guides pg ON pg.project_id = p.id AND pg.role = 'primary_guide' AND pg.status = 'active' LEFT JOIN users fu ON fu.id = pg.faculty_id ORDER BY p.updated_at DESC LIMIT 50")->fetchAll();
        $faculty = $pdo->query("SELECT id, name, department_id FROM users WHERE role = 'faculty' AND status = 'active' ORDER BY name")->fetchAll();
        $departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();
        $departmentsAll = $pdo->query('SELECT id, name, code, status FROM departments ORDER BY name')->fetchAll();
        $batches = $pdo->query("SELECT id, name FROM batches WHERE status = 'active' ORDER BY year DESC")->fetchAll();
        $batchesAll = $pdo->query('SELECT id, name, year, status FROM batches ORDER BY year DESC')->fetchAll();
        $users = $pdo->query('SELECT id, name, email, role, status FROM users ORDER BY created_at DESC LIMIT 50')->fetchAll();
        $counts = $pdo->query("SELECT (SELECT COUNT(*) FROM projects WHERE status = 'pending_approval') AS pending, (SELECT COUNT(*) FROM projects) AS projects, (SELECT COUNT(*) FROM users WHERE status = 'active') AS active_users")->fetch();
        return compact('pending', 'pendingAppeals', 'allProjects', 'faculty', 'departments', 'departmentsAll', 'batches', 'batchesAll', 'users', 'counts');
    }

    if ($user['role'] === 'student') {
        $projectQuery = $pdo->prepare('SELECT p.*, pm.role AS member_role FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.student_id = ? ORDER BY p.created_at DESC');
        $projectQuery->execute([(int) $user['id']]);
        $projects = $projectQuery->fetchAll();
        $membersQuery = $pdo->prepare('SELECT pm.student_id, u.name, u.email, pm.role, pm.joined_at FROM project_members pm JOIN users u ON u.id = pm.student_id WHERE pm.project_id = ? ORDER BY pm.role DESC, u.name');
        $stagesQuery = $pdo->prepare('SELECT s.id, s.name, s.description, s.sort_order, COALESCE(bsd.start_date, s.default_start_date) AS starts_at, COALESCE(bsd.deadline, s.default_deadline) AS deadline_at FROM submission_stages s LEFT JOIN batch_stage_deadlines bsd ON bsd.stage_id = s.id AND bsd.batch_id = ? WHERE s.status = \'active\' ORDER BY s.sort_order, s.id');
        $submissionsQuery = $pdo->prepare('SELECT s.id, s.stage_id, s.version, s.status, s.is_special_revision, s.submitted_at, s.remarks, st.name AS stage_name, e.percentage, e.grade, e.comments AS evaluation_comments, sf.id AS file_id, sa.id AS appeal_id, sa.status AS appeal_status, sa.decision_reason AS appeal_decision_reason FROM submissions s JOIN submission_stages st ON st.id = s.stage_id LEFT JOIN evaluations e ON e.submission_id = s.id LEFT JOIN submission_files sf ON sf.submission_id = s.id LEFT JOIN submission_appeals sa ON sa.submission_id = s.id WHERE s.project_id = ? ORDER BY st.sort_order, s.version DESC');
        foreach ($projects as &$project) {
            $membersQuery->execute([(int) $project['id']]);
            $project['members'] = $membersQuery->fetchAll();
            $stagesQuery->execute([(int) $project['batch_id']]);
            $project['stages'] = $stagesQuery->fetchAll();
            $submissionsQuery->execute([(int) $project['id']]);
            $project['submissions'] = $submissionsQuery->fetchAll();
        }
        unset($project);
        $submissionIds = [];
        foreach ($projects as $project) {
            foreach ($project['submissions'] as $submission) {
                $submissionIds[] = (int) $submission['id'];
            }
        }
        $feedback = feedback_by_submission($pdo, $submissionIds);
        foreach ($projects as &$project) {
            foreach ($project['submissions'] as &$submission) {
                $submission['feedback'] = $feedback[(int) $submission['id']] ?? [];
            }
            unset($submission);
        }
        unset($project);
        return compact('projects');
    }

    $projectsQuery = $pdo->prepare("SELECT p.id, p.title, p.status AS project_status, pg.role FROM project_guides pg JOIN projects p ON p.id = pg.project_id WHERE pg.faculty_id = ? AND pg.status = 'active' ORDER BY p.updated_at DESC");
    $projectsQuery->execute([(int) $user['id']]);
    $projects = $projectsQuery->fetchAll();
    $submissions = [];
    if ($projects) {
        $ids = array_map(static fn(array $row): int => (int) $row['id'], $projects);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $query = $pdo->prepare("SELECT s.id, s.project_id, s.stage_id, s.version, s.status, s.is_special_revision, s.submitted_at, s.remarks, st.name AS stage_name, p.title AS project_title, e.id AS evaluation_id, e.percentage, e.grade, e.comments AS evaluation_comments, sf.id AS file_id, pg.role AS guide_role FROM submissions s JOIN projects p ON p.id = s.project_id JOIN submission_stages st ON st.id = s.stage_id JOIN project_guides pg ON pg.project_id = p.id AND pg.faculty_id = ? AND pg.status = 'active' LEFT JOIN evaluations e ON e.submission_id = s.id LEFT JOIN submission_files sf ON sf.submission_id = s.id WHERE s.project_id IN ($marks) ORDER BY s.submitted_at DESC");
        $query->execute(array_merge([(int) $user['id']], $ids));
        $submissions = $query->fetchAll();
    }
    $feedback = feedback_by_submission($pdo, array_map(static fn(array $row): int => (int) $row['id'], $submissions));
    foreach ($submissions as &$submission) {
        $submission['feedback'] = $feedback[(int) $submission['id']] ?? [];
    }
    unset($submission);
    $criteriaByStage = [];
    if ($submissions) {
        $stageIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['stage_id'], $submissions)));
        $stageMarks = implode(',', array_fill(0, count($stageIds), '?'));
        $criteriaQuery = $pdo->prepare("SELECT id, stage_id, name, max_marks, weight FROM rubric_criteria WHERE status = 'active' AND stage_id IN ($stageMarks) ORDER BY sort_order, id");
        $criteriaQuery->execute($stageIds);
        foreach ($criteriaQuery->fetchAll() as $criterion) {
            $criteriaByStage[(int) $criterion['stage_id']][] = $criterion;
        }
    }
    return compact('projects', 'submissions', 'criteriaByStage');
}

function rubric_admin_data(PDO $pdo, int $requestedStageId): array
{
    $stages = $pdo->query('SELECT id, name, description, default_start_date, default_deadline, is_required, sort_order, status FROM submission_stages ORDER BY sort_order, id')->fetchAll();
    if (!$stages) {
        return ['stages' => [], 'stageId' => 0, 'stageStatus' => 'inactive', 'stage' => null, 'criteria' => []];
    }
    $ids = array_map(static fn(array $row): int => (int) $row['id'], $stages);
    $stageId = in_array($requestedStageId, $ids, true) ? $requestedStageId : $ids[0];
    $selectedStage = null;
    foreach ($stages as $stage) {
        if ((int) $stage['id'] === $stageId) {
            $selectedStage = $stage;
            break;
        }
    }
    $criteriaQuery = $pdo->prepare("SELECT id, name, description, max_marks, weight, sort_order FROM rubric_criteria WHERE stage_id = ? AND status = 'active' ORDER BY sort_order, id");
    $criteriaQuery->execute([$stageId]);
    return ['stages' => $stages, 'stageId' => $stageId, 'stageStatus' => $selectedStage['status'] ?? 'inactive', 'stage' => $selectedStage, 'criteria' => $criteriaQuery->fetchAll()];
}

function deadline_admin_data(PDO $pdo): array
{
    $batches = $pdo->query("SELECT id, name FROM batches WHERE status = 'active' ORDER BY year DESC")->fetchAll();
    $stages = $pdo->query('SELECT id, name, status, default_start_date, default_deadline, is_required FROM submission_stages ORDER BY sort_order, id')->fetchAll();
    $overrides = $pdo->query('SELECT batch_id, stage_id, start_date, deadline, is_required FROM batch_stage_deadlines')->fetchAll();
    $byKey = [];
    foreach ($overrides as $row) {
        $byKey[(int) $row['batch_id'] . ':' . (int) $row['stage_id']] = $row;
    }
    return compact('batches', 'stages', 'byKey');
}

function datetime_local_value(?string $value): string
{
    return $value ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
}

function download_file(PDO $pdo, array $user, int $fileId): never
{
    $query = $pdo->prepare('SELECT sf.id, sf.original_name, sf.stored_name, sf.mime_type, sf.file_size, s.project_id FROM submission_files sf JOIN submissions s ON s.id = sf.submission_id WHERE sf.id = ?');
    $query->execute([$fileId]);
    $file = $query->fetch();
    if (!$file) {
        http_response_code(404);
        exit('File not found.');
    }
    require_project_access($pdo, $user, (int) $file['project_id']);
    $base = realpath(APP_ROOT . '/storage/uploads');
    $path = $base ? realpath($base . DIRECTORY_SEPARATOR . basename((string) $file['stored_name'])) : false;
    if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
        http_response_code(404);
        exit('File not found.');
    }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="submission"; filename*=UTF-8\'\'' . rawurlencode((string) $file['original_name']));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
