<?php
// Διαδρομή: /classes/School.php

class School {
    /**
     * Ανακτά μια σχολή από το ID
     * 
     * @param int $id Το ID της σχολής
     * @return array|null Τα στοιχεία της σχολής ή null αν δεν βρέθηκε
     */
    public static function getById($id) {
        return db_get_row("SELECT * FROM schools WHERE id = ?", [$id]);
    }
    
    /**
     * Ανακτά μια σχολή από το email
     * 
     * @param string $email Το email της σχολής
     * @return array|null Τα στοιχεία της σχολής ή null αν δεν βρέθηκε
     */
    public static function getByEmail($email) {
        return db_get_row("SELECT * FROM schools WHERE email = ?", [$email]);
    }
    
    /**
     * Ανακτά μια σχολή από το tax_id (ΑΦΜ)
     * 
     * @param string $tax_id Το tax_id (ΑΦΜ) της σχολής
     * @return array|null Τα στοιχεία της σχολής ή null αν δεν βρέθηκε
     */
    public static function getByTaxId($tax_id) {
        return db_get_row("SELECT * FROM schools WHERE tax_id = ?", [$tax_id]);
    }
    
    /**
     * Ανακτά τη σχολή ενός χρήστη
     * 
     * @param int $user_id Το ID του χρήστη
     * @return array|null Τα στοιχεία της σχολής ή null αν δεν βρέθηκε
     */
    public static function getByUserId($user_id) {
        $user = User::getById($user_id);
        
        if (!$user || $user['role'] !== 'school') {
            return null;
        }
        
        return self::getById($user['school_id']);
    }
    
    /**
     * Ανακτά όλες τις σχολές
     * 
     * @param array $filters Φίλτρα για την ανάκτηση των σχολών
     * @param int $limit Ο μέγιστος αριθμός σχολών προς ανάκτηση
     * @param int $offset Η θέση εκκίνησης
     * @return array Οι σχολές
     */
    public static function getAll($filters = [], $limit = 100, $offset = 0) {
        $query = "SELECT * FROM schools WHERE 1=1";
        $params = [];
        
        // Προσθήκη φίλτρων
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (name LIKE ? OR email LIKE ? OR responsible_person LIKE ?)";
            $search = "%" . $filters['search'] . "%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['city'])) {
            $query .= " AND city = ?";
            $params[] = $filters['city'];
        }
        
