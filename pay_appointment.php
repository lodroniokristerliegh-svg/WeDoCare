<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/booking_shell.php';
require_role('patient');

$patientId = $_SESSION['patient_id'];
$apptId    = (int) (isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : 0));
$uploadDir = __DIR__ . '/uploads/proofs/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

// ---- The appointment must belong to this patient ----
$st = $pdo->prepare('SELECT * FROM appointments WHERE appointment_id = ? AND patient_id = ?');
$st->execute([$apptId, $patientId]);
$appt = $st->fetch();
if (!$appt) { header('Location: appointments_patient.php'); exit; }

// ---- Doctor + fee. The amount always comes from the database. ----
$doc = null;
if (!empty($appt['doctor_id'])) {
    $ds = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'doctor'");
    $ds->execute([(int) $appt['doctor_id']]);
    $doc = $ds->fetch();
}
if (!$doc) { $doc = $pdo->query("SELECT * FROM users WHERE role='doctor' ORDER BY user_id LIMIT 1")->fetch(); }

$consultFee = ($doc && isset($doc['consultation_fee'])) ? (float) $doc['consultation_fee'] : 0;
$medcertFee = ($doc && isset($doc['medcert_fee'])) ? (float) $doc['medcert_fee'] : 0;
$wantsMedCert = strpos($appt['concern'], '[+MedCert]') !== false;
$amount  = $consultFee + ($wantsMedCert ? $medcertFee : 0);
$service = 'Consultation Fee' . ($wantsMedCert ? ' + Medical Certificate' : '');

// ---- Latest payment on this appointment ----
$ps = $pdo->prepare('SELECT * FROM payments WHERE appointment_id = ? ORDER BY payment_id DESC LIMIT 1');
$ps->execute([$apptId]);
$lastPay = $ps->fetch();
$payStatus = $lastPay ? $lastPay['status'] : null;

// Who may pay: accepted (confirmed) appointment with no active payment.
$state = 'form';
if ($appt['status'] === 'pending')        { $state = 'waiting'; }
elseif ($appt['status'] === 'cancelled')  { $state = 'cancelled'; }
elseif ($payStatus === 'verified')        { $state = 'paid'; }
elseif ($payStatus === 'pending')         { $state = 'verifying'; }
elseif ($appt['status'] === 'confirmed')  { $state = 'later'; }   // accepted, consultation not finished yet
elseif ($amount <= 0)                     { $state = 'nofee'; }

$errors = [];
$old = [];

// ---- What the server will accept (phone photos are often bigger than this) ----
function ini_bytes($v) {
    $v = trim((string) $v); $n = (float) $v; $u = strtolower(substr($v, -1));
    if ($u === 'g') { $n *= 1024 * 1024 * 1024; } elseif ($u === 'm') { $n *= 1024 * 1024; } elseif ($u === 'k') { $n *= 1024; }
    return (int) $n;
}
$serverLimit = min(ini_bytes(ini_get('upload_max_filesize')), ini_bytes(ini_get('post_max_size')));
if ($serverLimit <= 0) { $serverLimit = 2 * 1024 * 1024; }
$fileLimit   = max(200 * 1024, min(5 * 1024 * 1024, $serverLimit - 100 * 1024));
$limitMB     = round($fileLimit / 1048576, 1);

// When the upload is bigger than PHP's post_max_size, PHP throws away the WHOLE form
// ($_POST and $_FILES become empty). That used to show the misleading messages
// "Please choose a payment method" and "Please confirm...".
$postTooBig = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH']));
if ($postTooBig) {
    $errors[] = 'The photo or file you chose is too large for the server. Please choose it again (photos are reduced automatically) or pick a file under ' . $limitMB . ' MB, then press Submit Payment.';
}

