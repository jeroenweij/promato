<?php
$pageSpecificCSS = ['capacity.css'];
$pageTitle = 'Hours Report';

require 'includes/header.php';

// Fetch realized hours for INTERNAL projects (Project < 100)
$internalRealisedStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(r.RealisedHours), 0) AS RealisedHours,
        COALESCE(SUM(r.InBudgetHours), 0) AS InBudgetHours,
        COALESCE(SUM(r.OutBudgetHours), 0) AS OutBudgetHours,
        COALESCE(SUM(r.BudgetHours), 0) AS TotalBudget
    FROM (
        SELECT
            SUM(h.Hours) / 100 AS RealisedHours,
            COALESCE(b.Hours, 0) AS BudgetHours,
            LEAST(
                SUM(h.Hours) / 100,
                COALESCE(b.Hours, 0)
            ) AS InBudgetHours,
            GREATEST(
                0,
                (SUM(h.Hours) / 100) - COALESCE(b.Hours, 0)
            ) AS OutBudgetHours
        FROM Hours h
        JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
        LEFT JOIN Budgets b ON b.Activity = a.Id AND b.Year = :selectedYear
        WHERE h.Project > 0
            AND h.Project < 100
            AND h.Year = :selectedYear
            AND a.Visible = 1
        GROUP BY a.Id, b.Hours
    ) AS r
");

$internalRealisedStmt->execute(['selectedYear' => $selectedYear]);
$internalData = $internalRealisedStmt->fetch(PDO::FETCH_ASSOC);
$internalHoursRealized = $internalData['RealisedHours'];
$internalHoursInBudget = $internalData['InBudgetHours'];
$internalHoursOutBudget = $internalData['OutBudgetHours'];

// Fetch realized hours for EXTERNAL projects (Project >= 100)
$externalRealisedStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(r.RealisedHours), 0) AS RealisedHours,
        COALESCE(SUM(r.InBudgetHours), 0) AS InBudgetHours,
        COALESCE(SUM(r.OutBudgetHours), 0) AS OutBudgetHours,
        COALESCE(SUM(r.BudgetHours), 0) AS TotalBudget
    FROM (
        SELECT
            SUM(h.Hours) / 100 AS RealisedHours,
            COALESCE(b.Hours, 0) AS BudgetHours,
            LEAST(
                SUM(h.Hours) / 100,
                COALESCE(b.Hours, 0)
            ) AS InBudgetHours,
            GREATEST(
                0,
                (SUM(h.Hours) / 100) - COALESCE(b.Hours, 0)
            ) AS OutBudgetHours
        FROM Hours h
        JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
        LEFT JOIN Budgets b ON b.Activity = a.Id AND b.Year = :selectedYear
        WHERE h.Project >= 100
            AND h.Year = :selectedYear
            AND a.Visible = 1
        GROUP BY a.Id, b.Hours
    ) AS r
");

$externalRealisedStmt->execute(['selectedYear' => $selectedYear]);
$externalData = $externalRealisedStmt->fetch(PDO::FETCH_ASSOC);
$externalHoursRealized = $externalData['RealisedHours'];
$externalHoursInBudget = $externalData['InBudgetHours'];
$externalHoursOutBudget = $externalData['OutBudgetHours'];

// Build pie chart data
$pieParts = [
    'Internal: In Budget' => $internalHoursInBudget,
    'Internal: Out of Budget' => $internalHoursOutBudget,
    'External: In Budget' => $externalHoursInBudget,
    'External: Out of Budget' => $externalHoursOutBudget
];

// Total hours for percentage calculations
$totalHours = array_sum($pieParts);

// Define colors for the pie chart segments
$pieColors = [
    'rgba(66, 133, 244, 0.7)',   // blue - Internal In Budget
    'rgba(219, 68, 55, 0.7)',    // red - Internal Out of Budget
    'rgba(15, 157, 88, 0.7)',    // green - External In Budget
    'rgba(244, 180, 0, 0.7)'    // yellow/orange - External Out of Budget
];
?>

<section class="white" id="hours-report">
    <div class="container">
        <h1>Hours Report - <?= $selectedYear ?></h1>

        <!-- Pie Chart and Legend -->
        <h3>Hours Distribution: Internal vs External Projects</h3>
        <div class="chart-container">
            <div class="pie-chart-wrapper">
                <canvas id="hoursReportPieChart" width="400" height="400"></canvas>
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
                        <?php
                        $i = 0;
                        foreach ($pieParts as $label => $value):
                            $percentage = $totalHours > 0 ? round($value / $totalHours * 100, 1) : 0;
                            $color = $pieColors[$i];
                        ?>
                        <tr>
                            <td><div style="width:30px; height:30px; background-color:<?= $color ?>; border-radius: 4px;"></div></td>
                            <td><strong><?= htmlspecialchars($label) ?></strong></td>
                            <td><?= number_form($value) ?> hrs</td>
                            <td><?= $percentage ?>%</td>
                        </tr>
                        <?php
                            $i++;
                        endforeach;
                        ?>
                        <tr class="table-info">
                            <td colspan="2"><strong>Total Hours</strong></td>
                            <td><strong><?= number_form($totalHours) ?> hrs</strong></td>
                            <td><strong>100%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <hr>
        <!-- Summary Tables -->
        <h3>Summary by Project Type</h3>
        <div class="row">
            <!-- Internal Projects Summary -->
            <div class="col-md-6 mb-4">
                <h4>Internal Projects (ID &lt; 100)</h4>
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td><strong>Hours Logged In Budget</strong></td>
                            <td class="text-right"><?= number_form($internalHoursInBudget) ?> hrs</td>
                        </tr>
                        <tr>
                            <td><strong>Hours Logged Out of Budget</strong></td>
                            <td class="text-right"><?= number_form($internalHoursOutBudget) ?> hrs</td>
                        </tr>
                        <tr class="table-info">
                            <td><strong>Total Hours Realized</strong></td>
                            <td class="text-right"><?= number_form($internalHoursRealized) ?> hrs</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- External Projects Summary -->
            <div class="col-md-6 mb-4">
                <h4>External Projects (ID &ge; 100)</h4>
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td><strong>Hours Logged In Budget</strong></td>
                            <td class="text-right"><?= number_form($externalHoursInBudget) ?> hrs</td>
                        </tr>
                        <tr>
                            <td><strong>Hours Logged Out of Budget</strong></td>
                            <td class="text-right"><?= number_form($externalHoursOutBudget) ?> hrs</td>
                        </tr>
                        <tr class="table-info">
                            <td><strong>Total Hours Realized</strong></td>
                            <td class="text-right"><?= number_form($externalHoursRealized) ?> hrs</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pieCtx = document.getElementById('hoursReportPieChart').getContext('2d');
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
                            const total = <?= $totalHours ?>;
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