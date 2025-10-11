<?php
require 'includes/auth.php';

// Get JSON data from POST request
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (
    !isset($data['userId']) || 
    !isset($data['pageId']) || 
    !isset($data['action'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userIdx = (int)$data['userId'];
$pageIdx = (int)$data['pageId'];
$action = $data['action']; // 'grant' or 'revoke'

try {
    if ($action === 'grant') {
        // Insert new access record (ignore if already exists)
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO PageAccess (UserId, PageId) 
            VALUES (:userId, :pageId)
        ");
        $stmt->execute([
            ':userId' => $userIdx,
            ':pageId' => $pageIdx
        ]);
        
        $message = 'Access granted successfully';
        
    } elseif ($action === 'revoke') {
        // Delete access record
        $stmt = $pdo->prepare("
            DELETE FROM PageAccess 
            WHERE UserId = :userId AND PageId = :pageId
        ");
        $stmt->execute([
            ':userId' => $userIdx,
            ':pageId' => $pageIdx
        ]);
        
        $message = 'Access revoked successfully';
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'userId' => $userIdx,
        'pageId' => $pageIdx,
        'action' => $action
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in update_page_access.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>