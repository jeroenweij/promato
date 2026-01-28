<?php
/**
 * AJAX endpoint for saving sync log to database
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'log') {
    $success = isset($_POST['success']) && $_POST['success'] === '1';
    $matched = (int)($_POST['matched'] ?? 0);
    $failed = (int)($_POST['failed'] ?? 0);
    $sprints = (int)($_POST['sprints'] ?? 0);
    $message = $_POST['message'] ?? '';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO SyncLog (UserId, Success, SyncType, ProjectsMatched, ProjectsFailed, SprintsSynced, Message)
            VALUES (:userId, :success, 'project', :matched, :failed, :sprints, :message)
        ");
        $stmt->execute([
            ':userId' => $_SESSION['user_id'],
            ':success' => $success ? 1 : 0,
            ':matched' => $matched,
            ':failed' => $failed,
            ':sprints' => $sprints,
            ':message' => $message
        ]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
