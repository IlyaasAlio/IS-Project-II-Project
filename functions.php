<?php
/* ---------- Basic helpers ---------- */
function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function url($path = '') { return BASE_PATH . '/' . ltrim($path, '/'); }

function redirect($path) { header('Location: ' . url($path)); exit; }

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(403);
        die('Invalid request.');
    }
}

function flash($msg = null, $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return; }
    if (!empty($_SESSION['flash'])) {
        [$m, $t] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return "<div class='alert $t'>" . e($m) . "</div>";
    }
    return '';
}

function password_error($p) {
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p))
        return 'Password must contain letters and numbers.';
    return null;
}

/* ---------- Current user, roles ---------- */
function current_user() {
    global $pdo;
    static $user = false;
    if ($user !== false) return $user;
    if (empty($_SESSION['user_id'])) return $user = null;

    $s = $pdo->prepare('SELECT u.*, f.name AS facility_name, f.type AS facility_type,
                               f.location AS facility_location, f.is_verified AS facility_verified
                        FROM users u
                        LEFT JOIN facilities f ON f.facility_id = u.facility_id
                        WHERE u.id = ?');
    $s->execute([$_SESSION['user_id']]);
    $row = $s->fetch();
    return $user = ($row && $row['is_active']) ? $row : null;
}

function role_home($role) {
    switch ($role) {
        case 'admin':          return 'admin/dashboard.php';
        case 'facility_staff': return 'facility/dashboard.php';
        default:               return 'patient/dashboard.php';
    }
}

function role_label($role) {
    switch ($role) {
        case 'admin':          return 'Administrator';
        case 'facility_staff': return 'Facility staff';
        default:               return 'Patient';
    }
}

function require_login() {
    if (!current_user()) {
        $_SESSION = [];
        redirect('login.php');
    }
}

function require_role(...$roles) {
    require_login();
    $me = current_user();
    if (!in_array($me['role'], $roles, true)) {
        flash('You do not have access to that page.', 'error');
        redirect(role_home($me['role']));
    }
}

/* ---------- Display helpers ---------- */
function icon($name, $size = 20) {
    static $paths = [
        'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'pill'     => '<path d="M10.5 20.5a5 5 0 0 1-7-7l10-10a5 5 0 0 1 7 7z"/><path d="M8.5 8.5l7 7"/>',
        'package'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'warning'  => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'x'        => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        'menu'     => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'send'     => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'layers'   => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'inbox'    => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    ];
    return '<svg class="ic" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$name] ?? '') . '</svg>';
}

function initials($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function first_name($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    return $parts[0] !== '' ? $parts[0] : 'there';
}

function nav_link($path, $label, $icon = '', $count = 0) {
    $current = ltrim(substr($_SERVER['SCRIPT_NAME'] ?? '', strlen(BASE_PATH)), '/');
    $active = ($current === $path) ? ' class="active"' : '';
    return '<a href="' . e(url($path)) . '"' . $active . '>'
         . ($icon !== '' ? icon($icon, 20) : '')
         . '<span>' . e($label) . '</span>'
         . ($count > 0 ? '<span class="count">' . (int) $count . '</span>' : '')
         . '</a>';
}

function badge($text, $type = 'grey') {
    return '<span class="badge ' . e($type) . '">' . e($text) . '</span>';
}

function fmt_dt($s) { return $s ? date('d M Y, H:i', strtotime($s)) : '-'; }
function fmt_date($s) { return $s ? date('d M Y', strtotime($s)) : '-'; }

function facility_type_label($t) {
    $map = ['pharmacy' => 'Pharmacy', 'hospital' => 'Hospital', 'clinic' => 'Clinic', 'health_centre' => 'Health centre'];
    return $map[$t] ?? $t;
}

function contact_line($phone, $email) {
    $parts = [];
    if ($phone) $parts[] = 'Phone: ' . e($phone);
    if ($email) $parts[] = 'Email: <a href="mailto:' . e($email) . '">' . e($email) . '</a>';
    return $parts ? implode(' &middot; ', $parts) : 'No contact details on file';
}

/* ---------- Alert engine (NotificationService in the class diagram) ----------
   When a facility has stock of a drug, every pending alert for that drug
   (for any facility, or for this facility) becomes "available" and an
   in-app notification is recorded. Returns how many patients were alerted. */
function trigger_alerts(PDO $pdo, int $drugId, int $facilityId): int {
    $s = $pdo->prepare("SELECT a.alert_id
                        FROM alerts a
                        JOIN users u ON u.id = a.user_id
                        WHERE a.drug_id = ? AND a.status = 'pending' AND u.is_active = 1
                          AND (a.facility_id IS NULL OR a.facility_id = ?)");
    $s->execute([$drugId, $facilityId]);
    $ids = $s->fetchAll(PDO::FETCH_COLUMN);

    $upd = $pdo->prepare("UPDATE alerts SET status = 'available', facility_id = ? WHERE alert_id = ?");
    $ins = $pdo->prepare("INSERT INTO notifications (alert_id, channel, sent_at, delivery_status)
                          VALUES (?, 'in_app', NOW(), 'sent')");
    foreach ($ids as $id) {
        $upd->execute([$facilityId, $id]);
        $ins->execute([$id]);
    }
    return count($ids);
}
