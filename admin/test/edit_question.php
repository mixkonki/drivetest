<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

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
    $chapter_id = intval($_POST['chapter_id']);
    $question_text = trim($_POST['question_text']);
    $explanation = trim($_POST['explanation']);
    $question_type = $_POST['question_type'] ?? 'single_choice';
    $answers = $_POST['answers'] ?? [];
    $correct_answers = $_POST['correct_answers'] ?? [];

    $uploadDir = BASE_PATH . '/admin/test/uploads/';
    $question_media = $question['question_media'];
    if (isset($_FILES['question_media']) && $_FILES['question_media']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['question_media']['tmp_name']);
        if (in_array($fileType, $allowedTypes) && $_FILES['question_media']['size'] <= 10 * 1024 * 1024) {
            $fileName = uniqid() . '_' . basename($_FILES['question_media']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['question_media']['tmp_name'], $targetPath)) {
                $question_media = $fileName;
            }
        }
    }

    if (!empty($question_text) && $chapter_id > 0) {
        $query = "UPDATE questions SET chapter_id = ?, question_text = ?, question_explanation = ?, question_type = ?, question_media = ? WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("issssi", $chapter_id, $question_text, $explanation, $question_type, $question_media, $question_id);
        
        if ($stmt->execute()) {
            $mysqli->query("DELETE FROM test_answers WHERE question_id = $question_id");
            foreach ($answers as $index => $answer) {
                if (!empty($answer)) {
                    $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                    $query = "INSERT INTO test_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                    $stmt_answer = $mysqli->prepare($query);
                    $stmt_answer->bind_param("isi", $question_id, $answer, $is_correct);
                    $stmt_answer->execute();
                    $stmt_answer->close();
                }
            }
            header("Location: manage_questions.php?success=updated");
            exit();
        } else {
            $error = "❌ Σφάλμα: " . $stmt->error;
        }
        $stmt->close();
    } else {
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