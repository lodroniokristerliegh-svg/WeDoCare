<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';

// The homepage's "Book Appointment" buttons send everyone here. A
// logged-out visitor has no account yet, so send them to register first
// (they come straight back here after signing in).
if (empty($_SESSION['user_id'])) {
    header('Location: register.php?redirect=book_appointment.php');
    exit;
}

require_role('patient');

$patientId = $_SESSION['patient_id'];

// ---- Patient record (pre-fills Step 3) ----
$ps = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
$ps->execute([$patientId]);
$patient = $ps->fetch();

// ---- Doctors, fees and payment details — all come from the database,
//      so the patient can never choose or edit the amount they pay. ----
// This clinic is for Dr. Deguito only. Resolve one doctor account on the
// server and do not let patients choose a doctor or specialization.
$doctorStmt = $pdo->query("SELECT * FROM users
    WHERE role='doctor' AND is_active=1
    ORDER BY CASE WHEN full_name LIKE '%Deguito%' THEN 0 ELSE 1 END, user_id ASC
    LIMIT 1");
$singleDoctor = $doctorStmt->fetch();
if (!$singleDoctor) {
    $doctorStmt = $pdo->query("SELECT * FROM users
        WHERE role='doctor'
        ORDER BY CASE WHEN full_name LIKE '%Deguito%' THEN 0 ELSE 1 END, user_id ASC
        LIMIT 1");
    $singleDoctor = $doctorStmt->fetch();
}
$doctors = $singleDoctor ? [$singleDoctor] : [];
$doctorsById = [];
foreach ($doctors as $d) { $doctorsById[(int) $d['user_id']] = $d; }
$doctorDisplayName = $singleDoctor ? $singleDoctor['full_name'] : 'Dr. Kendrick Kerk Urag Deguito';

$errors = [];
$old = [];

require_once __DIR__ . '/includes/booking_shell.php';



if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'book') {
    $old = $_POST;

    // Always assign the clinic's single doctor; ignore any client-supplied doctor ID.
    $doctorId = $singleDoctor ? (int) $singleDoctor['user_id'] : 0;
    $type     = (isset($_POST['appt_type']) ? $_POST['appt_type'] : 'walkin') === 'online' ? 'online' : 'walkin';
    $concern  = trim(isset($_POST['concern']) ? $_POST['concern'] : '');
    $doc      = isset($doctorsById[$doctorId]) ? $doctorsById[$doctorId] : null;
    $date     = isset($_POST['appt_date']) ? $_POST['appt_date'] : '';
    $time     = isset($_POST['appt_time']) ? $_POST['appt_time'] : '';
    $contact  = trim(isset($_POST['contact_no']) ? $_POST['contact_no'] : '');
    $address  = trim(isset($_POST['address']) ? $_POST['address'] : '');
    $wantsMedCert = !empty($_POST['addon_medcert']);
    $agreed   = !empty($_POST['agree_terms']);

    if (!$doc) { $errors[] = 'The clinic doctor is currently unavailable for booking. Please contact the clinic.'; }
    if (!$concern) { $errors[] = 'Please describe your reason for consultation.'; }
    if (!$date || !$time) {
        $errors[] = 'Please choose a date and time.';
    } elseif ($date < date('Y-m-d')) {
        $errors[] = 'Please choose a date that is today or later.';
    }
    if (!$contact || !$address) { $errors[] = 'Please complete your contact number and address.'; }
    if (!$agreed) { $errors[] = 'Please accept the pre-appointment terms.'; }

    // Slot already taken?
    if (!$errors) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM appointments
                              WHERE appt_date = ? AND appt_time = ? AND status != 'cancelled'
                              AND (doctor_id = ? OR doctor_id IS NULL)");
        $chk->execute([$date, $time, $doctorId]);
        if ((int) $chk->fetchColumn() > 0) {
            $errors[] = 'That date and time is already booked. Please choose another slot.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE patients SET contact_no = ?, address = ? WHERE patient_id = ?')
                ->execute([$contact, $address, $patientId]);

            // appointments.concern is VARCHAR(255): keep room for the add-on tag.
            $finalConcern = mb_substr($concern, 0, 200) . ($wantsMedCert ? ' [+MedCert]' : '');

            // Payment is NOT collected here. The doctor accepts the request first,
            // then the patient pays from "My Appointments".
            $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, appt_date, appt_time, appt_type, concern, status)
                           VALUES (?, ?, ?, ?, ?, ?, "pending")')
                ->execute([$patientId, $doctorId, $date, $time, $type, $finalConcern]);

            $pdo->commit();
            set_flash('Appointment requested! Wait for the doctor to accept it. The consultation fee is paid after your consultation.');
            header('Location: dashboard_patient.php'); exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = 'Something went wrong while saving your appointment. Please try again.';
        }
    }
}

