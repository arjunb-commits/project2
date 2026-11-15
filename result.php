<?php
require __DIR__ . '/includes/functions.php';
require_login();

// Score a finished game exactly once. Refreshing this page just re-shows the saved result.
if (!empty($_SESSION['game']['over'])) {
    $game   = $_SESSION['game'];
    $won    = $game['winner'] === 'player';
    $score  = calculate_score($game, $won);
    $entry  = [
        'id'         => bin2hex(random_bytes(6)),
        'user_id'    => current_user_id(),
        'username'   => $_SESSION['username'],
        'score'      => $score['total'],
        'difficulty' => $game['difficulty'],
        'turns'      => $game['turn'],
        'won'        => $won,
        'date'       => date('c'),
    ];
    update_json(SCORES_FILE, function (array &$scores) use ($entry) {
        $scores[] = $entry;
    });

    $rank = null;
    if ($won) {
        foreach (ranked_wins('all') as $i => $row) {
            if ($row['id'] === $entry['id']) {
                $rank = $i + 1;
                break;
            }
        }
    }
    $_SESSION['last_result'] = ['won' => $won, 'score' => $score, 'rank' => $rank,
                                'difficulty' => $game['difficulty'], 'turns' => $game['turn'],
                                'ladders' => $game['ladders_climbed'], 'snakes' => $game['snakes_hit']];
    unset($_SESSION['game']);
}

if (empty($_SESSION['last_result'])) {
    flash('warning', 'No finished game to show yet.');
    redirect('lobby.php');
}

$r         = $_SESSION['last_result'];
$s         = $r['score'];
$pageTitle = 'Result';
require __DIR__ . '/includes/header.php';
?>
<section class="banner <?= $r['won'] ? 'win' : 'lose' ?>">
    <h1><?= $r['won'] ? '&#127942; You win!' : 'The Dice Bot wins this time' ?></h1>
    <p><?= (int)$r['turns'] ?> turns &middot; <?= (int)$r['ladders'] ?> ladders &middot; <?= (int)$r['snakes'] ?> snakes &middot; <?= h(DIFFICULTIES[$r['difficulty']]['label']) ?></p>
</section>

<div class="two-col">
    <section class="card">
        <h2>Score breakdown</h2>
        <table class="score-table">
            <tr><td>Base score</td><td><?= number_format($s['base']) ?></td></tr>
            <tr><td>Turn penalty (&minus;10 &times; <?= (int)$r['turns'] ?>)</td><td>&minus; <?= number_format($s['turn_penalty']) ?></td></tr>
            <tr><td>Ladder bonus (+15 &times; <?= (int)$r['ladders'] ?>)</td><td>+ <?= number_format($s['ladder_bonus']) ?></td></tr>
            <tr><td>Snake penalty (&minus;10 &times; <?= (int)$r['snakes'] ?>)</td><td>&minus; <?= number_format($s['snake_penalty']) ?></td></tr>
            <tr><td>Difficulty multiplier</td><td>&times; <?= h($s['multiplier']) ?></td></tr>
            <tr><td>Win bonus</td><td>+ <?= number_format($s['win_bonus']) ?></td></tr>
            <tr class="total"><td>Final score</td><td><?= number_format($s['total']) ?></td></tr>
        </table>
    </section>
    <section class="card">
        <?php if ($r['won']): ?>
            <p class="big-rank">Saved! Leaderboard rank <strong>#<?= (int)$r['rank'] ?></strong></p>
        <?php else: ?>
            <p>Only wins are added to the leaderboard. Better luck next time!</p>
        <?php endif; ?>
        <p><a class="btn" href="lobby.php">Play Again</a></p>
        <p><a class="btn btn-secondary" href="leaderboard.php">View Leaderboard</a></p>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
