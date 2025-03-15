<?php
// Διαδρομή: /includes/user_functions.php

/**
 * Ελέγχει αν ο χρήστης είναι συνδεδεμένος
 * 
 * @return boolean
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Ελέγχει αν ο χρήστης έχει έναν συγκεκριμένο ρόλο
 * 
 * @param string|array $role Ο ρόλος ή οι ρόλοι προς έλεγχο
 * @return boolean
 */
function has_role($role) {
    if (!is_logged_in() || !isset($_SESSION['role'])) {
        return false;
    }
    
    if (is_array($role)) {
        return in_array($_SESSION['role'], $role);
    }
    
    return $_SESSION['role'] === $role;
}

/**
 * Απαιτεί ο χρήστης να είναι συνδεδεμένος
 * 
 * @param string $redirect_url Η διεύθυνση ανακατεύθυνσης αν ο χρήστης δεν είναι συνδεδεμένος
 */
function require_login($redirect_url = '/public/login.php') {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . $redirect_url);
        exit;
    }
}

/**
 * Απαιτεί ο χρήστης να έχει έναν συγκεκριμένο ρόλο
 * 
 * @param string|array $role Ο ρόλος ή οι ρόλοι που επιτρέπονται
 * @param string $redirect_url Η διεύθυνση ανακατεύθυνσης αν ο χρήστης δεν έχει τον απαιτούμενο ρόλο
 */
function require_role($role, $redirect_url = '/public/login.php') {
    require_login($redirect_url);
    
    if (!has_role($role)) {
        header("Location: " . BASE_URL . $redirect_url);
        exit;
    }
}

/**
 * Επιστρέφει τα στοιχεία του τρέχοντος χρήστη
 * 
 * @return array|null Τα στοιχεία του χρήστη ή null αν δεν είναι συνδεδεμένος
 */
function get_current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    return db_get_row("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

/**
 * Ελέγχει αν ο χρήστης έχει ενεργή συνδρομή για μια συγκεκριμένη κατηγορία
 * 
 * @param int $category_id Το ID της κατηγορίας
 * @return boolean
 */
function has_active_subscription($category_id) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Έλεγχος για ενεργή συνδρομή
    $subscription = db_get_row("
        SELECT s.* 
        FROM subscriptions s
        WHERE (s.user_id = ? OR (s.school_id IN (SELECT school_id FROM students WHERE user_id = ?)))
        AND s.status = 'active'
        AND s.expiry_date >= CURDATE()
        AND JSON_CONTAINS(s.categories, ?)
    ", [$user_id, $user_id, json_encode($category_id)]);
    
    return !empty($subscription);
}

/**
 * Αποθηκεύει ένα μήνυμα επιτυχίας στη συνεδρία
 * 
 * @param string $message Το μήνυμα
 */
function set_success_message($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * Αποθηκεύει ένα μήνυμα σφάλματος στη συνεδρία
 * 
 * @param string $message Το μήνυμα
 */
function set_error_message($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * Αποσύνδεση χρήστη
 */
function logout() {
    // Καταστροφή όλων των δεδομένων συνεδρίας
    session_unset();
    session_destroy();
    
    // Ανακατεύθυνση στην αρχική σελίδα
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}