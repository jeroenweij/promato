<?php
/**
 * Burndown Chart - Sprint Hours Overview
 *
 * Shows estimated hours from synced OpenProject sprints
 * compared with actual hours from Yoobi (imported via Hours table)
 *
 * Uses data from ProjectSprints table (synced via sync_sprints.php)
 */

$pageSpecificCSS = ['burndown.css'];
require 'includes/header.php';

$selectedProject = isset($_GET['project']) ? (int)$_GET['project'] : null;
$selectedSprint = isset($_GET['sprint']) ? (int)$_GET['sprint'] : null;

// Get projects with Sync enabled that have sprints
$stmt = $pdo->query("
    SELECT DISTINCT p.Id, p.Name, p.Status, p.OpenProjectId, pe.Name AS ManagerName,
           (SELECT COUNT(*) FROM ProjectSprints ps WHERE ps.ProjectId = p.Id) AS SprintCount
    FROM Projects p
    LEFT JOIN Personel pe ON p.Manager = pe.Id
    WHERE p.Sync = 1
    ORDER BY p.Name
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sprints = [];
$burndownData = null;
$selectedProjectData = null;

// Get sprints for selected project
if ($selectedProject) {
    // Find selected project data
    foreach ($projects as $p) {
        if ($p['Id'] == $selectedProject) {
            $selectedProjectData = $p;
            break;
        }
    }

    // Get sprints from ProjectSprints table (ordered by date for charts)
    $sprintStmt = $pdo->prepare("
        SELECT VersionId, SprintName, StartDate, EndDate,
               EstimatedHours / 100 AS EstimatedHours,
               COALESCE(LoggedHours, 0) / 100 AS LoggedHours
        FROM ProjectSprints
        WHERE ProjectId = :projectId
        ORDER BY COALESCE(StartDate, '9999-12-31') ASC, SprintName ASC
    ");
    $sprintStmt->execute([':projectId' => $selectedProject]);
    $sprints = $sprintStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals and burndown data
    $totalEstimated = array_sum(array_column($sprints, 'EstimatedHours'));
    $totalLogged = array_sum(array_column($sprints, 'LoggedHours'));

    // Calculate cumulative burndown (remaining hours after each sprint)
    $burndownPoints = [];
    $remainingHours = $totalEstimated;
    foreach ($sprints as $s) {
        $remainingHours -= $s['LoggedHours'];
        $burndownPoints[] = [
            'sprint' => $s['SprintName'],
            'remaining' => max(0, $remainingHours),
            'estimated' => $s['EstimatedHours'],
            'logged' => $s['LoggedHours']
        ];
    }

    if ($selectedSprint) {
        // Get sprint data
        $sprintDataStmt = $pdo->prepare("
            SELECT VersionId, SprintName, StartDate, EndDate,
                   EstimatedHours / 100 AS EstimatedHours,
                   COALESCE(LoggedHours, 0) / 100 AS LoggedHours
            FROM ProjectSprints
            WHERE VersionId = :sprintId AND ProjectId = :projectId
        ");
        $sprintDataStmt->execute([':sprintId' => $selectedSprint, ':projectId' => $selectedProject]);
        $sprintData = $sprintDataStmt->fetch(PDO::FETCH_ASSOC);

        if ($sprintData) {
            $burndownData = [
                'sprint' => $sprintData,
                'estimated' => (float)$sprintData['EstimatedHours'],
                'logged' => (float)$sprintData['LoggedHours']
            ];
        }
    }
}
?>

<section class="white">
    <div class="container-fluid">
        <h2>Burndown Dashboard</h2>

        <?php if (empty($projects)): ?>
            <div class="alert alert-warning">
                <strong>No Projects Available</strong><br>
                No projects have sync enabled. Enable sync for projects in
                <a href="projects_edit.php">Project Edit</a> and run
                <a href="sync_sprints.php">Sync sprints</a> first.
            </div>
        <?php endif; ?>

        <!-- Project & Sprint Selection -->
        <form method="get" class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Project</label>
                <select name="project" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Select Project --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['Id'] ?>" <?= $selectedProject == $p['Id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['Name']) ?>
                            (<?= $p['SprintCount'] ?> sprints)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selectedProject && !empty($sprints)): ?>
            <div class="col-md-4">
                <label class="form-label">Sprint</label>
                <select name="sprint" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Select Sprint --</option>
                    <?php foreach ($sprints as $s): ?>
                        <option value="<?= $s['VersionId'] ?>" <?= $selectedSprint == $s['VersionId'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['SprintName']) ?>
                            <?php if ($s['StartDate'] || $s['EndDate']): ?>
                                (<?= $s['StartDate'] ?? '?' ?> - <?= $s['EndDate'] ?? '?' ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php elseif ($selectedProject && empty($sprints)): ?>
            <div class="col-md-4">
                <label class="form-label">Sprint</label>
                <p class="text-muted">No sprints synced for this project. Run <a href="sync_sprints.php">Sync Sprints</a>.</p>
            </div>
            <?php endif; ?>
        </form>

        <?php if ($burndownData):
            $remaining = max(0, $burndownData['estimated'] - $burndownData['logged']);
            $progress = $burndownData['estimated'] > 0
                ? round(($burndownData['logged'] / $burndownData['estimated']) * 100)
                : 0;
        ?>
        <!-- Burndown Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Estimated (OpenProject)</h5>
                        <h2><?= number_form($burndownData['estimated'], 1) ?> hrs</h2>
                        <small>Sprint: <?= htmlspecialchars($burndownData['sprint']['SprintName']) ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Logged (Yoobi)</h5>
                        <h2><?= number_form($burndownData['logged'], 1) ?> hrs</h2>
                        <small>Progress: <?= $progress ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Remaining</h5>
                        <h2><?= number_form($remaining, 1) ?> hrs</h2>
                        <small>Estimated - Logged</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Hours Overview</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th class="text-right">Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Estimated (OpenProject)</td>
                            <td class="text-right"><?= number_form($burndownData['estimated'], 1) ?></td>
                        </tr>
                        <tr>
                            <td>Logged (Yoobi)</td>
                            <td class="text-right"><?= number_form($burndownData['logged'], 1) ?></td>
                        </tr>
                        <tr>
                            <td>Remaining</td>
                            <td class="text-right"><?= number_form($remaining, 1) ?></td>
                        </tr>
                        <tr class="table-info">
                            <td><strong>Progress</strong></td>
                            <td class="text-right"><strong><?= $progress ?>%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Burndown Chart Canvas -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Hours Overview</h5>
            </div>
            <div class="card-body">
                <canvas id="burndownChart" height="100"></canvas>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        const ctx = document.getElementById('burndownChart').getContext('2d');
        const estimated = <?= $burndownData['estimated'] ?>;
        const logged = <?= $burndownData['logged'] ?>;
        const remaining = <?= $remaining ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Estimated', 'Logged', 'Remaining'],
                datasets: [{
                    label: 'Hours',
                    data: [estimated, logged, remaining],
                    backgroundColor: [
                        'rgba(0, 123, 255, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(23, 162, 184, 0.7)'
                    ],
                    borderColor: [
                        'rgba(0, 123, 255, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(23, 162, 184, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        </script>

        <?php elseif ($selectedProject && !$selectedSprint && !empty($sprints)):
            $totalRemaining = max(0, $totalEstimated - $totalLogged);
            $overallProgress = $totalEstimated > 0 ? round(($totalLogged / $totalEstimated) * 100) : 0;
            $estimationAccuracy = $totalEstimated > 0 ? round(($totalLogged / $totalEstimated) * 100) : 0;
        ?>
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Estimated</h5>
                        <h2><?= number_form($totalEstimated, 1) ?> hrs</h2>
                        <small><?= count($sprints) ?> sprints</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Logged</h5>
                        <h2><?= number_form($totalLogged, 1) ?> hrs</h2>
                        <small>Progress: <?= $overallProgress ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Remaining</h5>
                        <h2><?= number_form($totalRemaining, 1) ?> hrs</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card <?= $estimationAccuracy > 100 ? 'bg-danger' : ($estimationAccuracy > 90 ? 'bg-warning' : 'bg-secondary') ?> text-white">
                    <div class="card-body">
                        <h5 class="card-title">Estimation Accuracy</h5>
                        <h2><?= $estimationAccuracy ?>%</h2>
                        <small><?= $estimationAccuracy > 100 ? 'Over budget' : ($estimationAccuracy > 90 ? 'Near budget' : 'Under budget') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Burndown Chart</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="burndownChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Estimation Accuracy per Sprint</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="accuracyChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Burndown Chart - shows remaining hours decreasing over sprints
        const burndownCtx = document.getElementById('burndownChart').getContext('2d');
        const sprintLabels = ['Start', <?= implode(', ', array_map(fn($p) => "'" . addslashes($p['sprint']) . "'", $burndownPoints)) ?>];
        const remainingData = [<?= $totalEstimated ?>, <?= implode(', ', array_column($burndownPoints, 'remaining')) ?>];

        // Ideal burndown line (linear decrease)
        const idealBurndown = [];
        const sprintCount = <?= count($sprints) ?>;
        for (let i = 0; i <= sprintCount; i++) {
            idealBurndown.push(<?= $totalEstimated ?> - (<?= $totalEstimated ?> / sprintCount * i));
        }

        new Chart(burndownCtx, {
            type: 'line',
            data: {
                labels: sprintLabels,
                datasets: [{
                    label: 'Actual Remaining',
                    data: remainingData,
                    borderColor: 'rgba(0, 123, 255, 1)',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.1
                }, {
                    label: 'Ideal Burndown',
                    data: idealBurndown,
                    borderColor: 'rgba(108, 117, 125, 0.5)',
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Hours Remaining' }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Estimation Accuracy Chart - compares estimated vs logged per sprint
        const accuracyCtx = document.getElementById('accuracyChart').getContext('2d');
        const estimatedData = [<?= implode(', ', array_column($burndownPoints, 'estimated')) ?>];
        const loggedData = [<?= implode(', ', array_column($burndownPoints, 'logged')) ?>];
        const sprintNames = [<?= implode(', ', array_map(fn($p) => "'" . addslashes($p['sprint']) . "'", $burndownPoints)) ?>];

        new Chart(accuracyCtx, {
            type: 'bar',
            data: {
                labels: sprintNames,
                datasets: [{
                    label: 'Estimated',
                    data: estimatedData,
                    backgroundColor: 'rgba(0, 123, 255, 0.7)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }, {
                    label: 'Logged',
                    data: loggedData,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Hours' }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
        </script>

        <!-- Sprint Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Sprints for <?= htmlspecialchars($selectedProjectData['Name'] ?? 'Project') ?></h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Sprint</th>
                            <th>Start</th>
                            <th>End</th>
                            <th class="text-right">Estimated</th>
                            <th class="text-right">Logged</th>
                            <th class="text-right">Diff</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sprints as $s):
                            $diff = $s['LoggedHours'] - $s['EstimatedHours'];
                            $diffClass = $diff > 0 ? 'text-danger' : ($diff < 0 ? 'text-success' : '');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($s['SprintName']) ?></td>
                            <td><?= $s['StartDate'] ?? '-' ?></td>
                            <td><?= $s['EndDate'] ?? '-' ?></td>
                            <td class="text-right"><?= number_form($s['EstimatedHours'], 1) ?></td>
                            <td class="text-right"><?= number_form($s['LoggedHours'], 1) ?></td>
                            <td class="text-right <?= $diffClass ?>">
                                <?= $diff > 0 ? '+' : '' ?><?= number_form($diff, 1) ?>
                            </td>
                            <td>
                                <a href="?project=<?= $selectedProject ?>&sprint=<?= $s['VersionId'] ?>" class="btn btn-sm btn-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php
                            $totalDiff = $totalLogged - $totalEstimated;
                            $totalDiffClass = $totalDiff > 0 ? 'text-danger' : ($totalDiff < 0 ? 'text-success' : '');
                        ?>
                        <tr class="table-secondary">
                            <td colspan="3"><strong>Total</strong></td>
                            <td class="text-right"><strong><?= number_form($totalEstimated, 1) ?></strong></td>
                            <td class="text-right"><strong><?= number_form($totalLogged, 1) ?></strong></td>
                            <td class="text-right <?= $totalDiffClass ?>"><strong><?= $totalDiff > 0 ? '+' : '' ?><?= number_form($totalDiff, 1) ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php elseif (!$selectedProject): ?>
        <div class="alert alert-info">
            Select a project to view sprint burndown data.
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
