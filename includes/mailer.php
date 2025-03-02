<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Φόρτωση ρυθμίσεων από config.php (αν χρειάζεται, αλλά θα κρατήσουμε τις υπάρχουσες ρυθμίσεις)
require_once '../config/config.php';

class Emailer {
    private $mail;
    private $logFile = 'C:/wamp64/www/drivetest/debug_log.txt';

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configureSMTP();
    }

    private function configureSMTP() {
        // Διατήρηση των υφιστάμενων ρυθμίσεων SMTP
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.thessdrive.gr';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'info@thessdrive.gr';
        $this->mail->Password = 'inf1q2w!Q@W';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;

        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';
    }

    public function sendEmail($to, $subject, $body, $isHTML = true, $fromEmail = 'info@thessdrive.gr', $fromName = 'DriveTest Support', $cc = [], $bcc = []) {
        try {
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
        // Κλήση της υπάρχουσας μεθόδου sendEmail() με προεπιλεγμένες παραμέτρους
        return $this->sendEmail($to, $subject, $message, true, 'info@thessdrive.gr', 'DriveTest Support');
    }

    public function sendVerificationEmail($email, $token, $language = 'el') {
        // Φόρτωση μεταφράσεων για την τρέχουσα γλώσσα
        $translations = require "../languages/{$language}.php";
        
        $subject = isset($translations['verification_subject']) ? $translations['verification_subject'] : 'Επαλήθευση Email - DriveTest';
        $verification_link = BASE_URL . '/public/verify.php?token=' . $token . '&lang=' . $language;
        $body = "<h2>" . (isset($translations['verification_title']) ? $translations['verification_title'] : 'Καλώς ήρθατε στο DriveTest!') . "</h2>
                 <p>" . (isset($translations['verification_body']) ? $translations['verification_body'] : 'Κάντε κλικ στον παρακάτω σύνδεσμο για να επαληθεύσετε το email σας:') . "</p>
                 <a href='$verification_link'>" . (isset($translations['verify_link']) ? $translations['verify_link'] : 'Επαλήθευση Email') . "</a>
                 <p>" . (isset($translations['verification_ignore']) ? $translations['verification_ignore'] : 'Εάν δεν εγγραφήκατε εσείς, αγνοήστε αυτό το email.') . "</p>";

        return $this->sendEmail($email, $subject, $body);
    }

    public function sendResetPasswordEmail($email, $token, $language = 'el') {
        // Φόρτωση μεταφράσεων για την τρέχουσα γλώσσα
        $translations = require "../languages/{$language}.php";

        $subject = isset($translations['reset_subject']) ? $translations['reset_subject'] : 'Επαναφορά Κωδικού - DriveTest';
        $reset_link = BASE_URL . '/public/reset_password_process.php?token=' . $token . '&lang=' . $language;
        $body = "<h2>" . (isset($translations['reset_title']) ? $translations['reset_title'] : 'Επαναφορά Κωδικού') . "</h2>
                 <p>" . (isset($translations['reset_body']) ? $translations['reset_body'] : 'Κάντε κλικ στον παρακάτω σύνδεσμο για να επαναφέρετε τον κωδικό σας:') . "</p>
                 <a href='$reset_link'>" . (isset($translations['reset_link']) ? $translations['reset_link'] : 'Επαναφορά Κωδικού') . "</a>
                 <p>" . (isset($translations['reset_ignore']) ? $translations['reset_ignore'] : 'Ο σύνδεσμος ισχύει για 1 ώρα. Εάν δεν ζητήσατε επαναφορά, αγνοήστε αυτό το email.') . "</p>";

        return $this->sendEmail($email, $subject, $body);
    }

    public function sendSubscriptionReminder($email, $subscriptionDetails, $language = 'el') {
        // Φόρτωση μεταφράσεων για την τρέχουσα γλώσσα
        $translations = require "../languages/{$language}.php";

        $subject = isset($translations['reminder_subject']) ? $translations['reminder_subject'] : 'Υπενθύμιση Συνδρομής - DriveTest';
        $expiryDate = date('d/m/Y', strtotime($subscriptionDetails['expiry_date']));
        $body = "<h2>" . (isset($translations['reminder_title']) ? $translations['reminder_title'] : 'Υπενθύμιση Συνδρομής') . "</h2>
                 <p>" . (isset($translations['reminder_body']) ? $translations['reminder_body'] : 'Η συνδρομή σας λήγει στις') . " {$expiryDate}. " . (isset($translations['reminder_action']) ? $translations['reminder_action'] : 'Ανανεώστε τη συνδρομή σας για να συνεχίσετε να έχετε πρόσβαση στα τεστ μας.') . "</p>
                 <a href='" . BASE_URL . "/subscriptions/buy.php'>" . (isset($translations['renew_subscription']) ? $translations['renew_subscription'] : 'Ανανέωση Συνδρομής') . "</a>";

        return $this->sendEmail($email, $subject, $body);
    }

    private function log($message) {
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