<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

// Modal mode is used when this page is embedded from the Patients screen.
$isModal = isset($_GET['modal']) && $_GET['modal'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'issue') {
    $patientId = (int) (isset($_POST['patient_id']) ? $_POST['patient_id'] : 0);
    $date      = isset($_POST['rx_date']) ? $_POST['rx_date'] : date('Y-m-d');
    $drugNames = isset($_POST['drug_name']) ? $_POST['drug_name'] : [];
    $freqs     = isset($_POST['frequency']) ? $_POST['frequency'] : [];
    $durations = isset($_POST['duration']) ? $_POST['duration'] : [];

    $chk = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE patient_id = ?');
    $chk->execute([$patientId]);
    if (!$patientId || !(int) $chk->fetchColumn()) {
        set_flash('Please choose a patient from the patient list.');
        header('Location: patients_doctor.php'); exit;
    }

    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT INTO prescriptions (patient_id, date_issued, status) VALUES (?, ?, "issued")');
    $ins->execute([$patientId, $date]);
    $rxId = $pdo->lastInsertId();

    $insItem = $pdo->prepare('INSERT INTO prescription_items (prescription_id, drug_name, dosage, frequency, duration) VALUES (?,?,?,?,?)');
    $any = false;
    foreach ($drugNames as $i => $drug) {
        $drug = trim($drug);
        if ($drug === '') continue;
        $any = true;
        $insItem->execute([$rxId, $drug, '', trim(isset($freqs[$i]) ? $freqs[$i] : ''), trim(isset($durations[$i]) ? $durations[$i] : '')]);
    }
    if ($any) {
        $pdo->commit();
        set_flash('E-Prescription issued successfully.');
        header('Location: prescriptions_doctor.php?view=' . $rxId . ($isModal ? '&modal=1' : '')); exit;
    }
    $pdo->rollBack();
    set_flash('Please add at least one medicine.');
    header('Location: prescriptions_doctor.php?patient=' . $patientId . ($isModal ? '&modal=1' : '')); exit;
}

// Doctor clinic-header info — from Settings if filled in, otherwise a sensible default.
$docName  = isset($doctorProfile['full_name']) ? $doctorProfile['full_name'] : $_SESSION['full_name'];
$docLic   = isset($doctorProfile['license_no']) ? $doctorProfile['license_no'] : '';
$docSpec  = isset($doctorProfile['specialization']) ? $doctorProfile['specialization'] : 'General Practitioner';
$docAddr  = isset($doctorProfile['clinic_address']) ? $doctorProfile['clinic_address'] : 'Surigao City, Surigao del Norte, Philippines';
$docPhone = isset($doctorProfile['contact_no']) ? $doctorProfile['contact_no'] : '';

