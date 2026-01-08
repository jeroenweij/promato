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
        AND YEAR(a.StartDate) <= :selectedYear
        AND YEAR(a.EndDate) >= :selectedYear
");
$wbsoRealizedStmt->execute(['selectedYear' => $selectedYear]);
$wbsoRealizedHours = $wbsoRealizedStmt->fetchColumn();

// 2A️⃣ Fetch total activity budget for activities with WBSO
$activityBudgetStmt = $pdo->prepare("
    SELECT COALESCE(SUM(b.Hours), 0) AS TotalActivityBudget
    FROM Budgets b
    JOIN Activities a ON b.Activity = a.Id
    WHERE b.`Year` = :selectedYear
        AND a.Wbso IS NOT NULL
        AND a.Visible = 1
        AND YEAR(a.StartDate) <= :selectedYear
        AND YEAR(a.EndDate) >= :selectedYear
");
$activityBudgetStmt->execute(['selectedYear' => $selectedYear]);
$activityBudgetHours = $activityBudgetStmt->fetchColumn();

// 2B️⃣ Fetch total planned hours on WBSO activities
$plannedHoursStmt = $pdo->prepare("
    SELECT COALESCE(SUM(h.Plan) / 100, 0) AS TotalPlannedHours
    FROM Hours h
    JOIN Activities a ON h.Activity = a.Key AND h.Project = a.Project
    WHERE h.`Year` = :selectedYear
        AND a.Wbso IS NOT NULL
        AND a.Visible = 1
        AND YEAR(a.StartDate) <= :selectedYear
        AND YEAR(a.EndDate) >= :selectedYear
");
$plannedHoursStmt->execute(['selectedYear' => $selectedYear]);
$totalPlannedHours = $plannedHoursStmt->fetchColumn();

// 3️⃣ Fetch WBSO realized hours breakdown by WBSO item
$wbsoBreakdownStmt = $pdo->prepare("
    SELECT
        w.Name AS WbsoName,
        w.Description AS WbsoDescription,
        w.Id AS WbsoId,
        COALESCE(wb.Hours, 0) AS BudgetHours,
        COALESCE((
            SELECT SUM(b2.Hours)
            FROM Budgets b2
            JOIN Activities a2 ON b2.Activity = a2.Id
            WHERE a2.Wbso = w.Id
                AND a2.Visible = 1
                AND b2.Year = :selectedYear
                AND YEAR(a2.StartDate) <= :selectedYear
                AND YEAR(a2.EndDate) >= :selectedYear
        ), 0) AS ActivityBudgetHours,
        COALESCE((
            SELECT SUM(h2.Hours) / 100
            FROM Hours h2
            JOIN Activities a3 ON h2.Activity = a3.Key AND h2.Project = a3.Project
            WHERE a3.Wbso = w.Id
                AND a3.Visible = 1
                AND h2.Year = :selectedYear
                AND YEAR(a3.StartDate) <= :selectedYear
                AND YEAR(a3.EndDate) >= :selectedYear
        ), 0) AS RealizedHours,
        COALESCE((
            SELECT SUM(h3.Plan) / 100
            FROM Hours h3
            JOIN Activities a4 ON h3.Activity = a4.Key AND h3.Project = a4.Project
            WHERE a4.Wbso = w.Id
                AND a4.Visible = 1
                AND h3.Year = :selectedYear
                AND YEAR(a4.StartDate) <= :selectedYear
                AND YEAR(a4.EndDate) >= :selectedYear
        ), 0) AS PlannedHours
    FROM Wbso w
    LEFT JOIN WbsoBudget wb ON w.Id = wb.WbsoId AND wb.`Year` = :selectedYear
    WHERE YEAR(w.StartDate) <= :selectedYear
        AND (w.EndDate IS NULL OR YEAR(w.EndDate) >= :selectedYear)
    ORDER BY w.Name
");
$wbsoBreakdownStmt->execute(['selectedYear' => $selectedYear]);
$wbsoBreakdown = $wbsoBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);

// 📊 Calculate percentages for bar charts
// Chart 1: WBSO budget vs activity budget
$wbsoBudgetVsActivityPercentage = $activityBudgetHours > 0 ? round(($wbsoBudgetedHours / $activityBudgetHours) * 100, 1) : 0;

// Chart 2: Realized vs WBSO budget
$realizedVsWbsoBudgetPercentage = $wbsoBudgetedHours > 0 ? round(($wbsoRealizedHours / $wbsoBudgetedHours) * 100, 1) : 0;

// Chart 3: Realized vs activity budget
$realizedVsActivityBudgetPercentage = $activityBudgetHours > 0 ? round(($wbsoRealizedHours / $activityBudgetHours) * 100, 1) : 0;

// Chart 4: Realized vs planned hours
$realizedVsPlannedPercentage = $totalPlannedHours > 0 ? round(($wbsoRealizedHours / $totalPlannedHours) * 100, 1) : 0;
?>

<section class="white" id="wbso-overview">
    <div class="container">
        <h1>WBSO Overview - <?= $selectedYear ?></h1>

        <!-- Progress Charts -->
        <div class="row">
            <!-- Chart 1: WBSO Budget vs Activity Budget -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>WBSO Budget vs Project Budget</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $wbsoBudgetVsActivityPercentage > 100 ? 'bg-danger' : 'bg-info' ?>"
                                 role="progressbar"
                                 style="width: <?= min(100, $wbsoBudgetVsActivityPercentage) ?>%">
                                <?= $wbsoBudgetVsActivityPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>WBSO Budget: <?= number_form($wbsoBudgetedHours) ?> hrs</span>
                        <span>Project Budget: <?= number_form($activityBudgetHours) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Chart 2: Realized vs WBSO Budget -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Realized vs WBSO Budget</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $realizedVsWbsoBudgetPercentage > 100 ? 'bg-danger' : 'bg-warning' ?>"
                                 role="progressbar"
                                 style="width: <?= min(100, $realizedVsWbsoBudgetPercentage) ?>%">
                                <?= $realizedVsWbsoBudgetPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Realized: <?= number_form($wbsoRealizedHours) ?> hrs</span>
                        <span>WBSO Budget: <?= number_form($wbsoBudgetedHours) ?> hrs</span>
                        <span>Remaining: <?= number_form(max(0, $wbsoBudgetedHours - $wbsoRealizedHours)) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Chart 3: Realized vs Activity Budget -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Realized vs Project Budget</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $realizedVsActivityBudgetPercentage > 100 ? 'bg-danger' : 'bg-success' ?>"
                                 role="progressbar"
                                 style="width: <?= min(100, $realizedVsActivityBudgetPercentage) ?>%">
                                <?= $realizedVsActivityBudgetPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Realized: <?= number_form($wbsoRealizedHours) ?> hrs</span>
                        <span>Project Budget: <?= number_form($activityBudgetHours) ?> hrs</span>
                        <span>Remaining: <?= number_form(max(0, $activityBudgetHours - $wbsoRealizedHours)) ?> hrs</span>
                    </div>
                </div>
            </div>

            <!-- Chart 4: Realized vs Planned Hours -->
            <div class="col-md-6 mb-4">
                <div class="progress-block">
                    <h4>Realized vs Planned Hours</h4>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar <?= $realizedVsPlannedPercentage > 100 ? 'bg-danger' : 'bg-primary' ?>"
                                 role="progressbar"
                                 style="width: <?= min(100, $realizedVsPlannedPercentage) ?>%">
                                <?= $realizedVsPlannedPercentage ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <span>Realized: <?= number_form($wbsoRealizedHours) ?> hrs</span>
                        <span>Planned: <?= number_form($totalPlannedHours) ?> hrs</span>
                        <span>Remaining: <?= number_form(max(0, $totalPlannedHours - $wbsoRealizedHours)) ?> hrs</span>
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
                    <th>WBSO Budget</th>
                    <th>Project Budget</th>
                    <th>Planned Hours</th>
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
                    <td><?= number_form($wbso['ActivityBudgetHours']) ?> hrs</td>
                    <td><?= number_form($wbso['PlannedHours']) ?> hrs</td>
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
                    <td colspan="8" class="text-center text-muted">No WBSO items found for <?= $selectedYear ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="8" class="text-center text-muted"><a href="wbso_details.php">Show more details</a></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
