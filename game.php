<?php
require __DIR__ . '/includes/functions.php';
require_login();

// No active game: go back to the lobby. Finished game: go to the result page.
if (empty($_SESSION['game'])) {
    flash('warning', 'Start a new game from the lobby first.');
    redirect('lobby.php');
}
if (!empty($_SESSION['game']['over'])) {
    redirect('result.php');
}

if (is_post()) {
    $action = post_str('action');
    $game   = &$_SESSION['game'];

    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
    } elseif ($action === 'quit') {
        unset($_SESSION['game']);
        flash('warning', 'Game abandoned. It was not saved.');
        redirect('lobby.php');
    } elseif ($action === 'roll') {
        // The one-time token blocks refresh / back-button / double-click rolls.
        $sent = post_str('turn_token');
        if ($sent === '' || !hash_equals($game['turn_token'], $sent)) {
            flash('warning', 'That roll was already counted.');
        } else {
            play_round($game);
            if ($game['over']) {
                redirect('result.php');
            }
        }
    }
    unset($game);
    redirect('game.php');   // Post/Redirect/Get: refreshing never repeats a roll
}

$game      = $_SESSION['game'];
$pageTitle = 'Game';
$diff      = DIFFICULTIES[$game['difficulty']];
require __DIR__ . '/includes/header.php';
?>
<h1>Adventures of the Dice</h1>
<div class="game-layout">
    <section aria-label="Board">
        <?= render_board($game) ?>
        <p class="legend">
            <span class="swatch you"></span> You (square <?= (int)$game['player_pos'] ?>)
            <span class="swatch bot"></span> Dice Bot (square <?= (int)$game['bot_pos'] ?>)
            <span class="swatch up"></span> Ladder
            <span class="swatch down"></span> Snake
        </p>
    </section>

    <aside class="card" aria-label="Game controls">
        <p class="turn">Turn <?= (int)$game['turn'] + 1 ?> &middot; <?= h($diff['label']) ?></p>

        <div class="dice-row">
            <div class="dice-box">
                <span class="dice-label">You</span>
                <span class="die" aria-label="Your last roll: <?= (int)$game['player_roll'] ?>"><?= die_face($game['player_roll']) ?></span>
            </div>
            <div class="dice-box">
                <span class="dice-label">Dice Bot</span>
                <span class="die" aria-label="Bot's last roll: <?= (int)$game['bot_roll'] ?>"><?= die_face($game['bot_roll']) ?></span>
            </div>
        </div>

        <form method="post" action="game.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="roll">
            <input type="hidden" name="turn_token" value="<?= h($game['turn_token']) ?>">
            <button type="submit" class="btn btn-big">Roll Dice</button>
        </form>

        <h2>Recent moves</h2>
        <ul class="log">
            <?php foreach (array_slice($game['log'], 0, 6) as $line): ?>
                <li><?= h($line) ?></li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="game.php" class="quit-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="quit">
            <button type="submit" class="btn btn-link">Quit game</button>
        </form>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
