<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/mailer.php';

// Έλεγχος αν ο χρήστης είναι σχολή
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'school') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$school_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Λήψη στοιχείων σχολής για την επιβεβαίωση του ορίου μαθητών
$school_query = "SELECT s.id, s.students_limit FROM schools s JOIN users u ON s.email = u.email WHERE u.id = ?";
$stmt_school = $mysqli->prepare($school_query);
$stmt_school->bind_param("i", $school_id);
$stmt_school->execute();
$school_result = $stmt_school->get_result();
$school = $school_result->fetch_assoc();
$stmt_school->close();

if (!$school) {
    header("Location: " . BASE_URL . "/schools/school_profile.php?error=school_not_found");
    exit();
}

// Έλεγχος αν η μέθοδος είναι POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_email = trim($_POST['student_email']);
    
    // Έλεγχος αν το email είναι έγκυρο
    if (!filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Παρακαλώ εισάγετε ένα έγκυρο email.";
    } else {
        // Έλεγχος αν ο μαθητής υπάρχει ήδη
        $check_query = "SELECT id, role, school_id FROM users WHERE email = ?";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("s", $student_email);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows === 0) {
            // Ο μαθητής δεν υπάρχει, στέλνουμε πρόσκληση για εγγραφή
            // Δημιουργία μοναδικού token για την πρόσκληση
            $invitation_token = bin2hex(random_bytes(32));
            
            // Αποθήκευση πρόσκλησης στη βάση
            $insert_invite = "INSERT INTO student_invitations (school_id, email, token, created_at) VALUES (?, ?, ?, NOW())";
            $stmt_invite = $mysqli->prepare($insert_invite);
            $stmt_invite->bind_param("iss", $school['id'], $student_email, $invitation_token);
            
            if ($stmt_invite->execute()) {
                // Αποστολή email πρόσκλησης
                $school_name_query = "SELECT fullname FROM users WHERE id = ?";
                $stmt_name = $mysqli->prepare($school_name_query);
                $stmt_name->bind_param("i", $school_id);
                $stmt_name->execute();
                $result_name = $stmt_name->get_result();
                $school_name = $result_name->fetch_assoc()['fullname'];
                $stmt_name->close();
                
                $subject = "Πρόσκληση εγγραφής στο DriveTest από την σχολή " . $school_name;
                $invitation_link = BASE_URL . "/public/register_student.php?token=" . $invitation_token;
                
                $message = "<h2>Πρόσκληση Εγγραφής Μαθητή</h2>
                            <p>Έχετε λάβει πρόσκληση από την σχολή <strong>" . htmlspecialchars($school_name) . "</strong> για να εγγραφείτε στην πλατφόρμα DriveTest ως μαθητής.</p>
                            <p>Για να ολοκληρώσετε την εγγραφή σας, πατήστε στον παρακάτω σύνδεσμο:</p>
                            <p><a href='" . $invitation_link . "' style='padding: 10px 15px; background-color: #aa3636; color: white; text-decoration: none; border-radius: 5px;'>Εγγραφή στο DriveTest</a></p>
                            <p>Ο σύνδεσμος θα είναι ενεργός για 48 ώρες.</p>
                            <p>Αν δεν ζητήσατε εσείς αυτή την πρόσκληση, παρακαλούμε αγνοήστε αυτό το email.</p>";
                
                if (send_mail($student_email, $subject, $message)) {
                    $success = "Η πρόσκληση στάλθηκε επιτυχώς στο email " . htmlspecialchars($student_email);
                } else {
                    $error = "Σφάλμα κατά την αποστολή της πρόσκλησης. Παρακαλώ δοκιμάστε ξανά.";
                }
            } else {
                $error = "Σφάλμα κατά την αποθήκευση της πρόσκλησης.";
            }
            $stmt_invite->close();
        } else {
            $user = $result->fetch_assoc();
            
            // Έλεγχος αν ο χρήστης είναι ήδη μαθητής άλλης σχολής
            if ($user['role'] === 'student' && $user['school_id'] !== null && $user['school_id'] != $school['id']) {
                $error = "Ο χρήστης είναι ήδη μαθητής σε άλλη σχολή.";
            } 
            // Έλεγχος αν ο χρήστης είναι ήδη μαθητής της σχολής
            elseif ($user['role'] === 'student' && $user['school_id'] == $school['id']) {
                $error = "Ο χρήστης είναι ήδη μαθητής στη σχολή σας.";
            }
            // Έλεγχος ρόλου χρήστη (δεν μπορεί να είναι admin ή σχολή)
            elseif ($user['role'] === 'admin' || $user['role'] === 'school') {
                $error = "Ο λογαριασμός με αυτό το email δεν μπορεί να γίνει μαθητής.";
            } 
            else {
                // Έλεγχος αν έχει συμπληρωθεί το όριο μαθητών της σχολής
                $count_query = "SELECT COUNT(*) as count FROM users WHERE school_id = ? AND role = 'student'";
                $stmt_count = $mysqli->prepare($count_query);
                $stmt_count->bind_param("i", $school['id']);
                $stmt_count->execute();
                $count_result = $stmt_count->get_result();
                $current_count = $count_result->fetch_assoc()['count'];
                $stmt_count->close();
                
                if ($current_count >= $school['students_limit']) {
                    $error = "Έχετε φτάσει το όριο μαθητών της σχολής σας. Αναβαθμίστε τη συνδρομή σας για περισσότερους μαθητές.";
                } else {
                    // Ενημέρωση του χρήστη σε μαθητή της σχολής
                    $update_query = "UPDATE users SET role = 'student', school_id = ? WHERE id = ?";
                    $stmt_update = $mysqli->prepare($update_query);
                    $stmt_update->bind_param("ii", $school['id'], $user['id']);
                    
                    if ($stmt_update->execute()) {
                        // Ενημέρωση του χρήστη με email
                        $school_name_query = "SELECT fullname FROM users WHERE id = ?";
                        $stmt_name = $mysqli->prepare($school_name_query);
                        $stmt_name->bind_param("i", $school_id);
                        $stmt_name->execute();
                        $result_name = $stmt_name->get_result();
                        $school_name = $result_name->fetch_assoc()['fullname'];
                        $stmt_name->close();
                        
                        $subject = "Προσθήκη σε σχολή στο DriveTest";
                        $message = "<h2>Προσθήκη σε Σχολή</h2>
                                    <p>Ο λογαριασμός σας στο DriveTest έχει συνδεθεί με την σχολή <strong>" . htmlspecialchars($school_name) . "</strong>.</p>
                                    <p>Πλέον έχετε πρόσβαση σε όλο το εκπαιδευτικό υλικό και τα τεστ που παρέχει η σχολή.</p>
                                    <p>Μπορείτε να συνδεθείτε στο λογαριασμό σας από τον παρακάτω σύνδεσμο:</p>
                                    <p><a href='" . BASE_URL . "/public/login.php' style='padding: 10px 15px; background-color: #aa3636; color: white; text-decoration: none; border-radius: 5px;'>Σύνδεση στο DriveTest</a></p>";
                        
                        send_mail($student_email, $subject, $message); // Δεν ελέγχουμε αν στάλθηκε καθώς ο χρήστης έχει ήδη προστεθεί
                        
                        $success = "Ο χρήστης προστέθηκε επιτυχώς ως μαθητής της σχολής σας.";
                    } else {
                        $error = "Σφάλμα κατά την ενημέρωση του χρήστη.";
                    }
                    $stmt_update->close();
                }
            }
        }
        $stmt_check->close();
    }
}

// Ανακατεύθυνση στη σελίδα προφίλ με το κατάλληλο μήνυμα
if (!empty($success)) {
    header("Location: " . BASE_URL . "/schools/school_profile.php?success=" . urlencode($success) . "#students-tab");
    exit();
} elseif (!empty($error)) {
    header("Location: " . BASE_URL . "/schools/school_profile.php?error=" . urlencode($error) . "#students-tab");
    exit();
} else {
    // Αν δεν έγινε POST request, ανακατεύθυνση στη σελίδα προφίλ
    header("Location: " . BASE_URL . "/schools/school_profile.php#students-tab");
    exit();
}