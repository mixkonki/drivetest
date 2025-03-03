<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    $address = trim($_POST['hidden_address'] ?? $_POST['address'] ?? '');
    $street_number = trim($_POST['hidden_street_number'] ?? $_POST['street_number'] ?? '');
    $postal_code = trim($_POST['hidden_postal_code'] ?? $_POST['postal_code'] ?? '');
    $city = trim($_POST['hidden_city'] ?? $_POST['city'] ?? '');
    
    $latitude = !empty($_POST['hidden_latitude']) ? (float)$_POST['hidden_latitude'] : 
                (!empty($_POST['latitude']) ? (float)$_POST['latitude'] : null);
    $longitude = !empty($_POST['hidden_longitude']) ? (float)$_POST['hidden_longitude'] : 
                 (!empty($_POST['longitude']) ? (float)$_POST['longitude'] : null);
    
    $avatar_name = null;
    if (!empty($_FILES['avatar']['name'])) {
        $upload_dir = '../uploads/avatars/';
        $avatar_name = 'avatar_' . $user_id . '.' . strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $avatar_path = $upload_dir . $avatar_name;
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path);
    }
    
    $stmt = $mysqli->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_user = $result->fetch_assoc();
    $stmt->close();
    
    $final_avatar = $avatar_name ?? $current_user['avatar'];
    
    $stmt = $mysqli->prepare("UPDATE users SET 
        fullname = ?, 
        email = ?, 
        phone = ?, 
        address = ?, 
        street_number = ?, 
        postal_code = ?, 
        city = ?, 
        latitude = ?, 
        longitude = ?, 
        avatar = ? 
        WHERE id = ?");
    
    $stmt->bind_param(
        "sssssssddsi", 
        $fullname, 
        $email, 
        $phone, 
        $address, 
        $street_number, 
        $postal_code, 
        $city, 
        $latitude, 
        $longitude, 
        $final_avatar, 
        $user_id
    );
    
    if ($stmt->execute()) {
        header("Location: " . BASE_URL . "/users/user_profile.php?success=1");
        exit();
    } else {
        header("Location: " . BASE_URL . "/users/user_profile.php?error=" . 
            urlencode("Αποτυχία ενημέρωσης προφίλ: " . $stmt->error));
        exit();
    }
}

header("Location: " . BASE_URL . "/users/user_profile.php?error=invalid_request");
exit();
?>