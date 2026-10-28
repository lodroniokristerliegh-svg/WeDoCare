<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

function dist_bar($label, $pct, $color) {
    $pct = round($pct, 1);
    return '<div style="margin-bottom:6px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
        <span>' . e($label) . '</span><span style="font-weight:600">' . $pct . '%</span>
      </div>
      <div style="background:var(--bg);border-radius:4px;height:8px;overflow:hidden">
        <div style="width:' . $pct . '%;background:' . $color . ';height:100%;border-radius:4px;"></div>
      </div>
    </div>';
}

$consultsThisMonth = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='done' AND MONTH(appt_date)=MONTH(CURDATE()) AND YEAR(appt_date)=YEAR(CURDATE())")->fetchColumn();
$rxThisMonth        = (int) $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE MONTH(date_issued)=MONTH(CURDATE()) AND YEAR(date_issued)=YEAR(CURDATE())")->fetchColumn();
$labsThisMonth      = (int) $pdo->query("SELECT COUNT(*) FROM lab_referrals WHERE MONTH(date_issued)=MONTH(CURDATE()) AND YEAR(date_issued)=YEAR(CURDATE())")->fetchColumn();
$revenueThisMonth   = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn();

$walkinCount = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE appt_type='walkin' AND MONTH(appt_date)=MONTH(CURDATE())")->fetchColumn();
$onlineCount = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE appt_type='online' AND MONTH(appt_date)=MONTH(CURDATE())")->fetchColumn();
$typeTotal = max(1, $walkinCount + $onlineCount);

$cashAmt  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE method='Cash'  AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn();
$gcashAmt = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE method='GCash' AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn();
$mayaAmt  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE method='Maya'  AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn();
$methodTotal = max(0.01, $cashAmt + $gcashAmt + $mayaAmt);

$weekLabels = []; $weekVals = [];
for ($i = 5; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $weekLabels[] = date('D', strtotime($d));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appt_date = ? AND status='done'");
    $stmt->execute([$d]);
    $weekVals[] = (int) $stmt->fetchColumn();
}
$maxVal = max(1, max($weekVals));

$pageTitle  = 'Reports';
$pageSub    = 'Analytics and statistical summaries';
$activePage = 'reports';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="card mb-16">
  <div class="card-head"><div class="card-title">Monthly Overview — <?= date('F Y') ?></div></div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr)">
    <div style="text-align:center;padding:20px 16px">
      <div style="font-size:36px;font-weight:700;color:var(--navy)"><?= $consultsThisMonth ?></div>
      <div class="text-sm text-muted" style="margin-top:4px">Total Consultations</div>
    </div>
    <div style="text-align:center;padding:20px 16px;border-left:1px solid var(--border)">
      <div style="font-size:36px;font-weight:700;color:var(--teal)"><?= $rxThisMonth ?></div>
      <div class="text-sm text-muted" style="margin-top:4px">Prescriptions Issued</div>
    </div>
    <div style="text-align:center;padding:20px 16px;border-left:1px solid var(--border)">
      <div style="font-size:36px;font-weight:700;color:var(--amber)"><?= $labsThisMonth ?></div>
      <div class="text-sm text-muted" style="margin-top:4px">Lab Referrals</div>
    </div>
    <div style="text-align:center;padding:20px 16px;border-left:1px solid var(--border)">
      <div style="font-size:36px;font-weight:700;color:var(--green)">₱<?= number_format($revenueThisMonth/1000, 1) ?>k</div>
      <div class="text-sm text-muted" style="margin-top:4px">Revenue Collected</div>
    </div>
  </div>
</div>

<div class="grid-2 mb-16">
  <div class="card">
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
    <div class="card-head"><div class="card-title">Consultation Type Distribution (This Month)</div></div>
    <div style="display:flex;flex-direction:column;gap:12px;padding:8px 0">
      <?= dist_bar('Walk-in', $walkinCount/$typeTotal*100, 'var(--navy-light)') ?>
      <?= dist_bar('Online (Google Meet)', $onlineCount/$typeTotal*100, 'var(--teal)') ?>
    </div>
    <div style="margin-top:16px">
      <div class="card-head" style="margin-bottom:10px"><div class="card-title">Payment Methods (This Month)</div></div>
      <?= dist_bar('Cash',  $cashAmt/$methodTotal*100,  'var(--amber)') ?>
      <?= dist_bar('GCash', $gcashAmt/$methodTotal*100, 'var(--navy-light)') ?>
      <?= dist_bar('Maya',  $mayaAmt/$methodTotal*100,  'var(--teal)') ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
