<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once $config['includes_path'] . '/db_connection.php';
require_once $config['includes_path'] . '/user_auth.php';

// Τίτλος Σελίδας
$page_title = "Πίνακας Ελέγχου Μαθητή";

// Φόρτωση Header
require_once $config['includes_path'] . '/header.php';

// Ελέγχουμε αν υπάρχει παράμετρος id ή view για προβολή/επεξεργασία
$user_id = $_SESSION['user_id'];
$view_student_id = isset($_GET['view']) ? intval($_GET['view']) : (isset($_GET['id']) ? intval($_GET['id']) : $user_id);

if ($view_student_id != $user_id) {
    // Ανάκτηση δεδομένων του μαθητή που θέλουμε να δούμε/επεξεργαστούμε
    $query = "SELECT fullname FROM users WHERE id = ? AND role = 'student'";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare student query for user_id $view_student_id: " . $mysqli->error);
        header("Location: " . BASE_URL . "/public/login.php?error=query_failed");
        exit();
    }
    $stmt->bind_param("i", $view_student_id);
    $stmt->execute();
    $stmt->bind_result($fullname);
    $stmt->fetch();
    $stmt->close();

    if (!$fullname) {
        error_log("Student not found for ID: $view_student_id");
        header("Location: " . BASE_URL . "/public/login.php?error=student_not_found");
        exit();
    }
} else {
    // Ανάκτηση πληροφοριών του μαθητή
    $query = "SELECT fullname FROM users WHERE id = ? AND role = 'student'";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare student query for user_id $user_id: " . $mysqli->error);
        header("Location: " . BASE_URL . "/public/login.php?error=query_failed");
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($fullname);
    $stmt->fetch();
    $stmt->close();
}
?>

<main class="container">
    <h1>Προβολή/Επεξεργασία Μαθητή: <?= htmlspecialchars($fullname) ?>!</h1>
    <p>Αυτά είναι τα στοιχεία του μαθητή.</p>

    <section class="dashboard-section">
        <h2><i class="fas fa-star"></i> Συνδρομές & Πρόσβαση</h2>
        <p>Δεν έχεις ενεργή συνδρομή αυτή τη στιγμή.</p>
        <a href="<?= $config['base_url'] ?>/subscriptions/buy.php" class="btn-primary">Αγορά Συνδρομής</a>
    </section>

    <section class="dashboard-section">
        <h2><i class="fas fa-book"></i> Πρόσβαση στα Τεστ</h2>
        <a href="<?= $config['base_url'] ?>/tests/start.php" class="btn-secondary">Ξεκίνα ένα Τεστ</a>
    </section>

    <section class="dashboard-section">
        <h2><i class="fas fa-cog"></i> Ρυθμίσεις Λογαριασμού</h2>
        <a href="<?= $config['base_url'] ?>/profile.php" class="btn-secondary">Το Προφίλ μου</a>
        <a href="<?= $config['base_url'] ?>/logout.php" class="btn-danger">Αποσύνδεση</a>
        <a href="<?= $config['base_url'] ?>/admin/users.php" class="btn-primary">Επιστροφή</a>
    </section>
</main>

<?php
// Φόρτωση Footer
require_once $config['includes_path'] . '/footer.php';
?>