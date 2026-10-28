<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';

// Safe even if includes/helpers.php is an older copy without valid_link().
if (!function_exists('valid_link')) {
    function valid_link($url) {
        return is_string($url) && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL);
    }
}
require_role('doctor');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'set_link') {
    $id   = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
    $link = trim(isset($_POST['gmeet_link']) ? $_POST['gmeet_link'] : '');
    if ($link !== '' && !preg_match('#^https?://#i', $link)) {
        // Accept "meet.google.com/abc-defg-hij" by adding https://, reject anything else.
        if (preg_match('#^(meet\.google\.com/|www\.|[a-z0-9-]+(\.[a-z0-9-]+)+/)#i', $link)) {
            $link = 'https://' . $link;
        }
    }
    if ($link !== '' && !valid_link($link)) {
        set_flash('That link is not valid. It must start with https:// (example: https://meet.google.com/abc-defg-hij).');
        header('Location: consult_doctor.php'); exit;
    }
    $pdo->prepare('UPDATE appointments SET gmeet_link = ? WHERE appointment_id = ?')->execute([$link ?: null, $id]);
    set_flash('Google Meet link saved.');
    header('Location: consult_doctor.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'done_consult') {
    $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
    // Finish the consultation and delete the Meet link so it can't be reused.
    $pdo->prepare("UPDATE appointments SET status = 'done', gmeet_link = NULL WHERE appointment_id = ?")->execute([$id]);
    set_flash('Consultation marked as done. The Meet link was deleted and the patient is reminded to pay the consultation fee.');
    header('Location: consult_doctor.php'); exit;
}

$stmt = $pdo->query("SELECT a.*, p.first_name, p.last_name, p.contact_no FROM appointments a
                      JOIN patients p ON p.patient_id = a.patient_id
                      WHERE a.appt_type = 'online' AND a.status != 'cancelled'
                      ORDER BY a.appt_date, a.appt_time");
$onlineAppts = $stmt->fetchAll();

$pageTitle  = 'Online Consults';
$pageSub    = 'Google Meet consultation links and schedule';
$activePage = 'consult';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <div class="card-head">
    <div class="card-title">Online Consultation Schedule</div>
    <a class="card-link" href="https://meet.google.com/new" target="_blank" style="text-decoration:none;">+ Create a new Google Meet room ↗</a>
  </div>

  <?php if ($onlineAppts): foreach ($onlineAppts as $a): $name = $a['first_name'].' '.$a['last_name']; $isToday = $a['appt_date'] === date('Y-m-d'); ?>
  <div class="appt-item" style="align-items:flex-start;">
    <?= avatar_html($name) ?>
    <div class="appt-info" style="flex:1;">
      <div class="appt-name"><?= e($name) ?> <?php if ($isToday): ?><span class="badge b-online" style="margin-left:6px;">Today</span><?php endif; ?></div>
      <div class="appt-detail"><?= fmt_date($a['appt_date']) ?> &middot; <?= fmt_time($a['appt_time']) ?> &middot; <?= e($a['concern']) ?></div>
      <?php if ($a['status'] === 'done'): ?>
        <div class="text-sm text-muted mt-8">Consultation completed — Meet link deleted.</div>
      <?php else: ?>
      <form method="post" action="consult_doctor.php" class="flex gap-8 mt-8" style="align-items:center;">
          <input type="hidden" name="action" value="set_link">
          <input type="hidden" name="id" value="<?= $a['appointment_id'] ?>">
          <input type="text" class="form-control" name="gmeet_link" placeholder="https://meet.google.com/xxx-xxxx-xxx"
                 value="<?= e($a['gmeet_link']) ?>" style="max-width:280px;font-size:12px;">
          <button type="submit" class="btn-outline" style="font-size:12px;">Save Link</button>
          <?php if (valid_link($a['gmeet_link'])): ?>
          <a class="gmeet-btn" href="<?= e($a['gmeet_link']) ?>" target="_blank" style="text-decoration:none;">
            <svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
            Join Meet
          </a>
          <?php endif; ?>
        </form>
      <form method="post" action="consult_doctor.php" class="mt-8" onsubmit="return confirm('Mark this consultation as done? The Google Meet link will be deleted.');">
        <input type="hidden" name="action" value="done_consult">
        <input type="hidden" name="id" value="<?= $a['appointment_id'] ?>">
        <button type="submit" class="btn-outline" style="font-size:12px;color:var(--green);">&#10003; Done Consult</button>
      </form>
      <?php endif; ?>
    </div>
    <span class="badge <?= badge_class($a['status']) ?>"><?= ucfirst($a['status']) ?></span>
  </div>
  <?php endforeach; else: ?>
    <div class="empty"><div class="empty-icon">📹</div><div class="empty-text">No online consultations scheduled</div></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
