<?php
require 'includes/header.php';
require 'includes/db.php';

$projectId = isset($_GET['project']) ? (int)$_GET['project'] : 0;

if (!$projectId) {
    header("Location: finance.php");
    exit;
}

// Get project basic info
$stmt = $pdo->prepare("
    SELECT p.*, s.Status as StatusName, pm.Name as ManagerName, pm.Email as ManagerEmail
    FROM Projects p
    LEFT JOIN Status s ON p.Status = s.Id
    LEFT JOIN Personel pm ON p.Manager = pm.Id
    WHERE p.Id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    echo "Project not found.";
    exit;
}

// Get activities with budgets and actual hours
$stmt = $pdo->prepare("
    SELECT 
        a.Key,
        a.Name as ActivityName,
        a.StartDate,
        a.EndDate,
        a.Visible,
        COALESCE(b.Budget, 0) as Budget,
        COALESCE(b.OopSpend, 0) as OOPSpend,
        COALESCE(b.Hours, 0) as BudgetedHours,
        COALESCE(b.Rate, 0) as Rate,
        COALESCE((
            SELECT SUM(h.Hours) / 100
            FROM Hours h
            WHERE h.Project = a.Project AND h.Activity = a.Key AND h.Year = :year AND h.Person > 0
        ), 0) as ActualHours,
        COALESCE((
            SELECT SUM(h.Plan) / 100
            FROM Hours h
            WHERE h.Project = a.Project AND h.Activity = a.Key AND h.Year = :year AND h.Person > 0
        ), 0) as PlannedHours
    FROM Activities a
    LEFT JOIN Budgets b ON b.Activity = a.Id AND b.Year = :year
    WHERE a.Project = :project
    ORDER BY a.Key, a.Name
");
$stmt->execute([':project' => $projectId, ':year' => $selectedYear]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get personnel hours breakdown with their rates from budgets
$stmt = $pdo->prepare("
    SELECT 
        p.Id as PersonId,
        p.Name as PersonName,
        p.Shortname,
        d.Name as Team,
        h.Activity,
        SUM(h.Hours) / 100 as ActualHours,
        SUM(h.Plan) / 100 as PlannedHours
    FROM Hours h
    INNER JOIN Personel p ON h.Person = p.Id
    LEFT JOIN Teams d ON p.Team = d.Id
    WHERE h.Project = :project AND h.Year = :year AND h.Person > 0
    GROUP BY p.Id, p.Name, p.Shortname, d.Name, h.Activity
    HAVING ActualHours > 0 OR PlannedHours > 0
");
$stmt->execute([':project' => $projectId, ':year' => $selectedYear]);
$personnelActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build personnel array with calculated rates
$personnelMap = [];
foreach ($personnelActivities as $pa) {
    $personId = $pa['PersonId'];
    
    // Get the rate for this activity
    $activityRate = 0;
    foreach ($activities as $act) {
        if ($act['Key'] == $pa['Activity']) {
            $activityRate = $act['Rate'];
            break;
        }
    }
    print("Activity rate $activityRate");
    
    if (!isset($personnelMap[$personId])) {
        $personnelMap[$personId] = [
            'PersonName' => $pa['PersonName'],
            'Shortname' => $pa['Shortname'],
            'Team' => $pa['Team'],
            'ActualHours' => 0,
            'PlannedHours' => 0,
            'LaborCost' => 0,
            'TotalRate' => 0,
            'RateCount' => 0
        ];
    }
    
    $personnelMap[$personId]['ActualHours'] += $pa['ActualHours'];
    $personnelMap[$personId]['PlannedHours'] += $pa['PlannedHours'];
    $personnelMap[$personId]['LaborCost'] += $pa['ActualHours'] * $activityRate;
    
    if ($activityRate > 0) {
        $personnelMap[$personId]['TotalRate'] += $activityRate;
        $personnelMap[$personId]['RateCount']++;
    }
}

// Calculate average rates
$personnel = [];
foreach ($personnelMap as $personId => $person) {
    $person['AvgRate'] = $person['RateCount'] > 0 
        ? $person['TotalRate'] / $person['RateCount'] 
        : 0;
    unset($person['TotalRate']);
    unset($person['RateCount']);
    $personnel[] = $person;
}

// Sort by actual hours descending
usort($personnel, function($a, $b) {
    return $b['ActualHours'] <=> $a['ActualHours'];
});

// Calculate project totals
$totals = [
    'budget' => 0,
    'oop' => 0,
    'budgeted_hours' => 0,
    'actual_hours' => 0,
    'planned_hours' => 0,
    'labor_cost' => 0
];

foreach ($activities as &$activity) {
    $activity['LaborCost'] = $activity['ActualHours'] * $activity['Rate'];
    $activity['TotalCost'] = $activity['LaborCost'] + $activity['OOPSpend'];
    $activity['Remaining'] = $activity['Budget'] - $activity['TotalCost'];
    $activity['Utilization'] = $activity['Budget'] > 0 ? ($activity['TotalCost'] / $activity['Budget']) * 100 : 0;
    $activity['HourUtilization'] = $activity['BudgetedHours'] > 0 ? ($activity['ActualHours'] / $activity['BudgetedHours']) * 100 : 0;
    
    $totals['budget'] += $activity['Budget'];
    $totals['oop'] += $activity['OOPSpend'];
    $totals['budgeted_hours'] += $activity['BudgetedHours'];
    $totals['actual_hours'] += $activity['ActualHours'];
    $totals['planned_hours'] += $activity['PlannedHours'];
    $totals['labor_cost'] += $activity['LaborCost'];
}

$totals['total_cost'] = $totals['labor_cost'] + $totals['oop'];
$totals['remaining'] = $totals['budget'] - $totals['total_cost'];
$totals['utilization'] = $totals['budget'] > 0 ? ($totals['total_cost'] / $totals['budget']) * 100 : 0;
$totals['hour_utilization'] = $totals['budgeted_hours'] > 0 ? ($totals['actual_hours'] / $totals['budgeted_hours']) * 100 : 0;

// Calculate personnel totals
$personnel_totals = [
    'actual_hours' => 0,
    'planned_hours' => 0,
    'labor_cost' => 0
];

foreach ($personnel as $person) {
    $personnel_totals['actual_hours'] += $person['ActualHours'];
    $personnel_totals['planned_hours'] += $person['PlannedHours'];
    $personnel_totals['labor_cost'] += $person['LaborCost'];
}

// Format functions
function formatCurrency($amount) {
    return '€ ' . number_format($amount, 2, ',', '.');
}

function formatHours($hours) {
    return number_format($hours, 1, ',', '.') . 'h';
}

function getStatusColor($statusId) {
    $colors = [1 => '#6c757d', 2 => '#ffc107', 3 => '#28a745', 4 => '#dc3545'];
    return $colors[$statusId] ?? '#6c757d';
}

function getBudgetHealth($utilization) {
    if ($utilization > 100) return ['class' => 'danger', 'text' => 'Over Budget', 'icon' => '🔴'];
    if ($utilization > 85) return ['class' => 'warning', 'text' => 'At Risk', 'icon' => '⚠️'];
    return ['class' => 'success', 'text' => 'Healthy', 'icon' => '✅'];
}

$health = getBudgetHealth($totals['utilization']);

// Calculate additional metrics for Key Metrics section
$avgHourlyRate = $totals['actual_hours'] > 0 ? $totals['budget'] / $totals['actual_hours'] : 0;
$budgetedRate = $totals['budgeted_hours'] > 0 ? $totals['budget'] / $totals['budgeted_hours'] : 0;
$laborPct = $totals['total_cost'] > 0 ? ($totals['labor_cost'] / $totals['total_cost']) * 100 : 0;
$oopPct = $totals['total_cost'] > 0 ? ($totals['oop'] / $totals['total_cost']) * 100 : 0;
?>

<section class="white">
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <a href="finance.php" class="btn btn-secondary mb-3">← Back to Financial Report</a>
            <h2><?= htmlspecialchars($project['Name']) ?> - Financial Details</h2>
            <p class="text-muted">
                <span class="badge" style="background-color: <?= getStatusColor($project['Status']) ?>; color: white;">
                    <?= htmlspecialchars($project['StatusName']) ?>
                </span>
                | Manager: <strong><?= htmlspecialchars($project['ManagerName'] ?? 'Unassigned') ?></strong>
                <?php if ($project['ManagerEmail']): ?>
                    (<?= htmlspecialchars($project['ManagerEmail']) ?>)
                <?php endif; ?>
                | Year: <strong><?= $selectedYear ?></strong>
            </p>
        </div>
    </div>

    <!-- Executive Summary -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">Executive Summary</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Budget</h6>
                            <h3 class="text-primary"><?= formatCurrency($totals['budget']) ?></h3>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Spent</h6>
                            <h3><?= formatCurrency($totals['total_cost']) ?></h3>
                            <small class="text-muted">
                                Labor: <?= formatCurrency($totals['labor_cost']) ?><br>
                                OOP: <?= formatCurrency($totals['oop']) ?>
                            </small>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Remaining Budget</h6>
                            <h3 class="<?= $totals['remaining'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= formatCurrency($totals['remaining']) ?>
                            </h3>
                            <small class="text-muted"><?= number_format($totals['utilization'], 1) ?>% utilized</small>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Financial Health</h6>
                            <h3 class="text-<?= $health['class'] ?>">
                                <?= $health['icon'] ?> <?= $health['text'] ?>
                            </h3>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <h6 class="text-muted mb-3">Budget Utilization</h6>
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-<?= $health['class'] ?>" 
                                     role="progressbar" 
                                     style="width: <?= min($totals['utilization'], 100) ?>%">
                                    <?= number_format($totals['utilization'], 1) ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hours Analysis -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Hours Analysis</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Budgeted Hours:</strong></td>
                            <td class="text-end"><?= formatHours($totals['budgeted_hours']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Actual Hours Worked:</strong></td>
                            <td class="text-end"><?= formatHours($totals['actual_hours']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Planned Hours:</strong></td>
                            <td class="text-end"><?= formatHours($totals['planned_hours']) ?></td>
                        </tr>
                        <tr class="table-secondary">
                            <td><strong>Hour Utilization:</strong></td>
                            <td class="text-end">
                                <strong><?= number_format($totals['hour_utilization'], 1) ?>%</strong>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Remaining Hours:</strong></td>
                            <td class="text-end <?= ($totals['budgeted_hours'] - $totals['actual_hours']) >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= formatHours($totals['budgeted_hours'] - $totals['actual_hours']) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Key Metrics</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Actual Average Rate:</strong></td>
                            <td class="text-end">€<?= number_format($avgHourlyRate, 2) ?>/h</td>
                        </tr>
                        <tr>
                            <td><small class="text-muted">Total budget ÷ actual hours worked</small></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td><strong>Budgeted Rate:</strong></td>
                            <td class="text-end">€<?= number_format($budgetedRate, 2) ?>/h</td>
                        </tr>
                        <tr>
                            <td><small class="text-muted">Total budget ÷ budgeted hours</small></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td><strong>Labor Cost %:</strong></td>
                            <td class="text-end"><?= number_format($laborPct, 1) ?>%</td>
                        </tr>
                        <tr>
                            <td><strong>OOP Cost %:</strong></td>
                            <td class="text-end"><?= number_format($oopPct, 1) ?>%</td>
                        </tr>
                        <tr>
                            <td><strong>Total Activities:</strong></td>
                            <td class="text-end"><?= count($activities) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Breakdown -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Activity Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Period</th>
                                    <th class="text-end">Budget</th>
                                    <th class="text-end">Labor Cost</th>
                                    <th class="text-end">OOP</th>
                                    <th class="text-end">Total Spent</th>
                                    <th class="text-end">Remaining</th>
                                    <th class="text-center">Hours</th>
                                    <th class="text-end">Utilization</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($activity['ActivityName']) ?></strong>
                                        <?php if (!$activity['Visible']): ?>
                                            <span class="badge bg-secondary">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?= (new DateTime($activity['StartDate']))->format('d/m/Y') ?><br>
                                            <?= (new DateTime($activity['EndDate']))->format('d/m/Y') ?>
                                        </small>
                                    </td>
                                    <td class="text-end"><?= formatCurrency($activity['Budget']) ?></td>
                                    <td class="text-end">
                                        <?= formatCurrency($activity['LaborCost']) ?>
                                        <br><small class="text-muted">@ €<?= number_format($activity['Rate'], 2) ?>/h</small>
                                    </td>
                                    <td class="text-end"><?= formatCurrency($activity['OOPSpend']) ?></td>
                                    <td class="text-end"><strong><?= formatCurrency($activity['TotalCost']) ?></strong></td>
                                    <td class="text-end <?= $activity['Remaining'] < 0 ? 'text-danger' : 'text-success' ?>">
                                        <strong><?= formatCurrency($activity['Remaining']) ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <small>
                                            <?= formatHours($activity['ActualHours']) ?> / <?= formatHours($activity['BudgetedHours']) ?>
                                            <?php if ($activity['BudgetedHours'] > 0): ?>
                                                <br>(<?= number_format($activity['HourUtilization'], 1) ?>%)
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $activity['Utilization'] > 100 ? 'bg-danger' : ($activity['Utilization'] > 85 ? 'bg-warning' : 'bg-success') ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= min($activity['Utilization'], 100) ?>%">
                                                <?= number_format($activity['Utilization'], 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="2">TOTAL</th>
                                    <th class="text-end"><?= formatCurrency($totals['budget']) ?></th>
                                    <th class="text-end"><?= formatCurrency($totals['labor_cost']) ?></th>
                                    <th class="text-end"><?= formatCurrency($totals['oop']) ?></th>
                                    <th class="text-end"><?= formatCurrency($totals['total_cost']) ?></th>
                                    <th class="text-end <?= $totals['remaining'] < 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= formatCurrency($totals['remaining']) ?>
                                    </th>
                                    <th class="text-center">
                                        <?= formatHours($totals['actual_hours']) ?> / <?= formatHours($totals['budgeted_hours']) ?>
                                    </th>
                                    <th class="text-end"><?= number_format($totals['utilization'], 1) ?>%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personnel Breakdown -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Personnel Hours & Costs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Team</th>
                                    <th class="text-end">Actual Hours</th>
                                    <th class="text-end">Planned Hours</th>
                                    <th class="text-end">Avg Rate</th>
                                    <th class="text-end">Labor Cost</th>
                                    <th class="text-end">% of Total Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($personnel as $person): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($person['PersonName']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($person['Shortname']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($person['Team'] ?? '-') ?></td>
                                    <td class="text-end"><?= formatHours($person['ActualHours']) ?></td>
                                    <td class="text-end"><?= formatHours($person['PlannedHours']) ?></td>
                                    <td class="text-end">€<?= number_format($person['AvgRate'], 2) ?></td>
                                    <td class="text-end"><?= formatCurrency($person['LaborCost']) ?></td>
                                    <td class="text-end">
                                        <?php 
                                        $pct = $personnel_totals['actual_hours'] > 0 ? ($person['ActualHours'] / $personnel_totals['actual_hours']) * 100 : 0;
                                        echo number_format($pct, 1) . '%';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="2">TOTAL</th>
                                    <th class="text-end"><?= formatHours($personnel_totals['actual_hours']) ?></th>
                                    <th class="text-end"><?= formatHours($personnel_totals['planned_hours']) ?></th>
                                    <th></th>
                                    <th class="text-end"><?= formatCurrency($personnel_totals['labor_cost']) ?></th>
                                    <th class="text-end">100%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-<?= $health['class'] ?>">
                <div class="card-header bg-<?= $health['class'] ?> text-white">
                    <h5 class="mb-0">💡 Recommendations & Insights</h5>
                </div>
                <div class="card-body">
                    <ul>
                        <?php if ($totals['utilization'] > 100): ?>
                            <li class="text-danger"><strong>CRITICAL:</strong> Project is over budget by <?= formatCurrency(abs($totals['remaining'])) ?>. Immediate action required.</li>
                        <?php elseif ($totals['utilization'] > 85): ?>
                            <li class="text-warning"><strong>WARNING:</strong> Project is at <?= number_format($totals['utilization'], 1) ?>% budget utilization. Only <?= formatCurrency($totals['remaining']) ?> remaining.</li>
                        <?php else: ?>
                            <li class="text-success"><strong>HEALTHY:</strong> Project is within budget with <?= formatCurrency($totals['remaining']) ?> remaining (<?= number_format(100 - $totals['utilization'], 1) ?>% of budget).</li>
                        <?php endif; ?>
                        
                        <?php if ($totals['hour_utilization'] > 100): ?>
                            <li class="text-danger">Hours exceeded budget by <?= formatHours($totals['actual_hours'] - $totals['budgeted_hours']) ?> (<?= number_format($totals['hour_utilization'] - 100, 1) ?>% over).</li>
                        <?php elseif ($totals['hour_utilization'] > 85): ?>
                            <li class="text-warning">Hour utilization at <?= number_format($totals['hour_utilization'], 1) ?>%. Only <?= formatHours($totals['budgeted_hours'] - $totals['actual_hours']) ?> hours remaining.</li>
                        <?php endif; ?>
                        
                        <?php if ($totals['planned_hours'] > $totals['budgeted_hours']): ?>
                            <li class="text-warning">Planned hours (<?= formatHours($totals['planned_hours']) ?>) exceed budgeted hours (<?= formatHours($totals['budgeted_hours']) ?>). Budget revision may be needed.</li>
                        <?php endif; ?>
                        
                        <?php if ($laborPct > 80): ?>
                            <li>Labor costs represent <?= number_format($laborPct, 1) ?>% of total spending. Project is labor-intensive.</li>
                        <?php endif; ?>
                        
                        <?php if ($totals['actual_hours'] == 0 && $totals['budget'] > 0): ?>
                            <li class="text-info">No hours logged yet. Project may not have started.</li>
                        <?php endif; ?>
                        
                        <?php if ($avgHourlyRate > $budgetedRate * 1.1): ?>
                            <li class="text-warning">Actual hourly rate (€<?= number_format($avgHourlyRate, 2) ?>) is significantly higher than budgeted rate (€<?= number_format($budgetedRate, 2) ?>). Consider reviewing resource allocation.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="mt-4 mb-4">
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Report</button>
        <a href="finance.php" class="btn btn-primary">← Back to Overview</a>
    </div>
</div>
</section>

<?php require 'includes/footer.php'; ?>