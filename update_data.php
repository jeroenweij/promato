<?php
/**
 * Data Sync - OpenProject & Yoobi Integration
 *
 * Syncs sprint data from OpenProject (estimated hours) and Yoobi (logged hours)
 * for all active and closed projects in the selected year.
 * Uses AJAX for real-time progress updates.
 */

$pageSpecificCSS = [];
require 'includes/header.php';
require_once 'includes/openproject_api.php';
require_once 'includes/yoobi_api.php';

$openProjectConfigured = defined('OPENPROJECT_URL') && defined('OPENPROJECT_API_KEY')
    && OPENPROJECT_URL !== 'https://your-openproject-instance.com';

$yoobiApi = new YoobiAPI();
$yoobiConfigured = $yoobiApi->isConfigured();

// Get all active and closed projects that have activities in the selected year
// Exclude projects with ExcludeSync = 1
$stmt = $pdo->prepare("
    SELECT DISTINCT p.Id, p.Name, p.Status, p.OpenProjectId, p.ExcludeSync, s.Status AS StatusName
    FROM Projects p
    LEFT JOIN Status s ON p.Status = s.Id
    WHERE p.Status IN (3, 4)
    AND p.ExcludeSync = 0
    AND EXISTS (
        SELECT 1 FROM Activities a
        WHERE a.Project = p.Id
        AND YEAR(a.StartDate) <= :year
        AND YEAR(a.EndDate) >= :year
    )
    ORDER BY p.Id, p.Name
");
$stmt->execute([':year' => $selectedYear]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent sync history
$historyStmt = $pdo->prepare("
    SELECT sl.*, p.Name AS UserName
    FROM SyncLog sl
    LEFT JOIN Personel p ON sl.UserId = p.Id
    ORDER BY sl.SyncTime DESC
    LIMIT 20
");
$historyStmt->execute();
$syncHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="white">
    <div class="container-fluid">
        <!-- Projects to Sync -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Projects to Sync (<?= count($projects) ?>)</h5>
                <?php if ($openProjectConfigured && count($projects) > 0): ?>
                <button type="button" id="syncBtn" class="btn btn-primary" onclick="startSync()">
                    <i data-lucide="refresh-cw" class="mr-2"></i>
                    Start Sync
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($projects)): ?>
                    <p class="p-3 mb-0 text-muted">No projects to sync for <?= $selectedYear ?></p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>OpenProject ID</th>
                            <th style="width: 120px;">Sync Status</th>
                        </tr>
                    </thead>
                    <tbody id="projectsTable">
                        <?php foreach ($projects as $project): ?>
                        <tr id="project-row-<?= $project['Id'] ?>">
                            <td><?= $project['Id'] ?></td>
                            <td><?= htmlspecialchars($project['Name']) ?></td>
                            <td><?= htmlspecialchars($project['StatusName']) ?></td>
                            <td>
                                <span id="opid-<?= $project['Id'] ?>"><?= htmlspecialchars($project['OpenProjectId'] ?? '-') ?></span>
                            </td>
                            <td>
                                <span id="status-<?= $project['Id'] ?>" class="badge badge-secondary">Pending</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <!-- Progress Bar -->
            <div class="card-footer" id="progressSection" style="display: none;">
                <div class="d-flex align-items-center mb-2">
                    <span id="progressText">Syncing...</span>
                    <span class="ml-auto" id="progressCount">0 / <?= count($projects) ?></span>
                </div>
                <div class="progress">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- Sync Log Output -->
        <div class="card mb-4" id="logCard" style="display: none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sync Log</h5>
                <span id="logStatus" class="badge badge-info">In Progress</span>
            </div>
            <div class="card-body p-0">
                <div id="syncLog" class="sync-log" style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px;">
                    <table class="table table-sm table-striped mb-0">
                        <tbody id="logBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer" id="logSummary" style="display: none;">
                <strong>Summary:</strong>
                <span id="summaryText"></span>
            </div>
        </div>

        <!-- Sync History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Sync History</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($syncHistory)): ?>
                    <p class="p-3 mb-0 text-muted">No sync history yet</p>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Projects Matched</th>
                            <th>Failed</th>
                            <th>Sprints</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($syncHistory as $history): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i', strtotime($history['SyncTime'])) ?></td>
                            <td><?= htmlspecialchars($history['UserName'] ?? 'Unknown') ?></td>
                            <td>
                                <?php if ($history['Success']): ?>
                                    <span class="badge badge-success">Success</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $history['ProjectsMatched'] ?></td>
                            <td><?= $history['ProjectsFailed'] ?></td>
                            <td><?= $history['SprintsSynced'] ?></td>
                            <td><small><?= htmlspecialchars($history['Message'] ?? '') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
const projects = <?= json_encode(array_map(fn($p) => ['id' => $p['Id'], 'name' => $p['Name']], $projects)) ?>;
const selectedYear = <?= $selectedYear ?>;
let syncRunning = false;
let projectsMatched = 0;
let projectsFailed = 0;
let sprintsSynced = 0;

function addLog(type, message, details = null) {
    const logBody = document.getElementById('logBody');
    const time = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    let rowClass = '';
    let badge = '';

    switch (type) {
        case 'error':
            rowClass = 'table-danger';
            badge = '<span class="badge badge-danger">ERROR</span>';
            break;
        case 'success':
            rowClass = 'table-success';
            badge = '<span class="badge badge-success">OK</span>';
            break;
        case 'warning':
            rowClass = 'table-warning';
            badge = '<span class="badge badge-warning">WARN</span>';
            break;
        default:
            badge = '<span class="badge badge-info">INFO</span>';
    }

    let detailsHtml = '';
    if (details) {
        const detailStr = Object.entries(details).map(([k, v]) => `${k}: ${v}`).join(', ');
        detailsHtml = `<small class="text-muted">(${detailStr})</small>`;
    }

    const row = document.createElement('tr');
    row.className = rowClass;
    row.innerHTML = `
        <td style="width: 70px;" class="text-muted">${time}</td>
        <td style="width: 80px;">${badge}</td>
        <td>${escapeHtml(message)} ${detailsHtml}</td>
    `;
    logBody.appendChild(row);

    // Auto-scroll to bottom
    const syncLog = document.getElementById('syncLog');
    syncLog.scrollTop = syncLog.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateProgress(current, total) {
    const percent = Math.round((current / total) * 100);
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressCount').textContent = `${current} / ${total}`;
}

function updateProjectStatus(projectId, status, className) {
    const statusEl = document.getElementById('status-' + projectId);
    if (statusEl) {
        statusEl.textContent = status;
        statusEl.className = 'badge badge-' + className;
    }
}

function updateOpenProjectId(projectId, opid) {
    const opidEl = document.getElementById('opid-' + projectId);
    if (opidEl && opid) {
        opidEl.textContent = opid;
    }
}

async function syncProject(project) {
    updateProjectStatus(project.id, 'Syncing...', 'info');

    const formData = new FormData();
    formData.append('project_id', project.id);
    formData.append('year', selectedYear);

    try {
        const response = await fetch('sync_project.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success && result.matched) {
            projectsMatched++;
            sprintsSynced += result.sprintCount;

            const matchType = result.matchType === 'id' ? 'by ID' : 'by name';
            addLog('success', `[${project.name}] Matched ${matchType}: ${result.openProjectIdentifier}`);

            if (result.sprintCount > 0) {
                addLog('info', `[${project.name}] Synced ${result.sprintCount} sprint(s)`);
                result.sprints.forEach(sprint => {
                    addLog('success', `[${project.name}] Sprint '${sprint.name}': ${sprint.estimatedHours} hours`, {
                        workPackages: sprint.workPackages
                    });
                });
            } else {
                addLog('info', `[${project.name}] No sprints found for ${selectedYear}`);
            }

            updateProjectStatus(project.id, 'Synced', 'success');
            updateOpenProjectId(project.id, result.openProjectIdentifier);

        } else if (result.success && !result.matched) {
            projectsFailed++;
            addLog('error', `[${project.name}] ${result.error || 'No match found'}`);
            updateProjectStatus(project.id, 'No Match', 'danger');

        } else {
            projectsFailed++;
            addLog('error', `[${project.name}] ${result.error || 'Unknown error'}`);
            updateProjectStatus(project.id, 'Error', 'danger');
        }

    } catch (error) {
        projectsFailed++;
        addLog('error', `[${project.name}] Network error: ${error.message}`);
        updateProjectStatus(project.id, 'Error', 'danger');
    }
}

async function startSync() {
    if (syncRunning) return;
    syncRunning = true;

    // Reset counters
    projectsMatched = 0;
    projectsFailed = 0;
    sprintsSynced = 0;

    // Update UI
    document.getElementById('syncBtn').disabled = true;
    document.getElementById('syncBtn').innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Syncing...';
    document.getElementById('logCard').style.display = 'block';
    document.getElementById('progressSection').style.display = 'block';
    document.getElementById('logBody').innerHTML = '';
    document.getElementById('logStatus').className = 'badge badge-info';
    document.getElementById('logStatus').textContent = 'In Progress';
    document.getElementById('logSummary').style.display = 'none';

    addLog('info', `Starting sync for year ${selectedYear}`);
    addLog('info', `Found ${projects.length} project(s) to sync`);

    // Sync each project sequentially
    for (let i = 0; i < projects.length; i++) {
        await syncProject(projects[i]);
        updateProgress(i + 1, projects.length);
    }

    // Complete
    const success = projectsFailed === 0;
    const message = `Sync completed: ${projectsMatched} projects matched, ${projectsFailed} failed, ${sprintsSynced} sprints synced`;
    addLog('info', message);

    // Update UI
    document.getElementById('logStatus').className = success ? 'badge badge-success' : 'badge badge-warning';
    document.getElementById('logStatus').textContent = success ? 'Completed' : 'Completed with errors';
    document.getElementById('progressText').textContent = 'Sync complete';
    document.getElementById('progressBar').className = 'progress-bar ' + (success ? 'bg-success' : 'bg-warning');
    document.getElementById('logSummary').style.display = 'block';
    document.getElementById('summaryText').textContent = `${projectsMatched} matched, ${projectsFailed} failed, ${sprintsSynced} sprints`;
    document.getElementById('syncBtn').disabled = false;
    document.getElementById('syncBtn').innerHTML = '<i data-lucide="refresh-cw" class="mr-2"></i>Start Sync';

    // Re-init lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Save sync log to database
    const logFormData = new FormData();
    logFormData.append('action', 'log');
    logFormData.append('success', success ? '1' : '0');
    logFormData.append('matched', projectsMatched);
    logFormData.append('failed', projectsFailed);
    logFormData.append('sprints', sprintsSynced);
    logFormData.append('message', message);

    fetch('sync_log.php', {
        method: 'POST',
        body: logFormData
    });

    syncRunning = false;
}
</script>

<?php require 'includes/footer.php'; ?>
