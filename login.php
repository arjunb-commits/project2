<?php
require __DIR__ . '/includes/functions.php';

// Used so a wrong username takes as long to reject as a wrong password.
const DUMMY_HASH = '$2y$10$6YRFxsCraOsTiKy9Dtzel.dF3U483Ot6.K2OtucF.xey4u7dBTkJa';

if (current_user_id() !== null) {
    redirect('lobby.php');
}

$errors = [];

// Pre-fill the username from the "remember me" cookie (validated before use).
$username = '';
if (isset($_COOKIE['last_user']) && is_string($_COOKIE['last_user'])
    && preg_match('/^[A-Za-z0-9_]{3,20}$/', $_COOKIE['last_user'])) {
    $username = $_COOKIE['last_user'];
}
$remember = $username !== '';

if (is_post()) {
    $username = post_str('username');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';
    $remember = isset($_POST['remember']);

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $user = $username !== '' ? find_user($username) : null;
        $hash = $user['password_hash'] ?? DUMMY_HASH;
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            $errors[] = 'Incorrect username or password.';
        } else {
            session_regenerate_id(true);              // prevent session fixation
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];

            $cookieOptions = ['path' => COOKIE_PATH, 'httponly' => true, 'samesite' => 'Lax',
                              'secure' => !empty($_SERVER['HTTPS'])];
            if ($remember) {
                setcookie('last_user', $user['username'], $cookieOptions + ['expires' => time() + 60 * 60 * 24 * 30]);
            } else {
                setcookie('last_user', '', $cookieOptions + ['expires' => time() - 3600]);
            }

            $target = $_SESSION['redirect_after_login'] ?? 'lobby.php';
            unset($_SESSION['redirect_after_login']);
            if (!in_array($target, ALLOWED_REDIRECTS, true)) {
                $target = 'lobby.php';
            }
            redirect($target);
        }
    }
}

$pageTitle = 'Log In';
require __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
    <h1>Log In</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="post" action="login.php" novalidate>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= h($username) ?>"
               maxlength="20" autocomplete="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" maxlength="72"
               autocomplete="current-password" required>

        <label class="check"><input type="checkbox" name="remember" value="1" <?= $remember ? 'checked' : '' ?>>
            Remember my username (cookie)</label>

        <button type="submit" class="btn">Log In</button>
    </form>
    <p class="center">No account? <a href="register.php">Register</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
