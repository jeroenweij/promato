<?php
/**
 * Burndown Chart - OpenProject vs Yoobi Hours Comparison
 *
 * Compares estimated hours from OpenProject versions/sprints
 * with actual hours written in Yoobi (imported via Hours table)
 *
 * Setup required:
 * 1. Add OPENPROJECT_URL and OPENPROJECT_API_KEY to .env.php
 * 2. Map Promato projects to OpenProject projects via the admin section below
 */

$pageSpecificCSS = ['burndown.css'];
require 'includes/header.php';
require_once 'includes/openproject_api.php';

// Check if OpenProject is configured
$openProjectConfigured = defined('OPENPROJECT_URL') && defined('OPENPROJECT_API_KEY');

$selectedProject = isset($_GET['project']) ? (int)$_GET['project'] : null;
$selectedVersion = isset($_GET['version']) ? (int)$_GET['version'] : null;

// Get active projects from Promato
$stmt = $pdo->query("
    SELECT p.Id, p.Name, p.Status, p.OpenProjectId, pe.Name AS ManagerName
    FROM Projects p
    LEFT JOIN Personel pe ON p.Manager = pe.Id
    WHERE p.Status = 3
    ORDER BY p.Name
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$versions = [];
$burndownData = null;
$error = null;
$openProjectMatch = null; // Will hold the matched OpenProject project data
$linkingError = null;

if ($openProjectConfigured && $selectedProject) {
    try {
        $api = new OpenProjectAPI();

        // Find the selected Promato project
        $promatoProject = null;
        foreach ($projects as $p) {
            if ($p['Id'] == $selectedProject) {
                $promatoProject = $p;
                break;
            }
        }

        if ($promatoProject) {
            $openProjectIdentifier = null;

            // Step 1: Try to match on OpenProjectId if set
            if (!empty($promatoProject['OpenProjectId'])) {
                $openProjectMatch = $api->getProjectByIdentifier($promatoProject['OpenProjectId']);
                if ($openProjectMatch) {
                    $openProjectIdentifier = $promatoProject['OpenProjectId'];
                } else {
                    $linkingError = "OpenProject identifier '{$promatoProject['OpenProjectId']}' not found in OpenProject.";
                }
            }

            // Step 2: If no identifier set or not found, try to match by name
            if (!$openProjectMatch) {
                $openProjectMatch = $api->findProjectByName($promatoProject['Name']);

                if ($openProjectMatch) {
                    // Auto-update the OpenProjectId in the database
                    $openProjectIdentifier = $openProjectMatch['identifier'];
                    $updateStmt = $pdo->prepare("UPDATE Projects SET OpenProjectId = :opid WHERE Id = :id");
                    $updateStmt->execute([':opid' => $openProjectIdentifier, ':id' => $selectedProject]);

                    // Update local array too
                    $promatoProject['OpenProjectId'] = $openProjectIdentifier;
                    foreach ($projects as &$p) {
                        if ($p['Id'] == $selectedProject) {
                            $p['OpenProjectId'] = $openProjectIdentifier;
                            break;
                        }
                    }
                    unset($p);
                }
            }

            // Step 3: If still no match, show error
            if (!$openProjectMatch) {
                $linkingError = "Project '{$promatoProject['Name']}' is not linked to OpenProject. No matching identifier or project name found.";
            }

            // Get versions if we have a match
            if ($openProjectIdentifier) {
                $versions = $api->getVersions($openProjectIdentifier);

                if ($selectedVersion) {
                    // Get burndown data for selected version
                    $opData = $api->getVersionHoursSummary($selectedVersion);

                    // Get actual hours from Yoobi (Hours table) for this project
                    // We sum all hours for the project in the selected year
                    $stmt = $pdo->prepare("
                        SELECT
                            COALESCE(SUM(h.Hours), 0) / 100 AS actualHours,
                            COALESCE(SUM(h.Plan), 0) / 100 AS plannedHours
                        FROM Hours h
                        WHERE h.Project = :project AND h.Year = :year
                    ");
                    $stmt->execute([':project' => $selectedProject, ':year' => $selectedYear]);
                    $yoobiData = $stmt->fetch(PDO::FETCH_ASSOC);

                    $burndownData = [
                        'openproject' => $opData,
                        'yoobi' => [
                            'actual' => (float)$yoobiData['actualHours'],
                            'planned' => (float)$yoobiData['plannedHours']
                        ]
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<section class="white">
    <div class="container-fluid">
        <h2>Burndown Dashboard</h2>

        <?php if (!$openProjectConfigured): ?>
            <div class="alert alert-warning">
                <strong>Configuration Required</strong><br>
                Add the following to your <code>.env.php</code> file:
                <pre>define('OPENPROJECT_URL', 'https://your-openproject-instance.com');
define('OPENPROJECT_API_KEY', 'your-api-key-here');</pre>
                <small>Generate an API key in OpenProject: My Account → Access tokens → API</small>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($linkingError): ?>
            <div class="alert alert-warning">
                <strong>Project Not Linked</strong><br>
                <?= htmlspecialchars($linkingError) ?>
                <hr>
                <small>To link manually, set the <code>OpenProjectId</code> field in the Projects table to match the OpenProject project identifier (slug).</small>
            </div>
        <?php endif; ?>

        <!-- Project & Version Selection -->
        <form method="get" class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Project</label>
                <select name="project" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Select Project --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['Id'] ?>" <?= $selectedProject == $p['Id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['Name']) ?>
                            <?= !empty($p['OpenProjectId']) ? ' ✓' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">✓ = linked to OpenProject</small>
            </div>

            <?php if ($selectedProject && !empty($versions)): ?>
            <div class="col-md-4">
                <label class="form-label">Sprint / Version</label>
                <select name="version" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Select Version --</option>
                    <?php foreach ($versions as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $selectedVersion == $v['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['name']) ?>
                            <?php if (!empty($v['startDate']) || !empty($v['endDate'])): ?>
                                (<?= $v['startDate'] ?? '?' ?> - <?= $v['endDate'] ?? '?' ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php elseif ($selectedProject && $openProjectConfigured && $openProjectMatch && empty($versions)): ?>
            <div class="col-md-4">
                <label class="form-label">Sprint / Version</label>
                <p class="text-muted">No versions found in OpenProject for "<?= htmlspecialchars($openProjectMatch['name'] ?? '') ?>"</p>
            </div>
            <?php endif; ?>

            <input type="hidden" name="project" value="<?= $selectedProject ?>">
        </form>

        <?php if ($burndownData): ?>
        <!-- Burndown Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Estimated (OpenProject)</h5>
                        <h2><?= number_form($burndownData['openproject']['estimated'], 1) ?> hrs</h2>
                        <small><?= $burndownData['openproject']['workPackageCount'] ?> work packages</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Remaining (OpenProject)</h5>
                        <h2><?= number_form($burndownData['openproject']['remaining'], 1) ?> hrs</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Spent (OpenProject)</h5>
                        <h2><?= number_form($burndownData['openproject']['spent'], 1) ?> hrs</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Actual (Yoobi)</h5>
                        <h2><?= number_form($burndownData['yoobi']['actual'], 1) ?> hrs</h2>
                        <small>Planned: <?= number_form($burndownData['yoobi']['planned'], 1) ?> hrs</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Hours Comparison</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th class="text-right">OpenProject</th>
                            <th class="text-right">Yoobi</th>
                            <th class="text-right">Difference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Estimated vs Planned</td>
                            <td class="text-right"><?= number_form($burndownData['openproject']['estimated'], 1) ?></td>
                            <td class="text-right"><?= number_form($burndownData['yoobi']['planned'], 1) ?></td>
                            <?php $diff = $burndownData['openproject']['estimated'] - $burndownData['yoobi']['planned']; ?>
                            <td class="text-right <?= $diff > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $diff > 0 ? '+' : '' ?><?= number_form($diff, 1) ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Spent vs Actual</td>
                            <td class="text-right"><?= number_form($burndownData['openproject']['spent'], 1) ?></td>
                            <td class="text-right"><?= number_form($burndownData['yoobi']['actual'], 1) ?></td>
                            <?php $diff = $burndownData['openproject']['spent'] - $burndownData['yoobi']['actual']; ?>
                            <td class="text-right <?= abs($diff) > 1 ? 'text-warning' : '' ?>">
                                <?= $diff > 0 ? '+' : '' ?><?= number_form($diff, 1) ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Remaining Work</td>
                            <td class="text-right"><?= number_form($burndownData['openproject']['remaining'], 1) ?></td>
                            <td class="text-right">
                                <?= number_form(max(0, $burndownData['yoobi']['planned'] - $burndownData['yoobi']['actual']), 1) ?>
                            </td>
                            <td class="text-right">-</td>
                        </tr>
                        <tr class="table-info">
                            <td><strong>Progress</strong></td>
                            <td class="text-right">
                                <?php
                                $opProgress = $burndownData['openproject']['estimated'] > 0
                                    ? round(($burndownData['openproject']['spent'] / $burndownData['openproject']['estimated']) * 100)
                                    : 0;
                                ?>
                                <?= $opProgress ?>%
                            </td>
                            <td class="text-right">
                                <?php
                                $yoobiProgress = $burndownData['yoobi']['planned'] > 0
                                    ? round(($burndownData['yoobi']['actual'] / $burndownData['yoobi']['planned']) * 100)
                                    : 0;
                                ?>
                                <?= $yoobiProgress ?>%
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Burndown Chart Canvas -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Burndown Chart</h5>
            </div>
            <div class="card-body">
                <canvas id="burndownChart" height="100"></canvas>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        const ctx = document.getElementById('burndownChart').getContext('2d');
        const estimated = <?= $burndownData['openproject']['estimated'] ?>;
        const remaining = <?= $burndownData['openproject']['remaining'] ?>;
        const spent = <?= $burndownData['openproject']['spent'] ?>;
        const actual = <?= $burndownData['yoobi']['actual'] ?>;

        // Simple burndown visualization
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Estimated', 'Remaining', 'Spent (OP)', 'Actual (Yoobi)'],
                datasets: [{
                    label: 'Hours',
                    data: [estimated, remaining, spent, actual],
                    backgroundColor: [
                        'rgba(0, 123, 255, 0.7)',
                        'rgba(23, 162, 184, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)'
                    ],
                    borderColor: [
                        'rgba(0, 123, 255, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)'
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

        <?php elseif ($selectedProject && !$selectedVersion && !empty($versions)): ?>
        <!-- Show version overview when project selected but no version -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Versions Overview for Project</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($versions as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['name']) ?></td>
                            <td><?= $v['startDate'] ?? '-' ?></td>
                            <td><?= $v['endDate'] ?? '-' ?></td>
                            <td><?= $v['status'] ?? '-' ?></td>
                            <td>
                                <a href="?project=<?= $selectedProject ?>&version=<?= $v['id'] ?>" class="btn btn-sm btn-primary">
                                    View Burndown
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            Select a project to view sprint burndown data.
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
