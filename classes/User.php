<?php
// Διαδρομή: /classes/User.php

class User {
    /**
     * Ανακτά έναν χρήστη από το ID
     * 
     * @param int $id Το ID του χρήστη
     * @return array|null Τα στοιχεία του χρήστη ή null αν δεν βρέθηκε
     */
    public static function getById($id) {
        return db_get_row("SELECT * FROM users WHERE id = ?", [$id]);
    }
    
    /**
     * Ανακτά έναν χρήστη από το email
     * 
     * @param string $email Το email του χρήστη
     * @return array|null Τα στοιχεία του χρήστη ή null αν δεν βρέθηκε
     */
    public static function getByEmail($email) {
        return db_get_row("SELECT * FROM users WHERE email = ?", [$email]);
    }
    
    /**
     * Ανακτά όλους τους χρήστες
     * 
     * @param array $filters Φίλτρα για την ανάκτηση των χρηστών
     * @param int $limit Ο μέγιστος αριθμός χρηστών προς ανάκτηση
     * @param int $offset Η θέση εκκίνησης
     * @return array Οι χρήστες
     */
    public static function getAll($filters = [], $limit = 100, $offset = 0) {
        $query = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        // Προσθήκη φίλτρων
        if (!empty($filters['role'])) {
            $query .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (fullname LIKE ? OR email LIKE ?)";
            $search = "%" . $filters['search'] . "%";
            $params[] = $search;
            $params[] = $search;
        }
        
        // Προσθήκη ταξινόμησης
        $query .= " ORDER BY created_at DESC";
        
        // Προσθήκη pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        return db_query($query, $params);
    }
    
