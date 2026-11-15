<?php
require __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();

// A finished game that has not been scored yet goes to the result page.
if (!empty($_SESSION['game']['over'])) {
    redirect('result.php');
}

if (is_post()) {
    $difficulty = post_str('difficulty');
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
    } elseif (!array_key_exists($difficulty, DIFFICULTIES)) {   // whitelist
        flash('error', 'Please choose a valid difficulty.');
    } else {
        $_SESSION['game'] = new_game($difficulty);
        unset($_SESSION['last_result']);
        redirect('game.php');
    }
    redirect('lobby.php');
}

$stats     = user_stats($userId);
$hasGame   = !empty($_SESSION['game']) && empty($_SESSION['game']['over']);
$pageTitle = 'Lobby';
require __DIR__ . '/includes/header.php';
?>
<h1>Welcome back, <?= h($_SESSION['username']) ?>!</h1>

<section class="stats" aria-label="Your stats">
    <div class="stat"><span class="stat-label">Games played</span><span class="stat-value"><?= (int)$stats['played'] ?></span></div>
    <div class="stat"><span class="stat-label">Wins</span><span class="stat-value"><?= (int)$stats['wins'] ?></span></div>
    <div class="stat"><span class="stat-label">Best score</span><span class="stat-value"><?= number_format($stats['best']) ?></span></div>
</section>

<div class="two-col">
    <section class="card">
        <h2>New Game</h2>
        <form method="post" action="lobby.php">
            <?= csrf_field() ?>
            <fieldset>
                <legend>Difficulty</legend>
                <?php foreach (DIFFICULTIES as $key => $d): ?>
                    <label class="radio">
                        <input type="radio" name="difficulty" value="<?= h($key) ?>" <?= $key === 'normal' ? 'checked' : '' ?>>
                        <strong><?= h($d['label']) ?></strong>
                        <span class="hint"><?= (int)$d['snakes'] ?> snakes, <?= (int)$d['ladders'] ?> ladders, score &times;<?= h($d['multiplier']) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit" class="btn">Start New Game</button>
        </form>
        <?php if ($hasGame): ?>
            <p class="center"><a class="btn btn-secondary" href="game.php">Resume Game in Progress</a></p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>How to Play</h2>
        <ol>
            <li>Race the Dice Bot from square 1 to square 100.</li>
            <li>Each round you roll first, then the bot rolls.</li>
            <li>Land on a green <strong>&#9650; ladder</strong> to climb. Land on a red <strong>&#9660; snake</strong> to slide down.</li>
            <li>You need an <strong>exact roll</strong> to land on 100.</li>
            <li>Fewer turns and more ladders mean a higher score. Win to join the leaderboard!</li>
        </ol>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
