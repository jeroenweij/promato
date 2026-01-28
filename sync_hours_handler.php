<?php
/**
 * Hours Sync Handler - Yoobi Integration
 *
 * AJAX handler for syncing hours from Yoobi API.
 * Supports streaming output for real-time progress updates.
 * Supports both manual (user-triggered) and auto (scheduled) syncs.
 */

ignore_user_abort(true);

// Check if this is an auto sync request (triggered by auto_sync.php)
$isAutoSync = isset($_POST['auto']) && $_POST['auto'] === '1';

// For auto sync, verify it's a local request (same server)
if ($isAutoSync) {
    $localIps = ['127.0.0.1', '::1', $_SERVER['SERVER_ADDR'] ?? ''];
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($clientIp, $localIps) && $clientIp !== $_SERVER['SERVER_ADDR']) {
        http_response_code(403);
        exit('Auto sync only allowed from local server');
    }

    // Load DB without auth for auto sync
    require_once 'includes/db.php';

    // Get user_id from POST (passed by auto_sync.php from the triggering user's session)
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
} else {
    // Manual sync requires authentication
    require 'includes/auth.php';
    require_once 'includes/db.php';
    $userId = $_SESSION['user_id'] ?? null;
}

require_once 'includes/yoobi_api.php';
require_once 'includes/sync_team_hours.php';

// Handle log file viewing (requires auth)
if (isset($_GET['action']) && $_GET['action'] === 'viewlog' && isset($_GET['file'])) {
    if ($isAutoSync) {
        http_response_code(403);
        exit('Not allowed');
    }
    header('Content-Type: text/plain');
    $filename = basename($_GET['file']); // Sanitize filename
    $logDir = __DIR__ . '/logs/hours_sync';
    $filepath = $logDir . '/' . $filename;

    if (file_exists($filepath) && is_file($filepath)) {
        readfile($filepath);
    } else {
        echo "Log file not found: $filename";
    }
    exit;
}

// Only allow POST for sync
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Determine sync type for logging
$syncType = $isAutoSync ? 'hours_auto' : 'hours';
$syncSlot = $isAutoSync ? ($_POST['slot'] ?? 'unknown') : null;

// Setup streaming output
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no'); // Disable nginx buffering
set_time_limit(0);
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

// Logging setup
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logDir = $logDir . '/hours_sync';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFilename = date('Y-m-d_H-i-s') . ($isAutoSync ? '_auto' : '') . '_sync.log';
$logPath = $logDir . '/' . $logFilename;
$logHandle = fopen($logPath, 'w');

function logmsg($msg, $logHandle = null) {
    $timestamp = date('H:i:s');
    $line = "[$timestamp] $msg";
    echo $line . "\n";
    @ob_flush();
    @flush();

    if ($logHandle) {
        fwrite($logHandle, $line . "\n");
    }
}

function progress($percent) {
    echo "PROGRESS:$percent\n";
    @ob_flush();
    @flush();
}

// Clean up old log files (older than 7 days)
$sevenDaysAgo = strtotime('-7 days');
$oldFiles = glob($logDir . '/*.log');
foreach ($oldFiles as $file) {
    if (filemtime($file) < $sevenDaysAgo) {
        unlink($file);
    }
}

// Validate year parameter
$selectedYear = filter_var($_POST['year'] ?? 0, FILTER_VALIDATE_INT);
if (!$selectedYear || $selectedYear < 2000 || $selectedYear > 2100) {
    logmsg("ERROR: Invalid year parameter", $logHandle);
    fclose($logHandle);
    exit;
}

// Check daily sync limit 

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

if ($todaySyncCount >= 5) {
    logmsg("ERROR: Daily sync limit (5) reached", $logHandle);
    fclose($logHandle);
    exit;
}

// Initialize Yoobi API
$yoobi = new YoobiAPI();
if (!$yoobi->isConfigured()) {
    logmsg("ERROR: Yoobi API is not configured", $logHandle);
    fclose($logHandle);
    exit;
}

