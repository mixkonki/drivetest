<?php
/**
 * Κλάση για την ενσωμάτωση του API της ΑΑΔΕ
 * 
 * Παρέχει μεθόδους για την επικοινωνία με το web service της ΑΑΔΕ
 * για την επαλήθευση ΑΦΜ και την ανάκτηση φορολογικών στοιχείων
 * 
 * @package DriveTest
 * @author DriveTest Team
 */

class AADEIntegration {
    /**
     * @var string Όνομα χρήστη για το API της ΑΑΔΕ
     */
    private $username;
    
    /**
     * @var string Κωδικός πρόσβασης για το API της ΑΑΔΕ
     */
    private $password;
    
    /**
     * @var string Διαδρομή αρχείου καταγραφής
     */
    private $logFile;
    
    /**
     * @var string URL του SOAP service
     */
    private $serviceUrl = 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2';
    
    /**
     * @var array Πίνακας με κρυφά πεδία σε logs
     */
    private $sensitiveFields = ['Username', 'Password'];
    
    /**
     * Κατασκευαστής
     * 
     * @param string $username Όνομα χρήστη για το API της ΑΑΔΕ
     * @param string $password Κωδικός πρόσβασης για το API της ΑΑΔΕ
     * @param string $logFile Προαιρετική διαδρομή αρχείου καταγραφής
     */
    public function __construct($username, $password, $logFile = null) {
        $this->username = $username;
        $this->password = $password;
        $this->logFile = $logFile;
    }
    
    /**
     * Ανάκτηση πληροφοριών επιχείρησης από το ΑΦΜ
     * 
     * @param string $afm ΑΦΜ της επιχείρησης
     * @param string $asOnDate Προαιρετική ημερομηνία για ιστορικά στοιχεία (μορφή Y-m-d)
     * @return array Πίνακας με τις πληροφορίες της επιχείρησης
     * @throws Exception Σε περίπτωση σφάλματος
     */
    public function getCompanyInfo($afm, $asOnDate = null) {
        $this->log("Έναρξη ανάκτησης πληροφοριών για ΑΦΜ: $afm");
        
        // Έλεγχος μορφής ΑΦΜ
        if (!$this->isValidAfm($afm)) {
            throw new Exception("Μη έγκυρο ΑΦΜ. Ο αλγόριθμος επικύρωσης απέτυχε.");
        }
        
        // Δημιουργία XML για το SOAP request
        $xml = $this->createAfmMethodRequest($afm, $asOnDate);
        
        // Αποστολή του αιτήματος και λήψη της απάντησης
        $response = $this->sendSoapRequest($xml);
        
        // Επεξεργασία της απάντησης
        $result = $this->parseAfmMethodResponse($response);
        
        $this->log("Επιτυχής ανάκτηση πληροφοριών για ΑΦΜ: $afm");
        
        return $result;
    }
    
    /**
     * Ανάκτηση πληροφοριών έκδοσης του API
     * 
     * @return string Πληροφορίες έκδοσης
     * @throws Exception Σε περίπτωση σφάλματος
     */
    public function getVersionInfo() {
        $this->log("Έναρξη ανάκτησης πληροφοριών έκδοσης API");
        
        // Δημιουργία XML για το SOAP request
        $xml = $this->createVersionInfoRequest();
        
        // Αποστολή του αιτήματος και λήψη της απάντησης
        $response = $this->sendSoapRequest($xml, false);
        
        // Επεξεργασία της απάντησης
        $result = $this->parseVersionInfoResponse($response);
        
        $this->log("Επιτυχής ανάκτηση πληροφοριών έκδοσης API: $result");
        
        return $result;
    }
    
    /**
     * Δημιουργία του XML για το αίτημα rgWsPublic2AfmMethod
     * 
     * @param string $afm ΑΦΜ της επιχείρησης
     * @param string $asOnDate Προαιρετική ημερομηνία για ιστορικά στοιχεία (μορφή Y-m-d)
     * @return string XML του αιτήματος
     */
    private function createAfmMethodRequest($afm, $asOnDate = null) {
        // Αν δεν έχει οριστεί ημερομηνία, χρησιμοποιούμε τη σημερινή
        if (empty($asOnDate)) {
            $asOnDate = date('Y-m-d');
        }
        
        // Το afm_called_by πρέπει να είναι ένας έγκυρος ΑΦΜ και όχι το username
        // Συχνά, τα διαπιστευτήρια της ΑΑΔΕ περιλαμβάνουν το afm_called_by ως ξεχωριστή παράμετρο
        // Εδώ χρησιμοποιούμε το username, αλλά σε πραγματικές συνθήκες θα πρέπει να είναι ο ΑΦΜ του καλούντος
        
        // Δημιουργία του XML
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" 
               xmlns:ns1="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
               xmlns:ns2="http://rgwspublic2/RgWsPublic2Service" 
               xmlns:ns3="http://rgwspublic2/RgWsPublic2">
    <soap:Header>
        <ns1:Security>
            <ns1:UsernameToken>
                <ns1:Username>{$this->username}</ns1:Username>
                <ns1:Password>{$this->password}</ns1:Password>
            </ns1:UsernameToken>
        </ns1:Security>
    </soap:Header>
    <soap:Body>
        <ns2:rgWsPublic2AfmMethod>
            <ns2:INPUT_REC>
                <ns3:afm_called_for>{$afm}</ns3:afm_called_for>
                <ns3:as_on_date>{$asOnDate}</ns3:as_on_date>
            </ns2:INPUT_REC>
        </ns2:rgWsPublic2AfmMethod>
    </soap:Body>
</soap:Envelope>
XML;

        return $xml;
    }
    
