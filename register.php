<?php
require_once __DIR__ . '/config.php';

// Only ever hand off to a page on our own whitelist — never trust the
// query string directly for a redirect target (that's how open-redirect
// bugs happen).
$allowedRedirects = ['book_appointment.php'];
$redirect = (isset($_GET['redirect']) && in_array($_GET['redirect'], $allowedRedirects, true))
    ? $_GET['redirect']
    : (isset($_POST['redirect']) && in_array($_POST['redirect'], $allowedRedirects, true) ? $_POST['redirect'] : '');

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($redirect ?: ($_SESSION['role'] === 'doctor' ? 'dashboard_doctor.php' : 'dashboard_patient.php')));
    exit;
}

$error = '';
$old = isset($_POST) ? $_POST : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lname    = trim(isset($_POST['lname']) ? $_POST['lname'] : '');
    $fname    = trim(isset($_POST['fname']) ? $_POST['fname'] : '');
    $mname    = trim(isset($_POST['mname']) ? $_POST['mname'] : '');
    $dob      = trim(isset($_POST['dob']) ? $_POST['dob'] : '');
    $gender   = trim(isset($_POST['gender']) ? $_POST['gender'] : '');
    $address  = trim(isset($_POST['address']) ? $_POST['address'] : '');
    $contact  = trim(isset($_POST['contact']) ? $_POST['contact'] : '');
    $email    = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? $_POST['password2'] : '';

    if (!$lname || !$fname || !$dob || !$gender || !$address || !$contact || !$email || !$password || !$password2) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email already exists. Please sign in instead.';
        } else {
            try {
                $pdo->beginTransaction();

                $insPatient = $pdo->prepare(
                    'INSERT INTO patients (last_name, first_name, middle_name, dob, gender, contact_no, email, address)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insPatient->execute([$lname, $fname, $mname ?: null, $dob, $gender, $contact, $email, $address]);
                $patientId = $pdo->lastInsertId();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $pdo->prepare(
                    'INSERT INTO users (role, full_name, email, password_hash, patient_id)
                     VALUES ("patient", ?, ?, ?, ?)'
                );
                $insUser->execute(["$fname $lname", $email, $hash, $patientId]);

                $pdo->commit();

                header('Location: login.php?registered=1' . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

try {
    $doc = $pdo->query("SELECT full_name, specialization, license_no, photo FROM users WHERE role='doctor' LIMIT 1")->fetch();
} catch (PDOException $e) {
    $doc = null;
}
$docName  = isset($doc['full_name']) ? $doc['full_name'] : 'Dr. Kendrick Kerk Urag Deguito';
$docSpec  = isset($doc['specialization']) ? $doc['specialization'] : 'General Practitioner';
$docLic   = isset($doc['license_no']) ? $doc['license_no'] : '';
$docPhoto = isset($doc['photo']) ? $doc['photo'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — WeDoCare</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #0B3D6B; --navy-dark: #082D52; --navy-light: #185FA5;
  --teal: #0F6E56; --teal-light: #E1F5EE;
  --bg: #F7FAFC; --white: #FFFFFF; --text: #1A1A2E; --text2: #5F6B7A; --text3: #94A0AF;
  --border: #E3E9F2; --green: #1E9E5A; --green-light: #E8F8EF;
  --red: #C0392B; --red-light: #FCEBEB; --radius: 10px; --radius-lg: 20px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
  min-height: 100vh; display: flex; background: var(--bg); color: var(--text);
}
.serif { font-family: 'Fraunces', Georgia, serif; }

/* ===== LEFT — BRAND PANEL ===== */
.brand-panel {
  flex: 0 0 36%; position: relative; overflow: hidden;
  background: linear-gradient(155deg, var(--navy-dark) 0%, var(--navy) 55%, var(--navy-light) 100%);
  color: #fff; display: flex; flex-direction: column; justify-content: space-between;
  padding: 48px 44px; min-height: 100vh;
}
.brand-panel::before {
  content: ''; position: absolute; width: 400px; height: 400px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.06), transparent 70%); top: -160px; right: -150px;
}
.brand-panel::after {
  content: ''; position: absolute; width: 300px; height: 300px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.05), transparent 70%); bottom: -120px; left: -100px;
}
.bp-top, .bp-mid, .bp-bottom { position: relative; z-index: 1; }
.bp-logo { font-size: 24px; font-weight: 600; letter-spacing: -0.4px; }
.bp-official-logo { display:block; width:70px; height:70px; object-fit:contain; background:#fff; border-radius:12px; padding:5px; margin-bottom:12px; box-sizing:border-box; }
.bp-logo em { font-style: normal; color: #7FC4B0; }
.bp-tagline { font-size: 12px; color: rgba(255,255,255,0.55); margin-top: 4px; }

.bp-mid { margin: auto 0; padding: 40px 0; }
.bp-headline { font-size: 26px; font-weight: 500; line-height: 1.35; margin-bottom: 24px; }
.bp-feature-list { display: flex; flex-direction: column; gap: 16px; }
.bp-feature { display: flex; align-items: center; gap: 12px; font-size: 13px; color: rgba(255,255,255,0.82); }
.bp-feature-ico { width: 28px; height: 28px; border-radius: 8px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bp-feature-ico svg { width: 13px; height: 13px; stroke: #7FC4B0; fill: none; stroke-width: 2.2; }

.bp-doctor { display: flex; align-items: center; gap: 14px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.14); }
.bp-doctor-photo { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); flex-shrink: 0; }
.bp-doctor-ph { width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.14); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bp-doctor-ph svg { width: 20px; height: 20px; stroke: rgba(255,255,255,0.6); fill: none; stroke-width: 1.6; }
.bp-doctor-name { font-size: 13px; font-weight: 700; }
.bp-doctor-role { font-size: 11px; color: rgba(255,255,255,0.55); margin-top: 1px; }

/* ===== RIGHT — FORM PANEL ===== */
.form-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
.form-inner { width: 100%; max-width: 600px; animation: riseIn 0.6s cubic-bezier(0.16,1,0.3,1) both; }
@keyframes riseIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

.form-title { font-size: 27px; font-weight: 600; color: var(--navy-dark); margin-bottom: 8px; }
.form-sub { font-size: 13.5px; color: var(--text2); margin-bottom: 30px; }

.alert { border-radius: var(--radius); font-size: 12.5px; font-weight: 500; margin-bottom: 18px; padding: 14px 16px; display: flex; align-items: flex-start; gap: 12px; }
.alert-error { background: var(--red-light); color: var(--red); border: 1px solid #F3CFCB; }
.alert-icon { width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; margin-top: 1px; background: var(--red); }
.alert-title { font-weight: 700; font-size: 13px; margin-bottom: 2px; }
.alert-sub { font-weight: 400; font-size: 12px; color: var(--text2); }

.field { margin-bottom: 16px; }
.field label { font-size: 13px; font-weight: 600; color: var(--text); display: block; margin-bottom: 7px; }
.field input, .field select {
  width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px;
  font-size: 13.5px; color: var(--text); outline: none; transition: border-color 0.15s, box-shadow 0.15s; height: 44px;
}
.field select { background: #fff; }
.field input::placeholder { color: var(--text3); }
.field input:focus, .field select:focus { border-color: var(--navy-light); box-shadow: 0 0 0 3px rgba(24,95,165,0.12); }

.field-input-wrap { position: relative; display: flex; align-items: center; }
.field-ico-box {
  position: absolute; left: 7px; width: 30px; height: 30px; border-radius: 8px;
  background: var(--teal-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.field-ico-box svg { width: 15px; height: 15px; stroke: var(--teal); fill: none; stroke-width: 2; }
.field-input-wrap input { padding-left: 50px; height: 48px; }
.field-toggle-eye { position: absolute; right: 14px; width: 18px; height: 18px; cursor: pointer; stroke: var(--text3); fill: none; stroke-width: 1.8; }

.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }

.btn {
  width: 100%; padding: 14px; border: none; border-radius: 12px; color: #fff;
  font-size: 14.5px; font-weight: 700; cursor: pointer; letter-spacing: 0.2px;
  background: linear-gradient(90deg, var(--navy) 0%, var(--navy-light) 100%);
  box-shadow: 0 10px 24px -8px rgba(11,61,107,0.5);
  display: flex; align-items: center; justify-content: center; gap: 9px;
  transition: transform 0.12s ease, box-shadow 0.12s ease; margin-top: 6px;
}
.btn svg { width: 16px; height: 16px; stroke: #fff; fill: none; stroke-width: 2.2; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 14px 28px -8px rgba(11,61,107,0.55); }

.auth-or { display: flex; align-items: center; gap: 14px; margin: 24px 0 20px; color: var(--text3); font-size: 12px; }
.auth-or::before, .auth-or::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.switch { text-align: center; font-size: 13px; color: var(--text2); }
.switch a { color: var(--navy-light); font-weight: 700; text-decoration: none; }
.switch a:hover { text-decoration: underline; }
.back-home { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text2); text-decoration: none; margin-bottom: 24px; }
.back-home:hover { color: var(--navy); }
.back-home svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.2; }

.section-label { font-size: 11px; font-weight: 700; color: var(--navy-light); text-transform: uppercase; letter-spacing: 0.5px; margin: 22px 0 12px; }
.section-label:first-of-type { margin-top: 4px; }

@media (max-width: 880px) {
  .brand-panel { display: none; }
  .form-panel { padding: 40px 20px; }
}
@media (max-width: 560px) {
  .row2, .row3 { grid-template-columns: 1fr; }
}

/* Floating registration window: compact modal-style panel instead of full-screen split layout */
body {
  min-height: 100vh;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  background: radial-gradient(circle at top left, rgba(29,95,224,.12), transparent 38%),
              linear-gradient(160deg, #EAF2FC 0%, #DCEBFC 55%, #F7FAFC 100%);
}
.brand-panel { display: none !important; }
.form-panel {
  flex: none;
  width: min(100%, 760px);
  max-height: calc(100vh - 48px);
  overflow-y: auto;
  align-items: flex-start;
  justify-content: center;
  padding: 0;
  background: #fff;
  border: 1px solid rgba(255,255,255,.85);
  border-radius: 22px;
  box-shadow: 0 24px 70px rgba(11,31,77,.22), 0 3px 12px rgba(11,31,77,.06);
}
.form-inner {
  max-width: 100%;
  padding: 30px 34px;
  animation: riseIn .25s ease-out both;
}
.form-title { font-size: 24px; margin-bottom: 6px; }
.form-sub { margin-bottom: 20px; }
@media (max-width: 560px) {
  body { padding: 12px; }
  .form-panel { max-height: calc(100vh - 24px); border-radius: 16px; }
  .form-inner { padding: 22px 18px; }
  .form-title { font-size: 22px; }
}


/* Medical background image for both patient and doctor authentication pages.
   The semi-transparent overlay keeps the login/register card readable. */
body {
  background-image: linear-gradient(rgba(5, 28, 55, 0.28), rgba(5, 28, 55, 0.28)), url("assets/background.png") !important;
  background-position: center center !important;
  background-size: cover !important;
  background-repeat: no-repeat !important;
  background-attachment: fixed !important;
}

</style>
</head>
<body>

<div class="brand-panel">
  <div class="bp-top">
    <img class="bp-official-logo" src="assets/wedocare-logo.png" alt="WeDoCare official medical logo">
    <div class="bp-logo">We<em>Do</em>Care</div>
    <div class="bp-tagline">Web-Based Patient Management System</div>
  </div>

  <div class="bp-mid">
    <div class="bp-headline serif">Join in a minute, get cared for from day one.</div>
    <div class="bp-feature-list">
      <div class="bp-feature">
        <div class="bp-feature-ico"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        Book online or walk-in appointments
      </div>
      <div class="bp-feature">
        <div class="bp-feature-ico"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg></div>
        Receive prescriptions and lab referrals
      </div>
      <div class="bp-feature">
        <div class="bp-feature-ico"><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        Pay securely and track your history
      </div>
    </div>
  </div>

  <div class="bp-doctor">
    <?php if ($docPhoto): ?>
      <img class="bp-doctor-photo" src="<?= htmlspecialchars($docPhoto) ?>" alt="">
    <?php else: ?>
      <div class="bp-doctor-ph"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg></div>
    <?php endif; ?>
    <div>
      <div class="bp-doctor-name"><?= htmlspecialchars($docName) ?></div>
      <div class="bp-doctor-role"><?= htmlspecialchars($docSpec) ?><?= $docLic ? ' · Lic. '.htmlspecialchars($docLic) : '' ?></div>
    </div>
  </div>
</div>

<div class="form-panel">
  <div class="form-inner">
    <a class="back-home" href="home.php"><svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Back to homepage</a>

    <div class="form-title serif">Create your account</div>
    <div class="form-sub">Register to book appointments and access your medical records online.</div>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <div class="alert-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16" r="0.6" fill="#fff" stroke="none"/></svg></div>
        <div><div class="alert-title">Registration failed</div><div class="alert-sub"><?= htmlspecialchars($error) ?></div></div>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php">
      <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>"><?php endif; ?>
      <div class="section-label">Personal Information</div>
      <div class="row3">
        <div class="field"><label>Last Name</label><input type="text" name="lname" required value="<?= htmlspecialchars(isset($old['lname']) ? $old['lname'] : '') ?>"></div>
        <div class="field"><label>First Name</label><input type="text" name="fname" required value="<?= htmlspecialchars(isset($old['fname']) ? $old['fname'] : '') ?>"></div>
        <div class="field"><label>Middle Name</label><input type="text" name="mname" value="<?= htmlspecialchars(isset($old['mname']) ? $old['mname'] : '') ?>"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Date of Birth</label><input type="date" name="dob" required value="<?= htmlspecialchars(isset($old['dob']) ? $old['dob'] : '') ?>"></div>
        <div class="field">
          <label>Gender</label>
          <select name="gender" required>
            <option value="">Select</option>
            <option <?= ((isset($old['gender']) ? $old['gender'] : '') === 'Male') ? 'selected' : '' ?>>Male</option>
            <option <?= ((isset($old['gender']) ? $old['gender'] : '') === 'Female') ? 'selected' : '' ?>>Female</option>
          </select>
        </div>
      </div>
      <div class="field"><label>Complete Address</label><input type="text" name="address" required value="<?= htmlspecialchars(isset($old['address']) ? $old['address'] : '') ?>"></div>

      <div class="section-label">Contact &amp; Account</div>
      <div class="row2">
        <div class="field"><label>Contact Number</label><input type="text" name="contact" required value="<?= htmlspecialchars(isset($old['contact']) ? $old['contact'] : '') ?>"></div>
        <div class="field"><label>Email Address</label><input type="email" name="email" required value="<?= htmlspecialchars(isset($old['email']) ? $old['email'] : '') ?>"></div>
      </div>
      <div class="row2">
        <div class="field">
          <label>Password</label>
          <div class="field-input-wrap">
            <div class="field-ico-box"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0F6E56" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <input type="password" name="password" id="reg-pw1" required placeholder="Min. 8 characters">
            <svg class="field-toggle-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94A0AF" stroke-width="1.8" onclick="togglePw('reg-pw1')"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Confirm Password</label>
          <div class="field-input-wrap">
            <div class="field-ico-box"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0F6E56" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <input type="password" name="password2" id="reg-pw2" required>
            <svg class="field-toggle-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94A0AF" stroke-width="1.8" onclick="togglePw('reg-pw2')"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
        </div>
      </div>
      <button type="submit" class="btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Account
      </button>
    </form>

    <div class="auth-or">or</div>
    <div class="switch">Already have an account? <a href="login.php<?= $redirect ? '?redirect=' . htmlspecialchars($redirect) : '' ?>">Sign in</a></div>
  </div>
</div>

<script>
  function togglePw(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
  }
</script>
</body>
</html>