    /**
     * Δημιουργεί έναν νέο χρήστη
     * 
     * @param array $data Τα στοιχεία του χρήστη
     * @return int|false Το ID του νέου χρήστη ή false σε περίπτωση σφάλματος
     */
    public static function create($data) {
        // Έλεγχος αν υπάρχει ήδη χρήστης με το ίδιο email
        $existing_user = self::getByEmail($data['email']);
        if ($existing_user) {
            return false;
        }
        
        // Δημιουργία hash για τον κωδικό
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Προετοιμασία των παραμέτρων
        $params = [
            $data['fullname'],
            $data['email'],
            $hashed_password,
            $data['role'] ?? 'user',
            $data['status'] ?? 'active'
        ];
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            INSERT INTO users (fullname, email, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ", $params);
    }
    
    /**
     * Ενημερώνει τα στοιχεία ενός χρήστη
     * 
     * @param int $id Το ID του χρήστη
     * @param array $data Τα νέα στοιχεία
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function update($id, $data) {
        // Έλεγχος αν υπάρχει ο χρήστης
        $user = self::getById($id);
        if (!$user) {
            return false;
        }
        
        // Προετοιμασία των στοιχείων προς ενημέρωση
        $fields = [];
        $params = [];
        
        if (isset($data['fullname'])) {
            $fields[] = "fullname = ?";
            $params[] = $data['fullname'];
        }
        
        if (isset($data['email'])) {
            // Έλεγχος αν υπάρχει ήδη άλλος χρήστης με το ίδιο email
            $existing_user = self::getByEmail($data['email']);
            if ($existing_user && $existing_user['id'] != $id) {
                return false;
            }
            
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        
        if (isset($data['address'])) {
            $fields[] = "address = ?";
            $params[] = $data['address'];
        }
        
        if (isset($data['city'])) {
            $fields[] = "city = ?";
            $params[] = $data['city'];
        }
        
        if (isset($data['postal_code'])) {
            $fields[] = "postal_code = ?";
            $params[] = $data['postal_code'];
        }
        
        if (empty($fields)) {
            return true; // Δεν υπάρχουν πεδία προς ενημέρωση
        }
        
        // Προσθήκη του ID στις παραμέτρους
        $params[] = $id;
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            UPDATE users 
            SET " . implode(", ", $fields) . " 
            WHERE id = ?
        ", $params);
    }
    
    /**
     * Διαγράφει έναν χρήστη
     * 
     * @param int $id Το ID του χρήστη
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function delete($id) {
        // Έλεγχος αν υπάρχει ο χρήστης
        $user = self::getById($id);
        if (!$user) {
            return false;
        }
        
        // Διαγραφή του χρήστη
        return db_query("DELETE FROM users WHERE id = ?", [$id]);
    }
    
    /**
     * Ελέγχει τα στοιχεία σύνδεσης ενός χρήστη
     * 
     * @param string $email Το email του χρήστη
     * @param string $password Ο κωδικός πρόσβασης
     * @return array|false Τα στοιχεία του χρήστη ή false σε περίπτωση λάθους στοιχείων
     */
    public static function login($email, $password) {
        // Ανάκτηση του χρήστη με βάση το email
        $user = self::getByEmail($email);
        
/**
     * Ελέγχει τα στοιχεία σύνδεσης ενός χρήστη
     * 
     * @param string $email Το email του χρήστη
     * @param string $password Ο κωδικός πρόσβασης
     * @return array|false Τα στοιχεία του χρήστη ή false σε περίπτωση λάθους στοιχείων
     */
    public static function login($email, $password) {
        // Ανάκτηση του χρήστη με βάση το email
        $user = self::getByEmail($email);
        
        // Έλεγχος αν βρέθηκε ο χρήστης και αν ο κωδικός είναι σωστός
        if ($user && password_verify($password, $user['password']) && $user['status'] === 'active') {
            // Ενημέρωση της τελευταίας σύνδεσης
            db_query("UPDATE users SET last_activity = NOW() WHERE id = ?", [$user['id']]);
            
            // Αποθήκευση των στοιχείων του χρήστη στη συνεδρία
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            return $user;
        }
        
        return false;
    }
    
    /**
     * Αλλάζει τον κωδικό πρόσβασης ενός χρήστη
     * 
     * @param int $id Το ID του χρήστη
     * @param string $current_password Ο τρέχων κωδικός
     * @param string $new_password Ο νέος κωδικός
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function changePassword($id, $current_password, $new_password) {
        // Ανάκτηση του χρήστη
        $user = self::getById($id);
        
        // Έλεγχος αν βρέθηκε ο χρήστης και αν ο τρέχων κωδικός είναι σωστός
        if ($user && password_verify($current_password, $user['password'])) {
            // Δημιουργία hash για τον νέο κωδικό
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Ενημέρωση του κωδικού
            return db_query("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $id]);
        }
        
        return false;
    }
    
    /**
     * Αποστέλλει ένα email για επαναφορά κωδικού
     * 
     * @param string $email Το email του χρήστη
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function resetPasswordRequest($email) {
        // Ανάκτηση του χρήστη με βάση το email
        $user = self::getByEmail($email);
        
        if (!$user) {
            return false;
        }
        
        // Δημιουργία ενός μοναδικού token
        $token = bin2hex(random_bytes(32));
        
        // Αποθήκευση του token στη βάση δεδομένων
        $result = db_query("
            UPDATE users 
            SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
            WHERE id = ?
        ", [$token, $user['id']]);
        
        if (!$result) {
            return false;
        }
        
        // Αποστολή του email
        global $config;
        
        // Εδώ θα προσθέσεις τον κώδικα για την αποστολή του email
        // Χρησιμοποιώντας το $config για τις ρυθμίσεις SMTP
        
        return true;
    }
    
    /**
     * Επαναφέρει τον κωδικό πρόσβασης ενός χρήστη με βάση ένα token
     * 
     * @param string $token Το token επαναφοράς
     * @param string $new_password Ο νέος κωδικός
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function resetPassword($token, $new_password) {
        // Ανάκτηση του χρήστη με βάση το token
        $user = db_get_row("
            SELECT * FROM users 
            WHERE reset_token = ? AND reset_token_expiry > NOW()
        ", [$token]);
        
        if (!$user) {
            return false;
        }
        
        // Δημιουργία hash για τον νέο κωδικό
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Ενημέρωση του κωδικού και καθαρισμός του token
        return db_query("
            UPDATE users 
            SET password = ?, reset_token = NULL, reset_token_expiry = NULL 
            WHERE id = ?
        ", [$hashed_password, $user['id']]);
    }
}