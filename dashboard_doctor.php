<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

$today = date('Y-m-d');

$todayAppts = $pdo->prepare("SELECT a.*, p.first_name, p.last_name FROM appointments a
                              JOIN patients p ON p.patient_id = a.patient_id
                              WHERE a.appt_date = ? ORDER BY a.appt_time");
$todayAppts->execute([$today]);
$todayAppts = $todayAppts->fetchAll();

$totalPatients   = (int) $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$rxThisMonth     = (int) $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE MONTH(date_issued)=MONTH(CURDATE()) AND YEAR(date_issued)=YEAR(CURDATE())")->fetchColumn();
$pendingPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();

// Weekly consultation counts (last 6 days incl. today) for the mini bar chart.
$weekLabels = []; $weekVals = [];
for ($i = 5; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $weekLabels[] = date('D', strtotime($d));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appt_date = ? AND status='done'");
    $stmt->execute([$d]);
    $weekVals[] = (int) $stmt->fetchColumn();
}
$maxVal = max(1, max($weekVals));

$recentRx = $pdo->query("SELECT pr.*, p.first_name, p.last_name FROM prescriptions pr
                          JOIN patients p ON p.patient_id = pr.patient_id
                          ORDER BY pr.created_at DESC LIMIT 4")->fetchAll();

$recentPatients = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT 4")->fetchAll();

$pageTitle  = 'Dashboard';
$pageSub    = date('l, F j, Y');
$activePage = 'dashboard';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="doctor-dashboard-intro mb-20">
  <h2 style="margin:0 0 6px;font-size:22px;line-height:1.35;color:var(--text);">Your Practice at a Glance</h2>
  <p style="margin:0;color:var(--text2);font-size:15px;line-height:1.6;">Review today’s appointments, patient records, prescriptions, and payment tasks from one place.</p>
</div>

<div class="grid-4 mb-20">
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--blue-light)">
      <svg viewBox="0 0 24 24" style="color:var(--navy-light)"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </div>
    <div class="stat-label">Today's Appointments</div>
    <div class="stat-value"><?= count($todayAppts) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--teal-light)">
      <svg viewBox="0 0 24 24" style="color:var(--teal)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    </div>
    <div class="stat-label">Total Patients</div>
    <div class="stat-value"><?= $totalPatients ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--amber-light)">
      <svg viewBox="0 0 24 24" style="color:var(--amber)"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg>
    </div>
    <div class="stat-label">Prescriptions This Month</div>
    <div class="stat-value"><?= $rxThisMonth ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--red-light)">
      <svg viewBox="0 0 24 24" style="color:var(--red)"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    </div>
    <div class="stat-label">Pending Payments</div>
    <div class="stat-value"><?= $pendingPayments ?></div>
    <?php if ($pendingPayments): ?><div class="stat-change down">⚠ Needs verification</div><?php endif; ?>
  </div>
</div>

<div style="margin:0 0 10px;font-size:18px;font-weight:700;color:var(--text);">Quick Actions</div>
<div class="qa-grid mb-20">
  <a class="qa-btn" href="patients_doctor.php" style="text-decoration:none;color:inherit;">
    <div class="qa-icon">👥</div><div class="qa-label">View Patients</div>
  </a>
  <a class="qa-btn" href="patients_doctor.php" style="text-decoration:none;color:inherit;">
    <div class="qa-icon">💊</div><div class="qa-label">New E-Prescription</div>
  </a>
  <a class="qa-btn" href="lab_referrals_doctor.php" style="text-decoration:none;color:inherit;">
    <div class="qa-icon">🔬</div><div class="qa-label">Lab Referral</div>
  </a>
  <a class="qa-btn" href="consult_doctor.php" style="text-decoration:none;color:inherit;">
    <div class="qa-icon">📹</div><div class="qa-label">Start Consultation</div>
  </a>
</div>

<div class="grid-2 mb-16">
  <div class="card">
    <div class="card-head">
      <div class="card-title">Today's Appointments</div>
    </div>
    <?php if ($todayAppts): ?>
      <?php foreach ($todayAppts as $a): $name = $a['first_name'].' '.$a['last_name']; ?>
      <div class="appt-item">
        <?= avatar_html($name) ?>
        <div class="appt-info">
          <div class="appt-name"><?= e($name) ?></div>
          <div class="appt-detail"><?= e($a['concern']) ?></div>
        </div>
        <div class="appt-right">
          <div class="appt-time"><?= fmt_time($a['appt_time']) ?></div>
          <span class="badge <?= $a['appt_type']==='online'?'b-online':'b-walkin' ?>"><?= $a['appt_type']==='online'?'Online':'Walk-in' ?></span><br>
          <span class="badge <?= badge_class($a['status']) ?>" style="margin-top:2px"><?= ucfirst($a['status']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty"><div class="empty-icon">📅</div><div class="empty-text">No appointments today</div></div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card mb-16">
      <div class="card-head"><div class="card-title">Completed Consultations — Last 6 Days</div></div>
      <div class="bars-wrap">
        <?php foreach ($weekLabels as $i => $lbl): ?>
        <div class="bar-col">
          <div class="bar<?= $i===5?' today':'' ?>" style="height:<?= round(($weekVals[$i]/$maxVal)*80) ?>px"></div>
          <div class="bar-lbl"><?= e($lbl) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="chart-legend">
        <span><span class="legend-dot" style="background:var(--navy-light)"></span>Today</span>
        <span><span class="legend-dot" style="background:#B5D4F4"></span>Past days</span>
      </div>
    </div>
    <div class="card">
      <div class="card-head">
        <div class="card-title">Recent Prescriptions</div>
        <a class="card-link" href="patients_doctor.php" style="text-decoration:none;">Open patients</a>
      </div>
      <?php if ($recentRx): foreach ($recentRx as $rx): ?>
        <div class="appt-item">
          <div class="avatar av-amber" style="width:36px;height:36px;font-size:14px;">💊</div>
          <div class="appt-info">
            <div class="appt-name"><?= e($rx['first_name'].' '.$rx['last_name']) ?></div>
            <div class="appt-detail">Issued <?= fmt_date($rx['date_issued']) ?></div>
          </div>
          <span class="badge <?= badge_class($rx['status']) ?>"><?= ucfirst($rx['status']) ?></span>
        </div>
      <?php endforeach; else: ?>
        <div class="empty"><div class="empty-icon">💊</div><div class="empty-text">No prescriptions yet</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div class="card-title">Recent Patients</div>
    <a class="card-link" href="patients_doctor.php" style="text-decoration:none;">See all patients →</a>
  </div>
  <div class="pt-grid">
    <?php if ($recentPatients): foreach ($recentPatients as $p): $full = $p['first_name'].' '.$p['last_name']; ?>
      <div class="pt-item">
        <?= avatar_html($full) ?>
        <div style="flex:1;min-width:0">
          <div class="pt-name"><?= e($full) ?></div>
          <div class="pt-meta"><?= e($p['gender'][0]) ?>, <?= calc_age($p['dob']) ?> &middot; <?= e(mb_substr($p['history'] ?: 'New patient', 0, 28)) ?>...</div>
        </div>
        <a class="mini-btn" href="patients_doctor.php?view=<?= $p['patient_id'] ?>" style="text-decoration:none;">View</a>
      </div>
    <?php endforeach; else: ?>
      <div class="empty"><div class="empty-icon">👥</div><div class="empty-text">No patients yet</div></div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
