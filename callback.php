<?php
require_once 'vendor/autoload.php';
require 'includes/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $id_token = $_POST['credential'];

    $client = new Google_Client(['client_id' => '736797298668-1380256p7or78ojij5eqssddb8d4gpge.apps.googleusercontent.com']);
    $payload = $client->verifyIdToken($id_token);

    if ($payload && isset($payload['email'])) {
        $email = $payload['email'];

        // Look up user in database
        $stmt = $pdo->prepare("SELECT Id, Shortname, Type, Team FROM Personel WHERE Type > 1 AND Email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // User found, set session variables
            $_SESSION['user_id'] = $user['Id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $user['Shortname'];
            $_SESSION['user_team'] = $user['Team'];
            $_SESSION['auth_level'] = $user['Type'];

            $pdo->exec('UPDATE Personel SET LastLogin = CURRENT_TIMESTAMP WHERE Id = ' . $user['Id']);

            // Check if there's a redirect URL stored in the session
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect_url = $_SESSION['redirect_after_login'];
                // Clear the stored URL
                unset($_SESSION['redirect_after_login']);
                
                // Redirect to the originally requested page
                header("Location: $redirect_url");
                exit;
            }
            
            // Default redirect if no specific page was requested
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
