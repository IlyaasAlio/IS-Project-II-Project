<?php
require 'config.php';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$hash  = hash('sha256', $token);
$errors = [];

$s = $pdo->prepare('SELECT * FROM password_resets
                    WHERE token_hash = ? AND used = 0 AND expires_at > NOW()');
$s->execute([$hash]);
$reset = $s->fetch();

if (!$reset) {
    $title = 'Reset Password';
    include 'header.php';
    echo "<div class='card'><div class='alert error'>This reset link is invalid or has expired.</div>
          <a href='forgot_password.php'>Request a new link</a></div>";
    include 'footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($err = password_error($pass)) $errors[] = $err;
    if ($pass !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$reset['id']]);
        flash('Password reset successful. Please log in.');
        redirect('login.php');
    }
}
$title = 'Reset Password';
include 'header.php';
?>
<div class="card">
  <h2>Set New Password</h2>
  <?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New Password</label>
    <input type="password" name="password" required>
    <label>Confirm New Password</label>
    <input type="password" name="confirm_password" required>
    <button type="submit">Reset Password</button>
  </form>
</div>
<?php include 'footer.php'; ?>
