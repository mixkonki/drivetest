<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Φόρτωση ρυθμίσεων από config.php
require_once '../config/config.php';

class Emailer {
    private $mail;
    private $logFile;

    public function __construct() {
        global $config;
        
        // Ορισμός του log file από το config αν υπάρχει, αλλιώς χρήση προεπιλεγμένου
        $this->logFile = isset($config['log_path']) ? $config['log_path'] : BASE_PATH . '/logs/email_log.txt';
        
        // Δημιουργία φακέλου logs αν δεν υπάρχει
        $log_dir = dirname($this->logFile);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $this->mail = new PHPMailer(true);
        $this->configureSMTP();
    }

    private function configureSMTP() {
        global $config;
        
        // Χρήση των SMTP ρυθμίσεων από το config.php
        $this->mail->isSMTP();
        $this->mail->Host = $config['smtp_host'] ?? 'smtp.thessdrive.gr';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $config['smtp_username'] ?? 'info@thessdrive.gr';
        $this->mail->Password = $config['smtp_password'] ?? 'inf1q2w!Q@W';
        $this->mail->SMTPSecure = $config['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = $config['smtp_port'] ?? 587;
        
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';
    }

    public function sendEmail($to, $subject, $body, $isHTML = true, $fromEmail = null, $fromName = null, $cc = [], $bcc = []) {
        global $config;
        
        // Χρήση των ρυθμίσεων από το config ή των προεπιλεγμένων τιμών
        $fromEmail = $fromEmail ?? ($config['email_from'] ?? 'info@thessdrive.gr');
        $fromName = $fromName ?? ($config['email_from_name'] ?? 'DriveTest Support');
        
        try {
            // Επαναφορά των ρυθμίσεων για νέο email
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            $this->mail->clearAttachments();
            
            $this->mail->setFrom($fromEmail, $fromName);
            $this->mail->addAddress($to);
            
            // Προσθήκη CC και BCC αν υπάρχουν
            foreach ($cc as $ccAddress) {
                $this->mail->addCC($ccAddress);
            }
            
            foreach ($bcc as $bccAddress) {
                $this->mail->addBCC($bccAddress);
            }
            
            $this->mail->isHTML($isHTML);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body); // Κείμενο χωρίς HTML για μη HTML clients
            
            $this->mail->send();
            $this->log("✅ Email sent successfully to $to with subject: $subject");
            return true;
        } catch (Exception $e) {
            $this->log("❌ Σφάλμα αποστολής email: " . $this->mail->ErrorInfo . " - To: $to, Subject: $subject");
            return false;
        }
    }

    public function send_mail($to, $subject, $message) {
        global $config;
        // Κλήση της υπάρχουσας μεθόδου sendEmail() με προεπιλεγμένες παραμέτρους
        return $this->sendEmail(
            $to, 
            $subject, 
            $message, 
            true, 
            $config['email_from'] ?? 'info@thessdrive.gr', 
            $config['email_from_name'] ?? 'DriveTest Support'
        );
    }

    public function sendVerificationEmail($email, $token, $language = 'el') {
        // Φόρτωση μεταφράσεων για την τρέχουσα γλώσσα
        $translations = $this->loadTranslations($language);
        
        $subject = $translations['verification_subject'] ?? 'Επαλήθευση Email - DriveTest';
        $verification_link = BASE_URL . '/public/verify.php?token=' . $token . '&lang=' . $language;
        
        $body = "<h2>" . ($translations['verification_title'] ?? 'Καλώς ήρθατε στο DriveTest!') . "</h2>
                 <p>" . ($translations['verification_body'] ?? 'Κάντε κλικ στον παρακάτω σύνδεσμο για να επαληθεύσετε το email σας:') . "</p>
                 <a href='$verification_link'>" . ($translations['verify_link'] ?? 'Επαλήθευση Email') . "</a>
                 <p>" . ($translations['verification_ignore'] ?? 'Εάν δεν εγγραφήκατε εσείς, αγνοήστε αυτό το email.') . "</p>";

        return $this->sendEmail($email, $subject, $body);
    }

