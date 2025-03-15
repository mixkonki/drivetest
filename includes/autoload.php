<?php
// Διαδρομή: /includes/autoload.php

/**
 * Autoloader για τις κλάσεις
 * 
 * @param string $class_name Το όνομα της κλάσης
 */
function autoload($class_name) {
    // Έλεγχος αν η κλάση είναι από εξωτερική βιβλιοθήκη
    if (strpos($class_name, '\\') !== false) {
        return;
    }
    
    // Διαδρομή της κλάσης
    $class_file = BASE_PATH . '/classes/' . $class_name . '.php';
    
    // Έλεγχος αν υπάρχει το αρχείο
    if (file_exists($class_file)) {
        require_once $class_file;
    }
}

// Εγγραφή του autoloader
spl_autoload_register('autoload');