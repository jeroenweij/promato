<?php
/**
 * Project Details Page
 *
 * Displays detailed information about a project, including activities,
 * Gantt chart, and hours progress.
 */

// Set page-specific CSS files
$pageSpecificCSS = ['gantt-chart.css', 'progress-chart.css', 'plantable.css', 'projects.css', 'page-project-edit.css'];

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

    // Fetch all activities for the project with budget hours in a single query
    $activityStmt = $pdo->prepare("
        SELECT 
            a.*,
            w.Name AS WBSO, 
            b.Hours AS BudgetHours 
        FROM Activities a
        LEFT JOIN Budgets b ON a.Id = b.Activity AND b.`Year` = :selectedYear
        LEFT JOIN Wbso w ON a.Wbso = w.Id 
        WHERE a.Project = :projectId
        AND YEAR(a.StartDate) <= :selectedYear 
        AND YEAR(a.EndDate) >= :selectedYear
        ORDER BY a.`Key` ASC
    ");
    $activityStmt->execute([
        ':selectedYear' => $selectedYear,
        ':projectId' => $projectId
    ]);
    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

    // Create a lookup map for activities by Key for faster access
    $activityMap = [];
    foreach ($activities as $activity) {
        $activityMap[$activity['Key']] = $activity;
    }

    // Extract activity IDs
    $activityKeys = array_column($activities, 'Key');

    // Fetch hours in a single optimized query that gets both person hours and activity totals
    $hoursData = [];
    $spentMap = [];
    $planMap = [];

    // Fetch personnel data first to minimize queries
    $personelStmt = $pdo->prepare("
        SELECT DISTINCT p.Id, p.Shortname AS Name, p.Fultime, p.Ord
        FROM Hours h 
        JOIN Personel p ON h.Person = p.Id
        WHERE h.`Year` = :selectedYear AND h.Project = :projectId AND h.Person > 0 AND (h.Plan > 0 OR h.Hours > 0)
        ORDER BY p.Ord, p.Shortname
    ");
    $personelStmt->execute([
        ':selectedYear' => $selectedYear,
        ':projectId' => $projectId
    ]);
    $personnel = $personelStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build a map of personnel IDs for faster lookups
    $personnelMap = [];
    foreach ($personnel as $person) {
        $personnelMap[$person['Id']] = $person;
    }

    // Single query to get all hours data
    if (!empty($activityKeys)) {
        $hoursStmt = $pdo->prepare("
            SELECT 
                Activity,
                Person,
                Plan,
                Hours
            FROM Hours 
            WHERE Project = :projectId AND `Year` = :selectedYear AND (Plan > 0 OR Hours > 0)
        ");
        $hoursStmt->execute([
            ':selectedYear' => $selectedYear,
            ':projectId' => $projectId
        ]);

        while ($row = $hoursStmt->fetch(PDO::FETCH_ASSOC)) {
            $activityKey = $row['Activity'];
            $personId = $row['Person'];
            $plan = (int)$row['Plan'];
            $logged = (int)$row['Hours'];
            
            // Store data for individual person hours
            if (!isset($hoursData[$activityKey][$personId])) {
                $hoursData[$activityKey][$personId] = [
                    'PlannedHours' => 0,
                    'LoggedHours' => 0
                ];
            }
            
            $hoursData[$activityKey][$personId]['PlannedHours'] += $plan;
            $hoursData[$activityKey][$personId]['LoggedHours'] += $logged;

            // Track totals per activity
            if (!isset($spentMap[$activityKey])) $spentMap[$activityKey] = 0;
            if (!isset($planMap[$activityKey])) $planMap[$activityKey] = 0;

            // Person = 0 means actual spent hours, otherwise it's planned
            if ($personId == 0) {
                $spentMap[$activityKey] += $logged;
            } else {
                $planMap[$activityKey] += $plan;
            }
        }
    }

    // Calculate totals (only once)
    $totalBudget = array_sum(array_column($activities, 'BudgetHours'));
    $totalSpent = array_sum($spentMap) / 100; // Convert cents to hours
    $totalPlanned = array_sum($planMap) / 100; // Convert cents to hours

    // Prepare data for JavaScript charts - do this once and optimize
    $jsGanttData = [];
    $jsProgressData = [];
    
    $yearStart = "{$selectedYear}-01-01";
    $yearEnd = "{$selectedYear}-12-31";

    foreach ($activities as $a) {
        $activityKey = $a['Key'];

        // Add data to Gantt chart
        $jsGanttData[] = [
            'name' => $a['Name'],
            'startDate' => max($a['StartDate'], $yearStart),
            'endDate' => min($a['EndDate'], $yearEnd),
        ];
        
        // Add data to Progress chart
        $jsProgressData[] = [
            'name' => $a['Name'],
            'SpentHours' => ($spentMap[$activityKey] ?? 0) / 100,
            'PlanHours' => ($planMap[$activityKey] ?? 0) / 100,
            'BudgetHours' => $a['BudgetHours'],
        ];
    }

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

<section class="white">
    <div class="container">
        <!-- Project Header -->
        <div class="project-header">
            <h1>
                <?= $project['Id'] ?> - <?= htmlspecialchars($project['Name']); ?>
                <?php if ($userAuthLevel >= 4): ?>
                    <a href="project_edit.php?project_id=<?= htmlspecialchars($project['Id']) ?>" class="edit-link">
                        ✏️ Edit Project
                    </a>
                <?php endif; ?>
            </h1>
        </div>

        <!-- Status and Manager -->
        <div class="status-manager-row">
            <div class="info-card">
                <strong>Status:</strong>
                <span><?= htmlspecialchars($project['StatusName']) ?></span>
            </div>
            <div class="info-card">
                <strong>Project Manager:</strong>
                <span><?= htmlspecialchars($project['ManagerName'] ?? 'Not assigned') ?></span>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Budget</h4>
                <div class="stat-row">
                    <div class="stat-left">
                        <span class="value"><?= number_format($totalBudget, 0) ?></span>
                        <span class="unit">hours</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <h4>Total Planned</h4>
                <div class="stat-row">
                    <div class="stat-left">
                        <span class="value"><?= number_format($totalPlanned, 0) ?></span>
                        <span class="unit">hours</span>
                    </div>
                    <div class="stat-right">
                        <span class="value percentage"><?= number_format($totalPlanned / max($totalBudget, 1) * 100, 0) ?></span>
                        <span class="unit">%</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <h4>Total Logged</h4>
                <div class="stat-row">
                    <div class="stat-left">
                        <span class="value"><?= number_format($totalSpent, 0) ?></span>
                        <span class="unit">hours</span>
                    </div>
                    <div class="stat-right">
                        <span class="value percentage"><?= number_format($totalSpent / max($totalBudget, 1) * 100, 0) ?></span>
                        <span class="unit">%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gantt Chart Section -->
        <div class="section-card">
            <h3>📅 Project Timeline</h3>
            <div id="ganttChart"></div>
        </div>

        <!-- Hours Progress Chart Section -->
        <div class="section-card">
            <h3>📊 Hours Progress</h3>
            <div id="progressChart"></div>
        </div>

        <!-- Activities Table Section -->
        <div class="section-card">
            <h3>📋 Activities & Hours Planning</h3>
            <div class="table-container">
                <div class="scrollable-columns">
                    <div class="horizontal-scroll-container">
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
                                $overplanClass = isOverBudget($plannedHours, $activity['BudgetHours']);
                                $overbudgetClass = isOverBudget($spentHours, $plannedHours);
                                ?>
                                <tr>
                                    <td class="text"><strong><?= $taskCode ?></strong></td>
                                    <td class="text"><?= htmlspecialchars($activity['Name']) ?></td>
                                    <td class="text"><?= htmlspecialchars($activity['WBSO'] ?? '') ?></td>
                                    <td class="text"><?= $activity['StartDate'] ?></td>
                                    <td class="text"><?= $activity['EndDate'] ?></td>
                                    <td class="totals"><?= $activity['BudgetHours'] ?? 0 ?></td>
                                    <td class="totals <?= $overplanClass ?>"><?= $plannedHours ?></td>
                                    <td class="totals <?= $overbudgetClass ?>"><?= $spentHours ?></td>

                                    <?php foreach ($personnel as $person):
                                        $personId = $person['Id'];
                                        $personPlanned = '';
                                        $personLogged = '&nbsp;';
                                        
                                        if (isset($hoursData[$activityKey][$personId]['PlannedHours']) && $hoursData[$activityKey][$personId]['PlannedHours'] > 0){
                                            $personPlanned = $hoursData[$activityKey][$personId]['PlannedHours'] / 100;
                                        }
                                        if (isset($hoursData[$activityKey][$personId]['LoggedHours']) && $hoursData[$activityKey][$personId]['LoggedHours'] > 0) {
                                            $personLogged = $hoursData[$activityKey][$personId]['LoggedHours'] / 100;
                                        }
                                        $personOverbudget = isOverBudget($personLogged, $personPlanned);
                                        
                                        if ($userAuthLevel >= 4 || $_SESSION['user_id'] == $project['Manager']) {
                                            echo '<td class="editbudget"><input type="text" data-project="' . $activity['Project'] . '" data-activity="' . $activity['Key'] . '" data-person="' . $personId . '" value="' . $personPlanned . '" maxlength="4" size="3" class="hiddentext editbudget"></td>';
                                        } else {
                                            echo '<td class="editbudget">' . $personPlanned . '</td>';
                                        }
                                        ?>
                                        <td class="budget <?= $personOverbudget ?>"><?= $personLogged ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>    
                    </div>
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
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Use event delegation instead of individual handlers
        document.querySelector('.plantable').addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('editbudget')) {
                updateValue(e.target);
            }
        });
    });

    // Debounce function to limit update rate
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
    
    // Optimized update function
    function updateValue(input) {
        const project = input.dataset.project;
        const activity = input.dataset.activity;
        const person = input.dataset.person;
        const value = parseFloat(input.value) || 0;
        
        // Update UI immediately for better UX
        updateRowTotals(input.closest('tr'));

        // Debounced API call
        debouncedSaveValue(project, activity, person, value, input);
    }
    
    // Debounced save function
    const debouncedSaveValue = debounce(function(project, activity, person, value, input) {
        const formData = new FormData();
        formData.append('project', project);
        formData.append('activity', activity);
        formData.append('person', person);
        formData.append('year', <?= $selectedYear ?>);
        formData.append('plan', value);
        
        fetch('update_hours_plan.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                alert(`Failed to save. project=${project}&activity=${activity}&person=${person}&plan=${value}`);
                return;
            }
            // Update UI after successful save
            updateRowTotals(input.closest('tr'));
        })
        .catch(err => {
            console.error('Error saving data:', err);
            alert('Failed to save data. Please try again.');
        });
    }, 300);
    
    // Function to update row totals
    function updateRowTotals(row) {
        const inputs = row.querySelectorAll('input.editbudget');
        
        // Update per-person overbudget
        inputs.forEach(inp => {
            const planVal = parseFloat(inp.value) || 0;
            const tdInput = inp.closest('td');
            const tdLogged = tdInput.nextElementSibling;

            if (tdLogged && tdLogged.classList.contains('budget')) {
                const loggedVal = parseFloat(tdLogged.innerText) || 0;
                tdLogged.classList.toggle('overbudget', loggedVal > 0 && loggedVal > planVal);
            }
        });

        // Calculate total planned for this activity (row)
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
        plannedCell.classList.toggle('overbudget', totalPlanned > budget);

        // Check Logged Hours > Planned Hours
        const logged = parseFloat(loggedCell.innerText) || 0;
        loggedCell.classList.toggle('overbudget', logged > totalPlanned);
    }
</script>

<!-- Include JavaScript files -->
<script src="js/gantt-chart.js"></script>
<script src="js/progress-chart.js"></script>
<?php require 'includes/footer.php'; ?>