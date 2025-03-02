<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Βεβαιωνόμαστε ότι η κωδικοποίηση είναι UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Λειτουργία για καθαρισμό κωδικοποίησης
function clean_encoding($string) {
    if (empty($string)) {
        return '';
    }
    // Αφαίρεση BOM αν υπάρχει
    $string = str_replace("\xEF\xBB\xBF", '', $string);
    
    // Logging για debugging της κωδικοποίησης
    global $mysqli;
    log_debug("Processing string (raw): " . bin2hex($string) . " (length: " . strlen($string) . ")");

    // Δοκιμάζουμε τις κωδικοποιήσεις, δίνοντας προτεραιότητα στη Windows-1253
    $encodings_to_try = ['Windows-1253', 'UTF-8', 'ISO-8859-7', 'ASCII'];
    foreach ($encodings_to_try as $encoding) {
        if ($encoding === 'Windows-1253') {
            // Χρησιμοποιούμε iconv() για Windows-1253, καθώς mb_detect_encoding() δεν την υποστηρίζει
            try {
                $converted = iconv('Windows-1253', 'UTF-8//IGNORE', $string);
                if ($converted !== false) {
                    $cleaned = trim(preg_replace('/\s+/', ' ', $converted));
                    log_debug("Converted from Windows-1253 to UTF-8: $cleaned");
                    return $cleaned;
                }
            } catch (Exception $e) {
                log_debug("Failed to convert from Windows-1253: " . $e->getMessage());
                continue;
            }
        } else {
            // Δοκιμάζουμε με mb_detect_encoding() για τις υπόλοιπες κωδικοποιήσεις
            $detected = mb_detect_encoding($string, $encoding, true);
            if ($detected !== false) {
                $cleaned = trim(preg_replace('/\s+/', ' ', $string));
                log_debug("Detected encoding $encoding, cleaned: $cleaned");
                return $cleaned;
            }
        }
    }
    // Αν όλες αποτυγχάνουν, επιστρέφουμε το string ως UTF-8 με ignore για debugging
    try {
        $fallback = iconv('Windows-1253', 'UTF-8//IGNORE', $string);
        $cleaned = trim(preg_replace('/\s+/', ' ', $fallback));
        log_debug("Fallback to Windows-1253 to UTF-8: $cleaned");
        return $cleaned;
    } catch (Exception $e) {
        log_debug("Failed fallback conversion: " . $e->getMessage());
        return trim(preg_replace('/\s+/', ' ', $string)); // Επιστρέφουμε το ακατέργαστο string για debugging
    }
}

// Λειτουργία για logging (για debugging)
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

