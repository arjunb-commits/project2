<?php
require __DIR__ . '/includes/functions.php';

if (current_user_id() !== null) {
    redirect('lobby.php');
}

$errors   = [];
$username = '';

if (is_post()) {
    $username = post_str('username');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $password = is_string($password) ? $password : '';
    $confirm  = is_string($confirm) ? $confirm : '';

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username must be 3-20 characters: letters, numbers and underscores only.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be between 8 and 72 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        // Re-check for a duplicate while holding the file lock.
        $created = update_json(USERS_FILE, function (array &$users) use ($username, $password) {
            $key = strtolower($username);
            if (isset($users[$key])) {
                return false;
            }
            $users[$key] = [
                'id'            => bin2hex(random_bytes(8)),
                'username'      => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created'       => date('c'),
            ];
            return true;
        });

        if ($created) {
            flash('success', 'Account created! Please log in.');
            redirect('login.php');
        }
        $errors[] = 'That username is already taken. Please choose another.';
    }
}

$pageTitle = 'Create Account';
require __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
    <h1>Create Account</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="post" action="register.php" novalidate>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= h($username) ?>"
               minlength="3" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" autocomplete="username" required>
        <p class="hint">3-20 letters, numbers or underscores.</p>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" maxlength="72"
               autocomplete="new-password" required>
        <p class="hint">At least 8 characters.</p>

        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72"
               autocomplete="new-password" required>

        <button type="submit" class="btn">Register</button>
    </form>
    <p class="center">Already registered? <a href="login.php">Log in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
