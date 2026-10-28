<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

// ---------- Handle actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $lname   = trim(isset($_POST['lname']) ? $_POST['lname'] : '');
        $fname   = trim(isset($_POST['fname']) ? $_POST['fname'] : '');
        $mname   = trim(isset($_POST['mname']) ? $_POST['mname'] : '');
        $dob     = isset($_POST['dob']) ? $_POST['dob'] : '';
        $gender  = isset($_POST['gender']) ? $_POST['gender'] : '';
        $civil   = trim(isset($_POST['civil']) ? $_POST['civil'] : '');
        $contact = trim(isset($_POST['contact']) ? $_POST['contact'] : '');
        $email   = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
        $blood   = trim(isset($_POST['blood']) ? $_POST['blood'] : '');
        $allergies = trim(isset($_POST['allergies']) ? $_POST['allergies'] : '') ?: 'NKDA';
        $history = trim(isset($_POST['history']) ? $_POST['history'] : '');

        if ($lname && $fname && $dob && $gender && $contact && $address && (!$email || filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $ins = $pdo->prepare('INSERT INTO patients (last_name, first_name, middle_name, dob, gender, civil_status,
                                   contact_no, email, address, blood_type, allergies, history)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([$lname, $fname, $mname ?: null, $dob, $gender, $civil ?: null, $contact, $email ?: null, $address, $blood ?: null, $allergies, $history ?: null]);
            set_flash('Patient record added successfully.');
        } else {
            set_flash('Please complete all required fields.');
        }
        header('Location: patients_doctor.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $pdo->prepare('DELETE FROM patients WHERE patient_id=?')->execute([$id]);
        set_flash('Patient record deleted.');
        header('Location: patients_doctor.php'); exit;
    }
}

// ---------- View single patient (overlay modal) ----------
$viewPatient = null;
if (!empty($_GET['view'])) {
    $stmt = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
    $stmt->execute([(int) $_GET['view']]);
    $viewPatient = $stmt->fetch();
}

