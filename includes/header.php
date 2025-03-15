<?php
// Συμπερίλαβε το config
require_once dirname(__DIR__) . '/config/config.php';
// Έλεγχος αν η μεταβλητή $page_title έχει οριστεί, αλλιώς χρήση προεπιλεγμένου τίτλου
$page_title = isset($page_title) ? $page_title . ' - DriveTest' : 'DriveTest - Πλατφόρμα Θεωρητικών Τεστ Οδήγησης';
// Έλεγχος για ειδικά CSS
$additional_css = isset($additional_css) ? $additional_css : '';
$load_test_css = isset($load_test_css) && $load_test_css ? true : false;


// Φόρτωση των βοηθητικών συναρτήσεων χρήστη
require_once dirname(__FILE__) . '/user_functions.php';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    
    <!-- Φόρτωση όλων των CSS -->
    <?php require_once dirname(__FILE__) . '/styles.php'; ?>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
 <!-- Google Maps API -->
<?php if (isset($load_map_js) && $load_map_js === true): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['google_maps_api_key'] ?>&libraries=places"></script>
<script src="<?= BASE_URL ?>/assets/js/maps.js"></script>
<?php endif; ?>
    
    <!-- PWA Support -->
    <link rel="manifest" href="<?= BASE_URL ?>/public/manifest.json">
    <meta name="theme-color" content="#aa3636">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="DriveTest - Η καλύτερη πλατφόρμα προετοιμασίας για θεωρητικές εξετάσεις οδήγησης">
    <meta property="og:image" content="<?= BASE_URL ?>/assets/images/drivetest.png">
    <meta property="og:url" content="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>">
</head>
<body>
    <!-- Κύρια Navigation Bar -->
    <header class="main-header">
        <div class="container">
            <nav class="navbar">
                <div class="navbar-brand">
                    <a href="<?= BASE_URL ?>/public/index.php">
                        <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest Logo" class="logo">
                    </a>
                </div>
                
                <button class="navbar-toggler" id="navbar-toggler" aria-label="Άνοιγμα μενού">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="navbar-menu" id="navbar-menu">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>/public/index.php" class="nav-link">
                                <i class="fas fa-home"></i> Αρχική
                            </a>
                        </li>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['role'] === 'user'): ?>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/users/dashboard.php" class="nav-link">
                                        <i class="fas fa-tachometer-alt"></i> Πίνακας Ελέγχου
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/test/start.php" class="nav-link">
                                        <i class="fas fa-tasks"></i> Τεστ
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/subscriptions/buy.php" class="nav-link">
                                        <i class="fas fa-credit-card"></i> Συνδρομές
                                    </a>
                                </li>
                            <?php elseif ($_SESSION['role'] === 'school'): ?>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/schools/dashboard.php" class="nav-link">
                                        <i class="fas fa-tachometer-alt"></i> Πίνακας Ελέγχου
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/schools/manage_students.php" class="nav-link">
                                        <i class="fas fa-user-graduate"></i> Μαθητές
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/schools/manage_student_requests.php" class="nav-link">
                                        <i class="fas fa-user-plus"></i> Αιτήματα
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/subscriptions/school_subscriptions.php" class="nav-link">
                                        <i class="fas fa-credit-card"></i> Συνδρομές
                                    </a>
                                </li>
                            <?php elseif ($_SESSION['role'] === 'student'): ?>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/students/dashboard.php" class="nav-link">
                                        <i class="fas fa-tachometer-alt"></i> Πίνακας Ελέγχου
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/test/start.php" class="nav-link">
                                        <i class="fas fa-tasks"></i> Τεστ
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/test/history.php" class="nav-link">
                                        <i class="fas fa-history"></i> Ιστορικό
                                    </a>
                                </li>
                            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                <li class="nav-item">
                                    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link">
                                        <i class="fas fa-tachometer-alt"></i> Διαχείριση
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <li class="nav-item dropdown">
                                <a href="#" class="nav-link dropdown-toggle" id="user-dropdown" data-toggle="dropdown">
                                    <i class="fas fa-user-circle"></i> 
                                    <?= htmlspecialchars(isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Λογαριασμός') ?>
                                </a>
                                <div class="dropdown-menu" aria-labelledby="user-dropdown">
                                    <?php if ($_SESSION['role'] === 'user'): ?>
                                        <a href="<?= BASE_URL ?>/users/user_profile.php" class="dropdown-item">
                                            <i class="fas fa-id-card"></i> Το προφίλ μου
                                        </a>
                                        <a href="<?= BASE_URL ?>/users/user_subscriptions.php" class="dropdown-item">
                                            <i class="fas fa-credit-card"></i> Οι συνδρομές μου
                                        </a>
                                    <?php elseif ($_SESSION['role'] === 'school'): ?>
                                        <a href="<?= BASE_URL ?>/schools/school_profile.php" class="dropdown-item">
                                            <i class="fas fa-id-card"></i> Το προφίλ μου
                                        </a>
                                    <?php elseif ($_SESSION['role'] === 'student'): ?>
                                        <a href="<?= BASE_URL ?>/students/dashboard.php?id=<?= $_SESSION['user_id'] ?>" class="dropdown-item">
                                            <i class="fas fa-id-card"></i> Το προφίλ μου
                                        </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="<?= BASE_URL ?>/public/logout.php" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt"></i> Αποσύνδεση
                                    </a>
                                </div>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>/public/login.php" class="nav-link">
                                    <i class="fas fa-sign-in-alt"></i> Σύνδεση
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a href="#" class="nav-link dropdown-toggle" id="register-dropdown" data-toggle="dropdown">
                                    <i class="fas fa-user-plus"></i> Εγγραφή
                                </a>
                                <div class="dropdown-menu" aria-labelledby="register-dropdown">
                                    <a href="<?= BASE_URL ?>/public/register_user.php" class="dropdown-item">
                                        <i class="fas fa-user"></i> Εγγραφή Χρήστη
                                    </a>
                                    <a href="<?= BASE_URL ?>/public/register_school.php" class="dropdown-item">
                                        <i class="fas fa-school"></i> Εγγραφή Σχολής
                                    </a>
                                </div>
                            </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>/public/about.php" class="nav-link">
                                <i class="fas fa-info-circle"></i> Σχετικά
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>/public/contact.php" class="nav-link">
                                <i class="fas fa-envelope"></i> Επικοινωνία
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Περιοχή μηνυμάτων (alerts) -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert-container">
        <div class="container">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="close-alert" aria-label="Κλείσιμο">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert-container">
        <div class="container">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close-alert" aria-label="Κλείσιμο">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Κύριο περιεχόμενο, κάθε σελίδα θα προσθέτει το δικό της περιεχόμενο μετά από αυτό -->
    <main role="main">