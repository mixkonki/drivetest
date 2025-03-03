<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Έλεγχος αν ο χρήστης είναι μαθητής άλλης σχολής
if ($user_role === 'student') {
    $check_query = "SELECT school_id FROM users WHERE id = ?";
    $stmt_check = $mysqli->prepare($check_query);
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $user = $result->fetch_assoc();
    $stmt_check->close();
    
    if (!empty($user['school_id'])) {
        header("Location: " . BASE_URL . "/users/dashboard.php?error=" . urlencode("Είστε ήδη μαθητής σε άλλη σχολή."));
        exit();
    }
}

// Έλεγχος αν υπάρχει POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_id'])) {
    $school_id = intval($_POST['school_id']);
    
    // Έλεγχος αν υπάρχει η σχολή
    $school_query = "SELECT s.id, s.name, s.students_limit FROM schools s WHERE s.id = ?";
    $stmt_school = $mysqli->prepare($school_query);
    $stmt_school->bind_param("i", $school_id);
    $stmt_school->execute();
    $result = $stmt_school->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: " . BASE_URL . "/public/school_search.php?error=" . urlencode("Η σχολή δεν βρέθηκε."));
        exit();
    }
    
    $school = $result->fetch_assoc();
    $stmt_school->close();
    
    // Έλεγχος αν υπάρχει ήδη αίτημα
    $check_request = "SELECT id, status FROM school_join_requests WHERE user_id = ? AND school_id = ?";
    $stmt_check = $mysqli->prepare($check_request);
    $stmt_check->bind_param("ii", $user_id, $school_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        
        if ($request['status'] === 'pending') {
            header("Location: " . BASE_URL . "/public/school_search.php?error=" . urlencode("Έχετε ήδη υποβάλει αίτημα για αυτή τη σχολή. Περιμένετε την απάντηση."));
            exit();
        } elseif ($request['status'] === 'rejected') {
            // Αν το προηγούμενο αίτημα απορρίφθηκε, επιτρέπουμε νέο αίτημα
            $update_query = "UPDATE school_join_requests SET status = 'pending', updated_at = NOW() WHERE id = ?";
            $stmt_update = $mysqli->prepare($update_query);
            $stmt_update->bind_param("i", $request['id']);
            $stmt_update->execute();
            $stmt_update->close();
            
            header("Location: " . BASE_URL . "/users/dashboard.php?success=" . urlencode("Το αίτημα υποβλήθηκε εκ νέου με επιτυχία."));
            exit();
        }
    } else {
        // Έλεγχος του ορίου μαθητών της σχολής
        $count_query = "SELECT COUNT(*) as count FROM users WHERE school_id = ? AND role = 'student'";
        $stmt_count = $mysqli->prepare($count_query);
        $stmt_count->bind_param("i", $school_id);
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $current_count = $count_result->fetch_assoc()['count'];
        $stmt_count->close();
        
        if ($current_count >= $school['students_limit']) {
            header("Location: " . BASE_URL . "/public/school_search.php?error=" . urlencode("Η σχολή έχει φτάσει το όριο μαθητών της."));
            exit();
        }
        
        // Εισαγωγή νέου αιτήματος
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $insert_query = "INSERT INTO school_join_requests (user_id, school_id, notes, status, created_at) VALUES (?, ?, ?, 'pending', NOW())";
        $stmt_insert = $mysqli->prepare($insert_query);
        $stmt_insert->bind_param("iis", $user_id, $school_id, $notes);
        
        if ($stmt_insert->execute()) {
            // Ειδοποίηση της σχολής με email
            $user_query = "SELECT fullname, email FROM users WHERE id = ?";
            $stmt_user = $mysqli->prepare($user_query);
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $user_result = $stmt_user->get_result();
            $user = $user_result->fetch_assoc();
            $stmt_user->close();
            
            $school_admin_query = "SELECT u.email FROM users u JOIN schools s ON u.email = s.email WHERE s.id = ?";
            $stmt_admin = $mysqli->prepare($school_admin_query);
            $stmt_admin->bind_param("i", $school_id);
            $stmt_admin->execute();
            $admin_result = $stmt_admin->get_result();
            
            if ($admin_result->num_rows > 0) {
                $admin = $admin_result->fetch_assoc();
                $admin_email = $admin['email'];
                
                $subject = "Νέο αίτημα συμμετοχής στη σχολή σας";
                $message = "<h2>Νέο αίτημα συμμετοχής</h2>
                            <p>Ο χρήστης <strong>" . htmlspecialchars($user['fullname']) . "</strong> (<a href='mailto:" . $user['email'] . "'>" . $user['email'] . "</a>) 
                            έχει ζητήσει να συμμετάσχει στη σχολή σας.</p>";
                
                if (!empty($notes)) {
                    $message .= "<p><strong>Σημειώσεις:</strong> " . htmlspecialchars($notes) . "</p>";
                }
                
                $message .= "<p>Μπορείτε να διαχειριστείτε τα αιτήματα συμμετοχής από τον <a href='" . BASE_URL . "/schools/school_profile.php#students-tab'>πίνακα ελέγχου της σχολής σας</a>.</p>";
                
                require_once '../includes/mailer.php';
                send_mail($admin_email, $subject, $message);
            }
            $stmt_admin->close();
            
            header("Location: " . BASE_URL . "/users/dashboard.php?success=" . urlencode("Το αίτημα υποβλήθηκε με επιτυχία. Θα ειδοποιηθείτε όταν εγκριθεί."));
            exit();
        } else {
            header("Location: " . BASE_URL . "/public/school_search.php?error=" . urlencode("Σφάλμα κατά την υποβολή του αιτήματος. Δοκιμάστε ξανά."));
            exit();
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
} else {
    // Αν δεν είναι POST request, ανακατεύθυνση στην αναζήτηση σχολών
    header("Location: " . BASE_URL . "/public/school_search.php");
    exit();
}