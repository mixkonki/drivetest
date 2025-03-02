<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];

    $cancel_query = "UPDATE subscriptions SET status = 'canceled' WHERE user_id = ? AND status = 'active'";
    $stmt = $mysqli->prepare($cancel_query);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        header("Location: subscriptions_management.php?success=1");
    } else {
        header("Location: subscriptions_management.php?error=1");
    }
    exit();
}
?>
