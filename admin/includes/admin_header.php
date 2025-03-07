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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
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
            <li><a href="<?= $config['base_url'] ?>/admin/dashboard.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" aria-label="Αρχική">
                <i class="nav-icon">📊</i>Αρχική
            </a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/users.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users.php') !== false ? 'active' : '' ?>" aria-label="Διαχείριση Χρηστών">
                <i class="nav-icon">👥</i>Χρήστες
            </a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/admin_subscriptions.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'admin_subscriptions.php') !== false ? 'active' : '' ?>" aria-label="Διαχείριση Κατηγοριών">
                <i class="nav-icon">💰</i>Συνδρομές
            </a></li>
            
            <!-- Dropdown για διαχείριση τεστ -->
            <li class="nav-dropdown">
                <a href="#" class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], '/test/') !== false ? 'active' : '' ?>" aria-label="Διαχείριση Τεστ">
                    <i class="nav-icon">🧩</i>Διαχείριση Τεστ <i class="dropdown-arrow">▼</i>
                </a>
                <ul class="dropdown-menu">
                    <li><a href="<?= $config['base_url'] ?>/admin/test/manage_questions.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_questions.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">❓</i>Ερωτήσεις
                    </a></li>
                    <li><a href="<?= $config['base_url'] ?>/admin/test/bulk_import.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'bulk_import.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">📥</i>Μαζική Εισαγωγή
                    </a></li>
                    <li><a href="<?= $config['base_url'] ?>/admin/test/manage_subcategories.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_subcategories.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">📑</i>Υποκατηγορίες
                    </a></li>
                    <li><a href="<?= $config['base_url'] ?>/admin/test/manage_chapters.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_chapters.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">📚</i>Κεφάλαια
                    </a></li>
                    <li><a href="<?= $config['base_url'] ?>/admin/test/test_config.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'test_config.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">⚙️</i>Ρυθμίσεις Τεστ
                    </a></li>
                    <li><a href="<?= $config['base_url'] ?>/admin/test/generate_test.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'generate_test.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">🧩</i>Δημιουργία Τεστ
                    </a></li>
                </ul>
            </li>
            
            <li><a href="<?= $config['base_url'] ?>/admin/settings.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : '' ?>" aria-label="Ρυθμίσεις">
                <i class="nav-icon">⚙️</i>Ρυθμίσεις
            </a></li>
            <li><a href="<?= $config['base_url'] ?>/admin/logout.php" class="nav-link logout-btn" aria-label="Αποσύνδεση">
                <i class="nav-icon">🚪</i>Αποσύνδεση
            </a></li>
        </ul>
    </nav>
</header>

<script>
// JavaScript για το dropdown μενού
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.nav-dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            dropdown.classList.toggle('active');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        });
    });
    
    // Κλείσιμο του dropdown όταν κάνουμε κλικ έξω από αυτό
    document.addEventListener('click', function(e) {
        dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                const menu = dropdown.querySelector('.dropdown-menu');
                dropdown.classList.remove('active');
                menu.style.display = 'none';
            }
        });
    });
});
</script>