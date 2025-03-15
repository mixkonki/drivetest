<?php
// public/index.php
require_once('../config/config.php');
require_once('../includes/db_connection.php');


// Προσθήκη του CSS της αρχικής σελίδας

$page_title = "Αρχική - DriveTest";
$load_home_css = true;

require_once('../includes/header.php');
?>

<main>
    <section class="welcome">
   <div>
     <h1>Καλώς ήρθατε στο DriveTest</h1>
        <h2>Την ψηφιακή πλατφόρμα προετοιμασίας για όλες τις θεωρητικές εξετάσεις στον τομέα των μεταφορών</h2>
            <p>Υποψήφιοι οδηγοί, απόκτηση ή ανανέωση ADR, Απόκτηση ΠΕΕ, χειριστές μηχανημάτων έργου.</p>
        </div>
    </section>

    <section class="buttons">
        <div class="container">
            <a href="register_user.php" class="btn-primary" aria-label="Εγγραφή Χρήστη">
                <img src="<?php echo BASE_URL; ?>/assets/images/user_icon.png" alt="Εικονίδιο χρήστη">
                Εγγραφή Χρήστη
            </a>
            <a href="register_school.php" class="btn-primary" aria-label="Εγγραφή Σχολής">
                <img src="<?php echo BASE_URL; ?>/assets/images/company_icon.png" alt="Εικονίδιο Σχολής">
                Εγγραφή Σχολής
            </a>
        </div>
    </section>
    
    <section class="features">
        <div class="container">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Θεωρητικά Τεστ</h3>
                <p>Εξασκηθείτε με χιλιάδες ερωτήσεις από την επίσημη τράπεζα θεμάτων του Υπουργείου Μεταφορών.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Παρακολούθηση Προόδου</h3>
                <p>Δείτε αναλυτικά στατιστικά για την πρόοδό σας και εντοπίστε τα σημεία που χρειάζονται βελτίωση.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Για Σχολές</h3>
                <p>Ειδικές λειτουργίες για σχολές οδηγών με δυνατότητα παρακολούθησης της προόδου των μαθητών.</p>
            </div>
        </div>
    </section>

    <section class="software-section">
        <div>
            <h2>Λογισμικό Διαχείρισης Ανθρώπινου Δυναμικού</h2>
            <p>Το DriveJobs προσφέρει λογισμικό για τη διαχείριση πληρωμών, αδειών και πιστοποιήσεων. Αξιοποιήστε τη δύναμη των εργαλείων μας για να εξοικονομήσετε χρόνο και να αυξήσετε την παραγωγικότητα.</p>
            <a href="#" class="btn-secondary">Μάθετε περισσότερα</a>
        </div>
    </section>
</main>

<?php require_once('../includes/footer.php'); ?>