$syncModeLabel = $isAutoSync ? "AUTO sync (slot: $syncSlot)" : "Manual sync";
logmsg("Starting Yoobi hours sync for year $selectedYear - $syncModeLabel", $logHandle);
progress(10);

// Step 1: Refresh the insight source
logmsg("Refreshing insight source (declarations)...", $logHandle);
$refreshResult = $yoobi->refreshInsightSource('declarations');
if ($refreshResult === null) {
    logmsg("WARNING: Could not refresh insight source: " . $yoobi->getLastError(), $logHandle);
    logmsg("Continuing with existing data...", $logHandle);
} else {
    logmsg("Insight source refreshed successfully", $logHandle);
}
progress(20);

// Step 2: Fetch the report
logmsg("Fetching hours report from Yoobi...", $logHandle);
$startDate = $selectedYear . '-01-01';
$endDate = $selectedYear . '-12-31';

$reportQuery = [
    'type' => 'select',
    'index' => 'employeeelastic',
    'query' => [
        'aggs' => [
            'by' => [
                'rows' => [
                    ['uniqueName' => 'f_projectcode_f'],
                    ['uniqueName' => 'f_activitycode_f']
                ],
                'cols' => [
                    ['uniqueName' => 'f_employee_f']
                ]
            ],
            'values' => [
                ['func' => 'sum', 'field' => ['uniqueName' => 'f_decltime_f']]
            ]
        ]
    ],
    'querytype' => 'select',
    'page' => 0,
    'requestHeaders' => new stdClass(),
    'periodstart' => $startDate,
    'periodend' => $endDate,
    'lang' => 'nl_NL'
];

$result = $yoobi->getInsightReport($reportQuery);

if ($result === null) {
    logmsg("ERROR: Failed to fetch report: " . $yoobi->getLastError(), $logHandle);

    // Log sync failure
    $stmt = $pdo->prepare("INSERT INTO SyncLog (UserId, Success, SyncType, Message, LogFile, ProjectsMatched)
        VALUES (:userId, 0, :syncType, :message, :logFile, :year)");
    $stmt->execute([
        ':userId' => $userId,
        ':syncType' => $syncType,
        ':message' => 'Failed to fetch report: ' . $yoobi->getLastError(),
        ':logFile' => $logFilename,
        ':year' => $selectedYear
    ]);

    fclose($logHandle);
    exit;
}
progress(30);

// Validate response structure
if (!isset($result['COLUMNS']) || !isset($result['DATA'])) {
    logmsg("ERROR: Invalid response format - missing COLUMNS or DATA", $logHandle);
    fclose($logHandle);
    exit;
}

$columns = $result['COLUMNS'];
$data = $result['DATA'];
$totalRows = count($data);

logmsg("Received $totalRows rows from Yoobi API", $logHandle);
logmsg("Columns: " . implode(', ', $columns), $logHandle);
progress(35);

// Step 3: Build person mapping
logmsg("Building person mapping...", $logHandle);
$stmt = $pdo->query("SELECT Id, Name, Team FROM Personel");
$personMap = [];
$teamMap = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $personMap[strtolower(trim($row['Name']))] = $row['Id'];
    $teamMap[$row['Id']] = $row['Team'];
}
$teamMap[0] = null;

logmsg("Found " . count($personMap) . " persons in database", $logHandle);

// Find column indices in the flat data format
// Expected columns: Projectcode, Activiteitscode, Medewerker, Uren
$colIndices = [];
foreach ($columns as $i => $name) {
    $key = strtolower(trim($name));
    $colIndices[$key] = $i;
}

// Verify required columns exist
$requiredCols = ['projectcode', 'activiteitscode', 'medewerker', 'uren'];
$missingCols = [];
foreach ($requiredCols as $col) {
    if (!isset($colIndices[$col])) {
        $missingCols[] = $col;
    }
}
if (!empty($missingCols)) {
    logmsg("ERROR: Missing required columns: " . implode(', ', $missingCols), $logHandle);
    fclose($logHandle);
    exit;
}

