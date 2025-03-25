
<?php

// Ορίζουμε την κωδικοποίηση σε UTF-8

require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';
//mysqli_set_charset($mysqli, "utf8mb4");

$question_id = intval($_GET['id'] ?? 0);
if ($question_id === 0) {
    header("Location: manage_questions.php");
    exit();
}

$question = $mysqli->query("SELECT * FROM questions WHERE id = $question_id")->fetch_assoc();
if (!$question) {
    header("Location: manage_questions.php?error=not_found");
    exit();
}

$answers = $mysqli->query("SELECT * FROM test_answers WHERE question_id = $question_id")->fetch_all(MYSQLI_ASSOC);
$categories = $mysqli->query("SELECT id, name FROM test_categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$chapters = $mysqli->query("SELECT id, name FROM test_chapters ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Καταγραφή δεδομένων για debugging
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Επεξεργασία ερώτησης ID: $question_id\n", FILE_APPEND);
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
    
    $chapter_id = intval($_POST['chapter_id']);
    $question_text = trim($_POST['question_text']);
    $explanation = trim($_POST['explanation'] ?? '');
    $question_type = $_POST['question_type'] ?? 'single_choice';
    $answers = $_POST['answers'] ?? [];
    $correct_answers = $_POST['correct_answers'] ?? [];
    
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Correct answers: " . print_r($correct_answers, true) . "\n", FILE_APPEND);

    $uploadDir = BASE_PATH . '/admin/test/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $question_media = $question['question_media']; // Διατήρηση του υπάρχοντος αν δεν ανεβάσουμε νέο
    if (isset($_FILES['question_media']) && $_FILES['question_media']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['question_media']['tmp_name']);
        if (in_array($fileType, $allowedTypes) && $_FILES['question_media']['size'] <= 10 * 1024 * 1024) {
            $fileName = uniqid() . '_' . basename($_FILES['question_media']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['question_media']['tmp_name'], $targetPath)) {
                $question_media = $fileName;
                file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Νέο media: $question_media\n", FILE_APPEND);
            }
        }
    }

    if (!empty($question_text) && $chapter_id > 0) {
        $query = "UPDATE questions SET chapter_id = ?, question_text = ?, question_explanation = ?, question_type = ?, question_media = ? WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        
        if (!$stmt) {
            file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Σφάλμα προετοιμασίας: " . $mysqli->error . "\n", FILE_APPEND);
            $error = "❌ Σφάλμα προετοιμασίας: " . $mysqli->error;
        } else {
            $stmt->bind_param("issssi", $chapter_id, $question_text, $explanation, $question_type, $question_media, $question_id);
            
            if ($stmt->execute()) {
                // Διαγραφή παλιών απαντήσεων
                $delete_result = $mysqli->query("DELETE FROM test_answers WHERE question_id = $question_id");
                if (!$delete_result) {
                    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Σφάλμα διαγραφής απαντήσεων: " . $mysqli->error . "\n", FILE_APPEND);
                }
                
                // Εισαγωγή νέων απαντήσεων
                $success_count = 0;
                foreach ($answers as $index => $answer) {
                    if (!empty($answer)) {
                        $is_correct = in_array((string)$index, $correct_answers) ? 1 : 0;
                        $query = "INSERT INTO test_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                        $stmt_answer = $mysqli->prepare($query);
                        
                        if (!$stmt_answer) {
                            file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Σφάλμα προετοιμασίας απάντησης: " . $mysqli->error . "\n", FILE_APPEND);
                            continue;
                        }
                        
                        $stmt_answer->bind_param("isi", $question_id, $answer, $is_correct);
                        if ($stmt_answer->execute()) {
                            $success_count++;
                        } else {
                            file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Σφάλμα εισαγωγής απάντησης: " . $stmt_answer->error . "\n", FILE_APPEND);
                        }
                        $stmt_answer->close();
                    }
                }
                
                file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Επιτυχής ενημέρωση! Εισήχθησαν $success_count απαντήσεις.\n", FILE_APPEND);
                header("Location: manage_questions.php?success=updated");
                exit();
            } else {
                file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Σφάλμα εκτέλεσης: " . $stmt->error . "\n", FILE_APPEND);
                $error = "❌ Σφάλμα: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . "Λείπουν υποχρεωτικά πεδία. question_text: " . (empty($question_text) ? 'ΚΕΝΟ' : 'OK') . ", chapter_id: $chapter_id\n", FILE_APPEND);
        $error = "❌ Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Ερώτησης</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/test_styles.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>
<main class="admin-container">
    <h2>✏️ Επεξεργασία Ερώτησης (ID: <?= $question_id ?>)</h2>
    <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="category_id">Κατηγορία Τεστ:</label>
            <select name="category_id" id="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $question['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="chapter_id">Κεφάλαιο:</label>
            <select name="chapter_id" id="chapter_id" required>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= $ch['id'] ?>" <?= $ch['id'] == $question['chapter_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="question_text">Κείμενο Ερώτησης:</label>
            <textarea name="question_text" id="question_text" required><?= htmlspecialchars($question['question_text']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="question_type">Τύπος Ερώτησης:</label>
            <select name="question_type" id="question_type">
                <option value="single_choice" <?= $question['question_type'] === 'single_choice' ? 'selected' : '' ?>>Πολλαπλής Επιλογής (1 σωστή)</option>
                <option value="multiple_choice" <?= $question['question_type'] === 'multiple_choice' ? 'selected' : '' ?>>Πολλαπλών Σωστών</option>
                <option value="fill_in_blank" <?= $question['question_type'] === 'fill_in_blank' ? 'selected' : '' ?>>Συμπλήρωση Κενών</option>
            </select>
        </div>

        <div class="form-group">
            <label>Απαντήσεις:</label>
            <div id="answers_container">
                <?php foreach ($answers as $index => $answer): ?>
                <input type="text" name="answers[]" value="<?= htmlspecialchars($answer['answer_text']) ?>" placeholder="Απάντηση <?= $index + 1 ?>">
                <input type="checkbox" name="correct_answers[]" value="<?= $index ?>" <?= $answer['is_correct'] ? 'checked' : '' ?>> Σωστή
                <br>
                <?php endforeach; ?>
                <?php if (count($answers) < 3) for ($i = count($answers); $i < 3; $i++): ?>
                <input type="text" name="answers[]" placeholder="Απάντηση <?= $i + 1 ?>">
                <input type="checkbox" name="correct_answers[]" value="<?= $i ?>"> Σωστή
                <br>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="question_media">Multimedia Ερώτησης:</label>
            <input type="file" name="question_media" id="question_media" accept="image/*,video/*,audio/*">
            <?php if ($question['question_media']): ?>
                <p>Τρέχουσα: <?= htmlspecialchars($question['question_media']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="explanation">Επεξήγηση:</label>
            <textarea name="explanation" id="explanation"><?= htmlspecialchars($question['question_explanation']) ?></textarea>
        </div>

        <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
    </form>
</main>
<?php require_once '../includes/admin_footer.php'; ?>
</body>
</html>