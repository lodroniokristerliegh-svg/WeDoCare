<?php
/**
 * Google Sign-In configuration for WeDoCare.
 * Create OAuth credentials in Google Cloud Console and fill these values.
 * Keep the client secret private; do not commit/share it publicly.
 */
define('GOOGLE_OAUTH_CLIENT_ID', 'PASTE_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_OAUTH_CLIENT_SECRET', 'PASTE_GOOGLE_CLIENT_SECRET_HERE');

/** Exact URL of google_callback.php on your deployed site, using HTTPS. */
define('GOOGLE_OAUTH_REDIRECT_URI', 'https://YOUR-DOMAIN/google_callback.php');
