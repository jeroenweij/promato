<?php
require 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/csrf.php';

// Set proper Content-Type header
header('Content-Type: application/json');

// Verify CSRF token
csrf_protect();

$data = json_decode(file_get_contents('php://input'), true);

// Common variables
$projectId = $data['projectId'] ?? null;
$statusId = $data['newStatusId'] ?? null;

// Hours-specific variables
$activityId = $data['activityId'] ?? null;
$personId = $data['personId'] ?? null;
$year = $data['year'] ?? null;
if ($projectId !== null && $statusId !== null) {
    if ($activityId !== null && $personId !== null && $year !== null) {
        // Update Hours table
        $update = $pdo->prepare("
            UPDATE Hours
            SET Status = ?
            WHERE Project = ? AND Activity = ? AND Person = ? AND `Year` = ?
        ");
        $update->execute([$statusId, $projectId, $activityId, $personId, $year]);

    } else {
        // Update Projects table
        $update = $pdo->prepare("
            UPDATE Projects
            SET Status = ?
            WHERE ID = ?
        ");
        $update->execute([$statusId, $projectId]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

echo json_encode(['success' => true]);
?>