        if (!empty($filters['category'])) {
            $query .= " AND JSON_CONTAINS(categories, ?)";
            $params[] = json_encode($filters['category']);
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
     * Δημιουργεί μια νέα σχολή
     * 
     * @param array $data Τα στοιχεία της σχολής
     * @return int|false Το ID της νέας σχολής ή false σε περίπτωση σφάλματος
     */
    public static function create($data) {
        // Έλεγχος αν υπάρχει ήδη σχολή με το ίδιο email ή tax_id
        $existing_school = self::getByEmail($data['email']);
        $existing_tax_id = self::getByTaxId($data['tax_id']);
        
        if ($existing_school || $existing_tax_id) {
            return false;
        }
        
        // Προετοιμασία των παραμέτρων
        $params = [
            $data['name'],
            $data['tax_id'],
            $data['responsible_person'],
            $data['license_number'] ?? '',
            $data['address'] ?? '',
            $data['street_number'] ?? '',
            $data['postal_code'] ?? '',
            $data['city'] ?? '',
            $data['email'],
            $data['phone'] ?? '',
            $data['website'] ?? '',
            json_encode($data['social_links'] ?? []),
            json_encode($data['categories'] ?? []),
            json_encode($data['training_categories'] ?? []),
            $data['logo'] ?? 'default_school_logo.png'
        ];
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            INSERT INTO schools (
                name, tax_id, responsible_person, license_number, 
                address, street_number, postal_code, city, 
                email, phone, website, social_links, 
                categories, training_categories, logo, created_at
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", $params);
    }
    
    /**
     * Ενημερώνει τα στοιχεία μιας σχολής
     * 
     * @param int $id Το ID της σχολής
     * @param array $data Τα νέα στοιχεία
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function update($id, $data) {
        // Έλεγχος αν υπάρχει η σχολή
        $school = self::getById($id);
        if (!$school) {
            return false;
        }
        
        // Προετοιμασία των στοιχείων προς ενημέρωση
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['tax_id'])) {
            // Έλεγχος αν υπάρχει ήδη άλλη σχολή με το ίδιο tax_id
            $existing_school = self::getByTaxId($data['tax_id']);
            if ($existing_school && $existing_school['id'] != $id) {
                return false;
            }
            
            $fields[] = "tax_id = ?";
            $params[] = $data['tax_id'];
        }
        
        if (isset($data['responsible_person'])) {
            $fields[] = "responsible_person = ?";
            $params[] = $data['responsible_person'];
        }
        
        if (isset($data['license_number'])) {
            $fields[] = "license_number = ?";
            $params[] = $data['license_number'];
        }
        
        if (isset($data['address'])) {
            $fields[] = "address = ?";
            $params[] = $data['address'];
        }
        
        if (isset($data['street_number'])) {
            $fields[] = "street_number = ?";
            $params[] = $data['street_number'];
        }
        
        if (isset($data['postal_code'])) {
            $fields[] = "postal_code = ?";
            $params[] = $data['postal_code'];
        }
        
        if (isset($data['city'])) {
            $fields[] = "city = ?";
            $params[] = $data['city'];
        }
        
        if (isset($data['email'])) {
            // Έλεγχος αν υπάρχει ήδη άλλη σχολή με το ίδιο email
            $existing_school = self::getByEmail($data['email']);
            if ($existing_school && $existing_school['id'] != $id) {
                return false;
            }
            
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        
        if (isset($data['website'])) {
            $fields[] = "website = ?";
            $params[] = $data['website'];
        }
        
        if (isset($data['social_links'])) {
            $fields[] = "social_links = ?";
            $params[] = json_encode($data['social_links']);
        }
        
        if (isset($data['categories'])) {
            $fields[] = "categories = ?";
            $params[] = json_encode($data['categories']);
        }
        
        if (isset($data['training_categories'])) {
            $fields[] = "training_categories = ?";
            $params[] = json_encode($data['training_categories']);
        }
        
        if (isset($data['logo'])) {
            $fields[] = "logo = ?";
            $params[] = $data['logo'];
        }
        
        if (isset($data['subscription_type'])) {
            $fields[] = "subscription_type = ?";
            $params[] = $data['subscription_type'];
        }
        
        if (isset($data['subscription_expiry'])) {
            $fields[] = "subscription_expiry = ?";
            $params[] = $data['subscription_expiry'];
        }
        
        if (isset($data['students_limit'])) {
            $fields[] = "students_limit = ?";
            $params[] = $data['students_limit'];
        }
        
        if (empty($fields)) {
            return true; // Δεν υπάρχουν πεδία προς ενημέρωση
        }
        
        // Προσθήκη του ID στις παραμέτρους
        $params[] = $id;
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            UPDATE schools 
            SET " . implode(", ", $fields) . ", updated_at = NOW()
            WHERE id = ?
        ", $params);
    }
    
    /**
     * Διαγράφει μια σχολή
     * 
     * @param int $id Το ID της σχολής
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function delete($id) {
        // Έλεγχος αν υπάρχει η σχολή
        $school = self::getById($id);
        if (!$school) {
            return false;
        }
        
        // Διαγραφή της σχολής
        return db_query("DELETE FROM schools WHERE id = ?", [$id]);
    }
    
    /**
     * Ανακτά τους μαθητές μιας σχολής
     * 
     * @param int $school_id Το ID της σχολής
     * @return array Οι μαθητές της σχολής
     */
    public static function getStudents($school_id) {
        return db_query("
            SELECT u.* 
            FROM users u
            JOIN students s ON u.id = s.user_id
            WHERE s.school_id = ?
            ORDER BY u.fullname
        ", [$school_id]);
    }
    
    /**
     * Ανακτά τα αιτήματα εγγραφής σε μια σχολή
     * 
     * @param int $school_id Το ID της σχολής
     * @param string $status Η κατάσταση των αιτημάτων (pending, approved, rejected)
     * @return array Τα αιτήματα
     */
    public static function getJoinRequests($school_id, $status = 'pending') {
        return db_query("
            SELECT r.*, u.fullname, u.email 
            FROM school_join_requests r
            JOIN users u ON r.user_id = u.id
            WHERE r.school_id = ? AND r.status = ?
            ORDER BY r.created_at DESC
        ", [$school_id, $status]);
    }
    
    /**
     * Εγκρίνει ή απορρίπτει ένα αίτημα εγγραφής
     * 
     * @param int $request_id Το ID του αιτήματος
     * @param string $action Η ενέργεια (approve ή reject)
     * @param string $rejection_reason Ο λόγος απόρριψης (μόνο για reject)
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function processJoinRequest($request_id, $action, $rejection_reason = '') {
        // Ανάκτηση του αιτήματος
        $request = db_get_row("SELECT * FROM school_join_requests WHERE id = ?", [$request_id]);
        
        if (!$request || $request['status'] !== 'pending') {
            return false;
        }
        
        if ($action === 'approve') {
            // Εγγραφή του χρήστη ως μαθητή της σχολής
            db_query("
                INSERT INTO students (user_id, school_id, created_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE school_id = ?, updated_at = NOW()
            ", [$request['user_id'], $request['school_id'], $request['school_id']]);
            
            // Ενημέρωση του αιτήματος
            return db_query("
                UPDATE school_join_requests 
                SET status = 'approved', updated_at = NOW() 
                WHERE id = ?
            ", [$request_id]);
        } elseif ($action === 'reject') {
            // Ενημέρωση του αιτήματος
            return db_query("
                UPDATE school_join_requests 
                SET status = 'rejected', rejection_reason = ?, updated_at = NOW() 
                WHERE id = ?
            ", [$rejection_reason, $request_id]);
        }
        
        return false;
    }
}