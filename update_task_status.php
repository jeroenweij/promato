<?php
require 'includes/auth.php';
require 'includes/db.php';

// Get JSON data from POST request
$data = json_decode(file_get_contents('php://input'), true);

// Add debug logging
error_log("Received data: " . print_r($data, true));

// Validate required fields
if (
    !isset($data['projectId']) || 
    !isset($data['activityId']) || 
    !isset($data['personId']) || 
    !isset($data['status'])
) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Extract data
$projectId = $data['projectId'];
$activityId = $data['activityId'];
$personId = $data['personId'];
$status = $data['status'];

try {
    // Update the status in Hours table
    $stmt = $pdo->prepare("
        UPDATE Hours 
        SET StatusId = :status
        WHERE Person = :personId 
        AND Project = :projectId 
        AND Activity = :activityId
    ");
    
    $params = [
        ':status' => $status,
        ':personId' => $personId,
        ':projectId' => $projectId,
        ':activityId' => $activityId
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
                'activityId' => $activityId,
                'personId' => $personId,
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