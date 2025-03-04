<?php
// Διαδρομή: /test/review.php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος εξουσιοδότησης
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$test_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Έλεγχος αν το τεστ υπάρχει και ανήκει στον χρήστη
$query = "SELECT * FROM test_results WHERE id = ? AND user_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $test_id, $user_id);
$stmt->execute();
$test_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$test_result) {
    header("Location: history.php");
    exit();
}

// Ανάκτηση ερωτήσεων και απαντήσεων
$query = "
    SELECT q.*, tra.user_answer_id, tra.is_correct
    FROM test_results_answers tra
    JOIN questions q ON tra.question_id = q.id
    WHERE tra.test_result_id = ?
    ORDER BY q.id ASC
";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $test_id);
$stmt->execute();
$questions_result = $stmt->get_result();
$questions = [];

while ($question = $questions_result->fetch_assoc()) {
    // Ανάκτηση απαντήσεων για κάθε ερώτηση
    $answers_query = "SELECT * FROM test_answers WHERE question_id = ? ORDER BY id ASC";
    $answers_stmt = $mysqli->prepare($answers_query);
    $answers_stmt->bind_param("i", $question['id']);
    $answers_stmt->execute();
    $answers_result = $answers_stmt->get_result();
    
    $answers = [];
    while ($answer = $answers_result->fetch_assoc()) {
        $answers[] = $answer;
    }
    
    $question['answers'] = $answers;
    $questions[] = $question;
    
    $answers_stmt->close();
}
$stmt->close();

// Τίτλος σελίδας βάσει τύπου τεστ
$test_type_titles = [
    'random' => 'Τυχαίο Τεστ',
    'chapter' => 'Τεστ ανά Κεφάλαιο',
    'simulation' => 'Τεστ Προσομοίωσης',
    'difficult' => 'Δύσκολες Ερωτήσεις'
];
$test_type_title = $test_type_titles[$test_result['test_type']] ?? 'Άγνωστος Τύπος';

// Μετατροπή χρόνου
$time_spent = $test_result['time_spent'];
$hours = floor($time_spent / 3600);
$minutes = floor(($time_spent % 3600) / 60);
$seconds = $time_spent % 60;
$time_formatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

// Κατηγορία και κεφάλαιο
$category_name = '';
$chapter_name = '';

if ($test_result['category_id']) {
    $query = "SELECT name FROM subscription_categories WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $test_result['category_id']);
    $stmt->execute();
    $category_result = $stmt->get_result();
    $category_name = $category_result->fetch_assoc()['name'] ?? 'Άγνωστη κατηγορία';
    $stmt->close();
}