// ---------- View a specific issued prescription ----------
$viewRx = null; $viewItems = [];
if (!empty($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT pr.*, p.first_name, p.last_name, p.dob, p.gender, p.address
                            FROM prescriptions pr JOIN patients p ON p.patient_id = pr.patient_id
                            WHERE pr.prescription_id = ?");
    $stmt->execute([(int) $_GET['view']]);
    $viewRx = $stmt->fetch();
    if ($viewRx) {
        $itemStmt = $pdo->prepare('SELECT * FROM prescription_items WHERE prescription_id = ?');
        $itemStmt->execute([$viewRx['prescription_id']]);
        $viewItems = $itemStmt->fetchAll();
    }
}

// ---------- The patient this prescription is for (chosen on the Patients page) ----------
$rxPatient = null;
if (!$viewRx) {
    $pid = (int) (isset($_GET['patient']) ? $_GET['patient'] : 0);
    if ($pid) {
        $ps = $pdo->prepare('SELECT patient_id, first_name, middle_name, last_name, dob, gender, address, allergies FROM patients WHERE patient_id = ?');
        $ps->execute([$pid]);
        $rxPatient = $ps->fetch();
    }
    if (!$rxPatient) {
        set_flash('Choose a patient first, then click "E-Prescription" on their row.');
        header('Location: patients_doctor.php'); exit;
    }
}

// Earlier prescriptions of this patient (shown under the form)
$recent = [];
if ($rxPatient) {
    $rq = $pdo->prepare('SELECT pr.*, p.first_name, p.last_name FROM prescriptions pr
                         JOIN patients p ON p.patient_id = pr.patient_id
                         WHERE pr.patient_id = ? ORDER BY pr.created_at DESC LIMIT 15');
    $rq->execute([$rxPatient['patient_id']]);
    $recent = $rq->fetchAll();
}

$pageTitle  = 'E-Prescription';
$pageSub    = 'Issue and track digital prescriptions';
$activePage = 'patients';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if ($isModal): ?>
<style>
  html { height:100% !important; overflow-y:auto !important; }
  body { margin:0 !important; min-height:100% !important; height:auto !important; overflow-x:hidden !important; overflow-y:auto !important; background:#f4f7fb !important; }
  .app { display:block !important; min-height:0 !important; height:auto !important; overflow:visible !important; }
  .sidebar, .topbar, #payment-popup-toast, .toast { display:none !important; }
  .main { margin-left:0 !important; width:100% !important; min-width:0 !important; min-height:0 !important; height:auto !important; overflow:visible !important; }
  .content { margin:0 !important; padding:16px !important; width:100% !important; max-width:none !important; height:auto !important; min-height:0 !important; overflow:visible !important; }
  .rx-card { margin-bottom:14px !important; }
  .card { margin-bottom:12px !important; }
  #rx-print-wrap { padding-top:0 !important; }
  @media (max-width:640px) { .content { padding:10px !important; } }
</style>
<?php endif; ?>


<style>
@media print {
  @page { size: 101.6mm 152.4mm; margin: 0; }
  .sidebar, .topbar, .toast, #rx-issue-form-card, .card, .no-print { display: none !important; }
  body, .content, .main, .app { background: #fff !important; margin: 0 !important; padding: 0 !important; }
  #rx-print-area {
    display: block !important; box-shadow: none !important; border: none !important;
    margin: 0 !important; width: 101.6mm !important; height: 152.4mm !important;
  }
  #rx-print-outer { padding: 5mm !important; }
}
#rx-print-wrap { display: <?= $viewRx ? 'flex' : 'none' ?>; justify-content: center; padding: 20px 0; }
#rx-print-area { width: 384px; aspect-ratio: 101.6 / 152.4; }

/* ===== International Rx Pad — 4R document ===== */
.rxdoc { width: 100%; height: 100%; background: #fff; border: 1px solid var(--navy); position: relative; font-family: 'Inter', 'Segoe UI', sans-serif; display: flex; flex-direction: column; }
.rxdoc-topbar { height: 5px; background: linear-gradient(90deg, var(--navy) 0%, var(--navy-light) 55%, var(--teal) 100%); flex-shrink: 0; }
#rx-print-outer { padding: 14px 16px 12px; display: flex; flex-direction: column; flex: 1; min-height: 0; }

.rxdoc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; padding-bottom: 9px; border-bottom: 1.5px solid var(--navy); }
.rxdoc-brand { display: flex; align-items: center; gap: 7px; }
.rxdoc-mark { width: 28px; height: 28px; flex-shrink: 0; object-fit:contain; border-radius:3px; }
.rxdoc-clinic-name { font-family: 'Fraunces', Georgia, serif; font-size: 12.5px; font-weight: 600; color: var(--navy); line-height: 1.2; }
.rxdoc-clinic-sub { font-size: 7px; color: var(--text2); line-height: 1.5; margin-top: 2px; }
.rxdoc-refbox { text-align: right; flex-shrink: 0; }
.rxdoc-refno { font-size: 7px; font-weight: 700; color: var(--teal); letter-spacing: 0.3px; }
.rxdoc-refdate { font-size: 7px; color: var(--text2); margin-top: 2px; }

.rxdoc-patient { padding: 9px 0; border-bottom: 1px dashed var(--border2); }
.rxdoc-patient-name { font-size: 10.5px; font-weight: 700; color: var(--text); }
.rxdoc-patient-meta { font-size: 7.5px; color: var(--text2); margin-top: 3px; line-height: 1.5; }

.rxdoc-symbol-row { display: flex; align-items: baseline; gap: 6px; padding: 9px 0 5px; }
.rxdoc-symbol { font-family: 'Fraunces', Georgia, serif; font-size: 21px; font-weight: 600; color: var(--navy); line-height: 1; }
.rxdoc-symbol-label { font-size: 6.5px; letter-spacing: 1.2px; color: var(--text3); text-transform: uppercase; }

.rxdoc-meds { flex: 1; overflow: hidden; display: flex; flex-direction: column; gap: 7px; }
.rxdoc-med { display: flex; gap: 7px; }
.rxdoc-med-num { width: 13px; height: 13px; border-radius: 50%; background: var(--navy); color: #fff; font-size: 7px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
.rxdoc-med-name { font-size: 9px; font-weight: 700; color: var(--text); line-height: 1.3; }
.rxdoc-med-meta { font-size: 7.5px; color: var(--text2); margin-top: 1px; }

.rxdoc-foot { margin-top: auto; padding-top: 8px; }
.rxdoc-sigline { border-top: 1px solid var(--text); width: 76%; margin: 0 auto 4px; }
.rxdoc-signame { text-align: center; font-size: 8px; font-weight: 700; color: var(--text); }
.rxdoc-sigrole { text-align: center; font-size: 6px; color: var(--text3); letter-spacing: 0.3px; text-transform: uppercase; margin-top: 1px; }
.rxdoc-disclaimer { text-align: center; font-size: 5.8px; color: var(--text3); margin-top: 8px; line-height: 1.5; border-top: 1px solid var(--border); padding-top: 6px; }
</style>

<?php if ($viewRx): $fullName = $viewRx['first_name'].' '.$viewRx['last_name']; ?>
<div class="no-print mb-16">
  <a class="btn-cancel" href="patients_doctor.php?view=<?= (int) $viewRx['patient_id'] ?>" style="text-decoration:none;">&larr; Back to patient</a>
</div>

<div id="rx-print-wrap">
  <div id="rx-print-area">
    <div class="rxdoc">
      <div class="rxdoc-topbar"></div>
      <div id="rx-print-outer">
        <div class="rxdoc-head">
          <div class="rxdoc-brand">
            <img class="rxdoc-mark" src="assets/wedocare-logo.png" alt="WeDoCare official logo">
            <div>
              <div class="rxdoc-clinic-name">WeDoCare Clinic</div>
              <div class="rxdoc-clinic-sub">
                <?= e($docName) ?><br>
                <?= e($docSpec) ?><?= $docLic ? ' &middot; Lic. '.e($docLic) : '' ?>
              </div>
            </div>
          </div>
          <div class="rxdoc-refbox">
            <div class="rxdoc-refno">Rx-<?= str_pad($viewRx['prescription_id'], 5, '0', STR_PAD_LEFT) ?></div>
            <div class="rxdoc-refdate"><?= fmt_date($viewRx['date_issued']) ?></div>
          </div>
        </div>

        <div class="rxdoc-patient">
          <div class="rxdoc-patient-name"><?= e($fullName) ?></div>
          <div class="rxdoc-patient-meta">
            <?= calc_age($viewRx['dob']) ?> yrs old &middot; <?= e($viewRx['gender']) ?><br>
            <?= e($viewRx['address']) ?>
          </div>
        </div>

        <div class="rxdoc-symbol-row">
          <div class="rxdoc-symbol">℞</div>
          <div class="rxdoc-symbol-label">Prescription</div>
        </div>

        <div class="rxdoc-meds">
          <?php if ($viewItems): foreach ($viewItems as $i => $it): ?>
            <div class="rxdoc-med">
              <div class="rxdoc-med-num"><?= $i+1 ?></div>
              <div>
                <div class="rxdoc-med-name"><?= e($it['drug_name']) ?></div>
                <div class="rxdoc-med-meta"><?= e($it['frequency']) ?><?= $it['duration'] ? ' &middot; '.e($it['duration']) : '' ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <div class="rxdoc-foot">
          <div class="rxdoc-sigline"></div>
          <div class="rxdoc-signame"><?= e($docName) ?></div>
          <div class="rxdoc-sigrole">Attending Physician</div>
          <div class="rxdoc-disclaimer">
            <?= e($docAddr) ?><?= $docPhone ? ' &middot; '.e($docPhone) : '' ?><br>
            Issued via WeDoCare Patient Management System
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="no-print" style="text-align:center;margin-top:22px;">
  <button type="button" class="btn-primary" onclick="window.print()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Print Prescription (4R)
  </button>
  <div class="text-sm text-muted" style="margin-top:8px;">In the print dialog, set Paper Size to <strong>4R (4×6 in)</strong> and turn off "Headers and footers" for a clean result.</div>
</div>
<?php endif; ?>

<?php if (!$viewRx): ?>
<div class="rx-card mb-20" id="rx-issue-form-card">
  <div class="rx-header">
    <div>
      <div class="rx-clinic-name"><?= e($docName) ?></div>
      <div class="rx-clinic-info">
        <?= e($docSpec) ?><?= $docLic ? ' &middot; License No: '.e($docLic) : '' ?><br>
        <?= e($docAddr) ?><br>
        <?= $docPhone ? 'Contact: '.e($docPhone) : '' ?>
      </div>
    </div>
    <div class="rx-date-display">Date: <?= date('F j, Y') ?></div>
  </div>

  <div class="rx-body">
    <form method="post" action="prescriptions_doctor.php?patient=<?= (int) $rxPatient['patient_id'] ?><?= $isModal ? '&modal=1' : '' ?>" id="rx-form">
      <input type="hidden" name="action" value="issue">
      <div class="rx-patient-grid">
        <div>
          <div class="rx-field-label">Patient</div>
          <input type="hidden" name="patient_id" value="<?= (int) $rxPatient['patient_id'] ?>">
          <input class="rx-field-input" readonly style="font-weight:600;" value="<?= e($rxPatient['last_name'].', '.$rxPatient['first_name'].($rxPatient['middle_name'] ? ' '.$rxPatient['middle_name'] : '')) ?>">
        </div>
        <div>
          <div class="rx-field-label">Age / Sex</div>
          <input class="rx-field-input" id="rx-agesex" readonly value="<?= calc_age($rxPatient['dob']) ?> / <?= e($rxPatient['gender']) ?>">
        </div>
        <div>
          <div class="rx-field-label">Date</div>
          <input class="rx-field-input" type="date" name="rx_date" value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <div class="rx-field-label">Diagnosis</div>
          <input class="rx-field-input" name="diagnosis" placeholder="Diagnosis / chief complaint">
        </div>
        <div>
          <div class="rx-field-label">Address</div>
          <input class="rx-field-input" id="rx-address" readonly value="<?= e($rxPatient['address']) ?>">
        </div>
        <div>
          <div class="rx-field-label">Allergies</div>
          <input class="rx-field-input" name="allergies" placeholder="NKDA" value="<?= e($rxPatient['allergies']) ?>">
        </div>
      </div>

      <div class="rx-symbol">℞</div>

      <div id="rx-drugs-container">
        <div class="rx-drug-item">
          <div class="rx-drug-num">1</div>
          <div class="rx-drug-fields">
            <input class="form-control" name="drug_name[]" placeholder="Medicine name & strength (e.g. Amoxicillin 500mg)">
            <input class="form-control" name="frequency[]" placeholder="Frequency (e.g. 3x daily)">
            <input class="form-control" name="duration[]" placeholder="Duration (e.g. 7 days)">
          </div>
        </div>
        <div class="rx-drug-item">
          <div class="rx-drug-num">2</div>
          <div class="rx-drug-fields">
            <input class="form-control" name="drug_name[]" placeholder="Medicine name & strength">
            <input class="form-control" name="frequency[]" placeholder="Frequency">
            <input class="form-control" name="duration[]" placeholder="Duration">
          </div>
        </div>
      </div>

      <button type="button" class="btn-outline mb-20" onclick="rxAddDrug()" style="font-size:12px">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Medicine
      </button>

      <div class="mb-20">
        <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px">Special Instructions / Notes</label>
        <textarea class="form-control" name="notes" placeholder="e.g. Take after meals. Avoid alcohol. Complete full course. Return after 1 week for follow-up."></textarea>
      </div>

      <div class="rx-sig-area">
        <div>
          <div class="rx-sig-line"></div>
          <div class="rx-sig-label"><?= e($docName) ?></div>
          <div style="font-size:11px;color:var(--text3);text-align:center;margin-top:2px">Signature Over Printed Name</div>
        </div>
        <div class="rx-actions">
          <button type="submit" class="btn-primary">
            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/></svg>
            Issue Prescription
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><div class="card-title">Previous Prescriptions of This Patient</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Patient</th><th>Date Issued</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if ($recent): foreach ($recent as $rx): ?>
        <tr>
          <td><?= e($rx['first_name'].' '.$rx['last_name']) ?></td>
          <td><?= fmt_date($rx['date_issued']) ?></td>
          <td><span class="badge <?= badge_class($rx['status']) ?>"><?= ucfirst($rx['status']) ?></span></td>
          <td><a class="mini-btn" href="prescriptions_doctor.php?view=<?= $rx['prescription_id'] ?>" style="text-decoration:none;">View</a></td>
        </tr>
        <?php endforeach; else: ?>
          <?= empty_row(4, 'No prescriptions issued to this patient yet') ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
  let rxDrugCount = 2;
  function rxAddDrug() {
    rxDrugCount++;
    const wrap = document.createElement('div');
    wrap.className = 'rx-drug-item';
    wrap.innerHTML = `
      <div class="rx-drug-num">${rxDrugCount}</div>
      <div class="rx-drug-fields">
        <input class="form-control" name="drug_name[]" placeholder="Medicine name & strength">
        <input class="form-control" name="frequency[]" placeholder="Frequency">
        <input class="form-control" name="duration[]" placeholder="Duration">
      </div>`;
    document.getElementById('rx-drugs-container').appendChild(wrap);
  }
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
