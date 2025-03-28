<?php
// Διαδρομή: /admin/includes/admin_header.php

// Βεβαιωθείτε ότι το config φορτώνεται σωστά
require_once dirname(__DIR__, 2) . '/config/config.php';

// Φόρτωση admin_scripts.php για τον καθορισμό των JS αρχείων
require_once dirname(__FILE__) . '/admin_scripts.php';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Panel - DriveTest' ?></title>
    
    <!-- Φόρτωση των CSS styles -->
    <?php require_once dirname(__FILE__) . '/admin_styles.php'; ?>
</head>
<body>
<header class="admin-header" role="banner" aria-label="Admin Header">
    <div class="navbar">
        <div class="navbar-brand">
            <a href="<?= $config['base_url'] ?>/admin/dashboard.php">
                <img src="<?= $config['base_url'] ?>/assets/images/drivetest.png" alt="DriveTest Admin" class="logo">
            </a>
        </div>
        
        <button class="navbar-toggler" id="navbar-toggler" aria-label="Toggle navigation">
            <i>☰</i>
        </button>
        
        <div class="navbar-menu" id="navbar-menu">
            <ul class="navbar-nav" role="navigation" aria-label="Admin Navigation">
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/dashboard.php" class="nav-link">
                        <i class="nav-icon">📊</i>Αρχική
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/users.php" class="nav-link">
                        <i class="nav-icon">👥</i>Χρήστες
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/admin_subscriptions.php" class="nav-link">
                        <i class="nav-icon">💰</i>Συνδρομές
                    </a>
                </li>
                
                <!-- Dropdown για διαχείριση τεστ -->
                <li class="nav-item nav-dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="nav-icon">🧩</i>Διαχείριση Τεστ
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/manage_questions.php" class="dropdown-item">
                                <i class="nav-icon">❓</i>Ερωτήσεις
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/bulk_import.php" class="dropdown-item">
                                <i class="nav-icon">📥</i>Μαζική Εισαγωγή
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/manage_subcategories.php" class="dropdown-item">
                                <i class="nav-icon">📑</i>Υποκατηγορίες
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/manage_chapters.php" class="dropdown-item">
                                <i class="nav-icon">📚</i>Κεφάλαια
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/test_config.php" class="dropdown-item">
                                <i class="nav-icon">⚙️</i>Ρυθμίσεις Τεστ
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/generate_test.php" class="dropdown-item">
                                <i class="nav-icon">🧩</i>Δημιουργία Τεστ
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/settings.php" class="nav-link">
                        <i class="nav-icon">⚙️</i>Ρυθμίσεις
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/logout.php" class="nav-link logout-btn">
                        <i class="nav-icon">🚪</i>Αποσύνδεση
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Εδώ θα μπει το περιεχόμενο της κάθε σελίδας -->