if ($test_result['chapter_id']) {
    $query = "SELECT name FROM test_chapters WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $test_result['chapter_id']);
    $stmt->execute();
    $chapter_result = $stmt->get_result();
    $chapter_name = $chapter_result->fetch_assoc()['name'] ?? 'Άγνωστο κεφάλαιο';
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Ανασκόπηση Τεστ - DriveTest</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/test_review.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="main-container">
        <header class="site-header">
            <div class="logo">
                <a href="<?= BASE_URL ?>"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="DriveTest Logo"></a>
            </div>
            <nav>
                <ul>
                    <li><a href="<?= BASE_URL ?>/dashboard.php">Αρχική</a></li>
                    <li><a href="<?= BASE_URL ?>/test/history.php">Ιστορικό</a></li>
                    <li><a href="<?= BASE_URL ?>/profile.php">Προφίλ</a></li>
                    <li><a href="<?= BASE_URL ?>/logout.php">Αποσύνδεση</a></li>
                </ul>
            </nav>
        </header>

        <div class="review-container">
            <div class="review-header">
                <h1>Ανασκόπηση Τεστ</h1>
                <div class="test-info">
                    <div class="test-stats">
                        <div class="stat-item">
                            <div class="stat-label">Ημερομηνία</div>
                            <div class="stat-value"><?= date('d/m/Y H:i', strtotime($test_result['created_at'])) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Τύπος</div>
                            <div class="stat-value"><?= htmlspecialchars($test_type_title) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Κατηγορία</div>
                            <div class="stat-value"><?= htmlspecialchars($category_name) ?></div>
                        </div>
                        <?php if (!empty($chapter_name)): ?>
                        <div class="stat-item">
                            <div class="stat-label">Κεφάλαιο</div>
                            <div class="stat-value"><?= htmlspecialchars($chapter_name) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="stat-item">
                            <div class="stat-label">Βαθμολογία</div>
                            <div class="stat-value <?= $test_result['passed'] ? 'passed' : 'failed' ?>">
                                <?= $test_result['score'] ?> / <?= $test_result['total_questions'] ?>
                                (<?= round(($test_result['score'] / $test_result['total_questions']) * 100, 1) ?>%)
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Χρόνος</div>
                            <div class="stat-value"><?= $time_formatted ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Αποτέλεσμα</div>
                            <div class="stat-value <?= $test_result['passed'] ? 'passed' : 'failed' ?>">
                                <?= $test_result['passed'] ? 'Επιτυχία' : 'Αποτυχία' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="questions-review">
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-item <?= $question['is_correct'] ? 'correct' : 'incorrect' ?>">
                    <div class="question-header">
                        <div class="question-number">Ερώτηση <?= $index + 1 ?></div>
                        <div class="question-status">
                            <?php if ($question['is_correct']): ?>
                            <span class="status-badge correct">✓ Σωστή</span>
                            <?php else: ?>
                            <span class="status-badge incorrect">✗ Λάθος</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>
                    
                    <?php if (!empty($question['question_media'])): ?>
                    <div class="question-media">
                        <?php
                        $media_path = BASE_URL . '/admin/test/uploads/' . $question['question_media'];
                        $media_type = pathinfo($question['question_media'], PATHINFO_EXTENSION);
                        
                        if (in_array(strtolower($media_type), ['jpg', 'jpeg', 'png', 'gif'])):
                        ?>
                            <img src="<?= $media_path ?>" alt="Εικόνα ερώτησης" class="question-image">
                        <?php elseif (in_array(strtolower($media_type), ['mp4', 'webm'])): ?>
                            <video controls class="question-video">
                                <source src="<?= $media_path ?>" type="video/<?= $media_type ?>">
                                Ο browser σας δεν υποστηρίζει το βίντεο.
                            </video>
                        <?php elseif (in_array(strtolower($media_type), ['mp3', 'wav'])): ?>
                            <audio controls class="question-audio">
                                <source src="<?= $media_path ?>" type="audio/<?= $media_type ?>">
                                Ο browser σας δεν υποστηρίζει το ηχητικό.
                            </audio>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="answers-list">
                        <?php foreach ($question['answers'] as $answer): ?>
                        <div class="answer-item <?= $answer['is_correct'] ? 'correct-answer' : '' ?> <?= $answer['id'] == $question['user_answer_id'] ? 'user-answer' : '' ?>">
                            <div class="answer-icon">
                                <?php if ($answer['id'] == $question['user_answer_id'] && $answer['is_correct']): ?>
                                <span class="icon correct">✓</span>
                                <?php elseif ($answer['id'] == $question['user_answer_id'] && !$answer['is_correct']): ?>
                                <span class="icon incorrect">✗</span>
                                <?php elseif ($answer['is_correct']): ?>
                                <span class="icon correct">✓</span>
                                <?php else: ?>
                                <span class="icon"></span>
                                <?php endif; ?>
                            </div>
                            <div class="answer-text"><?= htmlspecialchars($answer['answer_text']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($question['question_explanation'])): ?>
                    <div class="question-explanation">
                        <div class="explanation-title">Επεξήγηση:</div>
                        <div class="explanation-text"><?= htmlspecialchars($question['question_explanation']) ?></div>
                        
                        <?php if (!empty($question['explanation_media'])): ?>
                        <div class="explanation-media">
                            <img src="<?= BASE_URL ?>/admin/test/uploads/<?= $question['explanation_media'] ?>" alt="Εικόνα επεξήγησης" class="explanation-image">
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="review-actions">
                <a href="<?= BASE_URL ?>/test/history.php" class="btn btn-secondary">Επιστροφή στο Ιστορικό</a>
                <a href="<?= BASE_URL ?>/test/start.php" class="btn btn-primary">Νέο Τεστ</a>
                <?php if (!$test_result['passed']): ?>
                <a href="<?= BASE_URL ?>/test/practice.php?result_id=<?= $test_id ?>" class="btn btn-success">Εξάσκηση στις Λάθος Απαντήσεις</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>