<?php
require 'includes/auth.php';
require 'includes/db.php';

// Get JSON data from POST request
$data = json_decode(file_get_contents('php://input'), true);

// Add debug logging
error_log("Received priority data: " . print_r($data, true));

// Validate data is an array
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data format. Expected array.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $updateCount = 0;
    $errors = [];

    // Prepare statement outside loop for better performance
    $stmt = $pdo->prepare("
        UPDATE Hours 
        SET Prio = :priority
        WHERE Person = :personId 
        AND Project = :projectId 
        AND Activity = :activityId
    ");

    foreach ($data as $item) {
        // Validate required fields for each item
        if (
            !isset($item['projectId']) || 
            !isset($item['activityId']) || 
            !isset($item['personId']) || 
            !isset($item['priority'])
        ) {
            $errors[] = "Missing required fields in item";
            continue;
        }

        // Execute update
        $params = [
            ':priority' => $item['priority'],
            ':personId' => $item['personId'],
            ':projectId' => $item['projectId'],
            ':activityId' => $item['activityId']
        ];
        
        error_log("Updating priority with params: " . print_r($params, true));
        
        $stmt->execute($params);
        $updateCount += $stmt->rowCount();
    }

    // Commit if no errors, otherwise rollback
    if (empty($errors)) {
        $pdo->commit();
        $response = [
            'success' => true,
            'message' => "Updated priorities for $updateCount tasks"
        ];
        echo json_encode($response);
    } else {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'error' => 'Some updates failed',
            'details' => $errors
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in priority update: " . $e->getMessage());
    // Ensure transaction is rolled back
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>