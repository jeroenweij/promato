<?php
require 'includes/db.php';

// Get JSON data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['users']) || !is_array($data['users'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit;
}

try {
    // Begin transaction to ensure data integrity
    $pdo->beginTransaction();
    
    // Prepare the update statement
    $stmt = $pdo->prepare("UPDATE Personel SET Department = :department, Ord = :order WHERE Id = :personId");
    
    foreach ($data['users'] as $user) {
        // Validate required fields
        if (!isset($user['personId']) || !isset($user['department']) || !isset($user['order'])) {
            throw new Exception('Missing required fields for user update');
        }
        
        // Execute the update for each user
        $stmt->execute([
            ':personId' => $user['personId'],
            ':department' => $user['department'],
            ':order' => $user['order']
        ]);
    }
    
    // Commit all changes if successful
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'User order and departments updated successfully',
        'updated_count' => count($data['users'])
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
