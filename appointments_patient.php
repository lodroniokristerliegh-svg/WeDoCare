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

// Booking happens through the step-by-step wizard. The consultation fee is
// paid AFTER the consultation (see pay_appointment.php); this page reminds the patient.
if (isset($_GET['new'])) {
    header('Location: book_appointment.php'); exit;
}

$stmt = $pdo->prepare("SELECT a.*,
        (SELECT pm.status FROM payments pm WHERE pm.appointment_id = a.appointment_id ORDER BY pm.payment_id DESC LIMIT 1) AS pay_status
        FROM appointments a WHERE a.patient_id = ? ORDER BY a.appt_date DESC, a.appt_time DESC");
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

$pageTitle  = 'My Appointments';
$pageSub    = 'Book online or walk-in consultations';
$activePage = 'appointments';
include __DIR__ . '/includes/layout_top.php';
?>

<?php
$dueTotal = 0; $dueCount = 0;
foreach ($appointments as $a) {
    if ($a['status'] === 'done' && $a['pay_status'] !== 'verified' && $a['pay_status'] !== 'pending') {
        $f = appointment_fee($pdo, $a);
        if ($f > 0) { $dueTotal += $f; $dueCount++; }
    }
}
if ($dueCount): ?>
<div style="background:var(--amber-light);border:1px solid var(--amber);color:var(--text);border-radius:var(--radius);padding:14px 18px;margin-bottom:16px;font-size:14px;">
  <strong>Reminder:</strong> you have <?= $dueCount ?> completed consultation<?= $dueCount === 1 ? '' : 's' ?> with an unpaid fee — <strong>₱<?= number_format($dueTotal, 2) ?></strong> in total. Please settle it using the Pay button below.
</div>
<?php endif; ?>

<div class="toolbar mb-16" style="display:flex;justify-content:space-between;align-items:center;">
  <div style="font-size:13px; color:var(--text2);"><?= count($appointments) ?> appointment<?= count($appointments)===1?'':'s' ?> on record</div>
  <a href="book_appointment.php" class="btn-primary" style="text-decoration:none;">+ Book an Appointment</a>
</div>

<div class="table-card">
  <table class="table">
    <thead><tr><th>Date</th><th>Time</th><th>Type</th><th>Concern</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if ($appointments): foreach ($appointments as $a): ?>
        <tr>
          <td><?= fmt_date($a['appt_date']) ?></td>
          <td><?= fmt_time($a['appt_time']) ?></td>
          <td><?= $a['appt_type']==='online' ? 'Online (Google Meet)' : 'Walk-in' ?></td>
          <td><?= e(str_replace(' [+MedCert]', ' (+ Medical Certificate)', $a['concern'])) ?></td>
          <td><span class="badge <?= badge_class($a['status']) ?>"><?= $a['status']==='confirmed' ? 'Accepted' : ucfirst($a['status']) ?></span></td>
          <td>
            <?php $fee = appointment_fee($pdo, $a); ?>
            <?php if ($a['status'] === 'pending'): ?>
              <span class="text-sm text-muted">Waiting for the doctor to accept</span>
            <?php elseif ($a['status'] === 'confirmed'): ?>
              <?php if ($a['appt_type']==='online'): ?>
                <?php if (!empty($a['gmeet_link']) && !valid_link($a['gmeet_link'])): ?>
                  <span class="text-sm text-muted">Link needs fixing — please contact the clinic</span>
                <?php elseif (!empty($a['gmeet_link'])): ?>
                  <a class="gmeet-btn" href="<?= e($a['gmeet_link']) ?>" target="_blank" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                    Join Meet
                  </a>
                <?php else: ?>
                  <span class="text-sm text-muted">Accepted — Meet link coming soon</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-sm text-muted">Accepted — see you at the clinic</span>
              <?php endif; ?>
              <?php if ($fee > 0): ?><div class="text-sm text-muted" style="margin-top:4px;">Fee ₱<?= number_format($fee, 2) ?> — payable after your consultation</div><?php endif; ?>
            <?php elseif ($a['status'] === 'done'): ?>
              <?php if ($a['pay_status'] === 'verified'): ?>
                <span class="text-sm text-muted">Paid</span>
              <?php elseif ($a['pay_status'] === 'pending'): ?>
                <span class="text-sm text-muted">Payment being verified</span>
              <?php elseif ($fee > 0): ?>
                <a class="btn-primary" href="pay_appointment.php?id=<?= (int) $a['appointment_id'] ?>" style="text-decoration:none;padding:6px 14px;font-size:12.5px;">
                  <?= $a['pay_status'] === 'rejected' ? 'Payment rejected — Pay Again' : 'Pay ₱' . number_format($fee, 2) ?>
                </a>
              <?php else: ?>
                <span class="text-sm text-muted">Completed</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <?= empty_row(6, 'No appointments yet.') ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
