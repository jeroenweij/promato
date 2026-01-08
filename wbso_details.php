<?php
$pageSpecificCSS = ['capacity.css'];
$pageTitle = 'WBSO Details';

require 'includes/header.php';

// Fetch all activities with WBSO for the selected year
$stmt = $pdo->prepare("
    SELECT
        p.Id AS ProjectId,
        p.Name AS ProjectName,
        p.Status AS ProjectStatus,
        a.Id AS ActivityId,
        a.Key AS ActivityKey,
        a.Name AS ActivityName,
        a.StartDate,
        a.EndDate,
        w.Id AS WbsoId,
        w.Name AS WbsoName,
        w.Description AS WbsoDescription,
        COALESCE(wb.Hours, 0) AS WbsoBudget,
        COALESCE(b.Hours, 0) AS ActivityBudget,
        COALESCE(SUM(h.Plan) / 100, 0) AS PlannedHours,
        COALESCE(SUM(h.Hours) / 100, 0) AS RealizedHours
    FROM Activities a
    JOIN Projects p ON a.Project = p.Id
    JOIN Wbso w ON a.Wbso = w.Id
    LEFT JOIN WbsoBudget wb ON w.Id = wb.WbsoId AND wb.Year = :selectedYear
    LEFT JOIN Budgets b ON a.Id = b.Activity AND b.Year = :selectedYear
    LEFT JOIN Hours h ON h.Activity = a.Key AND h.Project = p.Id AND h.Year = :selectedYear
    WHERE a.Visible = 1
        AND a.Wbso IS NOT NULL
        AND YEAR(a.StartDate) <= :selectedYear
        AND YEAR(a.EndDate) >= :selectedYear
    GROUP BY p.Id, p.Name, p.Status, a.Id, a.Key, a.Name, a.StartDate, a.EndDate, w.Id, w.Name, w.Description, wb.Hours, b.Hours
    ORDER BY w.Name, p.Name, a.Key
");
$stmt->execute(['selectedYear' => $selectedYear]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all WBSO items for the selected year (including those without activities)
$allWbsoStmt = $pdo->prepare("
    SELECT
        w.Id AS WbsoId,
        w.Name AS WbsoName,
        w.Description AS WbsoDescription,
        COALESCE(wb.Hours, 0) AS WbsoBudget
    FROM Wbso w
    LEFT JOIN WbsoBudget wb ON w.Id = wb.WbsoId AND wb.Year = :selectedYear
    WHERE YEAR(w.StartDate) <= :selectedYear
        AND (w.EndDate IS NULL OR YEAR(w.EndDate) >= :selectedYear)
    ORDER BY w.Name
");
$allWbsoStmt->execute(['selectedYear' => $selectedYear]);
$allWbsoItems = $allWbsoStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize all WBSO items (including those without activities)
$wbsoGroups = [];
foreach ($allWbsoItems as $wbsoItem) {
    $wbsoGroups[$wbsoItem['WbsoId']] = [
        'name' => $wbsoItem['WbsoName'] ?? '',
        'description' => $wbsoItem['WbsoDescription'] ?? '',
        'wbsoBudget' => $wbsoItem['WbsoBudget'] ?? 0,
        'projects' => [],
        'totalActivityBudget' => 0,
        'totalPlanned' => 0,
        'totalRealized' => 0
    ];
}

// Group activities by WBSO item -> Project -> Activities
foreach ($activities as $activity) {
    $wbsoId = $activity['WbsoId'];
    $projectId = $activity['ProjectId'];

    // Ensure WBSO group exists (in case activity references WBSO not active in selected year)
    if (!isset($wbsoGroups[$wbsoId])) {
        $wbsoGroups[$wbsoId] = [
            'name' => $activity['WbsoName'] ?? '',
            'description' => $activity['WbsoDescription'] ?? '',
            'wbsoBudget' => $activity['WbsoBudget'] ?? 0,
            'projects' => [],
            'totalActivityBudget' => 0,
            'totalPlanned' => 0,
            'totalRealized' => 0
        ];
    }

    // Initialize project group within WBSO if not exists
    if (!isset($wbsoGroups[$wbsoId]['projects'][$projectId])) {
        $wbsoGroups[$wbsoId]['projects'][$projectId] = [
            'name' => $activity['ProjectName'],
            'status' => $activity['ProjectStatus'],
            'activities' => [],
            'totalActivityBudget' => 0,
            'totalPlanned' => 0,
            'totalRealized' => 0
        ];
    }

    // Add activity to project
    $wbsoGroups[$wbsoId]['projects'][$projectId]['activities'][] = $activity;
    $wbsoGroups[$wbsoId]['projects'][$projectId]['totalActivityBudget'] += $activity['ActivityBudget'] ?? 0;
    $wbsoGroups[$wbsoId]['projects'][$projectId]['totalPlanned'] += $activity['PlannedHours'] ?? 0;
    $wbsoGroups[$wbsoId]['projects'][$projectId]['totalRealized'] += $activity['RealizedHours'] ?? 0;

    // Update WBSO totals
    $wbsoGroups[$wbsoId]['totalActivityBudget'] += $activity['ActivityBudget'] ?? 0;
    $wbsoGroups[$wbsoId]['totalPlanned'] += $activity['PlannedHours'] ?? 0;
    $wbsoGroups[$wbsoId]['totalRealized'] += $activity['RealizedHours'] ?? 0;
}

// Calculate grand totals
$grandTotalWbsoBudget = 0;
$grandTotalActivityBudget = 0;
$grandTotalPlanned = 0;
$grandTotalRealized = 0;
foreach ($wbsoGroups as $wbso) {
    $grandTotalWbsoBudget += $wbso['wbsoBudget'] ?? 0;
    $grandTotalActivityBudget += $wbso['totalActivityBudget'] ?? 0;
    $grandTotalPlanned += $wbso['totalPlanned'] ?? 0;
    $grandTotalRealized += $wbso['totalRealized'] ?? 0;
}

// Project status labels
$statusLabels = [
    1 => 'Lead',
    2 => 'Quote',
    3 => 'Active',
    4 => 'Closed'
];
?>

<section class="white" id="wbso-details">
    <div class="container">
        <h1>WBSO Activity Details - <?= $selectedYear ?></h1>

        <!-- Grand Totals Summary -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Overall Totals</h5>
                <div class="row">
                    <div class="col-md-2">
                        <strong>Total Activities:</strong> <?= count($activities) ?>
                    </div>
                    <div class="col-md-2">
                        <strong>WBSO Budget:</strong> <?= number_form($grandTotalWbsoBudget) ?> hrs
                    </div>
                    <div class="col-md-3">
                        <strong>Project Budget:</strong> <?= number_form($grandTotalActivityBudget) ?> hrs
                    </div>
                    <div class="col-md-2">
                        <strong>Planned:</strong> <?= number_form($grandTotalPlanned) ?> hrs
                    </div>
                    <div class="col-md-3">
                        <strong>Realized:</strong> <?= number_form($grandTotalRealized) ?> hrs
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($wbsoGroups)): ?>
            <div class="alert alert-info">
                No WBSO activities found for <?= $selectedYear ?>
            </div>
        <?php else: ?>
            <!-- WBSO Groups -->
            <?php foreach ($wbsoGroups as $wbsoId => $wbso): ?>
                <div class="card mb-4"><div class="card-body">
                <div class="">
                    <h4 class="border-bottom pb-2 mb-3">
                        <?= htmlspecialchars($wbso['name']) ?>
                        <?php if ($wbso['description']): ?>
                            <small class="text-muted"> - <?= htmlspecialchars($wbso['description']) ?></small>
                        <?php endif; ?>
                    </h4>

                    <?php if (empty($wbso['projects'])): ?>
                        <!-- No activities for this WBSO item -->
                        <div class="alert border">
                            <strong>No Activities Assigned</strong><br>
                            This WBSO item has a budget of <?= number_form($wbso['wbsoBudget']) ?> hrs but no activities have been assigned to it yet.
                        </div>
                    <?php else: ?>
                        <!-- Projects within this WBSO -->
                        <?php foreach ($wbso['projects'] as $projectId => $project): ?>
                        <div class="c">
                            <h5 class="mb-2">
                                <?= htmlspecialchars($project['name']) ?>
                                <span class="badge badge-secondary ml-2"><?= $statusLabels[$project['status']] ?? 'Unknown' ?></span>
                                <small class="text-muted">
                                    - Project Budget: <?= number_form($project['totalActivityBudget']) ?> hrs |
                                    Planned: <?= number_form($project['totalPlanned']) ?> hrs |
                                    Realized: <?= number_form($project['totalRealized']) ?> hrs
                                </small>
                            </h5>
                            <table class="table table-bordered table-hover">
                                <thead class="thead-light">
                                            <tr>
                                                <th>Task Code</th>
                                                <th>Activity Name</th>
                                                <th class="text-right">Project Budget</th>
                                                <th class="text-right">Planned</th>
                                                <th class="text-right">Realized</th>
                                                <th class="text-right">Remaining</th>
                                                <th>Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($project['activities'] as $activity):
                                                $taskCode = $projectId . '-' . str_pad($activity['ActivityKey'], 3, '0', STR_PAD_LEFT);
                                                $remaining = max(0, $activity['ActivityBudget'] - $activity['RealizedHours']);
                                                $percentage = $activity['ActivityBudget'] > 0 ? round(($activity['RealizedHours'] / $activity['ActivityBudget']) * 100, 1) : 0;
                                                $progressClass = $percentage > 100 ? 'bg-danger' : ($percentage >= 80 ? 'bg-warning' : 'bg-success');
                                                $overbudgetClass = $activity['RealizedHours'] > $activity['ActivityBudget'] ? 'overbudget' : '';
                                            ?>
                                            <tr>
                                                <td><strong><?= $taskCode ?></strong></td>
                                                <td><?= htmlspecialchars($activity['ActivityName']) ?></td>
                                                <td class="text-right"><?= number_form($activity['ActivityBudget']) ?></td>
                                                <td class="text-right"><?= number_form($activity['PlannedHours']) ?></td>
                                                <td class="text-right <?= $overbudgetClass ?>"><?= number_form($activity['RealizedHours']) ?></td>
                                                <td class="text-right"><?= number_form($remaining) ?></td>
                                                <td style="min-width: 150px;">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?= $progressClass ?>"
                                                             role="progressbar"
                                                             style="width: <?= min(100, $percentage) ?>%">
                                                            <?= $percentage ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <!-- Project Totals Row -->
                                            <tr class="table-info font-weight-bold">
                                                <td colspan="2" class="text-right"><strong>Project Total:</strong></td>
                                                <td class="text-right"><?= number_form($project['totalActivityBudget']) ?></td>
                                                <td class="text-right"><?= number_form($project['totalPlanned']) ?></td>
                                                <td class="text-right <?= $project['totalRealized'] > $project['totalActivityBudget'] ? 'overbudget' : '' ?>">
                                                    <?= number_form($project['totalRealized']) ?>
                                                </td>
                                                <td class="text-right"><?= number_form(max(0, $project['totalActivityBudget'] - $project['totalRealized'])) ?></td>
                                                <td>
                                                    <?php
                                                        $projectPercentage = $project['totalActivityBudget'] > 0 ? round(($project['totalRealized'] / $project['totalActivityBudget']) * 100, 1) : 0;
                                                        $projectProgressClass = $projectPercentage > 100 ? 'bg-danger' : ($projectPercentage >= 80 ? 'bg-warning' : 'bg-success');
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?= $projectProgressClass ?>"
                                                             role="progressbar"
                                                             style="width: <?= min(100, $projectPercentage) ?>%">
                                                            <?= $projectPercentage ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                        </div>
                        <?php endforeach; ?>

                        <!-- WBSO Item Totals -->
                        <?php if (!empty($wbso['projects'])): ?>
                        <div class="alert border mb-0">
                            <div class="row font-weight-bold">
                                <div class="col-md-2">
                                    <strong>WBSO Item Total:</strong>
                                </div>
                                <div class="col-md-2">
                                    WBSO Budget: <?= number_form($wbso['wbsoBudget']) ?> hrs
                                </div>
                                <div class="col-md-2">
                                    Project Budget: <?= number_form($wbso['totalActivityBudget']) ?> hrs
                                </div>
                                <div class="col-md-2">
                                    Planned: <?= number_form($wbso['totalPlanned']) ?> hrs
                                </div>
                                <div class="col-md-2">
                                    Realized: <?= number_form($wbso['totalRealized']) ?> hrs
                                </div>
                                <div class="col-md-2">
                                    <?php
                                        $wbsoPercentage = $wbso['totalActivityBudget'] > 0 ? round(($wbso['totalRealized'] / $wbso['totalActivityBudget']) * 100, 1) : 0;
                                        $wbsoProgressClass = $wbsoPercentage > 100 ? 'bg-danger' : ($wbsoPercentage >= 80 ? 'bg-warning' : 'bg-success');
                                    ?>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar <?= $wbsoProgressClass ?>"
                                             role="progressbar"
                                             style="width: <?= min(100, $wbsoPercentage) ?>%">
                                            <?= $wbsoPercentage ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                </div></div></div>
            <?php endforeach; ?>

            <!-- Grand Totals Row -->
            <div class="border-top pt-4 mt-4">
                <h4 class="mb-3">Grand Totals</h4>
                <div class="alert alert-secondary">
                    <div class="row font-weight-bold">
                        <div class="col-md-2">
                            <strong>WBSO Budget:</strong><br>
                            <?= number_form($grandTotalWbsoBudget) ?> hrs
                        </div>
                        <div class="col-md-2">
                            <strong>Project Budget:</strong><br>
                            <?= number_form($grandTotalActivityBudget) ?> hrs
                        </div>
                        <div class="col-md-2">
                            <strong>Planned:</strong><br>
                            <?= number_form($grandTotalPlanned) ?> hrs
                        </div>
                        <div class="col-md-2">
                            <strong>Realized:</strong><br>
                            <?= number_form($grandTotalRealized) ?> hrs
                        </div>
                        <div class="col-md-2">
                            <strong>Remaining:</strong><br>
                            <?= number_form(max(0, $grandTotalActivityBudget - $grandTotalRealized)) ?> hrs
                        </div>
                        <div class="col-md-2">
                            <strong>Progress:</strong>
                            <?php
                                $grandPercentage = $grandTotalActivityBudget > 0 ? round(($grandTotalRealized / $grandTotalActivityBudget) * 100, 1) : 0;
                                $grandProgressClass = $grandPercentage > 100 ? 'bg-danger' : ($grandPercentage >= 80 ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar <?= $grandProgressClass ?>"
                                     role="progressbar"
                                     style="width: <?= min(100, $grandPercentage) ?>%">
                                    <?= $grandPercentage ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
