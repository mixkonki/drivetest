<?php
// Διαδρομή: /includes/functions.php

/**
 * Καθαρισμός εισόδου
 * 
 * @param string $data Τα δεδομένα προς καθαρισμό
 * @return string Τα καθαρισμένα δεδομένα
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Έλεγχος email
 * 
 * @param string $email Το email προς έλεγχο
 * @return boolean Αν το email είναι έγκυρο
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Δημιουργία μοναδικού token
 * 
 * @param int $length Το μήκος του token
 * @return string Το token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Μορφοποίηση ημερομηνίας
 * 
 * @param string $date Η ημερομηνία
 * @param string $format Η μορφή
 * @return string Η μορφοποιημένη ημερομηνία
 */
function format_date($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

/**
 * Δημιουργία φιλικού URL
 * 
 * @param string $string Το string προς μετατροπή
 * @return string Το φιλικό URL
 */
function create_slug($string) {
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-z0-9- ]/i', '', $string);
    $string = str_replace(' ', '-', $string);
    $string = strtolower($string);
    return $string;
}

/**
 * Μετατροπή σε bytes
 * 
 * @param string $val Το string προς μετατροπή (πχ. 2M)
 * @return int Τα bytes
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

/**
 * Έλεγχος αν είναι ajax αίτημα
 * 
 * @return boolean
 */
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
/**
 * Έλεγχος αν είναι ajax αίτημα
 * 
 * @return boolean
 */
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Ανακατεύθυνση σε μια διεύθυνση URL
 * 
 * @param string $url Η διεύθυνση URL
 * @param array $params Παράμετροι για το URL
 */
function redirect($url, $params = []) {
    $url = BASE_URL . $url;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    header("Location: $url");
    exit;
}

/**
 * Επιστροφή μηνύματος επιτυχίας
 * 
 * @param string $url Η διεύθυνση URL
 * @param string $message Το μήνυμα
 */
function redirect_with_success($url, $message) {
    redirect($url, ['success' => $message]);
}

/**
 * Επιστροφή μηνύματος σφάλματος
 * 
 * @param string $url Η διεύθυνση URL
 * @param string $message Το μήνυμα
 */
function redirect_with_error($url, $message) {
    redirect($url, ['error' => $message]);
}

/**
 * Φόρτωση αρχείου
 * 
 * @param array $file Το αρχείο ($_FILES['file'])
 * @param string $destination Ο φάκελος προορισμού
 * @param array $allowed_types Οι επιτρεπόμενοι τύποι αρχείων
 * @param int $max_size Το μέγιστο μέγεθος σε bytes
 * @return string|false Το όνομα του αρχείου που αποθηκεύτηκε ή false σε περίπτωση σφάλματος
 */
function upload_file($file, $destination, $allowed_types = [], $max_size = 0) {
    // Έλεγχος αν υπάρχει το αρχείο
    if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Έλεγχος τύπου αρχείου
    $file_info = pathinfo($file['name']);
    $file_ext = strtolower($file_info['extension']);
    
    if (!empty($allowed_types) && !in_array($file_ext, $allowed_types)) {
        return false;
    }
    
    // Έλεγχος μεγέθους αρχείου
    if ($max_size > 0 && $file['size'] > $max_size) {
        return false;
    }
    
    // Δημιουργία μοναδικού ονόματος αρχείου
    $new_filename = uniqid() . '_' . $file_info['filename'] . '.' . $file_ext;
    $upload_path = $destination . '/' . $new_filename;
    
    // Μετακίνηση του αρχείου
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $new_filename;
    }
    
    return false;
}

/**
 * Διαγραφή αρχείου
 * 
 * @param string $filepath Η διαδρομή του αρχείου
 * @return boolean Επιτυχία ή αποτυχία
 */
function delete_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return false;
}

/**
 * Εμφάνιση πλοήγησης σελίδων
 * 
 * @param int $current_page Η τρέχουσα σελίδα
 * @param int $total_pages Ο συνολικός αριθμός σελίδων
 * @param string $url_pattern Το μοτίβο URL
 * @return string Το HTML της πλοήγησης
 */
