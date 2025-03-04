<?php
/**
 * AADE Integration Configuration
 * Configuration settings for AADE API integration
 * 
 * @package DriveTest
 */

// Ρυθμίσεις για την ενσωμάτωση της ΑΑΔΕ
$config['aade_integration'] = [
    // SOAP API Endpoint
    'endpoint' => 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2',
    
    // Διαπιστευτήρια για το API της ΑΑΔΕ - Αντικαταστήστε με τα δικά σας από την ΑΑΔΕ
    // ΣΗΜΑΝΤΙΚΟ: Για να λειτουργήσει το API, χρειάζεστε πραγματικά διαπιστευτήρια από την ΑΑΔΕ
    // Αν δεν έχετε διαπιστευτήρια, το σύστημα θα λειτουργεί σε κατάσταση προσομοίωσης
    'username' => '', // Αντικαταστήστε με το πραγματικό username
    'password' => '', // Αντικαταστήστε με το πραγματικό password
    
    // Διαδρομή για το αρχείο καταγραφής
    'log_path' => BASE_PATH . '/logs/aade_api.log',
    
    // Ενεργοποίηση/απενεργοποίηση της λειτουργίας ΑΑΔΕ
    'enabled' => true,
    
    // Χρόνος λήξης αποθηκευμένων δεδομένων (σε δευτερόλεπτα)
    'cache_expiry' => 86400, // 24 ώρες
    
    // Ρυθμίσεις για τα απαιτούμενα πεδία
    'required_fields' => [
        'schools' => ['tax_id', 'name', 'address', 'street_number', 'postal_code', 'city'],
    ],
];

// Για ευκολία πρόσβασης, ορίζουμε και τα διαπιστευτήρια ως ξεχωριστές μεταβλητές
$config['aade_username'] = $config['aade_integration']['username'];
$config['aade_password'] = $config['aade_integration']['password'];