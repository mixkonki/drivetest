<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Ανάκτηση όλων των διαθέσιμων ρυθμίσεων
$config_query = "SELECT tc.id, tc.test_name, tc.category_id, c.name as category_name, 
                tc.questions_count, tc.time_limit, tc.pass_percentage, tc.selection_method
                FROM test_configurations tc
                JOIN test_categories c ON tc.category_id = c.id
                WHERE tc.status = 'active'
                ORDER BY c.name, tc.test_name";
$config_result = $mysqli->query($config_query);
$configurations = $config_result ? $config_result->fetch_all(MYSQLI_ASSOC) : [];

// Χειρισμός δημιουργίας τεστ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config_id = intval($_POST['config_id'] ?? 0);
    $test_label = trim($_POST['test_label'] ?? '');
    
    if ($config_id === 0) {
        $error = "Παρακαλώ επιλέξτε μια ρύθμιση τεστ.";
    } else {
        // Ανάκτηση της ρύθμισης
        $config_query = "SELECT * FROM test_configurations WHERE id = ?";
        $config_stmt = $mysqli->prepare($config_query);
        $config_stmt->bind_param("i", $config_id);
        $config_stmt->execute();
        $config = $config_stmt->get_result()->fetch_assoc();
        
        if (!$config) {
            $error = "Η επιλεγμένη ρύθμιση δεν βρέθηκε.";
        } else {
            // Δημιουργία του νέου τεστ
            $status = 'active';
            $created_by = $_SESSION['user_id'];
            
            $test_query = "INSERT INTO test_generation (config_id, test_name, label, questions_count, time_limit, pass_percentage, status, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $test_stmt = $mysqli->prepare($test_query);
            $test_stmt->bind_param("issiiisi", $config_id, $config['test_name'], $test_label, $config['questions_count'], $config['time_limit'], $config['pass_percentage'], $status, $created_by);
            
            if ($test_stmt->execute()) {
                $test_id = $test_stmt->insert_id;
                
                // Επιλογή ερωτήσεων βάσει της ρύθμισης
                $questions = selectQuestionsForTest($mysqli, $config);
                
                if (empty($questions)) {
                    // Αν δεν βρέθηκαν ερωτήσεις, διαγράφουμε το τεστ
                    $delete_query = "DELETE FROM test_generation WHERE id = ?";
                    $delete_stmt = $mysqli->prepare($delete_query);
                    $delete_stmt->bind_param("i", $test_id);
                    $delete_stmt->execute();
                    
                    $error = "Δεν βρέθηκαν ερωτήσεις για τη συγκεκριμένη ρύθμιση.";
                } else {
                    // Αποθήκευση των ερωτήσεων στο τεστ
                    foreach ($questions as $index => $question_id) {
                        $position = $index + 1;
                        $question_query = "INSERT INTO test_generation_questions (test_id, question_id, position) VALUES (?, ?, ?)";
                        $question_stmt = $mysqli->prepare($question_query);
                        $question_stmt->bind_param("iii", $test_id, $question_id, $position);
                        $question_stmt->execute();
                    }
                    
                    $success = "Το τεστ δημιουργήθηκε επιτυχώς! <a href='view_test.php?id={$test_id}'>Προβολή</a>";
                }
            } else {
                $error = "Σφάλμα κατά τη δημιουργία του τεστ: " . $test_stmt->error;
            }
        }
    }
}

// Συνάρτηση επιλογής ερωτήσεων βάσει ρύθμισης
function selectQuestionsForTest($mysqli, $config) {
    $category_id = $config['category_id'];
    $questions_count = $config['questions_count'];
    $selection_method = $config['selection_method'];
    $chapter_distribution = json_decode($config['chapter_distribution'] ?? '{}', true);
    
    $selected_questions = [];
    
    // Ανάλογα με τη μέθοδο επιλογής
    switch ($selection_method) {
        case 'random':
            // Τυχαία επιλογή ερωτήσεων από την κατηγορία
            $query = "SELECT q.id FROM questions q
                     JOIN test_chapters c ON q.chapter_id = c.id
                     JOIN test_subcategories s ON c.subcategory_id = s.id
                     WHERE s.test_category_id = ? AND q.status = 'active'
                     ORDER BY RAND() LIMIT ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ii", $category_id, $questions_count);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $selected_questions[] = $row['id'];
            }
            break;
            
        case 'proportional':
            // Αναλογική επιλογή ερωτήσεων βάσει των ποσοστών ανά κεφάλαιο
            
            // Αν δεν υπάρχει κατανομή, επιστρέφουμε κενό πίνακα
            if (empty($chapter_distribution)) return [];
            
            // Υπολογισμός συνόλου ποσοστών
            $total_percentage = array_sum($chapter_distribution);
            if ($total_percentage == 0) return [];
            
            // Για κάθε κεφάλαιο, υπολογισμός ερωτήσεων βάσει ποσοστού
            foreach ($chapter_distribution as $chapter_id => $percentage) {
                if ($percentage == 0) continue;
                
                $chapter_questions_count = round(($percentage / $total_percentage) * $questions_count);
                if ($chapter_questions_count == 0) continue;
                
                $query = "SELECT id FROM questions WHERE chapter_id = ? AND status = 'active' ORDER BY RAND() LIMIT ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ii", $chapter_id, $chapter_questions_count);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $selected_questions[] = $row['id'];
                }
            }
            
            // Αν δεν συμπληρώθηκε ο απαιτούμενος αριθμός ερωτήσεων
            if (count($selected_questions) < $questions_count) {
                $remaining = $questions_count - count($selected_questions);
                
                if (!empty($selected_questions)) {
                    $placeholders = implode(',', array_fill(0, count($selected_questions), '?'));
                    $query = "SELECT q.id FROM questions q
                             JOIN test_chapters c ON q.chapter_id = c.id
                             JOIN test_subcategories s ON c.subcategory_id = s.id
                             WHERE s.test_category_id = ? AND q.status = 'active'
                               AND q.id NOT IN ($placeholders)
                             ORDER BY RAND() LIMIT ?";
                    
                    $stmt = $mysqli->prepare($query);
                    $params = array_merge([$category_id], $selected_questions, [$remaining]);
                    $types = str_repeat('i', count($params));
                    $stmt->bind_param($types, ...$params);
                } else {
                    $query = "SELECT q.id FROM questions q
                             JOIN test_chapters c ON q.chapter_id = c.id
                             JOIN test_subcategories s ON c.subcategory_id = s.id
                             WHERE s.test_category_id = ? AND q.status = 'active'
                             ORDER BY RAND() LIMIT ?";
                    
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("ii", $category_id, $remaining);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $selected_questions[] = $row['id'];
                }
            }
            break;
            
        case 'fixed':
            // Σταθερός αριθμός ερωτήσεων ανά κεφάλαιο
            if (empty($chapter_distribution)) return [];
            
            foreach ($chapter_distribution as $chapter_id => $count) {
                if ($count == 0) continue;
                
                $query = "SELECT id FROM questions WHERE chapter_id = ? AND status = 'active' ORDER BY RAND() LIMIT ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ii", $chapter_id, $count);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $selected_questions[] = $row['id'];
                }
            }
            break;
    }
    
    // Ανακάτεμα των ερωτήσεων για τυχαία σειρά
    shuffle($selected_questions);
    
    // Περιορισμός σε $questions_count ερωτήσεις, εάν επιλέχθηκαν περισσότερες
    if (count($selected_questions) > $questions_count) {
        $selected_questions = array_slice($selected_questions, 0, $questions_count);
    }
    
    return $selected_questions;
}

