<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php'; // Προσθήκη της σύνδεσης με τη βάση
require_once '../includes/user_auth.php';
$page_title = "Ιστορικό Τεστ";
$load_test_css = true; // Αυτή η γραμμή υποδεικνύει ότι χρειαζόμαστε το test.css
require_once '../includes/header.php';

// Έλεγχος εξουσιοδότησης
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Παράμετροι φιλτραρίσματος
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$result = isset($_GET['result']) ? $_GET['result'] : '';

// Βασικό SQL ερώτημα
$query = "
    SELECT tr.*, sc.name AS category_name, tc.name AS chapter_name
    FROM test_results tr
    LEFT JOIN subscription_categories sc ON tr.test_category_id = sc.id
    LEFT JOIN test_chapters tc ON tr.chapter_id = tc.id
    WHERE tr.user_id = ?
";

// Προσθήκη φίλτρων
$params = [$user_id];
$types = "i";

if ($category_id > 0) {
    $query .= " AND tr.test_category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

if (!empty($type)) {
    $query .= " AND tr.test_type = ?";
    $params[] = $type;
    $types .= "s";
}

if (!empty($from_date)) {
    $query .= " AND DATE(tr.created_at) >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $query .= " AND DATE(tr.created_at) <= ?";
    $params[] = $to_date;
    $types .= "s";
}

if ($result == 'passed') {
    $query .= " AND tr.passed = 1";
} elseif ($result == 'failed') {
    $query .= " AND tr.passed = 0";
}

// Ταξινόμηση
$query .= " ORDER BY tr.created_at DESC";

// Εκτέλεση του ερωτήματος
$stmt = $mysqli->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ανάκτηση όλων των κατηγοριών για το φίλτρο
$categories_query = "
    SELECT DISTINCT sc.id, sc.name
    FROM subscription_categories sc
    JOIN test_results tr ON tr.test_category_id = sc.id
    WHERE tr.user_id = ?
    ORDER BY sc.name
";
$stmt = $mysqli->prepare($categories_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container">
    <h1 class="page-title">📋 Ιστορικό Τεστ</h1>
    
    <div class="filter-container">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label for="category_id">Κατηγορία:</label>
                <select name="category_id" id="category_id" class="form-control">
                    <option value="0">Όλες</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="type">Τύπος:</label>
                <select name="type" id="type" class="form-control">
                    <option value="">Όλοι</option>
                    <option value="random" <?= $type == 'random' ? 'selected' : '' ?>>Τυχαίο</option>
                    <option value="chapter" <?= $type == 'chapter' ? 'selected' : '' ?>>Ανά Κεφάλαιο</option>
                    <option value="simulation" <?= $type == 'simulation' ? 'selected' : '' ?>>Προσομοίωση</option>
                    <option value="difficult" <?= $type == 'difficult' ? 'selected' : '' ?>>Δύσκολες</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="from_date">Από:</label>
                <input type="date" name="from_date" id="from_date" value="<?= $from_date ?>" class="form-control">
            </div>
            
            <div class="filter-group">
                <label for="to_date">Έως:</label>
                <input type="date" name="to_date" id="to_date" value="<?= $to_date ?>" class="form-control">
            </div>
            
            <div class="filter-group">
                <label for="result">Αποτέλεσμα:</label>
                <select name="result" id="result" class="form-control">
                    <option value="">Όλα</option>
                    <option value="passed" <?= $result == 'passed' ? 'selected' : '' ?>>Επιτυχία</option>
                    <option value="failed" <?= $result == 'failed' ? 'selected' : '' ?>>Αποτυχία</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Φιλτράρισμα</button>
                <a href="history.php" class="btn btn-secondary">Καθαρισμός</a>
            </div>
        </form>
    </div>
    
    <?php if (empty($history)): ?>
    <div class="alert alert-info">
        <p>Δεν βρέθηκαν αποτελέσματα τεστ.</p>
        <p>Ξεκινήστε ένα νέο τεστ από την <a href="<?= BASE_URL ?>/test/start.php">σελίδα έναρξης τεστ</a>.</p>
    </div>
    <?php else: ?>
    
    <div class="history-stats">
        <div class="stat-card">
            <h3>Σύνολο Τεστ</h3>
            <p class="stat-value"><?= count($history) ?></p>
        </div>
        
        <div class="stat-card">
            <h3>Επιτυχημένα</h3>
            <?php
            $passed_count = 0;
            foreach ($history as $test) {
                if ($test['passed']) $passed_count++;
            }
            $passed_percentage = count($history) > 0 ? round(($passed_count / count($history)) * 100, 1) : 0;
            ?>
            <p class="stat-value"><?= $passed_count ?> (<?= $passed_percentage ?>%)</p>
        </div>
        
        <div class="stat-card">
            <h3>Μέσος Όρος</h3>
            <?php
            $total_score = 0;
            foreach ($history as $test) {
                $total_score += ($test['score'] / $test['total_questions']) * 100;
            }
            $avg_score = count($history) > 0 ? round($total_score / count($history), 1) : 0;
            ?>
            <p class="stat-value"><?= $avg_score ?>%</p>
        </div>
    </div>
    
    <div class="history-table-container">
        <table class="history-table">
            <thead>
                <tr>
                    <th>Ημερομηνία</th>
                    <th>Κατηγορία</th>
                    <th>Τύπος</th>
                    <th>Κεφάλαιο</th>
                    <th>Βαθμολογία</th>
                    <th>Χρόνος</th>
                    <th>Ενέργειες</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $test): 
                    // Μετατροπή χρόνου
                    $time_spent = $test['time_spent'];
                    $hours = floor($time_spent / 3600);
                    $minutes = floor(($time_spent % 3600) / 60);
                    $seconds = $time_spent % 60;
                    $time_formatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
                    
                    // Υπολογισμός ποσοστού
                    $score_percentage = round(($test['score'] / $test['total_questions']) * 100, 1);
                ?>
                <tr class="<?= $test['passed'] ? 'passed-test' : 'failed-test' ?>">
                    <td><?= date('d/m/Y H:i', strtotime($test['created_at'])) ?></td>
                    <td><?= htmlspecialchars($test['category_name'] ?? 'Άγνωστη') ?></td>
                    <td>
                        <?php switch ($test['test_type']) {
                            case 'random': echo '🎲 Τυχαίο'; break;
                            case 'chapter': echo '📚 Κεφάλαιο'; break;
                            case 'simulation': echo '🕒 Προσομοίωση'; break;
                            case 'difficult': echo '🔥 Δύσκολο'; break;
                            default: echo htmlspecialchars($test['test_type']);
                        } ?>
                    </td>
                    <td><?= htmlspecialchars($test['chapter_name'] ?? '-') ?></td>
                    <td class="score-cell <?= $test['passed'] ? 'passed' : 'failed' ?>">
                        <?= $test['score'] ?>/<?= $test['total_questions'] ?> (<?= $score_percentage ?>%)
                    </td>
                    <td><?= $time_formatted ?></td>
                    <td class="actions-cell">
                        <a href="<?= BASE_URL ?>/test/review.php?id=<?= $test['id'] ?>" class="btn btn-sm btn-primary">Ανασκόπηση</a>
                        <?php if (!$test['passed']): ?>
                        <a href="<?= BASE_URL ?>/test/practice.php?result_id=<?= $test['id'] ?>" class="btn btn-sm btn-success">Εξάσκηση</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/test/start.php" class="btn btn-primary">Νέο Τεστ</a>
        <a href="<?= BASE_URL ?>/users/dashboard.php" class="btn btn-secondary">Επιστροφή στην Αρχική</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>