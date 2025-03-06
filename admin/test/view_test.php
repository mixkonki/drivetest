<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Λήψη του test_id από το query string
$test_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($test_id === 0) {
    die("<p class='error-message'>Απαιτείται αναγνωριστικό τεστ.</p>");
}

// Ανάκτηση των στοιχείων του τεστ
$test_query = "SELECT tg.*, tc.name AS category_name, cf.selection_method, u.fullname AS creator_name
              FROM test_generation tg
              JOIN test_configurations cf ON tg.config_id = cf.id
              JOIN test_categories tc ON cf.category_id = tc.id
              JOIN users u ON tg.created_by = u.id
              WHERE tg.id = ?";
$test_stmt = $mysqli->prepare($test_query);
$test_stmt->bind_param("i", $test_id);
$test_stmt->execute();
$test = $test_stmt->get_result()->fetch_assoc();

if (!$test) {
    die("<p class='error-message'>Το τεστ δεν βρέθηκε.</p>");
}

// Ανάκτηση των ερωτήσεων του τεστ
$questions_query = "SELECT tgq.position, q.id, q.question_text, q.question_type, ch.name AS chapter_name
                  FROM test_generation_questions tgq
                  JOIN questions q ON tgq.question_id = q.id
                  JOIN test_chapters ch ON q.chapter_id = ch.id
                  WHERE tgq.test_id = ?
                  ORDER BY tgq.position ASC";
$questions_stmt = $mysqli->prepare($questions_query);
$questions_stmt->bind_param("i", $test_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();
$questions = $questions_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προβολή Τεστ</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin_styles.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/view_test.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<main class="admin-container">
    <h2 class="admin-title">🔍 Προβολή Τεστ: <?= htmlspecialchars($test['test_name']) ?></h2>
    
    <div class="admin-section">
        <h3>Στοιχεία Τεστ</h3>
        <div class="test-details">
            <div class="detail-item">
                <span class="detail-label">Όνομα:</span>
                <span class="detail-value"><?= htmlspecialchars($test['test_name']) ?></span>
            </div>
            <?php if (!empty($test['label'])): ?>
            <div class="detail-item">
                <span class="detail-label">Ετικέτα:</span>
                <span class="detail-value"><?= htmlspecialchars($test['label']) ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-item">
                <span class="detail-label">Κατηγορία:</span>
                <span class="detail-value"><?= htmlspecialchars($test['category_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Αριθμός Ερωτήσεων:</span>
                <span class="detail-value"><?= $test['questions_count'] ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Χρονικό Όριο:</span>
                <span class="detail-value"><?= $test['time_limit'] ?> λεπτά</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Ποσοστό Επιτυχίας:</span>
                <span class="detail-value"><?= $test['pass_percentage'] ?>%</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Μέθοδος Επιλογής Ερωτήσεων:</span>
                <span class="detail-value">
                    <?php 
                        switch($test['selection_method']) {
                            case 'random': echo 'Τυχαία'; break;
                            case 'proportional': echo 'Αναλογική'; break;
                            case 'fixed': echo 'Σταθερός αριθμός ανά κεφάλαιο'; break;
                            default: echo $test['selection_method'];
                        }
                    ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Κατάσταση:</span>
                <span class="detail-value"><?= $test['status'] === 'active' ? 'Ενεργό' : 'Ανενεργό' ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Δημιουργός:</span>
                <span class="detail-value"><?= htmlspecialchars($test['creator_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Ημερομηνία Δημιουργίας:</span>
                <span class="detail-value"><?= date('d/m/Y H:i', strtotime($test['created_at'])) ?></span>
            </div>
        </div>
    </div>
    
    <div class="admin-section">
        <h3>Ερωτήσεις Τεστ (<?= count($questions) ?>)</h3>
        <?php if (empty($questions)): ?>
            <p class="info-message">Δεν βρέθηκαν ερωτήσεις για αυτό το τεστ.</p>
        <?php else: ?>
            <div class="questions-list">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item">
                        <div class="question-header">
                            <span class="question-number">#<?= $index + 1 ?></span>
                            <span class="question-type">
                                <?php 
                                    switch($question['question_type']) {
                                        case 'single_choice': echo 'Μονής Επιλογής'; break;
                                        case 'multiple_choice': echo 'Πολλαπλής Επιλογής'; break;
                                        case 'fill_in_blank': echo 'Συμπλήρωσης Κενών'; break;
                                        default: echo $question['question_type'];
                                    }
                                ?>
                            </span>
                            <span class="question-chapter"><?= htmlspecialchars($question['chapter_name']) ?></span>
                        </div>
                        <div class="question-text">
                            <?= htmlspecialchars($question['question_text']) ?>
                        </div>
                        <div class="question-answers" id="answers-<?= $question['id'] ?>" data-question-id="<?= $question['id'] ?>">
                            <p class="loading-answers">Φόρτωση απαντήσεων...</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="admin-actions">
        <a href="generate_test.php" class="btn-secondary">🔙 Επιστροφή</a>
        <button class="btn-primary" id="print-test">🖨️ Εκτύπωση</button>
        <button class="btn-primary" id="export-test">📥 Εξαγωγή PDF</button>
    </div>
</main>

<script src="<?= BASE_URL ?>/admin/assets/js/view_test.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>
</body>
</html>