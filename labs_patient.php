<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('patient');

$patientId = $_SESSION['patient_id'];
$uploadDir = __DIR__ . '/uploads/lab_results/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

if (post_too_big()) {
    set_flash(upload_too_big_message());
    header('Location: ' . basename($_SERVER['PHP_SELF'])); exit;
}

// ---------- Patient uploads their lab result for a referral ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'upload_result') {
    $refId = (int) (isset($_POST['referral_id']) ? $_POST['referral_id'] : 0);
    $notes = trim(isset($_POST['result_notes']) ? $_POST['result_notes'] : '');

    $check = $pdo->prepare('SELECT referral_id FROM lab_referrals WHERE referral_id = ? AND patient_id = ?');
    $check->execute([$refId, $patientId]);

    if (!$check->fetch()) {
        set_flash('Lab referral not found.');
    } elseif (empty($_FILES['result']['name'])) {
        set_flash('Please choose your lab result file to upload.');
    } else {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $mime = mime_content_type($_FILES['result']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            set_flash('Please upload a JPG, PNG, or PDF file only.');
        } elseif ($_FILES['result']['size'] > 8 * 1024 * 1024) {
            set_flash('File is too large (max 8MB).');
        } else {
            try {
                $ext = pathinfo($_FILES['result']['name'], PATHINFO_EXTENSION);
                $filename = 'result_' . $refId . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                move_uploaded_file($_FILES['result']['tmp_name'], $uploadDir . $filename);

                $pdo->prepare("UPDATE lab_referrals SET result_file=?, result_notes=?, status='completed' WHERE referral_id=?")
                    ->execute(['uploads/lab_results/' . $filename, $notes ?: null, $refId]);
                set_flash('Lab result uploaded successfully. Dr. Deguito will review it.');
            } catch (PDOException $e) {
                set_flash('Please ask the clinic to run migrate_lab_results.php first (one-time setup).');
            }
        }
    }
    header('Location: labs_patient.php'); exit;
}

$refs = $pdo->prepare('SELECT * FROM lab_referrals WHERE patient_id = ? ORDER BY date_issued DESC');
$refs->execute([$patientId]);
$refs = $refs->fetchAll();

$testsStmt = $pdo->prepare('SELECT test_name FROM lab_referral_tests WHERE referral_id = ?');

$pageTitle  = 'Lab Results & Referrals';
$pageSub    = 'Laboratory request forms and result uploads';
$activePage = 'labs';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if ($refs): foreach ($refs as $r): $testsStmt->execute([$r['referral_id']]); $tests = $testsStmt->fetchAll(PDO::FETCH_COLUMN); ?>
<div class="card mb-16">
  <div class="card-head">
    <div class="card-title">Lab Referral — <?= fmt_date($r['date_issued']) ?></div>
    <span class="badge <?= badge_class($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
  </div>
  <div style="padding:4px 4px 12px;">
    <?php if ($tests): foreach ($tests as $t): ?>
      <span class="badge b-walkin" style="margin:4px 4px 0 0;display:inline-block;"><?= e($t) ?></span>
    <?php endforeach; else: ?>
      <div class="text-sm text-muted">No specific tests listed.</div>
    <?php endif; ?>
  </div>

  <div style="border-top:1px solid var(--border);padding:14px 4px 4px;">
    <?php if (!empty($r['result_file'])): ?>
      <div style="display:flex;align-items:center;gap:10px;">
        <a href="<?= e($r['result_file']) ?>" target="_blank" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
          <div style="width:34px;height:34px;border-radius:8px;background:var(--teal-light);display:flex;align-items:center;justify-content:center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          <span class="text-sm" style="color:var(--navy-light);font-weight:600;">Result uploaded — View file</span>
        </a>
      </div>
      <?php if (!empty($r['result_notes'])): ?>
        <div class="text-sm text-muted" style="margin-top:6px;">Note: <?= e($r['result_notes']) ?></div>
      <?php endif; ?>
    <?php else: ?>
      <button type="button" class="btn-secondary" onclick="openResultModal(<?= $r['referral_id'] ?>)">
        Upload Lab Result
      </button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; else: ?>
  <div class="card" style="text-align:center;padding:30px 22px;">
    <div style="font-size:38px;margin-bottom:10px;" aria-hidden="true">🧪</div>
    <h2 style="font-size:20px;margin-bottom:8px;">No lab referrals yet</h2>
    <p style="font-size:15px;color:var(--text2);max-width:520px;margin:0 auto 14px;">
      Laboratory requests from Dr. Deguito will appear here. If a request is listed, you can review it and upload your result when it is ready.
    </p>
    <a class="btn-secondary" href="dashboard_patient.php" style="text-decoration:none;">Back to My Dashboard</a>
  </div>
<?php endif; ?>

<!-- ============ UPLOAD LAB RESULT MODAL ============ -->
<div class="modal-overlay" id="result-modal-overlay">
  <div class="modal" style="width:480px;">
    <div class="modal-head">
      <div class="modal-title">Upload Lab Result</div>
      <div class="modal-close" onclick="closeResultModal()">
        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <form method="post" action="labs_patient.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_result">
      <input type="hidden" name="referral_id" id="rr-referral-id">
      <div class="modal-body">
        <div style="border:2px dashed var(--border2);border-radius:var(--radius-lg);padding:24px 14px;text-align:center;
             background:var(--bg);cursor:pointer;margin-bottom:14px;" onclick="document.getElementById('rr-file-input').click()">
          <div id="rr-preview-wrap">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.6" style="margin:0 auto 8px;">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
            <div style="font-size:12px;color:var(--text2);font-weight:600;">Upload your lab result</div>
            <div style="font-size:11px;color:var(--text3);margin-top:4px;">JPG, PNG, or PDF — max 8MB</div>
          </div>
          <img id="rr-preview-img" style="display:none;max-width:100%;max-height:130px;border-radius:8px;">
        </div>
        <input type="file" name="result" id="rr-file-input" accept="image/*,application/pdf" required style="display:none;"
               onchange="previewLabResult(this)">
        <div class="form-group">
          <label>Note (optional)</label>
          <textarea class="form-control" name="result_notes" placeholder="Anything you'd like to mention to the doctor about this result..."></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeResultModal()">Close</button>
        <button type="submit" class="btn-primary">Submit Result</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openResultModal(refId) {
    document.getElementById('rr-referral-id').value = refId;
    document.getElementById('result-modal-overlay').classList.add('open');
  }
  function closeResultModal() {
    document.getElementById('result-modal-overlay').classList.remove('open');
    document.getElementById('rr-file-input').value = '';
    document.getElementById('rr-preview-img').style.display = 'none';
    document.getElementById('rr-preview-wrap').style.display = 'block';
  }
  function previewLabResult(input) {
    if (!input.files || !input.files[0]) return;
    if (!input.files[0].type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('rr-preview-img').src = e.target.result;
      document.getElementById('rr-preview-img').style.display = 'block';
      document.getElementById('rr-preview-wrap').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
