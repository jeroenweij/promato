<?php
require 'includes/auth.php';
require_once 'includes/db.php';

// Get JSON data from POST request
$data = json_decode(file_get_contents('php://input'), true);

// Add debug logging
error_log("Received data: " . print_r($data, true));

// Validate required fields
if (
    !isset($data['projectId']) || 
    !isset($data['teamId']) || 
    !isset($data['status'])
) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Extract data
$projectId = $data['projectId'];
$teamId = $data['teamId'];
$status = $data['status'];
$changePrio = $status > 3 ? ', Prio=250' : '';

try {
    // Update the status in Hours table
    $stmt = $pdo->prepare("
        UPDATE TeamHours 
        SET Status = :status $changePrio
        WHERE Team = :teamId 
        AND Project = :projectId 
        AND Year = 2025
    ");
    
    $params = [
        ':status' => $status,
        ':teamId' => $teamId,
        ':projectId' => $projectId
    ];
    
    error_log("Executing query with params: " . print_r($params, true));
    
    $stmt->execute($params);
    
    // Check if update was successful
    if ($stmt->rowCount() > 0) {
        $response = [
            'success' => true,
            'message' => 'Task status updated successfully',
            'data' => [
                'projectId' => $projectId,
                'teamId' => $teamId,
                'status' => $status
            ]
        ];
        error_log("Update successful: " . print_r($response, true));
        echo json_encode($response);
    } else {
        $response = [
            'success' => false,
            'message' => 'No matching task found or status unchanged'
        ];
        error_log("Update unsuccessful: " . print_r($response, true));
        echo json_encode($response);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>