<?php
/**
 * AADE Integration Class
 * 
 * Provides functionality to interact with the Greek Tax Authority (AADE) SOAP API
 * for validating VAT numbers and fetching company information.
 * 
 * @package DriveTest
 */

class AADEIntegration {
    /** @var string API endpoint URL */
    private $endpoint = 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2?WSDL';
    
    /** @var string API username */
    private $username;
    
    /** @var string API password */
    private $password;
    
    /** @var SoapClient SOAP client instance */
    private $client;
    
    /** @var string Path to log API interactions */
    private $logPath;
    
    /**
     * Constructor
     * 
     * @param string $username API username
     * @param string $password API password
     * @param string $logPath Optional path to log file
     */
    public function __construct($username, $password, $logPath = null) {
        $this->username = $username;
        $this->password = $password;
        $this->logPath = $logPath ?: dirname(__DIR__) . '/logs/aade_api.log';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(dirname($this->logPath))) {
            mkdir(dirname($this->logPath), 0755, true);
        }
        
        try {
            $this->initClient();
        } catch (Exception $e) {
            $this->logError('Failed to initialize SOAP client: ' . $e->getMessage());
            throw new Exception('Failed to connect to AADE API: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize the SOAP client
     */
    private function initClient() {
        $options = [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE
        ];
        
        $this->client = new SoapClient($this->endpoint, $options);
    }
    
    /**
     * Check if a VAT number (AFM) is valid and fetch company details
     * 
     * @param string $vatNumber The VAT number (AFM) to validate
     * @param string $asOnDate Optional date format YYYY-MM-DD
     * @return array Company information if valid
     * @throws Exception If VAT number is invalid or service error
     */
    public function getCompanyInfo($vatNumber, $asOnDate = null) {
        // Validate AFM format (9 digits)
        if (!preg_match('/^\d{9}$/', $vatNumber)) {
            throw new Exception('Invalid VAT number format. Must be 9 digits.');
        }
        
        try {
            $requestXml = $this->buildRequestXml($vatNumber, $asOnDate);
            $this->logRequest($requestXml);
            
            $response = $this->client->rgWsPublic2AfmMethod([
                'INPUT_REC' => ['afm' => $vatNumber, 'asOnDate' => $asOnDate]
            ]);
            
            $responseData = $response->result;
            $this->logResponse(print_r($responseData, true));
            
            // Check for error in response
            if (isset($responseData->error) && $responseData->error) {
                throw new Exception('AADE Error: ' . $responseData->errorMessage);
            }
            
            // Process and return company information
            return $this->processResponse($responseData);
            
        } catch (SoapFault $fault) {
            $this->logError('SOAP Fault: ' . $fault->getMessage());
            throw new Exception('AADE Service Error: ' . $fault->getMessage());
        } catch (Exception $e) {
            $this->logError('Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check AADE API version info
     * 
     * @return string Version information
     */
    public function getVersionInfo() {
        try {
            $response = $this->client->rgWsPublic2VersionInfo();
            return $response->result;
        } catch (SoapFault $fault) {
            $this->logError('SOAP Fault in version check: ' . $fault->getMessage());
            throw new Exception('AADE Service Error: ' . $fault->getMessage());
        }
    }
    
    /**
     * Build XML request for the AADE API
     * 
     * @param string $vatNumber The VAT number
     * @param string|null $asOnDate Optional date
     * @return string XML request
     */
    private function buildRequestXml($vatNumber, $asOnDate = null) {
        $xml = '<RgWsPublic2AfmMethod_WithOUTAsOnDate_Request>';
        $xml .= $this->username . ' ' . $this->password;
        $xml .= $vatNumber;
        
        if ($asOnDate) {
            $xml .= $asOnDate;
        }
        
        $xml .= '</RgWsPublic2AfmMethod_WithOUTAsOnDate_Request>';
        
        return $xml;
    }
    
    /**
     * Process and format the AADE response
     * 
     * @param object $response The SOAP response object
     * @return array Formatted company information
     */
    private function processResponse($response) {
        // Convert response to a more usable format
        return [
            'afm' => $response->afm ?? '',
            'name' => ($response->legalName ?? '') ?: ($response->firmName ?? ''),
            'commercialName' => $response->commercialTitle ?? '',
            'legalForm' => $response->legalStatusDesc ?? '',
            'address' => [
                'street' => $response->postalAddress ?? '',
                'streetNumber' => $response->postalAddressNo ?? '',
                'postalCode' => $response->postalZipCode ?? '',
                'city' => $response->postalAreaDescription ?? '',
            ],
            'status' => [
                'description' => $response->registrationDesc ?? '',
                'isActive' => (isset($response->registrationFlag) && $response->registrationFlag == 1)
            ],
            'activities' => [
                [
                    'code' => $response->firmActCode ?? '',
                    'description' => $response->firmActDescr ?? '',
                    'isPrimary' => (isset($response->firmActKindDesc) && $response->firmActKindDesc == 'ΚΥΡΙΑ')
                ]
            ],
            'registrationDate' => $response->registDate ?? '',
            'deactivationDate' => $response->stopDate ?? '',
            'raw' => $response // Keep the raw response for debugging
        ];
    }
    
    /**
     * Log API request
     * 
     * @param string $data Request data
     */
    private function logRequest($data) {
        $this->log('REQUEST: ' . $data);
    }
    
    /**
     * Log API response
     * 
     * @param string $data Response data
     */
    private function logResponse($data) {
        $this->log('RESPONSE: ' . $data);
    }
    
    /**
     * Log error
     * 
     * @param string $message Error message
     */
    private function logError($message) {
        $this->log('ERROR: ' . $message);
    }
    
    /**
     * Write log to file
     * 
     * @param string $message Message to log
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($this->logPath, $logMessage, FILE_APPEND);
    }
}