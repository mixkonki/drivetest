<?php
// Διαδρομή: /admin/includes/admin_scripts.php
// Βεβαιωθείτε ότι το BASE_URL είναι διαθέσιμο
if (!defined('BASE_URL')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
?>

<!-- Αυτόματη φόρτωση script με βάση το όνομα της σελίδας -->
<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php'); // Αφαιρεί την κατάληξη .php
$page_specific_js = BASE_URL . '/admin/assets/js/' . $current_page . '.js';
$page_specific_js_file = BASE_PATH . '/admin/assets/js/' . $current_page . '.js';
if (file_exists($page_specific_js_file)) {
    echo '<script src="' . $page_specific_js . '"></script>';
}

// Για σελίδες σε υποφακέλους (π.χ. test/manage_questions.php)
if (strpos($_SERVER['PHP_SELF'], '/') !== false) {
    $path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
    if (count($path_parts) > 1) {
        // Το τελευταίο μέρος είναι το όνομα του αρχείου
        $filename = basename(end($path_parts), '.php');
        $subfolder = prev($path_parts); // Το προηγούμενο μέρος είναι ο υποφάκελος
        
        // Έλεγχος για JS σε υποφάκελο
        $subfolder_js_file = BASE_PATH . '/admin/assets/js/' . $subfolder . '/' . $filename . '.js';
        $subfolder_js = BASE_URL . '/admin/assets/js/' . $subfolder . '/' . $filename . '.js';
        if (file_exists($subfolder_js_file)) {
            echo '<script src="' . $subfolder_js . '"></script>';
        }
    }
}
?>

<!-- Conditional JS loading με βάση τις flag μεταβλητές -->
<?php
$admin_js_flags = [
    'load_chart_js' => '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>',
    'load_dashboard_js' => '<script src="' . BASE_URL . '/admin/assets/js/dashboard.js"></script>',
    'load_users_js' => '<script src="' . BASE_URL . '/admin/assets/js/users.js"></script>',
    'load_admin_subscriptions_js' => '<script src="' . BASE_URL . '/admin/assets/js/admin_subscriptions.js"></script>',
    'load_chapters_js' => '<script src="' . BASE_URL . '/admin/assets/js/test/chapters.js"></script>',
    'load_questions_js' => '<script src="' . BASE_URL . '/admin/assets/js/test/questions.js"></script>',
    'load_subcategories_js' => '<script src="' . BASE_URL . '/admin/assets/js/test/subcategories.js"></script>',
    'load_test_config_js' => '<script src="' . BASE_URL . '/admin/assets/js/test/test_config.js"></script>',
    'load_bulk_import_js' => '<script src="' . BASE_URL . '/admin/assets/js/test/bulk_import.js"></script>'
];

foreach ($admin_js_flags as $flag => $js_include) {
    if (isset($$flag) && $$flag === true) {
        echo $js_include . "\n";
    }
}
?>

<!-- Additional JS (αν έχει οριστεί από το σενάριο) -->
<?php if (isset($additional_js)): ?>
    <?= $additional_js ?>
<?php endif; ?>