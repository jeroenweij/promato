<?php
/**
 * CSRF Protection System
 *
 * Usage in forms:
 *   <?php csrf_field(); ?>
 *
 * Usage in AJAX:
 *   Add header: X-CSRF-Token: <?= csrf_token() ?>
 *
 * Verification (automatic in forms, manual in AJAX handlers):
 *   csrf_verify() or die
 */

/**
 * Generate a CSRF token for the current session
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF token field for forms
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST request or HTTP header
 *
 * @return bool True if valid, false otherwise
 */
function csrf_verify() {
    $token = null;

    // Check POST data
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // Check HTTP header (for AJAX requests)
    elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    // Verify token matches session
    if (!isset($_SESSION['csrf_token']) || !$token) {
        return false;
    }

    // Use hash_equals to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token and die with error if invalid
 */
function csrf_protect() {
    if (!csrf_verify()) {
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}

/**
 * Regenerate CSRF token (call after login/logout)
 */
function csrf_regenerate() {
    unset($_SESSION['csrf_token']);
    return csrf_token();
}
?>
