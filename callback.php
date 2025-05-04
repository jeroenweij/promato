<?php
require_once 'vendor/autoload.php';
require 'includes/db.php'; // Assuming you have $pdo here

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $id_token = $_POST['credential'];

    $client = new Google_Client(['client_id' => '736797298668-1380256p7or78ojij5eqssddb8d4gpge.apps.googleusercontent.com']);
    $payload = $client->verifyIdToken($id_token);

    if ($payload && isset($payload['email'])) {
        $email = $payload['email'];

        // Look up user in database
        $stmt = $pdo->prepare("SELECT Id, Shortname, Type FROM Personel WHERE Email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // User found, set session variables
            $_SESSION['user_id'] = $user['Id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $user['Shortname'];
            $_SESSION['auth_level'] = $user['Type'];

            header('Location: index.php');
            exit();
        } else {
            // User not found → redirect to login page
            header('Location: login.php?error=user_not_found');
            exit();
        }
    } else {
        // Invalid ID token → redirect to login page
        header('Location: login.php?error=invalid_token');
        exit();
    }
} else {
    // No token received → redirect to login page
    header('Location: login.php?error=no_token');
    exit();
}
?>
