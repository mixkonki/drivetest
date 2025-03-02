<?php
// Περιλαμβάνει τις βασικές ρυθμίσεις
require_once 'config/config.php';

// Δοκιμή καταγραφής μηνύματος με error_log
$test_message = "Test log message at " . date('Y-m-d H:i:s') . " - BASE_PATH: " . BASE_PATH . " - Current path: " . __DIR__ . " - Server root: " . $_SERVER['DOCUMENT_ROOT'];
error_log($test_message, 3, BASE_PATH . '/debug_log.txt');

// Εμφάνιση μηνύματος στην οθόνη για επιβεβαίωση
echo "Test log message has been attempted to be written to " . BASE_PATH . '/debug_log.txt' . " at " . date('Y-m-d H:i:s') . ". Check the debug_log.txt file or PHP error logs for results.";
?>

<!-- HTML για εμφάνιση αποτελέσματος -->
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Test Log</title>
</head>
<body>
    <h1>Δοκιμή Καταγραφής Logs</h1>
    <p>Το μήνυμα προσπάθησε να καταγραφεί στο <code><?= BASE_PATH ?>/debug_log.txt</code>. Ελέγξτε το αρχείο ή τα PHP error logs.</p>
</body>
</html>