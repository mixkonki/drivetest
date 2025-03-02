<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

file_put_contents("debug_log.txt", "🚀 [DEBUG] Ξεκινά η PHP.\n", FILE_APPEND);


header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ["success" => false, "message" => "Άγνωστο σφάλμα."];

file_put_contents("debug_log.txt", "🛠️ [DEBUG] Action: " . json_encode($_POST) . "\n", FILE_APPEND);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 🔹 Φόρτωση όλων των κεφαλαίων
    if ($action === 'list') {
        $query = "
            SELECT 
                c.id, 
                c.name, 
                c.description, 
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

        if (empty($name) || $subcategory_id === 0) {
            echo json_encode(["success" => false, "message" => "❌ Συμπληρώστε όλα τα πεδία."]);
            exit();
        }
        file_put_contents("debug_log.txt", "💾 [DEBUG] Αποθήκευση: name=$name, subcategory_id=$subcategory_id, description=$description\n", FILE_APPEND);

        $stmt = $mysqli->prepare("INSERT INTO test_chapters (subcategory_id, name, description) VALUES (?, ?, ?)");
        
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα SQL: " . $mysqli->error]);
            exit();
        }

        $stmt->bind_param("iss", $subcategory_id, $name, $description);
        
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

    if (empty($name) || $subcategory_id === 0 || $chapter_id === 0) {
        echo json_encode(["success" => false, "message" => "❌ Συμπληρώστε όλα τα πεδία."]);
        exit();
    }

    $stmt = $mysqli->prepare("UPDATE test_chapters SET subcategory_id = ?, name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("issi", $subcategory_id, $name, $description, $chapter_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "✅ Το κεφάλαιο ενημερώθηκε!"]);
    } else {
        echo json_encode(["success" => false, "message" => "❌ Σφάλμα ενημέρωσης: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// 🔹 Διαγραφή κεφαλαίου
if ($action === 'delete') {
    $chapter_id = intval($_POST['id'] ?? 0);

    if ($chapter_id === 0) {
        echo json_encode(["success" => false, "message" => "❌ Μη έγκυρο ID"]);
        exit();
    }

    $stmt = $mysqli->prepare("DELETE FROM test_chapters WHERE id = ?");
    $stmt->bind_param("i", $chapter_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "✅ Το κεφάλαιο διαγράφηκε!"]);
    } else {
        echo json_encode(["success" => false, "message" => "❌ Σφάλμα διαγραφής: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}

}

echo json_encode($response);
exit();
