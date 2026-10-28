<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('patient');

$patientId = $_SESSION['patient_id'];
$uploadDir = __DIR__ . '/uploads/proofs/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

if (post_too_big()) {
    set_flash(upload_too_big_message());
    header('Location: ' . basename($_SERVER['PHP_SELF'])); exit;
}

// ---------- Patient submits a brand-new payment (self-initiated) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'submit_payment') {
    $apptId    = (int) (isset($_POST['appointment_id']) ? $_POST['appointment_id'] : 0);
    $service   = trim(isset($_POST['service']) ? $_POST['service'] : '');
    $amount    = (float) (isset($_POST['amount']) ? $_POST['amount'] : 0);
    $method    = in_array(isset($_POST['method']) ? $_POST['method'] : '', ['GCash', 'Maya']) ? $_POST['method'] : 'GCash';
    $reference = trim(isset($_POST['reference_no']) ? $_POST['reference_no'] : '');
    $sender    = trim(isset($_POST['sender_name']) ? $_POST['sender_name'] : '');
    $datePaid  = isset($_POST['date_paid']) ? $_POST['date_paid'] : date('Y-m-d');

    if (!$service || $amount <= 0) {
        set_flash('Please fill in the service and a valid amount.');
    } elseif (!$reference || !$sender) {
        set_flash('Please fill in the reference number and sender name.');
    } elseif (empty($_FILES['proof']['name'])) {
        set_flash('Please upload your GCash/Maya screenshot as proof of payment.');
    } else {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $mime = mime_content_type($_FILES['proof']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            set_flash('Please upload a JPG, PNG, or PDF file only.');
        } elseif ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
            set_flash('File is too large (max 5MB).');
        } else {
            $ext = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
            $filename = 'proof_new_' . $patientId . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $filename);

            $ins = $pdo->prepare('INSERT INTO payments
                (patient_id, appointment_id, service, amount, method, reference_no, sender_name, date_paid, proof_image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")');
            $ins->execute([
                $patientId, $apptId ?: null, $service, $amount, $method,
                $reference, $sender, $datePaid, 'uploads/proofs/' . $filename,
            ]);
            set_flash('Payment submitted! Please wait for the clinic to verify it.');
        }
    }
    header('Location: payments_patient.php'); exit;
}

// ---------- Patient uploads proof for a payment the clinic already billed ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'upload_proof') {
    $paymentId  = (int) (isset($_POST['payment_id']) ? $_POST['payment_id'] : 0);
    $method     = in_array(isset($_POST['method']) ? $_POST['method'] : '', ['GCash', 'Maya']) ? $_POST['method'] : 'GCash';
    $reference  = trim(isset($_POST['reference_no']) ? $_POST['reference_no'] : '');
    $sender     = trim(isset($_POST['sender_name']) ? $_POST['sender_name'] : '');
    $datePaid   = isset($_POST['date_paid']) ? $_POST['date_paid'] : date('Y-m-d');

    $check = $pdo->prepare('SELECT payment_id FROM payments WHERE payment_id = ? AND patient_id = ? AND status = "pending"');
    $check->execute([$paymentId, $patientId]);

    if (!$check->fetch()) {
        set_flash('Payment record not found.');
    } elseif (!$reference || !$sender) {
        set_flash('Please fill in the reference number and sender name.');
    } elseif (empty($_FILES['proof']['name'])) {
        set_flash('Please upload your proof of payment screenshot.');
    } else {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $mime = mime_content_type($_FILES['proof']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            set_flash('Please upload a JPG, PNG, or PDF file only.');
        } elseif ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
            set_flash('File is too large (max 5MB).');
        } else {
            $ext = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
            $filename = 'proof_' . $paymentId . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $filename);
            $pdo->prepare('UPDATE payments SET method=?, reference_no=?, sender_name=?, date_paid=?, proof_image=? WHERE payment_id=?')
                ->execute([$method, $reference, $sender, $datePaid, 'uploads/proofs/' . $filename, $paymentId]);
            set_flash('Proof of payment submitted. Awaiting verification by the clinic.');
        }
    }
    header('Location: payments_patient.php'); exit;
}

$stmt = $pdo->prepare('SELECT * FROM payments WHERE patient_id = ? ORDER BY paid_at DESC');
$stmt->execute([$patientId]);
$payments = $stmt->fetchAll();

$appts = $pdo->prepare("SELECT appointment_id, appt_date, concern FROM appointments WHERE patient_id = ? ORDER BY appt_date DESC");
$appts->execute([$patientId]);
$appts = $appts->fetchAll();

// Doctor's GCash/Maya details, so the patient knows where to send payment.
try {
    $doctorPay = $pdo->query("SELECT gcash_number, gcash_qr, maya_number, maya_qr FROM users WHERE role='doctor' LIMIT 1")->fetch();
} catch (PDOException $e) {
    $doctorPay = null;
}

