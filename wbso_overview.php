<?php
$pageSpecificCSS = ['capacity.css'];
$pageTitle = 'WBSO Overview';

require 'includes/header.php';

// 1️⃣ Fetch total WBSO budget for the selected year
$wbsoBudgetStmt = $pdo->prepare("
    SELECT COALESCE(SUM(wb.Hours), 0) AS Budget
    FROM WbsoBudget wb
    WHERE wb.`Year` = :selectedYear
");
$wbsoBudgetStmt->execute(['selectedYear' => $selectedYear]);
$wbsoBudgetedHours = $wbsoBudgetStmt->fetchColumn();

// 2️⃣ Fetch total WBSO realized hours for the selected year
$wbsoRealizedStmt = $pdo->prepare("
    SELECT COALESCE(SUM(h.Hours) / 100, 0) AS RealisedHours
    FROM Hours h
    JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
    WHERE h.`Year` = :selectedYear
        AND a.Wbso IS NOT NULL
        AND a.Visible = 1
");
$wbsoRealizedStmt->execute(['selectedYear' => $selectedYear]);
$wbsoRealizedHours = $wbsoRealizedStmt->fetchColumn();

// 3️⃣ Fetch WBSO realized hours breakdown by WBSO item
$wbsoBreakdownStmt = $pdo->prepare("
    SELECT
        w.Name AS WbsoName,
        w.Description AS WbsoDescription,
        w.Id AS WbsoId,
        COALESCE(wb.Hours, 0) AS BudgetHours,
        COALESCE(SUM(h.Hours) / 100, 0) AS RealizedHours,
        COALESCE(SUM(h.Plan) / 100, 0) AS PlannedHours
    FROM Wbso w
    LEFT JOIN WbsoBudget wb ON w.Id = wb.WbsoId AND wb.`Year` = :selectedYear
    LEFT JOIN Activities a ON a.Wbso = w.Id AND a.Visible = 1
    LEFT JOIN Hours h ON h.Activity = a.Key AND h.Project = a.Project AND h.`Year` = :selectedYear
    WHERE YEAR(w.StartDate) <= :selectedYear
        AND (w.EndDate IS NULL OR YEAR(w.EndDate) >= :selectedYear)
    GROUP BY w.Id, w.Name, w.Description, wb.Hours
    ORDER BY w.Name
");
$wbsoBreakdownStmt->execute(['selectedYear' => $selectedYear]);
$wbsoBreakdown = $wbsoBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);

// 📊 Calculate pie chart values
$wbsoRealizedPie = round($wbsoRealizedHours);
$wbsoRemainingPie = round(max(0, $wbsoBudgetedHours - $wbsoRealizedHours));

// Calculate percentage
$wbsoRealizedPercentage = $wbsoBudgetedHours > 0 ? round(($wbsoRealizedHours / $wbsoBudgetedHours) * 100, 1) : 0;

// Build pie chart data
$pieParts = [
    'Hours Realized' => $wbsoRealizedPie,
    'Hours Remaining' => $wbsoRemainingPie
];

$pieColors = [
    'rgba(244, 180, 0, 0.7)',   // yellow - Realized
    'rgba(15, 157, 88, 0.7)'    // green - Remaining
];
?>

<section class="white" id="wbso-overview">
    <div class="container">
        <h1>WBSO Overview - <?= $selectedYear ?></h1>

        <!-- Pie Chart and Summary -->
        <h3>WBSO Budget Progress</h3>
        <div class="chart-container">
            <div class="pie-chart-wrapper">
                <canvas id="wbsoPieChart" width="400" height="400"></canvas>
            </div>

            <div class="pie-legend-table">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Color</th>
                            <th>Category</th>
                            <th>Hours</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><div style="width:30px; height:30px; background-color:<?= $pieColors[0] ?>; border-radius: 4px;"></div></td>
                            <td><strong>Hours Realized</strong></td>
                            <td><?= number_form($wbsoRealizedHours) ?> hrs</td>
                            <td><?= $wbsoBudgetedHours > 0 ? round($wbsoRealizedPie / $wbsoBudgetedHours * 100, 1) : 0 ?>%</td>
                        </tr>
                        <tr>
                            <td><div style="width:30px; height:30px; background-color:<?= $pieColors[1] ?>; border-radius: 4px;"></div></td>
                            <td><strong>Hours Remaining</strong></td>
                            <td><?= number_form($wbsoRemainingPie) ?> hrs</td>
                            <td><?= $wbsoBudgetedHours > 0 ? round($wbsoRemainingPie / $wbsoBudgetedHours * 100, 1) : 0 ?>%</td>
                        </tr>
                        <tr class="table-info">
                            <td colspan="2"><strong>Total WBSO Budget</strong></td>
                            <td><strong><?= number_form($wbsoBudgetedHours) ?> hrs</strong></td>
                            <td><strong>100%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <hr>

        <!-- Overall Progress -->
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="progress-block">
                    <h4>Total WBSO Hours Progress</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $wbsoRealizedPercentage > 100 ? 'bg-danger' : 'bg-warning' ?>"
                                 role="progressbar"
                                 style="width: <?= min(100, $wbsoRealizedPercentage) ?>%">
                                <?= $wbsoRealizedPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Realized: <?= number_form($wbsoRealizedHours) ?> hrs</span>
                        <span>Budgeted: <?= number_form($wbsoBudgetedHours) ?> hrs</span>
                        <span>Remaining: <?= number_form(max(0, $wbsoBudgetedHours - $wbsoRealizedHours)) ?> hrs</span>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <!-- WBSO Breakdown by Item -->
        <h3>WBSO Breakdown by Item</h3>
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>WBSO Item</th>
                    <th>Description</th>
                    <th>Budget Hours</th>
                    <th>Realized Hours</th>
                    <th>Remaining Hours</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wbsoBreakdown as $wbso):
                    $remaining = max(0, $wbso['BudgetHours'] - $wbso['RealizedHours']);
                    $percentage = $wbso['BudgetHours'] > 0 ? round(($wbso['RealizedHours'] / $wbso['BudgetHours']) * 100, 1) : 0;
                    $progressClass = $percentage > 100 ? 'bg-danger' : ($percentage >= 80 ? 'bg-warning' : 'bg-success');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($wbso['WbsoName']) ?></strong></td>
                    <td><?= htmlspecialchars($wbso['WbsoDescription'] ?? '') ?></td>
                    <td><?= number_form($wbso['BudgetHours']) ?> hrs</td>
                    <td><?= number_form($wbso['RealizedHours']) ?> hrs</td>
                    <td><?= number_form($remaining) ?> hrs</td>
                    <td>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar <?= $progressClass ?>"
                                 role="progressbar"
                                 style="width: <?= min(100, $percentage) ?>%">
                                <?= $percentage ?>%
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($wbsoBreakdown)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No WBSO items found for <?= $selectedYear ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pieCtx = document.getElementById('wbsoPieChart').getContext('2d');
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
                            const total = <?= $wbsoBudgetedHours ?>;
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
