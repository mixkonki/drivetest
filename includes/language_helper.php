<?php
// includes/language_helper.php

/**
 * Φορτώνει τις μεταφράσεις για τη συγκεκριμένη γλώσσα
 *
 * @param string $language Ο κωδικός της γλώσσας
 * @return array Οι μεταφράσεις
 */
function load_language($language = null) {
    global $config;
    
    // Αν δεν οριστεί γλώσσα, χρησιμοποιούμε την προεπιλεγμένη
    if ($language === null) {
        $language = $config['default_language'] ?? 'el';
    }
    
    // Επιτρεπόμενες γλώσσες
    $allowed_languages = array_keys($config['available_languages'] ?? ['el' => 'Ελληνικά']);
    
    // Επιβεβαίωση ότι η γλώσσα είναι έγκυρη
    if (!in_array($language, $allowed_languages)) {
        $language = 'el';
    }
    
    $translations_file = BASE_PATH . "/languages/{$language}.php";
    
    if (file_exists($translations_file)) {
        return require $translations_file;
    }
    
    // Fallback στα ελληνικά αν δεν βρεθεί το αρχείο
    return require BASE_PATH . "/languages/el.php";
}

/**
 * Μεταφράζει ένα κείμενο
 *
 * @param string $key Το κλειδί μετάφρασης
 * @param array $params Παράμετροι αντικατάστασης (προαιρετικό)
 * @return string Το μεταφρασμένο κείμενο
 */
function __($key, $params = []) {
    global $translations;
    
    if (isset($translations[$key])) {
        $text = $translations[$key];
        
        // Αντικατάσταση παραμέτρων
        if (!empty($params)) {
            foreach ($params as $param_key => $param_value) {
                $text = str_replace(":$param_key", $param_value, $text);
            }
        }
        
        return $text;
    }
    
    return $key; // Επιστροφή του κλειδιού αν δεν βρεθεί μετάφραση
}