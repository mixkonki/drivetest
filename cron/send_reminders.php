<?php
// Φόρτωση του config για να πάρουμε τις διαδρομές
require_once dirname(__DIR__) . '/config/config.php';
$config = require_once BASE_PATH . '/config/config.php';

// Φόρτωση συνδέσεων και mailer με βάση τις διαδρομές από το config
require_once $config['includes_path'] . '/db_connection.php';
require_once $config['includes_path'] . '/mailer.php';

// Φόρτωση γλώσσας (επιλογή προεπιλεγμένης γλώσσας, π.χ. Ελληνικά)
$language = 'el'; // Μπορεί να προσαρμοστεί με βάση τον χρήστη αργότερα
$languagesPath = $config['app_root'] . '/languages/';
$translations = require $languagesPath . "{$language}.php";

// Λογιστικό αρχείο (απόλυτη διαδρομή)
$logFile = $config['app_root'] . '/debug_log.txt';

// Ημερομηνίες υπενθυμίσεων (σε ημέρες πριν τη λήξη)
$reminderIntervals = [7, 3, 1]; // Υπενθυμίσεις 7, 3, και 1 ημέρα πριν τη λήξη

try {
    // Αρχικοποίηση του Emailer
    $emailer = new Emailer();

    $successCount = 0;
    $failCount = 0;

    // Επανάληψη για κάθε διάστημα υπενθύμισης
    foreach ($reminderIntervals as $interval) {
        // Επιλογή συνδρομών που λήγουν στο συγκεκριμένο διάστημα
        $query = "SELECT u.email, u.fullname, s.expiry_date, s.subscription_type 
                  FROM subscriptions s 
                  JOIN users u ON s.user_id = u.id 
                  WHERE s.expiry_date = DATE_ADD(CURDATE(), INTERVAL ? DAY) 
                  AND s.status = 'active'";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception("Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error);
        }

        $stmt->bind_param("i", $interval);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $to = $row['email'];
            $fullname = $row['fullname'];
            $expiryDate = date('d/m/Y', strtotime($row['expiry_date']));
            $subscriptionType = $row['subscription_type'];

            // Δημιουργία προσωποποιημένου μηνύματος
            $subject = isset($translations['subscription_reminder_subject']) ? $translations['subscription_reminder_subject'] : "Υπενθύμιση Λήξης Συνδρομής - DriveTest";
            $body = "<h2>" . (isset($translations['subscription_reminder_title']) ? $translations['subscription_reminder_title'] : 'Υπενθύμιση Συνδρομής') . "</h2>
                     <p>" . (isset($translations['subscription_reminder_body']) ? $translations['subscription_reminder_body'] : 'Αγαπητέ/ή') . " {$fullname},</p>
                     <p>" . (isset($translations['subscription_reminder_text']) ? $translations['subscription_reminder_text'] : 'Η συνδρομή σας τύπου') . " {$subscriptionType} " . (isset($translations['subscription_reminder_expiry']) ? $translations['subscription_reminder_expiry'] : 'λήγει στις') . " {$expiryDate}. " . (isset($translations['subscription_reminder_action']) ? $translations['subscription_reminder_action'] : 'Παρακαλούμε ανανεώστε την για να συνεχίσετε να έχετε πρόσβαση στα τεστ μας.') . "</p>
                     <a href='" . BASE_URL . "/subscriptions/buy.php' style='display: inline-block; padding: 10px 20px; background-color: #d9534f; color: white; text-decoration: none; border-radius: 5px;'>" . (isset($translations['renew_subscription']) ? $translations['renew_subscription'] : 'Ανανέωση Συνδρομής') . "</a>";

            // Αποστολή email
            if ($emailer->sendEmail($to, $subject, $body)) {
                $successCount++;
                error_log(date('[Y-m-d H:i:s] ') . "✅ Υπενθύμιση στάλθηκε επιτυχώς σε {$to} για συνδρομή που λήγει σε {$interval} ημέρες." . PHP_EOL, 3, $logFile);
            } else {
                $failCount++;
                error_log(date('[Y-m-d H:i:s] ') . "❌ Αποτυχία αποστολής υπενθύμισης σε {$to} για συνδρομή που λήγει σε {$interval} ημέρες." . PHP_EOL, 3, $logFile);
            }
        }
        $stmt->close();
    }

    // Λογιστικό αποτέλεσμα
    error_log(date('[Y-m-d H:i:s] ') . "📊 Ολοκληρώθηκε η αποστολή υπενθυμίσεων: {$successCount} επιτυχημένες, {$failCount} αποτυχημένες." . PHP_EOL, 3, $logFile);
    echo "📊 Ολοκληρώθηκε η αποστολή υπενθυμίσεων: {$successCount} επιτυχημένες, {$failCount} αποτυχημένες.";

} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "❌ Σφάλμα κατά την αποστολή υπενθυμίσεων: " . $e->getMessage() . PHP_EOL, 3, $logFile);
    echo "❌ Σφάλμα: " . $e->getMessage();
}

$mysqli->close();