    /**
     * Δημιουργία του XML για το αίτημα rgWsPublic2VersionInfo
     * 
     * @return string XML του αιτήματος
     */
    private function createVersionInfoRequest() {
        // Δημιουργία του XML
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" 
               xmlns:rgw="http://rgwspublic2/RgWsPublic2Service">
    <soap:Header/>
    <soap:Body>
        <rgw:rgWsPublic2VersionInfo/>
    </soap:Body>
</soap:Envelope>
XML;

        return $xml;
    }
    
    /**
     * Αποστολή του SOAP αιτήματος στην υπηρεσία της ΑΑΔΕ
     * 
     * @param string $xml XML του αιτήματος
     * @param bool $auth Αν χρειάζεται authentication
     * @return string XML της απάντησης
     * @throws Exception Σε περίπτωση σφάλματος
     */
    private function sendSoapRequest($xml, $auth = true) {
        // Σύνδεση με το API της ΑΑΔΕ
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $this->serviceUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/soap+xml;charset=UTF-8',
            'Content-Length: ' . strlen($xml)
        ]);
        
        // SSL Options
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Εκτέλεση του αιτήματος
        $response = curl_exec($ch);
        
        // Έλεγχος για σφάλματα
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->log("Σφάλμα cURL: $error", 'error');
            throw new Exception("Σφάλμα σύνδεσης με την ΑΑΔΕ: $error");
        }
        
        // Έλεγχος κωδικού απάντησης HTTP
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            curl_close($ch);
            $this->log("Σφάλμα HTTP: $httpCode", 'error');
            throw new Exception("Η υπηρεσία ΑΑΔΕ επέστρεψε σφάλμα HTTP: $httpCode");
        }
        
        curl_close($ch);
        
        // Καταγραφή της απάντησης
        $log_response = $this->sanitizeXml($response);
        $this->log("Απάντηση από ΑΑΔΕ: $log_response");
        
        return $response;
    }
    
    /**
     * Επεξεργασία της απάντησης του rgWsPublic2AfmMethod
     * 
     * @param string $response XML της απάντησης
     * @return array Πίνακας με τις πληροφορίες της επιχείρησης
     * @throws Exception Σε περίπτωση σφάλματος
     */
    private function parseAfmMethodResponse($response) {
        // Επεξεργασία της XML απάντησης
        libxml_use_internal_errors(true);
        
        try {
            $xml = new SimpleXMLElement($response);
            
            // Ορισμός των namespaces
            $xml->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');
            $xml->registerXPathNamespace('ns2', 'http://rgwspublic2/RgWsPublic2Service');
            $xml->registerXPathNamespace('ns3', 'http://rgwspublic2/RgWsPublic2');
            
            // Έλεγχος για στοιχεία σφάλματος
            $error_nodes = $xml->xpath('//soap:Fault');
            if (!empty($error_nodes)) {
                $error_message = (string) $error_nodes[0]->faultstring;
                $this->log("Σφάλμα SOAP: $error_message", 'error');
                throw new Exception("Σφάλμα ΑΑΔΕ: $error_message");
            }
            
            // Έλεγχος για σφάλμα στην απάντηση
            $error_code = $xml->xpath('//ns3:error_rec/ns3:error_code');
            $error_descr = $xml->xpath('//ns3:error_rec/ns3:error_descr');
            
            if (!empty($error_code) && !empty($error_descr) && (string) $error_code[0] != '0') {
                $error_code_val = (string) $error_code[0];
                $error_descr_val = (string) $error_descr[0];
                $this->log("Σφάλμα ΑΑΔΕ: $error_code_val - $error_descr_val", 'error');
                throw new Exception("Σφάλμα ΑΑΔΕ: $error_descr_val (Κωδικός: $error_code_val)");
            }
            
            // Εξαγωγή των βασικών πληροφοριών
            $basic_info = $xml->xpath('//ns3:basic_rec')[0];
            
            // Δημιουργία του πίνακα αποτελεσμάτων
            $result = [
                'afm' => (string) $basic_info->afm,
                'doy' => (string) $basic_info->doy,
                'doy_descr' => (string) $basic_info->doy_descr,
                'i_ni_flag_descr' => (string) $basic_info->i_ni_flag_descr,
                'deactivation_flag' => (string) $basic_info->deactivation_flag,
                'deactivation_flag_descr' => (string) $basic_info->deactivation_flag_descr,
                'firm_flag_descr' => (string) $basic_info->firm_flag_descr,
                'onomasia' => (string) $basic_info->onomasia,
                'commercial_title' => (string) $basic_info->commer_title,
                'legal_status_descr' => (string) $basic_info->legal_status_descr,
                'postal_address' => (string) $basic_info->postal_address,
                'postal_address_no' => (string) $basic_info->postal_address_no,
                'postal_zip_code' => (string) $basic_info->postal_zip_code,
                'postal_area_description' => (string) $basic_info->postal_area_description,
                'regist_date' => (string) $basic_info->regist_date,
                'stop_date' => (string) $basic_info->stop_date,
                'normal_vat_system_flag' => (string) $basic_info->normal_vat_system_flag
            ];
            
            // Εξαγωγή των δραστηριοτήτων
            $firm_act_nodes = $xml->xpath('//ns3:firm_act_tab/ns3:item');
            $activities = [];
            
            if (!empty($firm_act_nodes)) {
                foreach ($firm_act_nodes as $activity) {
                    $activities[] = [
                        'code' => (string) $activity->firm_act_code,
                        'description' => (string) $activity->firm_act_descr,
                        'kind' => (string) $activity->firm_act_kind,
                        'kind_description' => (string) $activity->firm_act_kind_descr
                    ];
                }
            }
            
            // Προσθήκη των δραστηριοτήτων στο αποτέλεσμα
            $result['activities'] = $activities;
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Σφάλμα επεξεργασίας XML: " . $e->getMessage(), 'error');
            throw new Exception("Σφάλμα επεξεργασίας της απάντησης: " . $e->getMessage());
        }
    }
    
    /**
     * Επεξεργασία της απάντησης του rgWsPublic2VersionInfo
     * 
     * @param string $response XML της απάντησης
     * @return string Πληροφορίες έκδοσης
     * @throws Exception Σε περίπτωση σφάλματος
     */
    private function parseVersionInfoResponse($response) {
        // Επεξεργασία της XML απάντησης
        libxml_use_internal_errors(true);
        
        try {
            $xml = new SimpleXMLElement($response);
            
            // Ορισμός των namespaces
            $xml->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');
            $xml->registerXPathNamespace('ns2', 'http://rgwspublic2/RgWsPublic2Service');
            
            // Έλεγχος για στοιχεία σφάλματος
            $error_nodes = $xml->xpath('//soap:Fault');
            if (!empty($error_nodes)) {
                $error_message = (string) $error_nodes[0]->faultstring;
                $this->log("Σφάλμα SOAP: $error_message", 'error');
                throw new Exception("Σφάλμα ΑΑΔΕ: $error_message");
            }
            
            // Εξαγωγή των πληροφοριών έκδοσης
            $version_nodes = $xml->xpath('//ns2:result');
            
            if (empty($version_nodes)) {
                throw new Exception("Δεν βρέθηκαν πληροφορίες έκδοσης στην απάντηση");
            }
            
            return (string) $version_nodes[0];
            
        } catch (Exception $e) {
            $this->log("Σφάλμα επεξεργασίας XML: " . $e->getMessage(), 'error');
            throw new Exception("Σφάλμα επεξεργασίας της απάντησης: " . $e->getMessage());
        }
    }
    
    /**
     * Έλεγχος εγκυρότητας ΑΦΜ με βάση τον αλγόριθμο
     * 
     * @param string $afm ΑΦΜ προς έλεγχο
     * @return bool Αν το ΑΦΜ είναι έγκυρο
     */
    public function isValidAfm($afm) {
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
    
    /**
     * Καταγραφή ενεργειών και σφαλμάτων
     * 
     * @param string $message Μήνυμα για καταγραφή
     * @param string $level Επίπεδο καταγραφής (info, error, debug)
     * @return void
     */
    private function log($message, $level = 'info') {
        // Έλεγχος αν έχει οριστεί αρχείο καταγραφής
        if (empty($this->logFile)) {
            return;
        }
        
        // Χρονοσφραγίδα
        $timestamp = date('Y-m-d H:i:s');
        
        // Μορφοποίηση της καταγραφής
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Προσθήκη στο αρχείο
        file_put_contents($this->logFile, $log_entry, FILE_APPEND);
    }
    
    /**
     * Καθαρισμός του XML από ευαίσθητα δεδομένα για καταγραφή
     * 
     * @param string $xml XML προς καθαρισμό
     * @return string Καθαρισμένο XML
     */
    private function sanitizeXml($xml) {
        // Αντικατάσταση των ευαίσθητων πεδίων
        foreach ($this->sensitiveFields as $field) {
            $pattern = "/(<ns1:{$field}>)(.+?)(<\/ns1:{$field}>)/";
            $xml = preg_replace($pattern, "$1*****$3", $xml);
        }
        
        // Συμπίεση του XML για καταγραφή
        $xml = preg_replace('/>\s+</', '><', $xml);
        $xml = preg_replace('/\s\s+/', ' ', $xml);
        
        return $xml;
    }
}