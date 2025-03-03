<?php
/**
 * Συνάρτηση για την αναζήτηση και επαλήθευση των φορολογικών στοιχείων μέσω της ΑΑΔΕ
 * 
 * @param string $afm Ο ΑΦΜ προς αναζήτηση
 * @return array Τα στοιχεία που επιστρέφει η ΑΑΔΕ ή τυχόν σφάλμα
 */
function getAadeDetails($afm) {
    // Προς το παρόν, επιστροφή προσομοιωμένων δεδομένων καθώς το API της ΑΑΔΕ δεν είναι προσβάσιμο
    // Αργότερα, θα αντικατασταθεί με πραγματική κλήση API όταν έχετε τα σωστά στοιχεία πρόσβασης
    
    // Έλεγχος εγκυρότητας ΑΦΜ (9 ψηφία)
    if (strlen($afm) !== 9 || !ctype_digit($afm)) {
        return array('error' => 'Ο ΑΦΜ πρέπει να αποτελείται από 9 ψηφία.');
    }
    
    try {
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
        
        // Όταν έχετε τα πραγματικά διαπιστευτήρια, θα μπορείτε να ενεργοποιήσετε τον παρακάτω κώδικα
        /*
        // Παράμετροι σύνδεσης με την υπηρεσία της ΑΑΔΕ
        $username = 'YOUR_AADE_USERNAME'; // Ο ειδικός κωδικός χρήστη σας
        $password = 'YOUR_AADE_PASSWORD'; // Ο ειδικός κωδικός πρόσβασής σας

        // URL της υπηρεσίας web service της ΑΑΔΕ
        $service_url = 'https://www1.gsis.gr/wsaade/RgWsPublic/RgWsPublicPort';
        
        // Δημιουργία του SOAP envelope
        $xml_request = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/" 
              xmlns:ns="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" 
              xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <env:Header>
        <ns:Security>
            <ns:UsernameToken>
                <ns:Username>{$username}</ns:Username>
                <ns:Password>{$password}</ns:Password>
            </ns:UsernameToken>
        </ns:Security>
    </env:Header>
    <env:Body>
        <rgWsPublicAfmMethod xmlns="http://gr/gsis/rgwspublic/RgWsPublic.wsdl">
            <INPUT_REC xsi:type="rgWsPublicInputRtType">
                <afm_called_by>{$username}</afm_called_by>
                <afm_called_for>{$afm}</afm_called_for>
            </INPUT_REC>
        </rgWsPublicAfmMethod>
    </env:Body>
</env:Envelope>
XML;

        // Αρχικοποίηση του cURL
        $ch = curl_init($service_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/xml;charset=UTF-8',
            'SOAPAction: ""',
            'Content-Length: ' . strlen($xml_request)
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Εκτέλεση του αιτήματος και λήψη της απάντησης
        $response = curl_exec($ch);
        
        // Έλεγχος για σφάλματα cURL
        if (curl_errno($ch)) {
            return array('error' => 'Σφάλμα cURL: ' . curl_error($ch));
        }
        
        // Έλεγχος κωδικού απάντησης HTTP
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return array('error' => 'Σφάλμα HTTP: ' . $http_code . '. Η ΑΑΔΕ δεν είναι διαθέσιμη. Παρακαλώ δοκιμάστε αργότερα.');
        }
        
        curl_close($ch);
        
        // Επεξεργασία της XML απάντησης
        libxml_use_internal_errors(true); // Ενεργοποίηση χειρισμού σφαλμάτων XML
        $xml = new SimpleXMLElement($response);
        
        // Ορισμός των namespaces
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('ns1', 'http://gr/gsis/rgwspublic/RgWsPublic.wsdl');
        
        // Έλεγχος για στοιχεία σφάλματος στην απάντηση
        $error_nodes = $xml->xpath('//soap:Fault');
        if (count($error_nodes) > 0) {
            $error_message = (string)$error_nodes[0]->faultstring;
            return array('error' => 'Σφάλμα ΑΑΔΕ: ' . $error_message);
        }
        
        // Εξαγωγή των δεδομένων από την απάντηση
        $result_nodes = $xml->xpath('//soap:Body/ns1:rgWsPublicAfmMethodResponse/result/RgWsPublicBasicRt');
        
        if (count($result_nodes) > 0) {
            $result = $result_nodes[0];
            
            // Δημιουργία του πίνακα με τα δεδομένα
            $data = array(
                'afm' => (string)$result->afm,
                'doy' => (string)$result->doy,
                'doy_descr' => (string)$result->doy_descr,
                'i_ni_flag_descr' => (string)$result->i_ni_flag_descr,
                'deactivation_flag' => (string)$result->deactivation_flag,
                'deactivation_flag_descr' => (string)$result->deactivation_flag_descr,
                'firm_flag_descr' => (string)$result->firm_flag_descr,
                'onomasia' => (string)$result->onomasia,
                'commercial_title' => (string)$result->commer_title,
                'legal_status_descr' => (string)$result->legal_status_descr,
                'postal_address' => (string)$result->postal_address,
                'postal_address_no' => (string)$result->postal_address_no,
                'postal_zip_code' => (string)$result->postal_zip_code,
                'postal_area_description' => (string)$result->postal_area_description,
                'regist_date' => (string)$result->regist_date,
                'stop_date' => (string)$result->stop_date,
                'normal_vat_system_flag' => (string)$result->normal_vat_system_flag
            );
            
            // Προσθήκη των δραστηριοτήτων αν υπάρχουν
            $activities = array();
            if (isset($result->firmActList)) {
                foreach ($result->firmActList->RgWsPublicFirmActRt as $activity) {
                    $activities[] = array(
                        'code' => (string)$activity->firm_act_code,
                        'description' => (string)$activity->firm_act_descr,
                        'kind' => (string)$activity->firm_act_kind,
                        'kind_description' => (string)$activity->firm_act_kind_descr
                    );
                }
                $data['activities'] = $activities;
            }
            
            return $data;
        } else {
            return array('error' => 'Δεν βρέθηκαν στοιχεία για τον ΑΦΜ ' . $afm);
        }
        */
    } catch (Exception $e) {
        return array('error' => 'Σφάλμα επεξεργασίας: ' . $e->getMessage());
    }
}
?>