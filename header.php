<?php
$me = current_user();
$GLOBALS['__layout'] = $me ? 'app' : 'auth';
$__title = $title ?? 'Drug & Vaccine Alert System';
$__theme = $me ? ['patient' => 'patient', 'facility_staff' => 'facility', 'admin' => 'admin'][$me['role']] ?? 'guest' : 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__title) ?> | Drug &amp; Vaccine Alert</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%231f2933'/%3E%3Cpath d='M13 6h6v7h7v6h-7v7h-6v-7H6v-6h7z' fill='white'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(url('style.css')) ?>">
</head>
<?php if ($me):
    // Small counters shown next to menu items
    $__alerts = 0; $__pendingFac = 0;
    if ($me['role'] === 'patient') {
        $c = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE user_id = ? AND status = 'available'");
        $c->execute([$me['id']]);
        $__alerts = (int) $c->fetchColumn();
    } elseif ($me['role'] === 'admin') {
        $__pendingFac = (int) $pdo->query('SELECT COUNT(*) FROM facilities WHERE is_verified = 0')->fetchColumn();
    }
?>
<body class="app-body theme-<?= e($__theme) ?>">
<div class="shell">
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="logo" href="<?= e(url(role_home($me['role']))) ?>">Drug &amp; Vaccine Alert System</a>

    <div class="nav-label"><?= e(role_label($me['role'])) ?></div>
    <nav class="side-nav">
    <?php if ($me['role'] === 'patient'): ?>
      <?= nav_link('patient/dashboard.php', 'Dashboard') ?>
      <?= nav_link('patient/search.php', 'Search drugs') ?>
      <?= nav_link('patient/alerts.php', 'My alerts', '', $__alerts) ?>
    <?php elseif ($me['role'] === 'facility_staff'): ?>
      <?= nav_link('facility/dashboard.php', 'Dashboard') ?>
      <?= nav_link('facility/inventory.php', 'Inventory') ?>
    <?php elseif ($me['role'] === 'admin'): ?>
      <?= nav_link('admin/dashboard.php', 'Dashboard') ?>
      <?= nav_link('admin/facilities.php', 'Facilities', '', $__pendingFac) ?>
      <?= nav_link('admin/users.php', 'Users') ?>
      <?= nav_link('admin/drugs.php', 'Drug catalog') ?>
    <?php endif; ?>
    </nav>

    <div class="nav-label">Account</div>
    <nav class="side-nav">
      <?= nav_link('change_password.php', 'Change password') ?>
      <a href="<?= e(url('logout.php')) ?>"><span>Log out</span></a>
    </nav>
  </aside>
  <div class="scrim" id="scrim"></div>

  <div class="content">
    <header class="topbar">
      <button type="button" class="menu-btn" id="menuBtn" aria-label="Open menu" aria-controls="sidebar">Menu</button>
      <span class="crumb"><?= e($__title) ?></span>
      <span class="spacer"></span>
      <div class="user-chip"><strong><?= e($me['full_name']) ?></strong><span><?= $me['facility_name'] ? e($me['facility_name']) : e(role_label($me['role'])) ?></span></div>
    </header>
    <main>
<?= flash() ?>
<?php else:
    $__script = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<body class="auth-body theme-guest">
<div class="auth">
  <header class="auth-top">
    <a class="logo" href="<?= e(url('login.php')) ?>">Drug &amp; Vaccine Alert System</a>
    <span>
      <?php if ($__script === 'register.php'): ?>
        <a href="<?= e(url('login.php')) ?>">Log in</a>
      <?php else: ?>
        <a href="<?= e(url('register.php')) ?>">Register</a>
      <?php endif; ?>
    </span>
  </header>
  <section class="auth-main">
    <div class="auth-box">
<?= flash() ?>
<?php endif; ?>
