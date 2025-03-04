<?php
// Διαδρομή: /test/test.php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος αν υπάρχει τεστ σε εξέλιξη
if (!isset($_SESSION['current_test'])) {
    header("Location: start.php");
    exit();
}

$test = $_SESSION['current_test'];
$current_question_index = isset($_GET['q']) ? intval($_GET['q']) : 0;

// Έλεγχος εγκυρότητας δείκτη ερώτησης
if ($current_question_index < 0 || $current_question_index >= count($test['questions'])) {
    $current_question_index = 0;
}

// Έλεγχος χρονικού ορίου
$time_elapsed = time() - $test['start_time'];
$time_remaining = ($test['time_limit'] > 0) ? ($test['time_limit'] * 60) - $time_elapsed : 0;

// Αυτόματη υποβολή αν έχει λήξει ο χρόνος
if ($test['time_limit'] > 0 && $time_remaining <= 0) {
    header("Location: submit_test.php?timeout=1");
    exit();
}

// Τρέχουσα ερώτηση
$current_question = $test['questions'][$current_question_index];

// Διαχείριση αποθήκευσης απάντησης
if (isset($_POST['save_answer'])) {
    $selected_answers = $_POST['answer_ids'] ?? [];
    
    // Αποθήκευση της απάντησης
    $_SESSION['current_test']['user_answers'][$current_question['id']] = $selected_answers;
    
    // Ανακατεύθυνση στην επόμενη ερώτηση αν έχει πατηθεί το κουμπί "Επόμενη"
    if (isset($_POST['next']) && $current_question_index < count($test['questions']) - 1) {
        header("Location: test.php?q=" . ($current_question_index + 1));
        exit();
    }
    // Ανακατεύθυνση στην προηγούμενη ερώτηση αν έχει πατηθεί το κουμπί "Προηγούμενη"
    else if (isset($_POST['prev']) && $current_question_index > 0) {
        header("Location: test.php?q=" . ($current_question_index - 1));
        exit();
    }
}

// Έλεγχος αν η ερώτηση έχει ήδη απαντηθεί
$selected_answer_ids = $_SESSION['current_test']['user_answers'][$current_question['id']] ?? [];

// Υπολογισμός προόδου
$progress_percentage = round(($current_question_index + 1) / count($test['questions']) * 100);

// Μετατροπή χρόνου σε λεπτά:δευτερόλεπτα
$minutes_remaining = floor($time_remaining / 60);
$seconds_remaining = $time_remaining % 60;
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Τεστ σε Εξέλιξη - DriveTest</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/test.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>Τεστ σε Εξέλιξη</h1>
            
            <?php if ($test['time_limit'] > 0): ?>
            <div class="timer" id="timer" data-remaining="<?= $time_remaining ?>">
                <span class="timer-icon">⏱️</span>
                <span id="minutes"><?= str_pad($minutes_remaining, 2, '0', STR_PAD_LEFT) ?></span>:<span id="seconds"><?= str_pad($seconds_remaining, 2, '0', STR_PAD_LEFT) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress" style="width: <?= $progress_percentage ?>%"></div>
                </div>
                <div class="progress-text">Ερώτηση <?= $current_question_index + 1 ?> από <?= count($test['questions']) ?></div>
            </div>
        </div>
        
        <div class="question-container">
            <?php if (isset($current_question['chapter_name'])): ?>
            <div class="question-chapter">
                Κεφάλαιο: <?= htmlspecialchars($current_question['chapter_name']) ?>
            </div>
            <?php endif; ?>
            
            <div class="question-text">
                <h2><?= htmlspecialchars($current_question['question_text']) ?></h2>
            </div>
            
            <?php if (!empty($current_question['question_media'])): ?>
            <div class="question-media">
                <?php
                $media_path = BASE_URL . '/admin/test/uploads/' . $current_question['question_media'];
                $media_type = pathinfo($current_question['question_media'], PATHINFO_EXTENSION);
                
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
            
            <form method="POST" class="answer-form">
                <div class="answers-container">
                    <?php 
                    $is_multiple = $current_question['question_type'] === 'multiple_choice';
                    $input_type = $is_multiple ? 'checkbox' : 'radio';
                    
                    foreach ($current_question['answers'] as $answer): 
                        $is_selected = in_array($answer['id'], $selected_answer_ids);
                    ?>
                    <div class="answer-option <?= $is_selected ? 'selected' : '' ?>">
                        <input type="<?= $input_type ?>" id="answer_<?= $answer['id'] ?>" 
                               name="answer_ids<?= $is_multiple ? '[]' : '' ?>" 
                               value="<?= $answer['id'] ?>" 
                               <?= $is_selected ? 'checked' : '' ?>>
                        <label for="answer_<?= $answer['id'] ?>"><?= htmlspecialchars($answer['answer_text']) ?></label>
                        
                        <?php if (!empty($answer['answer_media'])): ?>
                        <div class="answer-media">
                            <img src="<?= BASE_URL ?>/admin/test/uploads/<?= $answer['answer_media'] ?>" alt="Εικόνα απάντησης" class="answer-image">
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <div class="navigation-buttons">
                        <?php if ($current_question_index > 0): ?>
                        <button type="submit" name="prev" class="btn btn-secondary">◀ Προηγούμενη</button>
                        <?php endif; ?>
                        
                        <?php if ($current_question_index < count($test['questions']) - 1): ?>
                        <button type="submit" name="next" class="btn btn-primary">Επόμενη ▶</button>
                        <?php else: ?>
                        <a href="submit_test.php" class="btn btn-success">Ολοκλήρωση Τεστ</a>
                        <?php endif; ?>
                    </div>
                    
                    <input type="hidden" name="save_answer" value="1">
                    <input type="hidden" name="question_index" value="<?= $current_question_index ?>">
                </div>
            </form>
        </div>
        
        <div class="question-navigation">
            <?php for ($i = 0; $i < count($test['questions']); $i++): 
                $q_id = $test['questions'][$i]['id'];
                $q_answered = isset($_SESSION['current_test']['user_answers'][$q_id]) && !empty($_SESSION['current_test']['user_answers'][$q_id]);
            ?>
            <a href="test.php?q=<?= $i ?>" class="nav-number <?= $i == $current_question_index ? 'active' : '' ?> <?= $q_answered ? 'answered' : '' ?>">
                <?= $i + 1 ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    
    <script>
    // Timer script for countdown
    <?php if ($test['time_limit'] > 0): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const timerElement = document.getElementById('timer');
        const minutesElement = document.getElementById('minutes');
        const secondsElement = document.getElementById('seconds');
        let timeRemaining = parseInt(timerElement.getAttribute('data-remaining'));
        
        const timerInterval = setInterval(function() {
            timeRemaining--;
            
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                // Auto submit the test
                window.location.href = 'submit_test.php?timeout=1';
                return;
            }
            
            const mins = Math.floor(timeRemaining / 60);
            const secs = timeRemaining % 60;
            
            minutesElement.textContent = String(mins).padStart(2, '0');
            secondsElement.textContent = String(secs).padStart(2, '0');
            
            // Add warning styles when time is running out
            if (timeRemaining < 60) {
                timerElement.classList.add('timer-warning');
            }
            if (timeRemaining < 30) {
                timerElement.classList.add('timer-danger');
            }
        }, 1000);
    });
    <?php endif; ?>
    </script>
</body>
</html>