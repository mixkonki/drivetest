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
                // Προσθήκη πλήρους URL για εικόνες (για εμφάνιση στη JavaScript)
                if (!empty($row['icon'])) {
                    if (strpos($row['icon'], 'http') !== 0) {
                        $row['icon_url'] = $config['base_url'] . '/assets/images/' . $row['icon'];
                    } else {
                        $row['icon_url'] = $row['icon'];
                    }
                } else {
                    $row['icon_url'] = $config['base_url'] . '/assets/images/default.png';
                }
                
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
        
        // Διαχείριση αρχείου εικόνας αν περιλαμβάνεται
        if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = BASE_PATH . '/assets/images/categories/';
            
            // Έλεγχος αν υπάρχει ο φάκελος και αν όχι, δημιουργία του
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
                log_debug("Created directory: $upload_dir");
            }
            
            // Έλεγχος τύπου αρχείου
            $filename = basename($_FILES['icon_file']['name']);
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'svg');
            
            if (!in_array($file_ext, $allowed_exts)) {
                log_debug("subcategory_actions.php: Μη επιτρεπτός τύπος αρχείου: $file_ext");
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, svg']);
                exit;
            }
            
            // Δημιουργία προσωρινού ονόματος αρχείου
            $temp_filename = 'subcategory_temp_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $upload_path = $upload_dir . $temp_filename;
            
            if (move_uploaded_file($_FILES['icon_file']['tmp_name'], $upload_path)) {
                $icon = 'categories/' . $temp_filename;
                log_debug("subcategory_actions.php: Επιτυχές ανέβασμα αρχείου: $upload_path");
            } else {
                log_debug("subcategory_actions.php: Αποτυχία ανεβάσματος αρχείου");
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                exit;
            }
        }
        
        // Εισαγωγή νέας υποκατηγορίας
        $insert_query = "INSERT INTO test_subcategories (name, description, icon, test_category_id) VALUES (?, ?, ?, ?)";
        $insert_stmt = $mysqli->prepare($insert_query);
        $insert_stmt->bind_param("sssi", $name, $description, $icon, $category_id);
        
        if ($insert_stmt->execute()) {
            $new_id = $insert_stmt->insert_id;
            log_debug("subcategory_actions.php: Νέα υποκατηγορία προστέθηκε: $name με ID: $new_id");
            
            // Αν έχει ανεβεί εικόνα, μετονομασία του αρχείου με το ID της νέας υποκατηγορίας
            if (!empty($icon) && strpos($icon, 'subcategory_temp_') !== false) {
                $old_path = $upload_dir . basename($icon);
                $new_filename = 'subcategory_' . $new_id . '_' . time() . '.' . $file_ext;
                $new_path = $upload_dir . $new_filename;
                
                if (rename($old_path, $new_path)) {
                    $new_icon = 'categories/' . $new_filename;
                    
                    // Ενημέρωση του εικονιδίου στη βάση
                    $update_icon = "UPDATE test_subcategories SET icon = ? WHERE id = ?";
                    $stmt_update = $mysqli->prepare($update_icon);
                    $stmt_update->bind_param("si", $new_icon, $new_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                    
                    log_debug("subcategory_actions.php: Αρχείο μετονομάστηκε: $icon -> $new_icon");
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Subcategory added successfully', 'id' => $new_id]);
        } else {
            log_debug("subcategory_actions.php: Σφάλμα στην προσθήκη υποκατηγορίας: " . $insert_stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error adding subcategory: ' . $insert_stmt->error]);
            
            // Αν υπάρχει ανεβασμένο αρχείο, το διαγράφουμε
            if (!empty($icon) && file_exists($upload_dir . basename($icon))) {
                unlink($upload_dir . basename($icon));
                log_debug("subcategory_actions.php: Διαγραφή προσωρινού αρχείου: " . $upload_dir . basename($icon));
            }
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
        
        // Ανάκτηση του τρέχοντος εικονιδίου
        $get_icon_query = "SELECT icon FROM test_subcategories WHERE id = ?";
        $get_icon_stmt = $mysqli->prepare($get_icon_query);
        $get_icon_stmt->bind_param("i", $id);
        $get_icon_stmt->execute();
        $current_icon = $get_icon_stmt->get_result()->fetch_assoc()['icon'] ?? '';
        $get_icon_stmt->close();
        
        // Διαχείριση αρχείου εικόνας αν περιλαμβάνεται
        if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = BASE_PATH . '/assets/images/categories/';
            
            // Έλεγχος αν υπάρχει ο φάκελος και αν όχι, δημιουργία του
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
                log_debug("Created directory: $upload_dir");
            }
            
            // Έλεγχος τύπου αρχείου
            $filename = basename($_FILES['icon_file']['name']);
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'svg');
            
            if (!in_array($file_ext, $allowed_exts)) {
                log_debug("subcategory_actions.php: Μη επιτρεπτός τύπος αρχείου: $file_ext");
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, svg']);
                exit;
            }
            
            // Δημιουργία νέου ονόματος αρχείου με το ID της υποκατηγορίας
            $new_filename = 'subcategory_' . $id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['icon_file']['tmp_name'], $upload_path)) {
                $icon = 'categories/' . $new_filename;
                log_debug("subcategory_actions.php: Επιτυχές ανέβασμα αρχείου: $upload_path");
                
                // Διαγραφή του παλιού αρχείου αν υπάρχει και δεν είναι το ίδιο
                if (!empty($current_icon) && $current_icon !== $icon && strpos($current_icon, 'categories/') === 0) {
                    $old_file_path = $upload_dir . str_replace('categories/', '', $current_icon);
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                        log_debug("subcategory_actions.php: Διαγραφή παλιού αρχείου: $old_file_path");
                    }
                }
            } else {
                log_debug("subcategory_actions.php: Αποτυχία ανεβάσματος αρχείου");
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                exit;
            }
        }
        
        // Ενημέρωση της υποκατηγορίας
        $update_query = "UPDATE test_subcategories SET name = ?, description = ?, test_category_id = ?";
        $types = "ssi";
        $params = [$name, $description, $category_id];
        
        // Προσθήκη του εικονιδίου αν έχει οριστεί
        if (!empty($icon)) {
            $update_query .= ", icon = ?";
            $types .= "s";
            $params[] = $icon;
        }
        
        $update_query .= " WHERE id = ?";
        $types .= "i";
        $params[] = $id;
        
        $update_stmt = $mysqli->prepare($update_query);
        $update_stmt->bind_param($types, ...$params);
        
        if ($update_stmt->execute()) {
            log_debug("subcategory_actions.php: Η υποκατηγορία με ID $id ενημερώθηκε");
            echo json_encode(['success' => true, 'message' => 'Subcategory updated successfully']);
        } else {
            log_debug("subcategory_actions.php: Σφάλμα στην ενημέρωση της υποκατηγορίας: " . $update_stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating subcategory: ' . $update_stmt->error]);
            
            // Αν έχει ανεβεί νέο αρχείο και αποτύχει η ενημέρωση, διαγραφή του
            if (!empty($icon) && $icon !== $current_icon && strpos($icon, 'categories/') === 0) {
                $file_path = $upload_dir . str_replace('categories/', '', $icon);
                if (file_exists($file_path)) {
                    unlink($file_path);
                    log_debug("subcategory_actions.php: Διαγραφή νέου αρχείου μετά από αποτυχία: $file_path");
                }
            }
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
        
        // Ανάκτηση του εικονιδίου πριν τη διαγραφή
        $get_icon_query = "SELECT icon FROM test_subcategories WHERE id = ?";
        $get_icon_stmt = $mysqli->prepare($get_icon_query);
        $get_icon_stmt->bind_param("i", $id);
        $get_icon_stmt->execute();
        $icon = $get_icon_stmt->get_result()->fetch_assoc()['icon'] ?? '';
        $get_icon_stmt->close();
        
        // Διαγραφή της υποκατηγορίας
        $delete_query = "DELETE FROM test_subcategories WHERE id = ?";
        $delete_stmt = $mysqli->prepare($delete_query);
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            log_debug("subcategory_actions.php: Η υποκατηγορία με ID $id διαγράφηκε επιτυχώς");
            
            // Διαγραφή του αρχείου εικόνας αν υπάρχει
            if (!empty($icon) && strpos($icon, 'categories/') === 0) {
                $upload_dir = BASE_PATH . '/assets/images/categories/';
                $file_path = $upload_dir . str_replace('categories/', '', $icon);
                if (file_exists($file_path)) {
                    unlink($file_path);
                    log_debug("subcategory_actions.php: Διαγραφή εικόνας: $file_path");
                }
            }
            
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