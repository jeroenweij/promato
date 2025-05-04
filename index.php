<?php
$pageSpecificCSS = ['progress-chart.css'];

require 'includes/header.php';
require 'includes/db.php';

$userId = $_SESSION['user_id'] ?? null;

// Fetch activities per project where user is planned
$stmt = $pdo->prepare("
SELECT 
    h.Plan AS PlannedHours, 
    h.Hours AS LoggedHours,
    a.Name AS ActivityName, 
p.Id AS ProjectId, 
p.Name AS ProjectName 
FROM Hours h 
JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
JOIN Projects p ON a.Project = p.Id
WHERE h.Person = ? AND p.Id>10;
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by project
$projects = [];
foreach ($rows as $row) {
    $projects[$row['ProjectId']]['name'] = $row['ProjectName'];
    $projects[$row['ProjectId']]['activities'][] = [
        'ActivityName' => $row['ActivityName'],
        'PlannedHours' => $row['PlannedHours'] / 100,
        'LoggedHours' => $row['LoggedHours'] / 100,
    ];
}
?>

<section id="personal-dashboard">
    <div class="container">

        <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
        <p>Here is an overview of your projects and activities.</p>

        <?php if (empty($projects)): ?>
            <div class="alert alert-info">You have no planned activities on any project.</div>
        <?php else: ?>
        <?php foreach ($projects as $projectId => $project): ?>
            <h3><?= htmlspecialchars($project['name']) ?></h3>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Activity</th>
                    <th>Planned Hours</th>
                    <th>Logged Hours</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($project['activities'] as $activity): ?>
                    <tr>
                        <td><?= htmlspecialchars($activity['ActivityName']) ?></td>
                        <td><?= round($activity['PlannedHours'], 2) ?></td>
                        <td><?= round($activity['LoggedHours'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>

        <?php
        // Prepare for chart
        $jsProjects = [];
        foreach ($projects as $project) {
            foreach ($project['activities'] as $activity) {
                $jsProjects[] = [
                    'project' => $project['name'],
                    'activity' => $activity['ActivityName'],
                    'planned' => $activity['PlannedHours'],
                    'logged' => $activity['LoggedHours']
                ];
            }
        }
        ?>
            <script>
                const dashboardChartData = <?= json_encode($jsProjects, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            </script>

            <!-- Optional: add a per-activity chart -->
            <div id="progressChart"></div>
            <script src="js/progress-chart.js"></script>

        <?php endif; ?>

    </div>
</section>

<?php require 'includes/footer.php'; ?>
