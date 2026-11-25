<?php
require '../config.php';
require_role('admin');

$users  = $pdo->query('SELECT role, COUNT(*) FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);
$fac    = $pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(is_verified = 1), 0) AS verified, COALESCE(SUM(is_verified = 0), 0) AS pending FROM facilities')->fetch();
$drugsN = (int) $pdo->query('SELECT COUNT(*) FROM drugs_vaccines')->fetchColumn();
$alerts = $pdo->query('SELECT status, COUNT(*) FROM alerts GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$notes  = $pdo->query('SELECT delivery_status, COUNT(*) FROM notifications GROUP BY delivery_status')->fetchAll(PDO::FETCH_KEY_PAIR);
$updates30 = (int) $pdo->query('SELECT COUNT(*) FROM stock_logs WHERE created_at >= (NOW() - INTERVAL 30 DAY)')->fetchColumn();

$notesTotal = array_sum($notes);
$notesSent  = (int) ($notes['sent'] ?? 0);
$successRate = $notesTotal > 0 ? round($notesSent * 100 / $notesTotal) : null;

$unverified = $pdo->query("SELECT f.facility_id, f.name, f.type, f.location,
                                  (SELECT GROUP_CONCAT(u.email SEPARATOR ', ') FROM users u WHERE u.facility_id = f.facility_id) AS staff
                           FROM facilities f WHERE f.is_verified = 0 ORDER BY f.facility_id DESC LIMIT 5")->fetchAll();

$topDemand = $pdo->query("SELECT d.name, COUNT(*) AS waiting
                          FROM alerts a JOIN drugs_vaccines d ON d.drug_id = a.drug_id
                          WHERE a.status = 'pending'
                          GROUP BY d.drug_id, d.name ORDER BY waiting DESC LIMIT 5")->fetchAll();

$title = 'Dashboard';
include '../header.php';
?>
<div class="container">
  <section class="hero">
    
    <h1>System overview</h1>
    <div class="badges">
      <?php if ((int) $fac['pending'] > 0): ?>
        <a class="btn small" style="background:#fde68a;color:#78350f;border-color:#fde68a" href="<?= e(url('admin/facilities.php')) ?>"><?= icon('shield', 16) ?> <?= (int) $fac['pending'] ?> facilit<?= (int) $fac['pending'] === 1 ? 'y' : 'ies' ?> to verify</a>
      <?php else: ?>
        <?= badge('All facilities verified', 'green') ?>
      <?php endif; ?>
    </div>
  </section>

  <div class="stats">
    <div class="stat"><span class="stat-ic blue"><?= icon('user', 24) ?></span><div><div class="num"><?= (int) ($users['patient'] ?? 0) ?></div><div class="lbl">Patients</div></div></div>
    <div class="stat"><span class="stat-ic violet"><?= icon('users', 24) ?></span><div><div class="num"><?= (int) ($users['facility_staff'] ?? 0) ?></div><div class="lbl">Facility staff accounts</div></div></div>
    <div class="stat"><span class="stat-ic"><?= icon('building', 24) ?></span><div><div class="num"><?= (int) $fac['verified'] ?> / <?= (int) $fac['total'] ?></div><div class="lbl">Facilities verified</div></div></div>
    <div class="stat"><span class="stat-ic green"><?= icon('layers', 24) ?></span><div><div class="num"><?= $drugsN ?></div><div class="lbl">Drugs and vaccines in catalog</div></div></div>
    <div class="stat"><span class="stat-ic amber"><?= icon('clock', 24) ?></span><div><div class="num"><?= (int) ($alerts['pending'] ?? 0) ?></div><div class="lbl">Alerts waiting for stock</div></div></div>
    <div class="stat"><span class="stat-ic green"><?= icon('check', 24) ?></span><div><div class="num"><?= (int) ($alerts['available'] ?? 0) ?></div><div class="lbl">Alerts fulfilled</div></div></div>
    <div class="stat"><span class="stat-ic blue"><?= icon('send', 24) ?></span><div><div class="num"><?= $successRate === null ? '-' : $successRate . '%' ?></div><div class="lbl">Notification success rate (<?= $notesSent ?> of <?= $notesTotal ?> sent)</div></div></div>
    <div class="stat"><span class="stat-ic rose"><?= icon('chart', 24) ?></span><div><div class="num"><?= $updates30 ?></div><div class="lbl">Stock updates, last 30 days</div></div></div>
  </div>

  <div class="grid-2">
    <div class="panel">
      <div class="row"><h3>Facilities awaiting verification</h3><a href="<?= e(url('admin/facilities.php')) ?>">Manage</a></div>
      <?php if (!$unverified): ?>
        <div class="empty"><?= icon('shield', 36) ?><p>No facilities are waiting for verification.</p></div>
      <?php else: ?>
        <div class="scroll">
        <table class="data">
          <tr><th>Facility</th><th>Type</th><th>Location</th></tr>
          <?php foreach ($unverified as $f): ?>
            <tr>
              <td><?= e($f['name']) ?><div class="muted"><?= e($f['staff'] ?? '-') ?></div></td>
              <td><?= e(facility_type_label($f['type'])) ?></td>
              <td><?= e($f['location']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3>Most requested out-of-stock items</h3>
      <?php if (!$topDemand): ?>
        <div class="empty"><?= icon('inbox', 36) ?><p>No patients are waiting for any item.</p></div>
      <?php else: ?>
        <table class="data">
          <tr><th>Drug / vaccine</th><th>Patients waiting</th></tr>
          <?php foreach ($topDemand as $t): ?>
            <tr><td><?= e($t['name']) ?></td><td><?= (int) $t['waiting'] ?></td></tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include '../footer.php'; ?>
