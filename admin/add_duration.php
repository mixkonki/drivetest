<?php
// Ξεκινάμε το output buffering για την αποφυγή του "headers already sent" error
ob_start();

require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php'; // Προσθήκη για έλεγχο ρόλου

$load_form_common_css = true;

require_once 'includes/admin_header.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Αρχικοποίηση μεταβλητών για την φόρμα
$error_message = '';
$success_message = '';
$form_data = [
    'months' => ''
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $months = intval($_POST['months']);
    $form_data['months'] = $months;

    log_debug("Attempting to add duration - Months: $months");

    if ($months <= 0 || $months > 12) {
        $error_message = '🚨 Σφάλμα: Η διάρκεια πρέπει να είναι μεταξύ 1 και 12 μηνών.';
        log_debug("Validation failed: Months out of range: $months");
    } else {
        // Έλεγχος αν υπάρχει ήδη η διάρκεια
        $check_query = "SELECT COUNT(*) as count FROM subscription_durations WHERE months = ?";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("i", $months);
        $stmt_check->execute();
        $result = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result['count'] > 0) {
            $error_message = '🚨 Σφάλμα: Η διάρκεια ' . $months . ' μηνών υπάρχει ήδη!';
            log_debug("Duplicate duration value: $months months");
        } else {
            // Εισαγωγή στη βάση δεδομένων
            $query = "INSERT INTO subscription_durations (months) VALUES (?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("i", $months);

            if ($stmt->execute()) {
                $new_duration_id = $stmt->insert_id;
                log_debug("Duration added successfully with ID: $new_duration_id");
                $success_message = "Η διάρκεια $months μηνών προστέθηκε επιτυχώς.";
                
                // Ανακατεύθυνση στη σελίδα διαχείρισης συνδρομών
                header("Location: admin_subscriptions.php?success=added");
                exit();
            } else {
                $error_message = '🚨 Σφάλμα κατά την αποθήκευση: ' . $stmt->error;
                log_debug("SQL error adding duration: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}
?>

<main class="admin-container">
    <h2 class="admin-title">➕ Προσθήκη Διάρκειας Συνδρομής</h2>
    
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

    <div class="subscription-form-container">
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="months">
                    <i class="duration-icon">📅</i> Διάρκεια σε Μήνες:
                </label>
                <div class="duration-slider-container">
                    <input type="range" id="months-slider" min="1" max="12" value="<?= htmlspecialchars($form_data['months'] ?: '1') ?>" class="duration-slider">
                    <div class="duration-value-container">
                        <input type="number" name="months" id="months" min="1" max="12" value="<?= htmlspecialchars($form_data['months'] ?: '1') ?>" required class="form-control duration-input">
                        <span class="duration-unit">μήνες</span>
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
                        Διάρκεια: <strong id="duration-text">1 μήνας</strong>
                    </div>
                </div>
                <small class="form-text help-text">Επιλέξτε διάρκεια από 1 έως 12 μήνες</small>
            </div>
            
            <div class="duration-info-box">
                <h4>📝 Οδηγίες</h4>
                <p>Οι διάρκειες συνδρομών χρησιμοποιούνται σε συνδυασμό με τις κατηγορίες για τον καθορισμό του συνολικού κόστους των πακέτων συνδρομής.</p>
                <p>Παραδείγματα διαρκειών:</p>
                <ul>
                    <li><strong>1 μήνας</strong>: Μηνιαία συνδρομή</li>
                    <li><strong>3 μήνες</strong>: Τριμηνιαία συνδρομή</li>
                    <li><strong>6 μήνες</strong>: Εξαμηνιαία συνδρομή</li>
                    <li><strong>12 μήνες</strong>: Ετήσια συνδρομή</li>
                </ul>
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
</main>

<?php 
require_once 'includes/admin_footer.php';
// Κλείσιμο του output buffer
ob_end_flush();
?>