<?php
require 'includes/header.php';
require 'includes/db.php';

// Zero pad helper
function zeroPad($num, $places) {
    return str_pad($num, $places, "0", STR_PAD_LEFT);
}

// Get activities with project data
$sql = "SELECT Activities.Id AS ActivityId, Activities.Project, Activities.Key, Activities.Name AS ActivityName,
               Activities.BudgetHours, Activities.WBSO, Activities.StartDate, Activities.EndDate,
               Projects.Name AS ProjectName, Status.Status
        FROM Activities
        LEFT JOIN Projects ON Activities.Project = Projects.Id
        LEFT JOIN Status ON Projects.Status = Status.Id
        WHERE Projects.Status = 3
        ORDER BY Activities.Project, Activities.Key";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by project
$projects = [];
foreach ($rows as $row) {
    $projects[$row['Project']]['name'] = $row['ProjectName'];
    $projects[$row['Project']]['status'] = $row['Status'];
    $projects[$row['Project']]['activities'][] = $row;
}

// Render
echo '<section id="pricing"><div class="container">';

foreach ($projects as $projectId => $project) {
    // Make the project name clickable
    echo '<div class="row">';
    echo '<div class="col"><strong><a href="project_details.php?project_id=' . htmlspecialchars($projectId) . '">' . htmlspecialchars($projectId) . ' - ' . htmlspecialchars($project['name']) . '</a></strong> (' . htmlspecialchars($project['status']) . ')</div>';
    echo '</div>';

    // Activity header row
    echo '<div class="row" style="padding-left: 2rem; font-weight: bold;">';
    echo '<div class="col">TaskCode</div>';
    echo '<div class="col">Activity Name</div>';
    echo '<div class="col">WBSO label</div>';
    echo '<div class="col">Start Date</div>';
    echo '<div class="col">End Date</div>';
    echo '<div class="col">Hours logged </div>';
    echo '<div class="col">Hours budgeted</div>';
    echo '</div>';

    foreach ($project['activities'] as $activity) {
        $taskCode = $activity['Project'] . '-' . zeroPad($activity['Key'], 3);
        $wbso = $activity['WBSO'] ?? '';

        // Get total logged hours
        $hourStmt = $pdo->prepare("SELECT SUM(Hours) as TotalHours FROM Hours WHERE Project = ? AND Activity = ?");
        $hourStmt->execute([$activity['Project'], $activity['Key']]);
        $hourData = $hourStmt->fetch(PDO::FETCH_ASSOC);
        $logged = $hourData['TotalHours'] ? $hourData['TotalHours'] / 100 : 0;
        $budget = $activity['BudgetHours'] ?? 0;

        echo '<div class="row" style="padding-left: 2rem;">';
        echo '<div class="col">' . htmlspecialchars($taskCode) . '</div>';
        echo '<div class="col">' . htmlspecialchars($activity['ActivityName']) . '</div>';
        echo '<div class="col">' . htmlspecialchars($wbso) . '</div>';
        echo '<div class="col">' . htmlspecialchars($activity['StartDate']) . '</div>';
        echo '<div class="col">' . htmlspecialchars($activity['EndDate']) . '</div>';
        echo '<div class="col">' . htmlspecialchars($logged) . '</div>';
        echo '<div class="col">' . htmlspecialchars($budget) . '</div>';
        echo '</div>';
    }

    echo '<hr>';
}

echo '</div></section>';

require 'includes/footer.php';
?>

