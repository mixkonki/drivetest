<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

// 🔒 Έλεγχος αν ο χρήστης είναι διαχειριστής
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

// Προσθήκη του ειδικού CSS για τη διαχείριση κεφαλαίων
$additional_css = '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/chapter_management.css">';

// Λήψη κατηγοριών και υποκατηγοριών για το dropdown
$subcategories = [];
$query = "SELECT 
            sc.id AS subcategory_id, 
            sc.name AS subcategory_name, 
            tc.id AS category_id, 
            tc.name AS category_name 
          FROM test_subcategories sc
          JOIN test_categories tc ON sc.test_category_id = tc.id
          ORDER BY tc.name, sc.name ASC";
$result = $mysqli->query($query);
if ($result) {
    $subcategories = $result->fetch_all(MYSQLI_ASSOC);
}

require_once '../includes/admin_header.php';
?>

<main class="admin-container">
    <div class="admin-section-header">
        <h2 class="admin-title">📚 Προσθήκη Νέου Κεφαλαίου</h2>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <p><?= htmlspecialchars($_GET['success']) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <p><?= htmlspecialchars($_GET['error']) ?></p>
        </div>
    <?php endif; ?>

    <div class="chapter-form">
        <form action="chapter_actions.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            
            <div class="form-group">
                <label for="name">Όνομα Κεφαλαίου <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="subcategory_id">Υποκατηγορία <span class="required">*</span></label>
                <select id="subcategory_id" name="subcategory_id" class="form-control" required>
                    <option value="">-- Επιλέξτε Υποκατηγορία --</option>
                    <?php foreach($subcategories as $subcategory): ?>
                        <option value="<?= $subcategory['subcategory_id'] ?>">
                            <?= htmlspecialchars($subcategory['category_name']) ?> - <?= htmlspecialchars($subcategory['subcategory_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Περιγραφή</label>
                <textarea id="description" name="description" class="form-control" rows="4"></textarea>
            </div>
            
            <div class="form-group">
                <label>Εικονίδιο</label>
                <div class="icon-tabs">
                    <div class="tab-buttons">
                        <button type="button" id="upload-tab-btn" class="tab-btn active">Ανέβασμα Αρχείου</button>
                        <button type="button" id="url-tab-btn" class="tab-btn">URL Εικόνας</button>
                    </div>
                    
                    <div id="upload-tab" class="tab-content">
                        <div id="icon-preview" class="preview-container">
                            <img id="preview-image" src="" alt="Προεπισκόπηση Εικονιδίου" style="display: none;">
                        </div>
                        <div class="upload-controls">
                            <input type="file" id="icon_file" name="icon_file" class="file-input" accept="image/*">
                            <label for="icon_file" class="upload-btn">Επιλογή Εικόνας</label>
                        </div>
                    </div>
                    
                    <div id="url-tab" class="tab-content hidden">
                        <div class="url-preview-wrapper">
                            <img id="icon-url-preview" src="<?= BASE_URL ?>/assets/images/default.png" alt="Προεπισκόπηση URL">
                        </div>
                        <input type="text" id="icon" name="icon" class="form-control" placeholder="Εισάγετε URL εικόνας">
                        <span class="help-text">Εισάγετε πλήρες URL ή σχετική διαδρομή (π.χ. assets/images/icon.png)</span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
                <a href="manage_chapters.php" class="btn-secondary">❌ Ακύρωση</a>
            </div>
        </form>
    </div>
</main>

<script src="../assets/js/chapter_upload.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>