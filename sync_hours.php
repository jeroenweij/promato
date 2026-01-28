<?php
/**
 * Hours Sync - Yoobi Integration
 *
 * Syncs logged hours from Yoobi API into the Hours and TeamHours tables.
 * Uses AJAX for real-time progress updates.
 * Admin-only page with max 3 syncs per day.
 */

$pageSpecificCSS = [];
require 'includes/header.php';
require_once 'includes/yoobi_api.php';

$yoobiApi = new YoobiAPI();
$yoobiConfigured = $yoobiApi->isConfigured();

// Get today's sync count for hours sync
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');
$syncCountStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM SyncLog
    WHERE SyncType = 'hours'
    AND SyncTime BETWEEN :start AND :end
");
$syncCountStmt->execute([':start' => $todayStart, ':end' => $todayEnd]);
$todaySyncCount = (int)$syncCountStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
$maxSyncsPerDay = 3;
$canSync = $todaySyncCount < $maxSyncsPerDay;

// Get recent sync history for hours sync (both manual and auto)
$historyStmt = $pdo->prepare("
    SELECT sl.*, p.Name AS UserName
    FROM SyncLog sl
    LEFT JOIN Personel p ON sl.UserId = p.Id
    WHERE sl.SyncType IN ('hours', 'hours_auto')
    ORDER BY sl.SyncTime DESC
    LIMIT 20
");
$historyStmt->execute();
$syncHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Get log files
$logDir = __DIR__ . '/logs/hours_sync';
$logFiles = [];
if (is_dir($logDir)) {
    $files = glob($logDir . '/*.log');
    foreach ($files as $file) {
        $mtime = filemtime($file);
        $logFiles[] = [
            'name' => basename($file),
            'path' => $file,
            'date' => date('Y-m-d H:i:s', $mtime),
            'size' => filesize($file)
        ];
    }
    // Sort by date descending
    usort($logFiles, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
}
?>

<section class="white">
    <div class="container-fluid">
        <!-- Status Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Yoobi Hours Sync</h5>
                <div>
                    <span class="badge badge-<?= $canSync ? 'info' : 'warning' ?> mr-2">
                        <?= $todaySyncCount ?> / <?= $maxSyncsPerDay ?> syncs today
                    </span>
                    <?php if ($yoobiConfigured && $canSync): ?>
                    <button type="button" id="syncBtn" class="btn btn-primary" onclick="startSync()">
                        <i data-lucide="refresh-cw" class="mr-2"></i>
                        Start Sync
                    </button>
                    <?php elseif (!$yoobiConfigured): ?>
                    <button type="button" class="btn btn-secondary" disabled>
                        <i data-lucide="alert-circle" class="mr-2"></i>
                        Yoobi Not Configured
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-warning" disabled>
                        <i data-lucide="alert-triangle" class="mr-2"></i>
                        Daily Limit Reached
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Yoobi API:</strong>
                            <?php if ($yoobiConfigured): ?>
                                <span class="badge badge-success">Configured</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Not Configured</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Selected Year:</strong> <?= $selectedYear ?></p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Date Range:</strong> <?= $selectedYear ?>-01-01 to <?= $selectedYear ?>-12-31</p>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <p class="mb-1 text-muted">
                            <i data-lucide="clock" style="width:14px;height:14px;"></i>
                            <strong>Auto Sync Schedule:</strong> 07:00, 13:00 (runs automatically when a user visits a page)
                        </p>
                    </div>
                </div>
                <?php if (!$yoobiConfigured): ?>
                <div class="alert alert-warning mb-0 mt-3">
                    <strong>Configuration Required:</strong> Add YOOBI_CLIENT_ID, YOOBI_CLIENT_SECRET, and YOOBI_API_URL to your .env.php file.
                </div>
                <?php endif; ?>
            </div>
            <!-- Progress Bar -->
            <div class="card-footer" id="progressSection" style="display: none;">
                <div class="d-flex align-items-center mb-2">
                    <span id="progressText">Syncing...</span>
                    <span class="ml-auto" id="progressPercent">0%</span>
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
                <div id="syncLog" class="sync-log" style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; background: #f8f9fa;">
                    <pre id="logOutput" class="p-3 mb-0" style="white-space: pre-wrap;"></pre>
                </div>
            </div>
            <div class="card-footer" id="logSummary" style="display: none;">
                <strong>Summary:</strong>
                <span id="summaryText"></span>
            </div>
        </div>

        <!-- Sync History -->
        <div class="card mb-4">
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
                            <th>Type</th>
                            <th>User</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Records</th>
                            <th>Persons</th>
                            <th>Activities</th>
                            <th>Log</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($syncHistory as $history):
                            $isAuto = ($history['SyncType'] ?? '') === 'hours_auto';
                        ?>
                        <tr>
                            <td><?= date('Y-m-d H:i', strtotime($history['SyncTime'])) ?></td>
                            <td>
                                <?php if ($isAuto): ?>
                                    <span class="badge badge-info">Auto</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($history['UserName'] ?? ($isAuto ? 'System' : 'Unknown')) ?></td>
                            <td><?= $history['ProjectsMatched'] ?? '-' ?></td>
                            <td>
                                <?php if ($history['Success']): ?>
                                    <span class="badge badge-success">Success</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $history['HoursRecords'] ?? 0 ?></td>
                            <td><?= $history['ProjectsFailed'] ?? 0 ?></td>
                            <td><?= $history['SprintsSynced'] ?? 0 ?></td>
                            <td>
                                <?php if (!empty($history['LogFile'])): ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewLog('<?= htmlspecialchars($history['LogFile']) ?>')">
                                    <i data-lucide="file-text"></i>
                                </button>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log Files -->
        <?php if (!empty($logFiles)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Log Files (Last 7 Days)</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Date</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logFiles as $log): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($log['name']) ?></code></td>
                            <td><?= $log['date'] ?></td>
                            <td><?= number_format($log['size'] / 1024, 1) ?> KB</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewLog('<?= htmlspecialchars($log['name']) ?>')">
                                    View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Log Viewer Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logModalTitle">Log File</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="logModalContent" class="bg-light p-3" style="max-height: 500px; overflow: auto; font-size: 12px;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
const selectedYear = <?= $selectedYear ?>;
let syncRunning = false;

function addLogLine(text) {
    const logOutput = document.getElementById('logOutput');
    logOutput.textContent += text + '\n';

    // Auto-scroll to bottom
    const syncLog = document.getElementById('syncLog');
    syncLog.scrollTop = syncLog.scrollHeight;
}

function updateProgress(percent, text) {
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
    if (text) {
        document.getElementById('progressText').textContent = text;
    }
}

async function startSync() {
    if (syncRunning) return;
    syncRunning = true;

    // Update UI
    document.getElementById('syncBtn').disabled = true;
    document.getElementById('syncBtn').innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Syncing...';
    document.getElementById('logCard').style.display = 'block';
    document.getElementById('progressSection').style.display = 'block';
    document.getElementById('logOutput').textContent = '';
    document.getElementById('logStatus').className = 'badge badge-info';
    document.getElementById('logStatus').textContent = 'In Progress';
    document.getElementById('logSummary').style.display = 'none';

    addLogLine('Starting Yoobi hours sync for year ' + selectedYear + '...');
    updateProgress(5, 'Initializing...');

    try {
        const formData = new FormData();
        formData.append('year', selectedYear);
        formData.append('action', 'sync');

        const response = await fetch('sync_hours_handler.php', {
            method: 'POST',
            body: formData
        });

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            // Process complete lines
            const lines = buffer.split('\n');
            buffer = lines.pop(); // Keep incomplete line in buffer

            for (const line of lines) {
                if (line.trim()) {
                    // Check for progress updates
                    if (line.startsWith('PROGRESS:')) {
                        const percent = parseInt(line.substring(9));
                        updateProgress(percent, 'Processing...');
                    } else {
                        addLogLine(line);
                    }
                }
            }
        }

        // Process any remaining buffer
        if (buffer.trim()) {
            addLogLine(buffer);
        }

        // Update UI for completion
        const logContent = document.getElementById('logOutput').textContent;
        const success = !logContent.includes('ERROR') && !logContent.includes('Failed');

        document.getElementById('logStatus').className = success ? 'badge badge-success' : 'badge badge-danger';
        document.getElementById('logStatus').textContent = success ? 'Completed' : 'Failed';
        document.getElementById('progressBar').className = 'progress-bar ' + (success ? 'bg-success' : 'bg-danger');
        document.getElementById('progressText').textContent = 'Sync complete';
        updateProgress(100);

        // Extract summary from log
        const summaryMatch = logContent.match(/Imported (\d+) entries/);
        if (summaryMatch) {
            document.getElementById('summaryText').textContent = summaryMatch[0];
            document.getElementById('logSummary').style.display = 'block';
        }

    } catch (error) {
        addLogLine('ERROR: Network error - ' + error.message);
        document.getElementById('logStatus').className = 'badge badge-danger';
        document.getElementById('logStatus').textContent = 'Error';
        document.getElementById('progressBar').className = 'progress-bar bg-danger';
    }

    document.getElementById('syncBtn').disabled = false;
    document.getElementById('syncBtn').innerHTML = '<i data-lucide="refresh-cw" class="mr-2"></i>Start Sync';

    // Re-init lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    syncRunning = false;
}

async function viewLog(filename) {
    document.getElementById('logModalTitle').textContent = filename;
    document.getElementById('logModalContent').textContent = 'Loading...';
    $('#logModal').modal('show');

    try {
        const response = await fetch('sync_hours_handler.php?action=viewlog&file=' + encodeURIComponent(filename));
        const content = await response.text();
        document.getElementById('logModalContent').textContent = content;
    } catch (error) {
        document.getElementById('logModalContent').textContent = 'Error loading log file: ' + error.message;
    }
}
</script>

<?php require 'includes/footer.php'; ?>
