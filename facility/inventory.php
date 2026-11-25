<?php
require '../config.php';
require_role('facility_staff');
$me  = current_user();
$fid = (int) $me['facility_id'];
$verified = (bool) $me['facility_verified'];
$errors = [];

$drugs = $pdo->query('SELECT drug_id, name, category, pack_size FROM drugs_vaccines ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $drugId   = (int) ($_POST['drug_id'] ?? 0);
    $qtyRaw   = trim($_POST['quantity'] ?? '');
    $date     = trim($_POST['restocked'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');

    if (!$verified) $errors[] = 'Your facility must be verified by an administrator before you can update stock.';

    $s = $pdo->prepare('SELECT name FROM drugs_vaccines WHERE drug_id = ?');
    $s->execute([$drugId]);
    $drug = $s->fetch();
    if (!$drug) $errors[] = 'Choose a drug or vaccine from the list.';

    if ($qtyRaw === '' || !ctype_digit($qtyRaw) || (int) $qtyRaw > 1000000) $errors[] = 'Quantity must be a whole number (0 or more).';
    $qty = (int) $qtyRaw;

    $lastUpdated = null;
    if ($date !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors[] = 'Enter a valid restocked date.';
        } elseif ($date > date('Y-m-d')) {
            $errors[] = 'The restocked date cannot be in the future.';
        } else {
            $lastUpdated = $date . ' 00:00:00';
        }
    }
    if (strlen($supplier) > 150) $errors[] = 'Supplier name is too long.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $s = $pdo->prepare('SELECT quantity FROM inventory WHERE facility_id = ? AND drug_id = ? FOR UPDATE');
            $s->execute([$fid, $drugId]);
            $old = $s->fetchColumn();           // false when the item was never stocked
            $old = ($old === false) ? null : (int) $old;

            $pdo->prepare('INSERT INTO inventory (facility_id, drug_id, quantity, last_updated)
                           VALUES (?, ?, ?, COALESCE(?, NOW()))
                           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), last_updated = VALUES(last_updated)')
                ->execute([$fid, $drugId, $qty, $lastUpdated]);

            $pdo->prepare('INSERT INTO stock_logs (facility_id, drug_id, user_id, old_quantity, new_quantity, supplier)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$fid, $drugId, $me['id'], $old, $qty, $supplier !== '' ? $supplier : null]);

            $alerted = 0;
            if ($qty > 0 && ($old === null || $old === 0)) {
                $alerted = trigger_alerts($pdo, $drugId, $fid);
            }
            $pdo->commit();

            $msg = 'Stock updated for ' . $drug['name'] . '.';
            if ($alerted > 0) $msg .= ' ' . $alerted . ' patient' . ($alerted === 1 ? ' was' : 's were') . ' alerted.';
            if ($qty === 0) $msg .= ' It is now marked out of stock.';
            flash($msg);
            redirect('facility/inventory.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not save the update. Please try again.';
        }
    }
}

$s = $pdo->prepare('SELECT d.name, d.category, d.pack_size, i.quantity, i.last_updated
                    FROM inventory i JOIN drugs_vaccines d ON d.drug_id = i.drug_id
                    WHERE i.facility_id = ? ORDER BY d.name');
$s->execute([$fid]);
$stock = $s->fetchAll();

$s = $pdo->prepare('SELECT d.name, d.pack_size, l.old_quantity, l.new_quantity, l.supplier, l.created_at, u.full_name
                    FROM stock_logs l
                    JOIN drugs_vaccines d ON d.drug_id = l.drug_id
                    LEFT JOIN users u ON u.id = l.user_id
                    WHERE l.facility_id = ? ORDER BY l.log_id DESC LIMIT 10');
$s->execute([$fid]);
$logs = $s->fetchAll();

$title = 'Inventory';
include '../header.php';
?>
<div class="container">
  <h1>Inventory</h1>
  <p class="muted"><?= e($me['facility_name']) ?> &middot; <?= $verified ? badge('Verified', 'green') : badge('Awaiting verification', 'amber') ?></p>

  <?php if (!$verified): ?>
    <div class="alert info">Stock updates are disabled until an administrator verifies your facility.</div>
  <?php endif; ?>

  <div class="panel">
    <h3>Update stock</h3>
    <?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="span-2">
          <label for="drug_id">Drug or vaccine</label>
          <select id="drug_id" name="drug_id" required <?= $verified ? '' : 'disabled' ?>>
            <option value="">Select drug or vaccine</option>
            <?php foreach ($drugs as $d): ?>
              <option value="<?= (int) $d['drug_id'] ?>" <?= (int) ($_POST['drug_id'] ?? 0) === (int) $d['drug_id'] ? 'selected' : '' ?>>
                <?= e($d['name']) ?><?= $d['pack_size'] !== '' ? ' - ' . e($d['pack_size']) : '' ?> (<?= e($d['category']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="quantity">Quantity in stock (number of packs)</label>
          <input id="quantity" type="number" name="quantity" min="0" placeholder="Enter 0 if it has run out" value="<?= e($_POST['quantity'] ?? '') ?>" required <?= $verified ? '' : 'disabled' ?>>
        </div>
        <div>
          <label for="restocked">Last restocked date (optional)</label>
          <input id="restocked" type="date" name="restocked" max="<?= e(date('Y-m-d')) ?>" value="<?= e($_POST['restocked'] ?? '') ?>" <?= $verified ? '' : 'disabled' ?>>
        </div>
        <div class="span-2">
          <label for="supplier">Supplier / source (optional)</label>
          <input id="supplier" type="text" name="supplier" maxlength="150" value="<?= e($_POST['supplier'] ?? '') ?>" <?= $verified ? '' : 'disabled' ?>>
        </div>
      </div>
      <button type="submit" <?= $verified ? '' : 'disabled' ?>>Save inventory update</button>
    </form>
  </div>

  <div class="panel">
    <h3>Current stock</h3>
    <?php if (!$stock): ?>
      <p class="muted">No stock recorded yet.</p>
    <?php else: ?>
      <div class="scroll">
      <table class="data">
        <tr><th>Item</th><th>Type</th><th>Quantity</th><th>Status</th><th>Updated</th></tr>
        <?php foreach ($stock as $r): $q = (int) $r['quantity']; ?>
          <tr>
            <td><?= e($r['name']) ?><?= $r['pack_size'] !== '' ? ' <span class="muted">(' . e($r['pack_size']) . ')</span>' : '' ?></td>
            <td><?= e($r['category']) ?></td>
            <td><?= $q ?></td>
            <td><?= $q === 0 ? badge('Out of stock', 'red') : ($q <= LOW_STOCK_THRESHOLD ? badge('Low', 'amber') : badge('In stock', 'green')) ?></td>
            <td><?= e(fmt_date($r['last_updated'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>Recent stock updates</h3>
    <?php if (!$logs): ?>
      <p class="muted">No updates yet.</p>
    <?php else: ?>
      <div class="scroll">
      <table class="data">
        <tr><th>When</th><th>Item</th><th>Change</th><th>Supplier</th><th>By</th></tr>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td><?= e(fmt_dt($l['created_at'])) ?></td>
            <td><?= e($l['name']) ?><?= $l['pack_size'] !== '' ? ' <span class="muted">(' . e($l['pack_size']) . ')</span>' : '' ?></td>
            <td><?= $l['old_quantity'] === null ? 'New' : (int) $l['old_quantity'] ?> &rarr; <?= (int) $l['new_quantity'] ?></td>
            <td><?= e($l['supplier'] ?? '-') ?></td>
            <td><?= e($l['full_name'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../footer.php'; ?>
