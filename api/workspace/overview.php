<?php
// ============================================================
// WORKSPACE OVERVIEW API
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');
requireAuth();

$pdo = getDB();
if (currentUserId() <= 0) { http_response_code(401); echo json_encode(['success'=>false]); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'stats';

    if ($action === 'dashboard_context') {
        $data = [];
        $today = date('Y-m-d');
        
        // 1. TODAY: Priority tasks and today's events
        $stmtTasks = $pdo->prepare('SELECT id, title, priority, status FROM ws_tasks WHERE status != "completed" ORDER BY CASE priority WHEN "urgent" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END ASC LIMIT 5');
        $stmtTasks->execute();
        $data['today_tasks'] = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        $stmtEvents = $pdo->prepare('SELECT id, title, event_date, event_time FROM ws_events WHERE event_date = ? ORDER BY event_time ASC LIMIT 5');
        $stmtEvents->execute([$today]);
        $data['today_events'] = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

        // 2. CONTINUE: Recent activity (last note, last project, last course)
        $data['continue_course'] = $pdo->query('SELECT id, name FROM ws_courses WHERE status="in_progress" ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
        $data['continue_project'] = $pdo->query('SELECT id, name FROM ws_projects WHERE status="active" ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
        $data['continue_note'] = $pdo->query('SELECT id, title FROM ws_notes ORDER BY updated_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;

        // 3. PROGRESS: Active goals, semester stats, skill counts
        $data['active_goals'] = $pdo->query('SELECT COUNT(*) FROM ws_goals WHERE status="active"')->fetchColumn();
        $data['active_projects_count'] = $pdo->query('SELECT COUNT(*) FROM ws_projects WHERE status="active"')->fetchColumn();
        $data['learning_skills'] = $pdo->query('SELECT COUNT(*) FROM ws_skills WHERE status IN ("learning", "practicing")')->fetchColumn();
        
        // 4. UPCOMING: Future events and deadlines
        $stmtUpcoming = $pdo->prepare('SELECT id, title, event_date FROM ws_events WHERE event_date > ? ORDER BY event_date ASC LIMIT 5');
        $stmtUpcoming->execute([$today]);
        $data['upcoming_events'] = $stmtUpcoming->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // Default: General Stats (used by ws-overview.php)
    $stats = [];
    $stats['tasks_total'] = $pdo->query('SELECT COUNT(*) FROM ws_tasks')->fetchColumn();
    $stats['tasks_pending'] = $pdo->query('SELECT COUNT(*) FROM ws_tasks WHERE status != "completed"')->fetchColumn();
    $stats['projects_active'] = $pdo->query('SELECT COUNT(*) FROM ws_projects WHERE status = "active"')->fetchColumn();
    $stats['courses_active'] = $pdo->query('SELECT COUNT(*) FROM ws_courses WHERE status = "in_progress"')->fetchColumn();
    $stats['reading_active'] = $pdo->query('SELECT COUNT(*) FROM ws_reading_items WHERE status = "reading"')->fetchColumn();
    $stats['goals_active'] = $pdo->query('SELECT COUNT(*) FROM ws_goals WHERE status = "active"')->fetchColumn();
    $stats['notes_total'] = $pdo->query('SELECT COUNT(*) FROM ws_notes')->fetchColumn();
    
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}
