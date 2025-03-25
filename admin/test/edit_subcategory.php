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
            // Διαχείριση ανεβάσματος εικόνας
            $icon = $subcategory['icon']; // Διατήρηση του υπάρχοντος εικονιδίου εξ ορισμού
            
            // Έλεγχος αν έχει οριστεί νέο URL εικονιδίου
            if (isset($_POST['icon']) && !empty($_POST['icon'])) {
                $icon = trim($_POST['icon']);
                log_debug("Icon URL set: $icon");
            }
            
            // Έλεγχος αν υπάρχει ανεβασμένο αρχείο
            if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = BASE_PATH . '/assets/images/categories/';
                
                // Έλεγχος αν υπάρχει ο φάκελος και αν όχι, δημιουργία του
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                    log_debug("Created directory: $upload_dir");
                }
                
                // Καθαρισμός και δημιουργία ασφαλούς ονόματος αρχείου
                $filename = basename($_FILES['icon_file']['name']);
                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'svg');
                
                if (in_array($file_ext, $allowed_exts)) {
                    // Δημιουργία μοναδικού ονόματος αρχείου
                    $new_filename = 'subcategory_' . $subcategory_id . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['icon_file']['tmp_name'], $upload_path)) {
                        $icon = 'categories/' . $new_filename;
                        log_debug("File uploaded successfully: $icon");
                    } else {
                        log_debug("Upload failed: " . $_FILES['icon_file']['error']);
                    }
                } else {
                    log_debug("Invalid file extension: $file_ext");
                    $error_message = '🚨 Σφάλμα: Μη αποδεκτός τύπος αρχείου. Επιτρέπονται μόνο jpg, jpeg, png, gif, svg.';
                }
            }
            
            if (!isset($error_message)) {
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
}

// Προετοιμασία μιας μεταβλητής για επιπλέον CSS
$additional_css = '<link rel="stylesheet" href="' . $config['base_url'] . '/admin/assets/css/subcategory_upload.css">';

// Φορτώνουμε το header μετά το logging και τους ελέγχους
require_once '../includes/admin_header.php';

// Προετοιμασία URL προεπισκόπησης εικόνας
$icon_preview = '';
if (!empty($subcategory['icon'])) {
    if (strpos($subcategory['icon'], 'http://') === 0 || strpos($subcategory['icon'], 'https://') === 0) {
        $icon_preview = $subcategory['icon'];
    } else {
        $icon_preview = $config['base_url'] . '/assets/images/' . $subcategory['icon'];
    }
} else {
    $icon_preview = $config['base_url'] . '/assets/images/default.png';
}
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
        <form method="POST" action="edit_subcategory.php?id=<?= $subcategory_id ?>" enctype="multipart/form-data">
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
                <label>Εικονίδιο:</label>
                
                <div class="icon-tabs">
                    <div class="tab-buttons">
                        <button type="button" id="upload-tab-btn" class="tab-btn active">Ανέβασμα Αρχείου</button>
                        <button type="button" id="url-tab-btn" class="tab-btn">URL Εικόνας</button>
                    </div>
                    
                    <div id="upload-tab" class="tab-content">
                        <div id="icon-preview" class="preview-container">
                            <img id="preview-image" src="<?= htmlspecialchars($icon_preview) ?>" alt="Προεπισκόπηση εικονιδίου">
                        </div>
                        <div class="upload-controls">
                            <input type="file" id="icon_file" name="icon_file" accept="image/*" class="file-input">
                            <label for="icon_file" class="upload-btn">Επιλογή Εικόνας</label>
                        </div>
                    </div>
                    
                    <div id="url-tab" class="tab-content hidden">
                        <input type="text" id="icon" name="icon" value="<?= htmlspecialchars($subcategory['icon'] ?? '') ?>" class="form-control" placeholder="Εισάγετε URL ή όνομα αρχείου">
                        <div class="url-preview">
                            <img id="icon-url-preview" src="<?= htmlspecialchars($icon_preview) ?>" alt="Προεπισκόπηση εικονιδίου">
                        </div>
                        <small class="help-text">Μπορείτε να εισάγετε πλήρες URL (http://...) ή σχετική διαδρομή (categories/icon.png)</small>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="action-icon">💾</i> Αποθήκευση</button>
                <a href="manage_subcategories.php" class="btn-secondary"><i class="action-icon">❌</i> Ακύρωση</a>
            </div>
        </form>
    </div>
</main>

<!-- Φόρτωση του JavaScript για το ανέβασμα εικόνας -->
<script src="<?= $config['base_url'] ?>/admin/assets/js/subcategory_upload.js"></script>

<?php 
require_once '../includes/admin_footer.php';
// Κλείσιμο του output buffer
ob_end_flush();
?>