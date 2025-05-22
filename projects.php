<?php
$pageSpecificCSS = ['kanban.css', 'projects.css'];

require 'includes/header.php';
require 'includes/db.php';

// Zero pad helper
function zeroPad($num, $places) {
    return str_pad($num, $places, "0", STR_PAD_LEFT);
}

// Single comprehensive query to get all data we need
$sql = "SELECT 
    p.Id as ProjectId, p.Name as ProjectName, p.Status as ProjectStatus, s.Status as StatusName,
    pe.Shortname as ManagerName, pe.Name as ManagerFullName,
    a.Id as ActivityId, a.Key as ActivityKey, a.Name as ActivityName, a.StartDate, a.EndDate, a.IsTask,
    b.Hours as BudgetHours,
    w.Name as WBSO,
    COALESCE(h.TotalHours, 0) as LoggedHours,
    COALESCE(h.TaskCount, 0) as TaskCount,
    COALESCE(h.CompletedCount, 0) as CompletedCount
FROM Projects p
LEFT JOIN Status s ON p.Status = s.Id
LEFT JOIN Personel pe ON p.Manager = pe.Id
LEFT JOIN Activities a ON p.Id = a.Project 
    AND YEAR(a.StartDate) <= :selectedYear 
    AND YEAR(a.EndDate) >= :selectedYear
    AND a.Visible = 1
LEFT JOIN Budgets b ON a.Id = b.Activity AND b.Year = :selectedYear
LEFT JOIN Wbso w ON a.Wbso = w.Id
LEFT JOIN (
    SELECT Project, Activity, 
           SUM(Hours) as TotalHours,
           COUNT(*) as TaskCount,
           SUM(CASE WHEN Status = 4 THEN 1 ELSE 0 END) as CompletedCount
    FROM Hours 
    WHERE Year = :selectedYear AND Person > 0
    GROUP BY Project, Activity
) h ON a.Project = h.Project AND a.Key = h.Activity
WHERE a.Id IS NOT NULL
ORDER BY p.Status, p.Id, a.Key";

