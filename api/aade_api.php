<?php
/**
 * AADE API Endpoint
 * Handles API requests for AADE integration
 * 
 * @package DriveTest
 * @file api/aade_api.php
 */

// Ορισμός του header για JSON response
header('Content-Type: application/json');

// Καταγραφή σφαλμάτων για debugging
ini_set('display_errors', 0); // Απενεργοποίηση εμφάνισης σφαλμάτων στον browser
error_reporting(E_ALL);

try {
    // Φόρτωση των απαραίτητων αρχείων
    require_once dirname(__DIR__) . '/config/config.php';
    require_once dirname(__DIR__) . '/includes/db_connection.php';
    require_once dirname(__DIR__) . '/includes/aade_api.php';

    // Έλεγχος της μεθόδου HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Μη αποδεκτή μέθοδος. Επιτρέπονται μόνο GET και POST.']);
        exit();
    }

    // Λήψη της ενέργειας από το αίτημα
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    // Έλεγχος αν έχει οριστεί ενέργεια
    if (empty($action)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Δεν έχει οριστεί ενέργεια.']);
        exit();
    }

    // Εκτέλεση της ενέργειας
    switch ($action) {
        case 'validate':
            // Έλεγχος αν παρέχεται το ΑΦΜ
            if (!isset($_REQUEST['afm']) || empty($_REQUEST['afm'])) {
                http_response_code(400); // Bad Request
                echo json_encode(['error' => 'Το ΑΦΜ είναι υποχρεωτικό.']);
                exit();
            }
            
            $afm = trim($_REQUEST['afm']);
            
            // Επικύρωση με βάση τον αλγόριθμο
            $isValid = isValidAfm($afm);
            
            if (!$isValid) {
                http_response_code(400); // Bad Request
                echo json_encode(['error' => 'Μη έγκυρο ΑΦΜ. Ο αλγόριθμος επικύρωσης απέτυχε.']);
                exit();
            }
            
            // Επιστροφή απλής επιτυχίας
            echo json_encode(['success' => true, 'afm' => $afm]);
            break;
            
        case 'info':
            // Έλεγχος αν παρέχεται το ΑΦΜ
            if (!isset($_REQUEST['afm']) || empty($_REQUEST['afm'])) {
                http_response_code(400); // Bad Request
                echo json_encode(['error' => 'Το ΑΦΜ είναι υποχρεωτικό.']);
                exit();
            }
            
            $afm = trim($_REQUEST['afm']);
            
            // Χρήση της συνάρτησης getAadeDetails από το includes/aade_api.php
            $companyInfo = getAadeDetails($afm);
            
            // Έλεγχος για σφάλματα
            if (isset($companyInfo['error'])) {
                http_response_code(400); // Bad Request
                echo json_encode(['error' => $companyInfo['error']]);
                exit();
            }
            
            // Επιστροφή των αποτελεσμάτων σε μορφή JSON
            echo json_encode(['success' => true, 'data' => $companyInfo]);
            break;
            
        case 'version':
            // Απλή πληροφορία έκδοσης
            $versionInfo = "DriveTest AADE API v1.0";
            
            echo json_encode(['success' => true, 'version' => $versionInfo]);
            break;
            
        default:
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Μη έγκυρη ενέργεια.']);
            break;
    }
} catch (Exception $e) {
    // Καταγραφή του σφάλματος σε αρχείο
    error_log("AADE API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine(), 0);
    
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Σφάλμα στο server: ' . $e->getMessage()]);
    exit();
}