// ---------- Consultation history for the viewed patient ----------
$consultations = [];
if ($viewPatient) {
    $cs = $pdo->prepare('SELECT appointment_id, appt_date, appt_time, appt_type, concern, status
                         FROM appointments
                         WHERE patient_id = ?
                         ORDER BY appt_date DESC, appt_time DESC');
    $cs->execute([(int) $viewPatient['patient_id']]);
    $consultations = $cs->fetchAll();
}

// ---------- Prescriptions already issued to the viewed patient ----------
$patientRx = [];
if ($viewPatient) {
    $rs = $pdo->prepare('SELECT prescription_id, date_issued, status FROM prescriptions
                         WHERE patient_id = ? ORDER BY date_issued DESC, prescription_id DESC');
    $rs->execute([(int) $viewPatient['patient_id']]);
    $patientRx = $rs->fetchAll();
}

// ---------- Search / filter ----------
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$genderFilter = isset($_GET['gender']) ? $_GET['gender'] : 'all';

$sql = "SELECT * FROM patients WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (CONCAT(first_name,' ',last_name) LIKE ? OR contact_no LIKE ? OR history LIKE ?)";
    $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like;
}
if (in_array($genderFilter, ['Male','Female'])) { $sql .= " AND gender = ?"; $params[] = $genderFilter; }
$sql .= " ORDER BY last_name, first_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle  = 'Patients';
$pageSub    = 'Patient records and medical history';
$activePage = 'patients';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if ($viewPatient): $name = $viewPatient['first_name'].' '.($viewPatient['middle_name']?$viewPatient['middle_name'].' ':'').$viewPatient['last_name']; $allergyColor = $viewPatient['allergies']==='NKDA' ? 'var(--green)' : 'var(--red)'; ?>
<div class="patient-detail-overlay" id="patient-detail-modal" role="dialog" aria-modal="true" aria-labelledby="patient-detail-title" onclick="if(event.target===this) location.href='patients_doctor.php';">
<div class="patient-detail-modal">
  <div class="lab-header" style="background:var(--navy);display:flex;justify-content:space-between;align-items:center;">
    <div>
      <div class="lab-header-title" id="patient-detail-title"><?= e($name) ?></div>
      <div class="lab-header-sub">Patient ID: WDC-<?= str_pad($viewPatient['patient_id'],4,'0',STR_PAD_LEFT) ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:16px;">
      <button type="button" class="btn-primary" onclick="openPrescriptionModal(<?= (int) $viewPatient['patient_id'] ?>)" style="background:#fff;color:var(--navy);padding:7px 14px;font-size:12.5px;border:0;cursor:pointer;">+ E-Prescription</button>
      <a href="patients_doctor.php" aria-label="Close patient details" style="color:#fff;text-decoration:none;font-size:20px;">&times;</a>
    </div>
  </div>
  <div class="lab-body">
    <div class="form-grid">
      <div class="form-field"><label>Date of Birth</label><div class="rx-field-input"><?= fmt_date($viewPatient['dob']) ?> (<?= calc_age($viewPatient['dob']) ?> yrs old)</div></div>
      <div class="form-field"><label>Gender</label><div class="rx-field-input"><?= e($viewPatient['gender']) ?></div></div>
      <div class="form-field"><label>Civil Status</label><div class="rx-field-input"><?= e($viewPatient['civil_status'] ?: '—') ?></div></div>
      <div class="form-field"><label>Contact Number</label><div class="rx-field-input"><?= e($viewPatient['contact_no']) ?></div></div>
      <div class="form-field"><label>Email Address</label><div class="rx-field-input"><?= e($viewPatient['email']) ?></div></div>
      <div class="form-field"><label>Blood Type</label><div class="rx-field-input"><?= e($viewPatient['blood_type'] ?: '—') ?></div></div>
      <div class="form-field span-2"><label>Address</label><div class="rx-field-input"><?= e($viewPatient['address']) ?></div></div>
      <div class="form-field span-2"><label>Known Allergies</label><div class="rx-field-input" style="color:<?= $allergyColor ?>;font-weight:600;"><?= e($viewPatient['allergies']) ?></div></div>
      <div class="form-field span-2"><label>Medical History</label><div class="rx-field-input"><?= nl2br(e($viewPatient['history'] ?: 'No notes on file.')) ?></div></div>
    </div>

    <div style="margin-top:22px;">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px;color:var(--navy);">
        Consultation History (<?= count($consultations) ?>)
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Date</th><th>Time</th><th>Type</th><th>Concern</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($consultations): foreach ($consultations as $c): ?>
            <tr>
              <td><?= fmt_date($c['appt_date']) ?></td>
              <td><?= fmt_time($c['appt_time']) ?></td>
              <td><span class="badge <?= badge_class($c['appt_type']) ?>"><?= $c['appt_type'] === 'online' ? 'Online' : 'Walk-in' ?></span></td>
              <td style="font-size:12px"><?= e($c['concern']) ?></td>
              <td><span class="badge <?= badge_class($c['status']) ?>"><?= ucfirst(e($c['status'])) ?></span></td>
            </tr>
            <?php endforeach; else: ?>
              <?= empty_row(5, 'No consultation history yet') ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top:22px;">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px;color:var(--navy);">
        E-Prescriptions (<?= count($patientRx) ?>)
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Rx No.</th><th>Date Issued</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php if ($patientRx): foreach ($patientRx as $rx): ?>
            <tr>
              <td>Rx-<?= str_pad($rx['prescription_id'], 5, '0', STR_PAD_LEFT) ?></td>
              <td><?= fmt_date($rx['date_issued']) ?></td>
              <td><span class="badge <?= badge_class($rx['status']) ?>"><?= ucfirst(e($rx['status'])) ?></span></td>
              <td><a class="mini-btn" href="prescriptions_doctor.php?view=<?= (int) $rx['prescription_id'] ?>" style="text-decoration:none;">View</a></td>
            </tr>
            <?php endforeach; else: ?>
              <?= empty_row(4, 'No prescriptions issued yet') ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['new'])): ?>
