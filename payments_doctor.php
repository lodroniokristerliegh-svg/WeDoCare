<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $patientId = (int) (isset($_POST['patient_id']) ? $_POST['patient_id'] : 0);
        $service   = trim(isset($_POST['service']) ? $_POST['service'] : '');
        $amount    = (float) (isset($_POST['amount']) ? $_POST['amount'] : 0);
        $method    = in_array(isset($_POST['method']) ? $_POST['method'] : '', ['Cash','GCash','Maya']) ? $_POST['method'] : 'Cash';
        $status    = in_array(isset($_POST['status']) ? $_POST['status'] : '', ['pending','verified']) ? $_POST['status'] : 'pending';

        if ($patientId && $service && $amount > 0) {
            $ins = $pdo->prepare('INSERT INTO payments (patient_id, service, amount, method, status) VALUES (?,?,?,?,?)');
            $ins->execute([$patientId, $service, $amount, $method, $status]);
            set_flash('Payment record added.');
        } else {
            set_flash('Please complete all required fields.');
        }
        header('Location: payments_doctor.php'); exit;
    }

    if ($action === 'verify') {
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $pdo->prepare("UPDATE payments SET status='verified', verified_by=? WHERE payment_id=?")->execute([$_SESSION['user_id'], $id]);
        // Booking payments unlock the appointment (and the Google Meet link).
        $pdo->prepare("UPDATE appointments SET status='confirmed'
                       WHERE status='pending' AND appointment_id = (SELECT appointment_id FROM payments WHERE payment_id=?)")
            ->execute([$id]);
        set_flash('Payment verified.');
        header('Location: payments_doctor.php'); exit;
    }

    if ($action === 'reject') {
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $pdo->prepare("UPDATE payments SET status='rejected', verified_by=? WHERE payment_id=?")->execute([$_SESSION['user_id'], $id]);
        set_flash('Payment rejected.');
        header('Location: payments_doctor.php'); exit;
    }
}

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT pm.*, p.first_name, p.last_name FROM payments pm
        JOIN patients p ON p.patient_id = pm.patient_id WHERE 1=1";
$params = [];
if ($q !== '') { $sql .= " AND CONCAT(p.first_name,' ',p.last_name) LIKE ?"; $params[] = "%$q%"; }
if (in_array($filter, ['verified','pending','rejected'])) { $sql .= " AND pm.status = ?"; $params[] = $filter; }
if (in_array($filter, ['GCash','Maya','Cash'])) { $sql .= " AND pm.method = ?"; $params[] = $filter; }
$sql .= " ORDER BY pm.paid_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalCollected = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn();
$pendingAmount  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='pending'")->fetchColumn();
$digitalTotal   = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE method IN ('GCash','Maya') AND MONTH(paid_at)=MONTH(CURDATE())")->fetchColumn();

$patients = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name")->fetchAll();

$pageTitle  = 'Payments';
$pageSub    = 'Consultation fees and payment verification';
$activePage = 'payments';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="pay-summary mb-16">
  <div class="pay-card">
    <div class="pay-icon" style="background:var(--green-light)">
      <svg viewBox="0 0 24 24" style="color:var(--green)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <div class="pay-label">Total Collected (This Month)</div>
    <div class="pay-value">₱ <?= number_format($totalCollected, 2) ?></div>
  </div>
  <div class="pay-card">
    <div class="pay-icon" style="background:var(--amber-light)">
      <svg viewBox="0 0 24 24" style="color:var(--amber)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div class="pay-label">Pending Verification</div>
    <div class="pay-value">₱ <?= number_format($pendingAmount, 2) ?></div>
  </div>
  <div class="pay-card">
    <div class="pay-icon" style="background:var(--blue-light)">
      <svg viewBox="0 0 24 24" style="color:var(--navy-light)"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    </div>
    <div class="pay-label">GCash &amp; Maya (This Month)</div>
    <div class="pay-value">₱ <?= number_format($digitalTotal, 2) ?></div>
  </div>
</div>

