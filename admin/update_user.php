<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    try {
        $query = "UPDATE users SET fullname=?, email=?, role=? WHERE id=?";
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception("Προετοιμασία query απέτυχε: " . $mysqli->error);
        }
        $stmt->bind_param("sssi", $fullname, $email, $role, $id);
        if ($stmt->execute()) {
            header("Location: users.php?success=updated");
        } else {
            throw new Exception("Ενημέρωση χρήστη απέτυχε: " . $stmt->error);
        }
    } catch (Exception $e) {
        header("Location: users.php?error=" . urlencode($e->getMessage()));
        exit();
    }
    exit();
}
?>