<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

// 🔒 Έλεγχος αν ο χρήστης είναι διαχειριστής
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

// Έλεγχος αν υπάρχει ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . BASE_URL . "/admin/test/manage_chapters.php?error=Μη έγκυρο ID κεφαλαίου");
    exit();
}

$chapter_id = intval($_GET['id']);

// Ανάκτηση δεδομένων κεφαλαίου
$stmt = $mysqli->prepare("
    SELECT 
        c.id, 
        c.name, 
        c.description, 
        c.icon, 
        c.subcategory_id,
        sc.name AS subcategory_name,
        tc.id AS category_id,
        tc.name AS category_name
    FROM test_chapters c
    JOIN test_subcategories sc ON c.subcategory_id = sc.id
    JOIN test_categories tc ON sc.test_category_id = tc.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: " . BASE_URL . "/admin/test/manage_chapters.php?error=Το κεφάλαιο δε βρέθηκε");
    exit();
}

$chapter = $result->fetch_assoc();
$stmt->close();

// Διαμόρφωση του URL του εικονιδίου
if (!empty($chapter['icon'])) {
    if (filter_var($chapter['icon'], FILTER_VALIDATE_URL)) {
        $icon_url = $chapter['icon'];
    } else {
        $icon_url = rtrim(BASE_URL, '/') . '/assets/images/chapters/' . $chapter['icon'];
    }
} else {
    $icon_url = BASE_URL . '/assets/images/default.png';
}

// Έλεγχος αν το κεφάλαιο χρησιμοποιείται σε ερωτήσεις
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM questions WHERE chapter_id = ?");
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$result = $stmt->get_result();
$questions_count = $result->fetch_assoc()['count'];
$stmt->close();

// Έλεγχος αν υπάρχει επιβεβαίωση διαγραφής
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if ($confirmed) {
    $stmt = $mysqli->prepare("DELETE FROM test_chapters WHERE id = ?");
    $stmt->bind_param("i", $chapter_id);
    
    if ($stmt->execute()) {
        // Διαγραφή του αρχείου εικονιδίου αν υπάρχει και είναι τοπικό
        if (!empty($chapter['icon']) && !filter_var($chapter['icon'], FILTER_VALIDATE_URL) && file_exists('../../assets/images/chapters/' . $chapter['icon'])) {
            unlink('../../assets/images/chapters/' . $chapter['icon']);
        }
        
        header("Location: " . BASE_URL . "/admin/test/manage_chapters.php?success=Το κεφάλαιο διαγράφηκε επιτυχώς");
        exit();
    } else {
        header("Location: " . BASE_URL . "/admin/test/manage_chapters.php?error=Σφάλμα κατά τη διαγραφή: " . $stmt->error);
        exit();
    }
    $stmt->close();
}

// Προσθήκη του ειδικού CSS για τη διαχείριση κεφαλαίων
$additional_css = '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/chapter_management.css">';

require_once '../includes/admin_header.php';
?>

<main class="admin-container">
    <div class="confirmation-container">
        <div class="confirmation-header">
            <h2 class="admin-title">🗑️ Διαγραφή Κεφαλαίου</h2>
            <div class="confirmation-icon">
                <div class="warning-icon">❗</div>
            </div>
        </div>
        
        <div class="confirmation-content">
            <div class="chapter-preview">
                <?php if (!empty($chapter['icon'])): ?>
                    <div class="chapter-icon-container">
                        <img src="<?= $icon_url ?>" alt="<?= htmlspecialchars($chapter['name']) ?>" class="chapter-icon">
                    </div>
                <?php endif; ?>
                
                <div class="chapter-details">
                    <h3 class="chapter-name"><?= htmlspecialchars($chapter['name']) ?></h3>
                    <div class="chapter-parent"><?= htmlspecialchars($chapter['category_name']) ?> - <?= htmlspecialchars($chapter['subcategory_name']) ?></div>
                </div>
            </div>
            
            <p class="confirmation-message">
                Είστε σίγουροι ότι θέλετε να διαγράψετε το κεφάλαιο <strong><?= htmlspecialchars($chapter['name']) ?></strong>;
            </p>
            
            <?php if ($questions_count > 0): ?>
                <div class="confirmation-warning">
                    <strong>Προσοχή!</strong> Αυτό το κεφάλαιο χρησιμοποιείται σε <?= $questions_count ?> ερώτηση(εις). 
                    Η διαγραφή του μπορεί να προκαλέσει προβλήματα στα σχετικά τεστ και ερωτήσεις.
                </div>
            <?php endif; ?>
            
            <p class="confirmation-question">Θέλετε να συνεχίσετε με τη διαγραφή;</p>
            
            <div class="confirmation-actions">
                <a href="delete_chapter.php?id=<?= $chapter_id ?>&confirm=yes" class="btn-danger">🗑️ Ναι, Διαγραφή</a>
                <a href="manage_chapters.php" class="btn-secondary">❌ Όχι, Ακύρωση</a>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/admin_footer.php'; ?>