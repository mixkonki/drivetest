<?php

// Φόρτωση των ρυθμίσεων από το config.php
$config = include __DIR__ . '/../config/config.php';

if (!is_array($config)) {
    die("<p style='color:red;'>❌ Σφάλμα: Το config.php δεν επιστρέφει σωστά τις ρυθμίσεις. Η τιμή που επιστράφηκε είναι: " . var_export($config, true) . "</p>");
}



// Έλεγχος αν οι ρυθμίσεις της βάσης δεδομένων είναι πλήρεις
if (empty($config['db_host']) || empty($config['db_user']) || empty($config['db_name'])) {
    die("<p style='color:red;'>❌ Σφάλμα: Λείπουν ρυθμίσεις για τη βάση δεδομένων.</p>");
}

// Δημιουργία σύνδεσης με τη βάση δεδομένων
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_error) {
    die("<p style='color:red;'>❌ Σφάλμα σύνδεσης: " . $mysqli->connect_error . "</p>");
}


$mysqli->set_charset("utf8mb4");
?>
