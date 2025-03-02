<?php
// Περιλαμβάνει τις βασικές ρυθμίσεις και τη σύνδεση με τη βάση
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

// Ορίζει το header για JSON απόκριση
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Επιτρέπει αιτήματα από οποιαδήποτε προέλευση για testing
header('Access-Control-Allow-Methods: POST, GET, OPTIONS'); // Επιτρέπει συγκεκριμένες μεθόδους
header('Access-Control-Allow-Headers: Content-Type'); // Επιτρέπει headers

// Ελέγχει αν έχει οριστεί ενέργεια (action) στο αίτημα, από POST ή GET
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
if (empty($action)) {
    error_log("No action specified in profile.php API request for user profile. BASE_URL: " . BASE_URL . " - BASE_PATH: " . BASE_PATH . " - Current path: " . __DIR__ . " - Server root: " . $_SERVER['DOCUMENT_ROOT'] . " - GET data: " . print_r($_GET, true) . " - POST data: " . print_r($_POST, true) . " - FILES data: " . print_r($_FILES, true), 3, BASE_PATH . '/debug_log.txt');
    echo json_encode(['success' => false, 'message' => 'Δεν ορίστηκε ενέργεια.']);
    exit();
}

try {
    error_log("Processing action: $action in user profile API. Current directory: " . __DIR__ . " - Server root: " . $_SERVER['DOCUMENT_ROOT'] . " - BASE_PATH: " . BASE_PATH . " - GET data: " . print_r($_GET, true) . " - POST data: " . print_r($_POST, true) . " - FILES data: " . print_r($_FILES, true), 3, BASE_PATH . '/debug_log.txt');

    // Επαλήθευση σύνδεσης με τη βάση δεδομένων
    if (!$mysqli) {
        error_log("Database connection failed in profile.php: " . (isset($mysqli) ? $mysqli->error : 'No connection object') . " - BASE_PATH: " . BASE_PATH . " - Current path: " . __DIR__, 3, BASE_PATH . '/debug_log.txt');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }

    // Λήψη user_id από POST ή GET
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
    if (!$user_id) {
        error_log("No user_id provided in profile.php for action: $action. POST data: " . print_r($_POST, true) . " GET data: " . print_r($_GET, true) . " - BASE_PATH: " . BASE_PATH . " - Path: " . __DIR__, 3, BASE_PATH . '/debug_log.txt');
        echo json_encode(['success' => false, 'message' => 'No userID provided.']);
        exit();
    }

    switch ($action) {
        case 'update':
            // Έναρξη ενημέρωσης δεδομένων χρήστη
            error_log("Starting update for user ID: $user_id in user profile API. Server PATH: " . $_SERVER['DOCUMENT_ROOT'] . " - BASE_PATH: " . BASE_PATH . " - Current path: " . __DIR__ . " - POST data: " . print_r($_POST, true) . " - FILES data: " . print_r($_FILES, true), 3, BASE_PATH . '/debug_log.txt');

            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? null);
            $street_number = trim($_POST['street_number'] ?? null);
            $postal_code = trim($_POST['postal_code'] ?? null);
            $city = trim($_POST['city'] ?? null);

            // Ελέγχει αν τα απαιτούμενα πεδία είναι συμπληρωμένα
            if (empty($fullname) || empty($email)) {
                error_log("Missing required fields: fullname or email for user_id $user_id. POST data: " . print_r($_POST, true) . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                echo json_encode(['success' => false, 'message' => 'Τα πεδία Ονοματεπώνυμο και Email είναι υποχρεωτικά.']);
                exit();
            }

            // Γεωκωδικοποίηση διεύθυνσης με Google Maps API
            error_log("Geocoding address: $address $street_number, $city, $postal_code, Ελλάδα. API Key: " . $config['google_maps_api_key'] . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
            $geocode_url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address . " " . $street_number . ", " . $city . ", " . $postal_code . ", Ελλάδα") . "&key=" . $config['google_maps_api_key'];
            $geocode_response = @file_get_contents($geocode_url); // Προσθήκη @ για να αποφύγουμε fatal errors αν αποτύχει
            if ($geocode_response === false) {
                error_log("Failed to fetch geocode response for user_id $user_id: Network or API key issue. URL: $geocode_url - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
            } else {
                error_log("Geocode response: " . $geocode_response, 3, BASE_PATH . '/debug_log.txt');
            }
            $geocode_data = json_decode($geocode_response, true);

            $latitude = null;
            $longitude = null;
            if ($geocode_data['status'] === 'OK' && !empty($geocode_data['results'])) {
                $location = $geocode_data['results'][0]['geometry']['location'];
                $latitude = $location['lat'];
                $longitude = $location['lng'];
                error_log("Geocoding success - Latitude: $latitude, Longitude: $longitude for user_id $user_id - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
            } else {
                error_log("Geocode failed for user_id $user_id: " . ($geocode_data['status'] ?? 'No status') . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
            }

            // Διαχείριση avatar αν υπάρχει νέο αρχείο
            $avatar_path = null;
            if (!empty($_FILES['avatar']['name'])) {
                $upload_dir = '../../uploads/avatars/';
                $avatar_name = 'avatar_' . $user_id . '.' . strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $avatar_path = $upload_dir . $avatar_name;
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                    error_log("Created uploads/avatars directory for user_id $user_id at path: " . realpath($upload_dir) . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                }
                if (!is_writable($upload_dir)) {
                    error_log("Uploads/avatars directory is not writable for user_id $user_id. Path: " . realpath($upload_dir) . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                }
                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
                    error_log("Failed to upload avatar for user_id $user_id: " . $_FILES['avatar']['name'] . " - Error: " . print_r(error_get_last(), true) . " - Path: " . $avatar_path . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την αποθήκευση του avatar.']);
                    exit();
                }
                error_log("Avatar uploaded successfully to $avatar_path for user_id $user_id - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
            }

            // Λήψη τρέχοντος avatar για να διατηρηθεί αν δεν ανέβηκε νέο
            $stmt = $mysqli->prepare("SELECT avatar FROM users WHERE id = ?");
            if (!$stmt) {
                error_log("Prepare failed for SELECT avatar for user_id $user_id: " . $mysqli->error . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                throw new Exception("Prepare failed: " . $mysqli->error);
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_user = $result->fetch_assoc();
            $stmt->close();
            $final_avatar = $avatar_path ? $avatar_name : ($current_user['avatar'] ?? null);

            // Έναρξη transaction για ακεραιότητα δεδομένων
            $mysqli->begin_transaction();
            error_log("Transaction started for user ID: $user_id in user profile API. Connection state: " . $mysqli->thread_id . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');

            // Ενημέρωση δεδομένων χρήστη στον πίνακα users
            $stmt_user = $mysqli->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, street_number = ?, city = ?, postal_code = ?, latitude = ?, longitude = ?, avatar = ? WHERE id = ?");
            if (!$stmt_user) {
                error_log("Prepare failed for users update for user_id $user_id: " . $mysqli->error . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                throw new Exception("Prepare failed for users: " . $mysqli->error);
            }
            $stmt_user->bind_param("sssssssdsi", $fullname, $email, $phone, $address, $street_number, $city, $postal_code, $latitude, $longitude, $final_avatar, $user_id);
            if (!$stmt_user->execute()) {
                error_log("User update failed for user_id $user_id: " . $stmt_user->error . " - Query: " . $stmt_user->query . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                $mysqli->rollback();
                throw new Exception("User update failed: " . $stmt_user->error);
            }
            $mysqli->commit();
            error_log("User update successful for user_id $user_id. Affected rows: " . $stmt_user->affected_rows . " - Connection state: " . $mysqli->thread_id . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
            echo json_encode(['success' => true, 'message' => 'Το προφίλ ενημερώθηκε επιτυχώς.']);
            $stmt_user->close();
            break;

        case 'upload_avatar':
            // Διαχείριση ανεβάσματος avatar
            error_log("Avatar upload request received for user_id $user_id. POST data: " . print_r($_POST, true) . " FILES: " . print_r($_FILES, true) . " - BASE_PATH: " . BASE_PATH . " - Current directory: " . __DIR__ . " - Server root: " . $_SERVER['DOCUMENT_ROOT'], 3, BASE_PATH . '/debug_log.txt');
            
            if (isset($_FILES['avatar']) && !empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                $fileName = basename($file['name']);
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($fileType, $allowedTypes)) {
                    error_log("Invalid file type for avatar for user_id $user_id: $fileName - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                    echo json_encode(['success' => false, 'message' => 'Μη αποδεκτός τύπος αρχείου. Επιτρέπονται μόνο JPG, JPEG, PNG, GIF.']);
                    exit();
                } else {
                    $uploadDir = '../../uploads/avatars/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                        error_log("Created uploads/avatars directory for user_id $user_id at path: " . realpath($uploadDir) . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                    }
                    if (!is_writable($uploadDir)) {
                        error_log("Uploads/avatars directory is not writable for user_id $user_id. Path: " . realpath($uploadDir) . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                    }

                    $newFileName = 'avatar_' . $user_id . '.' . $fileType;
                    $targetFile = $uploadDir . $newFileName;

                    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                        error_log("Avatar uploaded successfully to $targetFile for user_id $user_id - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                        $query = "UPDATE users SET avatar = ? WHERE id = ?";
                        $stmt = $mysqli->prepare($query);
                        if (!$stmt) {
                            error_log("Failed to prepare avatar update query for user_id $user_id: " . $mysqli->error . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                            echo json_encode(['success' => false, 'message' => 'Failed to prepare avatar update query: ' . $mysqli->error]);
                            exit();
                        }
                        $stmt->bind_param("si", $newFileName, $user_id);
                        if ($stmt->execute()) {
                            error_log("Avatar updated in database for user_id $user_id. Affected rows: " . $stmt->affected_rows . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                            echo json_encode(['success' => true, 'message' => 'Avatar ενημερώθηκε επιτυχώς!']);
                        } else {
                            error_log("Failed to update avatar in database for user_id $user_id: " . $mysqli->error . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                            echo json_encode(['success' => false, 'message' => 'Failed to update avatar in database: ' . $mysqli->error]);
                        }
                        $stmt->close();
                    } else {
                        error_log("Failed to move uploaded file to $targetFile for user_id $user_id. Error: " . print_r(error_get_last(), true) . " - Path: " . $targetFile . " - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                        echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την αποθήκευση του αρχείου.']);
                    }
                }
            } else {
                error_log("No avatar file uploaded for user_id $user_id - BASE_PATH: " . BASE_PATH, 3, BASE_PATH . '/debug_log.txt');
                echo json_encode(['success' => false, 'message' => 'Δεν ανέβηκε αρχείο avatar.']);
            }
            break;

        default:
            error_log("Unknown action: $action for user_id $user_id in user profile API - BASE_PATH: " . BASE_PATH . " - Path: " . __DIR__ . " - GET data: " . print_r($_GET, true) . " - POST data: " . print_r($_POST, true), 3, BASE_PATH . '/debug_log.txt');
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Exception $e) {
    error_log("API error in profile.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString() . " - BASE_PATH: " . BASE_PATH . " - Path: " . __DIR__, 3, BASE_PATH . '/debug_log.txt');
    echo json_encode(['success' => false, 'message' => 'Σφάλμα στο server: ' . $e->getMessage()]);
}

// Κλείνει τη σύνδεση με τη βάση δεδομένων
$mysqli->close();
exit();