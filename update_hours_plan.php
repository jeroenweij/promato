<?php
require 'includes/auth.php';
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = $_POST['project'] ?? null;
    $activity = $_POST['activity'] ?? null;
    $person = $_POST['person'] ?? null;
    $year = $_POST['year'] ?? null;
    $plan = floatval($_POST['plan'] ?? 0);
    $planRaw = round($plan * 100);

    if ($project && $activity && $person && $year) {
        $stmt = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Plan, `Year`)
        VALUES (:project, :activity, :person, :hours, :year)
        ON DUPLICATE KEY UPDATE Plan = :hours");
        $stmt->execute([
            ':project' => $project,
            ':activity' => $activity,
            ':person' => $person,
            ':hours' => $planRaw,
            ':year' => $year
        ]);

        echo "OK";
    } else {
        http_response_code(400);
        echo "Missing input";
    }
}
