<?php
require 'config.php';
$message = '';
$devLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));

    $s = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $s->execute([$email]);
    $user = $s->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        // invalidate older tokens
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')->execute([$user['id']]);
        $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at)
                       VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
            ->execute([$user['id'], $hash]);

        $link = BASE_URL . '/reset_password.php?token=' . $token;

        if (DEV_MODE) {
            $devLink = $link; // local testing: no mail server needed
        } else {
            mail($email, 'Password Reset - Drug & Vaccine Alert',
                 "Click this link to reset your password (valid 30 minutes):\n$link");
        }
    }
    // Same message whether or not the email exists (prevents account enumeration)
    $message = 'If that email is registered, a reset link has been sent.';
}
$title = 'Forgot Password';
include 'header.php';
?>
<div class="card">
  <h2>Forgot Password</h2>
  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($devLink): ?>
    <div class="alert info"><strong>DEV MODE:</strong> <a href="<?= e($devLink) ?>">Click here to reset</a></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email</label>
    <input type="email" name="email" required>
    <button type="submit">Send Reset Link</button>
  </form>
  <p><a href="login.php">Back to login</a></p>
</div>
<?php include 'footer.php'; ?>
