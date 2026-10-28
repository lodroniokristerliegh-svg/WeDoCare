<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('patient');

$patientId = (int) $_SESSION['patient_id'];
$downloadId = isset($_GET['download']) ? (int) $_GET['download'] : 0;
$selectedRx = null;
$selectedItems = [];

// Clinic details are read from the doctor's profile so the patient's copy
// matches the prescription issued by the doctor.
$doctor = [];
try {
    $doctorStmt = $pdo->query("SELECT full_name, license_no, specialization, clinic_address, contact_no FROM users WHERE role = 'doctor' AND is_active = 1 ORDER BY user_id ASC LIMIT 1");
    $doctor = $doctorStmt ? $doctorStmt->fetch(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $doctor = [];
}
$docName = !empty($doctor['full_name']) ? $doctor['full_name'] : 'Dr. Deguito';
$docLic = isset($doctor['license_no']) ? $doctor['license_no'] : '';
$docSpec = !empty($doctor['specialization']) ? $doctor['specialization'] : 'General Practitioner';
$docAddr = !empty($doctor['clinic_address']) ? $doctor['clinic_address'] : 'Surigao City, Surigao del Norte, Philippines';
$docPhone = isset($doctor['contact_no']) ? $doctor['contact_no'] : '';

if ($downloadId > 0) {
    // Authorization is enforced in the query: patients can only view their own prescriptions.
    $stmt = $pdo->prepare("SELECT pr.*, p.first_name, p.middle_name, p.last_name, p.dob, p.gender, p.address FROM prescriptions pr JOIN patients p ON p.patient_id = pr.patient_id WHERE pr.prescription_id = ? AND pr.patient_id = ? AND EXISTS (SELECT 1 FROM payments pay WHERE pay.patient_id = pr.patient_id AND pay.status = 'verified')");
    $stmt->execute([$downloadId, $patientId]);
    $selectedRx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$selectedRx) {
        http_response_code(404);
        $downloadId = 0;
        set_flash('That prescription could not be found in your account.');
    } else {
        $itemStmt = $pdo->prepare('SELECT drug_name, dosage, frequency, duration FROM prescription_items WHERE prescription_id = ? ORDER BY item_id ASC');
        $itemStmt->execute([$downloadId]);
        $selectedItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$rx = $pdo->prepare("SELECT * FROM prescriptions WHERE patient_id = ? AND EXISTS (SELECT 1 FROM payments pay WHERE pay.patient_id = prescriptions.patient_id AND pay.status = 'verified') ORDER BY date_issued DESC, prescription_id DESC");
$rx->execute([$patientId]);
$prescriptions = $rx->fetchAll(PDO::FETCH_ASSOC);
$itemsStmt = $pdo->prepare('SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY item_id ASC');

$pageTitle  = $selectedRx ? 'Download E-Prescription' : 'My Prescriptions';
$pageSub    = $selectedRx ? 'Download a JPG image of your prescription' : 'Digital prescriptions issued by Dr. Deguito';
$activePage = 'prescriptions';
include __DIR__ . '/includes/layout_top.php';
?>

<style>
.rx-download-actions { display:flex; justify-content:center; align-items:center; gap:10px; flex-wrap:wrap; margin:18px 0 8px; }
.rx-download-note { text-align:center; color:var(--text2); font-size:12px; margin:8px 0 20px; }
#rx-print-wrap { display:flex; justify-content:center; padding:18px 0; }
#rx-print-area { width:384px; max-width:100%; aspect-ratio:101.6 / 152.4; }
.rxdoc { width:100%; height:100%; min-height:540px; background:#fff; border:1px solid var(--navy); position:relative; font-family:Inter,'Segoe UI',Arial,sans-serif; display:flex; flex-direction:column; box-sizing:border-box; }
.rxdoc * { box-sizing:border-box; }
.rxdoc-topbar { height:5px; background:linear-gradient(90deg,var(--navy) 0%,var(--navy-light) 55%,var(--teal) 100%); flex-shrink:0; }
#rx-print-outer { padding:14px 16px 12px; display:flex; flex-direction:column; flex:1; min-height:0; }
.rxdoc-head { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; padding-bottom:9px; border-bottom:1.5px solid var(--navy); }
.rxdoc-brand { display:flex; align-items:center; gap:7px; min-width:0; }
.rxdoc-mark { width:28px; height:28px; flex-shrink:0; object-fit:contain; border-radius:3px; }
.rxdoc-clinic-name { font-family:Fraunces,Georgia,serif; font-size:12.5px; font-weight:600; color:var(--navy); line-height:1.2; }
.rxdoc-clinic-sub { font-size:7px; color:var(--text2); line-height:1.5; margin-top:2px; }
.rxdoc-refbox { text-align:right; flex-shrink:0; }
.rxdoc-refno { font-size:7px; font-weight:700; color:var(--teal); letter-spacing:.3px; }
.rxdoc-refdate { font-size:7px; color:var(--text2); margin-top:2px; }
.rxdoc-patient { padding:9px 0; border-bottom:1px dashed var(--border2); }
.rxdoc-patient-name { font-size:10.5px; font-weight:700; color:var(--text); }
.rxdoc-patient-meta { font-size:7.5px; color:var(--text2); margin-top:3px; line-height:1.5; overflow-wrap:anywhere; }
.rxdoc-symbol-row { display:flex; align-items:baseline; gap:6px; padding:9px 0 5px; }
.rxdoc-symbol { font-family:Fraunces,Georgia,serif; font-size:21px; font-weight:600; color:var(--navy); line-height:1; }
.rxdoc-symbol-label { font-size:6.5px; letter-spacing:1.2px; color:var(--text3); text-transform:uppercase; }
.rxdoc-meds { flex:1; min-height:90px; overflow-wrap:anywhere; display:flex; flex-direction:column; gap:7px; }
.rxdoc-med { display:flex; gap:7px; break-inside:avoid; }
.rxdoc-med-num { width:13px; height:13px; border-radius:50%; background:var(--navy); color:#fff; font-size:7px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
.rxdoc-med-name { font-size:9px; font-weight:700; color:var(--text); line-height:1.3; }
.rxdoc-med-meta { font-size:7.5px; color:var(--text2); margin-top:1px; }
.rxdoc-foot { margin-top:auto; padding-top:8px; }
.rxdoc-sigline { border-top:1px solid var(--text); width:76%; margin:0 auto 4px; }
.rxdoc-signame { text-align:center; font-size:8px; font-weight:700; color:var(--text); }
.rxdoc-sigrole { text-align:center; font-size:6px; color:var(--text3); letter-spacing:.3px; text-transform:uppercase; margin-top:1px; }
.rxdoc-disclaimer { text-align:center; font-size:5.8px; color:var(--text3); margin-top:8px; line-height:1.5; border-top:1px solid var(--border); padding-top:6px; }
@media(max-width:480px) { #rx-print-area { width:100%; } .rxdoc { min-height:540px; } }
</style>

<?php if ($selectedRx):
    $fullName = trim($selectedRx['first_name'] . ' ' . (!empty($selectedRx['middle_name']) ? $selectedRx['middle_name'] . ' ' : '') . $selectedRx['last_name']);
?>
<div class="no-print mb-16"><a class="btn-cancel" href="prescriptions_patient.php" style="text-decoration:none;">&larr; Back to My Prescriptions</a></div>
<div id="rx-print-wrap">
  <div id="rx-print-area">
    <div class="rxdoc">
      <div class="rxdoc-topbar"></div>
      <div id="rx-print-outer">
        <div class="rxdoc-head">
          <div class="rxdoc-brand">
            <img class="rxdoc-mark" src="assets/wedocare-logo.png" alt="WeDoCare official logo">
            <div><div class="rxdoc-clinic-name">WeDoCare Clinic</div><div class="rxdoc-clinic-sub"><?= e($docName) ?><br><?= e($docSpec) ?><?= $docLic ? ' &middot; Lic. ' . e($docLic) : '' ?></div></div>
          </div>
          <div class="rxdoc-refbox"><div class="rxdoc-refno">Rx-<?= str_pad((string)$selectedRx['prescription_id'], 5, '0', STR_PAD_LEFT) ?></div><div class="rxdoc-refdate"><?= fmt_date($selectedRx['date_issued']) ?></div></div>
        </div>
        <div class="rxdoc-patient">
          <div class="rxdoc-patient-name"><?= e($fullName) ?></div>
          <div class="rxdoc-patient-meta"><?= e((string)calc_age($selectedRx['dob'])) ?> yrs old &middot; <?= e($selectedRx['gender']) ?><br><?= e($selectedRx['address']) ?></div>
        </div>
        <div class="rxdoc-symbol-row"><div class="rxdoc-symbol">℞</div><div class="rxdoc-symbol-label">Prescription</div></div>
        <div class="rxdoc-meds">
          <?php if ($selectedItems): foreach ($selectedItems as $i => $it): ?>
          <div class="rxdoc-med"><div class="rxdoc-med-num"><?= $i + 1 ?></div><div><div class="rxdoc-med-name"><?= e($it['drug_name']) ?></div><div class="rxdoc-med-meta"><?= e($it['frequency']) ?><?= !empty($it['duration']) ? ' &middot; ' . e($it['duration']) : '' ?></div><?php if (!empty($it['dosage'])): ?><div class="rxdoc-med-meta">Dosage: <?= e($it['dosage']) ?></div><?php endif; ?></div></div>
          <?php endforeach; else: ?><div class="rxdoc-med-name">No medicine items were listed.</div><?php endif; ?>
        </div>
        <div class="rxdoc-foot"><div class="rxdoc-sigline"></div><div class="rxdoc-signame"><?= e($docName) ?></div><div class="rxdoc-sigrole">Attending Physician</div><div class="rxdoc-disclaimer"><?= e($docAddr) ?><?= $docPhone ? ' &middot; ' . e($docPhone) : '' ?><br>Issued via WeDoCare Patient Management System</div></div>
      </div>
    </div>
  </div>
</div>
<div class="rx-download-actions no-print">
  <button type="button" class="btn-primary" id="download-rx-jpg">Download Prescription as JPG</button>
  <a class="btn-secondary" href="prescriptions_patient.php" style="text-decoration:none;">Back to prescriptions</a>
</div>
<p class="rx-download-note no-print">Your prescription will be downloaded as a JPG image that you can save on your phone and show at the pharmacy.</p>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
  var button = document.getElementById("download-rx-jpg");
  var target = document.querySelector("#rx-print-area .rxdoc");
  if (!button || !target) return;
  button.addEventListener("click", function () {
    if (typeof html2canvas !== "function") {
      alert("The JPG download tool could not load. Please check your internet connection and try again.");
      return;
    }
    var originalText = button.textContent;
    button.disabled = true;
    button.textContent = "Preparing JPG...";
    html2canvas(target, { scale: 3, backgroundColor: "#ffffff", useCORS: true, allowTaint: false, logging: false }).then(function (canvas) {
      var link = document.createElement("a");
      link.download = "WeDoCare-Prescription-Rx-<?= (int)$selectedRx['prescription_id'] ?>.jpg";
      link.href = canvas.toDataURL("image/jpeg", 0.95);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }).catch(function () {
      alert("Unable to create the JPG. Please reload the page and try again.");
    }).finally(function () {
      button.disabled = false;
      button.textContent = originalText;
    });
  });
})();
</script>
<?php else: ?>
<?php if ($prescriptions): foreach ($prescriptions as $r): $itemsStmt->execute([$r['prescription_id']]); $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="card mb-16">
  <div class="card-head"><div class="card-title">Prescription — <?= fmt_date($r['date_issued']) ?></div><span class="badge <?= badge_class($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></div>
  <div class="table-card"><table class="table"><thead><tr><th>Medicine</th><th>Frequency</th><th>Duration</th></tr></thead><tbody>
    <?php if ($items): foreach ($items as $it): ?><tr><td><?= e($it['drug_name']) ?><?php if (!empty($it['dosage'])): ?><div class="text-sm text-muted"><?= e($it['dosage']) ?></div><?php endif; ?></td><td><?= e($it['frequency']) ?></td><td><?= e($it['duration']) ?></td></tr><?php endforeach; else: ?><?= empty_row(3, 'No medicine items listed.') ?><?php endif; ?>
  </tbody></table></div>
  <div style="margin-top:14px;display:flex;justify-content:flex-end;"><a class="btn-primary" href="prescriptions_patient.php?download=<?= (int)$r['prescription_id'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">Download E-Prescription</a></div>
</div>
<?php endforeach; else: ?>
<div class="card" style="text-align:center;padding:30px 22px;"><div style="font-size:38px;margin-bottom:10px;" aria-hidden="true">💊</div><h2 style="font-size:20px;margin-bottom:8px;">No prescriptions available yet</h2><p style="font-size:15px;color:var(--text2);max-width:520px;margin:0 auto 14px;">Your e-prescriptions will appear here only after the clinic verifies your payment. If you have already paid, please wait for the clinic to verify the payment.</p><a class="btn-secondary" href="dashboard_patient.php" style="text-decoration:none;">Back to My Dashboard</a></div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
