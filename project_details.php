<?php
$pageSpecificCSS = ['gantt-chart.css', 'progress-chart.css'];

require 'includes/header.php';
require 'includes/db.php';

// Check if the project ID is provided in the URL
if (isset($_GET['project_id'])) {
    $projectId = $_GET['project_id'];

    // Fetch the project details along with the status and manager
    $projectStmt = $pdo->prepare("
        SELECT 
            Projects.*, 
            Status.Status AS Status, 
            Personel.Shortname AS Manager
        FROM Projects
        LEFT JOIN Status ON Projects.Status = Status.Id
        LEFT JOIN Personel ON Projects.Manager = Personel.Id
        WHERE Projects.Id = ?
    ");
    $projectStmt->execute([$projectId]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

    // If project is not found
    if (!$project) {
        echo 'Project not found.';
        require 'includes/footer.php';
        exit;
    }

    // Fetch the activities for the project
    $activityStmt = $pdo->prepare("SELECT * FROM Activities WHERE Project = ?");
    $activityStmt->execute([$projectId]);
    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

}
?>

<section id="project-details">
    <div class="container">

        <!-- Project Information -->
        <h1><?php echo htmlspecialchars($project['Name']); ?></h1>

        <div class="mb-3">
            <strong>Status:</strong>
            <?php
            echo htmlspecialchars($project['Status']);
            ?>
        </div>

        <div class="mb-3">
            <strong>Project Manager:</strong>
            <?php
            echo htmlspecialchars($project['Manager']);
            ?>
        </div>
        <hr>

        <h3>Project Timeline</h3>
        <div id="ganttChart"></div>

        <hr>
        <h3>Hours Progress</h3>
        <div id="progressChart"></div>

        <?php
        // Map each row to only the properties the Gantt needs:
        $jsActivities = array_map(function($a) {
            return [
                'name'      => $a['Name'],
                'startDate' => $a['StartDate'],
                'endDate'   => $a['EndDate'],
            ];
        }, $activities);
        ?>

        <script>
            // Create a global variable with the data that the external script will use
            const ganttChartData = {
                activities: <?php echo json_encode($jsActivities, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>
            };
        </script>

        <!-- Include JavaScript file -->
        <script src="js/gantt-chart.js"></script>

        <?php
        // Fetch hours spent per activity
        $activityIds = array_column($activities, 'Id');
        $spentMap = [];
        $planMap = [];

        if (count($activityIds) > 0) {
            $hoursStmt = $pdo->prepare("
                SELECT Activity,
                SUM(CASE WHEN Person > 0 THEN Plan ELSE 0 END) AS PlanHours,
                SUM(CASE WHEN Person = 0 THEN Hours ELSE 0 END) AS SpentHours
                FROM Hours WHERE Project = ? GROUP BY Activity
            ");
            
            $hoursStmt->execute([$projectId]);

            foreach ($hoursStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $spentMap[$row['Activity']] = $row['SpentHours'];
                $planMap[$row['Activity']] = $row['PlanHours'];
            }
        }

        $totalBudget = 0;
        foreach ($activities as &$a) {
            $totalBudget += $a['BudgetHours'];
        }
        unset($a); // break reference

        // Map each row to only the properties the hours bars needs:
        $jsActivities = array_map(function($a) use ($spentMap, $planMap) {
            return [
                'name'         => $a['Name'],
                'SpentHours'   => ($spentMap[$a['Key']] ?? 0) / 100,
                'PlanHours'   => ($planMap[$a['Key']] ?? 0) / 100,
                'BudgetHours'  => $a['BudgetHours'],
            ];
        }, $activities);
        ?>

    <script>
        // Create a global variable with the data that the external script will use
        const progressChartData = {
            activities: <?php echo json_encode($jsActivities, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>,
            totalBudget: <?php echo $totalBudget; ?>,
            totalSpent: <?php echo array_sum($spentMap)/100; ?>,
            totalPlan: <?php echo array_sum($planMap)/100; ?>
        };
    </script>

    <!-- Include JavaScript file -->
    <script src="js/progress-chart.js"></script>

        <hr>

        <!-- Activities List -->
        <h3>Activities</h3>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Task Code</th>
                <th>Activity Name</th>
                <th>WBSO Label</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Budget Hours</th>
                <th>Planned Hours</th>
                <th>Logged Hours</th>
            </tr>
            </thead>
            <tbody>
            <?php 
            foreach ($activities as $activity):
            
                ?>
                <tr>
                    <td><?php echo $activity['Project'] . '-' . str_pad($activity['Key'], 3, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($activity['Name']); ?></td>
                    <td><?php echo htmlspecialchars($activity['WBSO'] ?? ''); ?></td>
                    <td><?php echo $activity['StartDate']; ?></td>
                    <td><?php echo $activity['EndDate']; ?></td>
                    <td><?php echo $activity['BudgetHours'] ?? 0; ?></td>
                    <td><?php echo $planMap[$activity['Key']] ?? 0 /100; ?></td>
                    <td><?php echo $spentMap[$activity['Key']]?? 0 /100; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</section>

<?php require 'includes/footer.php'; ?>
