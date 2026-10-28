<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('doctor');

$error = '';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS doctor_schedule (
        schedule_id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        weekday TINYINT NOT NULL,
        is_available TINYINT(1) NOT NULL DEFAULT 0,
        start_time TIME NULL,
        end_time TIME NULL,
        appointment_mode ENUM('Both','Online','Walk-in') NOT NULL DEFAULT 'Both',
        notes VARCHAR(180) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_doctor_weekday (doctor_id, weekday)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { /* The schedule card will show a setup error if DB permissions are restricted. */ }
$uploadDir = __DIR__ . '/uploads/profile/';
$qrDir     = __DIR__ . '/uploads/qr/';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
if (!is_dir($qrDir)) { @mkdir($qrDir, 0755, true); }

if (post_too_big()) {
    set_flash(upload_too_big_message());
    header('Location: ' . basename($_SERVER['PHP_SELF'])); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_schedule') {
        $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        try {
            $pdo->beginTransaction();
            $saveDay = $pdo->prepare("INSERT INTO doctor_schedule
                (doctor_id, weekday, is_available, start_time, end_time, appointment_mode, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE is_available=VALUES(is_available), start_time=VALUES(start_time),
                end_time=VALUES(end_time), appointment_mode=VALUES(appointment_mode), notes=VALUES(notes)");
            foreach ($dayNames as $dayNo => $dayLabel) {
                $isOpen = !empty($_POST['available'][$dayNo]) ? 1 : 0;
                $start = trim((isset($_POST['start_time'][$dayNo]) ? $_POST['start_time'][$dayNo] : ''));
                $end = trim((isset($_POST['end_time'][$dayNo]) ? $_POST['end_time'][$dayNo] : ''));
                $mode = (isset($_POST['appointment_mode'][$dayNo]) ? $_POST['appointment_mode'][$dayNo] : 'Both');
                $notes = trim((isset($_POST['schedule_notes'][$dayNo]) ? $_POST['schedule_notes'][$dayNo] : ''));
                if (!in_array($mode, ['Both','Online','Walk-in'], true)) { $mode = 'Both'; }
                if ($isOpen && (!$start || !$end || $start >= $end)) {
                    throw new RuntimeException($dayLabel . ': choose a valid start and end time.');
                }
                $saveDay->execute([
                    (int)$_SESSION['user_id'], $dayNo, $isOpen,
                    $isOpen ? $start : null, $isOpen ? $end : null, $mode,
                    $notes !== '' ? mb_substr($notes, 0, 180) : null
                ]);
            }
            $pdo->commit();
            set_flash('Weekly consultation schedule updated and published on the homepage.');
            header('Location: settings_doctor.php#doctor-schedule'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the schedule. Please confirm the doctor_schedule table exists.';
        }
    }

    if ($action === 'update_profile') {
        $name     = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
        $email    = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $license  = trim(isset($_POST['license_no']) ? $_POST['license_no'] : '');
        $spec     = trim(isset($_POST['specialization']) ? $_POST['specialization'] : '');
        $address  = trim(isset($_POST['clinic_address']) ? $_POST['clinic_address'] : '');
        $contact  = trim(isset($_POST['contact_no']) ? $_POST['contact_no'] : '');
        $bio      = trim(isset($_POST['bio']) ? $_POST['bio'] : '');

        if ($name && (!$email || filter_var($email, FILTER_VALIDATE_EMAIL))) {
            if ($email === '') { $email = isset($doc['email']) ? $doc['email'] : ''; }
            try {
                $pdo->prepare('UPDATE users SET full_name=?, email=?, license_no=?, specialization=?, clinic_address=?, contact_no=?, bio=? WHERE user_id=?')
                    ->execute([$name, $email, $license ?: null, $spec ?: null, $address ?: null, $contact ?: null, $bio ?: null, $_SESSION['user_id']]);
            } catch (PDOException $e) {
                $pdo->prepare('UPDATE users SET full_name=?, email=?, license_no=?, specialization=?, clinic_address=?, contact_no=? WHERE user_id=?')
                    ->execute([$name, $email, $license ?: null, $spec ?: null, $address ?: null, $contact ?: null, $_SESSION['user_id']]);
            }
            $_SESSION['full_name'] = $name;
            $_SESSION['email'] = $email;
            set_flash('Profile updated successfully.');
            header('Location: settings_doctor.php'); exit;
        } else {
            $error = 'Please provide a valid name and email address.';
        }
    }

    if ($action === 'update_photo' && !empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = mime_content_type($_FILES['photo']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $error = 'Please upload a JPG, PNG, or WEBP image only.';
        } elseif ($_FILES['photo']['size'] > 3 * 1024 * 1024) {
            $error = 'Image is too large (max 3MB).';
        } else {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = 'doctor_' . $_SESSION['user_id'] . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename);
            $pdo->prepare('UPDATE users SET photo=? WHERE user_id=?')->execute(['uploads/profile/' . $filename, $_SESSION['user_id']]);
            set_flash('Profile photo updated.');
            header('Location: settings_doctor.php'); exit;
        }
    }

    if ($action === 'update_payment_details') {
        $gcashNum = trim(isset($_POST['gcash_number']) ? $_POST['gcash_number'] : '');
        $mayaNum  = trim(isset($_POST['maya_number']) ? $_POST['maya_number'] : '');

        $updates = ['gcash_number' => $gcashNum ?: null, 'maya_number' => $mayaNum ?: null];

        foreach (['gcash_qr' => 'gcash', 'maya_qr' => 'maya'] as $field => $prefix) {
            if (!empty($_FILES[$field]['name'])) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $mime = mime_content_type($_FILES[$field]['tmp_name']);
                if (in_array($mime, $allowed) && $_FILES[$field]['size'] <= 3 * 1024 * 1024) {
                    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
                    $filename = $prefix . '_qr_' . $_SESSION['user_id'] . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                    move_uploaded_file($_FILES[$field]['tmp_name'], $qrDir . $filename);
                    $updates[$field] = 'uploads/qr/' . $filename;
                } else {
                    $error = 'QR code images must be JPG/PNG/WEBP, max 3MB.';
                }
            }
        }

        if (!$error) {
            try {
                $sets = []; $vals = [];
                foreach ($updates as $col => $val) { $sets[] = "$col = ?"; $vals[] = $val; }
                $vals[] = $_SESSION['user_id'];
                $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE user_id = ?')->execute($vals);
                set_flash('Payment details updated.');
                header('Location: settings_doctor.php'); exit;
            } catch (PDOException $e) {
                $error = 'Please ask the clinic to run migrate_payment_qr.php first (one-time setup).';
            }
        }
    }

    if ($action === 'update_fees') {
        $cf = (float) (isset($_POST['consultation_fee']) ? $_POST['consultation_fee'] : 0);
        $mf = (float) (isset($_POST['medcert_fee']) ? $_POST['medcert_fee'] : 0);
        if ($cf <= 0 || $mf < 0) {
            $error = 'Consultation fee must be greater than zero.';
        } else {
            try {
                $pdo->prepare('UPDATE users SET consultation_fee = ?, medcert_fee = ? WHERE user_id = ?')
                    ->execute([$cf, $mf ?: null, $_SESSION['user_id']]);
                set_flash('Consultation fees updated.');
                header('Location: settings_doctor.php'); exit;
            } catch (PDOException $e) {
                $error = 'Please run migrate_booking.php first (one-time setup).';
            }
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
            header('Location: settings_doctor.php'); exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$doc = $stmt->fetch();
$doctorSchedule = [];
try {
    $sch = $pdo->prepare('SELECT weekday, is_available, start_time, end_time, appointment_mode, notes FROM doctor_schedule WHERE doctor_id = ?');
    $sch->execute([(int)$_SESSION['user_id']]);
    foreach ($sch->fetchAll() as $r) { $doctorSchedule[(int)$r['weekday']] = $r; }
} catch (PDOException $e) { $doctorSchedule = []; }
$weekdayLabels = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

$pageTitle  = 'Settings';
$pageSub    = 'Account, clinic profile, and payment settings';
$activePage = 'settings';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if ($error): ?>
  <div class="mb-16" style="background:var(--red-light);color:var(--red);padding:10px 14px;border-radius:8px;font-size:13px;"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid-2">
  <div>
    <div class="card mb-16">
      <div class="card-head"><div class="card-title">Profile Photo</div></div>
      <form method="post" action="settings_doctor.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_photo">
        <div style="display:flex;align-items:center;gap:18px;">
          <?php if (!empty($doc['photo'])): ?>
            <img src="<?= e($doc['photo']) ?>" alt="Doctor photo" style="width:84px;height:84px;border-radius:50%;object-fit:cover;border:2px solid var(--border);">
          <?php else: ?>
            <?= avatar_html($doc['full_name'], 84) ?>
          <?php endif; ?>
          <div style="flex:1;">
            <input type="file" name="photo" accept="image/*" style="font-size:12px;margin-bottom:8px;">
            <div class="text-sm text-muted" style="margin-bottom:10px;">JPG, PNG, or WEBP. Max 3MB. Used for your account only (sidebar and settings); it does not change the public homepage.</div>
            <button type="submit" class="btn-secondary">Upload Photo</button>
          </div>
        </div>
      </form>
    </div>

    <div class="card mb-16">
      <div class="card-head"><div class="card-title">Profile &amp; Credentials</div></div>
      <form method="post" action="settings_doctor.php">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-group mb-12">
          <label>Full Name</label>
          <input class="form-control" name="full_name" value="<?= e($doc['full_name']) ?>" required>
        </div>
        <div class="form-group mb-12">
          <label>Email Address</label>
          <input class="form-control" type="email" name="email" value="<?= e($doc['email']) ?>" placeholder="Email address (optional)">
        </div>
        <div class="form-group mb-12">
          <label>PRC License Number</label>
          <input class="form-control" name="license_no" value="<?= e($doc['license_no']) ?>" placeholder="e.g. 0123456">
        </div>
        <div class="form-group mb-12">
          <label>Specialization</label>
          <input class="form-control" name="specialization" value="<?= e($doc['specialization']) ?>" placeholder="e.g. General Practitioner">
        </div>
        <div class="form-group mb-12">
          <label>Clinic Address</label>
          <input class="form-control" name="clinic_address" value="<?= e($doc['clinic_address']) ?>" placeholder="Clinic address, City, Province">
        </div>
        <div class="form-group mb-12">
          <label>Contact Number</label>
          <input class="form-control" name="contact_no" value="<?= e($doc['contact_no']) ?>" placeholder="+63 9XX XXX XXXX">
        </div>
        <div class="form-group">
          <label>About / Bio (shown on the public homepage)</label>
          <textarea class="form-control" name="bio" placeholder="A short description of your practice and experience..."><?= e(isset($doc['bio']) ? $doc['bio'] : '') ?></textarea>
        </div>
        <button type="submit" class="btn-primary mt-16">Save Changes</button>
      </form>
    </div>
  </div>

  <div>
    <div class="card mb-16">
      <div class="card-head"><div class="card-title">Change Password</div></div>
      <form method="post" action="settings_doctor.php">
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

    <div class="card mb-16">
      <div class="card-head"><div class="card-title">Consultation Fees</div></div>
      <div class="text-sm text-muted" style="padding:0 0 14px;">Patients must pay these fixed amounts when booking. They cannot change the amount themselves.</div>
      <form method="post" action="settings_doctor.php">
        <input type="hidden" name="action" value="update_fees">
        <div class="form-group mb-12">
          <label>Consultation Fee (₱)</label>
          <input class="form-control" type="number" step="0.01" min="0.01" name="consultation_fee" value="<?= e(isset($doc['consultation_fee']) ? $doc['consultation_fee'] : '') ?>" required>
        </div>
        <div class="form-group mb-12">
          <label>Medical Certificate Fee (₱)</label>
          <input class="form-control" type="number" step="0.01" min="0" name="medcert_fee" value="<?= e(isset($doc['medcert_fee']) ? $doc['medcert_fee'] : '') ?>" placeholder="Leave blank if free">
        </div>
        <button type="submit" class="btn-primary">Save Fees</button>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-title">GCash / Maya Payment Details</div></div>
      <div class="text-sm text-muted" style="padding:0 0 14px;">These appear to patients when they pay via GCash or Maya, so they know exactly where to send their payment.</div>
      <form method="post" action="settings_doctor.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_payment_details">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <div class="form-group mb-12">
              <label>GCash Number</label>
              <input class="form-control" name="gcash_number" value="<?= e(isset($doc['gcash_number']) ? $doc['gcash_number'] : '') ?>" placeholder="09XX XXX XXXX">
            </div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px;">GCash QR Code</label>
            <?php if (!empty($doc['gcash_qr'])): ?>
              <img src="<?= e($doc['gcash_qr']) ?>" style="width:120px;height:120px;object-fit:contain;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;background:#fff;">
            <?php endif; ?>
            <input type="file" name="gcash_qr" accept="image/*" style="font-size:12px;display:block;">
          </div>
          <div>
            <div class="form-group mb-12">
              <label>Maya Number</label>
              <input class="form-control" name="maya_number" value="<?= e(isset($doc['maya_number']) ? $doc['maya_number'] : '') ?>" placeholder="09XX XXX XXXX">
            </div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px;">Maya QR Code</label>
            <?php if (!empty($doc['maya_qr'])): ?>
              <img src="<?= e($doc['maya_qr']) ?>" style="width:120px;height:120px;object-fit:contain;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;background:#fff;">
            <?php endif; ?>
            <input type="file" name="maya_qr" accept="image/*" style="font-size:12px;display:block;">
          </div>
        </div>

        <button type="submit" class="btn-primary mt-16">Save Payment Details</button>
      </form>
    </div>
  </div>
</div>

<div class="card mb-16" id="doctor-schedule" style="margin-top:18px;">
  <div class="card-head"><div class="card-title">Weekly Consultation Schedule</div></div>
  <p class="text-sm text-muted" style="margin:0 0 16px;">Set the regular hours patients can see on the public WeDoCare homepage. Leave a day unchecked when the clinic is closed. These are general hours; each appointment still requires confirmation.</p>
  <form method="post" action="settings_doctor.php#doctor-schedule">
    <input type="hidden" name="action" value="save_schedule">
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;min-width:720px;">
        <thead><tr style="text-align:left;border-bottom:1px solid var(--border);">
          <th style="padding:10px 8px;">Day</th><th style="padding:10px 8px;">Available</th><th style="padding:10px 8px;">From</th><th style="padding:10px 8px;">To</th><th style="padding:10px 8px;">Consultation type</th><th style="padding:10px 8px;">Note (optional)</th>
        </tr></thead>
        <tbody>
        <?php foreach ($weekdayLabels as $dayNo => $dayLabel): $sr = isset($doctorSchedule[$dayNo]) ? $doctorSchedule[$dayNo] : []; ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:10px 8px;font-weight:600;"><?= e($dayLabel) ?></td>
            <td style="padding:10px 8px;"><input type="checkbox" name="available[<?= $dayNo ?>]" value="1" <?= !empty($sr['is_available']) ? 'checked' : '' ?> aria-label="<?= e($dayLabel) ?> available"></td>
            <td style="padding:10px 8px;"><input class="form-control" style="min-width:125px;" type="time" name="start_time[<?= $dayNo ?>]" value="<?= e(isset($sr['start_time']) ? substr($sr['start_time'],0,5) : '') ?>"></td>
            <td style="padding:10px 8px;"><input class="form-control" style="min-width:125px;" type="time" name="end_time[<?= $dayNo ?>]" value="<?= e(isset($sr['end_time']) ? substr($sr['end_time'],0,5) : '') ?>"></td>
            <td style="padding:10px 8px;"><select class="form-control" name="appointment_mode[<?= $dayNo ?>]">
              <?php foreach (['Both'=>'Online + Walk-in','Online'=>'Online only','Walk-in'=>'Walk-in only'] as $modeKey=>$modeLabel): ?>
                <option value="<?= e($modeKey) ?>" <?= (isset($sr['appointment_mode']) ? $sr['appointment_mode'] : 'Both') === $modeKey ? 'selected' : '' ?>><?= e($modeLabel) ?></option>
              <?php endforeach; ?>
            </select></td>
            <td style="padding:10px 8px;"><input class="form-control" style="min-width:140px;" name="schedule_notes[<?= $dayNo ?>]" maxlength="180" value="<?= e(isset($sr['notes']) ? $sr['notes'] : '') ?>" placeholder="e.g. by appointment"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button type="submit" class="btn-primary mt-16">Save &amp; Publish Schedule</button>
  </form>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
