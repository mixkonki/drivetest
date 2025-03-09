<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Βεβαιωνόμαστε ότι έχουμε ορίσει τη σωστή κωδικοποίηση
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

// Ανάκτηση κατηγοριών
$categories_query = "SELECT id, name FROM test_categories ORDER BY name ASC";
$categories_result = $mysqli->query($categories_query);
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

// Ανάκτηση όλων των υποκατηγοριών
$subcategories_query = "SELECT s.id, s.name, s.test_category_id FROM test_subcategories s ORDER BY s.name ASC";
$subcategories_result = $mysqli->query($subcategories_query);
$subcategories = $subcategories_result ? $subcategories_result->fetch_all(MYSQLI_ASSOC) : [];

// Ανάκτηση όλων των κεφαλαίων
$chapters_query = "SELECT c.id, c.name, c.subcategory_id FROM test_chapters c ORDER BY c.name ASC";
$chapters_result = $mysqli->query($chapters_query);
$chapters = $chapters_result ? $chapters_result->fetch_all(MYSQLI_ASSOC) : [];

/**
 * Συνάρτηση για εύρεση ή δημιουργία κατηγορίας βάσει ονόματος
 */
function findOrCreateCategory($mysqli, $name) {
    global $created_categories;
    
    if (empty($name)) return 0;
    
    // Καθαρισμός και κανονικοποίηση του ονόματος
    $name = trim($name);
    
    // Αναζήτηση με βάση το όνομα
    $stmt = $mysqli->prepare("SELECT id FROM test_categories WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Δημιουργία νέας κατηγορίας αν δεν υπάρχει
    $stmt = $mysqli->prepare("INSERT INTO test_categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $new_id = $mysqli->insert_id;
    
    // Καταγραφή της νέας κατηγορίας
    $created_categories[] = [
        'id' => $new_id,
        'name' => $name
    ];
    
    logDebug("Δημιουργήθηκε νέα κατηγορία: $name (ID: $new_id)");
    
    return $new_id;
}

/**
 * Συνάρτηση για εύρεση ή δημιουργία υποκατηγορίας βάσει ονόματος και κατηγορίας
 */
function findOrCreateSubcategory($mysqli, $name, $category_id) {
    global $created_subcategories;
    
    if (empty($name) || $category_id <= 0) return 0;
    
    // Καθαρισμός και κανονικοποίηση του ονόματος
    $name = trim($name);
    
    // Αναζήτηση με βάση το όνομα και την κατηγορία
    $stmt = $mysqli->prepare("SELECT id FROM test_subcategories WHERE name = ? AND test_category_id = ?");
    $stmt->bind_param("si", $name, $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Δημιουργία νέας υποκατηγορίας αν δεν υπάρχει
    $stmt = $mysqli->prepare("INSERT INTO test_subcategories (name, test_category_id) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $category_id);
    $stmt->execute();
    $new_id = $mysqli->insert_id;
    
    // Καταγραφή της νέας υποκατηγορίας
    $created_subcategories[] = [
        'id' => $new_id,
        'name' => $name,
        'category_id' => $category_id
    ];
    
    logDebug("Δημιουργήθηκε νέα υποκατηγορία: $name (ID: $new_id) στην κατηγορία ID: $category_id");
    
    return $new_id;
}

/**
 * Καθαρισμός κωδικοποίησης για ελληνικούς χαρακτήρες
 */
function clean_encoding($string) {
    if (empty($string)) {
        return '';
    }
    // Αφαίρεση BOM αν υπάρχει
    $string = str_replace("\xEF\xBB\xBF", '', $string);
    
    // Μετατροπή από διάφορες κωδικοποιήσεις σε UTF-8
    $encodings = ['UTF-8', 'ISO-8859-7', 'Windows-1253'];
    
    // Δοκιμή διαφορετικών κωδικοποιήσεων
    foreach ($encodings as $encoding) {
        $decoded = @mb_convert_encoding($string, 'UTF-8', $encoding);
        if ($decoded !== false) {
            $string = $decoded;
            break;
        }
    }
    
    // Καθαρισμός περιττών κενών διαστημάτων
    return trim(preg_replace('/\s+/', ' ', $string));
}

/**
 * Συνάρτηση για εύρεση ή δημιουργία κεφαλαίου βάσει ονόματος και υποκατηγορίας
 */
function findOrCreateChapter($mysqli, $name, $subcategory_id) {
    global $created_chapters;
    
    if (empty($name) || $subcategory_id <= 0) return 0;
    
    // Καθαρισμός και κανονικοποίηση του ονόματος
    $name = trim($name);
    
    // Αναζήτηση με βάση το όνομα και την υποκατηγορία
    $stmt = $mysqli->prepare("SELECT id FROM test_chapters WHERE name = ? AND subcategory_id = ?");
    $stmt->bind_param("si", $name, $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Δημιουργία νέου κεφαλαίου αν δεν υπάρχει
    $stmt = $mysqli->prepare("INSERT INTO test_chapters (name, subcategory_id) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $subcategory_id);
    $stmt->execute();
    $new_id = $mysqli->insert_id;
    
    // Καταγραφή του νέου κεφαλαίου
    $created_chapters[] = [
        'id' => $new_id,
        'name' => $name,
        'subcategory_id' => $subcategory_id
    ];
    
    logDebug("Δημιουργήθηκε νέο κεφάλαιο: $name (ID: $new_id) στην υποκατηγορία ID: $subcategory_id");
    
    return $new_id;
}

// Διαχείριση προεπισκόπησης CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $preview_mode = true;
    $file = $_FILES['csv_file']['tmp_name'];
    $delimiter = isset($_POST['delimiter']) ? $_POST['delimiter'] : ';';
    
    if ($delimiter === 'tab') $delimiter = "\t";
    
    logDebug("Προεπισκόπηση CSV με delimiter: '$delimiter'");
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Ανάγνωση της πρώτης γραμμής (επικεφαλίδες)
        $headers = fgetcsv($handle, 0, $delimiter);
        
        if ($headers !== FALSE) {
            // Έλεγχος κωδικοποίησης και μετατροπή, αν χρειάζεται
            $headers = array_map('clean_encoding', $headers);
            $file_headers = $headers;
            logDebug("Επικεφαλίδες: " . implode(", ", $headers));
            
            // Έλεγχος αν το CSV έχει στήλες κατηγοριοποίησης
            $has_categorization = false;
            if (count($headers) >= 6) {
                // Ελέγχουμε αν οι πρώτες τρεις στήλες είναι κατηγορία, υποκατηγορία, κεφάλαιο
                $possible_cat_headers = ['κατηγορία', 'category', 'κατηγορια'];
                $possible_subcat_headers = ['υποκατηγορία', 'subcategory', 'υποκατηγορια'];
                $possible_chapter_headers = ['κεφάλαιο', 'chapter', 'κεφαλαιο'];
                
                $header0_lower = mb_strtolower($headers[0]);
                $header1_lower = mb_strtolower($headers[1]);
                $header2_lower = mb_strtolower($headers[2]);
                
                if (in_array($header0_lower, $possible_cat_headers) && 
                    in_array($header1_lower, $possible_subcat_headers) && 
                    in_array($header2_lower, $possible_chapter_headers)) {
                    $has_categorization = true;
                    logDebug("Εντοπίστηκαν στήλες κατηγοριοποίησης στο CSV");
                }
            }
            
            // Λήψη των πρώτων 5 γραμμών για προεπισκόπηση
            $row_count = 0;
            while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE && $row_count < 5) {
                // Διορθώνουμε την κωδικοποίηση των δεδομένων
                $data = array_map('clean_encoding', $data);
                
                $previewData[] = $data;
                $row_count++;
            }
        } else {
            $import_error = "Το αρχείο CSV είναι κενό ή έχει μη έγκυρη μορφή.";
            logDebug("Σφάλμα: Κενό ή μη έγκυρο CSV");
        }
        
        fclose($handle);
    } else {
        $import_error = "Δεν ήταν δυνατό το άνοιγμα του αρχείου.";
    }
}

// Επεξεργασία της φόρμας εισαγωγής
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_import'])) {
    $use_csv_categorization = isset($_POST['use_csv_categorization']) ? $_POST['use_csv_categorization'] == 'yes' : false;
    
    // Έλεγχος αν έχουμε έγκυρο αρχείο CSV
    $valid_file = false;
    
    if (isset($_FILES['csv_file_hidden']) && $_FILES['csv_file_hidden']['error'] === UPLOAD_ERR_OK) {
        $tmp_file = $_FILES['csv_file_hidden']['tmp_name'];
        $valid_file = true;
    } elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_file = $_FILES['csv_file']['tmp_name'];
        $valid_file = true;
    } else {
        $import_error = "Λείπει το αρχείο CSV.";
        logDebug("Σφάλμα: Λείπει το αρχείο CSV");
    }
    
    // Αν δεν χρησιμοποιούμε κατηγοριοποίηση από το CSV, ελέγχουμε ότι έχουν επιλεγεί τα απαραίτητα
    if (!$use_csv_categorization) {
        $category_id = intval($_POST['category_id'] ?? 0);
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        $chapter_id = intval($_POST['chapter_id'] ?? 0);
        
        if ($category_id === 0 || $subcategory_id === 0 || $chapter_id === 0) {
            $import_error = "Παρακαλώ επιλέξτε κατηγορία, υποκατηγορία και κεφάλαιο ή ενεργοποιήστε την κατηγοριοποίηση από το CSV.";
            logDebug("Σφάλμα: Λείπουν υποχρεωτικά πεδία");
            $valid_file = false;
        }
    }
    
    if ($valid_file && !isset($import_error)) {
        $delimiter = $_POST['delimiter'];
        if ($delimiter === 'tab') $delimiter = "\t";
        
        logDebug("Εισαγωγή CSV με: use_csv_categorization=" . ($use_csv_categorization ? "yes" : "no") . ", delimiter='$delimiter'");
        
        // Επεξεργασία του ZIP (αν υπάρχει)
        $media_files = [];
        if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = BASE_PATH . '/admin/test/uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $zip = new ZipArchive();
            if ($zip->open($_FILES['zip_file']['tmp_name']) === TRUE) {
                $zip->extractTo($uploadDir);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $media_files[$filename] = $uploadDir . $filename;
                }
                $zip->close();
                logDebug("Εξαγωγή " . count($media_files) . " αρχείων από το ZIP");
            } else {
                $import_error = "Σφάλμα κατά την εξαγωγή του αρχείου ZIP.";
                logDebug("Σφάλμα εξαγωγής ZIP");
            }
        }
        
        if (($handle = fopen($tmp_file, "r")) !== FALSE) {
            // Ανάγνωση της πρώτης γραμμής (επικεφαλίδες)
            $headers = fgetcsv($handle, 0, $delimiter);
            
            if ($headers !== FALSE) {
                // Έλεγχος κωδικοποίησης και μετατροπή, αν χρειάζεται
                $headers = array_map('clean_encoding', $headers);
                
                logDebug("Επικεφαλίδες για εισαγωγή: " . implode(", ", $headers));
                
                // Έλεγχος αν το CSV έχει στήλες κατηγοριοποίησης και τις χρησιμοποιούμε
                $has_categorization = false;
                $category_index = -1;
                $subcategory_index = -1;
                $chapter_index = -1;
                $offset = 0;
                
                if ($use_csv_categorization && count($headers) >= 6) {
                    // Ελέγχουμε αν οι πρώτες τρεις στήλες είναι κατηγορία, υποκατηγορία, κεφάλαιο
                    $possible_cat_headers = ['κατηγορία', 'category', 'κατηγορια'];
                    $possible_subcat_headers = ['υποκατηγορία', 'subcategory', 'υποκατηγορια'];
                    $possible_chapter_headers = ['κεφάλαιο', 'chapter', 'κεφαλαιο'];
                    
                    $header0_lower = mb_strtolower($headers[0]);
                    $header1_lower = mb_strtolower($headers[1]);
                    $header2_lower = mb_strtolower($headers[2]);
                    
                    if (in_array($header0_lower, $possible_cat_headers) && 
                        in_array($header1_lower, $possible_subcat_headers) && 
                        in_array($header2_lower, $possible_chapter_headers)) {
                        $has_categorization = true;
                        $category_index = 0;
                        $subcategory_index = 1;
                        $chapter_index = 2;
                        $offset = 3; // Μετατόπιση για τα υπόλοιπα δεδομένα
                        logDebug("Χρήση στηλών κατηγοριοποίησης από το CSV");
                    }
                }
                
                $question_index = $offset + 0; // Πρώτη στήλη μετά την κατηγοριοποίηση
                $explanation_index = $offset + 1;
                $correct_answer_index_index = $offset + 2;
                $answers_start_index = $offset + 3;
                
                // Έλεγχος δομής αρχείου
                $valid_format = false;
                if (count($headers) >= $answers_start_index + 1) {
                    // Αναμενόμενες επικεφαλίδες για το νέο format μετά την κατηγοριοποίηση:
                    // Ερώτηση, Επεξήγηση, Σωστή απάντηση, Απάντηση 1, Απάντηση 2...
                    $header_q_lower = mb_strtolower($headers[$question_index]);
                    if (strpos($header_q_lower, 'ερώτηση') !== false || strpos($header_q_lower, 'question') !== false) {
                        $valid_format = true;
                    }
                }
                
                if (!$valid_format) {
                    $import_error = "Μη έγκυρη μορφή CSV. Βεβαιωθείτε ότι το αρχείο περιέχει τις σωστές επικεφαλίδες.";
                    logDebug("Σφάλμα: Μη έγκυρη μορφή CSV");
                } else {
                    $line_number = 1; // Ξεκινάμε από 1 για να συμπεριλάβουμε την επικεφαλίδα
                    
                    // Επεξεργασία κάθε γραμμής
                    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                        $line_number++;
                        
                        // Διορθώνουμε την κωδικοποίηση των δεδομένων
                        $data = array_map('clean_encoding', $data);
                        
                        // Έλεγχος αν έχουμε αρκετά δεδομένα
                        if (count($data) < $answers_start_index + 1) {
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => "Ανεπαρκή δεδομένα στη γραμμή",
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                            continue;
                        }
                        
                        // Διαχείριση της κατηγοριοποίησης
                        $current_category_id = $category_id ?? 0;
                        $current_subcategory_id = $subcategory_id ?? 0;
                        $current_chapter_id = $chapter_id ?? 0;
                        
                        if ($has_categorization && $use_csv_categorization) {
                            // Χρήση των δεδομένων από το CSV για κατηγοριοποίηση
                            $category_name = isset($data[$category_index]) ? trim($data[$category_index]) : '';
                            $subcategory_name = isset($data[$subcategory_index]) ? trim($data[$subcategory_index]) : '';
                            $chapter_name = isset($data[$chapter_index]) ? trim($data[$chapter_index]) : '';
                            
                            if (empty($category_name) || empty($subcategory_name) || empty($chapter_name)) {
                                $errorLines[] = [
                                    'line' => $line_number,
                                    'error' => "Λείπουν δεδομένα κατηγοριοποίησης (κατηγορία/υποκατηγορία/κεφάλαιο)",
                                    'data' => implode($delimiter, $data)
                                ];
                                $failed_imports++;
                                continue;
                            }
                            
                            // Εύρεση ή δημιουργία των απαραίτητων κατηγοριών
                            $current_category_id = findOrCreateCategory($mysqli, $category_name);
                            if ($current_category_id <= 0) {
                                $errorLines[] = [
                                    'line' => $line_number,
                                    'error' => "Αδυναμία εύρεσης ή δημιουργίας κατηγορίας: $category_name",
                                    'data' => implode($delimiter, $data)
                                ];
                                $failed_imports++;
                                continue;
                            }
                            
                            $current_subcategory_id = findOrCreateSubcategory($mysqli, $subcategory_name, $current_category_id);
                            if ($current_subcategory_id <= 0) {
                                $errorLines[] = [
                                    'line' => $line_number,
                                    'error' => "Αδυναμία εύρεσης ή δημιουργίας υποκατηγορίας: $subcategory_name",
                                    'data' => implode($delimiter, $data)
                                ];
                                $failed_imports++;
                                continue;
                            }
                            
                            $current_chapter_id = findOrCreateChapter($mysqli, $chapter_name, $current_subcategory_id);
                            if ($current_chapter_id <= 0) {
                                $errorLines[] = [
                                    'line' => $line_number,
                                    'error' => "Αδυναμία εύρεσης ή δημιουργίας κεφαλαίου: $chapter_name",
                                    'data' => implode($delimiter, $data)
                                ];
                                $failed_imports++;
                                continue;
                            }
                        }
                        
                        // Προετοιμασία δεδομένων
                        $question_text = isset($data[$question_index]) ? trim($data[$question_index]) : '';
                        $explanation = isset($data[$explanation_index]) ? trim($data[$explanation_index]) : '';
                        $correct_answer_index = isset($data[$correct_answer_index_index]) ? (intval($data[$correct_answer_index_index]) - 1) : -1; // Μετατροπή από 1-based σε 0-based
                        
                        // Έλεγχος εγκυρότητας βασικών δεδομένων
                        if (empty($question_text) || $correct_answer_index < 0 || $correct_answer_index >= count($data) - $answers_start_index) {
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => "Λανθασμένος δείκτης σωστής απάντησης ή κενή ερώτηση",
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                            continue;
                        }
                        
                        // Συλλογή των απαντήσεων
                        $answers = array_slice($data, $answers_start_index);
                        if (empty($answers)) {
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => "Δεν βρέθηκαν απαντήσεις",
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                            continue;
                        }
                        
                        // Εύρεση εικόνων από το ZIP (αν υπάρχουν)
                        $question_media = '';
                        $explanation_media = '';
                        $answer_media = [];
                        
                        // Χρήση του αριθμού γραμμής ως αναγνωριστικό για τις εικόνες
                        $media_prefix = $line_number - 1; // -1 για να λάβουμε υπόψη την επικεφαλίδα
                        
                        if (!empty($media_files)) {
                            // Έλεγχος για εικόνα ερώτησης (διάφορα πιθανά ονόματα)
                            foreach (["question_$media_prefix.png", "question_$media_prefix.jpg", "q_$media_prefix.png", "q_$media_prefix.jpg"] as $possible_name) {
                                if (isset($media_files[$possible_name])) {
                                    $question_media = $possible_name;
                                    break;
                                }
                            }
                            
                            // Έλεγχος για εικόνα επεξήγησης
                            foreach (["explanation_$media_prefix.png", "explanation_$media_prefix.jpg", "exp_$media_prefix.png", "exp_$media_prefix.jpg"] as $possible_name) {
                                if (isset($media_files[$possible_name])) {
                                    $explanation_media = $possible_name;
                                    break;
                                }
                            }
                            
                            // Έλεγχος για εικόνες απαντήσεων
                            foreach ($answers as $index => $answer) {
                                $answer_index = $index + 1;
                                foreach (["answer_{$media_prefix}_{$answer_index}.png", "answer_{$media_prefix}_{$answer_index}.jpg", "a_{$media_prefix}_{$answer_index}.png", "a_{$media_prefix}_{$answer_index}.jpg"] as $possible_name) {
                                    if (isset($media_files[$possible_name])) {
                                        $answer_media[$index] = $possible_name;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Έναρξη συναλλαγής
                        $mysqli->begin_transaction();
                        
                        try {
                            // Εισαγωγή ερώτησης
                            $question_type = 'single_choice'; // Προεπιλογή: μονής επιλογής
                            $author_id = $_SESSION['user_id'] ?? 1;
                            $status = 'active';
                            
                            $question_query = "INSERT INTO questions (chapter_id, author_id, question_text, question_explanation, question_type, question_media, explanation_media, status)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            $question_stmt = $mysqli->prepare($question_query);
                            $question_stmt->bind_param("iissssss", $current_chapter_id, $author_id, $question_text, $explanation, $question_type, $question_media, $explanation_media, $status);
                            
                            if ($question_stmt->execute()) {
                                $question_id = $question_stmt->insert_id;
                                
                                // Εισαγωγή απαντήσεων
                                $answer_success = true;
                                foreach ($answers as $index => $answer_text) {
                                    if (empty(trim($answer_text))) continue;
                                    
                                    $is_correct = ($index == $correct_answer_index) ? 1 : 0;
                                    $current_answer_media = isset($answer_media[$index]) ? $answer_media[$index] : '';
                                    
                                    $answer_query = "INSERT INTO test_answers (question_id, answer_text, is_correct, answer_media) VALUES (?, ?, ?, ?)";
                                    $answer_stmt = $mysqli->prepare($answer_query);
                                    $answer_stmt->bind_param("isis", $question_id, $answer_text, $is_correct, $current_answer_media);
                                    
                                    if (!$answer_stmt->execute()) {
                                        $answer_success = false;
                                        throw new Exception("Σφάλμα εισαγωγής απάντησης: " . $answer_stmt->error);
                                    }
                                }
                                
                                // Εάν όλα πήγαν καλά, επικύρωση της συναλλαγής
                                if ($answer_success) {
                                    $mysqli->commit();
                                    $total_imported++;
                                    $successfulImports[] = [
                                        'question_id' => $question_id,
                                        'question_text' => $question_text
                                    ];
                                } else {
                                    throw new Exception("Σφάλμα εισαγωγής απαντήσεων");
                                }
                            } else {
                                throw new Exception("Σφάλμα εισαγωγής ερώτησης: " . $question_stmt->error);
                            }
                        } catch (Exception $e) {
                            // Αναίρεση της συναλλαγής σε περίπτωση σφάλματος
                            $mysqli->rollback();
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => $e->getMessage(),
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                        }
                    }
                    
                    if ($total_imported > 0) {
                        $import_success = true;
                    }
                }
            } else {
                $import_error = "Το αρχείο CSV είναι κενό ή έχει μη έγκυρη μορφή.";
                logDebug("Σφάλμα: Κενό ή μη έγκυρο CSV");
            }
            
            fclose($handle);
        } else {
            $import_error = "Δεν ήταν δυνατό το άνοιγμα του αρχείου.";
            logDebug("Σφάλμα: Δεν ήταν δυνατό το άνοιγμα του αρχείου");
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/categorization_styles.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>
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
                    <h4>Δημιουργήθηκαν Νέα Στοιχεία</h4>
                    
                    <?php if (!empty($created_categories)): ?>
                        <div class="created-group">
                            <h5>Νέες Κατηγορίες:</h5>
                            <ul class="new-items-list">
                                <?php foreach ($created_categories as $item): ?>
                                    <li><?= htmlspecialchars($item['name']) ?> (ID: <?= $item['id'] ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($created_subcategories)): ?>
                        <div class="created-group">
                            <h5>Νέες Υποκατηγορίες:</h5>
                            <ul class="new-items-list">
                                <?php foreach ($created_subcategories as $item): ?>
                                    <li><?= htmlspecialchars($item['name']) ?> (ID: <?= $item['id'] ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($created_chapters)): ?>
                        <div class="created-group">
                            <h5>Νέα Κεφάλαια:</h5>
                            <ul class="new-items-list">
                                <?php foreach ($created_chapters as $item): ?>
                                    <li><?= htmlspecialchars($item['name']) ?> (ID: <?= $item['id'] ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($successfulImports)): ?>
                <h4>Επιτυχείς Εισαγωγές</h4>
                <div class="success-list">
                    <?php foreach ($successfulImports as $import): ?>
                        <div class="success-item">
                            <span class="success-id">#<?= $import['question_id'] ?></span>
                            <span class="success-text"><?= htmlspecialchars($import['question_text']) ?></span>
                            <a href="edit_question.php?id=<?= $import['question_id'] ?>" class="btn-edit" title="Επεξεργασία">✏️</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorLines)): ?>
                <h4>Σφάλματα Εισαγωγής</h4>
                <div class="error-section">
                    <div class="error-list">
                        <?php foreach ($errorLines as $error): ?>
                            <div class="error-item">
                                <div class="error-header">
                                    <span class="error-line">Γραμμή <?= $error['line'] ?></span>
                                    <span class="error-message"><?= htmlspecialchars($error['error']) ?></span>
                                </div>
                                <pre class="error-data"><?= htmlspecialchars($error['data']) ?></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-actions">
                <a href="bulk_import.php" class="btn-primary">🔄 Νέα Εισαγωγή</a>
                <a href="manage_questions.php" class="btn-secondary">↩️ Επιστροφή στις Ερωτήσεις</a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($import_error): ?>
        <div class="alert error"><?= $import_error ?></div>
    <?php endif; ?>
    
    <?php if ($preview_mode): ?>
        <!-- Προεπισκόπηση CSV -->
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
                                <?php foreach ($row as $index => $cell): ?>
                                    <td><?= htmlspecialchars($cell) ?></td>
                                <?php endforeach; ?>
                                
                                <?php 
                                // Προσθήκη κενών κελιών αν η γραμμή έχει λιγότερες στήλες από τις επικεφαλίδες
                                for ($i = count($row); $i < count($file_headers); $i++): 
                                ?>
                                    <td class="empty-cell"></td>
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
                    // Έλεγχος αν το CSV έχει στήλες κατηγοριοποίησης
                    $has_categorization_columns = false;
                    if (count($file_headers) >= 3) {
                        $header0_lower = mb_strtolower($file_headers[0] ?? '');
                        $header1_lower = mb_strtolower($file_headers[1] ?? '');
                        $header2_lower = mb_strtolower($file_headers[2] ?? '');
                        
                        $possible_cat_headers = ['κατηγορία', 'category', 'κατηγορια'];
                        $possible_subcat_headers = ['υποκατηγορία', 'subcategory', 'υποκατηγορια'];
                        $possible_chapter_headers = ['κεφάλαιο', 'chapter', 'κεφαλαιο'];
                        
                        if (in_array($header0_lower, $possible_cat_headers) && 
                            in_array($header1_lower, $possible_subcat_headers) && 
                            in_array($header2_lower, $possible_chapter_headers)) {
                            $has_categorization_columns = true;
                        }
                    }
                    ?>
                    
                    <div class="radio-container">
                        <div class="radio-option">
                            <input type="radio" name="use_csv_categorization" value="yes" id="use_csv_yes" <?= $has_categorization_columns ? '' : 'disabled' ?>>
                            <label for="use_csv_yes">Χρήση στηλών από το CSV<?= $has_categorization_columns ? '' : ' (Δεν διαθέτει στήλες κατηγοριοποίησης)' ?></label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="use_csv_categorization" value="no" id="use_csv_no" checked>
                            <label for="use_csv_no">Επιλογή κατηγορίας, υποκατηγορίας και κεφαλαίου για όλες τις ερωτήσεις</label>
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
                                    <option value="<?= $subcategory['id'] ?>" data-category="<?= $subcategory['test_category_id'] ?>" style="display:none;"><?= htmlspecialchars($subcategory['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="chapter_id">Κεφάλαιο:</label>
                            <select name="chapter_id" id="chapter_id" required disabled>
                                <option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>
                                <?php foreach ($chapters as $chapter): ?>
                                    <option value="<?= $chapter['id'] ?>" data-subcategory="<?= $chapter['subcategory_id'] ?>" style="display:none;"><?= htmlspecialchars($chapter['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Κρυφά πεδία για τη διατήρηση των δεδομένων από την προεπισκόπηση -->
                <input type="hidden" name="delimiter" value="<?= htmlspecialchars($_POST['delimiter'] ?? ';') ?>">
                <input type="file" name="csv_file_hidden" id="csv_file_hidden" style="display: none;">
                <input type="hidden" name="submit_import" value="1">
                
                <!-- Κουμπιά ενεργειών -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary">📥 Εισαγωγή Ερωτήσεων</button>
                    <a href="bulk_import.php" class="btn-secondary">🔄 Νέα Εισαγωγή</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Φόρμα επιλογής αρχείου CSV -->
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
                    <span class="help-text">Το αρχείο ZIP μπορεί να περιέχει εικόνες με ονόματα αρχείων της μορφής: question_1.png, question_2.png, explanation_1.png, answer_1_1.png, answer_1_2.png κλπ.</span>
                </div>
                
                <div class="form-info">
                    <h4>Οδηγίες Μορφής CSV:</h4>
                    <p>Το αρχείο CSV μπορεί να έχει δύο διαφορετικές μορφές:</p>
                    
                    <h5>1. Με στήλες κατηγοριοποίησης:</h5>
                    <ol>
                        <li><strong>Κατηγορία:</strong> Όνομα της κατηγορίας</li>
                        <li><strong>Υποκατηγορία:</strong> Όνομα της υποκατηγορίας</li>
                        <li><strong>Κεφάλαιο:</strong> Όνομα του κεφαλαίου</li>
                        <li><strong>Ερώτηση:</strong> Το κείμενο της ερώτησης</li>
                        <li><strong>Επεξήγηση:</strong> Επεξήγηση της ερώτησης (προαιρετικό)</li>
                        <li><strong>Σωστή Απάντηση:</strong> Ο αριθμός της σωστής απάντησης (1, 2, 3, κλπ.)</li>
                        <li><strong>Απάντηση 1, 2, κλπ.:</strong> Οι επιλογές απάντησης</li>
                    </ol>
                    
                    <h5>2. Χωρίς στήλες κατηγοριοποίησης:</h5>
                    <ol>
                        <li><strong>Ερώτηση:</strong> Το κείμενο της ερώτησης</li>
                        <li><strong>Επεξήγηση:</strong> Επεξήγηση της ερώτησης (προαιρετικό)</li>
                        <li><strong>Σωστή Απάντηση:</strong> Ο αριθμός της σωστής απάντησης (1, 2, 3, κλπ.)</li>
                        <li><strong>Απάντηση 1, 2, κλπ.:</strong> Οι επιλογές απάντησης</li>
                    </ol>
                    
                    <p><strong>Παραδείγματα:</strong></p>
                    <pre>1. Με κατηγοριοποίηση:
Κατηγορία;Υποκατηγορία;Κεφάλαιο;Ερώτηση;Επεξήγηση;Σωστή απάντηση;Απάντηση 1;Απάντηση 2;Απάντηση 3
Επιβατικά;Διπλώματα Β;Σήματα;Ποιο από τα παρακάτω σήματα υποδεικνύει απαγόρευση στάθμευσης;Το σήμα Ρ-39 υποδεικνύει ότι απαγορεύεται η στάθμευση.;1;Κόκκινος κύκλος με χιαστή γραμμή;Μπλε τετράγωνο με λευκό P;Κίτρινο τρίγωνο

2. Χωρίς κατηγοριοποίηση:
Ερώτηση;Επεξήγηση;Σωστή απάντηση;Απάντηση 1;Απάντηση 2;Απάντηση 3
Ποιο από τα παρακάτω σήματα υποδεικνύει απαγόρευση στάθμευσης;Το σήμα Ρ-39 υποδεικνύει ότι απαγορεύεται η στάθμευση.;1;Κόκκινος κύκλος με χιαστή γραμμή;Μπλε τετράγωνο με λευκό P;Κίτρινο τρίγωνο</pre>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">📤 Προεπισκόπηση CSV</button>
                    <a href="example_questions_with_categories.csv" download class="btn-secondary">📥 Κατέβασμα Προτύπου CSV (με κατηγορίες)</a>
                    <a href="example_questions.csv" download class="btn-secondary">📥 Κατέβασμα Προτύπου CSV (απλό)</a>
                    <a href="manage_questions.php" class="btn-link">🔙 Επιστροφή</a>
                </div>
            </form>
        </div>
        
        <div class="form-section">
            <h3>Συμβουλές για επιτυχή εισαγωγή</h3>
            
            <div class="tip-item">
                <div class="tip-icon">💡</div>
                <div class="tip-content">
                    <h4>Κωδικοποίηση αρχείου</h4>
                    <p>Βεβαιωθείτε ότι το αρχείο CSV είναι αποθηκευμένο σε κωδικοποίηση UTF-8 για σωστή εμφάνιση των ελληνικών χαρακτήρων.</p>
                </div>
            </div>
            
            <div class="tip-item">
                <div class="tip-icon">💡</div>
                <div class="tip-content">
                    <h4>Κατηγοριοποίηση</h4>
                    <p>Μπορείτε να χρησιμοποιήσετε τις στήλες κατηγοριοποίησης για να τοποθετήσετε αυτόματα κάθε ερώτηση στην αντίστοιχη κατηγορία, υποκατηγορία και κεφάλαιο. Αν δεν υπάρχει κάποια από αυτές, θα δημιουργηθεί αυτόματα.</p>
                </div>
            </div>
            
            <div class="tip-item">
                <div class="tip-icon">💡</div>
                <div class="tip-content">
                    <h4>Εικόνες</h4>
                    <p>Οι εικόνες πρέπει να είναι σε μορφή PNG ή JPG και να έχουν όνομα αρχείου της μορφής: question_1.png, explanation_1.png, answer_1_1.png κλπ. Το αριθμητικό μέρος αντιστοιχεί στον αριθμό γραμμής (χωρίς την επικεφαλίδα).</p>
                </div>
            </div>
            
            <div class="tip-item">
                <div class="tip-icon">💡</div>
                <div class="tip-content">
                    <h4>Σωστή απάντηση</h4>
                    <p>Η "Σωστή απάντηση" πρέπει να είναι ο αριθμός της απάντησης (ξεκινώντας από το 1), όχι το κείμενο της απάντησης.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require_once '../includes/admin_footer.php'; ?>

<!-- Φόρτωση των εξωτερικών αρχείων JavaScript -->
<script src="<?= BASE_URL ?>/admin/assets/js/bulk_import.js"></script>
<script src="<?= BASE_URL ?>/admin/assets/js/bulk_import_categorization.js"></script>

</body>
</html>