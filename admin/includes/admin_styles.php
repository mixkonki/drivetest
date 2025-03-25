<?php
// Διαδρομή: /admin/includes/admin_styles.php
// Βεβαιωθείτε ότι το BASE_URL είναι διαθέσιμο
if (!defined('BASE_URL')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
?>

<!-- Φόρτωση βασικών CSS για το admin panel -->
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin.css">

<!-- Αυτόματη φόρτωση CSS με βάση το όνομα της σελίδας -->
<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php'); // Αφαιρεί την κατάληξη .php
$page_specific_css = BASE_PATH . '/admin/assets/css/' . $current_page . '.css';
if (file_exists($page_specific_css)) {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/' . $current_page . '.css">';
}

// Για σελίδες σε υποφακέλους (π.χ. test/manage_questions.php)
if (strpos($_SERVER['PHP_SELF'], '/') !== false) {
    $path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
    if (count($path_parts) > 1) {
        // Το τελευταίο μέρος είναι το όνομα του αρχείου
        $filename = basename(end($path_parts), '.php');
        $subfolder = prev($path_parts); // Το προηγούμενο μέρος είναι ο υποφάκελος
        
        // Έλεγχος για CSS σε υποφάκελο
        $subfolder_css = BASE_PATH . '/admin/assets/css/' . $subfolder . '/' . $filename . '.css';
        if (file_exists($subfolder_css)) {
            echo '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/' . $subfolder . '/' . $filename . '.css">';
        }
    }
}
?>

<!-- Φόρτωση CSS με βάση τις flag μεταβλητές -->
<?php
$admin_css_flags = [
    'load_dashboard_css' => 'dashboard.css',
    'load_users_css' => 'users.css',
    'load_admin_subscriptions_css' => 'admin_subscriptions.css',
    'load_chapters_css' => 'test/chapters.css',
    'load_questions_css' => 'test/questions.css',
    'load_subcategories_css' => 'test/subcategories.css',
    'load_test_config_css' => 'test/test_config.css',
    'load_bulk_import_css' => 'test/bulk_import.css'
];

$admin_css_flags = [
    // υπάρχοντα flags...
    'load_form_common_css' => 'form_common.css'
];
foreach ($admin_css_flags as $flag => $css_file) {
    if (isset($$flag) && $$flag === true) {
        $css_path = BASE_PATH . '/admin/assets/css/' . $css_file;
        if (file_exists($css_path)) {
            echo '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/' . $css_file . '">' . "\n";
        }
    }
}
?>

<!-- Additional CSS (αν έχει οριστεί από το σενάριο) -->
<?php if (isset($additional_css)): ?>
    <?= $additional_css ?>
<?php endif; ?>
