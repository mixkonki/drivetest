<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

// 🔒 Έλεγχος αν ο χρήστης είναι διαχειριστής (γίνεται στο admin_auth.php)

// Αναζήτηση/Φίλτρα
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$category_query = "
    SELECT s.id, s.name, s.price, s.icon, t.id AS test_category_id 
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

$duration_query = "SELECT * FROM subscription_durations WHERE months LIKE ? ORDER BY months ASC";
$duration_stmt = $mysqli->prepare($duration_query);
$duration_stmt->bind_param("s", $search_param);
$duration_stmt->execute();
$duration_result = $duration_stmt->get_result();
$durations = $duration_result ? $duration_result->fetch_all(MYSQLI_ASSOC) : [];

$category_stmt->close();
$duration_stmt->close();
?>

<main class="admin-container" role="main" aria-label="Admin Panel για Διαχείριση Κατηγοριών και Διαρκειών">
    <h1 class="admin-title" role="heading" aria-level="1">🔧 Διαχείριση Κατηγοριών & Διαρκειών Συνδρομών</h1>

    <div class="admin-actions" role="navigation" aria-label="Ενέργειες Διαχείρισης">
        <form method="GET" class="search-form" aria-label="Φόρμα Αναζήτησης">
            <input type="text" name="search" placeholder="Αναζήτηση κατηγορίας/διάρκειας" value="<?= htmlspecialchars($search) ?>" aria-label="Πεδίο Αναζήτησης" required>
            <button type="submit" class="btn-primary" aria-label="Κουμπί Αναζήτησης">🔍 Αναζήτηση</button>
        </form>
        <a href="add_category.php" class="btn-primary" aria-label="Προσθήκη Νέας Κατηγορίας">➕ Προσθήκη Κατηγορίας</a>
        <a href="add_duration.php" class="btn-primary" aria-label="Προσθήκη Νέας Διάρκειας">➕ Προσθήκη Διάρκειας</a>
        <a href="admin_dashboard.php" class="btn-secondary" aria-label="Επιστροφή στη Διαχείριση">⬅️ Επιστροφή στη Διαχείριση</a>
    </div>

    <div class="tables-container" role="region" aria-label="Πίνακες Κατηγοριών και Διαρκειών">
        <table class="admin-table" role="table" aria-label="Πίνακας Κατηγοριών">
            <thead role="rowgroup">
                <tr role="row">
                    <th role="columnheader">Κατηγορία</th>
                    <th role="columnheader">Τιμή (€)</th>
                    <th role="columnheader">Ενέργειες</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                <?php if (empty($categories)): ?>
                    <tr role="row"><td colspan="3" role="cell">Δεν βρέθηκαν κατηγορίες.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr role="row">
                            <td role="cell"><?= htmlspecialchars($cat['name']) ?></td>
                            <td role="cell"><?= htmlspecialchars($cat['price']) ?> €</td>
                            <td role="cell">
                                <a href="edit_category.php?id=<?= $cat['id'] ?>" class="btn-edit" aria-label="Επεξεργασία Κατηγορίας <?= htmlspecialchars($cat['name']) ?>">✏️ Επεξεργασία</a> |
                                <a href="delete_category.php?id=<?= $cat['id'] ?>" class="btn-delete" aria-label="Διαγραφή Κατηγορίας <?= htmlspecialchars($cat['name']) ?>" onclick="return confirm('Σίγουρα θέλετε να διαγράψετε αυτή την κατηγορία;')">❌ Διαγραφή</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="admin-table" role="table" aria-label="Πίνακας Διαρκειών">
            <thead role="rowgroup">
                <tr role="row">
                    <th role="columnheader">Μήνες</th>
                    <th role="columnheader">Ενέργειες</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                <?php if (empty($durations)): ?>
                    <tr role="row"><td colspan="2" role="cell">Δεν βρέθηκαν διαρκείες.</td></tr>
                <?php else: ?>
                    <?php foreach ($durations as $dur): ?>
                        <tr role="row">
                            <td role="cell"><?= htmlspecialchars($dur['months']) ?> μήνες</td>
                            <td role="cell">
                                <a href="edit_duration.php?id=<?= $dur['id'] ?>" class="btn-edit" aria-label="Επεξεργασία Διάρκειας <?= htmlspecialchars($dur['months']) ?> μηνών">✏️ Επεξεργασία</a> |
                                <a href="delete_duration.php?id=<?= $dur['id'] ?>" class="btn-delete" aria-label="Διαγραφή Διάρκειας <?= htmlspecialchars($dur['months']) ?> μηνών" onclick="return confirm('Σίγουρα θέλετε να διαγράψετε αυτή τη διάρκεια;')">❌ Διαγραφή</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once 'includes/admin_footer.php'; ?>