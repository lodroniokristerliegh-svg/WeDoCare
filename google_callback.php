<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google_oauth_config.php';

function google_login_fail($code) {
    header('Location: login.php?google_error=' . rawurlencode($code));
    exit;
}

if (isset($_GET['error'])) {
    google_login_fail('cancelled');
}
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) ||
    !hash_equals($_SESSION['google_oauth_state'], (string) $_GET['state'])) {
    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_redirect']);
    google_login_fail('state');
}
$redirect = isset($_SESSION['google_oauth_redirect']) && $_SESSION['google_oauth_redirect'] === 'book_appointment.php'
    ? 'book_appointment.php' : '';
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_redirect']);

if (empty($_GET['code'])) {
    google_login_fail('code');
}
if (strpos(GOOGLE_OAUTH_CLIENT_ID, 'PASTE_GOOGLE_CLIENT_ID_HERE') !== false ||
    strpos(GOOGLE_OAUTH_CLIENT_SECRET, 'PASTE_GOOGLE_CLIENT_SECRET_HERE') !== false ||
    strpos(GOOGLE_OAUTH_REDIRECT_URI, 'YOUR-DOMAIN') !== false) {
    google_login_fail('setup');
}

$tokenPayload = http_build_query([
    'code' => (string) $_GET['code'],
    'client_id' => GOOGLE_OAUTH_CLIENT_ID,
    'client_secret' => GOOGLE_OAUTH_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_OAUTH_REDIRECT_URI,
    'grant_type' => 'authorization_code',
], '', '&', PHP_QUERY_RFC3986);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content' => $tokenPayload,
        'timeout' => 15,
        'ignore_errors' => true,
    ]
]);
$tokenResponse = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
$token = is_string($tokenResponse) ? json_decode($tokenResponse, true) : null;
if (!is_array($token) || empty($token['access_token'])) {
    error_log('WeDoCare Google OAuth: token exchange failed.');
    google_login_fail('oauth');
}

$userContext = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Authorization: Bearer ' . $token['access_token'] . "\r\nAccept: application/json\r\n",
        'timeout' => 15,
        'ignore_errors' => true,
    ]
]);
$userResponse = @file_get_contents('https://openidconnect.googleapis.com/v1/userinfo', false, $userContext);
$googleUser = is_string($userResponse) ? json_decode($userResponse, true) : null;
if (!is_array($googleUser) || empty($googleUser['email']) || empty($googleUser['email_verified'])) {
    error_log('WeDoCare Google OAuth: user info missing or email not verified.');
    google_login_fail('profile');
}

$email = strtolower(trim($googleUser['email']));
$stmt = $pdo->prepare("SELECT user_id, role, full_name, email, patient_id, is_active FROM users WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Google sign-in is intentionally patient-only and only links to an existing account.
// It does not create a patient account or allow a Google identity to sign in as the doctor.
if (!$user || $user['role'] !== 'patient' || empty($user['patient_id'])) {
    google_login_fail('account');
}
if (empty($user['is_active'])) {
    google_login_fail('inactive');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['role'] = $user['role'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['patient_id'] = $user['patient_id'];
header('Location: ' . ($redirect ?: 'dashboard_patient.php'));
exit;
