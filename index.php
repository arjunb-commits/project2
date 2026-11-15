<?php
// Entry point: send visitors to the lobby (logged in) or the login page.
require __DIR__ . '/includes/functions.php';
redirect(current_user_id() !== null ? 'lobby.php' : 'login.php');
