<?php
require 'includes/auth.php';
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = $_POST['project'] ?? null;
    $activity = $_POST['activity'] ?? null;
    $team = $_POST['team'] ?? null;
    $year = $_POST['year'] ?? null;
    $plan = floatval($_POST['plan'] ?? 0);
    $planRaw = round($plan * 100);

    if ($project && $activity && $team && $year) {
        $stmt = $pdo->prepare("INSERT INTO TeamHours (Project, Activity, Team, Plan, `Year`)
        VALUES (:project, :activity, :team, :plan, :year)
        ON DUPLICATE KEY UPDATE Plan = :plan");
        try {
            $stmt->execute([
                ':project' => $project,
                ':activity' => $activity,
                ':team' => $team,
                ':plan' => $planRaw,
                ':year' => $year
            ]);

            echo "OK";
        } catch (PDOException $e) {
            http_response_code(500);
            echo "Database error: " . $e->getMessage();
        }
    } else {
        http_response_code(400);
        echo "Missing input";
    }
}
