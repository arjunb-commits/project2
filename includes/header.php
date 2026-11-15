<?php
/** Shared page header. Expects $pageTitle (string) before it is included. */
$pageTitle = $pageTitle ?? APP_NAME;
$loggedIn  = current_user_id() !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= $loggedIn ? 'lobby.php' : 'login.php' ?>">&#127922; <?= h(APP_NAME) ?></a>
    <nav aria-label="Main navigation">
        <?php if ($loggedIn): ?>
            <a href="lobby.php">Lobby</a>
            <a href="leaderboard.php">Leaderboard</a>
            <a href="logout.php">Logout (<?= h($_SESSION['username'] ?? '') ?>)</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?php foreach (take_flashes() as $f): ?>
    <div class="alert alert-<?= h($f['type']) ?>" role="status"><?= h($f['msg']) ?></div>
<?php endforeach; ?>