function old($key, $default = '') {
    global $old;
    return isset($old[$key]) ? htmlspecialchars($old[$key]) : $default;
}

$fullName = trim($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']);
$stepNames = ['Doctor & Consultation', 'Date & Time', 'Personal Information', 'Add-Ons', 'Pre-Appointment Terms', 'Confirmation'];

bk_open('Book Your Consultation');
?>

<form method="post" action="book_appointment.php" id="bw-form">
    <input type="hidden" name="action" value="book">

    <div class="bw-wrap">
      <div class="bw-rail">
        <div class="bw-title">Book Your<br>Consultation</div>
        <?php foreach ($stepNames as $i => $label): ?>
        <div class="bw-step-item<?= $i === 0 ? ' active' : '' ?>" data-rail="<?= $i + 1 ?>">
          <div class="bw-dot"><?= $i + 1 ?></div><div class="bw-step-label"><?= e($label) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="bw-main">
        <?php if ($errors): ?>
          <div class="bw-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <!-- STEP 1: DOCTOR & CONSULTATION -->
        <div class="bw-panel active" data-panel="1">
          <div class="bw-h">Doctor &amp; Consultation</div>
          <?php if (!$doctors): ?>
            <div class="bw-error">No doctor is available for booking right now. Please contact the clinic.</div>
          <?php endif; ?>
          <div class="form-grid grid-2 mb-16">
            <div class="form-group">
              <label>Doctor</label>
              <div class="form-control" style="display:flex;align-items:center;min-height:44px;background:var(--bg);">
                <?= e($doctorDisplayName) ?>
              </div>
            </div>
            <div class="form-group">
              <label>Specialization</label>
              <div class="form-control" style="display:flex;align-items:center;min-height:44px;background:var(--bg);">
                General Practitioner
              </div>
            </div>
          </div>
          <?php if ($singleDoctor): ?>
            <select name="doctor_id" id="bw-doctor" aria-hidden="true" tabindex="-1" style="display:none;">
              <option value="<?= (int) $singleDoctor['user_id'] ?>"
                data-fee="<?= isset($singleDoctor['consultation_fee']) ? (float) $singleDoctor['consultation_fee'] : 0 ?>"
                data-medcert="<?= isset($singleDoctor['medcert_fee']) ? (float) $singleDoctor['medcert_fee'] : 0 ?>"
                selected><?= e($doctorDisplayName) ?></option>
            </select>
          <?php else: ?>
            <select name="doctor_id" id="bw-doctor" aria-hidden="true" tabindex="-1" style="display:none;"><option value=""></option></select>
          <?php endif; ?>

          <div class="form-group mb-8"><label>Type of Consultation</label></div>
          <div class="bw-type-choice mb-16">
            <div class="bw-type-card" data-type="walkin" onclick="bwSelectType('walkin')">
              <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l8-4v18M13 21V11l6 3v7"/></svg>
              <div class="t">Walk-in</div><div class="s">Visit the clinic in person</div>
            </div>
            <div class="bw-type-card" data-type="online" onclick="bwSelectType('online')">
              <svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
              <div class="t">Online</div><div class="s">Consult via Google Meet</div>
            </div>
          </div>
          <input type="hidden" name="appt_type" id="bw-appt-type" value="<?= old('appt_type', 'walkin') ?>">

          <div class="form-group">
            <label>Reason for Consultation</label>
            <textarea class="form-control" name="concern" placeholder="Briefly describe your concern..." style="min-height:110px;"><?= old('concern') ?></textarea>
          </div>
        </div>

        <!-- STEP 2: DATE & TIME -->
        <div class="bw-panel" data-panel="2">
          <div class="bw-h">Date &amp; Time</div>
          <div class="form-grid grid-2">
            <div class="form-group">
              <label>Preferred Date</label>
              <input class="form-control" type="date" name="appt_date" min="<?= date('Y-m-d') ?>" value="<?= old('appt_date') ?>">
            </div>
            <div class="form-group">
              <label>Preferred Time</label>
              <input class="form-control" type="time" name="appt_time" value="<?= old('appt_time') ?>">
            </div>
          </div>
          <div style="font-size:12px;color:var(--text3);margin-top:10px;">If the slot is already taken, you'll be asked to pick another one when you submit.</div>
        </div>

        <!-- STEP 3: PERSONAL INFORMATION -->
        <div class="bw-panel" data-panel="3">
          <div class="bw-h">Personal Information</div>
          <div class="form-grid grid-2">
            <div class="form-group"><label>Full Name</label><input class="form-control" value="<?= e($fullName) ?>" readonly></div>
            <div class="form-group"><label>Date of Birth</label><input class="form-control" value="<?= e(fmt_date($patient['dob'])) ?>" readonly></div>
            <div class="form-group"><label>Email Address</label><input class="form-control" value="<?= e($patient['email']) ?>" readonly></div>
            <div class="form-group"><label>Contact Number</label><input class="form-control" name="contact_no" value="<?= old('contact_no', e($patient['contact_no'])) ?>"></div>
            <div class="form-group" style="grid-column:1/-1;"><label>Address</label><input class="form-control" name="address" value="<?= old('address', e($patient['address'])) ?>"></div>
          </div>
          <div style="font-size:12px;color:var(--text3);margin-top:10px;">Name, birth date and email come from your account. Update your contact number or address here if they changed.</div>
        </div>

        <!-- STEP 4: ADD-ONS -->
        <div class="bw-panel" data-panel="4">
          <div class="bw-h">Add-Ons</div>
          <label class="bw-addon" id="bw-addon-medcert">
            <input type="checkbox" name="addon_medcert" value="1" id="bw-addon-checkbox" <?= !empty($old['addon_medcert']) ? 'checked' : '' ?> onchange="bwAddonChanged()">
            <div>
              <div style="font-size:13.5px;font-weight:700;color:var(--text);">Request a Medical Certificate <span id="bw-addon-price" style="color:var(--navy-light);"></span></div>
              <div style="font-size:12px;color:var(--text2);margin-top:2px;">Issued after your consultation, for sick leave, fit-to-work, or similar needs.</div>
            </div>
          </label>
          <div style="font-size:12px;color:var(--text3);margin-top:10px;">Optional — skip this step if you don't need anything extra.</div>
        </div>

        <!-- STEP 5: PRE-APPOINTMENT TERMS -->
        <div class="bw-panel" data-panel="5">
          <div class="bw-h">Pre-Appointment Terms</div>
          <div class="bw-terms">
            <ul style="padding-left:18px;margin:0;">
              <li>This is a booking <strong>request</strong>. The doctor must accept it first — you can see its status in My Appointments.</li>
              <li>The consultation fee is paid <strong>after</strong> your consultation (GCash or Maya, or cash at the clinic for walk-ins). We will remind you on your dashboard until it is settled.</li>
              <li>Payments with a false, reused, or unreadable reference number or proof will be rejected, and the fee will remain unpaid.</li>
              <li>Please be on time. Late arrivals may need to be rescheduled depending on the clinic's schedule.</li>
              <li>Missed appointments may still be charged the consultation fee. To reschedule or cancel, contact the clinic in advance.</li>
              <li>The information you provided is true and may be used for your medical record.</li>
            </ul>
          </div>
          <label style="display:flex;gap:10px;align-items:flex-start;font-size:13.5px;cursor:pointer;">
            <input type="checkbox" name="agree_terms" value="1" id="bw-agree" style="margin-top:3px;" <?= !empty($old['agree_terms']) ? 'checked' : '' ?>>
            <span>I have read and agree to the pre-appointment terms.</span>
          </label>
        </div>

        <!-- STEP 6: CONFIRMATION -->
        <div class="bw-panel" data-panel="6">
          <div class="bw-h">Confirmation</div>
          <div class="card" style="background:var(--bg);box-shadow:none;">
            <div class="bw-review-row"><span>Doctor</span><span id="bw-r-doctor">—</span></div>
            <div class="bw-review-row"><span>Type</span><span id="bw-r-type">—</span></div>
            <div class="bw-review-row"><span>Date &amp; Time</span><span id="bw-r-when">—</span></div>
            <div class="bw-review-row"><span>Reason</span><span id="bw-r-concern">—</span></div>
            <div class="bw-review-row"><span>Contact</span><span id="bw-r-contact">—</span></div>
            <div class="bw-review-row"><span>Add-on</span><span id="bw-r-addon">None</span></div>
            <div class="bw-review-row"><span>Fee (payable after your consultation)</span><span id="bw-r-amount">—</span></div>
          </div>
          <div style="font-size:12px;color:var(--text3);margin-top:10px;">Submitting sends your request to the doctor. You will not be charged now — the fee is paid after your consultation.</div>
        </div>

        <div class="bw-actions">
          <button type="button" class="btn-cancel" id="bw-back-btn" onclick="bwBack()" style="visibility:hidden;">Back</button>
          <div style="display:flex;gap:8px;">
            <a href="appointments_patient.php" class="btn-cancel" style="text-decoration:none;">Cancel</a>
            <button type="button" class="btn-primary" id="bw-next-btn" onclick="bwNext()">Next</button>
          </div>
        </div>
      </div>
    </div>
</form>

<script>
let bwStep = 1;
const BW_TOTAL = 6;
const qs = (s) => document.querySelector(s);
const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});

