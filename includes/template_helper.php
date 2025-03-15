<?php
// includes/template_helper.php

/**
 * Φορτώνει ένα template με τα δεδομένα που παρέχονται
 *
 * @param string $template_name Το όνομα του template
 * @param array $data Τα δεδομένα για το template (προαιρετικό)
 * @return string Το περιεχόμενο του template
 */
function load_template($template_name, $data = []) {
    // Μετατροπή των δεδομένων σε μεταβλητές
    extract($data);
    
    // Έναρξη output buffering
    ob_start();
    
    // Συμπερίληψη του template
    require BASE_PATH . "/templates/{$template_name}.php";
    
    // Επιστροφή του περιεχομένου και τερματισμός του buffering
    return ob_get_clean();
}

/**
 * Εμφανίζει ένα template με τα δεδομένα που παρέχονται
 *
 * @param string $template_name Το όνομα του template
 * @param array $data Τα δεδομένα για το template (προαιρετικό)
 */
function display_template($template_name, $data = []) {
    echo load_template($template_name, $data);
}