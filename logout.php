<?php
// End the session completely, then return to the login page.
require __DIR__ . '/includes/functions.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => COOKIE_PATH,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_destroy();

// Start a fresh session just to show the goodbye message.
session_start();
session_regenerate_id(true);
flash('success', 'You have been logged out.');
redirect('login.php');
