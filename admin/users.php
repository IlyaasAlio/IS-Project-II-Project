<?php
require '../config.php';
require_role('admin');
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId === (int) $me['id']) {
        flash('You cannot deactivate your own account.', 'error');
    } else {
        $s = $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?');
        $s->execute([$userId]);
        flash('Account status updated.');
    }
    redirect('admin/users.php');
}

$role = $_GET['role'] ?? '';
$sql = 'SELECT u.id, u.full_name, u.email, u.phone, u.role, u.is_active, u.created_at, f.name AS facility
        FROM users u LEFT JOIN facilities f ON f.facility_id = u.facility_id';
$params = [];
if (in_array($role, ['patient', 'facility_staff', 'admin'], true)) {
    $sql .= ' WHERE u.role = ?';
    $params[] = $role;
}
$sql .= ' ORDER BY u.id DESC';
$s = $pdo->prepare($sql);
$s->execute($params);
$users = $s->fetchAll();

$title = 'Manage Users';
include '../header.php';
?>
<div class="container">
  <div class="row">
    <h1>User accounts</h1>
    <form method="get" class="filters">
      <select name="role" aria-label="Filter by role" onchange="this.form.submit()">
        <option value="">All roles</option>
        <option value="patient" <?= $role === 'patient' ? 'selected' : '' ?>>Patients</option>
        <option value="facility_staff" <?= $role === 'facility_staff' ? 'selected' : '' ?>>Facility staff</option>
        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrators</option>
      </select>
      <noscript><button type="submit" class="small">Filter</button></noscript>
    </form>
  </div>

  <div class="panel">
    <div class="scroll">
    <table class="data">
      <tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Facility</th><th>Joined</th><th>Status</th><th></th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone'] ?: '-') ?></td>
          <td><?= e(role_label($u['role'])) ?></td>
          <td><?= e($u['facility'] ?? '-') ?></td>
          <td><?= e(fmt_date($u['created_at'])) ?></td>
          <td><?= $u['is_active'] ? badge('Active', 'green') : badge('Deactivated', 'red') ?></td>
          <td>
            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
              <form method="post" onsubmit="return confirm('Change this account\'s status?');">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <button type="submit" class="small <?= $u['is_active'] ? 'btn-danger' : '' ?>"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
            <?php else: ?>
              <span class="muted">You</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>
</div>
<?php include '../footer.php'; ?>