$pageTitle  = 'My Payments';
$pageSub    = 'Consultation fees and payment history';
$activePage = 'payments';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="toolbar mb-16">
  <div style="font-size:13px; color:var(--text2);"><?= count($payments) ?> payment<?= count($payments)===1?'':'s' ?> on record</div>
  <button type="button" class="btn-primary ml-auto" onclick="openNewPaymentModal()">
    <svg viewBox="0 0 24 24" stroke="#fff" fill="none" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Pay via GCash / Maya
  </button>
</div>

<div class="table-card">
  <table class="table">
    <thead><tr><th>Date</th><th>Service</th><th>Amount</th><th>Method</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if ($payments): foreach ($payments as $pm): ?>
      <tr>
        <td><?= fmt_date(substr($pm['paid_at'],0,10)) ?></td>
        <td><?= e($pm['service']) ?></td>
        <td>₱<?= number_format($pm['amount'], 2) ?></td>
        <td><?= e($pm['method']) ?></td>
        <td><span class="badge <?= badge_class($pm['status']) ?>"><?= ucfirst($pm['status']) ?></span></td>
        <td>
          <?php if ($pm['status'] === 'pending'): ?>
            <?php if (!empty($pm['proof_image'])): ?>
              <a href="<?= e($pm['proof_image']) ?>" target="_blank" style="display:flex;align-items:center;gap:6px;text-decoration:none;">
                <img src="<?= e($pm['proof_image']) ?>" alt="Proof" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                <span class="text-sm text-muted">Submitted — awaiting verification</span>
              </a>
            <?php else: ?>
              <button type="button" class="btn-secondary"
                onclick="openProofModal(<?= $pm['payment_id'] ?>, '<?= e($pm['service']) ?>', '<?= number_format($pm['amount'],2) ?>')">
                Upload GCash/Maya Screenshot
              </button>
            <?php endif; ?>
          <?php elseif ($pm['status'] === 'verified'): ?>
            <?php if (!empty($pm['proof_image'])): ?>
              <a href="<?= e($pm['proof_image']) ?>" target="_blank" class="text-sm" style="color:var(--navy-light);">View receipt</a>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
        <?= empty_row(6, 'No payment records yet. Click "Pay via GCash / Maya" above to submit your first payment.') ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ============ NEW PAYMENT MODAL (self-initiated) ============ -->
