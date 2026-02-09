<?php
/**
 * AJAX endpoint for syncing a single project with OpenProject
 *
 * Returns JSON with sync results for real-time progress updates
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/openproject_api.php';
require_once 'includes/yoobi_api.php';

header('Content-Type: application/json');

// Get parameters
$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'Missing project_id']);
    exit;
}

// Check OpenProject configuration
$openProjectConfigured = defined('OPENPROJECT_URL') && defined('OPENPROJECT_API_KEY')
    && OPENPROJECT_URL !== 'https://your-openproject-instance.com';

if (!$openProjectConfigured) {
    echo json_encode(['success' => false, 'error' => 'OpenProject not configured']);
    exit;
}

// Get project details
$stmt = $pdo->prepare("SELECT Id, Name, OpenProjectId FROM Projects WHERE Id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    echo json_encode(['success' => false, 'error' => 'Project not found']);
    exit;
}

$result = [
    'success' => true,
    'projectId' => $projectId,
    'projectName' => $project['Name'],
    'matched' => false,
    'matchType' => null,
    'openProjectIdentifier' => null,
    'sprints' => [],
    'sprintCount' => 0,
    'error' => null
];

/**
 * Check if a sprint overlaps with a given year
 */
function sprintOverlapsYear($sprint, $year) {
    $yearStart = "$year-01-01";
    $yearEnd = "$year-12-31";

    $sprintStart = $sprint['startDate'] ?? null;
    $sprintEnd = $sprint['endDate'] ?? null;

    if (!$sprintStart && !$sprintEnd) {
        return true;
    }

    if ($sprintStart && !$sprintEnd) {
        return $sprintStart <= $yearEnd;
    }

    if (!$sprintStart && $sprintEnd) {
        return $sprintEnd >= $yearStart;
    }

    return $sprintStart <= $yearEnd && $sprintEnd >= $yearStart;
}

try {
    $api = new OpenProjectAPI();

    $openProjectMatch = null;
    $openProjectIdentifier = null;

    // Step 1: Try to match on OpenProjectId if set
    if (!empty($project['OpenProjectId'])) {
        $openProjectMatch = $api->getProjectByIdentifier($project['OpenProjectId']);
        if ($openProjectMatch) {
            $openProjectIdentifier = $project['OpenProjectId'];
            $result['matchType'] = 'id';
        }
    }

    // Step 2: If no identifier set or not found, try to match by name
    if (!$openProjectMatch) {
        $openProjectMatch = $api->findProjectByName($project['Name']);

        if ($openProjectMatch) {
            $openProjectIdentifier = $openProjectMatch['identifier'];
            // Auto-update the OpenProjectId in the database
            $updateStmt = $pdo->prepare("UPDATE Projects SET OpenProjectId = ? WHERE Id = ?");
            $updateStmt->execute([$openProjectIdentifier, $projectId]);
            $result['matchType'] = 'name';
        }
    }

    if (!$openProjectMatch) {
        $result['matched'] = false;
        $result['error'] = 'No matching project found in OpenProject';
        echo json_encode($result);
        exit;
    }

    $result['matched'] = true;
    $result['openProjectIdentifier'] = $openProjectIdentifier;

    // Initialize Yoobi API for logged hours
    $yoobiApi = new YoobiAPI();
    $yoobiConfigured = $yoobiApi->isConfigured();

    // Get versions/sprints for this project
    $versions = $api->getVersions($openProjectIdentifier);
    $versionsInYear = array_filter($versions, fn($v) => sprintOverlapsYear($v, $year));

    // Prepare upsert statement
    $upsertStmt = $pdo->prepare("
        INSERT INTO ProjectSprints (VersionId, ProjectId, SprintName, StartDate, EndDate, EstimatedHours, LoggedHours)
        VALUES (:versionId, :projectId, :sprintName, :startDate, :endDate, :estimatedHours, :loggedHours)
        ON DUPLICATE KEY UPDATE
            SprintName = VALUES(SprintName),
            StartDate = VALUES(StartDate),
            EndDate = VALUES(EndDate),
            EstimatedHours = VALUES(EstimatedHours),
            LoggedHours = VALUES(LoggedHours)
    ");

    foreach ($versionsInYear as $version) {
        $versionId = $version['id'];
        $versionName = $version['name'];
        $startDate = $version['startDate'] ?? null;
        $endDate = $version['endDate'] ?? null;

        // Get estimated hours for this version from OpenProject
        $hoursSummary = $api->getVersionHoursSummary($versionId);
        $estimatedHours = (int)round($hoursSummary['estimated'] * 100);

        // Get logged hours from Yoobi for this sprint's date range
        $loggedHours = 0;
        if ($yoobiConfigured && $startDate && $endDate) {
            $loggedHoursFloat = $yoobiApi->getProjectLoggedHours($project['Name'], $startDate, $endDate);
            $loggedHours = (int)round($loggedHoursFloat * 100);
        }

        // Upsert the sprint data
        $upsertStmt->execute([
            ':versionId' => $versionId,
            ':projectId' => $projectId,
            ':sprintName' => $versionName,
            ':startDate' => $startDate,
            ':endDate' => $endDate,
            ':estimatedHours' => $estimatedHours,
            ':loggedHours' => $loggedHours
        ]);

        $result['sprints'][] = [
            'name' => $versionName,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'estimatedHours' => $estimatedHours / 100,
            'loggedHours' => $loggedHours / 100,
            'workPackages' => $hoursSummary['workPackageCount']
        ];
    }

    $result['sprintCount'] = count($result['sprints']);

} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
