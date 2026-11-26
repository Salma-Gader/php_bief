<?php
declare(strict_types=1);

/**
 * Back-office sign-in page.
 * GET shows the form. POST checks the CSRF token, then the credentials.
 */

require __DIR__ . '/../../src/bootstrap.php';

Auth::startSession();

// Already signed in: go straight to the bookings.
if (Auth::check()) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!Csrf::isValid()) {
        $error = 'Your session expired. Sign in again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $result = Auth::attempt($username, $password);

        if ($result === 'ok') {
            redirect('index.php');
        }
        // Same message for a wrong username and a wrong password: it does not reveal which usernames exist.
        $error = $result === 'locked'
            ? 'Too many failed sign-in attempts. Wait 15 minutes, then try again.'
            : 'The username or password is incorrect.';
    }
}

$notice = match (true) {
    isset($_GET['logged_out']) => 'You are signed out.',
    isset($_GET['expired'])    => 'You were signed out after 30 minutes without activity.',
    default                    => '',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <title>Staff sign-in | Sunset Brunch</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <p class="auth-card__brand">Sunset Brunch</p>
        <h1 class="auth-card__title">Staff sign-in</h1>

        <?php if ($notice !== ''): ?>
            <p class="notice notice--info" role="status"><?= e($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice notice--error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php" class="auth-form">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required
                       maxlength="50" value="<?= e($username) ?>" <?= $username === '' ? 'autofocus' : '' ?>>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       <?= $username !== '' ? 'autofocus' : '' ?>>
            </div>
            <button class="button button--primary auth-form__submit" type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