<div class="modal-overlay" id="new-payment-overlay">
  <div class="modal" style="width:640px;">
    <div class="modal-head" style="background:var(--navy);border-radius:var(--radius-lg) var(--radius-lg) 0 0;border-bottom:none;">
      <div class="modal-title" style="color:#fff;">Pay via GCash / Maya</div>
      <div class="modal-close" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;" onclick="closeNewPaymentModal()">
        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <form method="post" action="payments_patient.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="submit_payment">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:24px;">
          <div>
            <div class="form-group mb-12">
              <label>Related Appointment (optional)</label>
              <select class="form-control" name="appointment_id">
                <option value="">General / Not tied to a specific appointment</option>
                <?php foreach ($appts as $a): ?>
                  <option value="<?= $a['appointment_id'] ?>"><?= fmt_date($a['appt_date']) ?> — <?= e($a['concern']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group mb-12">
              <label>What is this payment for? *</label>
              <input class="form-control" name="service" placeholder="e.g. Consultation Fee" required>
            </div>
            <div class="form-group mb-12">
              <label>Payment Method *</label>
              <select class="form-control" name="method" id="np-method-select" required onchange="toggleQrDisplay('np')">
                <option value="GCash">GCash</option>
                <option value="Maya">Maya</option>
              </select>
            </div>
            <div class="form-group mb-12">
              <label>Reference / OR No. *</label>
              <input class="form-control" name="reference_no" placeholder="e.g. 1234 5678 9012" required>
            </div>
            <div class="form-group mb-12">
              <label>Sender Name *</label>
              <input class="form-control" name="sender_name" placeholder="Name on the GCash/Maya account" required>
            </div>
            <div class="form-group mb-12">
              <label>Amount (₱) *</label>
              <input class="form-control" type="number" step="0.01" min="0.01" name="amount" required>
            </div>
            <div class="form-group">
              <label>Date Paid *</label>
              <input class="form-control" type="date" name="date_paid" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <div>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;margin-bottom:16px;text-align:center;">
              <div style="font-size:12px;font-weight:700;color:var(--text2);margin-bottom:10px;">SEND PAYMENT TO</div>

              <div id="np-qr-gcash">
                <?php if (!empty($doctorPay['gcash_qr'])): ?>
                  <img src="<?= e($doctorPay['gcash_qr']) ?>" style="width:130px;height:130px;object-fit:contain;background:#fff;border-radius:10px;border:1px solid var(--border);">
                <?php endif; ?>
                <div style="font-size:13px;font-weight:700;color:var(--navy);margin-top:8px;">GCash</div>
                <div style="font-size:13px;color:var(--text);"><?= e(isset($doctorPay['gcash_number']) ? $doctorPay['gcash_number'] : 'Not yet set up') ?></div>
              </div>

              <div id="np-qr-maya" style="display:none;">
                <?php if (!empty($doctorPay['maya_qr'])): ?>
                  <img src="<?= e($doctorPay['maya_qr']) ?>" style="width:130px;height:130px;object-fit:contain;background:#fff;border-radius:10px;border:1px solid var(--border);">
                <?php endif; ?>
                <div style="font-size:13px;font-weight:700;color:var(--teal);margin-top:8px;">Maya</div>
                <div style="font-size:13px;color:var(--text);"><?= e(isset($doctorPay['maya_number']) ? $doctorPay['maya_number'] : 'Not yet set up') ?></div>
              </div>
            </div>

            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px;">Proof of Payment *</label>
            <div id="np-dropzone" style="border:2px dashed var(--border2);border-radius:var(--radius-lg);
                 padding:26px 14px;text-align:center;background:var(--bg);cursor:pointer;"
                 onclick="document.getElementById('np-file-input').click()">
              <div id="np-preview-wrap">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.6" style="margin:0 auto 8px;">
                  <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                </svg>
                <div style="font-size:12px;color:var(--text2);font-weight:600;">PROOF OF PAYMENT</div>
                <div style="font-size:11px;color:var(--red);margin-top:6px;">Please upload your GCash / Maya screenshot</div>
              </div>
              <img id="np-preview-img" style="display:none;max-width:100%;max-height:150px;border-radius:8px;margin-top:6px;">
            </div>
            <div id="np-remove-wrap" style="display:none;text-align:center;margin-top:8px;">
              <button type="button" class="btn-cancel" style="font-size:12px;padding:6px 14px;" onclick="removeFile(event, 'np-file-input', 'np-preview-img', 'np-preview-wrap', 'np-remove-wrap')">
                ✕ Remove &amp; Choose Another
              </button>
            </div>
            <input type="file" name="proof" id="np-file-input" accept="image/*,application/pdf" required style="display:none;" onchange="previewPicked(this, 'np-preview-img', 'np-preview-wrap', 'np-remove-wrap')">
            <button type="button" class="btn-secondary" style="width:100%;margin-top:10px;justify-content:center;"
              onclick="document.getElementById('np-file-input').click()">Upload Image</button>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeNewPaymentModal()">Close</button>
        <button type="submit" class="btn-primary">Submit</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ UPLOAD PROOF MODAL (for a bill the clinic already created) ============ -->
<div class="modal-overlay" id="proof-modal-overlay">
  <div class="modal" style="width:640px;">
    <div class="modal-head" style="background:var(--navy);border-radius:var(--radius-lg) var(--radius-lg) 0 0;border-bottom:none;">
      <div class="modal-title" style="color:#fff;">Submit Proof of Payment</div>
      <div class="modal-close" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;" onclick="closeProofModal()">
        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <form method="post" action="payments_patient.php" enctype="multipart/form-data" id="proof-form">
      <input type="hidden" name="action" value="upload_proof">
      <input type="hidden" name="payment_id" id="pm-payment-id">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:24px;">
          <div>
            <div class="form-group mb-12">
              <label>Service</label>
              <input class="form-control" id="pm-service" readonly>
            </div>
            <div class="form-group mb-12">
              <label>Payment Method *</label>
              <select class="form-control" name="method" id="pm-method-select" required onchange="toggleQrDisplay('pm')">
                <option value="GCash">GCash</option>
                <option value="Maya">Maya</option>
              </select>
            </div>
            <div class="form-group mb-12">
              <label>Reference / OR No. *</label>
              <input class="form-control" name="reference_no" placeholder="e.g. 1234 5678 9012" required>
            </div>
            <div class="form-group mb-12">
              <label>Sender Name *</label>
              <input class="form-control" name="sender_name" placeholder="Name on the GCash/Maya account" required>
            </div>
            <div class="form-group mb-12">
              <label>Amount *</label>
              <input class="form-control" id="pm-amount" readonly>
            </div>
            <div class="form-group">
              <label>Date Paid *</label>
              <input class="form-control" type="date" name="date_paid" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <div>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;margin-bottom:16px;text-align:center;">
              <div style="font-size:12px;font-weight:700;color:var(--text2);margin-bottom:10px;">SEND PAYMENT TO</div>

              <div id="pm-qr-gcash">
                <?php if (!empty($doctorPay['gcash_qr'])): ?>
                  <img src="<?= e($doctorPay['gcash_qr']) ?>" style="width:130px;height:130px;object-fit:contain;background:#fff;border-radius:10px;border:1px solid var(--border);">
                <?php endif; ?>
                <div style="font-size:13px;font-weight:700;color:var(--navy);margin-top:8px;">GCash</div>
                <div style="font-size:13px;color:var(--text);"><?= e(isset($doctorPay['gcash_number']) ? $doctorPay['gcash_number'] : 'Not yet set up') ?></div>
              </div>

              <div id="pm-qr-maya" style="display:none;">
                <?php if (!empty($doctorPay['maya_qr'])): ?>
                  <img src="<?= e($doctorPay['maya_qr']) ?>" style="width:130px;height:130px;object-fit:contain;background:#fff;border-radius:10px;border:1px solid var(--border);">
                <?php endif; ?>
                <div style="font-size:13px;font-weight:700;color:var(--teal);margin-top:8px;">Maya</div>
                <div style="font-size:13px;color:var(--text);"><?= e(isset($doctorPay['maya_number']) ? $doctorPay['maya_number'] : 'Not yet set up') ?></div>
              </div>
            </div>

            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px;">Proof of Payment *</label>
            <div id="proof-dropzone" style="border:2px dashed var(--border2);border-radius:var(--radius-lg);
                 padding:26px 14px;text-align:center;background:var(--bg);cursor:pointer;"
                 onclick="document.getElementById('proof-file-input').click()">
              <div id="proof-preview-wrap">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.6" style="margin:0 auto 8px;">
                  <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                </svg>
                <div style="font-size:12px;color:var(--text2);font-weight:600;">PROOF OF PAYMENT</div>
                <div style="font-size:11px;color:var(--red);margin-top:6px;">Please upload your GCash / Maya screenshot</div>
              </div>
              <img id="proof-preview-img" style="display:none;max-width:100%;max-height:150px;border-radius:8px;margin-top:6px;">
            </div>
            <div id="proof-remove-wrap" style="display:none;text-align:center;margin-top:8px;">
              <button type="button" class="btn-cancel" style="font-size:12px;padding:6px 14px;" onclick="removeFile(event, 'proof-file-input', 'proof-preview-img', 'proof-preview-wrap', 'proof-remove-wrap')">
                ✕ Remove &amp; Choose Another
              </button>
            </div>
            <input type="file" name="proof" id="proof-file-input" accept="image/*,application/pdf" required style="display:none;" onchange="previewPicked(this, 'proof-preview-img', 'proof-preview-wrap', 'proof-remove-wrap')">
            <button type="button" class="btn-secondary" style="width:100%;margin-top:10px;justify-content:center;"
              onclick="document.getElementById('proof-file-input').click()">Upload Image</button>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeProofModal()">Close</button>
        <button type="submit" class="btn-primary">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
  function toggleQrDisplay(prefix) {
    const method = document.getElementById(prefix + '-method-select').value;
    document.getElementById(prefix + '-qr-gcash').style.display = method === 'GCash' ? 'block' : 'none';
    document.getElementById(prefix + '-qr-maya').style.display  = method === 'Maya'  ? 'block' : 'none';
  }

  function openNewPaymentModal() {
    document.getElementById('new-payment-overlay').classList.add('open');
  }
  function closeNewPaymentModal() {
    document.getElementById('new-payment-overlay').classList.remove('open');
    resetUpload('np-file-input', 'np-preview-img', 'np-preview-wrap', 'np-remove-wrap');
  }

  function openProofModal(paymentId, service, amount) {
    document.getElementById('pm-payment-id').value = paymentId;
    document.getElementById('pm-service').value = service;
    document.getElementById('pm-amount').value = '₱' + amount;
    document.getElementById('proof-modal-overlay').classList.add('open');
  }
  function closeProofModal() {
    document.getElementById('proof-modal-overlay').classList.remove('open');
    resetUpload('proof-file-input', 'proof-preview-img', 'proof-preview-wrap', 'proof-remove-wrap');
  }

  function previewPicked(input, imgId, wrapId, removeWrapId) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById(imgId);
      if (input.files[0].type.startsWith('image/')) {
        img.src = e.target.result;
        img.style.display = 'block';
        document.getElementById(wrapId).style.display = 'none';
        document.getElementById(removeWrapId).style.display = 'block';
      }
    };
    reader.readAsDataURL(input.files[0]);
  }

  function removeFile(evt, inputId, imgId, wrapId, removeWrapId) {
    evt.stopPropagation();
    resetUpload(inputId, imgId, wrapId, removeWrapId);
  }

  function resetUpload(inputId, imgId, wrapId, removeWrapId) {
    const input = document.getElementById(inputId);
    input.value = '';
    document.getElementById(imgId).src = '';
    document.getElementById(imgId).style.display = 'none';
    document.getElementById(wrapId).style.display = 'block';
    document.getElementById(removeWrapId).style.display = 'none';
  }
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
