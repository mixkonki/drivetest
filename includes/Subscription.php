<?php
// Διαδρομή: /classes/Subscription.php

class Subscription {
    /**
     * Ανακτά μια συνδρομή από το ID
     * 
     * @param int $id Το ID της συνδρομής
     * @return array|null Τα στοιχεία της συνδρομής ή null αν δεν βρέθηκε
     */
    public static function getById($id) {
        return db_get_row("SELECT * FROM subscriptions WHERE id = ?", [$id]);
    }
    
    /**
     * Ανακτά τις συνδρομές ενός χρήστη
     * 
     * @param int $user_id Το ID του χρήστη
     * @return array Οι συνδρομές του χρήστη
     */
    public static function getUserSubscriptions($user_id) {
        return db_query("
            SELECT s.*, 
                  GROUP_CONCAT(sc.name SEPARATOR ', ') as category_names
            FROM subscriptions s
            LEFT JOIN subscription_categories sc ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON))
            WHERE s.user_id = ?
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ", [$user_id]);
    }
    
    /**
     * Ανακτά τις συνδρομές μιας σχολής
     * 
     * @param int $school_id Το ID της σχολής
     * @return array Οι συνδρομές της σχολής
     */
    public static function getSchoolSubscriptions($school_id) {
        return db_query("
            SELECT s.*, 
                  GROUP_CONCAT(sc.name SEPARATOR ', ') as category_names
            FROM subscriptions s
            LEFT JOIN subscription_categories sc ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON))
            WHERE s.school_id = ?
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ", [$school_id]);
    }
    
    /**
     * Ελέγχει αν ένας χρήστης έχει ενεργή συνδρομή για μια συγκεκριμένη κατηγορία
     * 
     * @param int $user_id Το ID του χρήστη
     * @param int $category_id Το ID της κατηγορίας
     * @return boolean
     */
    public static function hasActiveSubscription($user_id, $category_id) {
        // Έλεγχος για ενεργή συνδρομή του χρήστη
        $user_subscription = db_get_row("
            SELECT * FROM subscriptions 
            WHERE user_id = ? AND status = 'active' 
              AND expiry_date >= CURDATE() 
              AND JSON_CONTAINS(categories, ?)
        ", [$user_id, json_encode($category_id)]);
        
        if ($user_subscription) {
            return true;
        }
        
        // Έλεγχος για ενεργή συνδρομή της σχολής του χρήστη
        $school_subscription = db_get_row("
            SELECT s.* 
            FROM subscriptions s
            JOIN students st ON s.school_id = st.school_id
            WHERE st.user_id = ? AND s.status = 'active' 
              AND s.expiry_date >= CURDATE() 
              AND JSON_CONTAINS(s.categories, ?)
        ", [$user_id, json_encode($category_id)]);
        
        return !empty($school_subscription);
    }
    
    /**
     * Δημιουργεί μια νέα συνδρομή
     * 
     * @param array $data Τα στοιχεία της συνδρομής
     * @return int|false Το ID της νέας συνδρομής ή false σε περίπτωση σφάλματος
     */
    public static function create($data) {
        // Υπολογισμός της ημερομηνίας λήξης
        $start_date = $data['start_date'] ?? date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime($start_date . ' + ' . $data['durations'][$data['categories'][0]] . ' months'));
        
        // Προετοιμασία των παραμέτρων
        $params = [
            $data['user_id'] ?? null,
            $data['school_id'] ?? null,
            $data['subscription_type'],
            $expiry_date,
            $data['status'] ?? 'pending',
            $start_date,
            json_encode($data['subscription_data'] ?? []),
            json_encode($data['categories']),
            json_encode($data['durations'])
        ];
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            INSERT INTO subscriptions (
                user_id, school_id, subscription_type, expiry_date,
                status, start_date, subscription_data, categories, durations
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", $params);
    }
    
    /**
     * Ενημερώνει μια συνδρομή
     * 
     * @param int $id Το ID της συνδρομής
     * @param array $data Τα νέα στοιχεία
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function update($id, $data) {
        // Έλεγχος αν υπάρχει η συνδρομή
        $subscription = self::getById($id);
        if (!$subscription) {
            return false;
        }
        
        // Προετοιμασία των στοιχείων προς ενημέρωση
        $fields = [];
        $params = [];
        
        if (isset($data['subscription_type'])) {
            $fields[] = "subscription_type = ?";
            $params[] = $data['subscription_type'];
        }
        
        if (isset($data['expiry_date'])) {
            $fields[] = "expiry_date = ?";
            $params[] = $data['expiry_date'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['start_date'])) {
            $fields[] = "start_date = ?";
            $params[] = $data['start_date'];
        }
        
        if (isset($data['renewal_token'])) {
            $fields[] = "renewal_token = ?";
            $params[] = $data['renewal_token'];
        }
        
        if (isset($data['subscription_data'])) {
            $fields[] = "subscription_data = ?";
            $params[] = json_encode($data['subscription_data']);
        }
        
        if (isset($data['categories'])) {
            $fields[] = "categories = ?";
            $params[] = json_encode($data['categories']);
        }
        
        if (isset($data['durations'])) {
            $fields[] = "durations = ?";
            $params[] = json_encode($data['durations']);
        }
        
        if (empty($fields)) {
            return true; // Δεν υπάρχουν πεδία προς ενημέρωση
        }
        
        // Προσθήκη του ID στις παραμέτρους
        $params[] = $id;
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            UPDATE subscriptions 
            SET " . implode(", ", $fields) . "
            WHERE id = ?
        ", $params);
    }
    
    /**
     * Ανανεώνει μια συνδρομή
     * 
     * @param int $id Το ID της συνδρομής
     * @param int $months Οι μήνες ανανέωσης
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function renew($id, $months) {
        // Έλεγχος αν υπάρχει η συνδρομή
        $subscription = self::getById($id);
        if (!$subscription) {
            return false;
        }
        
        // Υπολογισμός της νέας ημερομηνίας λήξης
        $expiry_date = date('Y-m-d', strtotime($subscription['expiry_date'] . ' + ' . $months . ' months'));
        
        // Ενημέρωση της συνδρομής
        return db_query("
            UPDATE subscriptions 
            SET expiry_date = ?, status = 'active'
            WHERE id = ?
        ", [$expiry_date, $id]);
    }
    
    /**
     * Ακυρώνει μια συνδρομή
     * 
     * @param int $id Το ID της συνδρομής
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function cancel($id) {
        // Έλεγχος αν υπάρχει η συνδρομή
        $subscription = self::getById($id);
        if (!$subscription) {
            return false;
        }
        
        // Ενημέρωση της συνδρομής
        return db_query("
            UPDATE subscriptions 
            SET status = 'canceled'
            WHERE id = ?
        ", [$id]);
    }
    
    /**
     * Ανακτά τις διαθέσιμες κατηγορίες συνδρομών
     * 
     * @return array Οι κατηγορίες συνδρομών
     */
    public static function getCategories() {
        return db_query("SELECT * FROM subscription_categories ORDER BY name");
    }
    
    /**
     * Ανακτά τις διαθέσιμες διάρκειες συνδρομών
     * 
     * @return array Οι διάρκειες συνδρομών
     */
    public static function getDurations() {
        return db_query("SELECT * FROM subscription_durations ORDER BY months");
    }
}