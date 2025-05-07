<?php
$pageSpecificCSS = ['progress-chart.css'];
$pageTitle = 'Capacity Overview';

require 'includes/header.php';
require 'includes/db.php';

// 1️⃣ Fetch project total budget
$budgetStmt = $pdo->prepare("SELECT COALESCE(SUM(Budgets.Hours),0) AS Budget FROM Activities LEFT JOIN Budgets ON Activities.Id = Budgets.Activity WHERE Project > 0");
$budgetStmt->execute();
$projectBudget = $budgetStmt->fetchColumn();

// 2️⃣ Fetch total planned + realised hours
$hoursStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN Person > 0 THEN Plan ELSE 0 END),0) AS PlannedHours,
        COALESCE(SUM(CASE WHEN Person = 0 THEN Hours ELSE 0 END),0) AS RealisedHours
    FROM Hours
    WHERE Project >0
");
$hoursStmt->execute();
$row = $hoursStmt->fetch();
$plannedHours = $row['PlannedHours'] / 100;
$realisedHours = $row['RealisedHours'] / 100;

// Fetch capacity
$currentYear = date('Y');
$personStmt = $pdo->prepare("SELECT p.Shortname, p.Id, p.Fultime, COALESCE(h.Plan, 0) AS AvailableHours 
    FROM Personel p LEFT JOIN Hours h ON h.Person = p.Id AND h.Project = 0 AND h.Activity = 0 
    WHERE p.plan=1 AND (YEAR(p.StartDate) <= :currentYear) AND
        (p.EndDate IS NULL OR YEAR(p.EndDate) >= :currentYear)");
$personStmt->execute(['currentYear' => $currentYear]);

$totalCapacity = 0;
$persons = [];
while ($person = $personStmt->fetch(PDO::FETCH_ASSOC)) {
    $capacity = $person['AvailableHours'] > 0
        ? round($person['AvailableHours'] / 100)  // Stored as hundredths in DB
        : round(($person['Fultime'] ?? 100) * 2080 / 100); // Fallback to estimate
    $totalCapacity += $capacity;

    $persons[] = [
        'Id' => $person['Id'],
        'Name' => $person['Shortname'],
        'Capacity' => $capacity
    ];
}

// 📝 Calculations for pie chart
$remainingCapacity = max(0, $totalCapacity - max($plannedHours, $realisedHours, $projectBudget));
$plannedNotRealised = max(0,$plannedHours - $realisedHours);
$budgetRemainder = max(0, $projectBudget - max($plannedHours, $realisedHours));

// Build pie chart parts array
$pieParts = [
    'Remaining Capacity' => $remainingCapacity,
    'Planned but not Realised' => $plannedNotRealised,
    'Realised Hours' => $realisedHours,
    'Project Budget Remainder' => $budgetRemainder
];

// 👥 Fetch per-person planned/realised
$personHoursStmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN Project > 0 THEN Plan ELSE 0 END)/100 AS PlannedHours,
        SUM(CASE WHEN Project = 0 THEN Hours ELSE 0 END)/100 AS RealisedHours,
        Person FROM Hours WHERE Person > 0 GROUP BY Person
");

$personHoursStmt->execute();
$personHoursMap = [];
foreach ($personHoursStmt as $ph) {
    $personHoursMap[$ph['Person']] = [
        'PlannedHours' => round($ph['PlannedHours']),
        'RealisedHours' => round($ph['RealisedHours'])
    ];
}

// Merge capacity with hours
$jsPersons = [];
foreach ($persons as $p) {
    $planned = $personHoursMap[$p['Id']]['PlannedHours'] ?? 0;
    $realised = $personHoursMap[$p['Id']]['RealisedHours'] ?? 0;
    $available = $p['Capacity'];

    $jsPersons[] = [
        'name' => $p['Name'],
        'PlanHours' => $planned,
        'SpentHours' => $realised,
        'BudgetHours' => $available
    ];
}
?>

<section id="capacity-overview">
    <div class="container">
        <h1>Capacity Overview</h1>

        <canvas id="capacityPieChart" width="400" height="400"></canvas>
        <table class="table table-striped mt-3">
            <thead>
            <tr>
                <th>Color</th>
                <th>Label</th>
                <th>Value</th>
                <th>% of Total Capacity</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // Define colors for pie chart
            $colors = [
                'rgba(66, 133, 244, 0.7)',  // blue
                'rgba(219, 68, 55, 0.7)',   // red
                'rgba(244, 180, 0, 0.7)',   // yellow
                'rgba(15, 157, 88, 0.7)'    // green
            ];
            $i = 0;
            foreach ($pieParts as $label => $value):
                $percentage = $totalCapacity > 0 ? round($value / $totalCapacity * 100, 1) : 0;
                $color = $colors[$i % count($colors)];
                ?>
                <tr>
                    <td><div style="width:20px; height:20px; background-color:<?= $color ?>;"></div></td>
                    <td><?= htmlspecialchars($label) ?></td>
                    <td><?= $value ?></td>
                    <td><?= $percentage ?>%</td>
                </tr>
                <?php $i++; endforeach; ?>
            </tbody>
        </table>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const pieCtx = document.getElementById('capacityPieChart').getContext('2d');
            const pieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    //labels: <?php echo json_encode(array_keys($pieParts)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($pieParts)); ?>,
                        backgroundColor: <?php echo json_encode($colors); ?>,
                        borderColor: <?php echo json_encode($colors); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        </script>

        <hr>
        <h3>Per Person Capacity Overview</h3>
        <div id="progressChart"></div>

        <script>
            const progressChartData = {
                activities: <?php echo json_encode($jsPersons, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>,
                totalBudget: <?php echo $totalCapacity; ?>,
                totalSpent: <?php echo $realisedHours; ?>,
                totalPlan: <?php echo $plannedHours; ?>
            };
        </script>

        <script src="js/progress-chart.js"></script>

    </div>
</section>

<?php require 'includes/footer.php'; ?>
