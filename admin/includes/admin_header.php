<?php
// Βεβαιωθείτε ότι το config φορτώνεται σωστά
require_once dirname(__DIR__, 2) . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - DriveTest</title>
    <link rel="icon" type="image/ico" href="<?= $config['base_url'] ?>/assets/images/favicon.ico">
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/admin_styles.css">
</head>
<body>
<header class="admin-header" role="banner" aria-label="Admin Header">
    <div class="logo">
        <a href="<?= $config['base_url'] ?>/admin/dashboard.php" aria-label="DriveTest Admin Logo">
            <img src="<?= $config['base_url'] ?>/assets/images/drivetest.png" alt="DriveTest Admin Panel" aria-label="DriveTest Logo">
        </a>
    </div>
    <nav>
        <ul role="navigation" aria-label="Admin Navigation">
            <li><a href="<?= $config['base_url'] ?>/admin/dashboard.php" class="nav-link" aria-label="Αρχική">Αρχική</a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/users.php" class="nav-link" aria-label="Διαχείριση Χρηστών">Διαχείριση Χρηστών</a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/admin_subscriptions.php" class="nav-link" aria-label="Διαχείριση Κατηγοριών">Διαχείριση Κατηγοριών</a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/test/manage_subcategories.php" class="nav-link" aria-label="Διαχείριση Υποκατηγοριών">Διαχείριση Υποκατηγοριών</a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/test/manage_chapters.php" class="nav-link" aria-label="Διαχείριση Κεφαλαίων">Διαχείριση Κεφαλαίων</a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/test/manage_questions.php" class="nav-link" aria-label="Διαχείριση Ερωτήσεων">Διαχείριση Ερωτήσεων</a></li>
            <!-- Προσθήκη στο admin/includes/admin_header.php, στην ενότητα <ul role="navigation"> -->
<li><a href="<?= BASE_URL ?>/admin/test/test_config.php" class="nav-link" aria-label="Ρυθμίσεις Τεστ">Ρυθμίσεις Τεστ</a></li>
<li><a href="<?= BASE_URL ?>/admin/test/generate_test.php" class="nav-link" aria-label="Δημιουργία Τεστ">Δημιουργία Τεστ</a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/settings.php" class="nav-link" aria-label="Ρυθμίσεις">Ρυθμίσεις</a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/logout.php" class="nav-link logout-btn" aria-label="Αποσύνδεση">Αποσύνδεση</a></li>
        </ul>
    </nav>
</header>