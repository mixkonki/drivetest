<?php
// Έλεγχος εάν το αρχείο μπορεί να εκτελεστεί χωρίς σφάλματα

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Φόρτωση των απαραίτητων αρχείων
$configPath = dirname(__DIR__) . '/config/config.php';
$dbPath = dirname(__DIR__) . '/includes/db_connection.php';
$aadePath = dirname(__DIR__) . '/includes/aade_api.php';

echo "Checking for file existence:\n";
echo "config.php exists: " . (file_exists($configPath) ? "Yes" : "No") . "\n";
echo "db_connection.php exists: " . (file_exists($dbPath) ? "Yes" : "No") . "\n";
echo "aade_api.php exists: " . (file_exists($aadePath) ? "Yes" : "No") . "\n";

try {
    echo "\nLoading config.php...\n";
    require_once $configPath;
    echo "Config loaded successfully.\n";
    
    echo "\nLoading db_connection.php...\n";
    require_once $dbPath;
    echo "DB connection loaded successfully.\n";
    
    echo "\nLoading aade_api.php...\n";
    require_once $aadePath;
    echo "AADE API loaded successfully.\n";
    
    echo "\nTesting getAadeDetails function with AFM 123456789...\n";
    $result = getAadeDetails('123456789');
    echo "Function returned: " . (is_array($result) ? "Array with " . count($result) . " elements" : "Not an array") . "\n";
    
    if (is_array($result)) {
        echo "\nArray contents:\n";
        print_r($result);
    }
    
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "In file: " . $e->getFile() . " line " . $e->getLine() . "\n";
}

echo "\nEnd of test.";