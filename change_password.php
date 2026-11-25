<?php
require 'config.php';
require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $s = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $s->execute([$_SESSION['user_id']]);
    $row = $s->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) $errors[] = 'Current password is incorrect.';
    if ($err = password_error($new)) $errors[] = $err;
    if ($new !== $confirm) $errors[] = 'New passwords do not match.';

    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        flash('Password changed successfully.');
        redirect('dashboard.php');
    }
}
$title = 'Change Password';
include 'header.php';
?>
<div class="card">
  <h2>Change Password</h2>
  <?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Current Password</label>
    <input type="password" name="current_password" required>
    <label>New Password</label>
    <input type="password" name="new_password" required>
    <label>Confirm New Password</label>
    <input type="password" name="confirm_password" required>
    <button type="submit">Update Password</button>
  </form>
</div>
<?php include 'footer.php'; ?>
