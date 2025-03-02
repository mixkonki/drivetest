<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Έλεγχος αν υπάρχει το email
    $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        echo "<p class='error-message'>Το email υπάρχει ήδη!</p>";
    } else {
        try {
            $stmt = $mysqli->prepare("INSERT INTO users (fullname, email, role, password, verified) VALUES (?, ?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Προετοιμασία query απέτυχε: " . $mysqli->error);
            }
            $stmt->bind_param("ssss", $fullname, $email, $role, $password);
            if ($stmt->execute()) {
                $redirect_url = ($role === 'admin') ? 'dashboard.php' : (($role === 'school') ? '../schools/dashboard.php' : (($role === 'student') ? '../students/dashboard.php' : '../users/dashboard.php'));
                header("Location: $redirect_url?success=added");
                exit();
            } else {
                throw new Exception("Προσθήκη χρήστη απέτυχε: " . $stmt->error);
            }
        } catch (Exception $e) {
            echo "<p class='error-message'>Σφάλμα: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    $check_stmt->close();
}
?>

<main class="admin-container">
    <h1 class="admin-title">Προσθήκη Χρήστη</h1>
    <form action="" method="post" class="admin-form" role="form" aria-label="Φόρμα Προσθήκης Χρήστη">
        <div class="form-group">
            <label for="fullname">Ονοματεπώνυμο:</label>
            <input type="text" id="fullname" name="fullname" required aria-required="true">
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required aria-required="true">
        </div>

        <div class="form-group">
            <label for="role">Ρόλος:</label>
            <select id="role" name="role" aria-required="true">
                <option value="user">Χρήστης</option>
                <option value="student">Μαθητής</option>
                <option value="school">Σχολή</option>
                <option value="admin">Διαχειριστής</option>
            </select>
        </div>

        <div class="form-group">
            <label for="password">Κωδικός:</label>
            <input type="password" id="password" name="password" required aria-required="true">
        </div>

        <button type="submit" class="btn-primary" aria-label="Προσθήκη χρήστη">Προσθήκη</button>
    </form>
</main>

<?php require_once 'includes/admin_footer.php'; ?>