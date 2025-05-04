<?php
require 'includes/auth.php';
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = $_POST['project'] ?? null;
    $activity = $_POST['activity'] ?? null;
    $person = $_POST['person'] ?? null;
    $plan = floatval($_POST['plan'] ?? 0);
    $planRaw = round($plan * 100);

    if ($project && $activity && $person) {
        // Check if entry exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Hours WHERE Project = ? AND Activity = ? AND Person = ?");
        $stmt->execute([$project, $activity, $person]);
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            // Update existing
            $update = $pdo->prepare("UPDATE Hours SET Plan = ? WHERE Project = ? AND Activity = ? AND Person = ?");
            $update->execute([$planRaw, $project, $activity, $person]);
        } else {
            // Insert new
            $insert = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Plan) VALUES (?, ?, ?, ?)");
            $insert->execute([$project, $activity, $person, $planRaw]);
        }

        echo "OK";
    } else {
        http_response_code(400);
        echo "Missing input";
    }
}
