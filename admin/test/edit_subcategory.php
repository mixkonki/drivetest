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

// Καταγραφή ότι φορτώθηκε η σελίδα
log_debug("Σελίδα edit_subcategory.php φορτώθηκε");

// Έλεγχος αν έχει οριστεί έγκυρο ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = '🚨 Σφάλμα: Μη έγκυρο ID υποκατηγορίας.';
    log_debug("Invalid subcategory ID provided: " . ($_GET['id'] ?? 'none'));
    
    // Ανακατεύθυνση στη σελίδα διαχείρισης υποκατηγοριών με μήνυμα σφάλματος
    header("Location: manage_subcategories.php?error=" . urlencode($error_message));
    exit();
}

$subcategory_id = intval($_GET['id']);

// Ανάκτηση των στοιχείων της υποκατηγορίας
$query = "SELECT s.id, s.name, s.description, s.icon, s.test_category_id, tc.name as category_name 
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

// Ανάκτηση όλων των κατηγοριών για το dropdown
$categories_query = "SELECT id, name FROM test_categories ORDER BY name";
$categories_result = $mysqli->query($categories_query);
$categories = array();
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Διαχείριση υποβολής φόρμας
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    
    log_debug("Επεξεργασία υποκατηγορίας ID: $subcategory_id - Όνομα: $name, Κατηγορία: $category_id");
    
    // Έλεγχος υποχρεωτικών πεδίων
    if (empty($name) || empty($category_id)) {
        $error_message = '🚨 Σφάλμα: Τα πεδία Όνομα και Κατηγορία είναι υποχρεωτικά.';
        log_debug("Validation failed: Missing required fields");
    } else {
        // Έλεγχος αν υπάρχει ήδη υποκατηγορία με αυτό το όνομα στην ίδια κατηγορία (εκτός της τρέχουσας)
        $check_query = "SELECT COUNT(*) as count FROM test_subcategories WHERE name = ? AND test_category_id = ? AND id != ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("sii", $name, $category_id, $subcategory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($check_result['count'] > 0) {
            $error_message = '🚨 Σφάλμα: Υπάρχει ήδη υποκατηγορία με αυτό το όνομα στην επιλεγμένη κατηγορία!';
            log_debug("Duplicate subcategory name found for category ID: $category_id");
        } else {
            // Ενημέρωση της υποκατηγορίας
            $update_query = "UPDATE test_subcategories SET name = ?, description = ?, icon = ?, test_category_id = ? WHERE id = ?";
            $update_stmt = $mysqli->prepare($update_query);
            $update_stmt->bind_param("sssii", $name, $description, $icon, $category_id, $subcategory_id);
            
            if ($update_stmt->execute()) {
                log_debug("Subcategory ID: $subcategory_id updated successfully");
                
                // Ανακατεύθυνση στη σελίδα διαχείρισης υποκατηγοριών με μήνυμα επιτυχίας
                header("Location: manage_subcategories.php?success=updated");
                exit();
            } else {
                $error_message = '🚨 Σφάλμα κατά την ενημέρωση: ' . $update_stmt->error;
                log_debug("Error updating subcategory: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
    }
}

// Φορτώνουμε το header μετά το logging και τους ελέγχους
require_once '../includes/admin_header.php';
?>

<main class="admin-container">
    <div class="admin-section-header">
        <h1 class="admin-title">✏️ Επεξεργασία Υποκατηγορίας</h1>
        
        <div class="admin-actions">
            <a href="manage_subcategories.php" class="btn-secondary"><i class="action-icon">🔙</i> Επιστροφή</a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Σφάλμα!</strong> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <div class="subcategory-form">
        <form method="POST" action="edit_subcategory.php?id=<?= $subcategory_id ?>">
            <div class="form-group">
                <label for="name">Όνομα Υποκατηγορίας:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($subcategory['name']) ?>" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="category_id">Κατηγορία:</label>
                <select id="category_id" name="category_id" required class="form-control">
                    <option value="">-- Επιλέξτε Κατηγορία --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $subcategory['test_category_id'] == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Περιγραφή:</label>
                <textarea id="description" name="description" class="form-control"><?= htmlspecialchars($subcategory['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="icon">Εικονίδιο (URL ή όνομα αρχείου):</label>
                <input type="text" id="icon" name="icon" value="<?= htmlspecialchars($subcategory['icon'] ?? '') ?>" class="form-control">
                <?php if (!empty($subcategory['icon'])): ?>
                <div class="icon-preview">
                    <img src="<?= strpos($subcategory['icon'], 'http') === 0 ? $subcategory['icon'] : $config['base_url'] . '/assets/images/' . $subcategory['icon'] ?>" 
                         alt="Προεπισκόπηση εικονιδίου" width="50">
                    <span class="icon-preview-label">Τρέχον εικονίδιο</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="action-icon">💾</i> Αποθήκευση</button>
                <a href="manage_subcategories.php" class="btn-secondary"><i class="action-icon">❌</i> Ακύρωση</a>
            </div>
        </form>
    </div>
</main>

<?php 
require_once '../includes/admin_footer.php';
// Κλείσιμο του output buffer
ob_end_flush();
?>