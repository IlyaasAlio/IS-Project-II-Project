<?php
require '../config.php';
require_role('patient');
$uid = current_user()['id'];

/* ---- Cancel a waiting alert ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $alertId = (int) ($_POST['alert_id'] ?? 0);
    $s = $pdo->prepare("UPDATE alerts SET status = 'cancelled' WHERE alert_id = ? AND user_id = ? AND status = 'pending'");
    $s->execute([$alertId, $uid]);
    flash($s->rowCount() ? 'Alert cancelled.' : 'That alert could not be cancelled.', $s->rowCount() ? 'success' : 'error');
    redirect('patient/alerts.php');
}

$filter = $_GET['status'] ?? '';
$valid  = ['pending', 'available', 'cancelled'];

$sql = "SELECT a.alert_id, a.status, a.created_at, d.name AS drug, d.category,
               f.name AS facility, f.location, f.type AS facility_type,
               (SELECT MAX(n.sent_at) FROM notifications n WHERE n.alert_id = a.alert_id) AS notified_at,
               (SELECT u.phone FROM users u WHERE u.facility_id = a.facility_id AND u.role = 'facility_staff' AND u.is_active = 1 ORDER BY u.id LIMIT 1) AS contact_phone,
               (SELECT u.email FROM users u WHERE u.facility_id = a.facility_id AND u.role = 'facility_staff' AND u.is_active = 1 ORDER BY u.id LIMIT 1) AS contact_email
        FROM alerts a
        JOIN drugs_vaccines d ON d.drug_id = a.drug_id
        LEFT JOIN facilities f ON f.facility_id = a.facility_id
        WHERE a.user_id = ?";
$params = [$uid];
if (in_array($filter, $valid, true)) {
    $sql .= ' AND a.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY a.alert_id DESC';
$s = $pdo->prepare($sql);
$s->execute($params);
$alerts = $s->fetchAll();

$labels = ['pending' => ['Waiting for stock', 'amber'], 'available' => ['Now available', 'green'], 'cancelled' => ['Cancelled', 'grey']];

$title = 'My Alerts';
include '../header.php';
?>
<div class="container">
  <div class="row">
    <h1>My alerts</h1>
    <form method="get" class="filters">
      <select name="status" aria-label="Filter alerts" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Waiting for stock</option>
        <option value="available" <?= $filter === 'available' ? 'selected' : '' ?>>Now available</option>
        <option value="cancelled" <?= $filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
      </select>
      <noscript><button type="submit" class="small">Filter</button></noscript>
    </form>
  </div>

  <?php if (!$alerts): ?>
    <div class="panel"><div class="empty"><?= icon('bell', 36) ?><p>No alerts to show.<br><a href="<?= e(url('patient/search.php')) ?>">Search for a drug</a> to create one.</p></div></div>
  <?php else: foreach ($alerts as $a):
    [$label, $color] = $labels[$a['status']]; ?>
    <div class="item <?= $a['status'] === 'available' ? 'new' : '' ?>">
      <div class="row">
        <h3><?= e($a['drug']) ?></h3>
        <?= badge($label, $color) ?>
      </div>
      <div class="muted">Requested <?= e(fmt_dt($a['created_at'])) ?></div>

      <?php if ($a['status'] === 'available'): ?>
        <div class="sub-item">
          <strong>In stock at <?= e($a['facility']) ?></strong> (<?= e(facility_type_label($a['facility_type'])) ?>) - <?= e($a['location']) ?><br>
          <span class="muted">Notified <?= e(fmt_dt($a['notified_at'])) ?></span><br>
          <span class="muted"><?= contact_line($a['contact_phone'], $a['contact_email']) ?></span>
        </div>
      <?php elseif ($a['status'] === 'pending'): ?>
        <div class="sub-item">
          <form method="post" onsubmit="return confirm('Cancel this alert?');">
            <?= csrf_field() ?>
            <input type="hidden" name="alert_id" value="<?= (int) $a['alert_id'] ?>">
            <button type="submit" class="small btn-outline">Cancel alert</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php include '../footer.php'; ?>
