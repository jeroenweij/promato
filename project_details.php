<?php
/**
 * Project Details Page
 *
 * Displays detailed information about a project, including activities,
 * Gantt chart, and hours progress.
 */

// Set page-specific CSS files
$pageSpecificCSS = ['gantt-chart.css', 'progress-chart.css'];

// Validate project ID and redirect if not provided
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    header('Location: index.php');
    exit;
}

$projectId = (int)$_GET['project_id']; // Cast to integer for security

// Include required files
require_once 'includes/header.php';
require_once 'includes/db.php';

try {
    // Fetch project details with a single query using JOIN
    $projectStmt = $pdo->prepare("
        SELECT 
            p.*,
            s.Status AS StatusName,
            m.Shortname AS ManagerName
        FROM Projects p
        LEFT JOIN Status s ON p.Status = s.Id
        LEFT JOIN Personel m ON p.Manager = m.Id
        WHERE p.Id = :projectId
    ");
    $projectStmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
    $projectStmt->execute();
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

    // If project is not found, display error and exit
    if (!$project) {
        echo '<div class="alert alert-danger">Project not found.</div>';
        require 'includes/footer.php';
        exit;
    }

    // Fetch all activities for the project
    $activityStmt = $pdo->prepare("
        SELECT * FROM Activities 
        WHERE Project = :projectId 
        ORDER BY `Key` ASC
    ");
    $activityStmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
    $activityStmt->execute();
    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

    // Extract activity IDs
    $activityKeys = array_column($activities, 'Key');

    // Fetch hours in a single query
    $hoursData = [];
    $spentMap = [];
    $planMap = [];

    if (!empty($activityKeys)) {
        $hoursStmt = $pdo->prepare("
            SELECT 
                Activity,
                Person,
                SUM(Plan) AS PlannedHours,
                SUM(Hours) AS LoggedHours
            FROM Hours 
            WHERE Project = :projectId
            GROUP BY Activity, Person
        ");
        $hoursStmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
        $hoursStmt->execute();

        foreach ($hoursStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // Store data for individual person hours
            $hoursData[$row['Activity']][$row['Person']] = [
                'PlannedHours' => $row['PlannedHours'],
                'LoggedHours' => $row['LoggedHours']
            ];

            // Accumulate totals
            if (!isset($spentMap[$row['Activity']])) $spentMap[$row['Activity']] = 0;
            if (!isset($planMap[$row['Activity']])) $planMap[$row['Activity']] = 0;

            // Person = 0 means actual spent hours, otherwise it's planned
            if ($row['Person'] == 0) {
                $spentMap[$row['Activity']] += $row['LoggedHours'];
            } else {
                $planMap[$row['Activity']] += $row['PlannedHours'];
            }
        }
    }

    // Fetch personnel data
    $personelStmt = $pdo->prepare("
        SELECT DISTINCT p.Id, p.Shortname AS Name, p.Fultime, p.Ord
        FROM Hours h 
        JOIN Personel p ON h.Person = p.Id
        WHERE h.Project = :projectId AND h.Person > 0
        ORDER BY p.Ord, p.Shortname
    ");
    $personelStmt->bindParam(':projectId', $projectId, PDO::PARAM_INT);
    $personelStmt->execute();
    $personnel = $personelStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalBudget = array_sum(array_column($activities, 'BudgetHours'));
    $totalSpent = array_sum($spentMap) / 100; // Convert cents to hours
    $totalPlanned = array_sum($planMap) / 100; // Convert cents to hours

    // Prepare data for JavaScript charts
    $jsGanttData = array_map(function($a) {
        return [
            'name' => $a['Name'],
            'startDate' => $a['StartDate'],
            'endDate' => $a['EndDate'],
        ];
    }, $activities);

    $jsProgressData = array_map(function($a) use ($spentMap, $planMap) {
        return [
            'name' => $a['Name'],
            'SpentHours' => ($spentMap[$a['Key']] ?? 0) / 100,
            'PlanHours' => ($planMap[$a['Key']] ?? 0) / 100,
            'BudgetHours' => $a['BudgetHours'],
        ];
    }, $activities);

} catch (PDOException $e) {
    // Log error and display user-friendly message
    error_log('Database error: ' . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while retrieving project data. Please try again later.</div>';
    require 'includes/footer.php';
    exit;
}

// Define a helper function to determine if hours are over budget
function isOverBudget($actual, $planned) {
    return ($actual > 0 && $actual > $planned) ? 'overbudget' : '';
}
?>

    <section id="project-details">
        <div class="container">
            <!-- Project Information -->
            <h1><?= htmlspecialchars($project['Name']) ?></h1>

            <div class="mb-3">
                <strong>Status:</strong>
                <?= htmlspecialchars($project['StatusName']) ?>
            </div>

            <div class="mb-3">
                <strong>Project Manager:</strong>
                <?= htmlspecialchars($project['ManagerName'] ?? '') ?>
            </div>
            <hr>

            <!-- Gantt Chart -->
            <h3>Project Timeline</h3>
            <div id="ganttChart"></div>

            <hr>

            <!-- Hours Progress Chart -->
            <h3>Hours Progress</h3>
            <div id="progressChart"></div>

            <hr>

            <!-- Activities List -->
            <h3>Activities</h3>
            <div class="container">
                <div class="autoheight">
                    <div class="horizontalscrol">
                        <table class="plantable">
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
                                <?php foreach ($personnel as $person): ?>
                                    <th colspan="2" class="name"><?= htmlspecialchars($person['Name']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($activities as $activity):
                                $activityKey = $activity['Key'];
                                $taskCode = $activity['Project'] . '-' . str_pad($activityKey, 3, '0', STR_PAD_LEFT);
                                $plannedHours = ($planMap[$activityKey] ?? 0) / 100;
                                $spentHours = ($spentMap[$activityKey] ?? 0) / 100;
                                $overbudgetClass = isOverBudget($spentHours, $plannedHours);
                                ?>
                                <tr>
                                    <td class="text"><?= $taskCode ?></td>
                                    <td class="text"><?= htmlspecialchars($activity['Name']) ?></td>
                                    <td class="text"><?= htmlspecialchars($activity['WBSO'] ?? '') ?></td>
                                    <td class="text"><?= $activity['StartDate'] ?></td>
                                    <td class="text"><?= $activity['EndDate'] ?></td>
                                    <td class="totals"><?= $activity['BudgetHours'] ?? 0 ?></td>
                                    <td class="totals"><?= $plannedHours ?></td>
                                    <td class="totals <?= $overbudgetClass ?>"><?= $spentHours ?></td>

                                    <?php foreach ($personnel as $person):
                                        $personId = $person['Id'];
                                        $personPlanned = ($hoursData[$activityKey][$personId]['PlannedHours'] ?? 0) / 100;
                                        $personLogged = ($hoursData[$activityKey][$personId]['LoggedHours'] ?? 0) / 100;
                                        $personOverbudget = isOverBudget($personLogged, $personPlanned);
                                        ?>
                                        <td class="editbudget"><?= $personPlanned ?></td>
                                        <td class="budget <?= $personOverbudget ?>"><?= $personLogged ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <br>&nbsp;
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript Data and Scripts -->
    <script>
        // Data for Gantt chart
        const ganttChartData = <?= json_encode(['activities' => $jsGanttData], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

        // Data for Progress chart
        const progressChartData = <?= json_encode([
            'activities' => $jsProgressData,
            'totalBudget' => $totalBudget,
            'totalSpent' => $totalSpent,
            'totalPlan' => $totalPlanned
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <!-- Include JavaScript files -->
    <script src="js/gantt-chart.js"></script>
    <script src="js/progress-chart.js"></script>

<?php require 'includes/footer.php'; ?>