    public function sendResetPasswordEmail($email, $token, $language = 'el') {
        // Φόρτωση μεταφράσεων για την τρέχουσα γλώσσα
        $translations = $this->loadTranslations($language);

        $subject = $translations['reset_subject'] ?? 'Επαναφορά Κωδικού - DriveTest';
        $reset_link = BASE_URL . '/public/reset_password_process.php?token=' . $token . '&lang=' . $language;
        
        $body = "<h2>" . ($translations['reset_title'] ?? 'Επαναφορά Κωδικού') . "</h2>
                 <p>" . ($translations['reset_body'] ?? 'Κάντε κλικ στον παρακάτω σύνδεσμο για να επαναφέρετε τον κωδικό σας:') . "</p>
                 <a href='$reset_link'>" . ($translations['reset_link'] ?? 'Επαναφορά Κωδικού') . "</a>
                 <p>" . ($translations['reset_ignore'] ?? 'Ο σύνδεσμος ισχύει για 1 ώρα. Εάν δεν ζητήσατε επαναφορά, αγνοήστε αυτό το email.') . "</p>";

        return $this->sendEmail($email, $subject, $body);
    }

    public function sendSubscriptionReminder($email, $subscriptionDetails, $language = 'el') {
        // Φόρτωση μεταφράσεων για την τρέχουσα γλώσσα
        $translations = $this->loadTranslations($language);

        $subject = $translations['reminder_subject'] ?? 'Υπενθύμιση Συνδρομής - DriveTest';
        $expiryDate = date('d/m/Y', strtotime($subscriptionDetails['expiry_date']));
        
        $body = "<h2>" . ($translations['reminder_title'] ?? 'Υπενθύμιση Συνδρομής') . "</h2>
                 <p>" . ($translations['reminder_body'] ?? 'Η συνδρομή σας λήγει στις') . " {$expiryDate}. " . 
                 ($translations['reminder_action'] ?? 'Ανανεώστε τη συνδρομή σας για να συνεχίσετε να έχετε πρόσβαση στα τεστ μας.') . "</p>
                 <a href='" . BASE_URL . "/subscriptions/buy.php'>" . 
                 ($translations['renew_subscription'] ?? 'Ανανέωση Συνδρομής') . "</a>";

        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Φορτώνει τις μεταφράσεις για την συγκεκριμένη γλώσσα
     * 
     * @param string $language Κωδικός γλώσσας
     * @return array Πίνακας με μεταφράσεις
     */
    private function loadTranslations($language) {
        $language_file = "../languages/{$language}.php";
        
        if (file_exists($language_file)) {
            return require $language_file;
        }
        
        // Αν δεν βρεθεί το αρχείο γλώσσας, επιστρέφουμε κενό πίνακα
        $this->log("⚠️ Warning: Language file not found for '{$language}'");
        return [];
    }

    private function log($message) {
        // Δημιουργία φακέλου logs αν δεν υπάρχει
        $log_dir = dirname($this->logFile);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $this->logFile);
    }
}

// Προσθήκη της συνάρτησης `send_mail()` ως global function για συμβατότητα με τον υπάρχοντα κώδικα
if (!function_exists('send_mail')) {
    function send_mail($to, $subject, $message) {
        $emailer = new Emailer();
        return $emailer->send_mail($to, $subject, $message);
    }
}

if (!function_exists('send_verification_email')) {
    function send_verification_email($email, $token, $language = 'el') {
        $emailer = new Emailer();
        return $emailer->sendVerificationEmail($email, $token, $language);
    }
}

if (!function_exists('send_reset_password_email')) {
    function send_reset_password_email($email, $token, $language = 'el') {
        $emailer = new Emailer();
        return $emailer->sendResetPasswordEmail($email, $token, $language);
    }
}

if (!function_exists('send_subscription_reminder')) {
    function send_subscription_reminder($email, $subscriptionDetails, $language = 'el') {
        $emailer = new Emailer();
        return $emailer->sendSubscriptionReminder($email, $subscriptionDetails, $language);
    }
}