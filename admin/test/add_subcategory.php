<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

$error_message = '';
$success_message = '';

// Ανάκτηση των κατηγοριών για το dropdown
$categories_query = "SELECT id, name FROM test_categories ORDER BY name";
$categories_result = $mysqli->query($categories_query);

// Επεξεργασία φόρμας
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Ανάκτηση και καθαρισμός δεδομένων
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');

    log_debug("Attempting to add subcategory - Name: $name, Category ID: $category_id");

    // Έλεγχος υποχρεωτικών πεδίων
    if (empty($name) || $category_id <= 0) {
        $error_message = '🚨 Σφάλμα: Συμπληρώστε όλα τα υποχρεωτικά πεδία (Όνομα, Κατηγορία).';
        log_debug("Validation failed: Missing required fields (name or category_id)");
    } else {
        // Έλεγχος αν υπάρχει ήδη η υποκατηγορία
        $check_query = "SELECT COUNT(*) as count FROM test_subcategories WHERE name = ? AND test_category_id = ?";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("si", $name, $category_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result['count'] > 0) {
            $error_message = '🚨 Σφάλμα: Η υποκατηγορία με αυτό το όνομα υπάρχει ήδη στην επιλεγμένη κατηγορία!';
            log_debug("Duplicate subcategory name: $name for category ID: $category_id");
        } else {
            // Διαχείριση εικόνας (από URL ή ανεβασμένο αρχείο)
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
                    // Το insert_id θα οριστεί μετά την αποθήκευση της υποκατηγορίας
                    // Γι' αυτό αποθηκεύουμε προσωρινά με ένα μοναδικό όνομα
                    $temp_filename = 'subcategory_temp_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                    $upload_path = $upload_dir . $temp_filename;
                    
                    if (move_uploaded_file($_FILES['icon_file']['tmp_name'], $upload_path)) {
                        $icon = 'categories/' . $temp_filename;
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
                // Εισαγωγή στη βάση δεδομένων
                $insert_query = "INSERT INTO test_subcategories (name, test_category_id, description, icon) VALUES (?, ?, ?, ?)";
                $stmt = $mysqli->prepare($insert_query);
                $stmt->bind_param("siss", $name, $category_id, $description, $icon);

                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    log_debug("Subcategory added successfully: $name, ID: $new_id");
                    
                    // Αν έχει ανεβεί εικόνα, μετονομασία του αρχείου με το ID της νέας υποκατηγορίας
                    if (!empty($icon) && strpos($icon, 'subcategory_temp_') !== false) {
                        $old_path = $upload_dir . basename($icon);
                        $new_filename = 'subcategory_' . $new_id . '_' . time() . '.' . $file_ext;
                        $new_path = $upload_dir . $new_filename;
                        
                        if (rename($old_path, $new_path)) {
                            $new_icon = 'categories/' . $new_filename;
                            
                            // Ενημέρωση του εικονιδίου στη βάση
                            $update_icon = "UPDATE test_subcategories SET icon = ? WHERE id = ?";
                            $stmt_update = $mysqli->prepare($update_icon);
                            $stmt_update->bind_param("si", $new_icon, $new_id);
                            $stmt_update->execute();
                            $stmt_update->close();
                            
                            log_debug("File renamed: $icon -> $new_icon");
                        }
                    }
                    
                    // Ανακατεύθυνση στη σελίδα διαχείρισης υποκατηγοριών με μήνυμα επιτυχίας
                    header("Location: manage_subcategories.php?success=added");
                    exit();
                } else {
                    $error_message = '🚨 Σφάλμα κατά την προσθήκη: ' . $stmt->error;
                    log_debug("SQL error inserting subcategory: " . $stmt->error);
                    
                    // Αν υπάρχει ανεβασμένο αρχείο, το διαγράφουμε
                    if (!empty($icon) && file_exists($upload_dir . basename($icon))) {
                        unlink($upload_dir . basename($icon));
                        log_debug("Deleted temporary file: " . $upload_dir . basename($icon));
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Προετοιμασία μιας μεταβλητής για επιπλέον CSS
$additional_css = '<link rel="stylesheet" href="' . $config['base_url'] . '/admin/assets/css/subcategory_upload.css">';

require_once '../includes/admin_header.php';
?>

<main class="admin-container">
    <div class="admin-section-header">
        <h1 class="admin-title">📂 Προσθήκη Νέας Υποκατηγορίας</h1>
        
        <div class="admin-actions">
            <a href="manage_subcategories.php" class="btn-secondary"><i class="action-icon">🔙</i> Επιστροφή</a>
        </div>
    </div>
    
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

    <div class="subcategory-form">
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-group">
                <label for="name">Όνομα Υποκατηγορίας:</label>
                <input type="text" name="name" id="name" value="<?= htmlspecialchars($name ?? '') ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label for="category_id">Κατηγορία:</label>
                <select name="category_id" id="category_id" required class="form-control">
                    <option value="">-- Επιλέξτε Κατηγορία --</option>
                    <?php $categories_result->data_seek(0); // Επαναφορά του δείκτη αποτελεσμάτων ?>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?= $category['id'] ?>" <?= (isset($category_id) && $category_id == $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Περιγραφή:</label>
                <textarea name="description" id="description" class="form-control"><?= htmlspecialchars($description ?? '') ?></textarea>
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
                            <img id="preview-image" src="<?= $config['base_url'] ?>/assets/images/default.png" alt="Προεπισκόπηση εικονιδίου">
                        </div>
                        <div class="upload-controls">
                            <input type="file" id="icon_file" name="icon_file" accept="image/*" class="file-input">
                            <label for="icon_file" class="upload-btn">Επιλογή Εικόνας</label>
                        </div>
                    </div>
                    
                    <div id="url-tab" class="tab-content hidden">
                        <input type="text" id="icon" name="icon" value="<?= htmlspecialchars($icon ?? '') ?>" class="form-control" placeholder="Εισάγετε URL ή όνομα αρχείου">
                        <div class="url-preview">
                            <img id="icon-url-preview" src="<?= $config['base_url'] ?>/assets/images/default.png" alt="Προεπισκόπηση εικονιδίου">
                        </div>
                        <small class="help-text">Μπορείτε να εισάγετε πλήρες URL (http://...) ή σχετική διαδρομή (categories/icon.png)</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
                <a href="manage_subcategories.php" class="btn-secondary">🔙 Επιστροφή</a>
            </div>
        </form>
    </div>
</main>

<!-- Φόρτωση του JavaScript για το ανέβασμα εικόνας -->
<script src="<?= $config['base_url'] ?>/admin/assets/js/subcategory_upload.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>