function bwDoctorOpt() { const d = qs('#bw-doctor'); return d.options[d.selectedIndex]; }
function bwFees() {
  const o = bwDoctorOpt();
  const consult = parseFloat(o && o.dataset.fee || '0') || 0;
  const mc = parseFloat(o && o.dataset.medcert || '0') || 0;
  const wants = qs('#bw-addon-checkbox').checked;
  return { consult, mc, wants, total: consult + (wants ? mc : 0) };
}

function bwGoTo(step) {
  document.querySelectorAll('.bw-panel').forEach(p => p.classList.toggle('active', p.dataset.panel == step));
  document.querySelectorAll('.bw-step-item').forEach(item => {
    const n = parseInt(item.dataset.rail, 10);
    item.classList.toggle('active', n === step);
    item.classList.toggle('done', n < step);
  });
  qs('#bw-back-btn').style.visibility = step === 1 ? 'hidden' : 'visible';
  qs('#bw-next-btn').textContent = step === BW_TOTAL ? 'Confirm Booking' : 'Next';
  bwStep = step;
  if (step === 6) bwFillReview();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function bwValidateStep(step) {
  if (step === 1) {
    if (!qs('#bw-doctor').value) { alert('Please choose a specialization and doctor.'); return false; }
    if (!qs('#bw-appt-type').value) { alert('Please choose Walk-in or Online.'); return false; }
    if (!qs('[name="concern"]').value.trim()) { alert('Please describe your reason for consultation.'); return false; }
  }
  if (step === 2) {
    if (!qs('[name="appt_date"]').value || !qs('[name="appt_time"]').value) { alert('Please choose a date and time.'); return false; }
  }
  if (step === 3) {
    if (!qs('[name="contact_no"]').value.trim() || !qs('[name="address"]').value.trim()) { alert('Please complete your contact number and address.'); return false; }
  }
  if (step === 5) {
    if (!qs('#bw-agree').checked) { alert('Please accept the pre-appointment terms to continue.'); return false; }
  }
  return true;
}

function bwNext() {
  if (!bwValidateStep(bwStep)) return;
  if (bwStep === BW_TOTAL) { qs('#bw-form').submit(); return; }
  bwGoTo(bwStep + 1);
}
function bwBack() { bwGoTo(bwStep - 1); }

function bwDoctorChanged() {
  const id = qs('#bw-doctor').value;
  const o = bwDoctorOpt();
  const mc = o && o.dataset.medcert ? parseFloat(o.dataset.medcert) : 0;
  qs('#bw-addon-price').textContent = mc > 0 ? '(+' + peso(mc) + ')' : '';
}

function bwSelectType(type) {
  qs('#bw-appt-type').value = type;
  document.querySelectorAll('.bw-type-card').forEach(c => c.classList.toggle('selected', c.dataset.type === type));
}

function bwAddonChanged() { qs('#bw-addon-medcert').classList.toggle('selected', qs('#bw-addon-checkbox').checked); }

function bwFillReview() {
  const f = bwFees();
  const type = qs('#bw-appt-type').value;
  qs('#bw-r-doctor').textContent = bwDoctorOpt().textContent;
  qs('#bw-r-type').textContent = type === 'online' ? 'Online (Google Meet)' : 'Walk-in';
  qs('#bw-r-when').textContent = (qs('[name="appt_date"]').value || '—') + ' · ' + (qs('[name="appt_time"]').value || '—');
  qs('#bw-r-concern').textContent = qs('[name="concern"]').value || '—';
  qs('#bw-r-contact').textContent = qs('[name="contact_no"]').value || '—';
  qs('#bw-r-addon').textContent = f.wants ? 'Medical Certificate' : 'None';
  qs('#bw-r-amount').textContent = f.consult > 0 ? peso(f.total) : 'Set by the clinic';
}

// Restore non-doctor booking fields after a validation error.
(function bwRestore() {
  bwDoctorChanged();
  const type = qs('#bw-appt-type').value;
  if (type) bwSelectType(type);
  bwAddonChanged();
  <?php if ($errors): ?>bwGoTo(<?= (int) 6 ?>);<?php endif; ?>
})();
</script>

<?php bk_close(); ?>
