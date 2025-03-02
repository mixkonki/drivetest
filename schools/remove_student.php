<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/school_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);
    $school_id = $_SESSION['user_id'];

    // Αφαιρούμε τον μαθητή από τη σχολή
    $query = "UPDATE users SET school_id = NULL WHERE id = ? AND school_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $student_id, $school_id);
    
    if ($stmt->execute()) {
        header("Location: school_subscriptions.php?success=1");
    } else {
        header("Location: school_subscriptions.php?error=1");
    }
    exit();
}
?>