<div class="modal-overlay open" id="patient-modal" role="dialog" aria-modal="true" aria-labelledby="patient-modal-title" onclick="if(event.target===this) location.href='patients_doctor.php';">
  <div class="modal modal-form modal-patient">
  <div class="modal-head"><div><div class="modal-title" id="patient-modal-title">Register New Patient</div><div class="text-sm text-muted">Add the patient’s details to the clinic record</div></div><a class="modal-close" href="patients_doctor.php" aria-label="Close">&times;</a></div>
  <div class="modal-body">
  <form method="post" action="patients_doctor.php">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="form-group"><label>Last Name *</label><input class="form-control" name="lname" required></div>
      <div class="form-group"><label>First Name *</label><input class="form-control" name="fname" required></div>
      <div class="form-group"><label>Middle Name</label><input class="form-control" name="mname"></div>
      <div class="form-group"><label>Date of Birth *</label><input class="form-control" type="date" name="dob" required></div>
      <div class="form-group">
        <label>Gender *</label>
        <select class="form-control" name="gender" required>
          <option value="">Select</option><option>Male</option><option>Female</option>
        </select>
      </div>
      <div class="form-group"><label>Civil Status</label><input class="form-control" name="civil" placeholder="Single / Married"></div>
      <div class="form-group"><label>Contact Number *</label><input class="form-control" name="contact" required></div>
      <div class="form-group"><label>Email Address <span class="text-muted">(Optional)</span></label><input class="form-control" type="email" name="email" placeholder="name@example.com (optional)"></div>
      <div class="form-group full"><label>Address *</label><input class="form-control" name="address" required></div>
      <div class="form-group"><label>Blood Type</label><input class="form-control" name="blood" placeholder="O+, A+, etc."></div>
      <div class="form-group"><label>Known Allergies</label><input class="form-control" name="allergies" placeholder="NKDA if none"></div>
      <div class="form-group full"><label>Medical History / Notes</label><textarea class="form-control" name="history"></textarea></div>
    </div>
    <div class="flex gap-8 mt-16">
      <button type="submit" class="btn-primary">Save Patient Record</button>
      <a class="btn-cancel" href="patients_doctor.php" style="text-decoration:none;">Cancel</a>
    </div>
  </form>
  </div>
  </div>
</div>
<?php endif; ?>

<div class="toolbar">
  <form method="get" action="patients_doctor.php" style="display:flex;gap:10px;flex:1;">
    <div class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" placeholder="Search by name, contact, or condition..." value="<?= e($q) ?>">
    </div>
    <select class="form-control" name="gender" style="width:140px" onchange="this.form.submit()">
      <option value="all"    <?= $genderFilter==='all'?'selected':'' ?>>All Gender</option>
      <option value="Male"   <?= $genderFilter==='Male'?'selected':'' ?>>Male</option>
      <option value="Female" <?= $genderFilter==='Female'?'selected':'' ?>>Female</option>
    </select>
    <button type="submit" class="btn-secondary">Filter</button>
  </form>
  <a class="btn-primary ml-auto" href="patients_doctor.php?new=1" style="text-decoration:none;">
    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Patient
  </a>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Patient Name</th><th>Age</th><th>Gender</th><th>Contact</th><th>Blood Type</th><th>Allergies</th><th>Condition</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if ($patients): foreach ($patients as $p): $name = $p['first_name'].' '.$p['last_name']; $allergyColor = $p['allergies']==='NKDA' ? 'var(--green)' : 'var(--red)'; ?>
      <tr>
        <td>
          <div class="td-flex">
            <?= avatar_html($name, 32) ?>
            <div>
              <div style="font-weight:500"><?= e($name) ?></div>
              <div class="text-sm text-muted"><?= e($p['email']) ?></div>
            </div>
          </div>
        </td>
        <td><?= calc_age($p['dob']) ?></td>
        <td><?= e($p['gender']) ?></td>
        <td><?= e($p['contact_no']) ?></td>
        <td><span class="badge b-walkin"><?= e($p['blood_type'] ?: '—') ?></span></td>
        <td style="color:<?= $allergyColor ?>;font-size:12px;font-weight:500"><?= e($p['allergies']) ?></td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px"><?= e($p['history'] ?: '—') ?></td>
        <td>
          <div class="flex gap-8">
            <a class="mini-btn" href="patients_doctor.php?view=<?= $p['patient_id'] ?>" style="text-decoration:none;">View</a>
            <button type="button" class="mini-btn" onclick="openPrescriptionModal(<?= (int) $p['patient_id'] ?>)" style="color:var(--navy-light);cursor:pointer;">E-Prescription</button>
            <form method="post" action="patients_doctor.php" onsubmit="return confirm('Delete this patient record? This also removes related appointments.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $p['patient_id'] ?>">
              <button type="submit" class="mini-btn" style="color:var(--red)">Delete</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; else: ?>
        <?= empty_row(8, 'No patients found') ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<!-- Floating overlay for E-Prescription -->
