<?php
// Διαδρομή: /admin/includes/admin_scripts.php
// Βεβαιωθείτε ότι το BASE_URL είναι διαθέσιμο
if (!defined('BASE_URL')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}

// Κατάλογος κοινών JS αρχείων για όλες τις σελίδες διαχείρισης
$common_js_files = [
    '/admin/assets/js/admin_main.js',
    '/admin/assets/js/admin_navbar.js'
];

// Αρχικοποίηση πίνακα για ειδικά JS
$admin_js_files = [];

// Αυτόματη φόρτωση JS με βάση το όνομα του αρχείου
$current_page = basename($_SERVER['PHP_SELF'], '.php'); // Αφαιρεί την κατάληξη .php
$specific_js = "/admin/assets/js/{$current_page}.js";

// Έλεγχος για JS σε υποφακέλους (π.χ. test/manage_questions.php)
if (strpos($_SERVER['PHP_SELF'], '/admin/test/') !== false) {
    // Φόρτωση του κοινού JS για το τμήμα test
    $test_common_js = "/admin/assets/js/test/test_common.js";
    if (file_exists(BASE_PATH . $test_common_js)) {
        $admin_js_files[] = $test_common_js;
    }
    
    // Φόρτωση του ειδικού JS για την τρέχουσα σελίδα του τμήματος test
    $test_file = basename($_SERVER['PHP_SELF'], '.php');
    $specific_test_js = "/admin/assets/js/test/{$test_file}.js";
    
    if (file_exists(BASE_PATH . $specific_test_js)) {
        $admin_js_files[] = $specific_test_js;
    }
} else {
    // Έλεγχος και φόρτωση του ειδικού JS για την τρέχουσα σελίδα
    if (file_exists(BASE_PATH . $specific_js)) {
        $admin_js_files[] = $specific_js;
    }
}

// Φόρτωση JS με βάση τις flag μεταβλητές
$admin_js_flags = [
    'load_dashboard_js' => '/admin/assets/js/dashboard.js',
    'load_users_js' => '/admin/assets/js/users.js',
    'load_admin_subscriptions_js' => '/admin/assets/js/admin_subscriptions.js',
    'load_chapters_js' => '/admin/assets/js/test/chapters.js',
    'load_questions_js' => '/admin/assets/js/test/questions.js',
    'load_subcategories_js' => '/admin/assets/js/test/subcategories.js',
    'load_test_config_js' => '/admin/assets/js/test/test_config.js',
    'load_bulk_import_js' => '/admin/assets/js/test/bulk_import.js'
];

// Φόρτωση των JS με βάση τις flag μεταβλητές
foreach ($admin_js_flags as $flag => $js_file) {
    if (isset($$flag) && $$flag === true) {
        if (file_exists(BASE_PATH . $js_file)) {
            $admin_js_files[] = $js_file;
        }
    }
}

// Ειδικές περιπτώσεις για εξωτερικά scripts
$external_js_flags = [
    'load_chart_js' => 'https://cdn.jsdelivr.net/npm/chart.js',
    // Προσθέστε εδώ και άλλα εξωτερικά JS
];

// Δεν φορτώνουμε ακόμα τα scripts, αυτό θα γίνει στο admin_footer.php
// Απλώς συλλέγουμε τη λίστα με τα απαιτούμενα scripts

// Τέλος, δημιουργούμε έναν πίνακα με τα εξωτερικά scripts που πρέπει να φορτωθούν
$external_scripts_to_load = [];
foreach ($external_js_flags as $flag => $js_url) {
    if (isset($$flag) && $$flag === true) {
        $external_scripts_to_load[] = $js_url;
    }
}