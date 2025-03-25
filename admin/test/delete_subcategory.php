<?php
// Ξεκινάμε το output buffering για την αποφυγή του "headers already sent" error
ob_start();

require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Έλεγχος αν έχει οριστεί έγκυρο ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = '🚨 Σφάλμα: Μη έγκυρο ID υποκατηγορίας.';
    log_debug("Invalid subcategory ID provided: " . ($_GET['id'] ?? 'none'));
    
    // Ανακατεύθυνση στη σελίδα διαχείρισης υποκατηγοριών με μήνυμα σφάλματος
    header("Location: manage_subcategories.php?error=" . urlencode($error_message));
    exit();
}

$subcategory_id = intval($_GET['id']);

// Ανάκτηση των στοιχείων της υποκατηγορίας για εμφάνιση
$query = "SELECT s.id, s.name, s.description, s.icon, tc.name as category_name 
          FROM test_subcategories s
          LEFT JOIN test_categories tc ON s.test_category_id = tc.id
          WHERE s.id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $subcategory_id);
$stmt->execute();
$result = $stmt->get_result();
$subcategory = $result->fetch_assoc();
$stmt->close();

if (!$subcategory) {
    $error_message = '🚨 Σφάλμα: Η υποκατηγορία δεν βρέθηκε.';
    log_debug("Subcategory with ID $subcategory_id not found");
    header("Location: manage_subcategories.php?error=" . urlencode($error_message));
    exit();
}

// Επιβεβαίωση διαγραφής
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    log_debug("Confirming deletion of subcategory ID: $subcategory_id");
    
    // Έλεγχος αν υπάρχουν κεφάλαια που χρησιμοποιούν αυτή την υποκατηγορία
    $check_chapters_query = "SELECT COUNT(*) as count FROM test_chapters WHERE subcategory_id = ?";
    $stmt_check = $mysqli->prepare($check_chapters_query);
    $stmt_check->bind_param("i", $subcategory_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['count'] > 0) {
        log_debug("Cannot delete subcategory ID $subcategory_id: Chapters exist for this subcategory");
        
        // Φόρτωση του header για εμφάνιση του σφάλματος
        require_once '../includes/admin_header.php';
        echo "<div class='admin-container'>";
        echo "<p class='error-message'>🚨 Σφάλμα: Δεν μπορείτε να διαγράψετε αυτήν την υποκατηγορία καθώς υπάρχουν κεφάλαια που την χρησιμοποιούν!</p>";
        echo "<div class='form-actions'>";
        echo "<a href='manage_subcategories.php' class='btn-secondary'><i class='action-icon'>🔙</i> Επιστροφή</a>";
        echo "</div>";
        echo "</div>";
        
        require_once '../includes/admin_footer.php';
        ob_end_flush();
        exit();
    }

    // Διαγραφή από τον πίνακα test_subcategories
    $delete_subcategory_query = "DELETE FROM test_subcategories WHERE id = ?";
    $stmt = $mysqli->prepare($delete_subcategory_query);
    $stmt->bind_param("i", $subcategory_id);

    if ($stmt->execute()) {
        log_debug("Subcategory ID $subcategory_id deleted successfully");
        
        // Ανακατεύθυνση στη σελίδα διαχείρισης υποκατηγοριών με μήνυμα επιτυχίας
        header("Location: manage_subcategories.php?success=deleted");
        $stmt->close();
        exit();
    } else {
        log_debug("SQL error deleting subcategory $subcategory_id: " . $stmt->error);
        
        // Φόρτωση του header για εμφάνιση του σφάλματος
        require_once '../includes/admin_header.php';
        echo "<div class='admin-container'>";
        echo "<p class='error-message'>🚨 Σφάλμα κατά τη διαγραφή: " . $stmt->error . "</p>";
        echo "<div class='form-actions'>";
        echo "<a href='manage_subcategories.php' class='btn-secondary'><i class='action-icon'>🔙</i> Επιστροφή</a>";
        echo "</div>";
        echo "</div>";
        
        require_once '../includes/admin_footer.php';
        $stmt->close();
        ob_end_flush();
        exit();
    }
} else {
    // Φόρτωση του header για την σελίδα επιβεβαίωσης
    require_once '../includes/admin_header.php';
    
    // Προετοιμασία της εικόνας της υποκατηγορίας
    $icon_path = '';
    if (!empty($subcategory['icon'])) {
        if (strpos($subcategory['icon'], 'http://') === 0 || strpos($subcategory['icon'], 'https://') === 0) {
            $icon_path = $subcategory['icon'];
        } else {
            $icon_path = BASE_URL . '/assets/images/' . $subcategory['icon'];
        }
    } else {
        $icon_path = BASE_URL . '/assets/images/default.png';
    }
    
    // Εμφάνιση επιβεβαίωσης διαγραφής με τα στοιχεία της υποκατηγορίας
    ?>
    <main class="admin-container" role="main" aria-label="Επιβεβαίωση Διαγραφής Υποκατηγορίας">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <h2 class="admin-title">⚠️ Επιβεβαίωση Διαγραφής</h2>
                <div class="confirmation-icon">
                    <span class="warning-icon">❗</span>
                </div>
            </div>
            
            <div class="confirmation-content">
                <div class="category-preview">
                    <div class="category-icon-container">
                        <img src="<?= htmlspecialchars($icon_path) ?>" alt="Εικονίδιο <?= htmlspecialchars($subcategory['name']) ?>" class="category-icon">
                    </div>
                    <div class="category-details">
                        <h3 class="category-name"><?= htmlspecialchars($subcategory['name']) ?></h3>
                        <div class="category-parent"><?= htmlspecialchars($subcategory['category_name']) ?></div>
                    </div>
                </div>
                
                <p class="confirmation-message">
                    Πρόκειται να διαγράψετε την υποκατηγορία <strong><?= htmlspecialchars($subcategory['name']) ?></strong>.
                </p>
                <p class="confirmation-warning">
                    Η ενέργεια αυτή δεν μπορεί να αναιρεθεί. Αν υπάρχουν κεφάλαια που χρησιμοποιούν αυτή την υποκατηγορία, η διαγραφή δεν θα επιτραπεί.
                </p>
                <div class="confirmation-question">
                    Είστε σίγουροι ότι θέλετε να προχωρήσετε;
                </div>
            </div>
            
            <div class="confirmation-actions">
                <a href="delete_subcategory.php?id=<?= $subcategory_id ?>&confirm=yes" class="btn-danger" aria-label="Επιβεβαίωση Διαγραφής Υποκατηγορίας">
                    <i class="action-icon">🗑️</i> Ναι, Διαγραφή
                </a>
                <a href="manage_subcategories.php" class="btn-secondary" aria-label="Επιστροφή χωρίς Διαγραφή">
                    <i class="action-icon">🔙</i> Όχι, Επιστροφή
                </a>
            </div>
        </div>
    </main>

    <!-- Χρησιμοποιούμε τα CSS από το admin_header.php -->
    <!-- Τα στυλ για τη σελίδα επιβεβαίωσης είναι ήδη μέρος του admin_unified.css -->
    <?php
    require_once '../includes/admin_footer.php';
    ob_end_flush();
}
?>