<?php
// Διαδρομή: /includes/styles.php
?>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap-reboot.min.css" rel="stylesheet">
<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<!-- Βασικά CSS -->
<?php
// Έλεγχος και φόρτωση βασικών CSS
$base_css_files = [
    '/assets/css/main.css',
    '/assets/css/navbar.css',
    '/assets/css/footer.css'
];
foreach ($base_css_files as $css_file) {
    if (file_exists(BASE_PATH . $css_file)) {
        echo '<link rel="stylesheet" href="' . BASE_URL . $css_file . '">' . "\n";
    }
}
?>
<!-- Ειδικά CSS για χρήστες -->
<?php if (function_exists('is_logged_in') && is_logged_in()): ?>
    <?php 
    $role_css = '';
    
    if (function_exists('has_role') && has_role('admin')) {
        $role_css = '/assets/css/admin.css';
    } elseif (function_exists('has_role') && has_role('school')) {
        $role_css = '/assets/css/school.css';
    } elseif (function_exists('has_role') && has_role('student')) {
        $role_css = '/assets/css/student.css';
    } else {
        $role_css = '/assets/css/user.css';
    }
    
    if (!empty($role_css) && file_exists(BASE_PATH . $role_css)) {
        echo '<link rel="stylesheet" href="' . BASE_URL . $role_css . '">' . "\n";
    }
    ?>
<?php endif; ?>

<!-- Αυτόματη φόρτωση CSS με βάση το όνομα της σελίδας -->
<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php'); // Αφαιρεί την κατάληξη .php
$page_specific_css = BASE_PATH . '/assets/css/' . $current_page . '.css';
if (file_exists($page_specific_css)) {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/' . $current_page . '.css">';
}
?>