<?php if (isset($_GET['new'])): ?>
<div class="modal-overlay open" id="payment-modal" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title" onclick="if(event.target===this) location.href='payments_doctor.php';">
  <div class="modal modal-form modal-payment">
  <div class="modal-head"><div><div class="modal-title" id="payment-modal-title">Record Payment</div><div class="text-sm text-muted">Enter payment information for a patient</div></div><a class="modal-close" href="payments_doctor.php" aria-label="Close">&times;</a></div>
  <div class="modal-body">
  <form method="post" action="payments_doctor.php">
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
      <div class="form-group"><label>Service *</label><input class="form-control" name="service" placeholder="Consultation Fee" required></div>
      <div class="form-group"><label>Amount (₱) *</label><input class="form-control" type="number" step="0.01" name="amount" required></div>
      <div class="form-group">
        <label>Payment Method</label>
        <select class="form-control" name="method">
          <option>Cash</option><option>GCash</option><option>Maya</option>
        </select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select class="form-control" name="status">
          <option value="pending">Pending</option>
          <option value="verified">Verified</option>
        </select>
      </div>
    </div>
    <div class="flex gap-8 mt-16">
      <button type="submit" class="btn-primary">Save Payment</button>
      <a class="btn-cancel" href="payments_doctor.php" style="text-decoration:none;">Cancel</a>
    </div>
  </form>
  </div>
  </div>
</div>
<?php endif; ?>

<div class="toolbar">
  <form method="get" action="payments_doctor.php" style="display:flex;gap:10px;flex:1;">
    <div class="search-box">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" placeholder="Search patient name..." value="<?= e($q) ?>">
    </div>
    <select class="form-control" name="filter" style="width:160px" onchange="this.form.submit()">
      <option value="all"      <?= $filter==='all'?'selected':'' ?>>All Payments</option>
      <option value="verified" <?= $filter==='verified'?'selected':'' ?>>Verified</option>
      <option value="pending"  <?= $filter==='pending'?'selected':'' ?>>Pending</option>
      <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
      <option value="GCash"    <?= $filter==='GCash'?'selected':'' ?>>GCash</option>
      <option value="Maya"     <?= $filter==='Maya'?'selected':'' ?>>Maya</option>
      <option value="Cash"     <?= $filter==='Cash'?'selected':'' ?>>Cash</option>
    </select>
    <button type="submit" class="btn-secondary">Filter</button>
  </form>
  <a class="btn-primary ml-auto" href="payments_doctor.php?new=1" style="text-decoration:none;">
    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Record Payment
  </a>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Patient</th><th>Date</th><th>Service</th><th>Amount</th><th>Method</th><th>Proof</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if ($payments): foreach ($payments as $pm): $name = $pm['first_name'].' '.$pm['last_name']; ?>
      <tr>
        <td><div class="td-flex"><?= avatar_html($name, 32) ?><div style="font-weight:500"><?= e($name) ?></div></div></td>
        <td><?= fmt_date(substr($pm['paid_at'],0,10)) ?></td>
        <td><?= e($pm['service']) ?></td>
        <td>₱<?= number_format($pm['amount'], 2) ?></td>
        <td><?= e($pm['method']) ?></td>
        <td>
          <?php if (!empty($pm['proof_image'])): ?>
            <button type="button" class="td-flex" style="border:none;background:none;cursor:pointer;padding:0;text-align:left;"
              onclick='openViewProofModal(<?= json_encode([
                "img" => $pm['proof_image'], "name" => $name, "service" => $pm['service'],
                "amount" => number_format($pm['amount'],2), "method" => $pm['method'],
                "ref" => isset($pm['reference_no']) ? $pm['reference_no'] : "", "sender" => isset($pm['sender_name']) ? $pm['sender_name'] : "",
                "datePaid" => !empty($pm['date_paid']) ? fmt_date($pm['date_paid']) : fmt_date(substr($pm['paid_at'],0,10)),
                "status" => $pm['status'], "id" => $pm['payment_id'],
              ]) ?>)'>
              <img src="<?= e($pm['proof_image']) ?>" alt="Proof" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
              <span class="text-sm" style="color:var(--navy-light);font-weight:600;">View Proof</span>
            </button>
          <?php else: ?>
            <span class="text-sm text-muted">—</span>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= badge_class($pm['status']) ?>"><?= ucfirst($pm['status']) ?></span></td>
        <td>
          <?php if ($pm['status'] === 'pending'): ?>
          <div class="flex gap-8">
            <form method="post" action="payments_doctor.php">
              <input type="hidden" name="action" value="verify">
              <input type="hidden" name="id" value="<?= $pm['payment_id'] ?>">
              <button type="submit" class="mini-btn" style="color:var(--green)">✓ Verify</button>
            </form>
            <form method="post" action="payments_doctor.php">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="id" value="<?= $pm['payment_id'] ?>">
              <button type="submit" class="mini-btn" style="color:var(--red)">Reject</button>
            </form>
          </div>
          <?php else: ?>
            <span class="text-sm text-muted">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
        <?= empty_row(8, 'No payment records found') ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ============ VIEW PROOF OF PAYMENT MODAL ============ -->
