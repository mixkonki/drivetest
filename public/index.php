<?php
// public/index.php
$config = require_once('../config/config.php');
require_once('../includes/db_connection.php');
require_once('../includes/header.php');
?>

<main>
<section class="welcome">
        <h1>Καλώς ήρθατε στο DriveTest<br></h1>
        <h2>την ψηφιακή πλατφόρμα προετοιμασίας για όλες τις θεωρητικές εετάσεις στον τομέα των μεταφορών</h2>
        <p>Υποψήφιοι οδηγοί, απόκτηση ή ανανέωση ADR, Απόκτηση ΠΕΕ, χειριστές μηχανημάτων έργου.</p>
    </section>

    <section class="buttons">
        <a href="register_user.php" class="btn-secondary" aria-label="Εγγραφή Χρήστη">
        <img src="<?php echo BASE_URL; ?>/assets/images/user_icon.png" alt="Εικονίδιο χρήστη">
            Εγγραφή Χρήστη
        </a>
        <a href="register_school.php" class="btn-secondary" aria-label="Εγγραφή Σχολής">
        <img src="<?php echo BASE_URL; ?>/assets/images/company_icon.png" alt="Εικονίδιο Σχολής">
            Εγγραφή Σχολής
        </a>
    </section>
    <section class="welcome">
        <h1>Λογισμικό Διαχείρισης Ανθρώπινου Δυναμικού</h1>
        <p>Το DriveJobs προσφέρει λογισμικό για τη διαχείριση πληρωμών, αδειών και πιστοποιήσεων. Αξιοποιήστε τη δύναμη των εργαλείων μας για να εξοικονομήσετε χρόνο και να αυξήσετε την παραγωγικότητα.</p>
    </section>
</main>
<?php require_once('../includes/footer.php'); ?>
