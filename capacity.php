<?php
$pageSpecificCSS = ['capacity.css'];
$pageTitle = 'Capacity Overview';

require 'includes/header.php';

// Get filter setting
$excludeInternal = isset($_GET['exclude_internal']) ? (int)$_GET['exclude_internal'] : 0;
$projectFilter = $excludeInternal ? "AND Project > 100" : "";
$projectFilterExt = $excludeInternal ? "AND h.Project > 100" : "";

// 1️⃣ Fetch project total budget (with filter)
$budgetStmt = $pdo->prepare("
    SELECT COALESCE(SUM(b.Hours), 0) AS Budget 
    FROM Activities a
    LEFT JOIN Budgets b ON a.Id = b.Activity AND b.`Year` = :selectedYear
    WHERE a.Project > 0 $projectFilter
    AND YEAR(a.StartDate) <= :selectedYear 
    AND (a.EndDate IS NULL OR YEAR(a.EndDate) >= :selectedYear)
");
$budgetStmt->execute(['selectedYear' => $selectedYear]);
$hoursBudgeted = $budgetStmt->fetchColumn();

// 2️⃣ Fetch total person planned hours (with filter)
$personPlannedStmt = $pdo->prepare("
    SELECT COALESCE(SUM(CASE WHEN Person > 0 THEN Plan ELSE 0 END), 0) AS PlannedHours
    FROM Hours
    WHERE Project > 0 $projectFilter AND `Year` = :selectedYear
");
$personPlannedStmt->execute(['selectedYear' => $selectedYear]);
$personHoursPlanned = $personPlannedStmt->fetchColumn() / 100;

// 3️⃣ Fetch total team planned hours (with filter)
$teamPlannedStmt = $pdo->prepare("
    SELECT COALESCE(SUM(Plan), 0) AS TeamPlanned
    FROM TeamHours
    WHERE Project > 0 $projectFilter AND `Year` = :selectedYear
");
$teamPlannedStmt->execute(['selectedYear' => $selectedYear]);
$teamHoursPlanned = $teamPlannedStmt->fetchColumn() / 100;

// 4️⃣ Fetch total realized hours (with filter)
$realisedStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(r.RealisedHours), 0) AS RealisedHours,
        COALESCE(SUM(r.InBudgetHours), 0) AS InBudgetHours,
        COALESCE(SUM(r.OutBudgetHours), 0) AS OutBudgetHours
    FROM (
        SELECT 
            SUM(CASE WHEN h.Person = 0 THEN h.Hours ELSE 0 END) / 100 AS RealisedHours,
            COALESCE(b.Hours, 0) AS BudgetHours,
            LEAST(
                SUM(CASE WHEN h.Person = 0 THEN h.Hours ELSE 0 END) / 100,
                COALESCE(b.Hours, 0)
            ) AS InBudgetHours,
            GREATEST(
                0,
                (SUM(CASE WHEN h.Person = 0 THEN h.Hours ELSE 0 END) / 100) - COALESCE(b.Hours, 0)
            ) AS OutBudgetHours
        FROM Hours h
        JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
        LEFT JOIN Budgets b ON b.Activity = a.Id AND b.Year = :selectedYear
        WHERE h.Project > 0 
            AND h.Year = :selectedYear
            AND a.Visible = 1
            $projectFilterExt
        GROUP BY a.Id, b.Hours
    ) AS r
");

$realisedStmt->execute(['selectedYear' => $selectedYear]);
$realisedData = $realisedStmt->fetch(PDO::FETCH_ASSOC);
$hoursRealized = $realisedData['RealisedHours'];
$hoursRealizedInBudget = $realisedData['InBudgetHours'];
$hoursRealizedOutBudget = $realisedData['OutBudgetHours'];

// 5️⃣ Fetch total capacity (all active personnel)
$capacityStmt = $pdo->prepare("
    SELECT 
        p.Id,
        p.Shortname, 
        p.Fultime, 
        COALESCE(h.Plan, 0) AS AvailableHours 
    FROM Personel p 
    LEFT JOIN Hours h ON h.Person = p.Id AND h.Project = 0 AND h.Activity = 0 AND h.`Year` = :selectedYear
    WHERE p.plan = 1 
    AND YEAR(p.StartDate) <= :selectedYear 
    AND (p.EndDate IS NULL OR YEAR(p.EndDate) >= :selectedYear)
");
$capacityStmt->execute(['selectedYear' => $selectedYear]);

$totalCapacity = 0;
while ($person = $capacityStmt->fetch(PDO::FETCH_ASSOC)) {
    $capacity = $person['AvailableHours'] > 0
        ? round($person['AvailableHours'] / 100)
        : round(($person['Fultime'] ?? 100) * 2080 / 100);
    $totalCapacity += $capacity;
}

// 📊 Calculate pie chart values
// All values should add up to totalCapacity
$hoursRealizedPie = round($hoursRealized);
$teamHoursPlannedPie = round(max(0, $teamHoursPlanned - $hoursRealized)); // Planned but not realized
$hoursBudgetedPie = round(max(0, $hoursBudgeted - max($hoursRealized, $teamHoursPlanned))); // Budgeted but not planned or realized
$availableCapacityPie = round(max(0, $totalCapacity - max($hoursRealized, $teamHoursPlanned, $hoursBudgeted)));

// Calculate percentages for progress bars
$realizedPercentage = $totalCapacity > 0 ? round(($hoursRealized / $totalCapacity) * 100, 1) : 0;
$realizedInBudgetPercentage = $totalCapacity > 0 ? round(($hoursRealizedInBudget / $totalCapacity) * 100, 1) : 0;
$realizedOutBudgetPercentage = $totalCapacity > 0 ? round(($hoursRealizedOutBudget / $totalCapacity) * 100, 1) : 0;
$budgetedPercentage = $totalCapacity > 0 ? round(($hoursBudgeted / $totalCapacity) * 100, 1) : 0;
$teamPlannedPercentage = $totalCapacity > 0 ? round(($teamHoursPlanned / $totalCapacity) * 100, 1) : 0;
$personPlannedPercentage = $totalCapacity > 0 ? round(($personHoursPlanned / $totalCapacity) * 100, 1) : 0;

// Remaining capacity for each category
$realizedRemaining = $totalCapacity - $hoursRealized;
$budgetedRemaining = $totalCapacity - $hoursBudgeted;
$teamPlannedRemaining = $totalCapacity - $teamHoursPlanned;
$personPlannedRemaining = $totalCapacity - $personHoursPlanned;

// Build pie chart data
$pieParts = [
    'Hours Realized' => $hoursRealizedPie,
    'Team Hours Planned' => $teamHoursPlannedPie,
    'Hours Budgeted' => $hoursBudgetedPie,
    'Available Capacity' => $availableCapacityPie
];

// Build pie chart legend data
$piePartsLegend = [
    'Hours Realized' => [$hoursRealized, $hoursRealizedPie],
    'Team Hours Planned' => [$teamHoursPlanned, $teamHoursPlannedPie],
    'Hours Budgeted' => [$hoursBudgeted, $hoursBudgetedPie],
    'Available Capacity' => [$totalCapacity, $availableCapacityPie]
];

$pieColors = [
    'rgba(244, 180, 0, 0.7)',   // yellow - Realized
    'rgba(219, 68, 55, 0.7)',   // red - Team Planned
    'rgba(66, 133, 244, 0.7)',  // blue - Budgeted
    'rgba(15, 157, 88, 0.7)'    // green - Available
];
?>

<section class="white" id="capacity-overview">
    <div class="container">
        <h1>Capacity Overview - <?= $selectedYear ?></h1>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="exclude_internal" 
                           name="exclude_internal" value="1" 
                           <?= $excludeInternal ? 'checked' : '' ?> 
                           onchange="this.form.submit()">
                    <label class="form-check-label" for="exclude_internal">
                        <strong>Exclude internal projects (Project ID < 100)</strong>
                        <br><small class="text-muted">Show only billable project hours</small>
                    </label>
                </div>
            </form>
        </div>

        <!-- Pie Chart and Legend -->
        <h3>Capacity Distribution</h3>
        <div class="chart-container">
            <div class="pie-chart-wrapper">
                <canvas id="capacityPieChart" width="400" height="400"></canvas>
            </div>
            
            <div class="pie-legend-table">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Color</th>
                            <th>Category</th>
                            <th>Hours</th>
                            <th>Remaining Hours</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 0;
                        foreach ($piePartsLegend as $label => $value):
                            $percentage = $totalCapacity > 0 ? round($value[1] / $totalCapacity * 100, 1) : 0;
                            $color = $pieColors[$i];
                        ?>
                        <tr>
                            <td><div style="width:30px; height:30px; background-color:<?= $color ?>; border-radius: 4px;"></div></td>
                            <td><strong><?= htmlspecialchars($label) ?></strong></td>
                            <td><?= number_form($value[0]) ?> hrs</td>
                            <td><?= number_form($value[1]) ?> hrs</td>
                            <td><?= $percentage ?>%</td>
                        </tr>
                        <?php 
                            $i++;
                        endforeach; 
                        ?>
                        <tr class="table-info">
                            <td colspan="2"><strong>Total Capacity</strong></td>
                            <td><strong><?= number_form($totalCapacity) ?> hrs</strong></td>
                            <td><strong><?= number_form($totalCapacity) ?> hrs</strong></td>
                            <td><strong>100%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <hr>
        <!-- Progress Blocks -->
        <div class="row">
            <!-- Hours Realized in budget-->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Hours Realized In Budget</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $realizedInBudgetPercentage > 100 ? 'bg-danger' : 'bg-success' ?>" 
                                 role="progressbar" 
                                 style="width: <?= min(100, $realizedInBudgetPercentage) ?>%">
                                <?= $realizedInBudgetPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Realized: <?= number_form($hoursRealizedInBudget) ?> hrs</span>
                        <span>Total Capacity: <?= number_form($totalCapacity) ?> hrs</span>
                        <span>Remaining: <?= number_form($realizedRemaining) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Hours Realized out budget-->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Hours Realized Out Of Budget</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $realizedOutBudgetPercentage > 100 ? 'bg-danger' : 'bg-success' ?>" 
                                 role="progressbar" 
                                 style="width: <?= min(100, $realizedOutBudgetPercentage) ?>%">
                                <?= $realizedOutBudgetPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Realized: <?= number_form($hoursRealizedOutBudget) ?> hrs</span>
                        <span>Total Capacity: <?= number_form($totalCapacity) ?> hrs</span>
                        <span>Remaining: <?= number_form($realizedRemaining) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Hours Realized -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Total Hours Realized</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $realizedPercentage > 100 ? 'bg-danger' : 'bg-success' ?>" 
                                 role="progressbar" 
                                 style="width: <?= min(100, $realizedPercentage) ?>%">
                                <?= $realizedPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Realized: <?= number_form($hoursRealized) ?> hrs</span>
                        <span>Total Capacity: <?= number_form($totalCapacity) ?> hrs</span>
                        <span>Remaining: <?= number_form($realizedRemaining) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Hours Budgeted -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Hours Budgeted</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $budgetedPercentage > 100 ? 'bg-danger' : 'bg-success' ?>" 
                                 role="progressbar" 
                                 style="width: <?= min(100, $budgetedPercentage) ?>%">
                                <?= $budgetedPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Budgeted: <?= number_form($hoursBudgeted) ?> hrs</span>
                        <span>Total Capacity: <?= number_form($totalCapacity) ?> hrs</span>
                        <span>Remaining: <?= number_form($budgetedRemaining) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Team Hours Planned -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Team Hours Planned</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $teamPlannedPercentage > 100 ? 'bg-danger' : 'bg-success' ?>" 
                                 role="progressbar" 
                                 style="width: <?= min(100, $teamPlannedPercentage) ?>%">
                                <?= $teamPlannedPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Planned: <?= number_form($teamHoursPlanned) ?> hrs</span>
                        <span>Total Capacity: <?= number_form($totalCapacity) ?> hrs</span>
                        <span>Remaining: <?= number_form($teamPlannedRemaining) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Person Hours Planned -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Person Hours Planned</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $personPlannedPercentage > 100 ? 'bg-danger' : 'bg-success' ?>" 
                                 role="progressbar" 
                                 style="width: <?= min(100, $personPlannedPercentage) ?>%">
                                <?= $personPlannedPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Planned: <?= number_form($personHoursPlanned) ?> hrs</span>
                        <span>Total Capacity: <?= number_form($totalCapacity) ?> hrs</span>
                        <span>Remaining: <?= number_form($personPlannedRemaining) ?> hrs</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pieCtx = document.getElementById('capacityPieChart').getContext('2d');
    const pieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_keys($pieParts)) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($pieParts)) ?>,
                        backgroundColor: <?= json_encode($pieColors) ?>,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = <?= $totalCapacity ?>;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value.toLocaleString() + ' hrs (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
});
</script>

<?php require 'includes/footer.php'; ?>