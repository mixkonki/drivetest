<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php'; // Προσθήκη για έλεγχο ρόλου
require_once 'includes/admin_header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<p class='error-message'>🚨 Σφάλμα: Μη έγκυρο ID διάρκειας.</p>");
}

$duration_id = intval($_GET['id']);

// Ανάκτηση των στοιχείων της διάρκειας
$query = "SELECT months FROM subscription_durations WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $duration_id);
$stmt->execute();
$result = $stmt->get_result();
$duration = $result->fetch_assoc();
$stmt->close();

if (!$duration) {
    die("<p class='error-message'>🚨 Σφάλμα: Η διάρκεια δεν βρέθηκε.</p>");
}

// Διαχείριση υποβολής φόρμας
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $months = intval($_POST['months']);

    if ($months <= 0 || $months > 12) {
        echo "<p class='error-message'>🚨 Σφάλμα: Η διάρκεια πρέπει να είναι μεταξύ 1 και 12 μηνών.</p>";
    } else {
        // Ενημέρωση της βάσης δεδομένων
        $query = "UPDATE subscription_durations SET months = ? WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ii", $months, $duration_id);

        if ($stmt->execute()) {
            header("Location: admin_subscriptions.php?success=updated");
            exit();
        } else {
            echo "<p class='error-message'>🚨 Σφάλμα κατά την ενημέρωση: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}
?>

<main class="admin-container">
    <h2 class="admin-title">✏️ Επεξεργασία Διάρκειας Συνδρομής</h2>

    <form method="POST" class="admin-form">
        <div class="form-group">
            <label for="months">Μήνες:</label>
            <input type="number" name="months" id="months" value="<?= htmlspecialchars($duration['months']) ?>" required min="1" max="12">
        </div>

        <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
    </form>

    <a href="admin_subscriptions.php" class="btn-secondary">🔙 Επιστροφή</a>
</main>

<?php require_once 'includes/admin_footer.php'; ?>