<?php
require 'includes/header.php';
require_once 'includes/db.php';

// Use selected year from header.php
// $selectedYear should already be set in header.php

// Get filter parameters
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : 0;

// Build WHERE clause for filters
$whereFilters = ["1=1"];
$params = [':year' => $selectedYear];

if ($statusFilter > 0) {
    $whereFilters[] = "p.Status = :status";
    $params[':status'] = $statusFilter;
}

$whereClause = implode(" AND ", $whereFilters);

// Fetch total hours
$realisedStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(r.RealisedHours), 0) AS RealisedHours,
        COALESCE(SUM(r.BillableHours), 0) AS BillableHours
    FROM (
        SELECT 
            SUM(CASE WHEN h.Person > 0 THEN h.Hours ELSE 0 END) / 100 AS RealisedHours,
            COALESCE(b.Hours, 0) AS BudgetHours,
            LEAST(
                SUM(CASE WHEN h.Person > 0 AND h.Project > 100 THEN h.Hours ELSE 0 END) / 100,
                COALESCE(b.Hours, 0)
            ) AS BillableHours
        FROM Hours h
        JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
        LEFT JOIN Budgets b ON b.Activity = a.Id AND b.Year = :selectedYear
        WHERE h.Project > 0 
            AND h.Year = :selectedYear
            AND a.Visible = 1
        GROUP BY a.Id, b.Hours
    ) AS r
");

$realisedStmt->execute(['selectedYear' => $selectedYear]);
$realisedData = $realisedStmt->fetch(PDO::FETCH_ASSOC);
$totalHoursRealized = $realisedData['RealisedHours'];
$billableHours = $realisedData['BillableHours'];

// Main financial query - Fixed budget calculation
$sql = "
SELECT 
    p.Id as ProjectId,
    p.Name as ProjectName,
    p.Status as StatusId,
    s.Status as StatusName,
    pm.Name as ManagerName,
    
    -- Budget data - sum only for this year
    COALESCE((
        SELECT SUM(b.Budget)
        FROM Budgets b
        INNER JOIN Activities a ON b.Activity = a.Id
        WHERE a.Project = p.Id AND b.Year = :year
    ), 0) as TotalBudget,
    
    COALESCE((
        SELECT SUM(b.OopSpend)
        FROM Budgets b
        INNER JOIN Activities a ON b.Activity = a.Id
        WHERE a.Project = p.Id AND b.Year = :year
    ), 0) as OOPSpend,
    
    COALESCE((
        SELECT SUM(b.Hours)
        FROM Budgets b
        INNER JOIN Activities a ON b.Activity = a.Id
        WHERE a.Project = p.Id AND b.Year = :year
    ), 0) as BudgetedHours,
    
    COALESCE((
        SELECT AVG(b.Rate)
        FROM Budgets b
        INNER JOIN Activities a ON b.Activity = a.Id
        WHERE a.Project = p.Id AND b.Year = :year AND b.Rate > 0
    ), 0) as AvgRate,
    
    -- Actual hours spent
    COALESCE((
        SELECT SUM(h.Hours) / 100
        FROM Hours h
        LEFT JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
        WHERE h.Project = p.Id AND a.Visible=1 AND h.Year = :year AND h.Person > 0
    ), 0) as ActualHours,
    
    -- Planned hours
    COALESCE((
        SELECT SUM(h.Plan) / 100
        FROM Hours h
        WHERE h.Project = p.Id AND h.Year = :year AND h.Person > 0
    ), 0) as PlannedHours
    
FROM Projects p
LEFT JOIN Status s ON p.Status = s.Id
LEFT JOIN Personel pm ON p.Manager = pm.Id
WHERE $whereClause
HAVING TotalBudget > 0 OR ActualHours > 0
ORDER BY p.Status, p.Name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals and metrics
$totals = [
    'budget' => 0,
    'oop' => 0,
    'labor_cost' => 0,
    'total_cost' => 0,
    'budgeted_hours' => 0,
    'planned_hours' => 0,
    'over_budget_count' => 0,
    'at_risk_count' => 0
];

