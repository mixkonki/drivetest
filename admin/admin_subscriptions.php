<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

// 🔒 Έλεγχος αν ο χρήστης είναι διαχειριστής (γίνεται στο admin_auth.php)

// Αναζήτηση/Φίλτρα
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ανάκτηση κατηγοριών
$category_query = "
    SELECT s.id, s.name, s.price, s.icon, t.id AS test_category_id, s.description 
    FROM subscription_categories s 
    LEFT JOIN test_categories t ON s.id = t.subscription_category_id
    WHERE s.name LIKE ?
    ORDER BY s.name ASC";
$category_stmt = $mysqli->prepare($category_query);
$search_param = "%{$search}%";
$category_stmt->bind_param("s", $search_param);
$category_stmt->execute();
$category_result = $category_stmt->get_result();
$categories = $category_result ? $category_result->fetch_all(MYSQLI_ASSOC) : [];

// Ανάκτηση διαρκειών
$duration_query = "SELECT * FROM subscription_durations WHERE months LIKE ? ORDER BY months ASC";
$duration_stmt = $mysqli->prepare($duration_query);
$duration_stmt->bind_param("s", $search_param);
$duration_stmt->execute();
$duration_result = $duration_stmt->get_result();
$durations = $duration_result ? $duration_result->fetch_all(MYSQLI_ASSOC) : [];

$category_stmt->close();
$duration_stmt->close();

// Επεξεργασία μηνυμάτων επιτυχίας/σφάλματος
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = 'Η προσθήκη ολοκληρώθηκε επιτυχώς!';
            break;
        case 'updated':
            $success_message = 'Η ενημέρωση ολοκληρώθηκε επιτυχώς!';
            break;
        case 'deleted':
            $success_message = 'Η διαγραφή ολοκληρώθηκε επιτυχώς!';
            break;
    }
}

if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}


?>

<main class="admin-container" role="main" aria-label="Admin Panel για Διαχείριση Κατηγοριών και Διαρκειών">
    <h1 class="admin-title" role="heading" aria-level="1">🔧 Διαχείριση Κατηγοριών & Διαρκειών Συνδρομών</h1>

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

    <div class="admin-actions" role="navigation" aria-label="Ενέργειες Διαχείρισης">
        <form method="GET" class="search-form" aria-label="Φόρμα Αναζήτησης">
            <input type="text" name="search" placeholder="Αναζήτηση κατηγορίας/διάρκειας" value="<?= htmlspecialchars($search) ?>" aria-label="Πεδίο Αναζήτησης">
            <button type="submit" class="btn-primary" aria-label="Κουμπί Αναζήτησης">🔍 Αναζήτηση</button>
        </form>
        <div class="action-buttons">
            <a href="add_category.php" class="btn-primary" aria-label="Προσθήκη Νέας Κατηγορίας">➕ Προσθήκη Κατηγορίας</a>
            <a href="add_duration.php" class="btn-primary" aria-label="Προσθήκη Νέας Διάρκειας">➕ Προσθήκη Διάρκειας</a>
            <a href="dashboard.php" class="btn-secondary" aria-label="Επιστροφή στη Διαχείριση">⬅️ Επιστροφή</a>
        </div>
    </div>

    <div class="admin-subscription-management-grid">
        <!-- Στήλη Κατηγοριών -->
        <div class="subscription-column categories-column">
            <div class="column-header">
                <h2>📂 Κατηγορίες Συνδρομών</h2>
                <span class="item-count"><?= count($categories) ?> κατηγορίες</span>
            </div>
            
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <p>Δεν βρέθηκαν κατηγορίες συνδρομών.</p>
                    <a href="add_category.php" class="btn-primary">Προσθήκη πρώτης κατηγορίας</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table" role="table" aria-label="Πίνακας Κατηγοριών">
                        <thead role="rowgroup">
                            <tr role="row">
                                <th role="columnheader" class="sortable" data-sort="name">Κατηγορία</th>
                                <th role="columnheader" class="sortable" data-sort="price">Τιμή (€)</th>
                                <th role="columnheader">Περιγραφή</th>
                                <th role="columnheader">Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup" id="categories-table-body">
                            <?php foreach ($categories as $cat): ?>
                                <tr role="row">
                                    <td role="cell" class="category-name">
                                        <?php if (!empty($cat['icon'])): ?>
                                            <img src="<?= BASE_URL ?>/assets/images/<?= htmlspecialchars($cat['icon']) ?>" 
                                                alt="Εικονίδιο <?= htmlspecialchars($cat['name']) ?>"
                                                class="category-icon">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </td>
                                    <td role="cell" class="text-right"><?= htmlspecialchars($cat['price']) ?> €</td>
                                    <td role="cell" class="category-description">
                                        <?= !empty($cat['description']) ? htmlspecialchars(substr($cat['description'], 0, 50)) . (strlen($cat['description']) > 50 ? '...' : '') : '-' ?>
                                    </td>
                                    <td role="cell" class="actions-cell">
                                        <a href="edit_category.php?id=<?= $cat['id'] ?>" class="btn-edit" aria-label="Επεξεργασία Κατηγορίας <?= htmlspecialchars($cat['name']) ?>">
                                            <span class="action-icon">✏️</span> 
                                        </a>
                                        <a href="delete_category.php?id=<?= $cat['id'] ?>" class="btn-delete" aria-label="Διαγραφή Κατηγορίας <?= htmlspecialchars($cat['name']) ?>">
                                            <span class="action-icon">❌</span> 
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Στήλη Διαρκειών -->
        <div class="subscription-column durations-column">
            <div class="column-header">
                <h2>⏱️ Διάρκειες Συνδρομών</h2>
                <span class="item-count"><?= count($durations) ?> διάρκειες</span>
            </div>
            
            <?php if (empty($durations)): ?>
                <div class="empty-state">
                    <p>Δεν βρέθηκαν διάρκειες συνδρομών.</p>
                    <a href="add_duration.php" class="btn-primary">Προσθήκη πρώτης διάρκειας</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table" role="table" aria-label="Πίνακας Διαρκειών">
                        <thead role="rowgroup">
                            <tr role="row">
                                <th role="columnheader" class="sortable" data-sort="months">Μήνες</th>
                                <th role="columnheader">Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup" id="durations-table-body">
                            <?php foreach ($durations as $dur): ?>
                                <tr role="row">
                                    <td role="cell">
                                        <span class="duration-value"><?= htmlspecialchars($dur['months']) ?></span> 
                                        <span class="duration-label"><?= htmlspecialchars($dur['months']) == 1 ? 'μήνας' : 'μήνες' ?></span>
                                    </td>
                                    <td role="cell" class="actions-cell">
                                        <a href="edit_duration.php?id=<?= $dur['id'] ?>" class="btn-edit" aria-label="Επεξεργασία Διάρκειας <?= htmlspecialchars($dur['months']) ?> μηνών">
                                            <span class="action-icon">✏️</span> 
                                        </a>
                                        <a href="delete_duration.php?id=<?= $dur['id'] ?>" class="btn-delete" aria-label="Διαγραφή Διάρκειας <?= htmlspecialchars($dur['months']) ?> μηνών">
                                            <span class="action-icon">❌</span> 
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Φόρτωση του JavaScript για τη σελίδα -->
<script src="<?= BASE_URL ?>/admin/assets/js/subscription_management.js"></script>

<?php require_once 'includes/admin_footer.php'; ?>