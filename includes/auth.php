<?php
session_start();
require_once 'pages.php';

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$userAuthLevel = $_SESSION['auth_level'] ?? 0;

if (!isset($pages[$currentPage])) {
    die("Page \"$currentPage\" not configured.");
}

if ($userAuthLevel < $pages[$currentPage]['auth_level']) {
    header("Location: login.php");
    exit;
}
?>

