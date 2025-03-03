<?php
// Περιλαμβάνει τις βασικές ρυθμίσεις και τη σύνδεση με τη βάση
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

// Επίλυση προβλημάτων CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Για αιτήματα OPTIONS, απλώς επιστρέφουμε επιτυχία
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ελέγχει αν έχει οριστεί ενέργεια (action) στο αίτημα, από POST ή GET
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Αν δεν υπάρχει action, αλλά είναι GET request, επιστρέφουμε ένα "health check" response
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'message' => 'API endpoint is healthy', 'requiresAction' => true]);
    exit();
}

// Αν δεν υπάρχει action για άλλα αιτήματα, επιστρέφουμε error
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Δεν ορίστηκε ενέργεια.', 'method' => $_SERVER['REQUEST_METHOD']]);
    exit();
}

try {
    // Επαλήθευση σύνδεσης με τη βάση δεδομένων
    if (!$mysqli) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }
    
    // Έλεγχος αν ο χρήστης είναι συνδεδεμένος
    session_start();
    if (!isset($_SESSION['user_id'])) {
        // Εάν δεν υπάρχει, ελέγχουμε αν έχει προσδιοριστεί το user_id
        if (!isset($_POST['user_id']) && !isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'Δεν έχετε συνδεθεί.']);
            exit();
        }
    }
    
    // Προτεραιότητα στο user_id της συνεδρίας αν υπάρχει
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0));
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'No userID provided.']);
        exit();
    }
    
    switch ($action) {
        case 'test':
            echo json_encode(['success' => true, 'message' => 'API Test successful', 'user_id' => $user_id]);
            break;
            
        case 'update':
            // Ανάκτηση δεδομένων
            $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : null;
            $street_number = isset($_POST['street_number']) ? trim($_POST['street_number']) : null;
            $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : null;
            $city = isset($_POST['city']) ? trim($_POST['city']) : null;
            $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
            $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
            
            // Ελέγχει αν τα απαιτούμενα πεδία είναι συμπληρωμένα
            if (empty($fullname) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Τα πεδία Ονοματεπώνυμο και Email είναι υποχρεωτικά.']);
                exit();
            }
            
            // Διαχείριση avatar αν υπάρχει νέο αρχείο
            $avatar_name = null;
            if (!empty($_FILES['avatar']['name'])) {
                $upload_dir = '../../uploads/avatars/';
                $avatar_name = 'avatar_' . $user_id . '.' . strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $avatar_path = $upload_dir . $avatar_name;
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
                    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την αποθήκευση του avatar.']);
                    exit();
                }
            }
            
            // Λήψη τρέχοντος avatar για να διατηρηθεί αν δεν ανέβηκε νέο
            $stmt = $mysqli->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_user = $result->fetch_assoc();
            $stmt->close();
            
            $final_avatar = $avatar_name ? $avatar_name : ($current_user['avatar'] ?? null);
            
            // Έναρξη transaction
            $mysqli->begin_transaction();
            
            // Ενημέρωση δεδομένων χρήστη στον πίνακα users
            $stmt_user = $mysqli->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, street_number = ?, city = ?, postal_code = ?, latitude = ?, longitude = ?, avatar = ? WHERE id = ?");
            $stmt_user->bind_param("sssssssddsi", $fullname, $email, $phone, $address, $street_number, $city, $postal_code, $latitude, $longitude, $final_avatar, $user_id);
            
            if (!$stmt_user->execute()) {
                $mysqli->rollback();
                throw new Exception("User update failed: " . $stmt_user->error);
            }
            
            $mysqli->commit();
            
            echo json_encode(['success' => true, 'message' => 'Το προφίλ ενημερώθηκε επιτυχώς.']);
            $stmt_user->close();
            break;
            
        case 'upload_avatar':
            if (isset($_FILES['avatar']) && !empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                $fileName = basename($file['name']);
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    echo json_encode(['success' => false, 'message' => 'Μη αποδεκτός τύπος αρχείου. Επιτρέπονται μόνο JPG, JPEG, PNG, GIF.']);
                    exit();
                }
                
                $uploadDir = '../../uploads/avatars/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $newFileName = 'avatar_' . $user_id . '.' . $fileType;
                $targetFile = $uploadDir . $newFileName;
                
                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $query = "UPDATE users SET avatar = ? WHERE id = ?";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("si", $newFileName, $user_id);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Avatar ενημερώθηκε επιτυχώς!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Αποτυχία ενημέρωσης avatar.']);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την αποθήκευση του αρχείου.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Δεν ανέβηκε αρχείο avatar.']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα στο server: ' . $e->getMessage()]);
}

// Κλείνει τη σύνδεση με τη βάση δεδομένων
$mysqli->close();
exit();
?>