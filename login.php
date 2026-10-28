<?php
require_once __DIR__ . '/config.php';

$allowedRedirects = ['book_appointment.php'];
$redirect = (isset($_GET['redirect']) && in_array($_GET['redirect'], $allowedRedirects, true))
    ? $_GET['redirect']
    : (isset($_POST['redirect']) && in_array($_POST['redirect'], $allowedRedirects, true) ? $_POST['redirect'] : '');

// If already logged in, go straight to the right dashboard (or the
// page they originally wanted, if it's on the whitelist).
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($redirect ?: ($_SESSION['role'] === 'doctor' ? 'dashboard_doctor.php' : 'dashboard_patient.php')));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id, role, full_name, email, password_hash, patient_id, is_active
                                FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password. Please try again.';
        } elseif (!$user['is_active']) {
            $error = 'This account has been deactivated. Please contact the clinic.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['patient_id'] = $user['patient_id'];

            if (!empty($_POST['remember'])) {
                $params = session_get_cookie_params();
                setcookie(session_name(), session_id(), time() + 60*60*24*30, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }

            header('Location: ' . ($redirect ?: ($user['role'] === 'doctor' ? 'dashboard_doctor.php' : 'dashboard_patient.php')));
            exit;
        }
    }
}

// Pull the doctor's own profile so the branding panel feels personal.
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
<title>Sign In — WeDoCare</title>
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
  flex: 0 0 44%; position: relative; overflow: hidden;
  background: linear-gradient(155deg, var(--navy-dark) 0%, var(--navy) 55%, var(--navy-light) 100%);
  color: #fff; display: flex; flex-direction: column; justify-content: space-between;
  padding: 48px 54px;
}
.brand-panel::before {
  content: ''; position: absolute; width: 460px; height: 460px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.06), transparent 70%); top: -180px; right: -160px;
}
.brand-panel::after {
  content: ''; position: absolute; width: 340px; height: 340px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.05), transparent 70%); bottom: -140px; left: -120px;
}
.bp-top, .bp-mid, .bp-bottom { position: relative; z-index: 1; }
.bp-logo { font-size: 26px; font-weight: 600; letter-spacing: -0.4px; }
.bp-official-logo { display:block; width:76px; height:76px; object-fit:contain; background:#fff; border-radius:14px; padding:5px; margin-bottom:14px; box-sizing:border-box; }
.bp-logo em { font-style: normal; color: #7FC4B0; }
.bp-tagline { font-size: 12.5px; color: rgba(255,255,255,0.55); margin-top: 4px; }

.bp-mid { margin: auto 0; padding: 40px 0; }
.bp-headline { font-size: 30px; font-weight: 500; line-height: 1.32; margin-bottom: 26px; max-width: 380px; }
.bp-feature-list { display: flex; flex-direction: column; gap: 17px; }
.bp-feature { display: flex; align-items: center; gap: 12px; font-size: 13.5px; color: rgba(255,255,255,0.82); }
.bp-feature-ico { width: 30px; height: 30px; border-radius: 8px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bp-feature-ico svg { width: 14px; height: 14px; stroke: #7FC4B0; fill: none; stroke-width: 2.2; }

.bp-doctor { display: flex; align-items: center; gap: 14px; padding-top: 26px; border-top: 1px solid rgba(255,255,255,0.14); }
.bp-doctor-photo { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); flex-shrink: 0; }
.bp-doctor-ph { width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.14); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bp-doctor-ph svg { width: 22px; height: 22px; stroke: rgba(255,255,255,0.6); fill: none; stroke-width: 1.6; }
.bp-doctor-name { font-size: 13.5px; font-weight: 700; }
.bp-doctor-role { font-size: 11.5px; color: rgba(255,255,255,0.55); margin-top: 1px; }

/* ===== RIGHT — FORM PANEL ===== */
.form-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
.form-inner { width: 100%; max-width: 400px; animation: riseIn 0.6s cubic-bezier(0.16,1,0.3,1) both; }
@keyframes riseIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

.form-title { font-size: 27px; font-weight: 600; color: var(--navy-dark); margin-bottom: 8px; }
.form-sub { font-size: 13.5px; color: var(--text2); margin-bottom: 30px; }

.alert {
  border-radius: var(--radius); font-size: 12.5px; font-weight: 500; margin-bottom: 18px;
  padding: 14px 16px; display: flex; align-items: flex-start; gap: 12px;
}
.alert-error { background: var(--red-light); color: var(--red); border: 1px solid #F3CFCB; }
.alert-success { background: var(--green-light); color: #16783F; border: 1px solid #C7EBD5; }
.alert-icon { width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; margin-top: 1px; }
.alert-success .alert-icon { background: var(--green); }
.alert-error .alert-icon { background: var(--red); }
.alert-title { font-weight: 700; font-size: 13px; margin-bottom: 2px; }
.alert-sub { font-weight: 400; font-size: 12px; color: var(--text2); }

.field { margin-bottom: 18px; }
.field label { font-size: 13px; font-weight: 600; color: var(--text); display: block; margin-bottom: 7px; }
.field-input-wrap { position: relative; display: flex; align-items: center; }
.field-ico-box {
  position: absolute; left: 7px; width: 30px; height: 30px; border-radius: 8px;
  background: var(--teal-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.field-ico-box svg { width: 15px; height: 15px; stroke: var(--teal); fill: none; stroke-width: 2; }
.field input {
  width: 100%; padding: 12px 14px 12px 50px; border: 1.5px solid var(--border); border-radius: 10px;
  font-size: 13.5px; color: var(--text); outline: none; transition: border-color 0.15s, box-shadow 0.15s; height: 48px;
}
.field input::placeholder { color: var(--text3); }
.field input:focus { border-color: var(--navy-light); box-shadow: 0 0 0 3px rgba(24,95,165,0.12); }
.field-toggle-eye { position: absolute; right: 14px; width: 18px; height: 18px; cursor: pointer; stroke: var(--text3); fill: none; stroke-width: 1.8; }

.field-row-between { display: flex; align-items: center; justify-content: space-between; margin: -6px 0 22px; }
.remember-check { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: var(--text2); cursor: pointer; user-select: none; }
.remember-check input { width: 15px; height: 15px; accent-color: var(--navy-light); cursor: pointer; }
.auth-forgot { font-size: 12.5px; color: var(--navy-light); font-weight: 600; text-decoration: none; }
.auth-forgot:hover { text-decoration: underline; }

.btn {
  width: 100%; padding: 14px; border: none; border-radius: 12px; color: #fff;
  font-size: 14.5px; font-weight: 700; cursor: pointer; letter-spacing: 0.2px;
  background: linear-gradient(90deg, var(--navy) 0%, var(--navy-light) 100%);
  box-shadow: 0 10px 24px -8px rgba(11,61,107,0.5);
  display: flex; align-items: center; justify-content: center; gap: 9px;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.btn svg { width: 16px; height: 16px; stroke: #fff; fill: none; stroke-width: 2.2; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 14px 28px -8px rgba(11,61,107,0.55); }

.auth-or { display: flex; align-items: center; gap: 14px; margin: 24px 0 20px; color: var(--text3); font-size: 12px; }
.auth-or::before, .auth-or::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.google-login { width: 100%; min-height: 48px; display: flex; align-items: center; justify-content: center; gap: 11px; padding: 12px 16px; border: 1px solid #DADCE0; border-radius: 10px; background: #fff; color: #202124; text-decoration: none; font-size: 14px; font-weight: 600; box-shadow: 0 1px 2px rgba(60,64,67,.12); transition: background .15s, box-shadow .15s; }
.google-login:hover { background: #F8FAFD; box-shadow: 0 2px 5px rgba(60,64,67,.18); }
.google-login svg { width: 19px; height: 19px; flex: 0 0 19px; }
.switch { text-align: center; font-size: 13px; color: var(--text2); }
.switch a { color: var(--navy-light); font-weight: 700; text-decoration: none; }
.switch a:hover { text-decoration: underline; }
.back-home { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text2); text-decoration: none; margin-bottom: 30px; }
.back-home:hover { color: var(--navy); }
.back-home svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.2; }

@media (max-width: 880px) {
  .brand-panel { display: none; }
  .form-panel { padding: 40px 20px; }
}

/* ===== Modern compact floating login window ===== */
body {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  overflow-x: hidden;
  background:
    radial-gradient(ellipse at 15% 15%, rgba(24,95,165,.14), transparent 38%),
    radial-gradient(ellipse at 85% 85%, rgba(15,110,86,.12), transparent 38%),
    linear-gradient(145deg, #edf4fa 0%, #f7fafc 52%, #e8f2f7 100%);
}
/* Keep the login focused: the large split-screen branding panel is removed. */
.brand-panel { display: none !important; }
.form-panel {
  position: relative;
  z-index: 2;
  flex: 0 1 auto;
  width: 100%;
  min-height: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.form-inner {
  width: min(100%, 440px);
  max-width: 440px;
  padding: 34px 34px 30px;
  background: rgba(255,255,255,.97);
  border: 1px solid rgba(219,230,240,.95);
  border-radius: 22px;
  box-shadow: 0 26px 75px rgba(12,45,78,.17), 0 4px 14px rgba(12,45,78,.05);
  animation: loginFloatIn .32s ease-out both;
}
@keyframes loginFloatIn {
  from { opacity: 0; transform: translateY(10px) scale(.985); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.form-title { font-size: 25px; margin-bottom: 6px; }
.form-sub { margin-bottom: 24px; line-height: 1.55; }
.back-home { margin-bottom: 22px; }
.field { margin-bottom: 15px; }
.field-row-between { margin: 0 0 18px; }
.auth-or { margin: 19px 0 16px; }
@media (max-width: 520px) {
  body { padding: 14px; }
  .form-inner {
    width: 100%;
    padding: 27px 22px 24px;
    border-radius: 17px;
  }
}
@media (max-height: 650px) {
  body { align-items: flex-start; }
  .form-inner { margin: 8px auto; }
}
@media (prefers-reduced-motion: reduce) {
  .form-inner { animation: none; }
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
    <div class="bp-headline serif">Your care, continued — right where you left off.</div>
    <div class="bp-feature-list">
      <div class="bp-feature">
        <div class="bp-feature-ico"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        Manage your appointments in one place
      </div>
      <div class="bp-feature">
        <div class="bp-feature-ico"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg></div>
        Access prescriptions and lab referrals anytime
      </div>
      <div class="bp-feature">
        <div class="bp-feature-ico"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
        Your records, kept private and secure
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

    <div class="form-title serif">Welcome back</div>
    <div class="form-sub">Sign in to continue to your WeDoCare account.</div>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <div class="alert-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16" r="0.6" fill="#fff" stroke="none"/></svg></div>
        <div><div class="alert-title">Sign in failed</div><div class="alert-sub"><?= htmlspecialchars($error) ?></div></div>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success">
        <div class="alert-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div><div class="alert-title">Account created successfully!</div><div class="alert-sub">Please sign in below.</div></div>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['loggedout'])): ?>
      <div class="alert alert-success">
        <div class="alert-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div><div class="alert-title">You have been logged out.</div><div class="alert-sub">Please sign in again to continue.</div></div>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['google_error'])): ?>
      <?php
        $googleError = (string) $_GET['google_error'];
        $googleMessages = [
          'setup' => 'Google sign-in is not configured yet. Please use email and password for now.',
          'cancelled' => 'Google sign-in was cancelled.',
          'state' => 'Your Google sign-in session expired or could not be verified. Please try again.',
          'code' => 'Google did not return an authorization code. Please try again.',
          'oauth' => 'Could not complete Google sign-in. Please try again later.',
          'profile' => 'Google could not verify your email address. Please try another account.',
          'account' => 'No existing patient account matches this Google email. Register with this email first, or use your usual login.',
          'inactive' => 'This patient account has been deactivated. Please contact the clinic.'
        ];
        $googleMessage = isset($googleMessages[$googleError]) ? $googleMessages[$googleError] : 'Google sign-in failed. Please try again.';
      ?>
      <div class="alert alert-error">
        <div class="alert-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16" r="0.6" fill="#fff" stroke="none"/></svg></div>
        <div><div class="alert-title">Google sign-in</div><div class="alert-sub"><?= htmlspecialchars($googleMessage, ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['accountmissing'])): ?>
      <div class="alert alert-error">
        <div class="alert-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16" r="0.6" fill="#fff" stroke="none"/></svg></div>
        <div><div class="alert-title">Account not found</div><div class="alert-sub">Your patient record could not be found — it may have been removed by the clinic. Please register again or contact the clinic.</div></div>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>"><?php endif; ?>
      <div class="field">
        <label>Email Address</label>
        <div class="field-input-wrap">
          <div class="field-ico-box"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0F6E56" stroke-width="2"><path d="M22 6c0-1.1-.9-2-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6z"/><path d="m22 6-10 7L2 6"/></svg></div>
          <input type="email" name="email" placeholder="you@email.com" required autofocus
                 value="<?= htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : '') ?>">
        </div>
      </div>
      <div class="field">
        <label>Password</label>
        <div class="field-input-wrap">
          <div class="field-ico-box"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0F6E56" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
          <input type="password" name="password" id="login-pw" placeholder="••••••••" required>
          <svg class="field-toggle-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94A0AF" stroke-width="1.8" onclick="togglePw()"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
      </div>

      <div class="field-row-between">
        <label class="remember-check">
          <input type="checkbox" name="remember" value="1">
          Remember me
        </label>
        <a class="auth-forgot" href="#" onclick="alert('Please contact the clinic administrator to reset your password.'); return false;">Forgot password?</a>
      </div>

      <button type="submit" class="btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Sign In
      </button>
    </form>

    <div class="auth-or">OR CONTINUE WITH</div>
    <a class="google-login" href="google_login.php<?= $redirect ? '?redirect=' . rawurlencode($redirect) : '' ?>" aria-label="Continue with Google">
      <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.42 37.98 46.98 31.84 46.98 24.55z"/><path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.87.93 7.52 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.14 1.45-4.88 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
      Continue with Google
    </a>
    <div class="switch" style="margin-top:22px">Don't have an account? <a href="register.php<?= $redirect ? '?redirect=' . htmlspecialchars($redirect) : '' ?>">Register as a patient</a></div>
  </div>
</div>

<script>
  function togglePw() {
    const el = document.getElementById('login-pw');
    el.type = el.type === 'password' ? 'text' : 'password';
  }
</script>
</body>
</html>
