<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php'; // Προσθήκη για έλεγχο ρόλου
require_once 'includes/admin_header.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $months = intval($_POST['months']);

    if ($months <= 0 || $months > 12) {
        echo "<p class='error-message'>🚨 Σφάλμα: Η διάρκεια πρέπει να είναι μεταξύ 1 και 12 μηνών.</p>";
    } else {
        // Εισαγωγή στη βάση δεδομένων
        $query = "INSERT INTO subscription_durations (months) VALUES (?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $months);

        if ($stmt->execute()) {
            header("Location: admin_subscriptions.php?success=added");
            exit();
        } else {
            echo "<p class='error-message'>🚨 Σφάλμα κατά την αποθήκευση: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}
?>

<main class="admin-container">
    <h2 class="admin-title">➕ Προσθήκη Διάρκειας Συνδρομής</h2>

    <form method="POST" class="admin-form">
        <div class="form-group">
            <label for="months">Μήνες:</label>
            <input type="number" name="months" id="months" required min="1" max="12">
        </div>

        <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
    </form>

    <a href="admin_subscriptions.php" class="btn-secondary">🔙 Επιστροφή</a>
</main>

<?php require_once 'includes/admin_footer.php'; ?>