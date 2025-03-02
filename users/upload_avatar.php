<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $fileName = basename($file['name']);
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Μη αποδεκτός τύπος αρχείου. Επιτρέπονται μόνο JPG, JPEG, PNG, GIF.']);
        exit();
    }

    $uploadDir = '../uploads/avatars/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = 'avatar_' . $user_id . '.' . $fileType;
    $targetFile = $uploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $query = "UPDATE users SET avatar = ? WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("si", $newFileName, $user_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            error_log("Failed to update avatar in database: " . $mysqli->error);
            echo json_encode(['success' => false, 'error' => 'Σφάλμα κατά την αποθήκευση του avatar στη βάση δεδομένων.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Σφάλμα κατά την αποθήκευση του αρχείου.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Δεν βρέθηκε αρχείο avatar.']);
}
exit();