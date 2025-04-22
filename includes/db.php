<?php
try {
    // Create the PDO connection
    $pdo = new PDO('mysql:host=host;port=port;dbname=mis;charset=utf8', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Handle connection errors
    echo 'Connection failed: ' . $e->getMessage();
    exit;
}
?>
