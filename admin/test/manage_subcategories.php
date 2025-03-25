<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Καταγραφή ότι φορτώθηκε η σελίδα
log_debug("Σελίδα manage_subcategories.php φορτώθηκε");

// Φόρτωση των κατηγοριών
$query = "SELECT id, name FROM test_categories ORDER BY name";
$result = $mysqli->query($query);
$categories = array();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Φόρτωση των υποκατηγοριών
$query = "SELECT s.*, c.name as category_name 
          FROM test_subcategories s 
          JOIN test_categories c ON s.test_category_id = c.id 
          ORDER BY c.name, s.name";
$result = $mysqli->query($query);
$subcategories = array();
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row;
}

log_debug("Φορτώθηκαν " . count($categories) . " κατηγορίες και " . count($subcategories) . " υποκατηγορίες");

// Προσθήκη CSS για το ανέβασμα εικόνων
$additional_css = '<link rel="stylesheet" href="' . $config['base_url'] . '/admin/assets/css/subcategory_upload.css">';

// Φορτώνουμε το header μετά το logging για να μην επηρεάσει την εμφάνιση σφαλμάτων
require_once '../includes/admin_header.php';

// Ανάκτηση μηνυμάτων επιτυχίας/σφάλματος
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = 'Η υποκατηγορία προστέθηκε επιτυχώς!';
            break;
        case 'updated':
            $success_message = 'Η υποκατηγορία ενημερώθηκε επιτυχώς!';
            break;
        case 'deleted':
            $success_message = 'Η υποκατηγορία διαγράφηκε επιτυχώς!';
            break;
    }
}

if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}
?>

<main class="admin-container">
    <div class="admin-section-header">
        <h1 class="admin-title">Διαχείριση Υποκατηγοριών</h1>
        
        <div class="admin-actions">
            <a href="add_subcategory.php" class="btn-primary"><i class="action-icon">➕</i> Προσθήκη Υποκατηγορίας</a>
            <button id="add-subcategory-btn" class="btn-primary"><i class="action-icon">📝</i> Γρήγορη Προσθήκη</button>
            <a href="../dashboard.php" class="btn-secondary"><i class="action-icon">🔙</i> Επιστροφή</a>
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

    <!-- Πίνακας υποκατηγοριών -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Υποκατηγορία</th>
                    <th>Κατηγορία</th>
                    <th>Περιγραφή</th>
                    <th>Εικονίδιο</th>
                    <th>Ενέργειες</th>
                </tr>
            </thead>
            <tbody id="subcategory-list">
                <?php if (empty($subcategories)): ?>
                <tr>
                    <td colspan="5" class="text-center">Δεν βρέθηκαν υποκατηγορίες</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($subcategories as $subcategory): ?>
                    <tr data-id="<?= $subcategory['id'] ?>">
                        <td><?= htmlspecialchars($subcategory['name']) ?></td>
                        <td><?= htmlspecialchars($subcategory['category_name']) ?></td>
                        <td><?= !empty($subcategory['description']) ? htmlspecialchars($subcategory['description']) : '-' ?></td>
                        <td>
                            <?php if (!empty($subcategory['icon'])): ?>
                                <img src="<?= strpos($subcategory['icon'], 'http') === 0 ? $subcategory['icon'] : $config['base_url'] . '/assets/images/' . $subcategory['icon'] ?>" 
                                     alt="Εικονίδιο" width="30" height="30">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_subcategory.php?id=<?= $subcategory['id'] ?>" class="btn-edit" title="Επεξεργασία"><i class="action-icon">✏️</i></a>
                            <a href="delete_subcategory.php?id=<?= $subcategory['id'] ?>" class="btn-delete" title="Διαγραφή"><i class="action-icon">❌</i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Φόρτωση του JavaScript για τη διαχείριση υποκατηγοριών -->
<script src="<?= $config['base_url'] ?>/admin/assets/js/subcategory_manager.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>