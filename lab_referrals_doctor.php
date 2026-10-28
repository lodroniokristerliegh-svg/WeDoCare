<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

$LAB_TESTS = [
    'Complete Blood Count (CBC)', 'Urinalysis', 'Fasting Blood Sugar (FBS)', 'Lipid Profile',
    'Creatinine', 'SGPT / ALT', 'SGOT / AST', 'Chest X-Ray', 'ECG / EKG', 'Uric Acid',
    'Blood Typing', 'HbA1c', 'Thyroid Function Test (TSH)', 'Hepatitis B Surface Antigen',
    'Ultrasound (Specify)', 'Stool Exam', 'Serum Electrolytes', '2D Echo / Echocardiogram',
    'Pap Smear', 'PSA (Prostate Specific Antigen)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'issue') {
    $patientId = (int) (isset($_POST['patient_id']) ? $_POST['patient_id'] : 0);
    $date      = isset($_POST['lab_date']) ? $_POST['lab_date'] : date('Y-m-d');
    $tests     = isset($_POST['tests']) ? $_POST['tests'] : [];

    if (!$patientId) {
        set_flash('Please select a patient.');
    } elseif (!$tests) {
        set_flash('Please select at least one laboratory test.');
    } else {
        $pdo->beginTransaction();
        $ins = $pdo->prepare('INSERT INTO lab_referrals (patient_id, date_issued, status) VALUES (?, ?, "issued")');
        $ins->execute([$patientId, $date]);
        $refId = $pdo->lastInsertId();

        $insTest = $pdo->prepare('INSERT INTO lab_referral_tests (referral_id, test_name) VALUES (?, ?)');
        foreach ($tests as $t) { $insTest->execute([$refId, $t]); }

        $pdo->commit();
        set_flash('Laboratory referral issued successfully.');
        header('Location: lab_referrals_doctor.php?view=' . $refId); exit;
    }
    header('Location: lab_referrals_doctor.php'); exit;
}

$docName = isset($doctorProfile['full_name']) ? $doctorProfile['full_name'] : $_SESSION['full_name'];
$docLic  = isset($doctorProfile['license_no']) ? $doctorProfile['license_no'] : '';
$docSpec = isset($doctorProfile['specialization']) ? $doctorProfile['specialization'] : 'General Practitioner';
$docAddr = isset($doctorProfile['clinic_address']) ? $doctorProfile['clinic_address'] : 'Surigao City, Surigao del Norte, Philippines';

