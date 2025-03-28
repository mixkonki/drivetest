<?php
// Διαδρομή: /admin/includes/admin_styles.php
// Βεβαιωθείτε ότι το BASE_URL είναι διαθέσιμο
if (!defined('BASE_URL')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}

// Κατάλογος κοινών CSS για όλες τις σελίδες διαχείρισης
$common_css_files = [
    '/admin/assets/css/variables.css',
    '/admin/assets/css/admin_navbar.css',
    '/admin/assets/css/admin_unified.css',
    '/admin/assets/css/admin_footer.css',
 ];

// Αρχικοποίηση πίνακα για ειδικά CSS
$admin_css_files = [];

// Αυτόματη φόρτωση CSS με βάση το όνομα του αρχείου
$current_page = basename($_SERVER['PHP_SELF'], '.php'); // Αφαιρεί την κατάληξη .php
$specific_css = "/admin/assets/css/{$current_page}.css";

// Έλεγχος για CSS σε υποφακέλους (π.χ. test/manage_questions.php)
if (strpos($_SERVER['PHP_SELF'], '/admin/test/') !== false) {
    // Φόρτωση του κοινού CSS για το τμήμα test
    $test_common_css = "/admin/assets/css/test/test_common.css";
    if (file_exists(BASE_PATH . $test_common_css)) {
        $admin_css_files[] = $test_common_css;
    }
    
    // Φόρτωση του ειδικού CSS για την τρέχουσα σελίδα του τμήματος test
    $test_file = basename($_SERVER['PHP_SELF'], '.php');
    $specific_test_css = "/admin/assets/css/test/{$test_file}.css";
    
    if (file_exists(BASE_PATH . $specific_test_css)) {
        $admin_css_files[] = $specific_test_css;
    }
} else {
    // Έλεγχος και φόρτωση του ειδικού CSS για την τρέχουσα σελίδα
    if (file_exists(BASE_PATH . $specific_css)) {
        $admin_css_files[] = $specific_css;
    }
}

// Φόρτωση CSS με βάση τις flag μεταβλητές
$admin_css_flags = [
    'load_dashboard_css' => 'dashboard.css',
    'load_users_css' => 'users.css',
    'load_admin_subscriptions_css' => 'admin_subscriptions.css',
    'load_chapters_css' => 'test/chapters.css',
    'load_questions_css' => 'test/questions.css',
    'load_subcategories_css' => 'test/subcategories.css',
    'load_test_config_css' => 'test/test_config.css',
    'load_bulk_import_css' => 'test/bulk_import.css',
    'load_form_common_css' => 'form_common.css'
];

foreach ($admin_css_flags as $flag => $css_file) {
    if (isset($$flag) && $$flag === true) {
        $css_path = "/admin/assets/css/{$css_file}";
        if (file_exists(BASE_PATH . $css_path)) {
            $admin_css_files[] = $css_path;
        }
    }
}

// Φόρτωση των κοινών CSS αρχείων
foreach ($common_css_files as $css_file) {
    if (file_exists(BASE_PATH . $css_file)) {
        echo '<link rel="stylesheet" href="' . BASE_URL . $css_file . '">' . "\n";
    }
}

// Φόρτωση των ειδικών admin CSS, αν υπάρχουν (αποφυγή διπλοτύπων)
if (!empty($admin_css_files)) {
    $loaded_files = []; // Για αποφυγή διπλών φορτώσεων
    
    foreach ($admin_css_files as $css_file) {
        if (!in_array($css_file, $loaded_files) && file_exists(BASE_PATH . $css_file)) {
            echo '<link rel="stylesheet" href="' . BASE_URL . $css_file . '">' . "\n";
            $loaded_files[] = $css_file;
        }
    }
}

// Φόρτωση τυχόν εξωτερικού CSS που έχει οριστεί από τη σελίδα
if (isset($additional_css) && !empty($additional_css)) {
    echo $additional_css;
}
?>