// Λίστα υπαρχόντων τεστ
$tests_query = "SELECT tg.id, tg.test_name, tg.label, tg.created_at, tg.questions_count, 
               tc.name as category_name, u.fullname as creator_name
               FROM test_generation tg
               JOIN test_configurations cf ON tg.config_id = cf.id
               JOIN test_categories tc ON cf.category_id = tc.id
               JOIN users u ON tg.created_by = u.id
               ORDER BY tg.created_at DESC";
$tests_result = $mysqli->query($tests_query);
$tests = $tests_result ? $tests_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Δημιουργία Τεστ</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin_styles.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/generate_test.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<main class="admin-container">
    <h2 class="admin-title">🧩 Δημιουργία Τεστ</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="admin-section">
        <h3>Επιλογή Ρύθμισης και Δημιουργία Τεστ</h3>
        
        <?php if (empty($configurations)): ?>
            <p class="info-message">Δεν υπάρχουν διαθέσιμες ρυθμίσεις. Παρακαλώ <a href="test_config.php">δημιουργήστε μια ρύθμιση</a> πρώτα.</p>
        <?php else: ?>
            <form method="POST" class="admin-form" id="generate-test-form">
                <div class="form-group">
                    <label for="config_id">Επιλογή Ρύθμισης:</label>
                    <select id="config_id" name="config_id" required>
                        <option value="">-- Επιλέξτε Ρύθμιση --</option>
                        <?php foreach ($configurations as $config): ?>
                            <option value="<?= $config['id'] ?>" data-questions="<?= $config['questions_count'] ?>" data-time="<?= $config['time_limit'] ?>" data-pass="<?= $config['pass_percentage'] ?>" data-method="<?= $config['selection_method'] ?>">
                                <?= htmlspecialchars($config['category_name']) ?> - <?= htmlspecialchars($config['test_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="test_label">Ετικέτα Τεστ (προαιρετικό):</label>
                    <input type="text" id="test_label" name="test_label" placeholder="Προσθέστε μια περιγραφή ή ετικέτα για το τεστ">
                </div>
                
                <div class="config-details" id="config-details" style="display: none;">
                    <h4>Λεπτομέρειες Επιλεγμένης Ρύθμισης:</h4>
                    <p><strong>Αριθμός Ερωτήσεων:</strong> <span id="config-questions">-</span></p>
                    <p><strong>Χρονικό Όριο:</strong> <span id="config-time">-</span> λεπτά</p>
                    <p><strong>Ποσοστό Επιτυχίας:</strong> <span id="config-pass">-</span>%</p>
                    <p><strong>Μέθοδος Επιλογής:</strong> <span id="config-method">-</span></p>
                </div>
                
                <button type="submit" class="btn-primary">🔄 Δημιουργία Τεστ</button>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="admin-section">
        <h3>Υπάρχοντα Τεστ</h3>
        <?php if (empty($tests)): ?>
            <p class="info-message">Δεν υπάρχουν ακόμα τεστ.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Όνομα</th>
                        <th>Ετικέτα</th>
                        <th>Κατηγορία</th>
                        <th>Ερωτήσεις</th>
                        <th>Δημιουργήθηκε</th>
                        <th>Δημιουργός</th>
                        <th>Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test): ?>
                        <tr>
                            <td><?= htmlspecialchars($test['test_name']) ?></td>
                            <td><?= htmlspecialchars($test['label'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($test['category_name']) ?></td>
                            <td><?= $test['questions_count'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($test['created_at'])) ?></td>
                            <td><?= htmlspecialchars($test['creator_name']) ?></td>
                            <td>
                                <a href="view_test.php?id=<?= $test['id'] ?>" class="btn-view">👁️</a>
                                <button class="btn-delete delete-test" data-id="<?= $test['id'] ?>">❌</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<script src="<?= BASE_URL ?>/admin/assets/js/generate_test.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>
</body>
</html>