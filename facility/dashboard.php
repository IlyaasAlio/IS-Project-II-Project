<?php
require '../config.php';
require_role('facility_staff');
$me  = current_user();
$fid = (int) $me['facility_id'];
$verified = (bool) $me['facility_verified'];

$s = $pdo->prepare('SELECT SUM(quantity > 0) AS in_stock, SUM(quantity = 0) AS out_of_stock,
                           SUM(quantity > 0 AND quantity <= ?) AS low
                    FROM inventory WHERE facility_id = ?');
$s->execute([LOW_STOCK_THRESHOLD, $fid]);
$inv = $s->fetch();

$s = $pdo->prepare("SELECT COUNT(*) FROM alerts a
                    JOIN notifications n ON n.alert_id = a.alert_id
                    WHERE a.facility_id = ?");
$s->execute([$fid]);
$alertsSent = (int) $s->fetchColumn();

// Patient demand: drugs people are waiting for, with this facility's current stock
$s = $pdo->prepare("SELECT d.name, d.category, COUNT(*) AS waiting, i.quantity
                    FROM alerts a
                    JOIN drugs_vaccines d ON d.drug_id = a.drug_id
                    LEFT JOIN inventory i ON i.drug_id = d.drug_id AND i.facility_id = ?
                    WHERE a.status = 'pending' AND (a.facility_id IS NULL OR a.facility_id = ?)
                    GROUP BY d.drug_id, d.name, d.category, i.quantity
                    ORDER BY waiting DESC LIMIT 10");
$s->execute([$fid, $fid]);
$demand = $s->fetchAll();

$title = 'Dashboard';
include '../header.php';
?>
<div class="container">
  <section class="hero">
    <h1><?= e($me['facility_name']) ?></h1>
    <p><?= e(facility_type_label($me['facility_type'])) ?> in <?= e($me['facility_location']) ?>.</p>
    <div class="badges">
      <?= $verified ? badge('Verified', 'green') : badge('Awaiting verification', 'amber') ?>
      <a class="btn small" style="background:#fff;color:var(--brand);border-color:#fff" href="<?= e(url('facility/inventory.php')) ?>"><?= icon('package', 16) ?> Update stock</a>
    </div>
  </section>

  <?php if (!$verified): ?>
    <div class="alert info">Your facility has not been verified by an administrator yet. You can look around, but you cannot update stock until it is verified.</div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><span class="stat-ic green"><?= icon('package', 24) ?></span><div><div class="num"><?= (int) $inv['in_stock'] ?></div><div class="lbl">Items in stock</div></div></div>
    <div class="stat"><span class="stat-ic amber"><?= icon('warning', 24) ?></span><div><div class="num"><?= (int) $inv['low'] ?></div><div class="lbl">Low stock (<?= LOW_STOCK_THRESHOLD ?> or fewer)</div></div></div>
    <div class="stat"><span class="stat-ic rose"><?= icon('x', 24) ?></span><div><div class="num"><?= (int) $inv['out_of_stock'] ?></div><div class="lbl">Out of stock items</div></div></div>
    <div class="stat"><span class="stat-ic blue"><?= icon('send', 24) ?></span><div><div class="num"><?= $alertsSent ?></div><div class="lbl">Patient alerts sent</div></div></div>
  </div>

  <div class="panel">
    <div class="row">
      <h3>Patient demand</h3>
      <a class="btn small" href="<?= e(url('facility/inventory.php')) ?>">Update stock</a>
    </div>
    <?php if (!$demand): ?>
      <div class="empty"><?= icon('inbox', 36) ?><p>No patients are waiting for any drug right now.</p></div>
    <?php else: ?>
      <div class="scroll">
      <table class="data">
        <tr><th>Drug / vaccine</th><th>Type</th><th>Patients waiting</th><th>Your stock</th></tr>
        <?php foreach ($demand as $d): ?>
          <tr>
            <td><?= e($d['name']) ?></td>
            <td><?= e($d['category']) ?></td>
            <td><?= (int) $d['waiting'] ?></td>
            <td><?= $d['quantity'] === null ? badge('Not stocked', 'grey') : ((int) $d['quantity'] === 0 ? badge('Out of stock', 'red') : e((int) $d['quantity'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../footer.php'; ?>