// ---------- View a specific issued referral ----------
$viewRef = null; $viewTests = [];
if (!empty($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT lr.*, p.first_name, p.last_name, p.dob, p.gender, p.address
                            FROM lab_referrals lr JOIN patients p ON p.patient_id = lr.patient_id
                            WHERE lr.referral_id = ?");
    $stmt->execute([(int) $_GET['view']]);
    $viewRef = $stmt->fetch();
    if ($viewRef) {
        $tStmt = $pdo->prepare('SELECT test_name FROM lab_referral_tests WHERE referral_id = ?');
        $tStmt->execute([$viewRef['referral_id']]);
        $viewTests = $tStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

$patients = $pdo->query("SELECT patient_id, first_name, last_name, dob, gender, address FROM patients ORDER BY last_name")->fetchAll();

$recent = $pdo->query("SELECT lr.*, p.first_name, p.last_name,
                        (SELECT COUNT(*) FROM lab_referral_tests t WHERE t.referral_id = lr.referral_id) AS test_count
                        FROM lab_referrals lr JOIN patients p ON p.patient_id = lr.patient_id
                        ORDER BY lr.created_at DESC LIMIT 15")->fetchAll();

$pageTitle  = 'Lab Referrals';
$pageSub    = 'Issue laboratory request forms';
$activePage = 'labreferral';
include __DIR__ . '/includes/layout_top.php';
?>

<style>
@media print {
  @page { size: 101.6mm 152.4mm; margin: 0; }
  .sidebar, .topbar, .toast, #lab-issue-form-card, .card, .no-print { display: none !important; }
  body, .content, .main, .app { background: #fff !important; margin: 0 !important; padding: 0 !important; }
  #lab-print-area {
    display: block !important; box-shadow: none !important; border: none !important;
    margin: 0 !important; width: 101.6mm !important; height: 152.4mm !important;
  }
  #lab-print-outer { padding: 5mm !important; }
}
#lab-print-wrap { display: <?= $viewRef ? 'flex' : 'none' ?>; justify-content: center; padding: 20px 0; }
#lab-print-area { width: 384px; aspect-ratio: 101.6 / 152.4; }

/* ===== International Lab Request — 4R document ===== */
.labdoc { width: 100%; height: 100%; background: #fff; border: 1px solid var(--navy); position: relative; font-family: 'Inter', 'Segoe UI', sans-serif; display: flex; flex-direction: column; }
.labdoc-topbar { height: 5px; background: linear-gradient(90deg, var(--teal) 0%, var(--navy-light) 55%, var(--navy) 100%); flex-shrink: 0; }
#lab-print-outer { padding: 14px 16px 12px; display: flex; flex-direction: column; flex: 1; min-height: 0; }

.labdoc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; padding-bottom: 9px; border-bottom: 1.5px solid var(--navy); }
.labdoc-brand { display: flex; align-items: center; gap: 7px; }
.labdoc-mark { width: 22px; height: 22px; flex-shrink: 0; }
.labdoc-clinic-name { font-family: 'Fraunces', Georgia, serif; font-size: 12.5px; font-weight: 600; color: var(--navy); line-height: 1.2; }
.labdoc-clinic-sub { font-size: 7px; color: var(--text2); line-height: 1.5; margin-top: 2px; }
.labdoc-refbox { text-align: right; flex-shrink: 0; }
.labdoc-refno { font-size: 7px; font-weight: 700; color: var(--teal); letter-spacing: 0.3px; }
.labdoc-refdate { font-size: 7px; color: var(--text2); margin-top: 2px; }

.labdoc-title { text-align: center; font-size: 8px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--text2); padding: 7px 0; border-bottom: 1px dashed var(--border2); }

.labdoc-patient { padding: 9px 0; border-bottom: 1px dashed var(--border2); }
.labdoc-patient-name { font-size: 10.5px; font-weight: 700; color: var(--text); }
.labdoc-patient-meta { font-size: 7.5px; color: var(--text2); margin-top: 3px; line-height: 1.5; }

.labdoc-section-label { font-size: 6.5px; letter-spacing: 1px; color: var(--text3); text-transform: uppercase; margin: 9px 0 6px; }
.labdoc-tests { flex: 1; overflow: hidden; display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; align-content: start; }
.labdoc-test { display: flex; align-items: flex-start; gap: 4px; font-size: 7px; color: var(--text); line-height: 1.3; }
.labdoc-test svg { width: 8px; height: 8px; stroke: var(--teal); fill: none; stroke-width: 3; flex-shrink: 0; margin-top: 1.5px; }

.labdoc-foot { margin-top: auto; padding-top: 8px; }
.labdoc-sigline { border-top: 1px solid var(--text); width: 76%; margin: 0 auto 4px; }
.labdoc-signame { text-align: center; font-size: 8px; font-weight: 700; color: var(--text); }
.labdoc-sigrole { text-align: center; font-size: 6px; color: var(--text3); letter-spacing: 0.3px; text-transform: uppercase; margin-top: 1px; }
.labdoc-disclaimer { text-align: center; font-size: 5.8px; color: var(--text3); margin-top: 8px; line-height: 1.5; border-top: 1px solid var(--border); padding-top: 6px; }
</style>

<?php if ($viewRef): $fullName = $viewRef['first_name'].' '.$viewRef['last_name']; ?>
<div class="no-print mb-16">
  <a class="btn-cancel" href="lab_referrals_doctor.php" style="text-decoration:none;">&larr; Back to all referrals</a>
</div>

<?php if (!empty($viewRef['result_file'])): ?>
<div class="no-print" style="background:var(--teal-light);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;max-width:384px;margin-left:auto;margin-right:auto;">
  <div class="card-title mb-12" style="margin-bottom:8px;">Patient-Uploaded Result</div>
  <a href="<?= e($viewRef['result_file']) ?>" target="_blank" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--teal);font-weight:600;font-size:13px;">
    📄 View Uploaded Result File ↗
  </a>
  <?php if (!empty($viewRef['result_notes'])): ?>
    <div class="text-sm" style="margin-top:6px;color:var(--text2);">Patient's note: <?= e($viewRef['result_notes']) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div id="lab-print-wrap">
  <div id="lab-print-area">
    <div class="labdoc">
      <div class="labdoc-topbar"></div>
      <div id="lab-print-outer">
        <div class="labdoc-head">
          <div class="labdoc-brand">
            <svg class="labdoc-mark" viewBox="0 0 64 64" fill="none">
              <defs><linearGradient id="labg1" x1="0" y1="0" x2="64" y2="64"><stop offset="0" stop-color="#0F6E56"/><stop offset="1" stop-color="#185FA5"/></linearGradient></defs>
              <circle cx="21" cy="14" r="6.5" fill="url(#labg1)"/><circle cx="43" cy="14" r="6.5" fill="url(#labg1)"/>
              <path d="M32 52C32 52 10 39.5 10 25.5C10 18.6 15.4 14 21.5 14C26 14 29.6 16.6 32 20.5C34.4 16.6 38 14 42.5 14C48.6 14 54 18.6 54 25.5C54 39.5 32 52 32 52Z" fill="url(#labg1)"/>
              <path d="M32 24V38M25 31H39" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <div>
              <div class="labdoc-clinic-name">WeDoCare Clinic</div>
              <div class="labdoc-clinic-sub">
                <?= e($docName) ?><br>
                <?= e($docSpec) ?><?= $docLic ? ' &middot; Lic. '.e($docLic) : '' ?>
              </div>
            </div>
          </div>
          <div class="labdoc-refbox">
            <div class="labdoc-refno">LR-<?= str_pad($viewRef['referral_id'], 5, '0', STR_PAD_LEFT) ?></div>
            <div class="labdoc-refdate"><?= fmt_date($viewRef['date_issued']) ?></div>
          </div>
        </div>

        <div class="labdoc-title">Laboratory Request Form</div>

        <div class="labdoc-patient">
          <div class="labdoc-patient-name"><?= e($fullName) ?></div>
          <div class="labdoc-patient-meta">
            <?= calc_age($viewRef['dob']) ?> yrs old &middot; <?= e($viewRef['gender']) ?><br>
            <?= e($viewRef['address']) ?>
          </div>
        </div>

        <div class="labdoc-section-label">Tests Requested</div>
        <div class="labdoc-tests">
          <?php if ($viewTests): foreach ($viewTests as $t): ?>
            <div class="labdoc-test">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span><?= e($t) ?></span>
            </div>
          <?php endforeach; else: ?>
            <span class="text-sm text-muted" style="font-size:7px;">No tests listed.</span>
          <?php endif; ?>
        </div>

        <div class="labdoc-foot">
          <div class="labdoc-sigline"></div>
          <div class="labdoc-signame"><?= e($docName) ?></div>
          <div class="labdoc-sigrole">Requesting Physician</div>
          <div class="labdoc-disclaimer">
            <?= e($docAddr) ?><br>
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
    Print Referral (4R)
  </button>
  <div class="text-sm text-muted" style="margin-top:8px;">In the print dialog, set Paper Size to <strong>4R (4×6 in)</strong> and turn off "Headers and footers" for a clean result.</div>
</div>
<?php endif; ?>

<?php if (!$viewRef): ?>
<div class="lab-card mb-20" id="lab-issue-form-card">
  <div class="lab-header">
    <div class="lab-header-title">Laboratory Referral / Request Form</div>
    <div class="lab-header-sub"><?= e($docName) ?> &middot; <?= e($docSpec) ?><?= $docLic ? ' &middot; Lic. No. '.e($docLic) : '' ?> &middot; <?= e($docAddr) ?></div>
  </div>
  <div class="lab-body">
    <form method="post" action="lab_referrals_doctor.php">
      <input type="hidden" name="action" value="issue">
      <div class="form-grid mb-20">
        <div class="form-group">
          <label>Patient *</label>
          <select class="form-control" name="patient_id" required onchange="labFillPatient()" id="lab-patient-select">
            <option value="">Select patient</option>
            <?php foreach ($patients as $p): ?>
              <option value="<?= $p['patient_id'] ?>"
                data-agesex="<?= calc_age($p['dob']) ?> / <?= e($p['gender']) ?>"
                data-address="<?= e($p['address']) ?>">
                <?= e($p['last_name'].', '.$p['first_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Age / Sex</label>
          <input class="form-control" id="lab-agesex" readonly placeholder="Auto-filled">
        </div>
        <div class="form-group">
          <label>Date</label>
          <input class="form-control" type="date" name="lab_date" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Clinical Impression / Diagnosis</label>
          <input class="form-control" name="diagnosis" placeholder="Diagnosis or clinical impression">
        </div>
        <div class="form-group full">
          <label>Address</label>
          <input class="form-control" id="lab-address" readonly placeholder="Auto-filled">
        </div>
      </div>

      <div class="card-title mb-12">Select Laboratory Tests Requested</div>
      <div class="lab-checks-grid mb-20">
        <?php foreach ($LAB_TESTS as $i => $t): ?>
          <label class="lab-check">
            <input type="checkbox" name="tests[]" value="<?= e($t) ?>">
            <span class="lab-check-label"><?= e($t) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="form-group mb-20">
        <label>Other Tests / Additional Instructions</label>
        <textarea class="form-control" name="remarks" placeholder="Other tests or special instructions for the laboratory..."></textarea>
      </div>

      <div class="rx-sig-area">
        <div>
          <div class="rx-sig-line"></div>
          <div class="rx-sig-label"><?= e($docName) ?></div>
          <div style="font-size:11px;color:var(--text3);text-align:center;margin-top:2px">Requesting Physician</div>
        </div>
        <div class="rx-actions">
          <button type="submit" class="btn-primary">
            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/></svg>
            Issue Referral
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><div class="card-title">Recently Issued Referrals</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Patient</th><th>Date Issued</th><th>Tests Requested</th><th>Result</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if ($recent): foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['first_name'].' '.$r['last_name']) ?></td>
          <td><?= fmt_date($r['date_issued']) ?></td>
          <td><?= (int) $r['test_count'] ?> test(s)</td>
          <td>
            <?php if (!empty($r['result_file'])): ?>
              <a href="<?= e($r['result_file']) ?>" target="_blank" style="color:var(--teal);font-weight:600;text-decoration:none;font-size:12px;">📄 View Result</a>
            <?php else: ?>
              <span class="text-sm text-muted">Not yet uploaded</span>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= badge_class($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          <td><a class="mini-btn" href="lab_referrals_doctor.php?view=<?= $r['referral_id'] ?>" style="text-decoration:none;">View</a></td>
        </tr>
        <?php endforeach; else: ?>
          <?= empty_row(6, 'No lab referrals issued yet') ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
  function labFillPatient() {
    const sel = document.getElementById('lab-patient-select');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('lab-agesex').value = opt.dataset.agesex || '';
    document.getElementById('lab-address').value = opt.dataset.address || '';
  }
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
