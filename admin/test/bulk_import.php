<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Ορισμός κωδικοποίησης
mysqli_set_charset($mysqli, "utf8mb4");

// Καταγραφή για εντοπισμό σφαλμάτων
function logDebug($message) {
    $logFile = BASE_PATH . '/admin/test/debug_log.txt';
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp [BULK_IMPORT] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Καταστάσεις φόρμας
$preview_mode = false;
$import_success = false;
$import_error = null;
$total_imported = 0;
$failed_imports = 0;
$successfulImports = [];
$errorLines = [];
$previewData = [];
$file_headers = [];
$created_categories = [];
$created_subcategories = [];
$created_chapters = [];

// Ανάκτηση δεδομένων
$categories_query = "SELECT id, name FROM test_categories ORDER BY name ASC";
$categories_result = $mysqli->query($categories_query);
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

$subcategories_query = "SELECT s.id, s.name, s.test_category_id FROM test_subcategories s ORDER BY s.name ASC";
$subcategories_result = $mysqli->query($subcategories_query);
$subcategories = $subcategories_result ? $subcategories_result->fetch_all(MYSQLI_ASSOC) : [];

$chapters_query = "SELECT c.id, c.name, c.subcategory_id FROM test_chapters c ORDER BY c.name ASC";
$chapters_result = $mysqli->query($chapters_query);
$chapters = $chapters_result ? $chapters_result->fetch_all(MYSQLI_ASSOC) : [];

// Συναρτήσεις (αμετάβλητες)
function findOrCreateCategory($mysqli, $name) { global $created_categories; if (empty($name)) return 0; $name = trim($name); $stmt = $mysqli->prepare("SELECT id FROM test_categories WHERE name = ?"); $stmt->bind_param("s", $name); $stmt->execute(); $result = $stmt->get_result(); if ($row = $result->fetch_assoc()) return $row['id']; $stmt = $mysqli->prepare("INSERT INTO test_categories (name) VALUES (?)"); $stmt->bind_param("s", $name); $stmt->execute(); $new_id = $mysqli->insert_id; $created_categories[] = ['id' => $new_id, 'name' => $name]; logDebug("Δημιουργήθηκε κατηγορία: $name (ID: $new_id)"); return $new_id; }
function findOrCreateSubcategory($mysqli, $name, $category_id) { global $created_subcategories; if (empty($name) || $category_id <= 0) return 0; $name = trim($name); $stmt = $mysqli->prepare("SELECT id FROM test_subcategories WHERE name = ? AND test_category_id = ?"); $stmt->bind_param("si", $name, $category_id); $stmt->execute(); $result = $stmt->get_result(); if ($row = $result->fetch_assoc()) return $row['id']; $stmt = $mysqli->prepare("INSERT INTO test_subcategories (name, test_category_id) VALUES (?, ?)"); $stmt->bind_param("si", $name, $category_id); $stmt->execute(); $new_id = $mysqli->insert_id; $created_subcategories[] = ['id' => $new_id, 'name' => $name, 'category_id' => $category_id]; logDebug("Δημιουργήθηκε υποκατηγορία: $name (ID: $new_id)"); return $new_id; }
function findOrCreateChapter($mysqli, $name, $subcategory_id) { global $created_chapters; if (empty($name) || $subcategory_id <= 0) return 0; $name = trim($name); $stmt = $mysqli->prepare("SELECT id FROM test_chapters WHERE name = ? AND subcategory_id = ?"); $stmt->bind_param("si", $name, $subcategory_id); $stmt->execute(); $result = $stmt->get_result(); if ($row = $result->fetch_assoc()) return $row['id']; $stmt = $mysqli->prepare("INSERT INTO test_chapters (name, subcategory_id) VALUES (?, ?)"); $stmt->bind_param("si", $name, $subcategory_id); $stmt->execute(); $new_id = $mysqli->insert_id; $created_chapters[] = ['id' => $new_id, 'name' => $name, 'subcategory_id' => $subcategory_id]; logDebug("Δημιουργήθηκε κεφάλαιο: $name (ID: $new_id)"); return $new_id; }
function clean_encoding($string) { if (empty($string)) return ''; $string = str_replace("\xEF\xBB\xBF", '', $string); $encodings = ['UTF-8', 'ISO-8859-7', 'Windows-1253']; foreach ($encodings as $encoding) { $decoded = @mb_convert_encoding($string, 'UTF-8', $encoding); if ($decoded !== false) { $string = $decoded; break; } } return trim(preg_replace('/\s+/', ' ', $string)); }

// Προεπισκόπηση CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $preview_mode = true;
    $file = $_FILES['csv_file']['tmp_name'];
    $delimiter = isset($_POST['delimiter']) ? $_POST['delimiter'] : ';';
    if ($delimiter === 'tab') $delimiter = "\t";

    $temp_dir = BASE_PATH . '/admin/test/temp/';
    if (!file_exists($temp_dir)) mkdir($temp_dir, 0777, true);
    $temp_file = $temp_dir . 'temp_' . session_id() . '.csv';
    move_uploaded_file($file, $temp_file);
    $_SESSION['temp_csv_file'] = $temp_file;

    logDebug("Προεπισκόπηση CSV με delimiter: '$delimiter', αρχείο: $temp_file");

    if (($handle = fopen($temp_file, "r")) !== FALSE) {
        $file_headers = fgetcsv($handle, 0, $delimiter);
        if ($file_headers !== FALSE) {
            $file_headers = array_map('clean_encoding', $file_headers);
            logDebug("Επικεφαλίδες CSV: " . implode(", ", $file_headers));
            $max_columns = count($file_headers);
            $row_count = 0;
            while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE && $row_count < 10) {
                $row = array_map('clean_encoding', $data);
                // Συμπλήρωση κενών στηλών με κενό string αν είναι λιγότερες από τις επικεφαλίδες
                while (count($row) < $max_columns) {
                    $row[] = '';
                }
                $previewData[] = $row;
                $row_count++;
            }
        } else {
            $import_error = "Δεν βρέθηκαν επικεφαλίδες.";
            logDebug("Σφάλμα: Κενό CSV ή μη έγκυρες επικεφαλίδες");
        }
        fclose($handle);
    } else {
        $import_error = "Αδυναμία ανοίγματος CSV.";
        logDebug("Σφάλμα: Αδυναμία ανοίγματος αρχείου");
    }
}

