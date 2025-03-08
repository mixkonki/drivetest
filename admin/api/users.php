<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['action']) || empty($_GET['action'])) {
    error_log("No action specified in users.php API request", 3, "C:/wamp64/www/drivetest/debug_log.txt");
    echo json_encode(['success' => false, 'message' => 'Δεν ορίστηκε ενέργεια.']);
    exit();
}

$action = $_GET['action'];

try {
    error_log("Processing action: $action", 3, "C:/wamp64/www/drivetest/debug_log.txt");

    switch ($action) {
        // Προσθήκη νέας περίπτωσης για ταξινόμηση
        case 'sort':
            // Επικύρωση παραμέτρων ταξινόμησης
            $valid_columns = ['id', 'fullname', 'email', 'role', 'status', 'subscription_status', 'created_at', 'school_id', 'phone'];
            $sort_column = isset($_GET['column']) && in_array($_GET['column'], $valid_columns) ? $_GET['column'] : 'id';
            $sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
            
            error_log("Sorting by column: $sort_column, order: $sort_order", 3, "C:/wamp64/www/drivetest/debug_log.txt");
            
            // Δημιουργία του ερωτήματος ταξινόμησης
            $query = "SELECT u.id, u.fullname, u.email, u.role, u.subscription_status, u.avatar, u.created_at, u.status, u.school_id, u.phone, s.name AS school_name 
                      FROM users u 
                      LEFT JOIN schools s ON u.school_id = s.id 
                      ORDER BY u.$sort_column $sort_order";
            
            $result = $mysqli->query($query);
            $roles = ['user' => 'Χρήστης', 'student' => 'Μαθητής', 'school' => 'Σχολή', 'admin' => 'Διαχειριστής'];
            $html = '';
            
            if ($result->num_rows > 0) {
                while ($user = $result->fetch_assoc()) {
                    $avatar_url = !empty($user['avatar']) ? $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) : $config['base_url'] . '/uploads/avatars/default.png';
                    $html .= "<tr>
                        <td>
                            <div class=\"user-info-tooltip\">
                                <img src=\"" . htmlspecialchars($avatar_url) . "\" class=\"user-avatar\" alt=\"Avatar χρήστη " . htmlspecialchars($user['fullname']) . "\">
                                <div class=\"tooltip-content\">
                                    <strong>" . htmlspecialchars($user['fullname']) . "</strong><br>
                                    <small>ID: " . $user['id'] . "</small>
                                </div>
                            </div>
                        </td>
                        <td><a href=\"" . $config['base_url'] . "/admin/edit_user.php?id=" . $user['id'] . "\" class=\"user-name-link\">" . htmlspecialchars($user['fullname']) . "</a></td>
                        <td>" . htmlspecialchars($user['email']) . "</td>
                        <td>" . htmlspecialchars($roles[$user['role']] ?? 'Άγνωστος') . "</td>
                        <td>" . htmlspecialchars($user['school_name'] ?? 'Καμία') . "</td>
                        <td>" . htmlspecialchars($user['subscription_status']) . "</td>
                        <td>" . htmlspecialchars($user['phone'] ?? '') . "</td>
                        <td>" . ($user['status'] == 'active' ? '<span class="status-active">Ενεργός</span>' : '<span class="status-inactive">Ανενεργός</span>') . "</td>
                        <td>" . htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))) . "</td>
                    </tr>";
                }
                echo json_encode(['success' => true, 'html' => $html]);
            } else {
                echo json_encode(['success' => true, 'html' => '<tr><td colspan="9">Καμία εγγραφή δεν βρέθηκε.</td></tr>']);
            }
            break;

        case 'filter':
            $query = "SELECT u.id, u.fullname, u.email, u.role, u.subscription_status, u.avatar, u.created_at, u.status, u.school_id, u.phone, s.name AS school_name 
                      FROM users u 
                      LEFT JOIN schools s ON u.school_id = s.id 
                      WHERE 1=1";
            $params = [];
            $types = '';
            
            if (!empty($_POST['search'])) {
                $search = $mysqli->real_escape_string($_POST['search']);
                $query .= " AND (u.fullname LIKE ? OR u.email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $types .= 'ss';
            }
            
            if (!empty($_POST['role'])) {
                $role = $mysqli->real_escape_string($_POST['role']);
                $query .= " AND u.role = ?";
                $params[] = $role;
                $types .= 's';
            }
            
            // Προσθήκη ταξινόμησης στο φιλτράρισμα
            $valid_columns = ['id', 'fullname', 'email', 'role', 'status', 'subscription_status', 'created_at', 'school_id', 'phone'];
            $sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $valid_columns) ? $_GET['sort'] : 'id';
            $sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
            
            $query .= " ORDER BY u.$sort_column $sort_order";
            
            $stmt = $mysqli->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $roles = ['user' => 'Χρήστης', 'student' => 'Μαθητής', 'school' => 'Σχολή', 'admin' => 'Διαχειριστής'];
            $html = '';
            
            if ($result->num_rows > 0) {
                while ($user = $result->fetch_assoc()) {
                    $avatar_url = !empty($user['avatar']) ? $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) : $config['base_url'] . '/uploads/avatars/default.png';
                    $html .= "<tr>
                        <td>
                            <div class=\"user-info-tooltip\">
                                <img src=\"" . htmlspecialchars($avatar_url) . "\" class=\"user-avatar\" alt=\"Avatar χρήστη " . htmlspecialchars($user['fullname']) . "\">
                                <div class=\"tooltip-content\">
                                    <strong>" . htmlspecialchars($user['fullname']) . "</strong><br>
                                    <small>ID: " . $user['id'] . "</small>
                                </div>
                            </div>
                        </td>
                        <td><a href=\"" . $config['base_url'] . "/admin/edit_user.php?id=" . $user['id'] . "\" class=\"user-name-link\">" . htmlspecialchars($user['fullname']) . "</a></td>
                        <td>" . htmlspecialchars($user['email']) . "</td>
                        <td>" . htmlspecialchars($roles[$user['role']] ?? 'Άγνωστος') . "</td>
                        <td>" . htmlspecialchars($user['school_name'] ?? 'Καμία') . "</td>
                        <td>" . htmlspecialchars($user['subscription_status']) . "</td>
                        <td>" . htmlspecialchars($user['phone'] ?? '') . "</td>
                        <td>" . ($user['status'] == 'active' ? '<span class="status-active">Ενεργός</span>' : '<span class="status-inactive">Ανενεργός</span>') . "</td>
                        <td>" . htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))) . "</td>
                    </tr>";
                }
                echo json_encode(['success' => true, 'html' => $html]);
            } else {
                echo json_encode(['success' => true, 'html' => '<tr><td colspan="9">Καμία εγγραφή δεν βρέθηκε.</td></tr>']);
            }
            $stmt->close();
            break;

        case 'delete':
            $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Μη έγκυρο ID χρήστη.']);
                exit();
            }
            
            $check_stmt = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Ο χρήστης δεν βρέθηκε.']);
                $check_stmt->close();
                exit();
            }
            
            $user = $check_result->fetch_assoc();
            $check_stmt->close();

            $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                if ($user['role'] === 'school') {
                    $stmt_school = $mysqli->prepare("DELETE FROM schools WHERE id = ?");
                    $stmt_school->bind_param("i", $id);
                    $stmt_school->execute();
                    $stmt_school->close();
                }
                echo json_encode(['success' => true, 'message' => 'Ο χρήστης διαγράφηκε επιτυχώς.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά τη διαγραφή: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        case 'update':
            error_log("Starting update for user ID: " . ($_GET['id'] ?? 'unknown'), 3, "C:/wamp64/www/drivetest/debug_log.txt");
            error_log("Received POST data: " . print_r($_POST, true), 3, "C:/wamp64/www/drivetest/debug_log.txt");
            error_log("Received FILES data: " . print_r($_FILES, true), 3, "C:/wamp64/www/drivetest/debug_log.txt");

            $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                error_log("Invalid user ID: " . ($_GET['id'] ?? ''), 3, "C:/wamp64/www/drivetest/debug_log.txt");
                echo json_encode(['success' => false, 'message' => 'Μη έγκυρο ID χρήστη.']);
                exit();
            }

            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? null);
            $street_number = trim($_POST['street_number'] ?? null);
            $postal_code = trim($_POST['postal_code'] ?? null);
            $city = trim($_POST['city'] ?? null);
            $latitude = trim($_POST['latitude'] ?? null);
            $longitude = trim($_POST['longitude'] ?? null);
            $role = in_array($_POST['role'] ?? 'user', ['user', 'student', 'school', 'admin']) ? $_POST['role'] : 'user';
            $subscription_status = in_array($_POST['subscription_status'] ?? 'pending', ['pending', 'active', 'expired']) ? $_POST['subscription_status'] : 'pending';
            $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive']) ? $_POST['status'] : 'active';
            $phone = trim($_POST['phone'] ?? null);
            $school_id = $role === 'school' ? $id : (trim($_POST['school_id'] ?? null) ?: null);
            $license_number = $role === 'school' ? trim($_POST['license_number'] ?? null) : null;
            $responsible_person = $role === 'school' ? trim($_POST['responsible_person'] ?? null) : null;

            if (empty($fullname) || empty($email)) {
                error_log("Missing required fields: fullname or email", 3, "C:/wamp64/www/drivetest/debug_log.txt");
                echo json_encode(['success' => false, 'message' => 'Τα πεδία Ονοματεπώνυμο και Email είναι υποχρεωτικά.']);
                exit();
            }

            $avatar_path = null;
            if (!empty($_FILES['avatar']['name'])) {
                $upload_dir = '../../uploads/avatars/';
                $avatar_name = basename($_FILES['avatar']['name']);
                $avatar_path = $upload_dir . $avatar_name;
                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
                    error_log("Failed to upload avatar: " . $_FILES['avatar']['name'], 3, "C:/wamp64/www/drivetest/debug_log.txt");
                    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την αποθήκευση του avatar.']);
                    exit();
                }
            }

            $stmt = $mysqli->prepare("SELECT avatar FROM users WHERE id = ?");
            if (!$stmt) {
                error_log("Prepare failed for SELECT avatar: " . $mysqli->error, 3, "C:/wamp64/www/drivetest/debug_log.txt");
                throw new Exception("Prepare failed: " . $mysqli->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_user = $result->fetch_assoc();
            $stmt->close();
            $final_avatar = $avatar_path ?: ($current_user['avatar'] ?? null);

            $mysqli->begin_transaction();
            error_log("Transaction started for user ID: $id", 3, "C:/wamp64/www/drivetest/debug_log.txt");

            if ($role === 'school') {
                $stmt_school = $mysqli->prepare("INSERT INTO schools (id, name, license_number, responsible_person, address, street_number, postal_code, city, email) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                                                ON DUPLICATE KEY UPDATE name=VALUES(name), license_number=VALUES(license_number), responsible_person=VALUES(responsible_person), 
                                                address=VALUES(address), street_number=VALUES(street_number), postal_code=VALUES(postal_code), city=VALUES(city), email=VALUES(email)");
                if (!$stmt_school) {
                    error_log("Prepare failed for schools update: " . $mysqli->error, 3, "C:/wamp64/www/drivetest/debug_log.txt");
                    throw new Exception("Prepare failed for schools: " . $mysqli->error);
                }
                $stmt_school->bind_param("issssssss", $id, $fullname, $license_number, $responsible_person, $address, $street_number, $postal_code, $city, $email);
                if (!$stmt_school->execute()) {
                    error_log("School update failed: " . $stmt_school->error, 3, "C:/wamp64/www/drivetest/debug_log.txt");
                    $mysqli->rollback();
                    throw new Exception("School update failed: " . $stmt_school->error);
                }
                $stmt_school->close();
            }

            error_log("Preparing user update for ID: $id", 3, "C:/wamp64/www/drivetest/debug_log.txt");
            // ΔΙΟΡΘΩΣΗ: Προσθήκη του 'street_number' στο ερώτημα UPDATE
            $stmt_user = $mysqli->prepare("UPDATE users SET fullname=?, email=?, address=?, street_number=?, postal_code=?, city=?, latitude=?, longitude=?, avatar=?, role=?, subscription_status=?, status=?, phone=?, school_id=? WHERE id=?");
            if (!$stmt_user) {
                error_log("Prepare failed for users update: " . $mysqli->error, 3, "C:/wamp64/www/drivetest/debug_log.txt");
                throw new Exception("Prepare failed for users: " . $mysqli->error);
            }
            $school_id_final = $school_id === '' ? null : $school_id;
            // ΔΙΟΡΘΩΣΗ: Προσθήκη του 'street_number' στις παραμέτρους και ένα επιπλέον 's' στο string τύπων
            $stmt_user->bind_param("sssssssssssssi", $fullname, $email, $address, $street_number, $postal_code, $city, $latitude, $longitude, $final_avatar, $role, $subscription_status, $status, $phone, $school_id_final, $id);
            if (!$stmt_user->execute()) {
                error_log("User update failed: " . $stmt_user->error, 3, "C:/wamp64/www/drivetest/debug_log.txt");
                $mysqli->rollback();
                throw new Exception("User update failed: " . $stmt_user->error);
            }
            $mysqli->commit();
            error_log("User update successful for ID: $id", 3, "C:/wamp64/www/drivetest/debug_log.txt");
            echo json_encode(['success' => true, 'message' => 'Ο χρήστης ενημερώθηκε επιτυχώς.']);
            $stmt_user->close();
            break;

        default:
            error_log("Unknown action: $action", 3, "C:/wamp64/www/drivetest/debug_log.txt");
            echo json_encode(['success' => false, 'message' => 'Άγνωστη ενέργεια.']);
    }
} catch (Exception $e) {
    error_log("API error in users.php: " . $e->getMessage(), 3, "C:/wamp64/www/drivetest/debug_log.txt");
    echo json_encode(['success' => false, 'message' => 'Σφάλμα στο server: ' . $e->getMessage()]);
}

$mysqli->close();