if ($state === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST' && !$postTooBig) {
    $old       = $_POST;
    $method    = isset($_POST['method']) && is_string($_POST['method']) ? trim($_POST['method']) : '';
    $reference = trim(isset($_POST['reference_no']) ? $_POST['reference_no'] : '');
    $sender    = trim(isset($_POST['sender_name']) ? $_POST['sender_name'] : '');
    $agreed    = isset($_POST['agree']) && (string) $_POST['agree'] === '1';

    if (!in_array($method, ['GCash', 'Maya', 'Cash'], true)) { $errors[] = 'Please choose a payment method.'; }
    if ($method === 'Cash' && $appt['appt_type'] !== 'walkin') {
        $errors[] = 'Online consultations must be paid by GCash or Maya before the appointment.';
    }
    if (!$agreed) { $errors[] = 'Please confirm that your payment details are correct.'; }

    $proofPath = null;
    if (!$errors && ($method === 'GCash' || $method === 'Maya')) {
        if (!$reference || !$sender) {
            $errors[] = 'Please fill in the reference number and sender name.';
        } elseif (empty($_FILES['proof']['name'])) {
            $errors[] = 'Please upload your ' . $method . ' screenshot as proof of payment.';
        } elseif (isset($_FILES['proof']['error']) && $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            $big = in_array($_FILES['proof']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $errors[] = $big ? 'That file is too large. Please choose a file under ' . $limitMB . ' MB.' : 'The file could not be uploaded. Please try again.';
        } else {
            $dup = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE reference_no = ? AND method = ? AND status != 'rejected'");
            $dup->execute([$reference, $method]);
            if ((int) $dup->fetchColumn() > 0) {
                $errors[] = 'That reference number has already been used on another payment.';
            } else {
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                $mime = mime_content_type($_FILES['proof']['tmp_name']);
                if (!in_array($mime, $allowed)) {
                    $errors[] = 'Please upload a JPG, PNG, WEBP or PDF file only.';
                } elseif ($_FILES['proof']['size'] > $fileLimit) {
                    $errors[] = 'File is too large (max ' . $limitMB . ' MB).';
                } else {
                    $ext = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
                    $filename = 'proof_appt' . $apptId . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                    move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $filename);
                    $proofPath = 'uploads/proofs/' . $filename;
                }
            }
        }
    }

    if (!$errors) {
        $pdo->prepare('INSERT INTO payments
                (patient_id, appointment_id, service, amount, method, reference_no, sender_name, date_paid, proof_image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")')
            ->execute([$patientId, $apptId, $service, $amount, $method,
                       $reference ?: null, $sender ?: null, date('Y-m-d'), $proofPath]);
        set_flash($method === 'Cash'
            ? 'Thank you! Please pay the full amount at the clinic when you arrive.'
            : 'Payment submitted! The clinic will verify it shortly' . ($appt['appt_type'] === 'online' ? ', then your Google Meet link is released.' : '.'));
        header('Location: dashboard_patient.php'); exit;
    }
}

function oldv($key, $default = '') {
    global $old;
    return isset($old[$key]) ? htmlspecialchars($old[$key]) : $default;
}

$when = fmt_date($appt['appt_date']) . ' at ' . fmt_time($appt['appt_time']);
bk_open('Pay for Your Consultation');
?>
<div class="bw-wrap">
  <div class="bw-rail">
    <div class="bw-title">Pay for Your<br>Consultation</div>
    <div class="bw-fee-box">
      <div class="bw-fee-row"><span>Doctor</span><span style="font-weight:600;color:var(--navy);"><?= e($doc ? $doc['full_name'] : '—') ?></span></div>
      <div class="bw-fee-row"><span>Date &amp; time</span><span style="font-weight:600;color:var(--navy);"><?= e($when) ?></span></div>
      <div class="bw-fee-row"><span>Type</span><span style="font-weight:600;color:var(--navy);"><?= $appt['appt_type'] === 'online' ? 'Online (Google Meet)' : 'Walk-in' ?></span></div>
      <div class="bw-fee-row" style="margin-top:8px;"><span>Consultation fee</span><span>₱<?= number_format($consultFee, 2) ?></span></div>
      <?php if ($wantsMedCert): ?><div class="bw-fee-row"><span>Medical certificate</span><span>₱<?= number_format($medcertFee, 2) ?></span></div><?php endif; ?>
      <div class="bw-fee-row total"><span>Total to pay</span><span>₱<?= number_format($amount, 2) ?></span></div>
    </div>
  </div>

  <div class="bw-main">
    <?php if ($state === 'form'): ?>
      <div class="bw-h">Payment</div>
      <?php if ($errors): ?>
        <div class="bw-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
      <?php endif; ?>
      <div style="font-size:14px;color:var(--text2);margin-bottom:18px;">Your consultation is done. Please settle your consultation fee below — the clinic will verify your payment.</div>

      <form method="post" action="pay_appointment.php?id=<?= $apptId ?>" enctype="multipart/form-data" id="pay-form">
        <input type="hidden" name="id" value="<?= $apptId ?>">
        <div class="bw-method-choice" role="group" aria-label="Choose payment method">
          <label class="bw-method-card <?= oldv('method', 'GCash') === 'GCash' ? 'selected' : '' ?>" data-method="GCash" onclick="selMethod('GCash')">
            <input type="radio" name="method" value="GCash" <?= oldv('method', 'GCash') === 'GCash' ? 'checked' : '' ?> required onchange="selMethod('GCash')"> GCash
          </label>
          <label class="bw-method-card <?= oldv('method', 'GCash') === 'Maya' ? 'selected' : '' ?>" data-method="Maya" onclick="selMethod('Maya')">
            <input type="radio" name="method" value="Maya" <?= oldv('method', 'GCash') === 'Maya' ? 'checked' : '' ?> required onchange="selMethod('Maya')"> Maya
          </label>
          <?php if ($appt['appt_type'] === 'walkin'): ?>
          <label class="bw-method-card <?= oldv('method', 'GCash') === 'Cash' ? 'selected' : '' ?>" data-method="Cash" onclick="selMethod('Cash')">
            <input type="radio" name="method" value="Cash" <?= oldv('method', 'GCash') === 'Cash' ? 'checked' : '' ?> required onchange="selMethod('Cash')"> Cash at clinic
          </label>
          <?php endif; ?>
        </div>

        <div id="pay-digital" style="display:none;">
          <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px;margin-bottom:16px;text-align:center;">
            <div style="font-size:12px;font-weight:700;color:var(--text2);margin-bottom:8px;">SEND PAYMENT TO</div>
            <div id="payto-GCash" style="display:none;">
              <?php if ($doc && !empty($doc['gcash_qr'])): ?><img src="<?= e($doc['gcash_qr']) ?>" style="width:130px;height:130px;object-fit:contain;background:#fff;border-radius:10px;border:1px solid var(--border);"><?php endif; ?>
              <div style="font-size:14px;font-weight:700;color:var(--navy);margin-top:6px;">GCash</div>
              <div style="font-size:14px;"><?= e($doc && !empty($doc['gcash_number']) ? $doc['gcash_number'] : 'Not yet set up') ?></div>
            </div>
            <div id="payto-Maya" style="display:none;">
              <?php if ($doc && !empty($doc['maya_qr'])): ?><img src="<?= e($doc['maya_qr']) ?>" style="width:130px;height:130px;object-fit:contain;background:#fff;border-radius:10px;border:1px solid var(--border);"><?php endif; ?>
              <div style="font-size:14px;font-weight:700;color:var(--teal);margin-top:6px;">Maya</div>
              <div style="font-size:14px;"><?= e($doc && !empty($doc['maya_number']) ? $doc['maya_number'] : 'Not yet set up') ?></div>
            </div>
            <div style="font-size:13px;color:var(--text2);margin-top:8px;">Send exactly <strong>₱<?= number_format($amount, 2) ?></strong>.</div>
          </div>
          <div class="form-grid grid-2 mb-12">
            <div class="form-group"><label>Reference No.</label><input class="form-control" name="reference_no" value="<?= oldv('reference_no') ?>" placeholder="e.g. 1234 5678 9012"></div>
            <div class="form-group"><label>Sender Name</label><input class="form-control" name="sender_name" value="<?= oldv('sender_name') ?>" placeholder="Name on the account"></div>
          </div>
          <div class="form-group mb-12"><label>Proof of Payment</label><input class="form-control" type="file" name="proof" accept="image/*,application/pdf"><div style="font-size:12px;color:var(--text3);">JPG, PNG, WEBP, or PDF. Large photos are reduced automatically.</div></div>
        </div>

        <div id="pay-cash" style="display:none;font-size:14px;color:var(--text2);background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:14px;">
          You'll pay <strong>₱<?= number_format($amount, 2) ?></strong> in person at the clinic when you arrive.
        </div>

        <label style="display:flex;gap:10px;align-items:flex-start;font-size:13.5px;cursor:pointer;margin-top:6px;">
          <input type="checkbox" name="agree" value="1" required style="margin-top:3px;" <?= !empty($old['agree']) ? 'checked' : '' ?>>
          <span>The payment details I entered are true and correct.</span>
        </label>

        <div class="bw-actions">
          <a href="appointments_patient.php" class="btn-cancel">Back</a>
          <button type="submit" class="btn-primary">Submit Payment</button>
        </div>
      </form>

    <?php else:
        $msgs = [
          'waiting'   => ['Waiting for the doctor', 'The doctor has not accepted this appointment yet.'],
          'later'     => ['Pay after your consultation', 'Your consultation fee of ₱' . number_format($amount, 2) . ' is due after your consultation. This page opens for payment once the doctor finishes it — we will remind you on your dashboard.'],
          'cancelled' => ['Appointment cancelled', 'This appointment was declined or cancelled, so no payment is needed.'],
          'paid'      => ['Already paid', 'Your payment for this appointment is verified. Thank you!'],
          'verifying' => ['Payment being verified', 'The clinic is checking your payment. You\'ll see the update on My Appointments.'],
          'nofee'     => ['Fee not set yet', 'The clinic has not set the consultation fee yet. Please contact the clinic.'],
        ]; ?>
      <div class="bw-h"><?= e($msgs[$state][0]) ?></div>
      <p style="color:var(--text2);font-size:15px;margin-bottom:24px;"><?= e($msgs[$state][1]) ?></p>
      <a href="appointments_patient.php" class="btn-primary">Back to My Appointments</a>
    <?php endif; ?>
  </div>
</div>

<script>
function selMethod(m) {
  document.querySelectorAll('input[name="method"]').forEach(r => { r.checked = (r.value === m); });
  document.querySelectorAll('.bw-method-card').forEach(c => c.classList.toggle('selected', c.dataset.method === m));
  var digital = (m === 'GCash' || m === 'Maya');
  document.getElementById('pay-digital').style.display = digital ? 'block' : 'none';
  document.getElementById('pay-cash').style.display = m === 'Cash' ? 'block' : 'none';
  ['GCash','Maya'].forEach(function (k) {
    var el = document.getElementById('payto-' + k);
    if (el) el.style.display = (m === k) ? 'block' : 'none';
  });
}
(function () { var m = document.querySelector('input[name="method"]:checked'); selMethod(m ? m.value : 'GCash'); })();

(function () {
  var inp = document.querySelector('input[name="proof"]');
  var form = document.getElementById('pay-form');
  if (!inp || !form) return;
  var MAX = <?= (int) $fileLimit ?>;
  var note = document.createElement('div');
  note.style.cssText = 'font-size:12.5px;color:var(--text2);margin-top:6px;';
  inp.parentNode.appendChild(note);
  function fmt(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB'; }
  inp.addEventListener('change', function () {
    note.textContent = '';
    var f = inp.files && inp.files[0];
    if (!f) return;
    if (f.type.indexOf('image/') === 0 && f.type !== 'image/gif' && f.size > 900 * 1024 && window.DataTransfer && window.File) {
      note.textContent = 'Reducing photo size…';
      var img = new Image(), url = URL.createObjectURL(f);
      img.onload = function () {
        var s = Math.min(1, 1600 / Math.max(img.width, img.height));
        var c = document.createElement('canvas');
        c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) {
          if (!b) { note.textContent = ''; return; }
          var nf = new File([b], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
          var dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
          note.textContent = 'Photo reduced from ' + fmt(f.size) + ' to ' + fmt(nf.size) + '.';
        }, 'image/jpeg', 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); note.textContent = ''; };
      img.src = url;
      return;
    }
    if (f.size > MAX) {
      alert('That file is too large (' + fmt(f.size) + '). Please choose a file under ' + fmt(MAX) + '.');
      inp.value = '';
    }
  });
  form.addEventListener('submit', function (e) {
    var f = inp.files && inp.files[0];
    var m = document.querySelector('input[name="method"]:checked');
    if (f && f.size > MAX && m && m.value !== 'Cash') {
      e.preventDefault();
      alert('The proof file is too large (' + fmt(f.size) + '). Please choose a file under ' + fmt(MAX) + '.');
    }
  });
})();
</script>
<?php bk_close(); ?>
