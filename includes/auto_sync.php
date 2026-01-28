<?php
/**
 * Auto Sync - Background Yoobi Hours Sync
 *
 * Checks if a scheduled sync should run and triggers it in the background.
 * Include this file in header.php to enable automatic syncing.
 *
 * Scheduled times: 07:00, 13:00
 * Only triggers if Yoobi is configured and no sync has run for that slot today.
 */

// Only run if a user is logged in (so we can record who triggered it)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    return;
}

// Only run if Yoobi is configured
if (!defined('YOOBI_CLIENT_ID') || !defined('YOOBI_CLIENT_SECRET') || YOOBI_CLIENT_ID === 'your-client-id') {
    return;
}

$autoSyncUserId = $_SESSION['user_id'];

// Scheduled sync times (24h format)
$syncSchedule = ['07:00', '13:00'];

// Get current time info
$today = date('Y-m-d');
$currentTime = date('H:i');

// Find which scheduled slot we should check
$slotToRun = null;
foreach ($syncSchedule as $scheduledTime) {
    // Check if current time is past the scheduled time
    if ($currentTime >= $scheduledTime) {
        $slotToRun = $scheduledTime;
    }
}

// No slot to check yet (before first scheduled time)
if ($slotToRun === null) {
    return;
}

// Check if this slot has already run today
$slotKey = $today . '_' . str_replace(':', '', $slotToRun);

// Use project root for logs (not includes directory)
$logDir = dirname(__DIR__) . '/logs/auto_sync';
$cacheFile = $logDir . '/' . $slotKey;

// Ensure logs directory exists
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// If cache file exists, this slot already ran today
if (file_exists($cacheFile)) {
    return;
}

// Check database to see if auto sync already ran for this slot today
// (in case cache file was deleted)
global $pdo;
if (isset($pdo)) {
    $slotStart = $today . ' ' . $slotToRun . ':00';
    $slotEnd = $today . ' ' . $slotToRun . ':59';

    // Give a 30-minute window after scheduled time
    $checkStart = date('Y-m-d H:i:s', strtotime($slotStart));
    $checkEnd = date('Y-m-d H:i:s', strtotime($slotStart) + 1800); // 30 min window

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM SyncLog
        WHERE SyncType = 'hours_auto'
        AND SyncTime BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $checkStart, ':end' => $checkEnd]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['cnt'] > 0) {
        // Already ran, create cache file to avoid repeated DB checks
        @touch($cacheFile);
        return;
    }
}

// Create cache file immediately to prevent other requests from triggering
@touch($cacheFile);
if (!file_exists($cacheFile)) {
    return;
}

// Check daily limit (max 3 manual + unlimited auto, but let's cap auto at 3 too)
if (isset($pdo)) {
    $todayStart = $today . ' 00:00:00';
    $todayEnd = $today . ' 23:59:59';

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM SyncLog
        WHERE SyncType = 'hours_auto'
        AND SyncTime BETWEEN :start AND :end
    ");
    $stmt->execute([':start' => $todayStart, ':end' => $todayEnd]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['cnt'] >= 3) {
        return; // Already ran 3 auto syncs today
    }
}

// Trigger background sync
// Build sync URL
// Use AUTO_SYNC_BASE_URL from .env.php if defined, otherwise use localhost
if (defined('AUTO_SYNC_BASE_URL')) {
    $syncUrl = rtrim(AUTO_SYNC_BASE_URL, '/') . '/sync_hours_handler.php';
} else {
    // Default: use 127.0.0.1 with current port for local requests
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $port = $_SERVER['SERVER_PORT'] ?? 80;
    $syncUrl = $scheme . '://127.0.0.1:' . $port . '/sync_hours_handler.php';
}

// Prepare POST data
$postData = http_build_query([
    'year' => date('Y'),
    'auto' => '1',
    'slot' => $slotToRun,
    'user_id' => $autoSyncUserId
]);

// Use fsockopen for async request (non-blocking)
$urlParts = parse_url($syncUrl);
$host = $urlParts['host'];
$port = isset($urlParts['port']) ? $urlParts['port'] : ($urlParts['scheme'] === 'https' ? 443 : 80);
$path = $urlParts['path'] ?? '/sync_hours_handler.php';

$fp = @fsockopen(
    ($urlParts['scheme'] === 'https' ? 'ssl://' : '') . $host,
    $port,
    $errno,
    $errstr,
    5
);

if ($fp) {
    $request = "POST $path HTTP/1.1\r\n";
    $request .= "Host: $host\r\n";
    $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $request .= "Content-Length: " . strlen($postData) . "\r\n";
    $request .= "Connection: close\r\n\r\n";
    $request .= $postData;

    // Send request and immediately close - don't wait for response
    fwrite($fp, $request);
    fclose($fp);
}

// Clean up old cache files (older than 2 days)
$oldFiles = glob($logDir . '/*');
$twoDaysAgo = strtotime('-2 days');
foreach ($oldFiles as $file) {
    if (is_file($file) && filemtime($file) < $twoDaysAgo) {
        @unlink($file);
    }
}
