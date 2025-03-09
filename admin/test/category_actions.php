<?php
/**
 * category_actions.php - Χειρισμός ενεργειών για τις κατηγορίες, υποκατηγορίες και κεφάλαια
 */

require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

// Ρύθμιση επικεφαλίδων
header('Content-Type: application/json');

// Καταγραφή ενεργειών
function logAction($message) {
    $logFile = BASE_PATH . '/admin/test/category_actions.log';
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Έλεγχος αν έχει οριστεί ενέργεια
if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Δεν έχει οριστεί ενέργεια.']);
    exit;
}

$action = $_POST['action'];
logAction("Action: $action");

switch ($action) {
    // ==================== Λίστα Κατηγοριών ====================
    case 'list_categories':
        $query = "SELECT id, name FROM test_categories ORDER BY name ASC";
        $result = $mysqli->query($query);
        
        if (!$result) {
            logAction("Error listing categories: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ανάκτηση κατηγοριών: ' . $mysqli->error]);
            exit;
        }
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        echo json_encode(['success' => true, 'categories' => $categories]);
        break;
    
    // ==================== Λίστα Υποκατηγοριών ====================
    case 'list_subcategories':
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        $where_clause = "";
        if ($category_id > 0) {
            $where_clause = "WHERE s.test_category_id = $category_id";
        }
        
        $query = "SELECT s.id, s.name, s.test_category_id, c.name AS category_name
                 FROM test_subcategories s
                 JOIN test_categories c ON s.test_category_id = c.id
                 $where_clause
                 ORDER BY s.name ASC";
        
        $result = $mysqli->query($query);
        
        if (!$result) {
            logAction("Error listing subcategories: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ανάκτηση υποκατηγοριών: ' . $mysqli->error]);
            exit;
        }
        
        $subcategories = [];
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = $row;
        }
        
        echo json_encode(['success' => true, 'subcategories' => $subcategories]);
        break;
    
    // ==================== Λίστα Κεφαλαίων ====================
    case 'list_chapters':
        $subcategory_id = isset($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : 0;
        
        if ($subcategory_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Απαιτείται το ID της υποκατηγορίας.']);
            exit;
        }
        
        $query = "SELECT c.id, c.name, c.subcategory_id
                 FROM test_chapters c
                 WHERE c.subcategory_id = ?
                 ORDER BY c.name ASC";
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $subcategory_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            logAction("Error listing chapters: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ανάκτηση κεφαλαίων: ' . $mysqli->error]);
            exit;
        }
        
        $chapters = [];
        while ($row = $result->fetch_assoc()) {
            $chapters[] = $row;
        }
        
        echo json_encode(['success' => true, 'chapters' => $chapters]);
        break;
    
    // ==================== Δημιουργία Κατηγορίας ====================
    case 'create_category':
        // Έλεγχος αν υπάρχουν δεδομένα
        if (!isset($_POST['data'])) {
            echo json_encode(['success' => false, 'message' => 'Δεν παρέχονται δεδομένα.']);
            exit;
        }
        
        $data = json_decode($_POST['data'], true);
        
        if (!isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Απαιτείται το όνομα της κατηγορίας.']);
            exit;
        }
        
        $name = trim($data['name']);
        
        // Έλεγχος αν υπάρχει ήδη κατηγορία με το ίδιο όνομα
        $check_query = "SELECT id FROM test_categories WHERE name = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_row = $check_result->fetch_assoc();
            echo json_encode(['success' => true, 'message' => 'Η κατηγορία υπάρχει ήδη.', 'category_id' => $existing_row['id'], 'is_new' => false]);
            exit;
        }
        
        // Δημιουργία νέας κατηγορίας
        $insert_query = "INSERT INTO test_categories (name) VALUES (?)";
        $insert_stmt = $mysqli->prepare($insert_query);
        $insert_stmt->bind_param("s", $name);
        
        if ($insert_stmt->execute()) {
            $category_id = $insert_stmt->insert_id;
            logAction("Created new category: $name (ID: $category_id)");
            echo json_encode(['success' => true, 'message' => 'Η κατηγορία δημιουργήθηκε επιτυχώς.', 'category_id' => $category_id, 'is_new' => true]);
        } else {
            logAction("Error creating category: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά τη δημιουργία της κατηγορίας: ' . $mysqli->error]);
        }
        break;
    
    // ==================== Δημιουργία Υποκατηγορίας ====================
    case 'create_subcategory':
        // Έλεγχος αν υπάρχουν δεδομένα
        if (!isset($_POST['data'])) {
            echo json_encode(['success' => false, 'message' => 'Δεν παρέχονται δεδομένα.']);
            exit;
        }
        
        $data = json_decode($_POST['data'], true);
        
        if (!isset($data['name']) || empty($data['name']) || !isset($data['category_id']) || intval($data['category_id']) <= 0) {
            echo json_encode(['success' => false, 'message' => 'Απαιτείται το όνομα της υποκατηγορίας και το ID της κατηγορίας.']);
            exit;
        }
        
        $name = trim($data['name']);
        $category_id = intval($data['category_id']);
        
        // Έλεγχος αν υπάρχει ήδη υποκατηγορία με το ίδιο όνομα στην ίδια κατηγορία
        $check_query = "SELECT id FROM test_subcategories WHERE name = ? AND test_category_id = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("si", $name, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_row = $check_result->fetch_assoc();
            echo json_encode(['success' => true, 'message' => 'Η υποκατηγορία υπάρχει ήδη.', 'subcategory_id' => $existing_row['id'], 'is_new' => false]);
            exit;
        }
        
        // Δημιουργία νέας υποκατηγορίας
        $insert_query = "INSERT INTO test_subcategories (name, test_category_id) VALUES (?, ?)";
        $insert_stmt = $mysqli->prepare($insert_query);
        $insert_stmt->bind_param("si", $name, $category_id);
        
        if ($insert_stmt->execute()) {
            $subcategory_id = $insert_stmt->insert_id;
            logAction("Created new subcategory: $name (ID: $subcategory_id) in category ID: $category_id");
            echo json_encode(['success' => true, 'message' => 'Η υποκατηγορία δημιουργήθηκε επιτυχώς.', 'subcategory_id' => $subcategory_id, 'is_new' => true]);
        } else {
            logAction("Error creating subcategory: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά τη δημιουργία της υποκατηγορίας: ' . $mysqli->error]);
        }
        break;
    
    // ==================== Δημιουργία Κεφαλαίου ====================
    case 'create_chapter':
        // Έλεγχος αν υπάρχουν δεδομένα
        if (!isset($_POST['data'])) {
            echo json_encode(['success' => false, 'message' => 'Δεν παρέχονται δεδομένα.']);
            exit;
        }
        
        $data = json_decode($_POST['data'], true);
        
        if (!isset($data['name']) || empty($data['name']) || !isset($data['subcategory_id']) || intval($data['subcategory_id']) <= 0) {
            echo json_encode(['success' => false, 'message' => 'Απαιτείται το όνομα του κεφαλαίου και το ID της υποκατηγορίας.']);
            exit;
        }
        
        $name = trim($data['name']);
        $subcategory_id = intval($data['subcategory_id']);
        
        // Έλεγχος αν υπάρχει ήδη κεφάλαιο με το ίδιο όνομα στην ίδια υποκατηγορία
        $check_query = "SELECT id FROM test_chapters WHERE name = ? AND subcategory_id = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("si", $name, $subcategory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_row = $check_result->fetch_assoc();
            echo json_encode(['success' => true, 'message' => 'Το κεφάλαιο υπάρχει ήδη.', 'chapter_id' => $existing_row['id'], 'is_new' => false]);
            exit;
        }
        
        // Δημιουργία νέου κεφαλαίου
        $insert_query = "INSERT INTO test_chapters (name, subcategory_id) VALUES (?, ?)";
        $insert_stmt = $mysqli->prepare($insert_query);
        $insert_stmt->bind_param("si", $name, $subcategory_id);
        
        if ($insert_stmt->execute()) {
            $chapter_id = $insert_stmt->insert_id;
            logAction("Created new chapter: $name (ID: $chapter_id) in subcategory ID: $subcategory_id");
            echo json_encode(['success' => true, 'message' => 'Το κεφάλαιο δημιουργήθηκε επιτυχώς.', 'chapter_id' => $chapter_id, 'is_new' => true]);
        } else {
            logAction("Error creating chapter: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά τη δημιουργία του κεφαλαίου: ' . $mysqli->error]);
        }
        break;
    
    // ==================== Προεπισκόπηση Κατηγοριοποίησης CSV ====================
    case 'preview_csv_categorization':
        // Έλεγχος αν υπάρχουν δεδομένα
        if (!isset($_POST['csv_content']) || empty($_POST['csv_content'])) {
            echo json_encode(['success' => false, 'message' => 'Δεν παρέχεται περιεχόμενο CSV.']);
            exit;
        }
        
        $csv_content = $_POST['csv_content'];
        $delimiter = isset($_POST['delimiter']) ? $_POST['delimiter'] : ';';
        
        // Ανάλυση του CSV
        $lines = explode("\n", $csv_content);
        $headers = str_getcsv(array_shift($lines), $delimiter);
        
        // Έλεγχος αν το CSV έχει τις απαραίτητες στήλες
        $has_categorization = false;
        
        if (count($headers) >= 6) {
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
            }
        }
        
        if (!$has_categorization) {
            echo json_encode(['success' => false, 'message' => 'Το CSV δεν περιέχει στήλες κατηγοριοποίησης.']);
            exit;
        }
        
        // Συλλογή των μοναδικών τιμών κατηγοριοποίησης
        $categories = [];
        $subcategories = [];
        $chapters = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $data = str_getcsv($line, $delimiter);
            
            if (count($data) >= 3) {
                $category = trim($data[0]);
                $subcategory = trim($data[1]);
                $chapter = trim($data[2]);
                
                if (!empty($category) && !isset($categories[$category])) {
                    $categories[$category] = [
                        'name' => $category,
                        'exists' => false,
                        'id' => null
                    ];
                }
                
                if (!empty($subcategory)) {
                    $subcategory_key = "{$category}|{$subcategory}";
                    if (!isset($subcategories[$subcategory_key])) {
                        $subcategories[$subcategory_key] = [
                            'name' => $subcategory,
                            'category' => $category,
                            'exists' => false,
                            'id' => null
                        ];
                    }
                }
                
                if (!empty($chapter)) {
                    $chapter_key = "{$category}|{$subcategory}|{$chapter}";
                    if (!isset($chapters[$chapter_key])) {
                        $chapters[$chapter_key] = [
                            'name' => $chapter,
                            'category' => $category,
                            'subcategory' => $subcategory,
                            'exists' => false,
                            'id' => null
                        ];
                    }
                }
            }
        }
        
        // Έλεγχος αν τα στοιχεία υπάρχουν ήδη στη βάση
        $existing_categories = [];
        $existing_subcategories = [];
        $existing_chapters = [];
        
        // Έλεγχος κατηγοριών
        $category_names = array_column($categories, 'name');
        if (!empty($category_names)) {
            $placeholders = implode(',', array_fill(0, count($category_names), '?'));
            $query = "SELECT id, name FROM test_categories WHERE name IN ($placeholders)";
            $stmt = $mysqli->prepare($query);
            
            $types = str_repeat('s', count($category_names));
            $stmt->bind_param($types, ...$category_names);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $existing_categories[$row['name']] = $row['id'];
            }
        }
        
        // Ενημέρωση κατηγοριών με υπάρχοντα IDs
        foreach ($categories as $key => &$category) {
            if (isset($existing_categories[$key])) {
                $category['exists'] = true;
                $category['id'] = $existing_categories[$key];
            }
        }
        
        // Προετοιμασία των δεδομένων για την απάντηση
        $response = [
            'success' => true,
            'has_categorization' => $has_categorization,
            'categories' => array_values($categories),
            'subcategories' => array_values($subcategories),
            'chapters' => array_values($chapters),
            'stats' => [
                'total_categories' => count($categories),
                'existing_categories' => count($existing_categories),
                'new_categories' => count($categories) - count($existing_categories),
                'total_subcategories' => count($subcategories),
                'total_chapters' => count($chapters)
            ]
        ];
        
        echo json_encode($response);
        break;
    
    // ==================== Άγνωστη ενέργεια ====================
    default:
        echo json_encode(['success' => false, 'message' => 'Άγνωστη ενέργεια: ' . $action]);
        break;
}