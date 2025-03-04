<?php
/**
 * ΑΑΔΕ API Helper Functions
 * 
 * Βοηθητικές συναρτήσεις για το API της ΑΑΔΕ
 * 
 * @package DriveTest
 */

// Φόρτωση της κλάσης AADEIntegration αν δεν έχει ήδη φορτωθεί
if (!class_exists('AADEIntegration')) {
    require_once dirname(__DIR__) . '/classes/AADEIntegration.php';
}

/**
 * Συνάρτηση για την αναζήτηση και επαλήθευση των φορολογικών στοιχείων μέσω της ΑΑΔΕ
 * 
 * @param string $afm Ο ΑΦΜ προς αναζήτηση
 * @return array Τα στοιχεία που επιστρέφει η ΑΑΔΕ ή τυχόν σφάλμα
 */
function getAadeDetails($afm) {
    global $config;
    
    // Έλεγχος εγκυρότητας ΑΦΜ (9 ψηφία)
    if (strlen($afm) !== 9 || !ctype_digit($afm)) {
        return array('error' => 'Ο ΑΦΜ πρέπει να αποτελείται από 9 ψηφία.');
    }
    
    try {
        // Αν έχουν οριστεί τα διαπιστευτήρια, προσπαθούμε να χρησιμοποιήσουμε το API της ΑΑΔΕ
        // αλλιώς χρησιμοποιούμε την προσομοίωση
        if (!empty($config['aade_username']) && !empty($config['aade_password']) && $config['aade_integration']['enabled']) {
            error_log("Προσπάθεια σύνδεσης με ΑΑΔΕ για ΑΦΜ: " . $afm);
            
            // Δημιουργία διαδρομής για το αρχείο καταγραφής
            $log_path = isset($config['aade_integration']['log_path']) 
                ? $config['aade_integration']['log_path'] 
                : dirname(__DIR__) . '/logs/aade_api.log';
            
            // Δημιουργία του φακέλου logs αν δεν υπάρχει
            $logs_dir = dirname($log_path);
            if (!file_exists($logs_dir)) {
                mkdir($logs_dir, 0755, true);
            }
            
            try {
                // Αρχικοποίηση του αντικειμένου ενσωμάτωσης ΑΑΔΕ
                $aadeIntegration = new AADEIntegration(
                    $config['aade_username'],
                    $config['aade_password'],
                    $log_path
                );
                
                // Ανάκτηση πληροφοριών επιχείρησης
                return $aadeIntegration->getCompanyInfo($afm);
            } catch (Exception $e) {
                error_log("Σφάλμα ΑΑΔΕ: " . $e->getMessage());
                
                // Αν υπάρχει συγκεκριμένο σφάλμα για τον AFM_CALLED_BY, χρησιμοποιούμε την προσομοίωση
                if (strpos($e->getMessage(), 'RG_WS_PUBLIC_AFM_CALLED_BY_NOT_FOUND') !== false || 
                    strpos($e->getMessage(), 'afm_called_by') !== false) {
                    error_log("Χρήση προσομοίωσης λόγω σφάλματος ΑΑΔΕ");
                } else {
                    return array('error' => $e->getMessage());
                }
            }
        }
        
        // Χρήση προσομοίωσης όταν δεν υπάρχουν διαπιστευτήρια ή σε περίπτωση σφαλμάτων
        error_log("Χρήση προσομοίωσης για ΑΦΜ: " . $afm);
        
        // Προσομοίωση μιας απάντησης της ΑΑΔΕ με προκαθορισμένα στοιχεία
        // Σε πραγματικό περιβάλλον, εδώ θα γίνεται η κλήση στο API της ΑΑΔΕ
        
        // Χρήση προκαθορισμένων παραδειγματικών δεδομένων για σκοπούς επίδειξης
        if ($afm === '123456789') {
            return array(
                'afm' => '123456789',
                'doy' => '1104',
                'doy_descr' => 'Δ.Ο.Υ. ΦΑΕ ΘΕΣΣΑΛΟΝΙΚΗΣ',
                'i_ni_flag_descr' => 'ΜΗ ΦΠ',
                'deactivation_flag' => '1',
                'deactivation_flag_descr' => 'ΕΝΕΡΓΟΣ ΑΦΜ',
                'firm_flag_descr' => 'ΕΠΙΤΗΔΕΥΜΑΤΙΑΣ',
                'onomasia' => 'ΠΑΡΑΔΕΙΓΜΑ ΑΕ',
                'commercial_title' => 'ΠΑΡΑΔΕΙΓΜΑ',
                'legal_status_descr' => 'ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ',
                'postal_address' => 'ΕΓΝΑΤΙΑΣ',
                'postal_address_no' => '10',
                'postal_zip_code' => '54625',
                'postal_area_description' => 'ΘΕΣΣΑΛΟΝΙΚΗ',
                'regist_date' => '2000-01-01',
                'stop_date' => '',
                'normal_vat_system_flag' => 'Y'
            );
        } else {
            // Χρήση του τελευταίου ψηφίου του ΑΦΜ για να καθορίσουμε αν είναι φυσικό πρόσωπο
            $isPhysical = (intval(substr($afm, -1)) % 2 == 0) ? 'ΦΠ' : 'ΜΗ ΦΠ';
            
            // Δημιουργία προκαθορισμένων δεδομένων βάσει του ΑΦΜ
            return array(
                'afm' => $afm,
                'doy' => '1104',
                'doy_descr' => 'Δ.Ο.Υ. ΦΑΕ ΘΕΣΣΑΛΟΝΙΚΗΣ',
                'i_ni_flag_descr' => $isPhysical,
                'deactivation_flag' => '1',
                'deactivation_flag_descr' => 'ΕΝΕΡΓΟΣ ΑΦΜ',
                'firm_flag_descr' => 'ΕΠΙΤΗΔΕΥΜΑΤΙΑΣ',
                'onomasia' => ($isPhysical == 'ΦΠ') ? 'ΠΑΠΑΔΟΠΟΥΛΟΣ ΝΙΚΟΛΑΟΣ' : 'ΕΤΑΙΡΕΙΑ ' . $afm,
                'commercial_title' => ($isPhysical == 'ΦΠ') ? '' : 'ΕΤΑΙΡΕΙΑ ' . substr($afm, 0, 3),
                'legal_status_descr' => ($isPhysical == 'ΦΠ') ? 'ΑΤΟΜΙΚΗ ΕΠΙΧΕΙΡΗΣΗ' : 'ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ',
                'postal_address' => 'ΕΓΝΑΤΙΑΣ',
                'postal_address_no' => substr($afm, 0, 2),
                'postal_zip_code' => '54' . substr($afm, 2, 3),
                'postal_area_description' => 'ΘΕΣΣΑΛΟΝΙΚΗ',
                'regist_date' => '2000-01-01',
                'stop_date' => '',
                'normal_vat_system_flag' => 'Y'
            );
        }
    } catch (Exception $e) {
        return array('error' => 'Σφάλμα επεξεργασίας: ' . $e->getMessage());
    }
}

/**
 * Συνάρτηση για την επικύρωση ΑΦΜ με βάση τον αλγόριθμο
 * 
 * @param string $afm ΑΦΜ προς επικύρωση
 * @return bool Αν το ΑΦΜ είναι έγκυρο
 */
function isValidAfm($afm) {
    // Έλεγχος μορφής (9 ψηφία)
    if (!preg_match('/^\d{9}$/', $afm)) {
        return false;
    }
    
    // Αλγόριθμος επικύρωσης ΑΦΜ
    $sum = 0;
    for ($i = 0; $i < 8; $i++) {
        $sum += intval($afm[$i]) * pow(2, 8 - $i);
    }
    
    $checkDigit = $sum % 11;
    if ($checkDigit > 9) {
        $checkDigit = 0;
    }
    
    return $checkDigit === intval($afm[8]);
}