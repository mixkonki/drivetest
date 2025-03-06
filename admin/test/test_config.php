<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Ανάκτηση όλων των κατηγοριών
$categories_query = "SELECT id, name FROM test_categories ORDER BY name ASC";
$categories_result = $mysqli->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Χειρισμός αποθήκευσης ρυθμίσεων
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $test_name = trim($_POST['test_name'] ?? '');
    $questions_count = intval($_POST['questions_count'] ?? 20);
    $time_limit = intval($_POST['time_limit'] ?? 30); // λεπτά
    $pass_percentage = intval($_POST['pass_percentage'] ?? 70);
    $selection_method = $_POST['selection_method'] ?? 'random'; // random, fixed, proportional
    $chapter_distribution = isset($_POST['chapter_distribution']) ? json_encode($_POST['chapter_distribution']) : '{}';
    $status = 'active';
    
    // Έλεγχος εγκυρότητας
    if (empty($test_name) || $category_id === 0 || $questions_count <= 0) {
        $error = "Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία";
    } else {
        // Έλεγχος αν υπάρχει ήδη η ρύθμιση για αυτή την κατηγορία
        $check_query = "SELECT id FROM test_configurations WHERE category_id = ? AND test_name = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("is", $category_id, $test_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Ενημέρωση υπάρχουσας ρύθμισης
            $config_id = $check_result->fetch_assoc()['id'];
            $update_query = "UPDATE test_configurations SET 
                            questions_count = ?, 
                            time_limit = ?, 
                            pass_percentage = ?, 
                            selection_method = ?, 
                            chapter_distribution = ?, 
                            updated_at = NOW()
                            WHERE id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param("iisssi", $questions_count, $time_limit, $pass_percentage, $selection_method, $chapter_distribution, $config_id);
            if ($stmt->execute()) {
                $success = "Οι ρυθμίσεις ενημερώθηκαν επιτυχώς!";
            } else {
                $error = "Σφάλμα κατά την ενημέρωση: " . $stmt->error;
            }
        } else {
            // Εισαγωγή νέας ρύθμισης
            $insert_query = "INSERT INTO test_configurations 
                            (category_id, test_name, questions_count, time_limit, pass_percentage, 
                            selection_method, chapter_distribution, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $mysqli->prepare($insert_query);
            $stmt->bind_param("isiissss", $category_id, $test_name, $questions_count, $time_limit, $pass_percentage, $selection_method, $chapter_distribution, $status);
            if ($stmt->execute()) {
                $success = "Οι ρυθμίσεις αποθηκεύτηκαν επιτυχώς!";
            } else {
                $error = "Σφάλμα κατά την αποθήκευση: " . $stmt->error;
            }
        }
    }
}

// Φόρτωση υπαρχουσών ρυθμίσεων
$configurations_query = "SELECT tc.*, c.name as category_name 
                        FROM test_configurations tc
                        JOIN test_categories c ON tc.category_id = c.id
                        ORDER BY c.name ASC";
$configurations_result = $mysqli->query($configurations_query);
$configurations = $configurations_result ? $configurations_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαχείριση Ρυθμίσεων Τεστ</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin_styles.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/test_config.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<main class="admin-container">
    <h2 class="admin-title">📋 Διαχείριση Ρυθμίσεων Τεστ</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="admin-section">
        <h3>Προσθήκη/Επεξεργασία Ρυθμίσεων Τεστ</h3>
        <form method="POST" class="admin-form" id="test-config-form">
            <div class="form-group">
                <label for="category_id">Κατηγορία:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Επιλέξτε Κατηγορία --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="test_name">Όνομα Τεστ:</label>
                <input type="text" id="test_name" name="test_name" required>
            </div>
            
            <div class="form-group">
                <label for="questions_count">Αριθμός Ερωτήσεων:</label>
                <input type="number" id="questions_count" name="questions_count" value="20" min="5" max="100" required>
            </div>
            
            <div class="form-group">
                <label for="time_limit">Χρονικό Όριο (λεπτά):</label>
                <input type="number" id="time_limit" name="time_limit" value="30" min="5" max="120" required>
            </div>
            
            <div class="form-group">
                <label for="pass_percentage">Ποσοστό Επιτυχίας (%):</label>
                <input type="number" id="pass_percentage" name="pass_percentage" value="70" min="50" max="100" required>
            </div>
            
            <div class="form-group">
                <label for="selection_method">Μέθοδος Επιλογής Ερωτήσεων:</label>
                <select id="selection_method" name="selection_method" required>
                    <option value="random">Τυχαία</option>
                    <option value="proportional">Αναλογική βάσει κεφαλαίων</option>
                    <option value="fixed">Συγκεκριμένος αριθμός ανά κεφάλαιο</option>
                </select>
            </div>
            
            <div id="chapter_distribution_container" style="display: none;">
                <h4>Κατανομή Ερωτήσεων ανά Κεφάλαιο</h4>
                <div id="chapters_list">
                    <!-- Θα συμπληρωθεί μέσω JavaScript -->
                </div>
            </div>
            
            <button type="submit" class="btn-primary">💾 Αποθήκευση Ρυθμίσεων</button>
        </form>
    </div>
    
    <div class="admin-section">
        <h3>Υπάρχουσες Ρυθμίσεις</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Κατηγορία</th>
                    <th>Όνομα Τεστ</th>
                    <th>Ερωτήσεις</th>
                    <th>Χρόνος (λεπτά)</th>
                    <th>% Επιτυχίας</th>
                    <th>Μέθοδος Επιλογής</th>
                    <th>Ενέργειες</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($configurations)): ?>
                    <tr>
                        <td colspan="7">Δεν βρέθηκαν ρυθμίσεις.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($configurations as $config): ?>
                        <tr>
                            <td><?= htmlspecialchars($config['category_name']) ?></td>
                            <td><?= htmlspecialchars($config['test_name']) ?></td>
                            <td><?= $config['questions_count'] ?></td>
                            <td><?= $config['time_limit'] ?></td>
                            <td><?= $config['pass_percentage'] ?>%</td>
                            <td>
                                <?php 
                                    switch($config['selection_method']) {
                                        case 'random': echo 'Τυχαία'; break;
                                        case 'proportional': echo 'Αναλογική'; break;
                                        case 'fixed': echo 'Σταθερή'; break;
                                        default: echo $config['selection_method'];
                                    }
                                ?>
                            </td>
                            <td>
                                <button type="button" class="btn-edit edit-config" data-id="<?= $config['id'] ?>">✏️</button>
                                <button type="button" class="btn-delete delete-config" data-id="<?= $config['id'] ?>">❌</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="<?= BASE_URL ?>/admin/assets/js/test_config.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>
</body>
</html>