<?php
/**
 * Project Details Page
 *
 * Displays detailed information about a project, including activities,
 * Gantt chart, and hours progress.
 */

// Set page-specific CSS files
$pageSpecificCSS = ['gantt-chart.css', 'progress-chart.css', 'plantable.css'];

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
            p.Manager,
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
        SELECT Activities.*, Wbso.Name AS WBSO, Budgets.Hours AS BudgetHours FROM Activities
        LEFT JOIN Budgets ON Activities.Id = Budgets.Activity
        LEFT JOIN Wbso ON Activities.Wbso = Wbso.Id 
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
        WHERE h.Project = :projectId AND h.Person > 0 AND (h.Plan>0 OR h.Hours>0)
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
                <div class="horizontalscrol scrollable-columns">
                    <table class="plantable">
                        <thead>
                        <tr>
                            <th class="text">Task Code</th>
                            <th class="text">Activity Name</th>
                            <th class="text">WBSO</th>
                            <th class="text">Start Date</th>
                            <th class="text">End Date</th>
                            <th class="text">Budget Hours</th>
                            <th class="text">Planned Hours</th>
                            <th class="text">Logged Hours</th>
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
                                    
                                    if ($userAuthLevel >= 4 || $_SESSION['user_id'] == $project['Manager']) {
                                        echo '<td class="editbudget"><input type="text" name="' . $activity['Project'] . '#' . $activity['Key'] . '#' . $personId . '" value="' . $personPlanned . '" maxlength="4" size="3" class="hiddentext editbudget" onchange="UpdateValue(this)"></td>';
                                    } else {
                                        echo '<td class="budget">' . $personPlanned . '</td>';
                                    }
                                    ?>
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

    <script>
   
   function UpdateValue(input) {
    const [project, activity, person] = input.name.split('#');
    const value = parseFloat(input.value) || 0;

    fetch('update_hours_plan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `project=${project}&activity=${activity}&person=${person}&plan=${value}`
    }).then(res => {
        if (!res.ok) {
            alert(`Failed to save. project=${project}&activity=${activity}&person=${person}&plan=${value}`);
            return;
        }

        const row = input.closest('tr');
        const inputs = row.querySelectorAll('input');

        // Recheck per-person overbudget
        inputs.forEach(inp => {
            const [p, a, personId] = inp.name.split('#');
            const planVal = parseFloat(inp.value) || 0;
            const tdInput = inp.closest('td');
            const tdLogged = tdInput.nextElementSibling;

            if (tdLogged && tdLogged.classList.contains('budget')) {
                const loggedVal = parseFloat(tdLogged.innerText) || 0;
                if (loggedVal > 0 && loggedVal > planVal) {
                    tdLogged.classList.add('overbudget');
                } else {
                    tdLogged.classList.remove('overbudget');
                }
            }
        });

        // Update total planned for this activity (row)
        let totalPlanned = 0;
        inputs.forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) totalPlanned += v;
        });

        const tds = row.querySelectorAll('td');
        const budgetCell = tds[5];
        const plannedCell = tds[6];
        const loggedCell = tds[7];

        // Update Planned Hours column
        plannedCell.innerText = totalPlanned;

        // Check Planned Hours > Budget Hours
        const budget = parseFloat(budgetCell.innerText) || 0;
        if (totalPlanned > budget) {
            plannedCell.classList.add('overbudget');
        } else {
            plannedCell.classList.remove('overbudget');
        }

        // Check Logged Hours > Planned Hours
        const logged = parseFloat(loggedCell.innerText) || 0;
        if (logged > totalPlanned) {
            loggedCell.classList.add('overbudget');
        } else {
            loggedCell.classList.remove('overbudget');
        }
    });
}

    </script>
<?php require 'includes/footer.php'; ?>