$stmt = $pdo->prepare($sql);
$stmt->execute([':selectedYear' => $selectedYear]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process data into organized structures
$projects = [];
$projectActivities = [];
$totalBudget = 0;
$totalLoggedHours = 0;
$totalTasks = 0;
$completedTasks = 0;

foreach ($data as $row) {
    $projectId = $row['ProjectId'];
    
    // Build project info (only once per project)
    if (!isset($projects[$projectId])) {
        $projects[$projectId] = [
            'Id' => $projectId,
            'Name' => $row['ProjectName'],
            'Status' => $row['ProjectStatus'],
            'StatusName' => $row['StatusName'],
            'ManagerName' => $row['ManagerName'],
            'ManagerFullName' => $row['ManagerFullName'],
            'BudgetHours' => 0,
            'LoggedHours' => 0,
            'ActivityCount' => 0
        ];
    }
    
    // Add activity data
    if ($row['ActivityId']) {
        $activity = [
            'ActivityId' => $row['ActivityId'],
            'Project' => $projectId,
            'Key' => $row['ActivityKey'],
            'ActivityName' => $row['ActivityName'],
            'StartDate' => $row['StartDate'],
            'EndDate' => $row['EndDate'],
            'IsTask' => $row['IsTask'],
            'BudgetHours' => $row['BudgetHours'] ?? 0,
            'WBSO' => $row['WBSO'],
            'LoggedHours' => $row['LoggedHours'] / 100, // Convert from stored format
            'TaskCount' => $row['TaskCount'],
            'CompletedCount' => $row['CompletedCount']
        ];
        
        $projectActivities[$projectId][] = $activity;
        
        // Update project totals
        $projects[$projectId]['BudgetHours'] += $activity['BudgetHours'];
        $projects[$projectId]['LoggedHours'] += $activity['LoggedHours'];
        $projects[$projectId]['ActivityCount']++;
        
        // Update global totals
        $totalBudget += $activity['BudgetHours'];
        $totalLoggedHours += $activity['LoggedHours'];
        $totalTasks += $activity['TaskCount'];
        $completedTasks += $activity['CompletedCount'];
    }
}

// Count active projects
$activeProjectCount = 0;
foreach ($projects as $project) {
    if ($project['Status'] == 3) $activeProjectCount++;
}

// Status colors
$statusColors = [
    1 => '#6c757d', // Lead - Gray
    2 => '#ffc107', // Quote - Yellow
    3 => '#28a745', // Active - Green
    4 => '#dc3545'  // Closed - Red
];

// Status badges
function getStatusBadge($status, $statusName, $statusColors) {
    $color = $statusColors[$status] ?? '#6c757d';
    return "<span class='badge' style='background-color: $color; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;'>$statusName</span>";
}

// Progress bar helper
function getProgressBar($current, $total) {
    if ($total == 0) return '<div class="progress-bar-empty">No budget set</div>';
    $percentage = ($current / $total) * 100;
    $drawPercentage = min(100, $percentage);
    $overshoot = $percentage > 100 ? 'overshoot' : '';
    return "<div class='progress'>
                <div class='progress-bar $overshoot' style='width: {$drawPercentage}%;'>
                " . $current . " / " . $total . " (" . round($percentage) . "%)
                </div>
            </div>";
}
?>

<section>

<div class="container">
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($projects); ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $activeProjectCount; ?></div>
                <div class="stat-label">Active Projects</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalLoggedHours, 0, ',', '.'); ?></div>
                <div class="stat-label">Hours Logged</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalBudget, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Budget Hours</div>
            </div>
        </div>
    </div>

    <?php
    // Group projects by status
    $projectsByStatus = [];
    foreach ($projects as $project) {
        $projectsByStatus[$project['Status']][] = $project;
    }

    // Display projects grouped by status
    foreach ([1, 2, 3, 4] as $statusId) {
        if (!isset($projectsByStatus[$statusId])) continue;
        
        $statusName = '';
        switch($statusId) {
            case 1: $statusName = 'Lead Projects'; break;
            case 2: $statusName = 'Quote Projects'; break;
            case 3: $statusName = 'Active Projects'; break;
            case 4: $statusName = 'Closed Projects'; break;
        }
        
        echo "<h2 class='section-title'>$statusName (" . count($projectsByStatus[$statusId]) . ")</h2>";
        
        foreach ($projectsByStatus[$statusId] as $project) {
            $projectId = $project['Id'];
            ?>
            
            <div class="project-card">
                <div class="project-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="project-title">
                                <a href="project_details.php?project_id=<?php echo htmlspecialchars($projectId); ?>">
                                    <?php echo htmlspecialchars($projectId . ' - ' . $project['Name']); ?>
                                </a>
                            </h3>
                            <div class="project-meta">
                                Manager: <strong><?php echo htmlspecialchars($project['ManagerName'] ?? 'Unassigned'); ?></strong> | 
                                Activities: <strong><?php echo $project['ActivityCount']; ?></strong> |
                                Budget: <strong><?php echo number_format($project['BudgetHours'], 0, ",", "."); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4 text-right">
                            <?php echo getStatusBadge($project['Status'], $project['StatusName'], $statusColors); ?>
                            <?php if ($project['BudgetHours'] > 0): ?>
                                <div class="mt-2">
                                    <?php echo getProgressBar($project['LoggedHours'], $project['BudgetHours']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($projectActivities[$projectId]) && !empty($projectActivities[$projectId])): ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Task Code</th>
                            <th>Activity Name</th>
                            <th>WBSO</th>
                            <th>Period</th>
                            <th>Hours Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projectActivities[$projectId] as $activity): 
                            $taskCode = $activity['Project'] . '-' . zeroPad($activity['Key'], 3);
                        ?>
                        <tr>
                            <td><span class="task-code"><?php echo htmlspecialchars($taskCode); ?></span></td>
                            <td><?php echo htmlspecialchars($activity['ActivityName']); ?></td>
                            <td>
                                <?php if ($activity['WBSO']): ?>
                                    <span class="wbso-tag"><?php echo htmlspecialchars($activity['WBSO']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php echo date('M j', strtotime($activity['StartDate'])) . ' - ' . date('M j, Y', strtotime($activity['EndDate'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo getProgressBar($activity['LoggedHours'], $activity['BudgetHours']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert-warning">
                    <strong>No activities found</strong> for this project in <?php echo $selectedYear; ?>.
                </div>
                <?php endif; ?>
            </div>
            
            <?php
        }
    }
    ?>
</div>
</section>
<?php require 'includes/footer.php'; ?>