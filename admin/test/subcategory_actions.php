<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ["success" => false, "message" => "Άγνωστο σφάλμα."];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // 🔹 Φόρτωση όλων των κατηγοριών (για dropdown)
    if ($action === 'list_categories') {
        $query = "SELECT id, name FROM test_categories ORDER BY name ASC";
        $result = $mysqli->query($query);

        if ($result) {
            $categories = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["success" => true, "categories" => $categories]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα SQL: " . $mysqli->error]);
        }
        exit();
    }

    // 🔹 Φόρτωση όλων των υποκατηγοριών
    if ($action === 'list') {
        $query = "
            SELECT 
                s.id, 
                s.name, 
                s.description, 
                s.icon, 
                c.id AS test_category_id, 
                c.name AS category_name 
            FROM test_subcategories s
            JOIN test_categories c ON s.test_category_id = c.id
            ORDER BY s.name ASC";
        
        $result = $mysqli->query($query);

        if ($result) {
            $subcategories = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["success" => true, "subcategories" => $subcategories]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα κατά την ανάκτηση υποκατηγοριών: " . $mysqli->error]);
        }
        exit();
    }

    // 🔹 Διαγραφή υποκατηγορίας
    if ($action === 'delete') {
        $subcategory_id = intval($_POST['id'] ?? 0);

        if ($subcategory_id === 0) {
            echo json_encode(["success" => false, "message" => "❌ Μη έγκυρο ID"]);
            exit();
        }

        $stmt = $mysqli->prepare("DELETE FROM test_subcategories WHERE id = ?");
        $stmt->bind_param("i", $subcategory_id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "✅ Υποκατηγορία διαγράφηκε!"]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα διαγραφής"]);
        }
        $stmt->close();
        exit();
    }

    // 🔹 Ενημέρωση/Επεξεργασία υποκατηγορίας
    if ($action === 'edit') {
        $subcategory_id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? ''); // Προαιρετικό icon

        if (empty($name) || $category_id === 0 || $subcategory_id === 0) {
            echo json_encode(["success" => false, "message" => "❌ Συμπληρώστε όλα τα πεδία."]);
            exit();
        }

        if (!empty($icon)) {
            $iconPath = BASE_PATH . '/assets/images/' . basename($icon);
            if (!file_exists($iconPath)) {
                echo json_encode(["success" => false, "message" => "❌ Το εικονίδιο δεν βρέθηκε!"]);
                exit();
            }
        }

        $stmt = $mysqli->prepare("UPDATE test_subcategories SET test_category_id = ?, name = ?, description = ?, icon = ? WHERE id = ?");
        $stmt->bind_param("isssi", $category_id, $name, $description, $icon, $subcategory_id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "✅ Η υποκατηγορία ενημερώθηκε!"]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα ενημέρωσης"]);
        }
        $stmt->close();
        exit();
    }

    // 🔹 Αποθήκευση νέας υποκατηγορίας
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? ''); // Προαιρετικό icon

        if (empty($name) || $category_id === 0) {
            echo json_encode(["success" => false, "message" => "❌ Συμπληρώστε όλα τα πεδία."]);
            exit();
        }

        if (!empty($icon)) {
            $iconPath = BASE_PATH . '/assets/images/' . basename($icon);
            if (!file_exists($iconPath)) {
                echo json_encode(["success" => false, "message" => "❌ Το εικονίδιο δεν βρέθηκε!"]);
                exit();
            }
        }

        $stmt = $mysqli->prepare("INSERT INTO test_subcategories (test_category_id, name, description, icon) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $category_id, $name, $description, $icon);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "✅ Υποκατηγορία αποθηκεύτηκε!"]);
        } else {
            echo json_encode(["success" => false, "message" => "❌ Σφάλμα αποθήκευσης"]);
        }
        $stmt->close();
        exit();
    }
}

echo json_encode($response);
exit();