<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';

// Safe even if includes/helpers.php is an older copy without valid_link().
if (!function_exists('valid_link')) {
    function valid_link($url) {
        return is_string($url) && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL);
    }
}
require_role('patient');

$patientId = $_SESSION['patient_id'];

$patient = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
$patient->execute([$patientId]);
$patient = $patient->fetch();
if (!$patient) {
    header('Location: login.php?accountmissing=1');
    exit;
}

$upcoming = $pdo->prepare("SELECT a.*,
        (SELECT pm.status FROM payments pm WHERE pm.appointment_id = a.appointment_id ORDER BY pm.payment_id DESC LIMIT 1) AS pay_status
        FROM appointments a WHERE a.patient_id = ? AND a.status != 'done'
        ORDER BY a.appt_date, a.appt_time");
$upcoming->execute([$patientId]);
$upcoming = $upcoming->fetchAll();

// Completed consultations whose fee has not been paid yet -> reminder banner
$dueStmt = $pdo->prepare("SELECT a.* FROM appointments a
        WHERE a.patient_id = ? AND a.status = 'done'
          AND NOT EXISTS (SELECT 1 FROM payments pm WHERE pm.appointment_id = a.appointment_id AND pm.status IN ('pending','verified'))
        ORDER BY a.appt_date DESC");
$dueStmt->execute([$patientId]);
$dueList = []; $dueTotal = 0;
foreach ($dueStmt->fetchAll() as $d) {
    $f = appointment_fee($pdo, $d);
    if ($f > 0) { $dueList[] = $d; $dueTotal += $f; }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?"); $stmt->execute([$patientId]);
$rxCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE patient_id = ? AND status = 'pending'"); $stmt->execute([$patientId]);
$pendingPay = (int) $stmt->fetchColumn();

$pageTitle  = 'My Dashboard';
$pageSub    = date('l, F j, Y');
$activePage = 'home';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="pp-hero">
  <div>
    <div class="pp-hero-title">Welcome back, <?= e($patient['first_name']) ?>!</div>
    <div class="pp-hero-sub">Here's a quick overview of your healthcare records with Dr. Deguito.</div>
    <div style="margin-top:16px;">
      <a href="book_appointment.php" class="btn-primary" style="background:#fff;color:var(--navy);text-decoration:none;font-weight:700;">
        Book an Appointment
      </a>
    </div>
  </div>
</div>

<div class="pp-section-title" style="margin:4px 0 12px;">Quick Access</div>
<div class="pp-quick-grid">
  <a class="pp-quick-card" href="appointments_patient.php" style="text-decoration:none;color:inherit;">
    <div class="pp-quick-ico" style="background:var(--blue-light); color:var(--navy-light);">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </div>
    <div class="pp-quick-label"><?= count($upcoming) ?></div>
    <div class="pp-quick-sub">Upcoming appointment<?= count($upcoming)===1?'':'s' ?></div>
  </a>
  <a class="pp-quick-card" href="prescriptions_patient.php" style="text-decoration:none;color:inherit;">
    <div class="pp-quick-ico" style="background:var(--teal-light); color:var(--teal);">
      <svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg>
    </div>
    <div class="pp-quick-label"><?= $rxCount ?></div>
    <div class="pp-quick-sub">E-Prescription<?= $rxCount===1?'':'s' ?> on file</div>
  </a>
  <a class="pp-quick-card" href="labs_patient.php" style="text-decoration:none;color:inherit;">
    <div class="pp-quick-ico" style="background:var(--purple-light); color:var(--purple);">
      <svg viewBox="0 0 24 24"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v11m0 0H5m4 0h10m0 0V9m0 6v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-6"/></svg>
    </div>
    <div class="pp-quick-label" style="font-size:18px;">Lab Results &amp; Referrals</div>
    <div class="pp-quick-sub">Open laboratory requests and available results</div>
  </a>
  <a class="pp-quick-card" href="payments_patient.php" style="text-decoration:none;color:inherit;">
    <div class="pp-quick-ico" style="background:var(--amber-light); color:var(--amber);">
      <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    </div>
    <div class="pp-quick-label"><?= $pendingPay ?></div>
    <div class="pp-quick-sub">Pending payment<?= $pendingPay===1?'':'s' ?></div>
  </a>
</div>

<?php if ($dueList): ?>
<div style="background:var(--amber-light);border:1px solid var(--amber);border-radius:var(--radius);padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
  <div style="font-size:14px;color:var(--text);">
    <strong>Consultation fee reminder:</strong> you have an unpaid fee of <strong>₱<?= number_format($dueTotal, 2) ?></strong>
    for <?= count($dueList) ?> completed consultation<?= count($dueList) === 1 ? '' : 's' ?>.
  </div>
  <a class="btn-primary" href="<?= count($dueList) === 1 ? 'pay_appointment.php?id=' . (int) $dueList[0]['appointment_id'] : 'appointments_patient.php' ?>" style="text-decoration:none;padding:8px 16px;font-size:13px;">Pay Now</a>
</div>
<?php endif; ?>

<div class="pp-section-title">Upcoming Appointments</div>
<?php if ($upcoming): foreach ($upcoming as $a): ?>
  <div class="pp-appt-row">
    <div class="pp-appt-date">
      <div class="pp-appt-day"><?= date('j', strtotime($a['appt_date'])) ?></div>
      <div class="pp-appt-mon"><?= date('M', strtotime($a['appt_date'])) ?></div>
    </div>
    <div class="pp-appt-info">
      <div class="pp-appt-type"><?= $a['appt_type']==='online' ? 'Online Consultation (Google Meet)' : 'Walk-in Consultation' ?></div>
      <div class="pp-appt-meta"><?= fmt_time($a['appt_time']) ?> &middot; <?= e($a['concern']) ?></div>
    </div>
    <?php if ($a['status'] === 'pending'): ?>
      <span class="text-sm text-muted" style="margin-right:10px;">Waiting for the doctor</span>
    <?php elseif ($a['status'] === 'confirmed' && $a['appt_type']==='online' && valid_link($a['gmeet_link'])): ?>
      <a class="gmeet-btn" href="<?= e($a['gmeet_link']) ?>" target="_blank" style="text-decoration:none;margin-right:10px;">
        <svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
        Join Meet
      </a>
    <?php endif; ?>
    <span class="badge <?= badge_class($a['status']) ?>"><?= $a['status']==='confirmed' ? 'Accepted' : ucfirst($a['status']) ?></span>
  </div>
<?php endforeach; else: ?>
  <div class="empty"><div class="empty-icon">📅</div><div class="empty-text">No upcoming appointments. Click "Book Appointment" to schedule one.</div></div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
