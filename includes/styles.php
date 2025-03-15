<?php
// Διαδρομή: /includes/styles.php

/**
 * Αυτό το αρχείο περιέχει όλα τα στυλ CSS που χρησιμοποιούνται στην εφαρμογή
 */
?>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Βασικά CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navbar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/footer.css">

<!-- Ειδικά CSS για χρήστες -->
<?php if (function_exists('is_logged_in') && is_logged_in()): ?>
    <?php if (function_exists('has_role') && has_role('admin')): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
    <?php elseif (function_exists('has_role') && has_role('school')): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/school.css">
    <?php elseif (function_exists('has_role') && has_role('student')): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/student.css">
    <?php else: ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user.css">
    <?php endif; ?>
<?php endif; ?>