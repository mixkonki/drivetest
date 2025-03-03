<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';

// Έλεγχος αν η συνεδρία έχει ήδη ξεκινήσει
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Αν ο χρήστης δεν είναι συνδεδεμένος, ανακατεύθυνση στη σελίδα σύνδεσης
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Δημιουργία ενός μοναδικού session token αν δεν υπάρχει
if (!isset($_SESSION['session_token'])) {
    $_SESSION['session_token'] = bin2hex(random_bytes(32));

    // Αποθήκευση του session token στη βάση δεδομένων αν υπάρχει η στήλη
    $update_query = "UPDATE users SET session_token = ? WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param("si", $_SESSION['session_token'], $user_id);
    $stmt->execute();
    $stmt->close();
}

// Έλεγχος αν υπάρχει η στήλη `session_token` στη βάση (προστασία από λάθη)
$check_query = "SELECT session_token, role FROM users WHERE id = ?";
$stmt = $mysqli->prepare($check_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($stored_token, $role);
$stmt->fetch();
$stmt->close();

// Αν ο session token στη βάση δεν ταιριάζει με αυτόν του session, αποσύνδεση χρήστη
if (!empty($stored_token) && $stored_token !== $_SESSION['session_token']) {
    session_destroy();
    header("Location: " . BASE_URL . "/public/login.php?error=session");
    exit();
}

// Ενημέρωση της τελευταίας δραστηριότητας του χρήστη
$update_activity = "UPDATE users SET last_activity = NOW() WHERE id = ?";
$stmt = $mysqli->prepare($update_activity);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// Αν η σελίδα απαιτεί συγκεκριμένο ρόλο, έλεγχος δικαιωμάτων
function check_user_role($required_role) {
    global $role;
    if ($role !== $required_role) {
        if ($role === 'user') {
            header("Location: " . BASE_URL . "/users/dashboard.php?error=unauthorized");
        } else if ($role === 'admin') {
            header("Location: " . BASE_URL . "/admin/dashboard.php?error=unauthorized");
        } else if ($role === 'school') {
            header("Location: " . BASE_URL . "/schools/dashboard.php?error=unauthorized");
        } else if ($role === 'student') {
            header("Location: " . BASE_URL . "/students/dashboard.php?error=unauthorized");
        } else {
            header("Location: " . BASE_URL . "/public/login.php?error=unauthorized");
        }
        exit();
    }
}
?>
