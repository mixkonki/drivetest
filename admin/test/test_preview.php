<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';

$test_id = intval($_GET['id'] ?? 0);
if ($test_id === 0) {
    die("Δεν έχει οριστεί ID τεστ");
}

// Ανάκτηση των δεδομένων του τεστ με όλες τις ρυθμίσεις
$test_query = "SELECT tg.*, tc.name AS category_name, cf.category_id
               FROM test_generation tg
               JOIN test_configurations cf ON tg.config_id = cf.id
               JOIN test_categories tc ON cf.category_id = tc.id
               WHERE tg.id = ?";
$test_stmt = $mysqli->prepare($test_query);
$test_stmt->bind_param("i", $test_id);
$test_stmt->execute();
$test = $test_stmt->get_result()->fetch_assoc();

if (!$test) {
    die("Το τεστ δεν βρέθηκε");
}

// Ανάκτηση των ερωτήσεων του τεστ
$questions_query = "SELECT q.*, tgq.position
                   FROM test_generation_questions tgq
                   JOIN questions q ON tgq.question_id = q.id
                   WHERE tgq.test_id = ?
                   ORDER BY tgq.position";
$questions_stmt = $mysqli->prepare($questions_query);
$questions_stmt->bind_param("i", $test_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();
$questions = [];

while ($row = $questions_result->fetch_assoc()) {
    // Ανάκτηση των απαντήσεων για κάθε ερώτηση
    $answers_query = "SELECT id, answer_text, is_correct
                     FROM test_answers
                     WHERE question_id = ?
                     ORDER BY id";
    $answers_stmt = $mysqli->prepare($answers_query);
    $answers_stmt->bind_param("i", $row['id']);
    $answers_stmt->execute();
    $answers_result = $answers_stmt->get_result();
    $row['answers'] = $answers_result->fetch_all(MYSQLI_ASSOC);
    
    $questions[] = $row;
}

// Προσδιορισμός του τύπου του τεστ για τις οδηγίες
$test_type_info = '';
if ($test['is_practice']) {
    $test_type_info = 'Τεστ Εξάσκησης - Θα λάβετε ανατροφοδότηση μετά από κάθε απάντηση.';
} elseif ($test['is_simulation']) {
    $test_type_info = 'Τεστ Προσομοίωσης - Προσομοιώνει τις πραγματικές συνθήκες εξέτασης.';
} else {
    $test_type_info = 'Κανονικό Τεστ';
}

// Υπολογισμός μέγιστου σκορ (1 πόντος για κάθε ερώτηση)
$max_score = count($questions);
$pass_score = ceil(($test['pass_percentage'] / 100) * $max_score);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προεπισκόπηση Τεστ: <?= htmlspecialchars($test['test_name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/test.css">
    <style>
        /* Παραμετροποίηση χρωμάτων από τις ρυθμίσεις του τεστ */
        :root {
            --primary-color: <?= $test['primary_color'] ?? '#aa3636' ?>;
            --background-color: <?= $test['background_color'] ?? '#f5f5f5' ?>;
        }
        
        .admin-notice {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        
        body {
            padding: 20px;
            background-color: var(--background-color);
        }
        
        .test-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .test-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .test-meta {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .test-meta-item {
            margin-bottom: 8px;
            display: flex;
        }
        
        .test-meta-label {
            font-weight: bold;
            width: 200px;
            color: #555;
        }
        
        .test-meta-value {
            flex: 1;
        }
        
        .question-container {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .question-number {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .question-text {
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .answers-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .answer-option {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .answer-option:hover {
            background-color: #f8f9fa;
        }
        
        .answer-option.selected {
            background-color: #e8f4f8;
            border-color: var(--primary-color);
        }
        
        .answer-option.correct {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .answer-option.incorrect {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .answer-input {
            margin-right: 10px;
            margin-top: 3px;
        }
        
        .answer-text {
            flex: 1;
        }
        
        .explanation-container {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        
        .test-footer {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 30px;
        }
        
        .progress-container {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress {
            height: 100%;
            background-color: var(--primary-color);
            width: 0;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            margin-top: 5px;
            font-size: 14px;
            color: #666;
        }
        
        .timer-container {
            text-align: center;
            margin-bottom: 20px;
            background-color: #fff;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .timer {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .controls {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .btn-primary, .btn-secondary {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #8a2828; /* Σκουρότερη έκδοση του primary color */
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Badge για τους τύπους τεστ */
        .test-type-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
        }
        
        .practice-badge {
            background-color: #28a745;
        }
        
        .simulation-badge {
            background-color: #007bff;
        }
        
        .normal-badge {
            background-color: #6c757d;
        }
        
        /* Το κουμπί επιστροφής */
        .back-button {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: background-color 0.2s;
        }
        
        .back-button:hover {
            background-color: #e2e6ea;
            text-decoration: none;
        }
        
        /* Admin marker για τις σωστές απαντήσεις */
        .admin-marker {
            margin-left: 10px;
            font-weight: bold;
        }
        
        .correct-marker {
            color: #28a745;
        }
        
        .incorrect-marker {
            color: #dc3545;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .test-container {
                padding: 10px;
            }
            
            .back-button {
                position: static;
                margin-bottom: 20px;
                text-align: center;
                justify-content: center;
            }
            
            .controls {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <a href="<?= BASE_URL ?>/admin/test/view_test.php?id=<?= $test_id ?>" class="back-button">
        ← Επιστροφή
    </a>

    <div class="test-container">
        <div class="admin-notice">
            <strong>Προεπισκόπηση Admin</strong> - Αυτή είναι μια προεπισκόπηση του τεστ. Οι απαντήσεις και επεξηγήσεις εμφανίζονται για διευκόλυνση.
        </div>
        
        <div class="test-header">
            <h1><?= htmlspecialchars($test['test_name']) ?></h1>
            <?php if (!empty($test['label'])): ?>
                <p><?= htmlspecialchars($test['label']) ?></p>
            <?php endif; ?>
            
            <?php if ($test['is_practice']): ?>
                <span class="test-type-badge practice-badge">Τεστ Εξάσκησης</span>
            <?php elseif ($test['is_simulation']): ?>
                <span class="test-type-badge simulation-badge">Τεστ Προσομοίωσης</span>
            <?php else: ?>
                <span class="test-type-badge normal-badge">Κανονικό Τεστ</span>
            <?php endif; ?>
        </div>
        
        <div class="test-meta">
            <div class="test-meta-item">
                <div class="test-meta-label">Κατηγορία:</div>
                <div class="test-meta-value"><?= htmlspecialchars($test['category_name']) ?></div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Αριθμός Ερωτήσεων:</div>
                <div class="test-meta-value"><?= count($questions) ?></div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Χρονικό Όριο:</div>
                <div class="test-meta-value"><?= $test['time_limit'] == 0 ? 'Απεριόριστο' : $test['time_limit'] . ' λεπτά' ?></div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Ποσοστό Επιτυχίας:</div>
                <div class="test-meta-value"><?= $test['pass_percentage'] ?>% (<?= $pass_score ?>/<?= $max_score ?> ερωτήσεις)</div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Εμφάνιση Απαντήσεων:</div>
                <div class="test-meta-value">
                    <?php 
                        switch($test['display_answers_mode']) {
                            case 'end_of_test': echo 'Στο τέλος του τεστ'; break;
                            case 'after_each_question': echo 'Μετά από κάθε ερώτηση'; break;
                            case 'never': echo 'Ποτέ'; break;
                            default: echo $test['display_answers_mode'];
                        }
                    ?>
                </div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Τυχαία Σειρά Ερωτήσεων:</div>
                <div class="test-meta-value"><?= $test['randomize_questions'] ? 'Ναι' : 'Όχι' ?></div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Τυχαία Σειρά Απαντήσεων:</div>
                <div class="test-meta-value"><?= $test['randomize_answers'] ? 'Ναι' : 'Όχι' ?></div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Εμφάνιση Αρίθμησης:</div>
                <div class="test-meta-value"><?= $test['show_question_numbers'] ? 'Ναι' : 'Όχι' ?></div>
            </div>
            <div class="test-meta-item">
                <div class="test-meta-label">Οδηγίες:</div>
                <div class="test-meta-value"><?= htmlspecialchars($test_type_info) ?></div>
            </div>
        </div>
        
        <div id="test-questions">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-container" id="question-<?= $index + 1 ?>">
                    <div class="question-header">
                        <span class="question-number">Ερώτηση <?= $index + 1 ?> από <?= count($questions) ?></span>
                        <span class="question-type">
                            <?php 
                                switch($question['question_type']) {
                                    case 'single_choice': echo 'Μονής Επιλογής'; break;
                                    case 'multiple_choice': echo 'Πολλαπλής Επιλογής'; break;
                                    case 'fill_in_blank': echo 'Συμπλήρωση Κενού'; break;
                                    default: echo $question['question_type'];
                                }
                            ?>
                        </span>
                    </div>
                    
                    <div class="question-text">
                        <?= htmlspecialchars($question['question_text']) ?>
                    </div>
                    
                    <?php if ($question['question_media']): ?>
                        <div class="question-media">
                            <img src="<?= BASE_URL ?>/admin/test/uploads/<?= $question['question_media'] ?>" alt="Εικόνα ερώτησης">
                        </div>
                    <?php endif; ?>
                    
                    <div class="answers-container">
                        <?php foreach ($question['answers'] as $answer_index => $answer): ?>
                            <div class="answer-option <?= $answer['is_correct'] ? 'correct' : '' ?>" data-correct="<?= $answer['is_correct'] ?>">
                                <input type="<?= $question['question_type'] === 'multiple_choice' ? 'checkbox' : 'radio' ?>" 
                                       name="answer-<?= $question['id'] ?>" 
                                       id="answer-<?= $question['id'] ?>-<?= $answer['id'] ?>"
                                       class="answer-input"
                                       value="<?= $answer['id'] ?>">
                                <label for="answer-<?= $question['id'] ?>-<?= $answer['id'] ?>" class="answer-text">
                                    <?= htmlspecialchars($answer['answer_text']) ?>
                                </label>
                                <span class="admin-marker <?= $answer['is_correct'] ? 'correct-marker' : 'incorrect-marker' ?>">
                                    <?= $answer['is_correct'] ? '✓' : '✗' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($question['question_explanation'])): ?>
                        <div class="explanation-container" style="display: block;">
                            <strong>Επεξήγηση:</strong> <?= htmlspecialchars($question['question_explanation']) ?>
                            
                            <?php if ($question['explanation_media']): ?>
                                <div class="explanation-media" style="margin-top: 10px;">
                                    <img src="<?= BASE_URL ?>/admin/test/uploads/<?= $question['explanation_media'] ?>" alt="Εικόνα επεξήγησης">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="test-footer">
            <?php if ($test['show_progress_bar']): ?>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress" style="width: 0%"></div>
                    </div>
                    <div class="progress-text">0/<?= count($questions) ?> ερωτήσεις απαντήθηκαν</div>
                </div>
            <?php endif; ?>
            
            <?php if ($test['show_timer'] && $test['time_limit'] > 0): ?>
                <div class="timer-container">
                    <div class="timer"><?= $test['time_limit'] ?>:00</div>
                    <div>Υπολειπόμενος χρόνος</div>
                </div>
            <?php endif; ?>
            
            <div class="controls">
                <button class="btn-secondary">Προηγούμενη</button>
                <button class="btn-primary">Επόμενη</button>
                <button class="btn-primary">Υποβολή Τεστ</button>
            </div>
        </div>
    </div>
    
    <script>
        // Δοκιμαστικό script για την προεπισκόπηση
        document.addEventListener('DOMContentLoaded', function() {
            // Προσομοίωση επιλογής απαντήσεων
            const answerOptions = document.querySelectorAll('.answer-option');
            answerOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const input = this.querySelector('input');
                    input.checked = !input.checked;
                    
                    if (input.type === 'radio') {
                        // Για radio buttons, αποεπιλογή όλων των άλλων
                        const name = input.getAttribute('name');
                        document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
                            radio.closest('.answer-option').classList.remove('selected');
                        });
                    }
                    
                    this.classList.toggle('selected', input.checked);
                    updateProgress();
                });
            });
            
            // Ενημέρωση της προόδου
            function updateProgress() {
                const totalQuestions = <?= count($questions) ?>;
                const answeredQuestions = document.querySelectorAll('.answer-option.selected').length;
                const progressPercent = (answeredQuestions / totalQuestions) * 100;
                
                const progressBar = document.querySelector('.progress');
                const progressText = document.querySelector('.progress-text');
                
                if (progressBar) {
                    progressBar.style.width = `${progressPercent}%`;
                }
                
                if (progressText) {
                    progressText.textContent = `${answeredQuestions}/${totalQuestions} ερωτήσεις απαντήθηκαν`;
                }
            }
            
            // Κουμπιά πλοήγησης
            const prevButton = document.querySelector('.btn-secondary');
            const nextButton = document.querySelector('.btn-primary');
            const submitButton = document.querySelectorAll('.btn-primary')[1];
            
            let currentQuestion = 0;
            const questions = document.querySelectorAll('.question-container');
            
            // Εμφανίζει μόνο την τρέχουσα ερώτηση (αρχικά την πρώτη)
            function showCurrentQuestion() {
                questions.forEach((question, index) => {
                    question.style.display = index === currentQuestion ? 'block' : 'none';
                });
                
                // Εμφάνιση/απόκρυψη κουμπιών ανάλογα με τη θέση
                prevButton.style.visibility = currentQuestion === 0 ? 'hidden' : 'visible';
                nextButton.style.display = currentQuestion === questions.length - 1 ? 'none' : 'block';
                submitButton.style.display = currentQuestion === questions.length - 1 ? 'block' : 'none';
            }
            
            // Αρχική εμφάνιση
            if (questions.length > 0) {
                showCurrentQuestion();
            }
            
            // Κουμπί Προηγούμενο
            prevButton.addEventListener('click', function() {
                if (currentQuestion > 0) {
                    currentQuestion--;
                    showCurrentQuestion();
                }
            });
            
            // Κουμπί Επόμενο
            nextButton.addEventListener('click', function() {
                if (currentQuestion < questions.length - 1) {
                    currentQuestion++;
                    showCurrentQuestion();
                }
            });
            
            // Κουμπί Υποβολής - στην προεπισκόπηση απλά εμφανίζει όλες τις ερωτήσεις
            submitButton.addEventListener('click', function() {
                questions.forEach(question => {
                    question.style.display = 'block';
                });
                
                submitButton.style.display = 'none';
                prevButton.style.display = 'none';
                nextButton.style.display = 'none';
                
                // Προσομοίωση της υποβολής
                alert('Το τεστ υποβλήθηκε επιτυχώς!');
            });
        });
    </script>
</body>
</html>