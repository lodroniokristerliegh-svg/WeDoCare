<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

// ---------- Handle actions (add / mark done / delete) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $patientId = (int) (isset($_POST['patient_id']) ? $_POST['patient_id'] : 0);
        $date      = isset($_POST['appt_date']) ? $_POST['appt_date'] : '';
        $time      = isset($_POST['appt_time']) ? $_POST['appt_time'] : '';
        $type      = (isset($_POST['appt_type']) ? $_POST['appt_type'] : 'walkin') === 'online' ? 'online' : 'walkin';
        $concern   = trim(isset($_POST['concern']) ? $_POST['concern'] : '');
        $status    = in_array(isset($_POST['status']) ? $_POST['status'] : '', ['pending','done','cancelled']) ? $_POST['status'] : 'pending';

        if ($patientId && $date && $time && $concern) {
            $ins = $pdo->prepare('INSERT INTO appointments (patient_id, appt_date, appt_time, appt_type, concern, status)
                                   VALUES (?, ?, ?, ?, ?, ?)');
            $ins->execute([$patientId, $date, $time, $type, $concern, $status]);
            set_flash('Appointment added successfully.');
        } else {
            set_flash('Please complete all required fields.');
        }
        header('Location: appointments_doctor.php'); exit;
    }

    if ($action === 'accept' || $action === 'decline') {
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        if ($action === 'accept') {
            $pdo->prepare("UPDATE appointments SET status='confirmed' WHERE appointment_id=? AND status='pending'")->execute([$id]);
            set_flash('Appointment accepted. The patient can now pay from My Appointments.');
        } else {
            $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE appointment_id=? AND status='pending'")->execute([$id]);
            set_flash('Appointment declined.');
        }
        header('Location: appointments_doctor.php'); exit;
    }

    if ($action === 'done') {
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $pdo->prepare("UPDATE appointments SET status='done' WHERE appointment_id=?")->execute([$id]);
        set_flash('Appointment marked as done.');
        header('Location: appointments_doctor.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $pdo->prepare('DELETE FROM appointments WHERE appointment_id=?')->execute([$id]);
        set_flash('Appointment deleted.');
        header('Location: appointments_doctor.php'); exit;
    }
}

// ---------- Search / filter ----------
$q      = trim(isset($_GET['q']) ? $_GET['q'] : '');
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT a.*, p.first_name, p.last_name, p.contact_no FROM appointments a
        JOIN patients p ON p.patient_id = a.patient_id WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR a.concern LIKE ?)";
    $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like;
}
if (in_array($filter, ['pending','confirmed','done','cancelled'])) { $sql .= " AND a.status = ?"; $params[] = $filter; }
if (in_array($filter, ['online','walkin']))            { $sql .= " AND a.appt_type = ?"; $params[] = $filter; }
$sql .= " ORDER BY a.appt_date DESC, a.appt_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$patients = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name")->fetchAll();