foreach ($projects as &$project) {
    // Calculate labor cost (actual hours * rate)
    $project['LaborCost'] = $project['ActualHours'] * $project['AvgRate'];
    $project['TotalCost'] = $project['LaborCost'] + $project['OOPSpend'];
    
    // Calculate remaining budget
    $project['RemainingBudget'] = $project['TotalBudget'] - $project['TotalCost'];
    
    // Calculate utilization percentage
    $project['BudgetUtilization'] = $project['TotalBudget'] > 0 
        ? ($project['TotalCost'] / $project['TotalBudget']) * 100 
        : 0;
    
    // Calculate hour utilization
    $project['HourUtilization'] = $project['BudgetedHours'] > 0 
        ? ($project['ActualHours'] / $project['BudgetedHours']) * 100 
        : 0;
    
    // Determine status
    if ($project['BudgetUtilization'] > 100) {
        $project['FinancialStatus'] = 'over';
        $totals['over_budget_count']++;
    } elseif ($project['BudgetUtilization'] > 85) {
        $project['FinancialStatus'] = 'risk';
        $totals['at_risk_count']++;
    } else {
        $project['FinancialStatus'] = 'healthy';
    }
    
    // Add to totals
    $totals['budget'] += $project['TotalBudget'];
    $totals['oop'] += $project['OOPSpend'];
    $totals['labor_cost'] += $project['LaborCost'];
    $totals['total_cost'] += $project['TotalCost'];
    $totals['budgeted_hours'] += $project['BudgetedHours'];
    $totals['planned_hours'] += $project['PlannedHours'];
}

$totals['remaining'] = $totals['budget'] - $totals['total_cost'];
$totals['utilization'] = $totals['budget'] > 0 ? ($totals['total_cost'] / $totals['budget']) * 100 : 0;

// Get status list for filter
$statusList = $pdo->query("SELECT Id, Status FROM Status ORDER BY Id")->fetchAll(PDO::FETCH_ASSOC);

// Format currency
function formatCurrency($amount) {
    return '€ ' . number_form($amount, 2);
}

// Format hours
function formatHours($hours) {
    return number_form($hours, 1) . 'h';
}

// Get status badge class
function getBudgetStatusBadge($status) {
    switch ($status) {
        case 'over': return 'bg-danger';
        case 'risk': return 'bg-warning text-dark';
        case 'healthy': return 'bg-success';
        default: return 'bg-secondary';
    }
}


// Status badges
function getStatusBadge($status, $statusName) {
    // Status colors
    $statusColors = [
        1 => '#6c757d', // Lead - Gray
        2 => '#ffc107', // Quote - Yellow
        3 => '#28a745', // Active - Green
        4 => '#dc3545'  // Closed - Red
    ];

    $color = $statusColors[$status] ?? '#6c757d';
    return "<span class='badge' style='background-color: $color; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;'>$statusName</span>";
}

?>

