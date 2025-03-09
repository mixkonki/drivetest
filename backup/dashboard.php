<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php'; // Έλεγχος αν είναι admin
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
        <div class="stats-card users-stats">
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

        <div class="stats-card questions-stats">
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

        <div class="stats-card tests-stats">
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

        <div class="stats-card subscriptions-stats">
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

    <div class="dashboard-panels">
        <div class="dashboard-panel recent-questions">
            <div class="panel-header">
                <h3 class="panel-title">📝 Πρόσφατες Ερωτήσεις</h3>
                <a href="test/manage_questions.php" class="panel-link">Όλες οι ερωτήσεις</a>
            </div>
            <div class="panel-content">
                <?php if (empty($recent_questions)): ?>
                    <div class="empty-state">Δεν υπάρχουν ερωτήσεις ακόμα.</div>
                <?php else: ?>
                    <div class="question-list">
                        <?php foreach ($recent_questions as $question): ?>
                            <div class="question-item">
                                <div class="question-text"><?= htmlspecialchars(mb_substr($question['question_text'], 0, 60) . (mb_strlen($question['question_text']) > 60 ? '...' : '')) ?></div>
                                <div class="question-meta">
                                    <div class="question-chapter"><?= htmlspecialchars($question['chapter_name']) ?></div>
                                    <div class="question-date"><?= date('d/m/Y', strtotime($question['created_at'])) ?></div>
                                </div>
                                <a href="test/edit_question.php?id=<?= $question['id'] ?>" class="question-edit">✏️</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-panel recent-tests">
            <div class="panel-header">
                <h3 class="panel-title">🧩 Πρόσφατα Τεστ</h3>
                <a href="test/generate_test.php" class="panel-link">Όλα τα τεστ</a>
            </div>
            <div class="panel-content">
                <?php if (empty($recent_tests)): ?>
                    <div class="empty-state">Δεν υπάρχουν τεστ ακόμα.</div>
                <?php else: ?>
                    <div class="test-list">
                        <?php foreach ($recent_tests as $test): ?>
                            <div class="test-item">
                                <div class="test-title"><?= htmlspecialchars($test['test_name']) ?></div>
                                <div class="test-meta">
                                    <div class="test-category"><?= htmlspecialchars($test['category_name']) ?></div>
                                    <div class="test-creator"><?= htmlspecialchars($test['creator_name']) ?></div>
                                    <div class="test-date"><?= date('d/m/Y', strtotime($test['created_at'])) ?></div>
                                </div>
                                <a href="test/view_test.php?id=<?= $test['id'] ?>" class="test-view">👁️</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="dashboard-quick-links">
        <h3 class="quick-links-title">⚡ Γρήγορες Ενέργειες</h3>
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