$projectIdx = $colIndices['projectcode'];
$activityIdx = $colIndices['activiteitscode'];
$employeeIdx = $colIndices['medewerker'];
$hoursIdx = $colIndices['uren'];

logmsg("Column mapping: Projectcode=$projectIdx, Activiteitscode=$activityIdx, Medewerker=$employeeIdx, Uren=$hoursIdx", $logHandle);
progress(40);

// Step 4: Clear existing hours for the year
logmsg("Clearing existing hours for year $selectedYear...", $logHandle);
$stmt = $pdo->prepare("UPDATE Hours SET Hours = 0 WHERE `Year` = :year");
$stmt->execute([':year' => $selectedYear]);
$stmt = $pdo->prepare("UPDATE TeamHours SET Hours = 0 WHERE `Year` = :year");
$stmt->execute([':year' => $selectedYear]);
logmsg("Existing hours cleared", $logHandle);
progress(45);

// Step 5: Process and insert the data
// Data format is flat: each row = [Projectcode, Project, Activiteitscode, Activiteit, Medewerker, Uren]
logmsg("Processing and inserting hours...", $logHandle);

$count = 0;
$processedPersons = [];
$processedActivities = [];
$unmatchedEmployees = [];
$teamHoursAccumulator = []; // Accumulate team hours to insert at the end

