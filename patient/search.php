<?php
require '../config.php';
require_role('patient');
$uid = current_user()['id'];

/* ---- Ask for an alert on an out-of-stock drug ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $drugId = (int) ($_POST['drug_id'] ?? 0);
    $back = 'patient/search.php?' . http_build_query([
        'q' => $_POST['q'] ?? '', 'category' => $_POST['category'] ?? '', 'in_stock' => $_POST['in_stock'] ?? '',
    ]);

    $s = $pdo->prepare('SELECT name FROM drugs_vaccines WHERE drug_id = ?');
    $s->execute([$drugId]);
    $drug = $s->fetch();
    if (!$drug) {
        flash('That drug or vaccine was not found.', 'error');
        redirect($back);
    }

    $s = $pdo->prepare("SELECT alert_id FROM alerts WHERE user_id = ? AND drug_id = ? AND status = 'pending'");
    $s->execute([$uid, $drugId]);
    if ($s->fetch()) {
        flash('You already have an alert waiting for ' . $drug['name'] . '.', 'info');
    } else {
        $pdo->prepare("INSERT INTO alerts (user_id, drug_id, facility_id, status) VALUES (?, ?, NULL, 'pending')")
            ->execute([$uid, $drugId]);
        flash('Alert created. You will be notified when ' . $drug['name'] . ' is back in stock.');
    }
    redirect($back);
}

/* ---- Search ---- */
$searched = isset($_GET['q']);
$q        = trim($_GET['q'] ?? '');
$cat      = $_GET['category'] ?? '';
$inStock  = !empty($_GET['in_stock']);
$drugs = [];
$stock = [];
$myPending = [];

if ($searched) {
    $sql = 'SELECT drug_id, name, category, manufacturer, pack_size FROM drugs_vaccines WHERE 1=1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (name LIKE ? OR manufacturer LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if (in_array($cat, ['Drug', 'Vaccine'], true)) {
        $sql .= ' AND category = ?';
        $params[] = $cat;
    }
    $sql .= ' ORDER BY name LIMIT 50';
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $drugs = $s->fetchAll();

    if ($drugs) {
        $ids = array_column($drugs, 'drug_id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $s = $pdo->prepare("SELECT i.drug_id, i.quantity, i.last_updated,
                                   f.facility_id, f.name, f.type, f.location,
                                   (SELECT u.phone FROM users u WHERE u.facility_id = f.facility_id AND u.role = 'facility_staff' AND u.is_active = 1 ORDER BY u.id LIMIT 1) AS contact_phone,
                                   (SELECT u.email FROM users u WHERE u.facility_id = f.facility_id AND u.role = 'facility_staff' AND u.is_active = 1 ORDER BY u.id LIMIT 1) AS contact_email
                            FROM inventory i
                            JOIN facilities f ON f.facility_id = i.facility_id
                            WHERE f.is_verified = 1 AND i.quantity > 0 AND i.drug_id IN ($ph)
                            ORDER BY i.quantity DESC");
        $s->execute($ids);
        foreach ($s->fetchAll() as $row) {
            $stock[$row['drug_id']][] = $row;
        }
        if ($inStock) {
            $drugs = array_values(array_filter($drugs, fn($d) => !empty($stock[$d['drug_id']])));
        }
    }

    $s = $pdo->prepare("SELECT drug_id FROM alerts WHERE user_id = ? AND status = 'pending'");
    $s->execute([$uid]);
    $myPending = array_flip($s->fetchAll(PDO::FETCH_COLUMN));
}

$title = 'Search Drugs & Vaccines';
include '../header.php';
?>
<div class="container">
  <h1>Search drugs and vaccines</h1>

  <div class="panel">
    <form method="get" class="filters">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search drug or vaccine" aria-label="Search drug or vaccine">
      <select name="category" aria-label="Category">
        <option value="">All types</option>
        <option value="Drug" <?= $cat === 'Drug' ? 'selected' : '' ?>>Drugs</option>
        <option value="Vaccine" <?= $cat === 'Vaccine' ? 'selected' : '' ?>>Vaccines</option>
      </select>
      <label class="inline-label"><input type="checkbox" name="in_stock" value="1" <?= $inStock ? 'checked' : '' ?>> In stock only</label>
      <button type="submit" class="small">Search</button>
    </form>
  </div>

  <?php if (!$searched): ?>
  <?php elseif (!$drugs): ?>
    <div class="panel"><div class="empty"><?= icon('search', 36) ?><p>No matching drugs or vaccines were found.<br>Try a shorter name or clear the filters.</p></div></div>
  <?php else: ?>
    <p class="muted"><?= count($drugs) ?> result<?= count($drugs) === 1 ? '' : 's' ?></p>
    <?php foreach ($drugs as $d):
      $facilities = $stock[$d['drug_id']] ?? []; ?>
      <div class="item">
        <div class="row">
          <div>
            <h3><?= e($d['name']) ?><?= $d['pack_size'] !== '' ? ' <span class="muted">(' . e($d['pack_size']) . ')</span>' : '' ?></h3>
            <span class="muted"><?= e($d['category']) ?><?= $d['manufacturer'] !== '' ? ' &middot; ' . e($d['manufacturer']) : '' ?></span>
          </div>
          <?= $facilities ? badge('In stock', 'green') : badge('Out of stock', 'red') ?>
        </div>

        <?php if ($facilities): foreach ($facilities as $f): ?>
          <div class="sub-item">
            <strong><?= e($f['name']) ?></strong> (<?= e(facility_type_label($f['type'])) ?>) - <?= e($f['location']) ?><br>
            <span class="muted">Packs available: <?= (int) $f['quantity'] ?> &middot; Updated <?= e(fmt_date($f['last_updated'])) ?></span><br>
            <span class="muted"><?= contact_line($f['contact_phone'], $f['contact_email']) ?></span>
          </div>
        <?php endforeach; else: ?>
          <div class="sub-item">
            <?php if (isset($myPending[$d['drug_id']])): ?>
              <?= badge('Alert requested', 'blue') ?> <span class="muted">You will be notified when it is back in stock.</span>
            <?php else: ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="drug_id" value="<?= (int) $d['drug_id'] ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="category" value="<?= e($cat) ?>">
                <input type="hidden" name="in_stock" value="<?= $inStock ? '1' : '' ?>">
                <button type="submit" class="small">Notify me when available</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include '../footer.php'; ?>
