<?php
require 'includes/auth.php';
require 'includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

foreach ($data as $item) {
    $stmt = $pdo->prepare("
        UPDATE Hours
        SET Prio = ?
        WHERE Project = ? AND Activity = ? AND Person = ?
    ");
    $stmt->execute([$item['priority'], $item['projectId'], $item['activityId'], $item['personId']]);
}

echo json_encode(['success' => true]);