$categories = $mysqli->query("SELECT id, name FROM test_categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$subcategories = $mysqli->query("SELECT id, name, test_category_id FROM test_subcategories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$chapters = $mysqli->query("SELECT id, name, subcategory_id FROM test_chapters ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        // Μαζική εισαγωγή μέσω CSV
        $file = $_FILES['csv_file']['tmp_name'];
        $zip_file = $_FILES['zip_file']['tmp_name'] ?? null;
        $category_id = intval($_POST['category_id']);
        $subcategory_id = intval($_POST['subcategory_id']);
        $chapter_id = intval($_POST['chapter_id']);

        $uploadDir = BASE_PATH . '/admin/test/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Εξαγωγή εικόνων από ZIP (αν υπάρχει)
        $media_files = [];
        if ($zip_file && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file) === TRUE) {
                $zip->extractTo($uploadDir);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $media_files[$filename] = $uploadDir . $filename;
                }
                $zip->close();
            } else {
                $error = "❌ Απέτυχε η εξαγωγή του ZIP αρχείου.";
                log_debug("ZIP extraction failed: " . $_FILES['zip_file']['name']);
            }
        }

        $handle = fopen($file, "r");
        if ($handle !== false) {
            // Διαβάζουμε την κεφαλίδα με διαχωριστικό ερωτηματικό (;)
            $header = fgetcsv($handle, 0, ';');
            $success_count = 0; // Ορισμός της μεταβλητής $success_count
            $error_messages = [];
            $is_new_format = (stripos($header[0] ?? '', 'Question') !== false);

            log_debug("Starting CSV import with header (raw): " . implode(';', $header) . " (cleaned): " . implode(';', array_map('clean_encoding', $header)));

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                // Ελέγχουμε αν η γραμμή έχει δεδομένα
                if (empty($data) || count($data) < 4) { // Τουλάχιστον Question, Correct answer, και 1 Answer
                    $error_messages[] = "Κενή ή ελλιπής γραμμή: " . implode(';', array_map('clean_encoding', $data));
                    log_debug("Empty or incomplete line (raw): " . implode(';', $data) . " (cleaned): " . implode(';', array_map('clean_encoding', $data)));
                    continue;
                }

                if ($is_new_format) {
                    // Νέα δομή: Question;Question explanation;Correct answer;Answer 1;Answer 2;...
                    $question_text = clean_encoding($data[0] ?? '');
                    $question_explanation = clean_encoding($data[1] ?? '');
                    $correct_answer_index = isset($data[2]) ? (intval($data[2]) - 1) : -1; // Μετατροπή από 1-based σε 0-based
                    $answers = array_map('clean_encoding', array_slice($data, 3)); // Όλες οι υπόλοιπες στήλες είναι απαντήσεις
                    $question_type = (count(array_filter(array_slice($answers, 0, $correct_answer_index + 1), function($a) { return $a === '1'; })) > 1) ? 'multiple_choice' : 'single_choice';

                    // Logging για debugging των δεδομένων (πριν και μετά το καθάρισμα)
                    log_debug("Processing line - Raw Question: " . ($data[0] ?? '') . ", Cleaned Question: $question_text, Raw Explanation: " . ($data[1] ?? '') . ", Cleaned Explanation: $question_explanation, Raw Correct: " . ($data[2] ?? '') . ", Cleaned Correct: $correct_answer_index, Raw Answers: " . implode(', ', $data) . ", Cleaned Answers: " . implode(', ', $answers));

                    // Έλεγχος εγκυρότητας Κατηγορίας, Υποκατηγορίας, Κεφαλαίου
                    if (!$category_id || !$mysqli->query("SELECT id FROM test_categories WHERE id = $category_id")->num_rows) {
                        $error_messages[] = "Μη έγκυρη κατηγορία στη γραμμή: " . implode(';', array_map('clean_encoding', $data));
                        log_debug("Invalid category ID: $category_id for line (raw): " . implode(';', $data));
                        continue;
                    }
                    if (!$subcategory_id || !$mysqli->query("SELECT id FROM test_subcategories WHERE id = $subcategory_id AND test_category_id = $category_id")->num_rows) {
                        $error_messages[] = "Μη έγκυρη υποκατηγορία για την επιλεγμένη κατηγορία στη γραμμή: " . implode(';', array_map('clean_encoding', $data));
                        log_debug("Invalid subcategory ID: $subcategory_id for category $category_id in line (raw): " . implode(';', $data));
                        continue;
                    }
                    if (!$chapter_id || !$mysqli->query("SELECT id FROM test_chapters WHERE id = $chapter_id AND subcategory_id = $subcategory_id")->num_rows) {
                        $error_messages[] = "Μη έγκυρο κεφάλαιο για την επιλεγμένη υποκατηγορία στη γραμμή: " . implode(';', array_map('clean_encoding', $data));
                        log_debug("Invalid chapter ID: $chapter_id for subcategory $subcategory_id in line (raw): " . implode(';', $data));
                        continue;
                    }
                } else {
                    // Παλιά δομή: chapter_id,question_text,question_explanation,question_type,answer_1,is_correct_1,...
                    $chapter_id = isset($data[0]) ? intval($data[0]) : 0;
                    $question_text = clean_encoding($data[1] ?? '');
                    $question_explanation = clean_encoding($data[2] ?? '');
                    $question_type = $data[3] ?? 'single_choice';
                    $answers = [];
                    for ($i = 4; $i < count($data); $i += 2) {
                        if (isset($data[$i]) && !empty($data[$i])) {
                            $answers[] = ['text' => clean_encoding($data[$i]), 'is_correct' => isset($data[$i + 1]) ? intval($data[$i + 1]) : 0];
                        }
                    }
                }

                // Έλεγχος εγκυρότητας
                if (empty($question_text) || !$chapter_id || !$mysqli->query("SELECT id FROM test_chapters WHERE id = $chapter_id")->num_rows) {
                    $error_messages[] = "Μη έγκυρη ερώτηση ή κεφάλαιο στη γραμμή: " . implode(';', array_map('clean_encoding', $data));
                    log_debug("Invalid question or chapter for line (raw): " . implode(';', $data));
                    continue;
                }

                // Εύρεση εικόνων από το ZIP (αν υπάρχουν)
                $question_media = '';
                $explanation_media = '';
                $answer_media = [];
                $media_prefix = $success_count; // Χρήση του $success_count για την αρίθμηση των αρχείων εικόνας

                // Έλεγχος για εικόνες, αν υπάρχουν
                if (!empty($media_files)) {
                    $question_media = $media_files["question_$media_prefix.png"] ?? '';
                    $explanation_media = $media_files["explanation_$media_prefix.png"] ?? '';
                    foreach ($answers as $index => $answer) {
                        $answer_media[$index] = $media_files["answer_$media_prefix_" . ($index + 1) . ".png"] ?? '';
                    }
                }

                // Εισαγωγή ερώτησης
                $author_id = $_SESSION['user_id'] ?? 1;
                $status = 'active'; // Ορισμός του status ως 'active'
                $query = "INSERT INTO questions (chapter_id, question_text, question_explanation, question_type, author_id, question_media, explanation_media, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("isssisss", $chapter_id, $question_text, $question_explanation, $question_type, $author_id, $question_media, $explanation_media, $status);
                
                if ($stmt->execute()) {
                    $question_id = $stmt->insert_id;
                    $success_count++;
                    log_debug("Successfully inserted question ID: $question_id for chapter $chapter_id - Question (raw): " . ($data[0] ?? '') . ", Question (cleaned): $question_text");

                    // Εισαγωγή απαντήσεων
                    foreach ($answers as $index => $answer) {
                        if ($is_new_format) {
                            $answer_text = $answer;
                            $is_correct = ($index == $correct_answer_index) ? 1 : 0;
                        } else {
                            $answer_text = $answer['text'];
                            $is_correct = $answer['is_correct'];
                        }
                        if (!empty($answer_text)) {
                            $answer_media_path = $answer_media[$index] ?? '';
                            $query = "INSERT INTO test_answers (question_id, answer_text, is_correct, answer_media) VALUES (?, ?, ?, ?)";
                            $stmt_answer = $mysqli->prepare($query);
                            $stmt_answer->bind_param("isis", $question_id, $answer_text, $is_correct, $answer_media_path);
                            if ($stmt_answer->execute()) {
                                log_debug("Inserted answer for question $question_id: $answer_text (raw: " . ($data[3 + $index] ?? '') . "), is_correct: $is_correct");
                            } else {
                                $error_messages[] = "Σφάλμα εισαγωγής απάντησης για ερώτηση ID $question_id: " . $stmt_answer->error;
                                log_debug("Failed to insert answer for question $question_id: " . $stmt_answer->error);
                            }
                            $stmt_answer->close();
                        }
                    }
                } else {
                    $error_messages[] = "Σφάλμα εισαγωγής ερώτησης στη γραμμή: " . implode(';', array_map('clean_encoding', $data)) . " - " . $stmt->error;
                    log_debug("Failed to insert question for line (raw): " . implode(';', $data) . " - " . $stmt->error);
                }
                $stmt->close();
            }
            fclose($handle);

            $result_message = "✅ Εισήχθησαν $success_count ερωτήσεις επιτυχώς.";
            if (!empty($error_messages)) {
                $result_message .= "<br>❌ Σφάλματα:<ul><li>" . implode('</li><li>', $error_messages) . "</li></ul>";
            }
        } else {
            $error = "❌ Απέτυχε η ανάγνωση του αρχείου CSV.";
            log_debug("Failed to open CSV file: $file");
        }
    } else {
        // Μονή εισαγωγή (υπάρχουσα λογική)
        $chapter_id = intval($_POST['chapter_id']);
        $question_text = trim($_POST['question_text']);
        $explanation = trim($_POST['explanation']);
        $question_type = $_POST['question_type'] ?? 'single_choice';
        $answers = $_POST['answers'] ?? [];
        $correct_answers = $_POST['correct_answers'] ?? [];

        $uploadDir = BASE_PATH . '/admin/test/uploads/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

        $question_media = '';
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
            $author_id = $_SESSION['user_id'] ?? 1;
            $status = 'active'; // Ορισμός του status ως 'active'
            $query = "INSERT INTO questions (chapter_id, question_text, question_explanation, question_type, author_id, question_media, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("isssiss", $chapter_id, $question_text, $explanation, $question_type, $author_id, $question_media, $status);
            
            if ($stmt->execute()) {
                $question_id = $stmt->insert_id;
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
                header("Location: manage_questions.php?success=added");
                exit();
            } else {
                $error = "❌ Σφάλμα: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "❌ Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προσθήκη Ερώτησης</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/test_styles.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>
<main class="admin-container">
    <h2>➕ Προσθήκη Ερώτησης</h2>
    <?php 
    if (isset($error)) echo "<p style='color: red;'>$error</p>"; 
    if (isset($result_message)) echo "<p>$result_message</p>"; 
    ?>

    <!-- Μονή Εισαγωγή -->
    <h3>Μονή Ερώτηση</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="category_id">Κατηγορία Τεστ:</label>
            <select name="category_id" id="category_id" required>
                <option value="">Επιλέξτε Κατηγορία</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="subcategory_id">Υποκατηγορία:</label>
            <select name="subcategory_id" id="subcategory_id" required>
                <option value="">Επιλέξτε Υποκατηγορία</option>
                <?php foreach ($subcategories as $sub): ?>
                    <option value="<?= $sub['id'] ?>" data-category="<?= $sub['test_category_id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="chapter_id">Κεφάλαιο:</label>
            <select name="chapter_id" id="chapter_id" required>
                <option value="">Επιλέξτε Κεφάλαιο</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= $ch['id'] ?>" data-subcategory="<?= $ch['subcategory_id'] ?>"><?= htmlspecialchars($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="question_text">Κείμενο Ερώτησης:</label>
            <textarea name="question_text" id="question_text" required></textarea>
        </div>

        <div class="form-group">
            <label for="question_type">Τύπος Ερώτησης:</label>
            <select name="question_type" id="question_type">
                <option value="single_choice">Πολλαπλής Επιλογής (1 σωστή)</option>
                <option value="multiple_choice">Πολλαπλών Σωστών</option>
                <option value="fill_in_blank">Συμπλήρωση Κενών</option>
            </select>
        </div>

        <div class="form-group">
            <label>Απαντήσεις:</label>
            <div id="answers_container">
                <?php for ($i = 0; $i < 3; $i++): ?>
                <input type="text" name="answers[]" placeholder="Απάντηση <?= $i + 1 ?>">
                <input type="checkbox" name="correct_answers[]" value="<?= $i ?>"> Σωστή
                <br>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="question_media">Multimedia Ερώτησης:</label>
            <input type="file" name="question_media" id="question_media" accept="image/*,video/*,audio/*">
        </div>

        <div class="form-group">
            <label for="explanation">Επεξήγηση:</label>
            <textarea name="explanation" id="explanation"></textarea>
        </div>

        <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
    </form>

    <!-- Μαζική Εισαγωγή -->
    <h3>Μαζική Εισαγωγή μέσω CSV</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="category_id">Κατηγορία Τεστ:</label>
            <select name="category_id" id="category_id" required>
                <option value="">Επιλέξτε Κατηγορία</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="subcategory_id">Υποκατηγορία:</label>
            <select name="subcategory_id" id="subcategory_id" required>
                <option value="">Επιλέξτε Υποκατηγορία</option>
                <?php foreach ($subcategories as $sub): ?>
                    <option value="<?= $sub['id'] ?>" data-category="<?= $sub['test_category_id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="chapter_id">Κεφάλαιο:</label>
            <select name="chapter_id" id="chapter_id" required>
                <option value="">Επιλέξτε Κεφάλαιο</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= $ch['id'] ?>" data-subcategory="<?= $ch['subcategory_id'] ?>"><?= htmlspecialchars($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="csv_file">Ανέβασμα CSV:</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            <p>Υποστηριζόμενη μορφή:</p>
            <ul>
                <li>Question;Question explanation;Correct answer;Answer 1;Answer 2;... (με διαχωριστικό ;)</li>
            </ul>
        </div>
        <div class="form-group">
            <label for="zip_file">Ανέβασμα ZIP με εικόνες (προαιρετικό):</label>
            <input type="file" name="zip_file" id="zip_file" accept=".zip">
            <p>Ονόματα αρχείων: question_X.png, explanation_X.png, answer_X_Y.png (X = αριθμός γραμμής, Y = αριθμός απάντησης, 0-based)</p>
        </div>
        <button type="submit" class="btn-primary">📤 Εισαγωγή</button>
    </form>
</main>
<?php require_once '../includes/admin_footer.php'; ?>
</body>
</html>