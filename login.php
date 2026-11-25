<?php
require 'config.php';
if (current_user()) redirect(role_home(current_user()['role']));

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    // Simple brute-force limit: 5 attempts, then 5-minute lock
    if (($_SESSION['attempts'] ?? 0) >= 5 && time() - ($_SESSION['last_attempt'] ?? 0) < 300) {
        $error = 'Too many attempts. Try again in a few minutes.';
    } else {
        $s = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $s->execute([$email]);
        $user = $s->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'This account has been deactivated. Please contact the administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                unset($_SESSION['attempts'], $_SESSION['last_attempt']);
                redirect(role_home($user['role']));
            }
        } else {
            $_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1;
            $_SESSION['last_attempt'] = time();
            $error = 'Invalid email or password.';
        }
    }
}
$title = 'Login';
include 'header.php';
?>
<div class="card">
  <h2>Log in</h2>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email</label>
    <input type="email" name="email" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit">Log in</button>
  </form>
  <p style="margin-top:18px"><a href="forgot_password.php">Forgot your password?</a></p>
</div>
<?php include 'footer.php'; ?>
