<?php
require '../config.php';
require_role('patient');
$me  = current_user();
$uid = $me['id'];

$s = $pdo->prepare('SELECT status, COUNT(*) FROM alerts WHERE user_id = ? GROUP BY status');
$s->execute([$uid]);
$byStatus  = $s->fetchAll(PDO::FETCH_KEY_PAIR);
$waiting   = (int) ($byStatus['pending'] ?? 0);
$available = (int) ($byStatus['available'] ?? 0);
$total     = $waiting + $available + (int) ($byStatus['cancelled'] ?? 0);

$s = $pdo->prepare("SELECT d.name AS drug, f.name AS facility, f.location, n.sent_at
                    FROM notifications n
                    JOIN alerts a ON a.alert_id = n.alert_id
                    JOIN drugs_vaccines d ON d.drug_id = a.drug_id
                    LEFT JOIN facilities f ON f.facility_id = a.facility_id
                    WHERE a.user_id = ?
                    ORDER BY n.sent_at DESC LIMIT 5");
$s->execute([$uid]);
$recent = $s->fetchAll();

$title = 'Dashboard';
include '../header.php';
?>
<div class="container">
  <section class="hero">
    <h1>Hello, <?= e(first_name($me['full_name'])) ?></h1>
    <form method="get" action="<?= e(url('patient/search.php')) ?>" class="hero-search">
      <?= icon('search', 20) ?>
      <input type="text" name="q" placeholder="Search drug or vaccine" aria-label="Search drug or vaccine">
      <button type="submit">Search</button>
    </form>
  </section>

  <div class="stats">
    <div class="stat"><span class="stat-ic amber"><?= icon('clock', 24) ?></span><div><div class="num"><?= $waiting ?></div><div class="lbl">Alerts waiting for stock</div></div></div>
    <div class="stat"><span class="stat-ic green"><?= icon('check', 24) ?></span><div><div class="num"><?= $available ?></div><div class="lbl">Drugs now available</div></div></div>
    <div class="stat"><span class="stat-ic blue"><?= icon('bell', 24) ?></span><div><div class="num"><?= $total ?></div><div class="lbl">Alerts requested in total</div></div></div>
  </div>

  <div class="panel">
    <div class="row"><h3>Latest notifications</h3><a href="<?= e(url('patient/alerts.php')) ?>">View all alerts</a></div>
    <?php if (!$recent): ?>
      <div class="empty">
        <?= icon('inbox', 36) ?>
        <p>No notifications yet.<br>Search for a drug that is out of stock and choose <strong>Notify me when available</strong>.</p>
      </div>
    <?php else: foreach ($recent as $r): ?>
      <div class="notice">
        <span class="notice-ic"><?= icon('check', 18) ?></span>
        <div>
          <strong><?= e($r['drug']) ?> is now in stock</strong>
          <div class="muted"><?= e($r['facility'] ?? 'A facility') ?><?= $r['location'] ? ' - ' . e($r['location']) : '' ?> &middot; <?= e(fmt_dt($r['sent_at'])) ?></div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php include '../footer.php'; ?>