<div class="patient-rx-overlay" id="patient-rx-overlay" role="dialog" aria-modal="true" aria-labelledby="patient-rx-title" aria-hidden="true" onclick="if(event.target===this) closePrescriptionModal();">
  <div class="patient-rx-modal">
    <div class="patient-rx-head">
      <div class="patient-rx-brand">
        <img src="assets/wedocare-logo.png" alt="WeDoCare official logo">
        <div id="patient-rx-title">E-Prescription</div>
      </div>
      <button type="button" class="patient-rx-close" onclick="closePrescriptionModal()" aria-label="Close E-Prescription">&times;</button>
    </div>
    <iframe id="patient-rx-frame" title="E-Prescription form" src="about:blank"></iframe>
  </div>
</div>

<style>
.patient-detail-overlay,.patient-rx-overlay {
  position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center;
  padding:20px; background:rgba(15,23,42,.52); backdrop-filter:blur(2px);
}
.patient-detail-modal {
  width:min(900px,96vw); max-height:88vh; overflow:auto; background:#fff; border-radius:14px;
  box-shadow:0 24px 70px rgba(15,23,42,.28);
}
.patient-detail-modal .lab-header { position:sticky; top:0; z-index:2; border-radius:14px 14px 0 0; }
.patient-detail-modal .lab-body { padding:22px; }
.patient-detail-modal .table-wrap { max-width:100%; overflow-x:auto; }
.patient-rx-overlay { display:none; }
.patient-rx-overlay.open { display:flex; }
.patient-rx-modal {
  width:min(980px,96vw); height:min(780px,90vh); background:#fff; border-radius:14px;
  overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 70px rgba(15,23,42,.28);
}
.patient-rx-head {
  flex:0 0 auto; display:flex; justify-content:space-between; align-items:center; gap:16px;
  padding:10px 20px; color:#fff; background:var(--navy); font-weight:600;
}
.patient-rx-brand { display:flex; align-items:center; gap:10px; min-width:0; }
.patient-rx-brand img { width:30px; height:30px; object-fit:contain; background:#fff; border-radius:6px; flex-shrink:0; }
.patient-rx-close { border:0; background:transparent; color:#fff; font-size:28px; line-height:1; cursor:pointer; }
.patient-rx-modal iframe {
  display:block; width:100%; height:100%; flex:1 1 auto; min-height:0;
  border:0; background:#fff; overflow:auto;
}
@media (max-width:640px) {
  .patient-detail-overlay,.patient-rx-overlay { padding:10px; }
  .patient-detail-modal { width:100%; max-height:94vh; }
  .patient-detail-modal .lab-body { padding:14px; }
  .patient-rx-modal { width:100%; height:94vh; }
}
</style>
<script>
function openPrescriptionModal(patientId) {
  const overlay = document.getElementById('patient-rx-overlay');
  const frame = document.getElementById('patient-rx-frame');
  frame.src = 'prescriptions_doctor.php?patient=' + encodeURIComponent(patientId) + '&modal=1';
  overlay.classList.add('open');
  overlay.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}
function closePrescriptionModal() {
  const overlay = document.getElementById('patient-rx-overlay');
  const frame = document.getElementById('patient-rx-frame');
  overlay.classList.remove('open');
  overlay.setAttribute('aria-hidden', 'true');
  frame.src = 'about:blank';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closePrescriptionModal();
    const detail = document.getElementById('patient-detail-modal');
    if (detail) window.location.href = 'patients_doctor.php';
  }
});
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
