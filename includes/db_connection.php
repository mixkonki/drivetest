<?php
// Διαδρομή: /includes/db_connection.php

// Φόρτωση των ρυθμίσεων από το config.php
$config = include __DIR__ . '/../config/config.php';

if (!is_array($config)) {
    die("<p style='color:red;'>❌ Σφάλμα: Το config.php δεν επιστρέφει σωστά τις ρυθμίσεις. Η τιμή που επιστράφηκε είναι: " . var_export($config, true) . "</p>");
}

// Έλεγχος αν οι ρυθμίσεις της βάσης δεδομένων είναι πλήρεις
if (empty($config['db_host']) || empty($config['db_user']) || empty($config['db_name'])) {
    die("<p style='color:red;'>❌ Σφάλμα: Λείπουν ρυθμίσεις για τη βάση δεδομένων.</p>");
}

// Δημιουργία σύνδεσης με τη βάση δεδομένων
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_error) {
    die("<p style='color:red;'>❌ Σφάλμα σύνδεσης: " . $mysqli->connect_error . "</p>");
}

$mysqli->set_charset("utf8mb4");

/**
 * Βοηθητική συνάρτηση για την εκτέλεση ερωτημάτων SQL με prepared statements
 * 
 * @param string $query Το ερώτημα SQL
 * @param array $params Οι παράμετροι για το prepared statement
 * @param string $types Οι τύποι των παραμέτρων (προαιρετικό)
 * @return mixed Τα αποτελέσματα του ερωτήματος ή true/false για ερωτήματα που δεν επιστρέφουν αποτελέσματα
 */
function db_query($query, $params = [], $types = null) {
    global $mysqli;
    
    $stmt = $mysqli->prepare($query);
    
    if (!$stmt) {
        error_log("SQL Error: " . $mysqli->error . " - Query: " . $query);
        return false;
    }
    
    if (!empty($params)) {
        // Αν δεν δίνεται ο τύπος των παραμέτρων, προσπαθούμε να τον αναγνωρίσουμε αυτόματα
        if ($types === null) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param) || is_double($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        
        // Δημιουργία του πίνακα με τις παραμέτρους για το bind_param
        $bind_params = array();
        $bind_params[] = $types;
        
        // Προσθήκη παραμέτρων στον πίνακα
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        
        // Κλήση της bind_param με τις παραμέτρους
        call_user_func_array(array($stmt, 'bind_param'), $bind_params);
    }
    
    // Εκτέλεση του ερωτήματος
    $stmt->execute();
    
    // Έλεγχος για σφάλματα
    if ($stmt->errno) {
        error_log("SQL Execute Error: " . $stmt->error . " - Query: " . $query);
        $stmt->close();
        return false;
    }
    
    // Έλεγχος αν το ερώτημα επιστρέφει αποτελέσματα
    $result = $stmt->get_result();
    
    if ($result) {
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    } else {
        // Για INSERT, UPDATE, DELETE ερωτήματα
        $affected_rows = $stmt->affected_rows;
        $insert_id = $stmt->insert_id;
        $stmt->close();
        
        return ($affected_rows > 0) ? ($insert_id > 0 ? $insert_id : true) : false;
    }
}

/**
 * Επιστρέφει μία εγγραφή από ένα ερώτημα
 * 
 * @param string $query Το ερώτημα SQL
 * @param array $params Οι παράμετροι για το prepared statement
 * @param string $types Οι τύποι των παραμέτρων (προαιρετικό)
 * @return array|null Η εγγραφή ή null αν δεν βρέθηκε
 */
function db_get_row($query, $params = [], $types = null) {
    $results = db_query($query, $params, $types);
    
    if (is_array($results) && !empty($results)) {
        return $results[0];
    }
    
    return null;
}

/**
 * Επιστρέφει μία τιμή από ένα ερώτημα
 * 
 * @param string $query Το ερώτημα SQL
 * @param array $params Οι παράμετροι για το prepared statement
 * @param string $types Οι τύποι των παραμέτρων (προαιρετικό)
 * @return mixed Η τιμή ή null αν δεν βρέθηκε
 */
function db_get_var($query, $params = [], $types = null) {
    $row = db_get_row($query, $params, $types);
    
    if ($row) {
        return reset($row); // Επιστρέφει την πρώτη τιμή
    }
    
    return null;
}

/**
 * Επιστρέφει το ID της τελευταίας εγγραφής
 * 
 * @return int Το ID της τελευταίας εγγραφής
 */
function db_insert_id() {
    global $mysqli;
    return $mysqli->insert_id;
}

/**
 * Διαφυγή ειδικών χαρακτήρων για ασφαλή χρήση σε ερωτήματα SQL
 * 
 * @param string $string Το string προς διαφυγή
 * @return string Το string μετά τη διαφυγή
 */
function db_escape($string) {
    global $mysqli;
    return $mysqli->real_escape_string($string);
}