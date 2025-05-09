<?php
session_start();

require 'includes/header.php';
require 'includes/db.php';
?>

    <meta name="google-signin-client_id" content="736797298668-1380256p7or78ojij5eqssddb8d4gpge.apps.googleusercontent.com">
        <script src="https://accounts.google.com/gsi/client" async defer></script>

<section>
    <div class="container">
    <h2>Login</h2>

    <div id="g_id_onload"
         data-client_id="736797298668-1380256p7or78ojij5eqssddb8d4gpge.apps.googleusercontent.com"
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