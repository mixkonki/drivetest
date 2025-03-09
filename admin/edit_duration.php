<?php
// Ξεκινάμε το output buffering για την αποφυγή του "headers already sent" error
ob_start();

require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php'; // Προσθήκη για έλεγχο ρόλου

// Φόρτωση ειδικών CSS και JS για τη σελίδα
$additional_css = '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/category_form.css">';
$additional_js = '<script src="' . BASE_URL . '/admin/assets/js/duration_form.js"></script>';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Αρχικοποίηση μεταβλητών για την φόρμα
$error_message = '';
$success_message = '';
$duration = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = '🚨 Σφάλμα: Μη έγκυρο ID διάρκειας.';
    log_debug("Invalid duration ID provided: " . ($_GET['id'] ?? 'none'));
} else {
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
        $error_message = '🚨 Σφάλμα: Η διάρκεια δεν βρέθηκε.';
        log_debug("Duration with ID $duration_id not found");
    }

    // Διαχείριση υποβολής φόρμας
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $months = intval($_POST['months']);

        log_debug("Attempting to edit duration $duration_id - Months: $months");

        if ($months <= 0 || $months > 12) {
            $error_message = '🚨 Σφάλμα: Η διάρκεια πρέπει να είναι μεταξύ 1 και 12 μηνών.';
            log_debug("Validation failed: Months out of range: $months");
        } else {
            // Έλεγχος αν υπάρχει ήδη η διάρκεια (εκτός της τρέχουσας)
            $check_query = "SELECT COUNT(*) as count FROM subscription_durations WHERE months = ? AND id != ?";
            $stmt_check = $mysqli->prepare($check_query);
            $stmt_check->bind_param("ii", $months, $duration_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($result['count'] > 0) {
                $error_message = '🚨 Σφάλμα: Η διάρκεια ' . $months . ' μηνών υπάρχει ήδη!';
                log_debug("Duplicate duration value: $months months");
            } else {
                // Ενημέρωση της βάσης δεδομένων
                $query = "UPDATE subscription_durations SET months = ? WHERE id = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ii", $months, $duration_id);

                if ($stmt->execute()) {
                    log_debug("Duration $duration_id updated successfully");
                    $success_message = "Η διάρκεια ενημερώθηκε επιτυχώς.";
                    
                    // Ανακατεύθυνση στη σελίδα διαχείρισης συνδρομών
                    header("Location: admin_subscriptions.php?success=updated");
                    exit();
                } else {
                    $error_message = '🚨 Σφάλμα κατά την ενημέρωση: ' . $stmt->error;
                    log_debug("SQL error updating duration: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}

require_once 'includes/admin_header.php';
?>

<main class="admin-container">
    <h2 class="admin-title">✏️ Επεξεργασία Διάρκειας Συνδρομής</h2>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <strong>Επιτυχία!</strong> <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Σφάλμα!</strong> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($duration) && $duration): ?>
    <div class="subscription-form-container">
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="months">
                    <i class="duration-icon">📅</i> Διάρκεια σε Μήνες:
                </label>
                <div class="duration-slider-container">
                    <input type="range" id="months-slider" min="1" max="12" value="<?= htmlspecialchars($duration['months']) ?>" class="duration-slider">
                    <div class="duration-value-container">
                        <input type="number" name="months" id="months" min="1" max="12" value="<?= htmlspecialchars($duration['months']) ?>" required class="form-control duration-input">
                        <span class="duration-unit"><?= $duration['months'] == 1 ? 'μήνας' : 'μήνες' ?></span>
                    </div>
                </div>
                <div class="duration-visualization">
                    <div class="duration-scale">
                        <span>1</span>
                        <span>3</span>
                        <span>6</span>
                        <span>9</span>
                        <span>12</span>
                    </div>
                    <div class="duration-description" id="duration-description">
                        Διάρκεια: <strong id="duration-text"><?= $duration['months'] ?> <?= $duration['months'] == 1 ? 'μήνας' : 'μήνες' ?></strong>
                    </div>
                </div>
                <small class="form-text help-text">Επιλέξτε διάρκεια από 1 έως 12 μήνες</small>
            </div>
            
            <div class="duration-info-box">
                <h4>📝 Σημείωση</h4>
                <p>Η τροποποίηση της διάρκειας θα επηρεάσει όλες τις μελλοντικές συνδρομές. Υπάρχουσες συνδρομές που χρησιμοποιούν αυτή τη διάρκεια δεν θα επηρεαστούν.</p>
                <p>Η τιμή της κάθε συνδρομής υπολογίζεται με βάση την τιμή της κατηγορίας και τη διάρκεια.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="action-icon">💾</i> Αποθήκευση
                </button>
                <a href="admin_subscriptions.php" class="btn-secondary">
                    <i class="action-icon">🔙</i> Επιστροφή
                </a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="alert alert-danger">
        <strong>Σφάλμα!</strong> Η διάρκεια δεν βρέθηκε ή δεν προσδιορίστηκε έγκυρο ID.
    </div>
    <div class="form-actions">
        <a href="admin_subscriptions.php" class="btn-secondary">
            <i class="action-icon">🔙</i> Επιστροφή
        </a>
    </div>
    <?php endif; ?>
</main>

<?php 
require_once 'includes/admin_footer.php';
// Κλείσιμο του output buffer
ob_end_flush();
?>