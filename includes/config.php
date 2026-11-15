<?php
/**
 * config.php - constants and session setup shared by every page.
 * Compatible with PHP 7.4+.
 */

define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('SCORES_FILE', DATA_DIR . '/scores.json');

const APP_NAME   = 'Adventures of the Dice';
const BOARD_SIZE = 100;

// Difficulty settings: number of snakes/ladders and the score multiplier.
const DIFFICULTIES = [
    'easy'   => ['label' => 'Easy',   'snakes' => 5,  'ladders' => 9, 'multiplier' => 1.0],
    'normal' => ['label' => 'Normal', 'snakes' => 8,  'ladders' => 8, 'multiplier' => 1.5],
    'hard'   => ['label' => 'Hard',   'snakes' => 11, 'ladders' => 6, 'multiplier' => 2.0],
];

// Only these pages may be used as a "return to" target after login.
const ALLOWED_REDIRECTS = ['lobby.php', 'game.php', 'leaderboard.php'];

// Errors are logged, not shown to players. Set display_errors to '1' while developing.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Cookies are scoped to this app's folder so other apps on the same
// server (for example other students' projects on CODD) never see them.
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('COOKIE_PATH', $scriptDir . '/');

if (session_status() === PHP_SESSION_NONE) {
    session_name('DICEQUEST_SESSID');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => COOKIE_PATH,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}
