<?php
// Ορισμός τίτλου σελίδας
$page_title = 'Πίνακας Διαχείρισης';

// Φόρτωση των απαραίτητων αρχείων
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php'; // Έλεγχος αν είναι admin

// Προσθήκη γραφημάτων αν χρειάζεται (θα φορτωθεί αυτόματα από το admin_scripts.php)
$load_chart_js = true;

// Δεν χρειάζεται το flag για το dashboard.js πλέον, 
// γιατί θα φορτωθεί αυτόματα βάσει του ονόματος της σελίδας

require_once __DIR__ . '/includes/admin_header.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Ανάκτηση στατιστικών από τη βάση
$users_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $users_count = $result->fetch_assoc()['count'];
}

$schools_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role='school'");
if ($result) {
    $schools_count = $result->fetch_assoc()['count'];
}

$questions_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM questions WHERE 1=1");
if ($result) {
    $questions_count = $result->fetch_assoc()['count'] ?? 0;
}

$active_subscriptions_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'");
if ($result) {
    $active_subscriptions_count = $result->fetch_assoc()['count'] ?? 0;
}

$tests_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM test_generation");
if ($result) {
    $tests_count = $result->fetch_assoc()['count'] ?? 0;
}

$revenue = 0;
$result = $mysqli->query("SELECT SUM(price) as total FROM subscriptions s JOIN subscription_categories sc ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON)) WHERE s.status = 'active'");
if ($result) {
    $revenue = $result->fetch_assoc()['total'] ?? 0;
}

$categories_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM test_categories");
if ($result) {
    $categories_count = $result->fetch_assoc()['count'] ?? 0;
}

$subcategories_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM test_subcategories");
if ($result) {
    $subcategories_count = $result->fetch_assoc()['count'] ?? 0;
}

$chapters_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM test_chapters");
if ($result) {
    $chapters_count = $result->fetch_assoc()['count'] ?? 0;
}

// Ανάκτηση πρόσφατων ερωτήσεων
$recent_questions_query = "SELECT q.id, q.question_text, c.name AS chapter_name, q.created_at
                         FROM questions q
                         JOIN test_chapters c ON q.chapter_id = c.id
                         ORDER BY q.created_at DESC
                         LIMIT 5";
$recent_questions_result = $mysqli->query($recent_questions_query);
$recent_questions = $recent_questions_result ? $recent_questions_result->fetch_all(MYSQLI_ASSOC) : [];

// Ανάκτηση πρόσφατων τεστ
$recent_tests_query = "SELECT tg.id, tg.test_name, tg.created_at, c.name AS category_name, u.fullname AS creator_name
                      FROM test_generation tg
                      JOIN test_configurations cf ON tg.config_id = cf.id
                      JOIN test_categories c ON cf.category_id = c.id
                      JOIN users u ON tg.created_by = u.id
                      ORDER BY tg.created_at DESC
                      LIMIT 5";
