<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$username = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (attempt_login($username, $password)) {
        set_flash('Welcome back!', 'success');
        redirect('/dashboard.php');
    } else {
        $error = 'Invalid credentials.';
    }
}

render_header('Sign In');
?>
<section class="panel">
    <h1>Sign in</h1>
    <?php if ($error): ?>
        <div class="flash danger"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form-auth">
        <label>
            Username
            <input type="text" name="username" value="<?= h($username) ?>" required autofocus>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Sign in</button>
    </form>
    <p>No account yet? <a href="/register.php">Create one</a>.</p>
</section>
<div class="intro-scale-note" style="display:flex; justify-content:center;">
    <span role="img" aria-label="warning">⚠️</span>
    Tested on Firefox and Chrome only. We strongly recommend using one of these browsers for the best experience.
</div>
<div class="intro-scale-note" style="margin-top:0.5rem; display:flex; justify-content:center;">
    <span role="img" aria-label="warning">⚠️</span>
    If you encounter a server crash or HTTP 500 error, please email synth3ticl4bs@gmail.com so we can investigate quickly.
</div>
<?php
render_footer();

