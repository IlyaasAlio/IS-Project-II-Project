<?php
require 'config.php';
if (current_user()) redirect(role_home(current_user()['role']));

$errors = [];
$name = $email = $phone = '';
$accountType = $_POST['account_type'] ?? 'patient';
$facName = $facType = $facLocation = '';
$facTypes = ['pharmacy', 'hospital', 'clinic', 'health_centre'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name        = trim($_POST['full_name'] ?? '');
    $email       = strtolower(trim($_POST['email'] ?? ''));
    $phone       = trim($_POST['phone'] ?? '');
    $pass        = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $facName     = trim($_POST['facility_name'] ?? '');
    $facType     = $_POST['facility_type'] ?? '';
    $facLocation = trim($_POST['facility_location'] ?? '');
    $isFacility  = ($accountType === 'facility');

    if ($name === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && !preg_match('/^\+?[0-9]{9,15}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    if ($err = password_error($pass)) $errors[] = $err;
    if ($pass !== $confirm) $errors[] = 'Passwords do not match.';
    if ($isFacility) {
        if ($facName === '') $errors[] = 'Facility name is required.';
        if (!in_array($facType, $facTypes, true)) $errors[] = 'Choose a facility type.';
        if ($facLocation === '') $errors[] = 'Facility location is required.';
    }

    if (!$errors) {
        $s = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $s->execute([$email]);
        if ($s->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();
                $facilityId = null;
                if ($isFacility) {
                    $pdo->prepare('INSERT INTO facilities (name, type, location, is_verified) VALUES (?,?,?,0)')
                        ->execute([$facName, $facType, $facLocation]);
                    $facilityId = (int) $pdo->lastInsertId();
                }
                $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, facility_id) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $email, $phone, $hash, $isFacility ? 'facility_staff' : 'patient', $facilityId]);
                $pdo->commit();
                flash($isFacility
                    ? 'Facility account created. An administrator must verify your facility before you can update stock. You can log in now.'
                    : 'Registration successful. Please log in.');
                redirect('login.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Could not create the account. Please try again.';
            }
        }
    }
}
$title = 'Register';
include 'header.php';
?>
<div class="card">
  <h2>Register</h2>
  <?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>I am registering as</label>
    <div class="choices">
      <label class="choice">
        <input type="radio" name="account_type" value="patient" <?= $accountType !== 'facility' ? 'checked' : '' ?>>
        <span class="choice-body"><?= icon('user', 22) ?><strong>Patient</strong><small>Search and get stock alerts</small></span>
      </label>
      <label class="choice">
        <input type="radio" name="account_type" value="facility" <?= $accountType === 'facility' ? 'checked' : '' ?>>
        <span class="choice-body"><?= icon('building', 22) ?><strong>Facility</strong><small>Pharmacy, clinic or hospital</small></span>
      </label>
    </div>

    <label>Full Name</label>
    <input type="text" name="full_name" value="<?= e($name) ?>" required>
    <label>Email</label>
    <input type="email" name="email" value="<?= e($email) ?>" required>
    <label>Phone (optional, for SMS alerts)</label>
    <input type="text" name="phone" value="<?= e($phone) ?>" placeholder="+2547XXXXXXXX">

    <div id="facilityFields" class="<?= $accountType === 'facility' ? '' : 'hidden' ?>">
      <label>Facility Name</label>
      <input type="text" name="facility_name" value="<?= e($facName) ?>">
      <label>Facility Type</label>
      <select name="facility_type">
        <option value="">Select type</option>
        <?php foreach ($facTypes as $t): ?>
          <option value="<?= e($t) ?>" <?= $facType === $t ? 'selected' : '' ?>><?= e(facility_type_label($t)) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Location (town / area)</label>
      <input type="text" name="facility_location" value="<?= e($facLocation) ?>">
    </div>

    <label>Password</label>
    <input type="password" name="password" required>
    <label>Confirm Password</label>
    <input type="password" name="confirm_password" required>
    <button type="submit">Register</button>
  </form>
  <p>Already registered? <a href="login.php">Login</a></p>
</div>
<script>
document.querySelectorAll('input[name="account_type"]').forEach(function (r) {
  r.addEventListener('change', function () {
    document.getElementById('facilityFields').classList.toggle('hidden', this.value !== 'facility');
  });
});
</script>
<?php include 'footer.php'; ?>
