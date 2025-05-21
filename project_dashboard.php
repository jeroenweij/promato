<?php
$pageSpecificCSS = ['kanban.css', 'management-dashboard.css'];

require 'includes/header.php';
require 'includes/db.php';

// Get current date for deadline calculations
$currentDate = date('Y-m-d');
$thirtyDaysAhead = date('Y-m-d', strtotime('+30 days'));

// 1. Fetch upcoming activity deadlines (activities due in the next 30 days)
$upcomingDeadlinesStmt = $pdo->prepare("
    SELECT 
        a.Name AS ActivityName,
        a.Key AS ActivityId,
        a.EndDate,
        p.Id AS ProjectId,
        p.Name AS ProjectName,
        SUM(h.Plan) AS TotalPlannedHours,
        SUM(h.Hours) AS TotalLoggedHours
    FROM Activities a
    JOIN Projects p ON a.Project = p.Id
    LEFT JOIN Hours h ON h.Activity = a.Key AND h.Project = a.Project AND h.Person > 0 AND h.`Year` = :selectedYear
    WHERE a.EndDate BETWEEN :currentDate AND :thirtyDaysAhead
    AND a.IsTask = 1
    GROUP BY a.Key, a.Project
    ORDER BY a.EndDate ASC
");

$upcomingDeadlinesStmt->execute([
    ':selectedYear' => $selectedYear,
    ':currentDate' => $currentDate,
    ':thirtyDaysAhead' => $thirtyDaysAhead
]);

$upcomingDeadlines = $upcomingDeadlinesStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch overbudget activities (where logged hours > budgeted hours)
$overbudgetActivitiesStmt = $pdo->prepare("
    SELECT 
        a.Name AS ActivityName,
        a.Key AS ActivityId,
        p.Id AS ProjectId,
        p.Name AS ProjectName,
        b.Hours * 100 AS BudgetedHours,
        SUM(h.Hours) AS TotalLoggedHours,
        (SUM(h.Hours) - (b.Hours * 100)) AS Overage
    FROM Activities a
    JOIN Projects p ON a.Project = p.Id
    JOIN Budgets b ON b.Activity = a.Id AND b.Year = :selectedYear
    JOIN Hours h ON h.Activity = a.Key AND h.Project = a.Project AND h.Person > 0 AND h.Year = :selectedYear
    WHERE a.IsTask = 1
    GROUP BY a.Key, a.Project, b.Hours
    HAVING SUM(h.Hours) > (b.Hours * 100)
    ORDER BY Overage DESC
");

$overbudgetActivitiesStmt->execute([
    ':selectedYear' => $selectedYear
]);

$overbudgetActivities = $overbudgetActivitiesStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch stalled activities (activities with zero hours logged and started more than 30 days ago)
$stalledActivitiesStmt = $pdo->prepare("
    SELECT 
        a.Name AS ActivityName,
        a.Key AS ActivityId,
        p.Id AS ProjectId,
        p.Name AS ProjectName,
        SUM(h.Plan) AS TotalPlannedHours,
        SUM(h.Hours) AS TotalLoggedHours,
        a.StartDate AS StartDate
    FROM Activities a
    JOIN Projects p ON a.Project = p.Id
    JOIN Hours h ON h.Activity = a.Key AND h.Project = a.Project AND h.Person > 0 AND h.`Year` = :selectedYear
    WHERE h.Status < 4 
    AND a.IsTask = 1
    AND h.Hours = 0
    AND a.StartDate IS NOT NULL
    AND DATEDIFF(CURRENT_DATE, a.StartDate) > 30
    GROUP BY a.Key, a.Project
    ORDER BY a.StartDate ASC
");

$stalledActivitiesStmt->execute([
    ':selectedYear' => $selectedYear
]);

$stalledActivities = $stalledActivitiesStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Project status summary
$projectStatusStmt = $pdo->query("
    SELECT 
        COUNT(*) AS TotalProjects,
        SUM(CASE WHEN p.Status = 4 THEN 1 ELSE 0 END) AS ClosedProjects,
        SUM(CASE WHEN p.Status = 3 THEN 1 ELSE 0 END) AS ActiveProjects,
        SUM(CASE WHEN p.Status = 2 THEN 1 ELSE 0 END) AS NotStartedProjects
    FROM Projects p
    LEFT JOIN Activities a ON p.Id = a.Project
    WHERE p.Status > 1
    AND (YEAR(a.StartDate) <= $selectedYear OR a.StartDate IS NULL)
    AND (YEAR(a.EndDate) >= $selectedYear OR a.EndDate IS NULL)
    GROUP BY p.Id
");

$projectStatus = $projectStatusStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals from the results
$totalProjects = count($projectStatus);
$closedProjects = 0;
$activeProjects = 0;
$notStartedProjects = 0;

foreach ($projectStatus as $project) {
    $closedProjects += $project['ClosedProjects'];
    $activeProjects += $project['ActiveProjects'];
    $notStartedProjects += $project['NotStartedProjects'];
}

// Create a summary array
$projectStatusSummary = [
    'TotalProjects' => $totalProjects,
    'ClosedProjects' => $closedProjects,
    'ActiveProjects' => $activeProjects,
    'NotStartedProjects' => $notStartedProjects
];

// 5. Resource allocation (total hours allocated vs available capacity)
$resourceAllocationStmt = $pdo->prepare("
    SELECT 
        u.Id AS PersonId,
        u.Name AS PersonName,
        u.Department,
        SUM(h.Plan)AS AvailableHours,
        SUM(h.Hours) AS AllocatedHours
    FROM Personel u
    LEFT JOIN Hours h ON h.Person = u.Id AND h.Project > 0 AND h.`Year` = :selectedYear
    WHERE u.Plan = 1 AND u.Type > 1
    GROUP BY u.Id
    ORDER BY u.Department, u.Name
");

$resourceAllocationStmt->execute([
    ':selectedYear' => $selectedYear
]);

$resourceAllocation = $resourceAllocationStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to format hours
function formatHours($hours) {
    return $hours / 100;
}

// Helper function to calculate completion percentage
function calculateCompletion($logged, $planned) {
    if ($planned <= 0) return 0;
    return round(($logged / $planned) * 100);
}

?>

<section id="management-dashboard">
    <div class="container">
        <h1 class="mb-4">Project Dashboard</h1>
        
        <!-- Top Summary Cards -->
        <div class="row mb-4">
           <!-- Projects Overview -->
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Projects</h5>
                        <div class="summary-stat"><?= $projectStatusSummary['TotalProjects'] ?? 0 ?></div>
                        <div class="progress mb-2">
                            <?php 
                            $completedPercent = ($projectStatusSummary['TotalProjects'] > 0) ? 
                                ($projectStatusSummary['ClosedProjects'] / $projectStatusSummary['TotalProjects']) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $completedPercent ?>%"></div>
                        </div>
                        <div class="status-breakdown">
                            <span class="badge bg-success"><?= $projectStatusSummary['ClosedProjects'] ?> Closed</span>
                            <span class="badge bg-primary"><?= $projectStatusSummary['ActiveProjects'] ?> Active</span>
                            <span class="badge bg-secondary"><?= $projectStatusSummary['NotStartedProjects'] ?> Not Started</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Overbudget Alert -->
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Budget Issues</h5>
                        <div class="summary-stat"><?= count($overbudgetActivities) ?></div>
                        <p class="text-danger">Activities Over Budget</p>
                        <?php if(count($overbudgetActivities) > 0): ?>
                            <div class="alert alert-danger mb-0">
                                <small>Highest Overage: <?= formatHours($overbudgetActivities[0]['Overage']) ?> hours</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Deadlines -->
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Upcoming Deadlines</h5>
                        <div class="summary-stat"><?= count($upcomingDeadlines) ?></div>
                        <p>Due in next 30 days</p>
                        <?php if(count($upcomingDeadlines) > 0): ?>
                            <div class="alert alert-warning mb-0">
                                <small>Next deadline: <?= date('M d', strtotime($upcomingDeadlines[0]['EndDate'])) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stalled Activities -->
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Stalled Activities</h5>
                        <div class="summary-stat"><?= count($stalledActivities) ?></div>
                        <p>No progress in 30+ days</p>
                        <?php if(count($stalledActivities) > 0): ?>
                            <div class="alert alert-info mb-0">
                                <small>Longest idle: <?= date('M d', strtotime($stalledActivities[0]['StartDate'])) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Upcoming Deadlines -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Upcoming Deadlines</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($upcomingDeadlines) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Due Date</th>
                                            <th>Project/Activity</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($upcomingDeadlines as $activity): 
                                            $planned = $activity['TotalPlannedHours'];
                                            $logged = $activity['TotalLoggedHours'];
                                            $percent = calculateCompletion($logged, $planned);
                                            $daysUntilDue = floor((strtotime($activity['EndDate']) - time()) / 86400);
                                            $statusClass = ($percent < 50 && $daysUntilDue < 7) ? 'table-danger' : '';
                                        ?>
                                        <tr class="<?= $statusClass ?>">
                                            <td>
                                                <?= date('M d, Y', strtotime($activity['EndDate'])) ?>
                                                <small class="d-block text-muted">
                                                    <?= $daysUntilDue ?> days left
                                                </small>
                                            </td>
                                            <td>
                                                <strong><a href="/project_details.php?project_id=<?= $activity['ProjectId'] ?>" class="hidden-link"><?= htmlspecialchars($activity['ProjectName']) ?></a></strong>
                                                <small class="d-block text-muted"><?= htmlspecialchars($activity['ActivityName']) ?></small>
                                            </td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar <?= $percent < 50 ? 'bg-warning' : ($percent <= 100 ?'bg-success':'bg-danger') ?>" 
                                                        role="progressbar" 
                                                        style="width: <?= min(100, $percent) ?>%">
                                                        <?= $percent ?>%
                                                    </div>
                                                </div>
                                                <small><?= formatHours($logged) ?> / <?= formatHours($planned) ?> hrs</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No upcoming deadlines in the next 30 days.</div>
                        <?php endif; ?>
                    </div>
                </div>
                        </div>

            <!-- Overbudget Activities -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Over Budget Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($overbudgetActivities) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Project/Activity</th>
                                            <th>Hours</th>
                                            <th>Overage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($overbudgetActivities as $activity): 
                                            $budgeted = $activity['BudgetedHours'] ?? 0;
                                            $logged = $activity['TotalLoggedHours'];
                                            $overage = $activity['Overage'];
                                            // Fixed division by zero
                                            $overagePercent = ($budgeted > 0) ? round(($overage / $budgeted) * 100) : 100;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><a href="/project_details.php?project_id=<?= $activity['ProjectId'] ?>" class="hidden-link"><?= htmlspecialchars($activity['ProjectName']) ?></a></strong>
                                                <small class="d-block text-muted"><?= htmlspecialchars($activity['ActivityName']) ?></small>
                                            </td>
                                            <td>
                                                <?= formatHours($logged) ?> / <?= formatHours($budgeted) ?> hrs
                                            </td>
                                            <td class="text-danger">
                                                +<?= formatHours($overage) ?> hrs
                                                <small class="d-block">(+<?= $overagePercent ?>%)</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">No activities are currently over budget.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Stalled Activities -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Stalled Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($stalledActivities) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Project/Activity</th>
                                            <th>Progress</th>
                                            <th>Last Update</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($stalledActivities as $activity): 
                                            $planned = $activity['TotalPlannedHours'];
                                            $logged = $activity['TotalLoggedHours'];
                                            $percent = calculateCompletion($logged, $planned);
                                            $daysSinceUpdate = floor((time() - strtotime($activity['StartDate'])) / 86400);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><a href="/project_details.php?project_id=<?= $activity['ProjectId'] ?>" class="hidden-link"><?= htmlspecialchars($activity['ProjectName']) ?></a></strong>
                                                <small class="d-block text-muted"><?= htmlspecialchars($activity['ActivityName']) ?></small>
                                            </td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-secondary" 
                                                        role="progressbar" 
                                                        style="width: <?= min(100, $percent) ?>%">
                                                        <?= $percent ?>%
                                                    </div>
                                                </div>
                                                <small><?= formatHours($logged) ?> / <?= formatHours($planned) ?> hrs</small>
                                            </td>
                                            <td>
                                                <?= date('d M, Y', strtotime($activity['StartDate'])) ?>
                                                <small class="d-block text-muted">
                                                    <?= $daysSinceUpdate ?> days ago
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">No stalled activities detected.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Resource Allocation -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Resource Allocation</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($resourceAllocation) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Personnel</th>
                                            <th>Department</th>
                                            <th>Allocation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($resourceAllocation as $resource): 
                                            $available = $resource['AvailableHours'];
                                            $allocated = $resource['AllocatedHours'];
                                            $allocPercent = ($available > 0) ? round(($allocated / $available) * 100) : 0;
                                            $overallocated = ($allocPercent > 90) ? 'table-warning' : '';
                                            $overallocated = ($allocPercent > 100) ? 'table-danger' : $overallocated;
                                        ?>
                                        <tr class="<?= $overallocated ?>">
                                            <td><?= htmlspecialchars($resource['PersonName']) ?></td>
                                            <td><?= htmlspecialchars($resource['Department']) ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar <?= $allocPercent > 90 ? ($allocPercent > 100 ? 'bg-danger' : 'bg-warning') : 'bg-success' ?>" 
                                                        role="progressbar" 
                                                        style="width: <?= min(100, $allocPercent) ?>%">
                                                        <?= $allocPercent ?>%
                                                    </div>
                                                </div>
                                                <small><?= formatHours($allocated) ?> / <?= formatHours($available) ?> hrs</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No resource allocation data available.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* Management Dashboard Specific Styles */
    .summary-card {
        height: 100%;
        border-left: 4px solid #007bff;
    }
    
    .summary-card:nth-child(2) {
        border-left-color: #dc3545;
    }
    
    .summary-card:nth-child(3) {
        border-left-color: #ffc107;
    }
    
    .summary-card:nth-child(4) {
        border-left-color: #17a2b8;
    }
    
    .summary-stat {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    
    .status-breakdown {
        display: flex;
        justify-content: space-between;
    }
    
    .kanban-progress {
        margin-top: 10px;
        height: 20px;
        background-color: #f0f0f0;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .kanban-progress .progress-bar {
        height: 100%;
        background-color: #007bff;
        color: white;
        text-align: center;
        line-height: 20px;
        font-size: 0.8rem;
    }
    
    .kanban-progress .progress-bar.overshoot {
        background-color: #dc3545;
    }
</style>

<?php require 'includes/footer.php'; ?>
