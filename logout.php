<?php
require_once __DIR__ . '/config/functions.php';

if (is_logged_in()) {
    log_action('logout', 'User logged out');
    
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

header("Location: login.php");
exit();