<div class="modal-overlay" id="view-proof-overlay">
  <div class="modal" style="width:600px;">
    <div class="modal-head" style="background:var(--navy);border-radius:var(--radius-lg) var(--radius-lg) 0 0;border-bottom:none;">
      <div class="modal-title" style="color:#fff;">Proof of Payment</div>
      <div class="modal-close" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;" onclick="closeViewProofModal()">
        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1.1fr;gap:22px;">
        <div>
          <img id="vp-img" src="" alt="Proof of payment" style="width:100%;border-radius:10px;border:1px solid var(--border);object-fit:contain;background:var(--bg);">
          <a id="vp-img-link" href="" target="_blank" class="btn-outline" style="margin-top:10px;width:100%;justify-content:center;text-decoration:none;font-size:12px;">
            Open Full Size ↗
          </a>
        </div>
        <div>
          <div class="rx-field-label">Patient</div>
          <div class="rx-field-input" id="vp-name" style="border:none;background:none;padding:4px 0;font-weight:600;"></div>

          <div class="rx-field-label" style="margin-top:12px;">Service</div>
          <div class="rx-field-input" id="vp-service" style="border:none;background:none;padding:4px 0;"></div>

          <div style="display:flex;gap:16px;margin-top:12px;">
            <div>
              <div class="rx-field-label">Amount</div>
              <div class="rx-field-input" id="vp-amount" style="border:none;background:none;padding:4px 0;font-weight:600;"></div>
            </div>
            <div>
              <div class="rx-field-label">Method</div>
              <div class="rx-field-input" id="vp-method" style="border:none;background:none;padding:4px 0;"></div>
            </div>
          </div>

          <div class="rx-field-label" style="margin-top:12px;">Reference / OR No.</div>
          <div class="rx-field-input" id="vp-ref" style="border:none;background:none;padding:4px 0;"></div>

          <div class="rx-field-label" style="margin-top:12px;">Sender Name</div>
          <div class="rx-field-input" id="vp-sender" style="border:none;background:none;padding:4px 0;"></div>

          <div class="rx-field-label" style="margin-top:12px;">Date Paid</div>
          <div class="rx-field-input" id="vp-date" style="border:none;background:none;padding:4px 0;"></div>

          <div style="margin-top:16px;" id="vp-status-badge"></div>
        </div>
      </div>
    </div>
    <div class="modal-foot" id="vp-actions">
      <button type="button" class="btn-cancel" onclick="closeViewProofModal()">Close</button>
      <form method="post" action="payments_doctor.php" id="vp-reject-form" style="display:inline;">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" id="vp-reject-id">
        <button type="submit" class="btn-cancel" style="color:var(--red);border-color:var(--red);">Reject</button>
      </form>
      <form method="post" action="payments_doctor.php" id="vp-verify-form" style="display:inline;">
        <input type="hidden" name="action" value="verify">
        <input type="hidden" name="id" id="vp-verify-id">
        <button type="submit" class="btn-primary">✓ Verify Payment</button>
      </form>
    </div>
  </div>
</div>

<script>
  function openViewProofModal(pm) {
    document.getElementById('vp-img').src = pm.img;
    document.getElementById('vp-img-link').href = pm.img;
    document.getElementById('vp-name').textContent = pm.name;
    document.getElementById('vp-service').textContent = pm.service;
    document.getElementById('vp-amount').textContent = '₱' + pm.amount;
    document.getElementById('vp-method').textContent = pm.method;
    document.getElementById('vp-ref').textContent = pm.ref || '—';
    document.getElementById('vp-sender').textContent = pm.sender || '—';
    document.getElementById('vp-date').textContent = pm.datePaid;
    document.getElementById('vp-reject-id').value = pm.id;
    document.getElementById('vp-verify-id').value = pm.id;

    const badgeMap = { pending: 'b-pending', verified: 'b-verified', rejected: 'b-cancelled' };
    document.getElementById('vp-status-badge').innerHTML =
      '<span class="badge ' + (badgeMap[pm.status] || 'b-pending') + '">' + pm.status.charAt(0).toUpperCase() + pm.status.slice(1) + '</span>';

    document.getElementById('vp-actions').style.display = pm.status === 'pending' ? 'flex' : 'none';
    if (pm.status !== 'pending') {
      document.getElementById('vp-actions').style.display = 'flex';
      document.getElementById('vp-reject-form').style.display = 'none';
      document.getElementById('vp-verify-form').style.display = 'none';
    } else {
      document.getElementById('vp-reject-form').style.display = 'inline';
      document.getElementById('vp-verify-form').style.display = 'inline';
    }

    document.getElementById('view-proof-overlay').classList.add('open');
  }
  function closeViewProofModal() {
    document.getElementById('view-proof-overlay').classList.remove('open');
  }
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
