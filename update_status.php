<?php
require 'includes/auth.php';
require 'includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$activityId = (int) $data['activityId'];
$projectId = (int) $data['projectId'];
$personId = (int) $data['personId'];
$year = $data['year'];
$statusId = $data['newStatusId'];

$update = $pdo->prepare("
    UPDATE Hours
    SET Status = ?
    WHERE Project = ? AND Activity = ? AND Person = ? AND `Year` = ?
");
$update->execute([$statusId, $projectId, $activityId, $personId, $year]);
echo json_encode(['success' => true]);

?>