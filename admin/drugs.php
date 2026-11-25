<?php
require '../config.php';
require_role('admin');
$errors = [];
$name = $manufacturer = $pack = '';
$category = 'Drug';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name         = trim($_POST['name'] ?? '');
        $category     = $_POST['category'] ?? '';
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $pack         = trim($_POST['pack_size'] ?? '');

        if ($name === '' || strlen($name) > 150) $errors[] = 'Enter a drug or vaccine name (up to 150 characters).';
        if (!in_array($category, ['Drug', 'Vaccine'], true)) $errors[] = 'Choose a category.';
        if (strlen($pack) > 50) $errors[] = 'Pack size is too long (up to 50 characters).';
        if (strlen($manufacturer) > 150) $errors[] = 'Manufacturer name is too long.';

        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO drugs_vaccines (name, category, manufacturer, pack_size) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $category, $manufacturer, $pack]);
                flash($name . ' added to the catalog.');
                redirect('admin/drugs.php');
            } catch (PDOException $ex) {
                $errors[] = ($ex->getCode() === '23000')
                    ? 'That drug or vaccine (with the same manufacturer and pack size) is already in the catalog.'
                    : 'Could not add the item. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM drugs_vaccines WHERE drug_id = ?')->execute([(int) ($_POST['drug_id'] ?? 0)]);
        flash('Item removed from the catalog.');
        redirect('admin/drugs.php');
    }
}

$drugs = $pdo->query("SELECT d.drug_id, d.name, d.category, d.manufacturer, d.pack_size,
                             (SELECT COUNT(*) FROM inventory i WHERE i.drug_id = d.drug_id AND i.quantity > 0) AS facilities_in_stock
                      FROM drugs_vaccines d ORDER BY d.name")->fetchAll();

$title = 'Drug Catalog';
include '../header.php';
?>
<div class="container">
  <h1>Drug and vaccine catalog</h1>

  <div class="panel">
    <h3>Add an item</h3>
    <?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label for="name">Name (include strength, e.g. Amoxicillin 500mg)</label>
      <input id="name" type="text" name="name" value="<?= e($name) ?>" maxlength="150" required>
      <label for="category">Category</label>
      <select id="category" name="category">
        <option value="Drug" <?= $category === 'Drug' ? 'selected' : '' ?>>Drug</option>
        <option value="Vaccine" <?= $category === 'Vaccine' ? 'selected' : '' ?>>Vaccine</option>
      </select>
      <label for="manufacturer">Manufacturer (optional)</label>
      <input id="manufacturer" type="text" name="manufacturer" value="<?= e($manufacturer) ?>" maxlength="150">
      <label for="pack_size">Pack size (optional, e.g. 10 x 3 = 3 strips of 10 tablets)</label>
      <input id="pack_size" type="text" name="pack_size" value="<?= e($pack) ?>" maxlength="50" placeholder="e.g. 10 x 3">
      <button type="submit">Add to catalog</button>
    </form>
  </div>

  <div class="panel">
    <h3>Catalog (<?= count($drugs) ?>)</h3>
    <?php if (!$drugs): ?>
      <p class="muted">The catalog is empty.</p>
    <?php else: ?>
      <div class="scroll">
      <table class="data">
        <tr><th>Name</th><th>Category</th><th>Pack size</th><th>Manufacturer</th><th>Facilities with stock</th><th></th></tr>
        <?php foreach ($drugs as $d): ?>
          <tr>
            <td><?= e($d['name']) ?></td>
            <td><?= e($d['category']) ?></td>
            <td><?= e($d['pack_size'] !== '' ? $d['pack_size'] : '-') ?></td>
            <td><?= e($d['manufacturer'] !== '' ? $d['manufacturer'] : '-') ?></td>
            <td><?= (int) $d['facilities_in_stock'] ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Remove this item? Its stock records and patient alerts will also be deleted.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="drug_id" value="<?= (int) $d['drug_id'] ?>">
                <button type="submit" class="small btn-danger">Remove</button>
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