function pagination($current_page, $total_pages, $url_pattern) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $pagination = '<ul class="pagination">';
    
    // Προηγούμενη σελίδα
    if ($current_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page - 1) . '">Προηγούμενη</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Προηγούμενη</span></li>';
    }
    
    // Σελίδες
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, 1) . '">1</a></li>';
        
        if ($start_page > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $pagination .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $total_pages) . '">' . $total_pages . '</a></li>';
    }
    
    // Επόμενη σελίδα
    if ($current_page < $total_pages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page + 1) . '">Επόμενη</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Επόμενη</span></li>';
    }
    
    $pagination .= '</ul>';
    
    return $pagination;
}

/**
 * Διαφυγή HTML
 * 
 * @param string $string Το string προς διαφυγή
 * @return string Το string μετά τη διαφυγή
 */
function html_escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Περικοπή κειμένου
 * 
 * @param string $text Το κείμενο
 * @param int $length Το μέγιστο μήκος
 * @param string $append Το κείμενο που προστίθεται στο τέλος
 * @return string Το περικομμένο κείμενο
 */
function truncate_text($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    
    return $text . $append;
}

/**
 * Φορτώνει ένα αρχείο απόψεων (view)
 * 
 * @param string $view_name Το όνομα του αρχείου απόψεων
 * @param array $data Τα δεδομένα για το αρχείο απόψεων
 */
function load_view($view_name, $data = []) {
    // Εξαγωγή των δεδομένων σε μεταβλητές
    extract($data);
    
    // Φόρτωση του αρχείου απόψεων
    require_once BASE_PATH . '/views/' . $view_name . '.php';
}

/**
 * Φορτώνει ένα αρχείο απόψεων και επιστρέφει το περιεχόμενό του
 * 
 * @param string $view_name Το όνομα του αρχείου απόψεων
 * @param array $data Τα δεδομένα για το αρχείο απόψεων
 * @return string Το περιεχόμενο του αρχείου απόψεων
 */
function get_view($view_name, $data = []) {
    // Έναρξη της προσωρινής αποθήκευσης εξόδου
    ob_start();
    
    // Φόρτωση του αρχείου απόψεων
    load_view($view_name, $data);
    
    // Λήψη του περιεχομένου και τερματισμός της προσωρινής αποθήκευσης
    return ob_get_clean();
}

/**
 * Αποστολή email
 * 
 * @param string $to Ο παραλήπτης
 * @param string $subject Το θέμα
 * @param string $message Το μήνυμα
 * @param array $attachments Τα συνημμένα αρχεία
 * @return boolean Επιτυχία ή αποτυχία
 */
function send_email($to, $subject, $message, $attachments = []) {
    global $config;
    
    // Έλεγχος αν έχει φορτωθεί η βιβλιοθήκη PHPMailer
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once BASE_PATH . '/vendor/phpmailer/phpmailer/src/Exception.php';
        require_once BASE_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once BASE_PATH . '/vendor/phpmailer/phpmailer/src/SMTP.php';
    }
    
    // Δημιουργία αντικειμένου PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Ρυθμίσεις SMTP
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port = $config['smtp_port'];
        $mail->CharSet = 'UTF-8';
        
        // Αποστολέας και παραλήπτης
        $mail->setFrom($config['email_from'], $config['email_from_name']);
        $mail->addAddress($to);
        
        // Περιεχόμενο
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Προσθήκη συνημμένων
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }
        
        // Αποστολή
        $mail->send();
        
        return true;
    } catch (Exception $e) {
        // Καταγραφή του σφάλματος
        error_log("Email sending failed: " . $mail->ErrorInfo);
        
        return false;
    }
}

/**
 * Έλεγχος ασφαλείας για CSRF
 * 
 * @return boolean Αν το token είναι έγκυρο
 */
function check_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        return false;
    }
    
    return true;
}

/**
 * Δημιουργία πεδίου CSRF
 * 
 * @return string Το HTML του πεδίου
 */
function csrf_field() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token();
    }
    
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}