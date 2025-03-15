<?php
// Διαδρομή: /includes/api_functions.php

/**
 * Επιστρέφει μια απάντηση JSON
 * 
 * @param mixed $data Τα δεδομένα προς επιστροφή
 * @param int $status_code Ο κωδικός κατάστασης HTTP
 */
function api_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Επιστρέφει ένα σφάλμα JSON
 * 
 * @param string $message Το μήνυμα σφάλματος
 * @param int $status_code Ο κωδικός κατάστασης HTTP
 */
function api_error($message, $status_code = 400) {
    api_response(['error' => $message], $status_code);
}

/**
 * Ελέγχει αν ο χρήστης είναι συνδεδεμένος
 * 
 * @return boolean
 */
function api_is_logged_in() {
    // Έλεγχος για Bearer token
    $headers = getallheaders();
    
    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        
        if (strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            
            // Έλεγχος του token στη βάση δεδομένων
            $user = db_get_row("SELECT * FROM users WHERE session_token = ?", [$token]);
            
            if ($user) {
                // Αποθήκευση των στοιχείων του χρήστη στη συνεδρία
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                return true;
            }
        }
    }
    
    // Έλεγχος για συνεδρία
    return isset($_SESSION['user_id']);
}

/**
 * Απαιτεί ο χρήστης να είναι συνδεδεμένος
 */
function api_require_login() {
    if (!api_is_logged_in()) {
        api_error('Unauthorized', 401);
    }
}

/**
 * Απαιτεί ο χρήστης να έχει έναν συγκεκριμένο ρόλο
 * 
 * @param string|array $role Ο ρόλος ή οι ρόλοι που επιτρέπονται
 */
function api_require_role($role) {
    api_require_login();
    
    if (is_array($role)) {
        if (!in_array($_SESSION['role'], $role)) {
            api_error('Forbidden', 403);
        }
    } else {
        if ($_SESSION['role'] !== $role) {
            api_error('Forbidden', 403);
        }
    }
}

/**
 * Λαμβάνει τα δεδομένα JSON από το αίτημα
 * 
 * @return array Τα δεδομένα JSON
 */
function api_get_json_data() {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        api_error('Invalid JSON data');
    }
    
    return $data;
}

/**
 * Ελέγχει αν τα απαιτούμενα πεδία υπάρχουν στα δεδομένα
 * 
 * @param array $data Τα δεδομένα προς έλεγχο
 * @param array $required_fields Τα απαιτούμενα πεδία
 */
function api_check_required_fields($data, $required_fields) {
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            api_error("Missing required field: $field");
        }
    }
}