// Εισαγωγή CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_import'])) {
    $use_csv_categorization = isset($_POST['use_csv_categorization']) && $_POST['use_csv_categorization'] === 'yes';
    $valid_file = false;
    $tmp_file = '';

    if (isset($_SESSION['temp_csv_file']) && file_exists($_SESSION['temp_csv_file'])) {
        $tmp_file = $_SESSION['temp_csv_file'];
        $valid_file = true;
        logDebug("Χρήση αρχείου από session: $tmp_file");
    } elseif (isset($_FILES['csv_file_hidden']) && $_FILES['csv_file_hidden']['error'] === UPLOAD_ERR_OK) {
        $tmp_file = $_FILES['csv_file_hidden']['tmp_name'];
        $valid_file = true;
        logDebug("Χρήση αρχείου από κρυφό input: {$_FILES['csv_file_hidden']['name']}");
    } else {
        $import_error = "Λείπει το αρχείο CSV.";
        logDebug("Σφάλμα: Λείπει το αρχείο CSV");
    }

    if (!$use_csv_categorization) {
        $category_id = intval($_POST['category_id'] ?? 0);
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        $chapter_id = intval($_POST['chapter_id'] ?? 0);
        if ($category_id === 0 || $subcategory_id === 0 || $chapter_id === 0) {
            $import_error = "Παρακαλώ επιλέξτε κατηγορία, υποκατηγορία και κεφάλαιο.";
            $valid_file = false;
        }
    }

    if ($valid_file && !$import_error) {
        $delimiter = $_POST['delimiter'];
        if ($delimiter === 'tab') $delimiter = "\t";

        $media_files = [];
        if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = BASE_PATH . '/admin/test/uploads/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $zip = new ZipArchive();
            if ($zip->open($_FILES['zip_file']['tmp_name']) === TRUE) {
                $zip->extractTo($uploadDir);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $media_files[$filename] = $uploadDir . $filename;
                }
                $zip->close();
                logDebug("Εξαγωγή " . count($media_files) . " αρχείων από ZIP");
            } else {
                $import_error = "Σφάλμα εξαγωγής ZIP.";
                logDebug("Σφάλμα: Αδυναμία εξαγωγής ZIP");
            }
        }

        if (($handle = fopen($tmp_file, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers !== FALSE) {
                $headers = array_map('clean_encoding', $headers);
                $has_categorization = $use_csv_categorization && count($headers) >= 6 &&
                    in_array(mb_strtolower($headers[0]), ['κατηγορία', 'category', 'κατηγορια']) &&
                    in_array(mb_strtolower($headers[1]), ['υποκατηγορία', 'subcategory', 'υποκατηγορια']) &&
                    in_array(mb_strtolower($headers[2]), ['κεφάλαιο', 'chapter', 'κεφαλαιο']);
                $offset = $has_categorization ? 3 : 0;
                $question_index = $offset;
                $explanation_index = $offset + 1;
                $correct_answer_index = $offset + 2;
                $answers_start_index = $offset + 3;

                if (count($headers) < $answers_start_index + 1 ||
                    !in_array(mb_strtolower($headers[$question_index]), ['ερώτηση', 'question', 'ερωτημα'])) {
                    $import_error = "Μη έγκυρη μορφή CSV.";
                    logDebug("Σφάλμα: Μη έγκυρη μορφή CSV");
                } else {
                    $line_number = 1;
                    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                        $line_number++;
                        $data = array_map('clean_encoding', $data);
                        if (count($data) < $answers_start_index + 1) {
                            $errorLines[] = ['line' => $line_number, 'error' => "Ανεπαρκή δεδομένα", 'data' => implode($delimiter, $data)];
                            $failed_imports++;
                            continue;
                        }

                        $current_category_id = $category_id ?? 0;
                        $current_subcategory_id = $subcategory_id ?? 0;
                        $current_chapter_id = $chapter_id ?? 0;
                        if ($has_categorization && $use_csv_categorization) {
                            $current_category_id = findOrCreateCategory($mysqli, trim($data[0]));
                            $current_subcategory_id = findOrCreateSubcategory($mysqli, trim($data[1]), $current_category_id);
                            $current_chapter_id = findOrCreateChapter($mysqli, trim($data[2]), $current_subcategory_id);
                            if ($current_chapter_id <= 0) {
                                $errorLines[] = ['line' => $line_number, 'error' => "Αδυναμία δημιουργίας κεφαλαίου", 'data' => implode($delimiter, $data)];
                                $failed_imports++;
                                continue;
                            }
                        }

                        $question_text = trim($data[$question_index]);
                        $explanation = trim($data[$explanation_index]);
                        $correct_answer_index_val = intval($data[$correct_answer_index]) - 1;
                        $answers = array_slice($data, $answers_start_index);

                        if (empty($question_text) || $correct_answer_index_val < 0 || $correct_answer_index_val >= count($answers)) {
                            $errorLines[] = ['line' => $line_number, 'error' => "Λανθασμένη ερώτηση ή δείκτης", 'data' => implode($delimiter, $data)];
                            $failed_imports++;
                            continue;
                        }

                        $question_media = '';
                        $explanation_media = '';
                        $answer_media = [];
                        $media_prefix = $line_number - 1;
                        if (!empty($media_files)) {
                            foreach (["question_$media_prefix.png", "question_$media_prefix.jpg"] as $name) {
                                if (isset($media_files[$name])) {
                                    $question_media = $name;
                                    break;
                                }
                            }
                            foreach (["explanation_$media_prefix.png", "explanation_$media_prefix.jpg"] as $name) {
                                if (isset($media_files[$name])) {
                                    $explanation_media = $name;
                                    break;
                                }
                            }
                            foreach ($answers as $index => $answer) {
                                $answer_index = $index + 1;
                                foreach (["answer_{$media_prefix}_{$answer_index}.png", "answer_{$media_prefix}_{$answer_index}.jpg"] as $name) {
                                    if (isset($media_files[$name])) {
                                        $answer_media[$index] = $name;
                                        break;
                                    }
                                }
                            }
                        }

                        $mysqli->begin_transaction();
                        try {
                            $question_query = "INSERT INTO questions (chapter_id, author_id, question_text, question_explanation, question_type, question_media, explanation_media, status)
                                            VALUES (?, ?, ?, ?, 'single_choice', ?, ?, 'active')";
                            $stmt = $mysqli->prepare($question_query);
                            $author_id = $_SESSION['user_id'] ?? 1;
                            $stmt->bind_param("iissss", $current_chapter_id, $author_id, $question_text, $explanation, $question_media, $explanation_media);
                            $stmt->execute();
                            $question_id = $mysqli->insert_id;

                            foreach ($answers as $index => $answer_text) {
                                if (empty(trim($answer_text))) continue;
                                $is_correct = ($index == $correct_answer_index_val) ? 1 : 0;
                                $current_answer_media = $answer_media[$index] ?? '';
                                $answer_query = "INSERT INTO test_answers (question_id, answer_text, is_correct, answer_media) VALUES (?, ?, ?, ?)";
                                $stmt = $mysqli->prepare($answer_query);
                                $stmt->bind_param("isis", $question_id, $answer_text, $is_correct, $current_answer_media);
                                $stmt->execute();
                            }
                            $mysqli->commit();
                            $total_imported++;
                            $successfulImports[] = ['question_id' => $question_id, 'question_text' => $question_text];
                        } catch (Exception $e) {
                            $mysqli->rollback();
                            $errorLines[] = ['line' => $line_number, 'error' => $e->getMessage(), 'data' => implode($delimiter, $data)];
                            $failed_imports++;
                        }
                    }
                    $import_success = $total_imported > 0;
                }
            } else {
                $import_error = "Κενό ή μη έγκυρο CSV.";
                logDebug("Σφάλμα: Κενό ή μη έγκυρο CSV");
            }
            fclose($handle);
        } else {
            $import_error = "Αδυναμία ανοίγματος CSV.";
            logDebug("Σφάλμα: Αδυναμία ανοίγματος CSV");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Μαζική Εισαγωγή Ερωτήσεων</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/enhanced_bulk_import.css">
</head>
<body>

<main class="admin-container">
    <h2 class="admin-title">📤 Μαζική Εισαγωγή Ερωτήσεων</h2>

    <?php if ($import_success): ?>
        <div class="result-section">
            <h3>✅ Η εισαγωγή ολοκληρώθηκε επιτυχώς!</h3>
            <div class="result-summary">
                <p>Εισήχθησαν επιτυχώς <strong><?= $total_imported ?></strong> ερωτήσεις.</p>
                <?php if ($failed_imports > 0): ?>
                    <p>Απέτυχε η εισαγωγή <strong><?= $failed_imports ?></strong> ερωτήσεων.</p>
                <?php endif; ?>
            </div>
            <?php if (!empty($created_categories) || !empty($created_subcategories) || !empty($created_chapters)): ?>
                <div class="created-items">
                    <?php if (!empty($created_categories)): ?>
                        <div class="created-group"><h5>Νέες Κατηγορίες:</h5><ul class="new-items-list"><?php foreach ($created_categories as $item): ?><li><?= htmlspecialchars($item['name']) ?> (ID: <?= $item['id'] ?>)</li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <?php if (!empty($created_subcategories)): ?>
                        <div class="created-group"><h5>Νέες Υποκατηγορίες:</h5><ul class="new-items-list"><?php foreach ($created_subcategories as $item): ?><li><?= htmlspecialchars($item['name']) ?> (ID: <?= $item['id'] ?>)</li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <?php if (!empty($created_chapters)): ?>
                        <div class="created-group"><h5>Νέα Κεφάλαια:</h5><ul class="new-items-list"><?php foreach ($created_chapters as $item): ?><li><?= htmlspecialchars($item['name']) ?> (ID: <?= $item['id'] ?>)</li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($successfulImports)): ?>
                <h4>Επιτυχείς Εισαγωγές</h4>
                <div class="success-list"><?php foreach ($successfulImports as $import): ?><div class="success-item"><span class="success-id">#<?= $import['question_id'] ?></span><span class="success-text"><?= htmlspecialchars($import['question_text']) ?></span><a href="edit_question.php?id=<?= $import['question_id'] ?>" class="btn-edit" title="Επεξεργασία">✏️</a></div><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if (!empty($errorLines)): ?>
                <h4>Σφάλματα Εισαγωγής</h4>
                <div class="error-section"><div class="error-list"><?php foreach ($errorLines as $error): ?><div class="error-item"><div class="error-header"><span class="error-line">Γραμμή <?= $error['line'] ?></span><span class="error-message"><?= htmlspecialchars($error['error']) ?></span></div><pre class="error-data"><?= htmlspecialchars($error['data']) ?></pre></div><?php endforeach; ?></div></div>
            <?php endif; ?>
            <div class="form-actions">
                <a href="bulk_import.php" class="btn-primary">🔄 Νέα Εισαγωγή</a>
                <a href="manage_questions.php" class="btn-secondary">↩️ Επιστροφή στις Ερωτήσεις</a>
            </div>
        </div>
    <?php elseif ($import_error): ?>
        <div class="alert error"><?= htmlspecialchars($import_error) ?></div>
    <?php elseif ($preview_mode): ?>
        <div class="form-section">
            <h3>Προεπισκόπηση CSV</h3>
            <div class="preview-table-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <?php foreach ($file_headers as $index => $header): ?>
                                <th><?= htmlspecialchars($header) ?><br><span class="column-index">Στήλη <?= $index + 1 ?></span></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData as $row): ?>
                            <tr>
                                <?php for ($i = 0; $i < count($file_headers); $i++): ?>
                                    <td><?= isset($row[$i]) ? htmlspecialchars($row[$i]) : '' ?></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="categorization-option">
                    <h4>Επιλογή τρόπου κατηγοριοποίησης</h4>
                    <?php
                    $has_categorization_columns = count($file_headers) >= 3 &&
                        in_array(mb_strtolower($file_headers[0]), ['κατηγορία', 'category', 'κατηγορια']) &&
                        in_array(mb_strtolower($file_headers[1]), ['υποκατηγορία', 'subcategory', 'υποκατηγορια']) &&
                        in_array(mb_strtolower($file_headers[2]), ['κεφάλαιο', 'chapter', 'κεφαλαιο']);
                    ?>
                    <div class="radio-container">
                        <div class="radio-option">
                            <input type="radio" name="use_csv_categorization" value="yes" id="use_csv_yes" <?= $has_categorization_columns ? '' : 'disabled' ?>>
                            <label for="use_csv_yes">Χρήση στηλών από το CSV<?= $has_categorization_columns ? '' : ' (Δεν διαθέτει στήλες κατηγοριοποίησης)' ?></label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="use_csv_categorization" value="no" id="use_csv_no" checked>
                            <label for="use_csv_no">Επιλογή κατηγορίας, υποκατηγορίας και κεφαλαίου</label>
                        </div>
                    </div>
                    <div class="category-fields" id="category-selection-fields">
                        <div class="form-group">
                            <label for="category_id">Κατηγορία:</label>
                            <select name="category_id" id="category_id" required>
                                <option value="">-- Επιλέξτε Κατηγορία --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subcategory_id">Υποκατηγορία:</label>
                            <select name="subcategory_id" id="subcategory_id" required disabled>
                                <option value="">-- Επιλέξτε πρώτα Κατηγορία --</option>
                                <?php foreach ($subcategories as $subcategory): ?>
                                    <option value="<?= $subcategory['id'] ?>" data-category="<?= $subcategory['test_category_id'] ?>" style="display:none"><?= htmlspecialchars($subcategory['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="chapter_id">Κεφάλαιο:</label>
                            <select name="chapter_id" id="chapter_id" required disabled>
                                <option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>
                                <?php foreach ($chapters as $chapter): ?>
                                    <option value="<?= $chapter['id'] ?>" data-subcategory="<?= $chapter['subcategory_id'] ?>" style="display:none"><?= htmlspecialchars($chapter['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="delimiter" value="<?= htmlspecialchars($_POST['delimiter'] ?? ';') ?>">
                <input type="file" name="csv_file_hidden" id="csv_file_hidden" style="display:none;">
                <input type="hidden" name="submit_import" value="1">
                <div class="form-actions">
                    <button type="submit" class="btn-primary">📥 Εισαγωγή Ερωτήσεων</button>
                    <a href="bulk_import.php" class="btn-secondary">🔄 Νέα Εισαγωγή</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="form-section">
            <h3>Ανέβασμα Αρχείου CSV</h3>
            <form method="POST" enctype="multipart/form-data" id="preview-form">
                <div class="form-group">
                    <label for="delimiter">Διαχωριστικό:</label>
                    <select name="delimiter" id="delimiter">
                        <option value=";">Ερωτηματικό (;)</option>
                        <option value=",">Κόμμα (,)</option>
                        <option value="tab">Tab</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="csv_file">Αρχείο CSV:</label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </div>
                <div class="form-group">
                    <label for="zip_file">Αρχείο ZIP με εικόνες (προαιρετικό):</label>
                    <input type="file" name="zip_file" id="zip_file" accept=".zip">
                    <span class="help-text">Το ZIP μπορεί να περιέχει εικόνες με ονόματα: question_1.png, answer_1_1.png κλπ.</span>
                </div>
                <div class="form-info">
                    <h4>Οδηγίες Μορφής CSV:</h4>
                    <p>Υποστηρίζονται δύο μορφές:</p>
                    <h5>1. Με κατηγοριοποίηση:</h5>
                    <ol><li>Κατηγορία</li><li>Υποκατηγορία</li><li>Κεφάλαιο</li><li>Ερώτηση</li><li>Επεξήγηση</li><li>Σωστή Απάντηση</li><li>Απάντηση 1, 2, 3...</li></ol>
                    <h5>2. Χωρίς κατηγοριοποίηση:</h5>
                    <ol><li>Ερώτηση</li><li>Επεξήγηση</li><li>Σωστή Απάντηση</li><li>Απάντηση 1, 2, 3...</li></ol>
                    <p><strong>Παράδειγμα:</strong></p>
                    <pre>Κατηγορία;Υποκατηγορία;Κεφάλαιο;Ερώτηση;Επεξήγηση;Σωστή Απάντηση;Απάντηση 1;Απάντηση 2;Απάντηση 3
Επιβατικά;Δίπλωμα Β;Σήματα;Ποιο σήμα απαγορεύει στάθμευση;Σήμα Ρ-39;1;Κόκκινος κύκλος;Μπλε τετράγωνο;Κίτρινο τρίγωνο</pre>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">📤 Προεπισκόπηση CSV</button>
                    <a href="example_questions_with_categories.csv" download class="btn-secondary">📥 Πρότυπο με Κατηγορίες</a>
                    <a href="example_questions.csv" download class="btn-secondary">📥 Πρότυπο Απλό</a>
                    <a href="manage_questions.php" class="btn-link">🔙 Επιστροφή</a>
                </div>
            </form>
        </div>
        <div class="form-section">
            <h3>Συμβουλές</h3>
            <div class="tip-item"><div class="tip-icon">💡</div><div class="tip-content"><h4>Κωδικοποίηση</h4><p>Αποθήκευσε το CSV σε UTF-8.</p></div></div>
            <div class="tip-item"><div class="tip-icon">💡</div><div class="tip-content"><h4>Κατηγοριοποίηση</h4><p>Χρησιμοποίησε στήλες κατηγοριοποίησης για αυτοματοποίηση.</p></div></div>
            <div class="tip-item"><div class="tip-icon">💡</div><div class="tip-content"><h4>Εικόνες</h4><p>Χρησιμοποίησε ονόματα όπως question_1.png, answer_1_1.png.</p></div></div>
            <div class="tip-item"><div class="tip-icon">💡</div><div class="tip-content"><h4>Σωστή Απάντηση</h4><p>Δώσε αριθμό (1, 2, 3...).</p></div></div>
        </div>
    <?php endif; ?>
</main>
<?php require_once '../includes/admin_footer.php'; ?>
<script src="<?= BASE_URL ?>/admin/assets/js/bulk_import.js"></script>
</body>
</html>