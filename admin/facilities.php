<?php
require '../config.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $facilityId = (int) ($_POST['facility_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['verify', 'unverify'], true)) {
        $s = $pdo->prepare('UPDATE facilities SET is_verified = ? WHERE facility_id = ?');
        $s->execute([$action === 'verify' ? 1 : 0, $facilityId]);
        flash($action === 'verify' ? 'Facility verified. Its staff can now update stock.' : 'Facility verification removed.');
    }
    redirect('admin/facilities.php');
}

$facilities = $pdo->query("SELECT f.facility_id, f.name, f.type, f.location, f.is_verified, f.created_at,
                                  (SELECT GROUP_CONCAT(u.email SEPARATOR ', ') FROM users u WHERE u.facility_id = f.facility_id) AS staff,
                                  (SELECT COUNT(*) FROM inventory i WHERE i.facility_id = f.facility_id) AS items
                           FROM facilities f
                           ORDER BY f.is_verified ASC, f.facility_id DESC")->fetchAll();

$title = 'Manage Facilities';
include '../header.php';
?>
<div class="container">
  <h1>Facilities</h1>

  <div class="panel">
    <?php if (!$facilities): ?>
      <p class="muted">No facilities have registered yet.</p>
    <?php else: ?>
      <div class="scroll">
      <table class="data">
        <tr><th>Facility</th><th>Type</th><th>Location</th><th>Staff</th><th>Items</th><th>Status</th><th></th></tr>
        <?php foreach ($facilities as $f): ?>
          <tr>
            <td><?= e($f['name']) ?></td>
            <td><?= e(facility_type_label($f['type'])) ?></td>
            <td><?= e($f['location']) ?></td>
            <td><?= e($f['staff'] ?? '-') ?></td>
            <td><?= (int) $f['items'] ?></td>
            <td><?= $f['is_verified'] ? badge('Verified', 'green') : badge('Pending', 'amber') ?></td>
            <td>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="facility_id" value="<?= (int) $f['facility_id'] ?>">
                <?php if ($f['is_verified']): ?>
                  <input type="hidden" name="action" value="unverify">
                  <button type="submit" class="small btn-outline">Remove verification</button>
                <?php else: ?>
                  <input type="hidden" name="action" value="verify">
                  <button type="submit" class="small">Verify</button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../footer.php'; ?>
