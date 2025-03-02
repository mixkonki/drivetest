<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $categories = json_encode($_POST['categories']);
    $duration = intval($_POST['duration']);
    $start_date = date('Y-m-d');
    $expiry_date = date('Y-m-d', strtotime("+{$duration} months"));

    // Έλεγχος αν υπάρχει ήδη ενεργή συνδρομή
    $query = "SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active'";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        // Αν υπάρχει ενεργή συνδρομή, ανανεώνουμε
        $update_query = "UPDATE subscriptions SET categories = ?, expiry_date = ?, status = 'active' WHERE user_id = ?";
        $stmt = $mysqli->prepare($update_query);
        $stmt->bind_param("ssi", $categories, $expiry_date, $user_id);
    } else {
        // Αν δεν υπάρχει, δημιουργούμε νέα συνδρομή
        $insert_query = "INSERT INTO subscriptions (user_id, categories, start_date, expiry_date, status) VALUES (?, ?, ?, ?, 'active')";
        $stmt = $mysqli->prepare($insert_query);
        $stmt->bind_param("isss", $user_id, $categories, $start_date, $expiry_date);
    }

    if ($stmt->execute()) {
        header("Location: subscriptions_management.php?success=1");
    } else {
        header("Location: subscriptions_management.php?error=1");
    }
    exit();
}
?>
