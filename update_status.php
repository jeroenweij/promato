<?php
require 'includes/auth.php';
require 'includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$activityId = (int) $data['activityId'];
$projectId = (int) $data['projectId'];
$personId = (int) $data['personId'];
$statusId = $data['newStatusId'];

$update = $pdo->prepare("
    UPDATE Hours
    SET StatusId = ?
    WHERE Project = ? AND Activity = ? AND Person = ?
");
$update->execute([$statusId, $projectId, $activityId, $personId]);
echo json_encode(['success' => true]);

?>