$pageTitle  = 'Appointments';
$pageSub    = 'Manage online and walk-in consultations';
$activePage = 'appointments';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if (isset($_GET['new'])): ?>
<div class="card mb-16">
  <div class="card-head"><div class="card-title">New Appointment</div></div>
  <form method="post" action="appointments_doctor.php">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="form-group">
        <label>Patient *</label>
        <select class="form-control" name="patient_id" required>
          <option value="">Select patient</option>
          <?php foreach ($patients as $p): ?>
            <option value="<?= $p['patient_id'] ?>"><?= e($p['last_name'].', '.$p['first_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>No patient record yet?</label>
        <a class="btn-secondary" href="patients_doctor.php?new=1" style="display:inline-block;text-decoration:none;">+ Register New Patient</a>
      </div>
      <div class="form-group">
        <label>Date *</label>
        <input class="form-control" type="date" name="appt_date" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-group">
        <label>Time *</label>
        <input class="form-control" type="time" name="appt_time" required>
      </div>
      <div class="form-group">
        <label>Consultation Type *</label>
        <select class="form-control" name="appt_type">
          <option value="walkin">Walk-in / Face-to-face</option>
          <option value="online">Online (Google Meet)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select class="form-control" name="status">
          <option value="pending">Pending</option>
          <option value="done">Done</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="form-group full">
        <label>Chief Complaint / Concern *</label>
        <textarea class="form-control" name="concern" required placeholder="Patient's reason for consultation"></textarea>
      </div>
    </div>
    <div class="flex gap-8 mt-16">
      <button type="submit" class="btn-primary">Save Appointment</button>
      <a class="btn-cancel" href="appointments_doctor.php" style="text-decoration:none;">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="toolbar">
  <form method="get" action="appointments_doctor.php" style="display:flex;gap:10px;flex:1;">
    <div class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" placeholder="Search patient name or concern..." value="<?= e($q) ?>">
    </div>
    <select class="form-control" name="filter" style="width:150px;" onchange="this.form.submit()">
      <option value="all"       <?= $filter==='all'?'selected':'' ?>>All Status</option>
      <option value="pending"   <?= $filter==='pending'?'selected':'' ?>>Pending</option>
      <option value="confirmed" <?= $filter==='confirmed'?'selected':'' ?>>Accepted</option>
      <option value="done"      <?= $filter==='done'?'selected':'' ?>>Done</option>
      <option value="cancelled" <?= $filter==='cancelled'?'selected':'' ?>>Cancelled</option>
      <option value="online"    <?= $filter==='online'?'selected':'' ?>>Online Only</option>
      <option value="walkin"    <?= $filter==='walkin'?'selected':'' ?>>Walk-in Only</option>
    </select>
    <button type="submit" class="btn-secondary">Filter</button>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Patient</th><th>Date & Time</th><th>Type</th><th>Concern</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if ($appointments): foreach ($appointments as $a): $name = $a['first_name'].' '.$a['last_name']; ?>
      <tr>
        <td>
          <div class="td-flex">
            <?= avatar_html($name, 32) ?>
            <div>
              <div style="font-weight:500"><?= e($name) ?></div>
              <div class="text-sm text-muted"><?= e($a['contact_no']) ?></div>
            </div>
          </div>
        </td>
        <td><?= fmt_date($a['appt_date']) ?><br><span class="text-sm text-muted"><?= fmt_time($a['appt_time']) ?></span></td>
        <td><span class="badge <?= $a['appt_type']==='online'?'b-online':'b-walkin' ?>"><?= $a['appt_type']==='online'?'Online':'Walk-in' ?></span></td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($a['concern']) ?></td>
        <td><span class="badge <?= badge_class($a['status']) ?>"><?= $a['status']==='confirmed' ? 'Accepted' : ucfirst($a['status']) ?></span></td>
        <td>
          <div class="flex gap-8">
            <?php if ($a['status'] === 'pending'): ?>
            <form method="post" action="appointments_doctor.php" onsubmit="return confirm('Accept this appointment? The patient will then be asked to pay.');">
              <input type="hidden" name="action" value="accept">
              <input type="hidden" name="id" value="<?= $a['appointment_id'] ?>">
              <button type="submit" class="mini-btn" style="color:var(--green)">Accept</button>
            </form>
            <form method="post" action="appointments_doctor.php" onsubmit="return confirm('Decline this appointment request?');">
              <input type="hidden" name="action" value="decline">
              <input type="hidden" name="id" value="<?= $a['appointment_id'] ?>">
              <button type="submit" class="mini-btn" style="color:var(--red)">Decline</button>
            </form>
            <?php endif; ?>
            <?php if ($a['status'] !== 'done' && $a['status'] !== 'cancelled'): ?>
            <form method="post" action="appointments_doctor.php" onsubmit="return confirm('Mark this appointment as done?');">
              <input type="hidden" name="action" value="done">
              <input type="hidden" name="id" value="<?= $a['appointment_id'] ?>">
              <button type="submit" class="mini-btn">✓ Done</button>
            </form>
            <?php endif; ?>
            <form method="post" action="appointments_doctor.php" onsubmit="return confirm('Delete this appointment?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $a['appointment_id'] ?>">
              <button type="submit" class="mini-btn" style="color:var(--red)">Delete</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; else: ?>
        <?= empty_row(6, 'No appointments found') ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
