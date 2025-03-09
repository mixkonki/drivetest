<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

// Βοηθητική συνάρτηση για τα logs
function log_message($message, $level = 'INFO') {
    file_put_contents("debug_log.txt", "[" . date("Y-m-d H:i:s") . "] [$level] $message\n", FILE_APPEND);
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ["success" => false, "message" => "Άγνωστο σφάλμα."];

log_message("🛠️ [DEBUG] Action: " . json_encode($_POST), 'DEBUG');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 🔹 Φόρτωση όλων των κεφαλαίων
    if ($action === 'list') {
        $query = "
            SELECT 
                c.id, 
                c.name, 
                c.description, 
                c.icon,
                sc.id AS subcategory_id,
                sc.name AS subcategory_name,
                tc.id AS category_id,
                tc.name AS category_name
            FROM test_chapters c
            JOIN test_subcategories sc ON c.subcategory_id = sc.id
            JOIN test_categories tc ON sc.test_category_id = tc.id
            ORDER BY c.name ASC";
            
        $result = $mysqli->query($query);

        if ($result) {
            $chapters = $result->fetch_all(MYSQLI_ASSOC);
            
            // Προσθήκη των URLs εικονιδίων
            foreach ($chapters as &$chapter) {
                if (!empty($chapter['icon'])) {
                    // Έλεγχος αν είναι πλήρες URL ή σχετική διαδρομή
                    if (filter_var($chapter['icon'], FILTER_VALIDATE_URL)) {
                        $chapter['icon_url'] = $chapter['icon'];
                    } else {
                        // Κατασκευή URL ανάλογα με το πού είναι αποθηκευμένα τα εικονίδια
                        $chapter['icon_url'] = rtrim(BASE_URL, '/') . '/assets/images/chapters/' . $chapter['icon'];
                    }
                } else {
                    $chapter['icon_url'] = null;
                }
            }
            
            echo json_encode(["success" => true, "chapters" => $chapters]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα SQL: " . $mysqli->error]);
        }
        exit();
    }

    // 🔹 Φόρτωση όλων των υποκατηγοριών για το dropdown
    if ($action === 'list_subcategories') {
        $query = "
            SELECT 
                sc.id AS subcategory_id, 
                sc.name AS subcategory_name, 
                tc.id AS category_id, 
                tc.name AS category_name 
            FROM test_subcategories sc
            JOIN test_categories tc ON sc.test_category_id = tc.id
            ORDER BY tc.name, sc.name ASC";

        $result = $mysqli->query($query);

        if ($result) {
            $subcategories = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["success" => true, "subcategories" => $subcategories]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα SQL: " . $mysqli->error]);
        }
        exit();
    }

    // 🔹 Αποθήκευση νέου κεφαλαίου
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $icon = null;

        if (empty($name) || $subcategory_id === 0) {
            echo json_encode(["success" => false, "message" => "❌ Συμπληρώστε όλα τα υποχρεωτικά πεδία."]);
            exit();
        }
        
        // Διαχείριση εικονιδίου αν έχει σταλεί αρχείο
        if (!empty($_FILES['icon_file']) && !empty($_FILES['icon_file']['name'])) {
            $uploadDir = '../../assets/images/chapters/';
            
            // Δημιουργία του φακέλου αν δεν υπάρχει
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Προετοιμασία ονόματος αρχείου
            $fileExt = pathinfo($_FILES['icon_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'chapter_icon_' . time() . '_' . uniqid() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            // Ανέβασμα του αρχείου
            if (move_uploaded_file($_FILES['icon_file']['tmp_name'], $filePath)) {
                $icon = $fileName;
                log_message("✅ Αρχείο εικονιδίου ανέβηκε: " . $fileName, 'INFO');
            } else {
                log_message("❌ Σφάλμα στο ανέβασμα αρχείου: " . $_FILES['icon_file']['error'], 'ERROR');
            }
        } 
        // Εναλλακτικά, χρήση του URL αν έχει δοθεί
        else if (!empty($_POST['icon'])) {
            $icon = trim($_POST['icon']);
            log_message("📝 Χρήση URL εικονιδίου: " . $icon, 'INFO');
        }
        
        log_message("💾 [DEBUG] Αποθήκευση: name=$name, subcategory_id=$subcategory_id, description=$description, icon=$icon", 'DEBUG');

        $stmt = $mysqli->prepare("INSERT INTO test_chapters (subcategory_id, name, description, icon) VALUES (?, ?, ?, ?)");
        
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα SQL: " . $mysqli->error]);
            exit();
        }

        $stmt->bind_param("isss", $subcategory_id, $name, $description, $icon);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "✅ Κεφάλαιο αποθηκεύτηκε!"]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα αποθήκευσης: " . $stmt->error]);
        }

        $stmt->close();
        exit();
    }
    
   // 🔹 Επεξεργασία κεφαλαίου
