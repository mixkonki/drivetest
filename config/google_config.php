<?php
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID'); // Αντικατέστησε με το Client ID σου
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET'); // Αντικατέστησε με το Client Secret σου
define('GOOGLE_REDIRECT_URL', BASE_URL . '/public/google_callback.php');
define('GOOGLE_SCOPES', ['email', 'profile']);