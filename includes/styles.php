<?php
/**
 * Φόρτωση όλων των απαραίτητων CSS αρχείων
 */

// Βασικά CSS που πρέπει να φορτώνονται πάντα
$main_styles = [
    'main.css',     // Κύριο CSS με βασικά στυλ
    'navbar.css',   // CSS για το navigation
    'footer.css',   // CSS για το footer
];

// CSS αρχεία που φορτώνονται βάσει της σελίδας
$page_specific_styles = [
    'test.css' => $load_test_css ?? false,             // Για τις σελίδες τεστ
    'auth.css' => $load_auth_css ?? false,             // Για σελίδες αυθεντικοποίησης
    'home.css' => $load_home_css ?? false,             // Για την αρχική σελίδα
    'dashboard.css' => $load_dashboard_css ?? false,   // Για τους πίνακες ελέγχου
    'profile.css' => $load_profile_css ?? false,       // Για τις σελίδες προφίλ
];

// Google Fonts
?>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Βασικά CSS -->
<?php foreach ($main_styles as $style): ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $style ?>">
<?php endforeach; ?>

<!-- Ειδικά CSS βάσει σελίδας -->
<?php foreach ($page_specific_styles as $style => $load): ?>
    <?php if ($load): ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $style ?>">
    <?php endif; ?>
<?php endforeach; ?>

<!-- Επιπλέον CSS (προαιρετικό) -->
<?= $additional_css ?? '' ?>