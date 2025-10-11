<?php
session_start();
require_once 'db.php';

/**
 * Check if user has access to a page
 * User has access if:
 * 1. Their auth level is sufficient, OR
 * 2. They have explicit access via PageAccess table
 */

// Get current page path and info
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$userAuthLevel = $_SESSION['auth_level'] ?? 1;
$userId = $_SESSION['user_id'] ?? 0;
$pageTitle = 'Unknown Page'; // Default title

// Skip auth check for login page
if ($currentPage === 'login.php') {
    return;
}

// Get page info and check access
$stmt = $pdo->prepare("
    SELECT p.Name, p.Auth, pa.UserId
    FROM Pages p
    LEFT JOIN PageAccess pa ON pa.PageId = p.Id AND pa.UserId = :userId
    WHERE p.Path = :path
    LIMIT 1
");
$stmt->execute([
    ':userId' => $userId,
    ':path' => $currentPage
]);

$pageInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// Page not found in database
if (!$pageInfo) {
    die("Page \"$currentPage\" not found in database.");
}

// Check if user has access via auth level OR explicit PageAccess
$hasAuthAccess = $userAuthLevel >= $pageInfo['Auth'];
$hasExplicitAccess = !is_null($pageInfo['UserId']);

if (!$hasAuthAccess && !$hasExplicitAccess) {
    // Store the page they tried to access for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit;
}
?>