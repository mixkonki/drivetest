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
    <!-- Φόρτωση του αρχείου μεταβλητών CSS -->
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/variables.css">
    <!-- Φόρτωση του κεντρικού CSS -->
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/assets/css/main.css">
    <!-- Φόρτωση του CSS για την navbar -->
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/admin_navbar.css">
    <!-- Φόρτωση του ενοποιημένου admin CSS -->
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/admin_unified.css">
    <!-- Φόρτωση του CSS για το footer -->
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/admin_footer.css">
    <!-- Φόρτωση του CSS για το dashboard (όπου χρειάζεται) -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false): ?>
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/admin_dashboard.css">
    <?php endif; ?>
    <!-- Φόρτωση του CSS για τη διαχείριση χρηστών (όπου χρειάζεται) -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'users.php') !== false): ?>
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/admin_users.css">
    <?php endif; ?>
    <!-- Φόρτωση του CSS για τη διαχείριση συνδρομών (όπου χρειάζεται) -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'admin_subscriptions.php') !== false): ?>
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/subscription_management.css">
    <?php endif; ?>

<!-- Φόρτωση του CSS για τη διαχείριση υποκατηγοριών (όπου χρειάζεται) -->
<?php if (strpos($_SERVER['PHP_SELF'], '/test/manage_subcategories.php') !== false || 
          strpos($_SERVER['PHP_SELF'], '/test/add_subcategory.php') !== false || 
          strpos($_SERVER['PHP_SELF'], '/test/edit_subcategory.php') !== false || 
          strpos($_SERVER['PHP_SELF'], '/test/delete_subcategory.php') !== false): ?>
<link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/subcategory_style.css">
<?php endif; ?>
<!-- Φόρτωση του CSS για τη διαχείριση κεφαλαίων (όπου χρειάζεται) -->
<?php if (strpos($_SERVER['PHP_SELF'], '/test/manage_chapters.php') !== false || 
          strpos($_SERVER['PHP_SELF'], '/test/add_chapter.php') !== false || 
          strpos($_SERVER['PHP_SELF'], '/test/edit_chapter.php') !== false || 
          strpos($_SERVER['PHP_SELF'], '/test/delete_chapter.php') !== false): ?>
<link rel="stylesheet" href="<?= $config['base_url'] ?>/admin/assets/css/chapter_management.css">
<?php endif; ?>
    <!-- Φόρτωση τυχόν επιπλέον CSS που έχει οριστεί στις σελίδες -->
<?= $additional_css ?? '' ?>
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
                    <a href="<?= $config['base_url'] ?>/admin/dashboard.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">📊</i>Αρχική
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/users.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">👥</i>Χρήστες
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/admin_subscriptions.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'admin_subscriptions.php') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">💰</i>Συνδρομές
                    </a>
                </li>
                
                <!-- Dropdown για διαχείριση τεστ -->
                <li class="nav-item nav-dropdown">
                    <a href="#" class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], '/test/') !== false ? 'active' : '' ?>">
                        <i class="nav-icon">🧩</i>Διαχείριση Τεστ
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/manage_questions.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_questions.php') !== false ? 'active' : '' ?>">
                                <i class="nav-icon">❓</i>Ερωτήσεις
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/bulk_import.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'bulk_import.php') !== false ? 'active' : '' ?>">
                                <i class="nav-icon">📥</i>Μαζική Εισαγωγή
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/manage_subcategories.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_subcategories.php') !== false ? 'active' : '' ?>">
                                <i class="nav-icon">📑</i>Υποκατηγορίες
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/manage_chapters.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_chapters.php') !== false ? 'active' : '' ?>">
                                <i class="nav-icon">📚</i>Κεφάλαια
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/test_config.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'test_config.php') !== false ? 'active' : '' ?>">
                                <i class="nav-icon">⚙️</i>Ρυθμίσεις Τεστ
                            </a>
                        </li>
                        <li>
                            <a href="<?= $config['base_url'] ?>/admin/test/generate_test.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'generate_test.php') !== false ? 'active' : '' ?>">
                                <i class="nav-icon">🧩</i>Δημιουργία Τεστ
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a href="<?= $config['base_url'] ?>/admin/settings.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : '' ?>">
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

<script>
// JavaScript για το responsive navbar και τα dropdowns
document.addEventListener('DOMContentLoaded', function() {
    // Toggle για το navbar στις κινητές συσκευές
    const navbarToggler = document.getElementById('navbar-toggler');
    const navbarMenu = document.getElementById('navbar-menu');
    
    if (navbarToggler) {
        navbarToggler.addEventListener('click', function() {
            navbarMenu.classList.toggle('active');
        });
    }
    
    // Διαχείριση των dropdowns
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.nav-dropdown');
            const menu = parent.querySelector('.dropdown-menu');
            
            // Κλείσιμο όλων των άλλων dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(dropdownMenu => {
                if (dropdownMenu !== menu) {
                    dropdownMenu.classList.remove('show');
                }
            });
            
            // Toggle του τρέχοντος dropdown
            menu.classList.toggle('show');
        });
    });
    
    // Κλείσιμο των dropdowns όταν κάνουμε κλικ έξω από αυτά
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    // Κλείσιμο του mobile menu όταν κάνουμε κλικ σε ένα link
    const navLinks = document.querySelectorAll('.navbar-menu .nav-link:not(.dropdown-toggle)');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                navbarMenu.classList.remove('active');
            }
        });
    });
});
</script>

<!-- Εδώ θα μπει το περιεχόμενο της κάθε σελίδας -->