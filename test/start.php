<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

$page_title = "Έναρξη Τεστ";
$load_test_css = true; // Αυτή η γραμμή υποδεικνύει ότι χρειαζόμαστε το test.css
require_once '../includes/header.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Καθαρισμός προηγούμενου τεστ
unset($_SESSION['current_test']);

// Ανάκτηση διαθέσιμων κατηγοριών
$query = "
    SELECT sc.id, sc.name, sc.description, tc.id AS test_category_id
    FROM subscription_categories sc
    JOIN test_categories tc ON sc.id = tc.subscription_category_id
    JOIN subscriptions s ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON))
    WHERE s.user_id = ? AND s.status = 'active' AND s.expiry_date >= CURRENT_DATE
    GROUP BY sc.id
    ORDER BY sc.name ASC
";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ανάκτηση όλων των κεφαλαίων
$query = "
    SELECT c.id, c.name, c.subcategory_id, s.test_category_id
    FROM test_chapters c
    JOIN test_subcategories s ON c.subcategory_id = s.id
    ORDER BY c.name ASC
";
$result = $mysqli->query($query);
$chapters = $result->fetch_all(MYSQLI_ASSOC);

// Ανάκτηση στατιστικών χρήστη
$query = "
    SELECT COUNT(*) as total_tests, 
           ROUND(AVG(score/total_questions*100), 1) as avg_score
    FROM test_results 
    WHERE user_id = ?
";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Εμφάνιση μηνύματος σφάλματος αν υπάρχει
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
?>

<div class="container">
    <div class="test-start-container">
        <h1 class="page-title">🎯 Έναρξη Νέου Τεστ</h1>
        
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($categories)): ?>
        <div class="alert alert-info">
            <p>Δεν έχετε ενεργή συνδρομή σε κάποια κατηγορία.</p>
            <p>Μπορείτε να αγοράσετε συνδρομή από την <a href="<?= BASE_URL ?>/subscriptions.php">σελίδα συνδρομών</a>.</p>
        </div>
        <?php else: ?>
        
        <?php if (!empty($stats) && $stats['total_tests'] > 0): ?>
        <div class="user-stats">
            <div class="stat-item">
                <div class="stat-label">Σύνολο Τεστ</div>
                <div class="stat-value"><?= $stats['total_tests'] ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Μέσος Όρος</div>
                <div class="stat-value <?= $stats['avg_score'] >= 70 ? 'good-score' : 'bad-score' ?>">
                    <?= $stats['avg_score'] ?>%
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <form action="generate_test.php" method="GET" id="test-form">
            <div class="form-group">
                <label for="category_id">Επιλέξτε Κατηγορία:</label>
                <select name="category_id" id="category_id" required class="form-control">
                    <option value="">-- Επιλέξτε Κατηγορία --</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" data-test-category="<?= $cat['test_category_id'] ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="test_type">Τύπος Τεστ:</label>
                <select name="test_type" id="test_type" required class="form-control">
                    <option value="random">🎲 Τυχαίο Τεστ</option>
                    <option value="chapter">📚 Τεστ ανά Κεφάλαιο</option>
                    <option value="simulation">🕒 Τεστ Προσομοίωσης</option>
                    <option value="difficult">🔥 Δύσκολες Ερωτήσεις</option>
                </select>
            </div>
            
            <div class="form-group" id="chapter-group" style="display: none;">
                <label for="chapter_id">Επιλέξτε Κεφάλαιο:</label>
                <select name="chapter_id" id="chapter_id" class="form-control">
                    <option value="">-- Επιλέξτε Κεφάλαιο --</option>
                    <?php foreach ($chapters as $chapter): ?>
                    <option value="<?= $chapter['id'] ?>" data-category="<?= $chapter['test_category_id'] ?>" style="display: none;">
                        <?= htmlspecialchars($chapter['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="question_count">Αριθμός Ερωτήσεων: <span id="question_count_value">20</span></label>
                <input type="range" name="question_count" id="question_count" min="5" max="50" value="20" step="5" class="form-control-range">
            </div>
            
            <div class="form-group">
                <label for="time_limit">Χρονικό Όριο (λεπτά): <span id="time_limit_value">Χωρίς όριο</span></label>
                <input type="range" name="time_limit" id="time_limit" min="0" max="60" value="0" step="5" class="form-control-range">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">🚀 Έναρξη Τεστ</button>
                <a href="<?= BASE_URL ?>/users/dashboard.php" class="btn btn-secondary">🔙 Επιστροφή</a>
                            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const testTypeSelect = document.getElementById('test_type');
    const categorySelect = document.getElementById('category_id');
    const chapterGroup = document.getElementById('chapter-group');
    const chapterSelect = document.getElementById('chapter_id');
    const questionCountInput = document.getElementById('question_count');
    const questionCountValue = document.getElementById('question_count_value');
    const timeLimitInput = document.getElementById('time_limit');
    const timeLimitValue = document.getElementById('time_limit_value');
    
    // Ενημέρωση εμφάνισης τιμών στα sliders
    questionCountInput.addEventListener('input', function() {
        questionCountValue.textContent = this.value;
    });
    
    timeLimitInput.addEventListener('input', function() {
        timeLimitValue.textContent = this.value == 0 ? 'Χωρίς όριο' : this.value + ' λεπτά';
    });
    
    // Εμφάνιση/απόκρυψη επιλογής κεφαλαίου
    testTypeSelect.addEventListener('change', function() {
        if (this.value === 'chapter') {
            chapterGroup.style.display = 'block';
            document.getElementById('chapter_id').setAttribute('required', 'required');
        } else {
            chapterGroup.style.display = 'none';
            document.getElementById('chapter_id').removeAttribute('required');
        }
    });
    
    // Φιλτράρισμα κεφαλαίων με βάση την κατηγορία
    categorySelect.addEventListener('change', function() {
        const testCategoryId = this.options[this.selectedIndex].getAttribute('data-test-category');
        const options = chapterSelect.querySelectorAll('option');
        
        // Επαναφορά επιλογής κεφαλαίου
        chapterSelect.value = '';
        
        options.forEach(option => {
            if (option.value === '') return; // Παραλείπουμε την προεπιλεγμένη επιλογή
            
            if (option.getAttribute('data-category') === testCategoryId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    });
    
    // Επικύρωση φόρμας πριν την υποβολή
    document.getElementById('test-form').addEventListener('submit', function(e) {
        if (testTypeSelect.value === 'chapter' && chapterSelect.value === '') {
            e.preventDefault();
            alert('Παρακαλώ επιλέξτε κεφάλαιο για το τεστ ανά κεφάλαιο.');
            return false;
        }
        
        if (categorySelect.value === '') {
            e.preventDefault();
            alert('Παρακαλώ επιλέξτε κατηγορία για το τεστ.');
            return false;
        }
        
        return true;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>