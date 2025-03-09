<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

header('Content-Type: application/json');

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Έλεγχος της ενέργειας που ζητήθηκε
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Καταγραφή της αίτησης
log_debug("subcategory_actions.php: Λήφθηκε αίτημα με action=$action");

// Διαχείριση των διαφορετικών ενεργειών
switch ($action) {
    case 'list':
        // Ανάκτηση υποκατηγοριών
        $query = "SELECT s.*, c.name as category_name 
                  FROM test_subcategories s 
                  JOIN test_categories c ON s.test_category_id = c.id 
                  ORDER BY c.name, s.name";
        $result = $mysqli->query($query);
        
        if ($result) {
            $subcategories = array();
            while ($row = $result->fetch_assoc()) {
                $subcategories[] = $row;
            }
            log_debug("subcategory_actions.php: Βρέθηκαν " . count($subcategories) . " υποκατηγορίες");
            echo json_encode(['success' => true, 'subcategories' => $subcategories]);
        } else {
            log_debug("subcategory_actions.php: Σφάλμα στην ανάκτηση υποκατηγοριών: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Error fetching subcategories: ' . $mysqli->error]);
        }
        break;
        
    case 'list_categories':
        // Ανάκτηση κατηγοριών
        $query = "SELECT id, name FROM test_categories ORDER BY name";
        $result = $mysqli->query($query);
        
        if ($result) {
            $categories = array();
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            log_debug("subcategory_actions.php: Βρέθηκαν " . count($categories) . " κατηγορίες");
            echo json_encode(['success' => true, 'categories' => $categories]);
        } else {
            log_debug("subcategory_actions.php: Σφάλμα στην ανάκτηση κατηγοριών: " . $mysqli->error);
            echo json_encode(['success' => false, 'message' => 'Error fetching categories: ' . $mysqli->error]);
        }
        break;
        
    case 'save':
        // Προσθήκη νέας υποκατηγορίας
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        
        // Έλεγχος υποχρεωτικών πεδίων
        if (empty($name) || empty($category_id)) {
            log_debug("subcategory_actions.php: Απόπειρα προσθήκης με ελλιπή στοιχεία");
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit;
        }
        
        // Έλεγχος αν υπάρχει ήδη η υποκατηγορία με αυτό το όνομα στην ίδια κατηγορία
        $check_query = "SELECT COUNT(*) as count FROM test_subcategories WHERE name = ? AND test_category_id = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("si", $name, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($check_result['count'] > 0) {
            log_debug("subcategory_actions.php: Η υποκατηγορία $name υπάρχει ήδη στην κατηγορία $category_id");
            echo json_encode(['success' => false, 'message' => 'A subcategory with this name already exists in this category']);
            exit;
        }
        
        // Εισαγωγή νέας υποκατηγορίας
        $insert_query = "INSERT INTO test_subcategories (name, description, icon, test_category_id) VALUES (?, ?, ?, ?)";
        $insert_stmt = $mysqli->prepare($insert_query);
        $insert_stmt->bind_param("sssi", $name, $description, $icon, $category_id);
        
        if ($insert_stmt->execute()) {
            $new_id = $insert_stmt->insert_id;
            log_debug("subcategory_actions.php: Νέα υποκατηγορία προστέθηκε: $name με ID: $new_id");
            echo json_encode(['success' => true, 'message' => 'Subcategory added successfully', 'id' => $new_id]);
        } else {
            log_debug("subcategory_actions.php: Σφάλμα στην προσθήκη υποκατηγορίας: " . $insert_stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error adding subcategory: ' . $insert_stmt->error]);
        }
        $insert_stmt->close();
        break;
        
    case 'edit':
        // Επεξεργασία υπάρχουσας υποκατηγορίας
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        
        // Έλεγχος υποχρεωτικών πεδίων
        if (empty($id) || empty($name) || empty($category_id)) {
            log_debug("subcategory_actions.php: Απόπειρα ενημέρωσης με ελλιπή στοιχεία");
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit;
        }
        
        // Έλεγχος αν υπάρχει ήδη η υποκατηγορία με αυτό το όνομα στην ίδια κατηγορία (εκτός του εαυτού της)
        $check_query = "SELECT COUNT(*) as count FROM test_subcategories WHERE name = ? AND test_category_id = ? AND id != ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("sii", $name, $category_id, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($check_result['count'] > 0) {
            log_debug("subcategory_actions.php: Υπάρχει ήδη άλλη υποκατηγορία με το όνομα $name στην κατηγορία $category_id");
            echo json_encode(['success' => false, 'message' => 'A subcategory with this name already exists in this category']);
            exit;
        }
        
        // Ενημέρωση της υποκατηγορίας
        $update_query = "UPDATE test_subcategories SET name = ?, description = ?, icon = ?, test_category_id = ? WHERE id = ?";
        $update_stmt = $mysqli->prepare($update_query);
        $update_stmt->bind_param("sssii", $name, $description, $icon, $category_id, $id);
        
        if ($update_stmt->execute()) {
            log_debug("subcategory_actions.php: Η υποκατηγορία με ID $id ενημερώθηκε");
            echo json_encode(['success' => true, 'message' => 'Subcategory updated successfully']);
        } else {
            log_debug("subcategory_actions.php: Σφάλμα στην ενημέρωση της υποκατηγορίας: " . $update_stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating subcategory: ' . $update_stmt->error]);
        }
        $update_stmt->close();
        break;
    
    case 'delete':
        // Διαγραφή υποκατηγορίας
        $id = intval($_POST['id'] ?? 0);
        
        if (empty($id)) {
            log_debug("subcategory_actions.php: Απόπειρα διαγραφής με μη έγκυρο ID");
            echo json_encode(['success' => false, 'message' => 'Invalid subcategory ID']);
            exit;
        }
        
        // Έλεγχος αν υπάρχουν κεφάλαια που χρησιμοποιούν αυτή την υποκατηγορία
        $check_query = "SELECT COUNT(*) as count FROM test_chapters WHERE subcategory_id = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($check_result['count'] > 0) {
            log_debug("subcategory_actions.php: Δεν είναι δυνατή η διαγραφή της υποκατηγορίας με ID $id καθώς έχει συσχετισμένα κεφάλαια");
            echo json_encode(['success' => false, 'message' => 'Cannot delete subcategory because it has associated chapters']);
            exit;
        }
        
        // Διαγραφή της υποκατηγορίας
        $delete_query = "DELETE FROM test_subcategories WHERE id = ?";
        $delete_stmt = $mysqli->prepare($delete_query);
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            log_debug("subcategory_actions.php: Η υποκατηγορία με ID $id διαγράφηκε επιτυχώς");
            echo json_encode(['success' => true, 'message' => 'Subcategory deleted successfully']);
        } else {
            log_debug("subcategory_actions.php: Σφάλμα κατά τη διαγραφή της υποκατηγορίας: " . $delete_stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error deleting subcategory: ' . $delete_stmt->error]);
        }
        $delete_stmt->close();
        break;
        
    default:
        log_debug("subcategory_actions.php: Άγνωστη ενέργεια: $action");
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$mysqli->close();
?>