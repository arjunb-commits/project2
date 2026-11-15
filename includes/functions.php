<?php
/**
 * functions.php - helpers for output, security, storage, login and game logic.
 */
require_once __DIR__ . '/config.php';

/* ------------------------------------------------------------------ */
/* Output, redirects, flash messages, CSRF                              */
/* ------------------------------------------------------------------ */

/** Escape text for safe HTML output. */
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read a trimmed string from $_POST (empty string if missing or not a string). */
function post_str(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/* ------------------------------------------------------------------ */
/* JSON file storage (no database): shared lock to read, exclusive to write */
/* ------------------------------------------------------------------ */

function ensure_data_files(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    foreach ([USERS_FILE, SCORES_FILE] as $file) {
        if (!file_exists($file)) {
            file_put_contents($file, '[]', LOCK_EX);
        }
    }
}

function read_json(string $file): array
{
    ensure_data_files();
    $handle = fopen($file, 'rb');
    if (!$handle) {
        return [];
    }
    flock($handle, LOCK_SH);
    $raw = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    $data = json_decode($raw === false || $raw === '' ? '[]' : $raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Read-modify-write under an exclusive lock so two players can never
 * corrupt the file. $change receives the data array by reference.
 */
function update_json(string $file, callable $change)
{
    ensure_data_files();
    $handle = fopen($file, 'c+');
    if (!$handle) {
        throw new RuntimeException('Cannot open data file.');
    }
    flock($handle, LOCK_EX);
    $raw  = stream_get_contents($handle);
    $data = json_decode($raw === false || $raw === '' ? '[]' : $raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    $result = $change($data);
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $result;
}

/* ------------------------------------------------------------------ */
/* Users and login                                                      */
/* ------------------------------------------------------------------ */

function find_user(string $username): ?array
{
    $users = read_json(USERS_FILE);
    return $users[strtolower($username)] ?? null;
}

function current_user_id(): ?string
{
    return isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
}

/** Protected pages call this first; visitors who are not logged in go to login.php. */
function require_login(): void
{
    if (current_user_id() === null) {
        $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (in_array($self, ALLOWED_REDIRECTS, true)) {
            $_SESSION['redirect_after_login'] = $self;
        }
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

/* ------------------------------------------------------------------ */
/* Board generation and game rules                                      */
/* ------------------------------------------------------------------ */

/**
 * Build a random board. Every snake head, snake tail, ladder base and ladder
 * top is a different square (never 1 or 100), so jumps can never chain.
 * Ladders and snakes always move at least one full row.
 */
function generate_board(string $difficulty): array
{
    $config = DIFFICULTIES[$difficulty];

    for ($attempt = 0; $attempt < 200; $attempt++) {
        $used = [1 => true, BOARD_SIZE => true];
        $ladders = [];
        $snakes = [];
        $ok = true;

        for ($i = 0; $i < $config['ladders'] && $ok; $i++) {
            $placed = false;
            for ($try = 0; $try < 100 && !$placed; $try++) {
                $base = random_int(2, 89);
                $top  = random_int($base + 10, 99);
                if (isset($used[$base]) || isset($used[$top])) {
                    continue;
                }
                $used[$base] = $used[$top] = true;
                $ladders[$base] = $top;
                $placed = true;
            }
            $ok = $placed;
        }

        for ($i = 0; $i < $config['snakes'] && $ok; $i++) {
            $placed = false;
            for ($try = 0; $try < 100 && !$placed; $try++) {
                $head = random_int(12, 99);
                $tail = random_int(2, $head - 10);
                if (isset($used[$head]) || isset($used[$tail])) {
                    continue;
                }
                $used[$head] = $used[$tail] = true;
                $snakes[$head] = $tail;
                $placed = true;
            }
            $ok = $placed;
        }

        if ($ok) {
            ksort($ladders);
            ksort($snakes);
            return ['ladders' => $ladders, 'snakes' => $snakes];
        }
    }

    // Extremely unlikely fallback: a fixed, valid board.
    return [
        'ladders' => [4 => 25, 13 => 46, 33 => 49, 42 => 63, 50 => 69, 62 => 81, 74 => 92],
        'snakes'  => [27 => 5, 40 => 3, 43 => 18, 54 => 31, 66 => 45, 89 => 53, 95 => 75],
    ];
}

function new_game(string $difficulty): array
{
    return [
        'difficulty'      => $difficulty,
        'board'           => generate_board($difficulty),
        'player_pos'      => 1,
        'bot_pos'         => 1,
        'turn'            => 0,
        'ladders_climbed' => 0,
        'snakes_hit'      => 0,
        'player_roll'     => null,
        'bot_roll'        => null,
        'log'             => ['Game started. Roll the dice to begin!'],
        'over'            => false,
        'winner'          => null,
        'turn_token'      => bin2hex(random_bytes(8)),
    ];
}

function roll_dice(): int
{
    return random_int(1, 6);
}

/**
 * Move one pawn. A roll that would go past square 100 is ignored
 * (exact finish required). Returns the new position and what happened.
 */
function apply_roll(int $position, int $roll, array $board): array
{
    $landed = $position + $roll;
    if ($landed > BOARD_SIZE) {
        return ['pos' => $position, 'landed' => $position, 'event' => 'overshoot'];
    }
    if (isset($board['ladders'][$landed])) {
        return ['pos' => $board['ladders'][$landed], 'landed' => $landed, 'event' => 'ladder'];
    }
    if (isset($board['snakes'][$landed])) {
        return ['pos' => $board['snakes'][$landed], 'landed' => $landed, 'event' => 'snake'];
    }
    return ['pos' => $landed, 'landed' => $landed, 'event' => 'move'];
}

function describe_move(string $who, int $roll, array $result, int $from): string
{
    $text = "$who rolled a $roll";
    switch ($result['event']) {
        case 'overshoot':
            return "$text but needs an exact roll to reach 100, so stays on $from.";
        case 'ladder':
            return "$text, landed on {$result['landed']} and climbed a ladder up to {$result['pos']}!";
        case 'snake':
            return "$text, landed on {$result['landed']} and slid down a snake to {$result['pos']}.";
        default:
            return "$text and moved from $from to {$result['pos']}.";
    }
}

/** Play one full round: the player rolls, then the bot (unless the player already won). */
function play_round(array &$game): void
{
    $game['turn']++;
    $names = ['player' => 'You', 'bot' => 'Dice Bot'];

    foreach (['player', 'bot'] as $who) {
        $key  = $who . '_pos';
        $from = $game[$key];
        $roll = roll_dice();
        $res  = apply_roll($from, $roll, $game['board']);

        $game[$key] = $res['pos'];
        $game[$who . '_roll'] = $roll;
        if ($who === 'player') {
            if ($res['event'] === 'ladder') { $game['ladders_climbed']++; }
            if ($res['event'] === 'snake')  { $game['snakes_hit']++; }
        }
        array_unshift($game['log'], describe_move($names[$who], $roll, $res, $from));

        if ($game[$key] === BOARD_SIZE) {
            $game['over']   = true;
            $game['winner'] = $who;
            array_unshift($game['log'], $who === 'player' ? 'You reached square 100!' : 'Dice Bot reached square 100.');
            break;
        }
    }

    $game['log'] = array_slice($game['log'], 0, 12);
    $game['turn_token'] = bin2hex(random_bytes(8)); // one-time token, rotated every roll
}

/** Score = (1000 - 10*turns + 15*ladders - 10*snakes, min 0) * multiplier, +200 for a win. */
function calculate_score(array $game, bool $won): array
{
    $multiplier = DIFFICULTIES[$game['difficulty']]['multiplier'];
    $turnPenalty  = 10 * $game['turn'];
    $ladderBonus  = 15 * $game['ladders_climbed'];
    $snakePenalty = 10 * $game['snakes_hit'];
    $subtotal = max(0, 1000 - $turnPenalty + $ladderBonus - $snakePenalty);
    $scaled   = (int)round($subtotal * $multiplier);
    $winBonus = $won ? 200 : 0;

    return [
        'base'          => 1000,
        'turn_penalty'  => $turnPenalty,
        'ladder_bonus'  => $ladderBonus,
        'snake_penalty' => $snakePenalty,
        'subtotal'      => $subtotal,
        'multiplier'    => $multiplier,
        'scaled'        => $scaled,
        'win_bonus'     => $winBonus,
        'total'         => $scaled + $winBonus,
    ];
}

/* ------------------------------------------------------------------ */
/* Scores and leaderboard                                               */
/* ------------------------------------------------------------------ */

/** Winning games only, sorted: score high-low, then fewer turns, then earlier date. */
function ranked_wins(string $difficulty = 'all'): array
{
    $wins = array_filter(read_json(SCORES_FILE), function ($entry) use ($difficulty) {
        return !empty($entry['won']) && ($difficulty === 'all' || ($entry['difficulty'] ?? '') === $difficulty);
    });
    usort($wins, function ($a, $b) {
        return [$b['score'], $a['turns'], $a['date']] <=> [$a['score'], $b['turns'], $b['date']];
    });
    return array_values($wins);
}

function user_stats(string $userId): array
{
    $stats = ['played' => 0, 'wins' => 0, 'best' => 0];
    foreach (read_json(SCORES_FILE) as $entry) {
        if (($entry['user_id'] ?? '') !== $userId) {
            continue;
        }
        $stats['played']++;
        if (!empty($entry['won'])) {
            $stats['wins']++;
            $stats['best'] = max($stats['best'], (int)$entry['score']);
        }
    }
    return $stats;
}

/* ------------------------------------------------------------------ */
/* Board rendering                                                      */
/* ------------------------------------------------------------------ */

/** Square number for a grid position. Row 0 is the bottom row; rows zig-zag. */
function square_number(int $row, int $col): int
{
    return $row * 10 + ($row % 2 === 0 ? $col + 1 : 10 - $col);
}

function render_board(array $game): string
{
    $out = '<div class="board" role="img" aria-label="Game board. You are on square '
         . (int)$game['player_pos'] . ', Dice Bot is on square ' . (int)$game['bot_pos'] . '.">';
    for ($row = 9; $row >= 0; $row--) {
        for ($col = 0; $col < 10; $col++) {
            $n = square_number($row, $col);
            $classes = ['sq', (($row + $col) % 2) ? 'alt' : ''];
            $mark = '';
            if (isset($game['board']['ladders'][$n])) {
                $classes[] = 'ladder';
                $mark = '<span class="mark up">&#9650;' . (int)$game['board']['ladders'][$n] . '</span>';
            } elseif (isset($game['board']['snakes'][$n])) {
                $classes[] = 'snake';
                $mark = '<span class="mark down">&#9660;' . (int)$game['board']['snakes'][$n] . '</span>';
            } elseif ($n === 1) {
                $classes[] = 'start';
                $mark = '<span class="mark">START</span>';
            } elseif ($n === BOARD_SIZE) {
                $classes[] = 'finish';
                $mark = '<span class="mark">&#9733; FINISH</span>';
            }
            $pawns = '';
            if ($game['player_pos'] === $n) { $pawns .= '<span class="pawn you" title="You"></span>'; }
            if ($game['bot_pos'] === $n)    { $pawns .= '<span class="pawn bot" title="Dice Bot"></span>'; }
            $out .= '<div class="' . h(trim(implode(' ', $classes))) . '"><span class="num">' . $n . '</span>'
                  . $mark . ($pawns !== '' ? '<span class="pawns">' . $pawns . '</span>' : '') . '</div>';
        }
    }
    return $out . '</div>';
}

/** Unicode die face for 1-6. */
function die_face($value): string
{
    $faces = [1 => '&#9856;', 2 => '&#9857;', 3 => '&#9858;', 4 => '&#9859;', 5 => '&#9860;', 6 => '&#9861;'];
    return $faces[(int)$value] ?? '&#127922;';
}
