<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google_oauth_config.php';

$clientId = GOOGLE_OAUTH_CLIENT_ID;
$redirectUri = GOOGLE_OAUTH_REDIRECT_URI;
if ($clientId === '' || strpos($clientId, 'PASTE_GOOGLE_CLIENT_ID_HERE') !== false ||
    strpos($redirectUri, 'YOUR-DOMAIN') !== false || !filter_var($redirectUri, FILTER_VALIDATE_URL)) {
    header('Location: login.php?google_error=setup');
    exit;
}

$allowedRedirects = ['book_appointment.php'];
$redirect = isset($_GET['redirect']) && in_array($_GET['redirect'], $allowedRedirects, true) ? $_GET['redirect'] : '';
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_redirect'] = $redirect;

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
exit;