if ($action === 'edit') {
    $chapter_id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $icon = null;
    $updateIcon = false;

    if (empty($name) || $subcategory_id === 0 || $chapter_id === 0) {
        echo json_encode(["success" => false, "message" => "❌ Συμπληρώστε όλα τα υποχρεωτικά πεδία."]);
        exit();
    }
    
    // Ανάκτηση τρέχοντος εικονιδίου
    $stmt = $mysqli->prepare("SELECT icon FROM test_chapters WHERE id = ?");
    $stmt->bind_param("i", $chapter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_icon = $result->fetch_assoc()['icon'] ?? null;
    $stmt->close();
    
    // Διαχείριση εικονιδίου αν έχει σταλεί αρχείο
    if (!empty($_FILES['icon_file']) && !empty($_FILES['icon_file']['name'])) {
        $uploadDir = '../../assets/images/chapters/';
        
        // Δημιουργία του φακέλου αν δεν υπάρχει
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Προετοιμασία ονόματος αρχείου
        $fileExt = pathinfo($_FILES['icon_file']['name'], PATHINFO_EXTENSION);
        $fileName = 'chapter_icon_' . time() . '_' . uniqid() . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        // Ανέβασμα του αρχείου
        if (move_uploaded_file($_FILES['icon_file']['tmp_name'], $filePath)) {
            $icon = $fileName;
            $updateIcon = true;
            log_message("✅ Αρχείο εικονιδίου ανέβηκε: " . $fileName, 'INFO');
            
            // Διαγραφή του παλιού αρχείου αν υπάρχει και είναι τοπικό
            if ($current_icon && !filter_var($current_icon, FILTER_VALIDATE_URL) && file_exists($uploadDir . $current_icon)) {
                unlink($uploadDir . $current_icon);
                log_message("🗑️ Παλιό εικονίδιο διαγράφηκε: " . $current_icon, 'INFO');
            }
        } else {
            log_message("❌ Σφάλμα στο ανέβασμα αρχείου: " . $_FILES['icon_file']['error'], 'ERROR');
        }
    } 
    // Εναλλακτικά, χρήση του URL αν έχει δοθεί
    else if (isset($_POST['icon'])) {
        $icon = $_POST['icon'] !== '' ? trim($_POST['icon']) : null;
        $updateIcon = true;
        log_message("📝 Χρήση URL εικονιδίου: " . $icon, 'INFO');
        
        // Διαγραφή του παλιού αρχείου αν υπάρχει και είναι τοπικό
        if ($icon !== $current_icon && $current_icon && !filter_var($current_icon, FILTER_VALIDATE_URL) && file_exists('../../assets/images/chapters/' . $current_icon)) {
            unlink('../../assets/images/chapters/' . $current_icon);
            log_message("🗑️ Παλιό εικονίδιο διαγράφηκε: " . $current_icon, 'INFO');
        }
    }
    
    log_message("💾 [DEBUG] Ενημέρωση: id=$chapter_id, name=$name, subcategory_id=$subcategory_id, description=$description, icon=$icon", 'DEBUG');

    if ($updateIcon) {
        $stmt = $mysqli->prepare("UPDATE test_chapters SET subcategory_id = ?, name = ?, description = ?, icon = ? WHERE id = ?");
        $stmt->bind_param("isssi", $subcategory_id, $name, $description, $icon, $chapter_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE test_chapters SET subcategory_id = ?, name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("issi", $subcategory_id, $name, $description, $chapter_id);
    }

    if ($stmt->execute()) {
        // Έλεγχος αν το αίτημα ήταν από φόρμα ή από AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // Αίτημα AJAX - επιστροφή JSON
            echo json_encode(["success" => true, "message" => "✅ Το κεφάλαιο ενημερώθηκε!"]);
        } else {
            // Κανονικό αίτημα φόρμας - ανακατεύθυνση
            header("Location: manage_chapters.php?success=Το κεφάλαιο ενημερώθηκε επιτυχώς");
            exit();
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα ενημέρωσης: " . $stmt->error]);
        } else {
            header("Location: edit_chapter.php?id=$chapter_id&error=Σφάλμα ενημέρωσης: " . urlencode($stmt->error));
            exit();
        }
    }
    
    $stmt->close();
    exit();
}}