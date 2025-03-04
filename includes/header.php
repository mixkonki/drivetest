<?php
// Έλεγχος αν το session έχει ήδη ξεκινήσει, αν όχι το ξεκινάμε
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Φόρτωση του config μόνο αν δεν έχει ήδη φορτωθεί
if (!defined('BASE_URL')) {
    require_once('../config/config.php');
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DriveTest</title>
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/header.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/forms.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (isset($load_test_css) && $load_test_css): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/test.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

<header>
    <div class="logo">
        <a href="<?= BASE_URL ?>/public/index.php">
            <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest">
        </a>
    </div>
    <nav>
        <ul>
            <li><a href="<?= BASE_URL ?>/public/index.php">Αρχική</a></li>
            <li><a href="<?= BASE_URL ?>/public/about.php">Σχετικά</a></li>
            <li><a href="<?= BASE_URL ?>/public/contact.php">Επικοινωνία</a></li>
            <li><a href="<?= BASE_URL ?>/public/login.php">Σύνδεση</a></li>
            <?php if (isset($_SESSION['user_id'])) : ?>
                <li><?php if (isset($_SESSION['user_id'])) : ?>
    <?php if (isset($_SESSION['role'])): ?>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Πίνακας Ελέγχου</a></li>
        <?php elseif ($_SESSION['role'] === 'user'): ?>
            <li><a href="<?= BASE_URL ?>/users/dashboard.php">Πίνακας Ελέγχου</a></li>
        <?php elseif ($_SESSION['role'] === 'school'): ?>
            <li><a href="<?= BASE_URL ?>/schools/dashboard.php">Πίνακας Ελέγχου</a></li>
        <?php elseif ($_SESSION['role'] === 'student'): ?>
            <li><a href="<?= BASE_URL ?>/students/dashboard.php">Πίνακας Ελέγχου</a></li>
        <?php endif; ?>
    <?php else: ?>
        <li><a href="<?= BASE_URL ?>/public/login.php">Πίνακας Ελέγχου</a></li>
    <?php endif; ?>
    <li><a href="<?= BASE_URL ?>/admin/logout.php" class="logout-btn">Αποσύνδεση</a></li>
<?php else : ?>
    <li><a href="<?= BASE_URL ?>/public/login.php" class="login-btn">Είσοδος</a></li>
<?php endif; ?></li>
       
            <?php endif; ?>
        </ul>
    </nav>
    <script async src="https://www.googletagmanager.com/gtag/js?id=YOUR_GA_TRACKING_ID"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'YOUR_GA_TRACKING_ID');
</script>
</header>
