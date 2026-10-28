<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('patient');

$patientId = $_SESSION['patient_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_profile') {
        $contact = trim(isset($_POST['contact']) ? $_POST['contact'] : '');
        $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
        $blood   = trim(isset($_POST['blood']) ? $_POST['blood'] : '');
        $allergies = trim(isset($_POST['allergies']) ? $_POST['allergies'] : '') ?: 'NKDA';

        if ($contact && $address) {
            $pdo->prepare('UPDATE patients SET contact_no=?, address=?, blood_type=?, allergies=? WHERE patient_id=?')
                ->execute([$contact, $address, $blood ?: null, $allergies, $patientId]);
            set_flash('Profile updated successfully.');
            header('Location: profile_patient.php'); exit;
        } else {
            $error = 'Contact number and address are required.';
        }
    }

    if ($action === 'change_password') {
        $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new     = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([$newHash, $_SESSION['user_id']]);
            set_flash('Password changed successfully.');
            header('Location: profile_patient.php'); exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
$stmt->execute([$patientId]);
$p = $stmt->fetch();
if (!$p) {
    header('Location: login.php?accountmissing=1');
    exit;
}

$pageTitle  = 'My Profile';
$pageSub    = 'Your personal and medical information';
$activePage = 'profile';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if ($error): ?>
  <div class="mb-16" style="background:var(--red-light);color:var(--red);padding:10px 14px;border-radius:8px;font-size:13px;"><?= e($error) ?></div>
<?php endif; ?>

<div class="lab-card mb-16" style="max-width:640px;">
  <div class="lab-header" style="background:var(--navy);">
    <div class="lab-header-title"><?= e($p['first_name'].' '.($p['middle_name']?$p['middle_name'].' ':'').$p['last_name']) ?></div>
    <div class="lab-header-sub">Patient ID: WDC-<?= str_pad($p['patient_id'],4,'0',STR_PAD_LEFT) ?></div>
  </div>
  <div class="lab-body">
    <form method="post" action="profile_patient.php">
      <input type="hidden" name="action" value="update_profile">
      <div class="form-grid">
        <div class="form-field"><label>Date of Birth</label><div class="rx-field-input"><?= fmt_date($p['dob']) ?> (<?= calc_age($p['dob']) ?> yrs old)</div></div>
        <div class="form-field"><label>Gender</label><div class="rx-field-input"><?= e($p['gender']) ?></div></div>
        <div class="form-field"><label>Contact Number</label><input class="form-control" name="contact" value="<?= e($p['contact_no']) ?>" required></div>
        <div class="form-field"><label>Email Address</label><div class="rx-field-input"><?= e($p['email']) ?></div></div>
        <div class="form-field span-2"><label>Address</label><input class="form-control" name="address" value="<?= e($p['address']) ?>" required></div>
        <div class="form-field"><label>Blood Type</label><input class="form-control" name="blood" value="<?= e($p['blood_type']) ?>" placeholder="O+, A+, etc."></div>
        <div class="form-field"><label>Known Allergies</label><input class="form-control" name="allergies" value="<?= e($p['allergies']) ?>" placeholder="NKDA if none"></div>
        <div class="form-field span-2"><label>Medical History</label><div class="rx-field-input"><?= nl2br(e($p['history'] ?: 'No notes on file.')) ?></div></div>
      </div>
      <div class="mt-16" style="text-align:right;">
        <button type="submit" class="btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div class="card" style="max-width:640px;">
  <div class="card-head"><div class="card-title">Change Password</div></div>
  <form method="post" action="profile_patient.php">
    <input type="hidden" name="action" value="change_password">
    <div class="form-group mb-12">
      <label>Current Password</label>
      <input class="form-control" type="password" name="current_password" required>
    </div>
    <div class="form-group mb-12">
      <label>New Password</label>
      <input class="form-control" type="password" name="new_password" required placeholder="Min. 8 characters">
    </div>
    <div class="form-group mb-12">
      <label>Confirm New Password</label>
      <input class="form-control" type="password" name="confirm_password" required>
    </div>
    <button type="submit" class="btn-primary">Update Password</button>
  </form>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