<section class="white">
<div class="container-fluid">
    <h2>Financial Report - <?= $selectedYear ?></h2>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-12">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Statuses</option>
                        <?php foreach ($statusList as $status): ?>
                            <option value="<?= $status['Id'] ?>" <?= $status['Id'] == $statusFilter ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['Status']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" onclick="window.location.href='financial_report.php'" class="btn btn-secondary">
                        Clear Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Total Budget</h5>
                    <h3><?= formatCurrency($totals['budget']) ?></h3>
                    <small><?= count($projects) ?> projects</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title">Total Spent</h5>
                    <h3><?= formatCurrency($totals['total_cost']) ?></h3>
                    <small>Labor: <?= formatCurrency($totals['labor_cost']) ?></small><br>
                    <small>OOP: <?= formatCurrency($totals['oop']) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white <?= $totals['remaining'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                <div class="card-body">
                    <h5 class="card-title">Remaining</h5>
                    <h3><?= formatCurrency($totals['remaining']) ?></h3>
                    <small><?= number_form($totals['utilization'], 1) ?>% utilized</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary">
                <div class="card-body">
                    <h5 class="card-title">Project Health</h5>
                    <h3><?= count($projects) - $totals['over_budget_count'] - $totals['at_risk_count'] ?> / <?= count($projects) ?></h3>
                    <small class="text-warning">⚠ <?= $totals['at_risk_count'] ?> at risk</small><br>
                    <small class="text-danger">🔴 <?= $totals['over_budget_count'] ?> over budget</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Hours Summary -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Hours Overview</h5>
                    <div class="row">
                        <div class="col-md-4">
                        <strong>Actual Hours:</strong> <?= formatHours($totalHoursRealized) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Hours VS budget:</strong>
                            <?php if ($totals['budgeted_hours'] > 0): ?>
                                (<?= number_form(($totalHoursRealized / $totals['budgeted_hours']) * 100, 1) ?>%)
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Budgeted Hours:</strong> <?= formatHours($totals['budgeted_hours']) ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Billable Hours:</strong> <?= formatHours($billableHours) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Billable VS Actual Hours:</strong> 
                            <?php if ($totalHoursRealized > 0): ?>
                                <?= number_form(($billableHours / $totalHoursRealized) * 100, 1) ?>%
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Planned Hours:</strong> <?= formatHours($totals['planned_hours']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Manager</th>
                    <th class="text-end">Budget</th>
                    <th class="text-end">Labor Cost</th>
                    <th class="text-end">OOP</th>
                    <th class="text-end">Total Spent</th>
                    <th class="text-end">Remaining</th>
                    <th class="text-end">Utilization</th>
                    <th class="text-center">Hours</th>
                    <th class="text-center">Health</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td>
                        <strong><a href="project_finance.php?project=<?= $project['ProjectId'] ?>" style="text-decoration: none; color: inherit;"><?= htmlspecialchars($project['ProjectName']) ?></a></strong>
                    </td>
                    <td>
                        <?php echo getStatusBadge($project['StatusId'], $project['StatusName']); ?>
                    </td>
                    <td><?= htmlspecialchars($project['ManagerName'] ?? '-') ?></td>
                    <td class="text-end"><?= formatCurrency($project['TotalBudget']) ?></td>
                    <td class="text-end">
                        <?= formatCurrency($project['LaborCost']) ?>
                        <br><small class="text-muted"><?= formatHours($project['ActualHours']) ?> @ €<?= number_form($project['AvgRate'], 2) ?></small>
                    </td>
                    <td class="text-end"><?= formatCurrency($project['OOPSpend']) ?></td>
                    <td class="text-end"><strong><?= formatCurrency($project['TotalCost']) ?></strong></td>
                    <td class="text-end <?= $project['RemainingBudget'] < 0 ? 'text-danger' : 'text-success' ?>">
                        <strong><?= formatCurrency($project['RemainingBudget']) ?></strong>
                    </td>
                    <td class="text-end">
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar <?= $project['BudgetUtilization'] > 100 ? 'bg-danger' : ($project['BudgetUtilization'] > 85 ? 'bg-warning' : 'bg-success') ?>" 
                                 role="progressbar" 
                                 style="width: <?= min($project['BudgetUtilization'], 100) ?>%"
                                 aria-valuenow="<?= $project['BudgetUtilization'] ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?= number_form($project['BudgetUtilization'], 1) ?>%
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <small>
                            <?= formatHours($project['ActualHours']) ?> / <?= formatHours($project['BudgetedHours']) ?>
                            <?php if ($project['BudgetedHours'] > 0): ?>
                                <br>(<?= number_form($project['HourUtilization'], 1) ?>%)
                            <?php endif; ?>
                        </small>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= getBudgetStatusBadge($project['FinancialStatus']) ?>" style='color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;'>
                            <?php
                            switch ($project['FinancialStatus']) {
                                case 'over': echo 'Over Budget'; break;
                                case 'risk': echo 'At Risk'; break;
                                case 'healthy': echo 'Healthy'; break;
                            }
                            ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-dark">
                <tr>
                    <th colspan="3">TOTAL</th>
                    <th class="text-end"><?= formatCurrency($totals['budget']) ?></th>
                    <th class="text-end"><?= formatCurrency($totals['labor_cost']) ?></th>
                    <th class="text-end"><?= formatCurrency($totals['oop']) ?></th>
                    <th class="text-end"><?= formatCurrency($totals['total_cost']) ?></th>
                    <th class="text-end <?= $totals['remaining'] < 0 ? 'text-danger' : 'text-success' ?>">
                        <?= formatCurrency($totals['remaining']) ?>
                    </th>
                    <th class="text-end"><?= number_form($totals['utilization'], 1) ?>%</th>
                    <th class="text-center">
                        <?= formatHours($totalHoursRealized) ?> / <?= formatHours($totals['budgeted_hours']) ?>
                    </th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Export Options -->
    <div class="mt-4 mb-4">
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Report</button>
        <button onclick="exportToCSV()" class="btn btn-secondary">📊 Export to CSV</button>
    </div>
</div>
</section>

<script>
function exportToCSV() {
    const table = document.querySelector('table');
    let csv = [];
    
    // Headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push('"' + th.textContent.trim() + '"');
    });
    csv.push(headers.join(','));
    
    // Rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            // Clean up text content
            let text = td.textContent.trim().replace(/\s+/g, ' ');
            row.push('"' + text + '"');
        });
        csv.push(row.join(','));
    });
    
    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'financial_report_<?= $selectedYear ?>.csv';
    a.click();
}
</script>

<?php require 'includes/footer.php'; ?>