$recent_tests_result = $mysqli->query($recent_tests_query);
$recent_tests = $recent_tests_result ? $recent_tests_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<main class="admin-container" role="main" aria-label="Πίνακας Διαχείρισης Admin">
    <div class="dashboard-header">
        <h1 class="dashboard-title">📊 Πίνακας Διαχείρισης</h1>
        <div class="dashboard-actions">
            <a href="test/add_question.php" class="btn-primary">➕ Προσθήκη Ερώτησης</a>
            <a href="test/bulk_import.php" class="btn-primary">📥 Μαζική Εισαγωγή</a>
            <a href="test/generate_test.php" class="btn-primary">🧩 Δημιουργία Τεστ</a>
        </div>
    </div>
    
    <div class="dashboard-stats-container">
        <div class="stats-card users-stats" style="opacity: 0; transform: translateY(20px);">
            <div class="stats-icon">👥</div>
            <div class="stats-content">
                <h3>Χρήστες</h3>
                <div class="stats-numbers">
                    <div class="stats-number">
                        <div class="stats-value"><?= $users_count ?></div>
                        <div class="stats-label">Συνολικά</div>
                    </div>
                    <div class="stats-number">
                        <div class="stats-value"><?= $schools_count ?></div>
                        <div class="stats-label">Σχολές</div>
                    </div>
                </div>
                <a href="users.php" class="stats-link">Διαχείριση Χρηστών →</a>
            </div>
        </div>
        
        <div class="stats-card questions-stats" style="opacity: 0; transform: translateY(20px);">
            <div class="stats-icon">❓</div>
            <div class="stats-content">
                <h3>Ερωτήσεις & Κατηγορίες</h3>
                <div class="stats-numbers">
                    <div class="stats-number">
                        <div class="stats-value"><?= $questions_count ?></div>
                        <div class="stats-label">Ερωτήσεις</div>
                    </div>
                    <div class="stats-number">
                        <div class="stats-value"><?= $categories_count ?></div>
                        <div class="stats-label">Κατηγορίες</div>
                    </div>
                    <div class="stats-number">
                        <div class="stats-value"><?= $subcategories_count ?></div>
                        <div class="stats-label">Υποκατηγορίες</div>
                    </div>
                    <div class="stats-number">
                        <div class="stats-value"><?= $chapters_count ?></div>
                        <div class="stats-label">Κεφάλαια</div>
                    </div>
                </div>
                <div class="stats-links">
                    <a href="test/manage_questions.php" class="stats-link">Διαχείριση Ερωτήσεων →</a>
                    <a href="test/manage_subcategories.php" class="stats-link">Διαχείριση Υποκατηγοριών →</a>
                    <a href="test/manage_chapters.php" class="stats-link">Διαχείριση Κεφαλαίων →</a>
                </div>
            </div>
        </div>
        
        <div class="stats-card tests-stats" style="opacity: 0; transform: translateY(20px);">
            <div class="stats-icon">🧩</div>
            <div class="stats-content">
                <h3>Τεστ</h3>
                <div class="stats-numbers">
                    <div class="stats-number">
                        <div class="stats-value"><?= $tests_count ?></div>
                        <div class="stats-label">Δημιουργημένα</div>
                    </div>
                </div>
                <div class="stats-links">
                    <a href="test/test_config.php" class="stats-link">Ρυθμίσεις Τεστ →</a>
                    <a href="test/generate_test.php" class="stats-link">Δημιουργία Τεστ →</a>
                </div>
            </div>
        </div>
        
        <div class="stats-card subscriptions-stats" style="opacity: 0; transform: translateY(20px);">
            <div class="stats-icon">💰</div>
            <div class="stats-content">
                <h3>Συνδρομές</h3>
                <div class="stats-numbers">
                    <div class="stats-number">
                        <div class="stats-value"><?= $active_subscriptions_count ?></div>
                        <div class="stats-label">Ενεργές</div>
                    </div>
                    <div class="stats-number">
                        <div class="stats-value"><?= number_format($revenue, 2) ?> €</div>
                        <div class="stats-label">Έσοδα</div>
                    </div>
                </div>
                <a href="admin_subscriptions.php" class="stats-link">Διαχείριση Συνδρομών →</a>
            </div>
        </div>
    </div>
    

    
    <div class="dashboard-quick-links">
        <h3 class="quick-links-title">⚡ Γρήγορες Ενέργειες</h3>
        
        <!-- Προσθήκη αναζήτησης (προαιρετικά) -->
        <!--
        <div class="quick-links-search">
            <input type="text" id="quickLinksSearch" placeholder="Αναζήτηση ενέργειας..." class="form-control">
        </div>
        -->
        
        <div class="quick-links-grid">
            <a href="users.php" class="quick-link">
                <div class="quick-link-icon">👥</div>
                <div class="quick-link-label">Διαχείριση Χρηστών</div>
            </a>
            <a href="admin_subscriptions.php" class="quick-link">
                <div class="quick-link-icon">💳</div>
                <div class="quick-link-label">Διαχείριση Συνδρομών</div>
            </a>
            <a href="test/manage_subcategories.php" class="quick-link">
                <div class="quick-link-icon">📑</div>
                <div class="quick-link-label">Διαχείριση Υποκατηγοριών</div>
            </a>
            <a href="test/manage_chapters.php" class="quick-link">
                <div class="quick-link-icon">📚</div>
                <div class="quick-link-label">Διαχείριση Κεφαλαίων</div>
            </a>
            <a href="test/manage_questions.php" class="quick-link">
                <div class="quick-link-icon">❓</div>
                <div class="quick-link-label">Διαχείριση Ερωτήσεων</div>
            </a>
            <a href="test/test_config.php" class="quick-link">
                <div class="quick-link-icon">⚙️</div>
                <div class="quick-link-label">Ρυθμίσεις Τεστ</div>
            </a>
            <a href="test/generate_test.php" class="quick-link">
                <div class="quick-link-icon">🧩</div>
                <div class="quick-link-label">Δημιουργία Τεστ</div>
            </a>
            <a href="test/bulk_import.php" class="quick-link">
                <div class="quick-link-icon">📥</div>
                <div class="quick-link-label">Μαζική Εισαγωγή</div>
            </a>
        </div>
    </div>
</main>

<?php require_once 'includes/admin_footer.php'; ?>