foreach ($data as $rowIndex => $row) {
    // Extract values from flat row
    $projectCode = $row[$projectIdx] ?? null;
    $activityCode = $row[$activityIdx] ?? null;
    $employeeName = $row[$employeeIdx] ?? null;
    $hoursValue = $row[$hoursIdx] ?? null;

    // Skip rows with missing data
    if ($projectCode === null || $activityCode === null || $employeeName === null || $hoursValue === null) {
        continue;
    }

    // Skip total rows
    $projectLower = strtolower(trim($projectCode));
    if ($projectLower === 'eindtotaal' || $projectLower === 'grand total') {
        continue;
    }

    // Parse project and activity as integers
    $project = (int)$projectCode;
    $activity = (int)$activityCode;

    if ($project <= 0) continue;

    // Look up employee
    $employeeKey = strtolower(trim($employeeName));
    if (!isset($personMap[$employeeKey])) {
        if (!isset($unmatchedEmployees[$employeeKey])) {
            $unmatchedEmployees[$employeeKey] = $employeeName;
        }
        continue;
    }
    $personId = $personMap[$employeeKey];

    // Parse hours (already a number from JSON, but handle string format too)
    $hours = is_numeric($hoursValue) ? floatval($hoursValue) : 0;
    if (is_string($hoursValue)) {
        $val = str_replace('.', '', $hoursValue); // Remove thousands separator
        $val = str_replace(',', '.', $val); // Convert comma to decimal
        $hours = floatval($val);
    }

    if ($hours <= 0) continue;

    // Insert into Hours table
    $stmt = $pdo->prepare("INSERT INTO Hours (Project, Activity, Person, Hours, `Year`)
        VALUES (:project, :activity, :person, :hours, :year)
        ON DUPLICATE KEY UPDATE Hours = :hours");
    $stmt->execute([
        ':project' => $project,
        ':activity' => $activity,
        ':person' => $personId,
        ':hours' => round($hours * 100),
        ':year' => $selectedYear
    ]);

    // Accumulate team hours (will insert/update at end to avoid duplicate key issues)
    $team = $teamMap[$personId] ?? null;
    if ($team !== null) {
        $teamKey = "$project-$activity-$team";
        if (!isset($teamHoursAccumulator[$teamKey])) {
            $teamHoursAccumulator[$teamKey] = [
                'project' => $project,
                'activity' => $activity,
                'team' => $team,
                'hours' => 0
            ];
        }
        $teamHoursAccumulator[$teamKey]['hours'] += round($hours * 100);
    }

    $count++;
    $processedPersons[$personId] = true;
    $processedActivities[$project . '-' . $activity] = true;

    // Update progress every 50 rows
    if ($rowIndex % 50 === 0) {
        $percent = 45 + round(($rowIndex / max(1, $totalRows)) * 35);
        progress($percent);
    }
}

// Log unmatched employees
if (!empty($unmatchedEmployees)) {
    logmsg("WARNING: Unmatched employees (" . count($unmatchedEmployees) . "): " . implode(', ', array_values($unmatchedEmployees)), $logHandle);
}

progress(80);

// Insert accumulated team hours
logmsg("Inserting team hours (" . count($teamHoursAccumulator) . " entries)...", $logHandle);
foreach ($teamHoursAccumulator as $entry) {
    $stmt = $pdo->prepare("INSERT INTO TeamHours (Project, Activity, Team, Hours, `Year`)
        VALUES (:project, :activity, :team, :hours, :year)
        ON DUPLICATE KEY UPDATE Hours = :hours");
    $stmt->execute([
        ':project' => $entry['project'],
        ':activity' => $entry['activity'],
        ':team' => $entry['team'],
        ':hours' => $entry['hours'],
        ':year' => $selectedYear
    ]);
}

progress(85);
logmsg("Imported $count hour entries", $logHandle);
logmsg("Processed " . count($processedPersons) . " unique persons", $logHandle);
logmsg("Processed " . count($processedActivities) . " unique activities", $logHandle);

// Step 6: Update special cases (like in upload_handler.php)
logmsg("Updating special cases...", $logHandle);

// Update national holidays - set Plan = Hours for Project 10, Activity 2
$stmt = $pdo->prepare("UPDATE Hours SET Plan = Hours WHERE Project = :project AND Activity = :activity AND `Year` = :year");
$stmt->execute([
    ':project' => 10,
    ':activity' => 2,
    ':year' => $selectedYear
]);
// Sync team hours for holidays
syncTeamHours($pdo, 10, 2, $selectedYear);

// Update planned leave if leave hours > planned hours
$stmt = $pdo->prepare("UPDATE Hours SET Plan = Hours WHERE Plan < Hours AND Project = :project AND Activity = :activity AND `Year` = :year");
$stmt->execute([
    ':project' => 10,
    ':activity' => 1,
    ':year' => $selectedYear
]);
logmsg("Updated planned hours for holidays and leave", $logHandle);

// Update hours of non-planable team members
$stmt = $pdo->prepare("UPDATE TeamHours AS th JOIN Teams AS t ON th.Team = t.Id SET th.Plan = th.Hours WHERE t.Planable = 0 AND th.`Year` = :year");
$stmt->execute([':year' => $selectedYear]);

$stmt = $pdo->prepare("UPDATE Hours AS h JOIN Personel AS p ON h.Person = p.Id JOIN Teams AS t ON p.Team = t.Id SET h.Plan = h.Hours WHERE t.Planable = 0 AND h.`Year` = :year");
$stmt->execute([':year' => $selectedYear]);
logmsg("Updated hours for non-planable teams", $logHandle);

progress(95);

// Step 7: Log sync result
$message = "Imported $count entries for $selectedYear. " .
    count($processedPersons) . " persons, " .
    count($processedActivities) . " activities.";

$stmt = $pdo->prepare("INSERT INTO SyncLog (UserId, Success, SyncType, ProjectsMatched, ProjectsFailed, SprintsSynced, HoursRecords, Message, LogFile)
    VALUES (:userId, 1, :syncType, :year, :persons, :activities, :records, :message, :logFile)");
$stmt->execute([
    ':userId' => $userId,
    ':syncType' => $syncType,
    ':year' => $selectedYear,
    ':persons' => count($processedPersons),
    ':activities' => count($processedActivities),
    ':records' => $count,
    ':message' => $message,
    ':logFile' => $logFilename
]);

progress(100);
logmsg("Sync completed successfully", $logHandle);
logmsg("Log saved to: $logFilename", $logHandle);

fclose($logHandle);
