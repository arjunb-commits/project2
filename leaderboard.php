<?php
require __DIR__ . '/includes/functions.php';
require_login();

// GET filter, checked against a whitelist.
$filter = filter_input(INPUT_GET, 'difficulty', FILTER_UNSAFE_RAW);
$filter = is_string($filter) ? $filter : 'all';
if ($filter !== 'all' && !array_key_exists($filter, DIFFICULTIES)) {
    $filter = 'all';
}

$rows      = array_slice(ranked_wins($filter), 0, 10);
$myId      = current_user_id();
$pageTitle = 'Leaderboard';
require __DIR__ . '/includes/header.php';
?>
<h1>Top 10 Players</h1>

<form method="get" action="leaderboard.php" class="filter-form">
    <label for="difficulty">Difficulty</label>
    <select id="difficulty" name="difficulty">
        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
        <?php foreach (DIFFICULTIES as $key => $d): ?>
            <option value="<?= h($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= h($d['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-small">Filter</button>
</form>

<?php if (!$rows): ?>
    <div class="card"><p>No winning scores yet. <a href="lobby.php">Be the first!</a></p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="leaderboard">
            <thead>
                <tr><th>Rank</th><th>Player</th><th>Score</th><th>Difficulty</th><th>Turns</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i => $row): ?>
                <tr class="<?= $row['user_id'] === $myId ? 'me' : '' ?>">
                    <td><?= $i + 1 ?></td>
                    <td><?= h($row['username']) ?><?= $row['user_id'] === $myId ? ' (you)' : '' ?></td>
                    <td><?= number_format((int)$row['score']) ?></td>
                    <td><?= h(DIFFICULTIES[$row['difficulty']]['label'] ?? $row['difficulty']) ?></td>
                    <td><?= (int)$row['turns'] ?></td>
                    <td><?= h(date('M j, Y', strtotime($row['date']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<p><a class="btn btn-secondary" href="lobby.php">Back to Lobby</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
