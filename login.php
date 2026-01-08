<?php
require 'includes/header.php';
require_once 'includes/db.php';

// Function to get user-friendly error messages
function getErrorMessage($error_code) {
    switch($error_code) {
        case 'user_not_found':
            return 'Access denied. Your account was not found or you do not have sufficient privileges to access this system.';
        case 'invalid_token':
            return 'Authentication failed. Please try signing in again.';
        case 'no_token':
            return 'No authentication token received. Please try signing in again.';
        default:
            return 'An error occurred during login. Please try again.';
    }
}
?>

    <meta name="google-signin-client_id" content="<?= GOOGLE_CLIENT_ID ?>">
    <script src="https://accounts.google.com/gsi/client" async defer></script>

<section>
    <div class="container">
    <h2>Login</h2>

    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            <strong>⚠️ Login Error:</strong><br>
            <?= htmlspecialchars(getErrorMessage($_GET['error'])) ?>
        </div>
    <?php endif; ?>

    <div id="g_id_onload"
         data-client_id="<?= GOOGLE_CLIENT_ID ?>"
         data-callback="handleCredentialResponse">
    </div>

    <div class="g_id_signin"
         data-type="standard"
         data-shape="rectangular"
         data-theme="outline"
         data-text="signin_with"
         data-size="large"
         data-logo_alignment="left">
    </div>

    <script>
        function handleCredentialResponse(response) {
            // The ID token is in response.credential
            console.log("ID Token: " + response.credential);

            // Send ID token to server (callback.php) using POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'callback.php';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'credential';
            input.value = response.credential;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
    </div>
</section>

<?php require 'includes/footer.php'; ?>