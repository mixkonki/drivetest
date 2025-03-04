/**
 * Script για την ενσωμάτωση με το API της ΑΑΔΕ
 * 
 * Παρέχει λειτουργίες για την επικοινωνία με το API της ΑΑΔΕ
 * και την αυτόματη συμπλήρωση φορμών με τα στοιχεία επιχειρήσεων.
 * 
 * @package DriveTest
 */

// Αντικείμενο για τη διαχείριση της ενσωμάτωσης με την ΑΑΔΕ
const AADEIntegration = {
    // Βασική διεύθυνση του API
    apiBaseUrl: window.location.origin + '/drivetest/api/aade_api.php',
    
    /**
     * Επικύρωση ΑΦΜ
     * 
     * @param {string} afm ΑΦΜ προς επικύρωση
     * @return {Promise} Promise που επιστρέφει αν το ΑΦΜ είναι έγκυρο
     */
    validateAfm: function(afm) {
        return fetch(`${this.apiBaseUrl}?action=validate&afm=${afm}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                return data.success;
            });
    },
    
    /**
     * Ανάκτηση στοιχείων επιχείρησης
     * 
     * @param {string} afm ΑΦΜ επιχείρησης
     * @param {string} asOnDate Προαιρετική ημερομηνία για ιστορικά στοιχεία (μορφή YYYY-MM-DD)
     * @return {Promise} Promise που επιστρέφει τα στοιχεία της επιχείρησης
     */
    getCompanyInfo: function(afm, asOnDate = null) {
        let url = `${this.apiBaseUrl}?action=info&afm=${afm}`;
        
        if (asOnDate) {
            url += `&date=${asOnDate}`;
        }
        
        return fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                return data.data;
            });
    },
    
    /**
     * Ανάκτηση πληροφοριών έκδοσης
     * 
     * @return {Promise} Promise που επιστρέφει την έκδοση του API
     */
    getVersionInfo: function() {
        return fetch(`${this.apiBaseUrl}?action=version`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                return data.version;
            });
    },
    
    /**
     * Παραγωγή προσομοιωμένων στοιχείων επιχείρησης
     * Χρησιμοποιείται όταν δεν είναι διαθέσιμο το API της ΑΑΔΕ
     * 
     * @param {string} afm ΑΦΜ επιχείρησης
     * @return {Promise} Promise που επιστρέφει τα προσομοιωμένα στοιχεία
     */
    getSimulatedCompanyInfo: function(afm) {
        return new Promise((resolve) => {
            // Μικρή καθυστέρηση για ρεαλιστική αίσθηση
            setTimeout(() => {
                // Προσομοίωση στοιχείων επιχείρησης με βάση το ΑΦΜ
                let data;
                
                if (afm === '123456789') {
                    data = {
                        'afm': '123456789',
                        'doy': '1104',
                        'doy_descr': 'Δ.Ο.Υ. ΦΑΕ ΘΕΣΣΑΛΟΝΙΚΗΣ',
                        'i_ni_flag_descr': 'ΜΗ ΦΠ',
                        'deactivation_flag': '1',
                        'deactivation_flag_descr': 'ΕΝΕΡΓΟΣ ΑΦΜ',
                        'firm_flag_descr': 'ΕΠΙΤΗΔΕΥΜΑΤΙΑΣ',
                        'onomasia': 'ΠΑΡΑΔΕΙΓΜΑ ΑΕ',
                        'commercial_title': 'ΠΑΡΑΔΕΙΓΜΑ',
                        'legal_status_descr': 'ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ',
                        'postal_address': 'ΕΓΝΑΤΙΑΣ',
                        'postal_address_no': '10',
                        'postal_zip_code': '54625',
                        'postal_area_description': 'ΘΕΣΣΑΛΟΝΙΚΗ'
                    };
                } else {
                    // Χρήση του τελευταίου ψηφίου του ΑΦΜ για να καθορίσουμε αν είναι φυσικό πρόσωπο
                    const isPhysical = (parseInt(afm.slice(-1)) % 2 === 0) ? 'ΦΠ' : 'ΜΗ ΦΠ';
                    
                    data = {
                        'afm': afm,
                        'doy': '1104',
                        'doy_descr': 'Δ.Ο.Υ. ΦΑΕ ΘΕΣΣΑΛΟΝΙΚΗΣ',
                        'i_ni_flag_descr': isPhysical,
                        'deactivation_flag': '1',
                        'deactivation_flag_descr': 'ΕΝΕΡΓΟΣ ΑΦΜ',
                        'firm_flag_descr': 'ΕΠΙΤΗΔΕΥΜΑΤΙΑΣ',
                        'onomasia': (isPhysical === 'ΦΠ') ? 'ΠΑΠΑΔΟΠΟΥΛΟΣ ΝΙΚΟΛΑΟΣ' : 'ΕΤΑΙΡΕΙΑ ' + afm,
                        'commercial_title': (isPhysical === 'ΦΠ') ? '' : 'ΕΤΑΙΡΕΙΑ ' + afm.substring(0, 3),
                        'legal_status_descr': (isPhysical === 'ΦΠ') ? 'ΑΤΟΜΙΚΗ ΕΠΙΧΕΙΡΗΣΗ' : 'ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ',
                        'postal_address': 'ΕΓΝΑΤΙΑΣ',
                        'postal_address_no': afm.substring(0, 2),
                        'postal_zip_code': '54' + afm.substring(2, 5),
                        'postal_area_description': 'ΘΕΣΣΑΛΟΝΙΚΗ'
                    };
                }
                
                resolve(data);
            }, 1000);
        });
    },
    
   /**
 * Αυτόματη συμπλήρωση φόρμας με στοιχεία επιχείρησης
 * 
 * @param {string} afm ΑΦΜ επιχείρησης
 * @param {Object} fieldMapping Αντιστοίχιση πεδίων API με πεδία φόρμας
 * @return {Promise} Promise που επιστρέφει αν η συμπλήρωση ήταν επιτυχής
 */
autoFillForm: function(afm, fieldMapping) {
    // Εμφάνιση μήνυμα φόρτωσης
    this.showLoader('Γίνεται ανάκτηση στοιχείων...');
    
    return this.getCompanyInfo(afm)
        .then(data => {
            // Εμφάνιση στην κονσόλα για debugging
            console.log('Στοιχεία που επιστράφηκαν από την ΑΑΔΕ:', data);
            console.log('Field mapping:', fieldMapping);
            
            // Συμπλήρωση της φόρμας με τα στοιχεία που επιστρέφονται
            for (const apiField in fieldMapping) {
                const formField = fieldMapping[apiField];
                const element = document.getElementById(formField);
                
                if (element && data[apiField] !== undefined) {
                    console.log(`Συμπλήρωση πεδίου ${formField} με τιμή ${data[apiField]}`);
                    element.value = data[apiField];
                    
                    // Πυροδότηση του event change
                    const event = new Event('change', { bubbles: true });
                    element.dispatchEvent(event);
                } else {
                    console.log(`Αδυναμία συμπλήρωσης πεδίου: ${formField} - Υπάρχει στοιχείο: ${!!element}, Υπάρχει τιμή: ${data[apiField] !== undefined}`);
                }
            }
            
            // Απόκρυψη μηνύματος φόρτωσης
            this.hideLoader();
            
            // Εμφάνιση μηνύματος επιτυχίας
            this.showMessage('Τα στοιχεία ανακτήθηκαν επιτυχώς από την ΑΑΔΕ!', 'success');
            
            return true;
        })
            .catch(error => {
                // Απόκρυψη μηνύματος φόρτωσης
                this.hideLoader();
                
                // Ελέγχουμε για συγκεκριμένα σφάλματα
                if (error.message.includes('RG_WS_PUBLIC_AFM_CALLED_BY_NOT_FOUND') || 
                    error.message.includes('afm_called_by')) {
                    // Αυτό το σφάλμα συμβαίνει όταν τα διαπιστευτήρια δεν έχουν ρυθμιστεί σωστά
                    this.showMessage('Χρησιμοποιείται η λειτουργία προσομοίωσης, καθώς τα διαπιστευτήρια ΑΑΔΕ δεν έχουν ρυθμιστεί σωστά.', 'warning');
                    
                    // Ανάκτηση προσομοιωμένων στοιχείων
                    return this.getSimulatedCompanyInfo(afm)
                        .then(data => {
                            // Συμπλήρωση της φόρμας με τα στοιχεία
                            for (const apiField in fieldMapping) {
                                const formField = fieldMapping[apiField];
                                const element = document.getElementById(formField);
                                
                                if (element && data[apiField] !== undefined) {
                                    element.value = data[apiField];
                                    
                                    // Πυροδότηση του event change
                                    const event = new Event('change', { bubbles: true });
                                    element.dispatchEvent(event);
                                }
                            }
                            
                            return true;
                        });
                } else {
                    // Εμφάνιση μηνύματος λάθους για άλλα σφάλματα
                    this.showMessage(`Σφάλμα: ${error.message}`, 'error');
                    return false;
                }
            });
    },
    
    /**
     * Εμφάνιση μηνύματος φόρτωσης
     * 
     * @param {string} message Προαιρετικό μήνυμα φόρτωσης
     */
    showLoader: function(message = 'Παρακαλώ περιμένετε...') {
        // Δημιουργία του loader αν δεν υπάρχει ήδη
        let loader = document.getElementById('aade-loader');
        
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'aade-loader';
            loader.className = 'loader-overlay';
            loader.innerHTML = `
                <div class="loader-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p id="loader-message">${message}</p>
                </div>
            `;
            document.body.appendChild(loader);
        } else {
            document.getElementById('loader-message').textContent = message;
            loader.style.display = 'flex';
        }
    },
    
    /**
     * Απόκρυψη μηνύματος φόρτωσης
     */
    hideLoader: function() {
        const loader = document.getElementById('aade-loader');
        
        if (loader) {
            loader.style.display = 'none';
        }
    },
    
    /**
     * Εμφάνιση μηνύματος
     * 
     * @param {string} message Το μήνυμα προς εμφάνιση
     * @param {string} type Ο τύπος του μηνύματος (success, error, warning, info)
     * @param {number} duration Διάρκεια σε ms (προεπιλογή: 5000ms)
     */
    showMessage: function(message, type = 'info', duration = 5000) {
        // Δημιουργία του container αν δεν υπάρχει ήδη
        let container = document.getElementById('aade-messages');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'aade-messages';
            container.className = 'messages-container';
            document.body.appendChild(container);
        }
        
        // Δημιουργία του μηνύματος
        const messageElement = document.createElement('div');
        messageElement.className = `alert alert-${type}`;
        messageElement.innerHTML = `
            <button type="button" class="close" onclick="this.parentElement.remove();">&times;</button>
            ${message}
        `;
        
        // Προσθήκη στο container
        container.appendChild(messageElement);
        
        // Αυτόματη απόκρυψη μετά από το συγκεκριμένο χρονικό διάστημα
        if (duration > 0) {
            setTimeout(() => {
                if (messageElement.parentNode) {
                    messageElement.remove();
                }
            }, duration);
        }
    }
};

/**
 * Συνάρτηση για ανάκτηση στοιχείων από την ΑΑΔΕ μέσω ΑΦΜ
 * Καλείται από το κουμπί "Ανάκτηση από ΑΑΔΕ" στις φόρμες
 * 
 * @param {string} afm ΑΦΜ προς αναζήτηση
 * @return {Promise} Promise που επιστρέφει αν η ανάκτηση ήταν επιτυχής
 */
function fetchCompanyInfo(afm) {
    // Έλεγχος αν έχει οριστεί ΑΦΜ
    if (!afm) {
        const afmField = document.getElementById('tax_id');
        if (afmField) {
            afm = afmField.value.trim();
        }
    }
    
    // Έλεγχος αν έχει οριστεί ΑΦΜ
    if (!afm) {
        AADEIntegration.showMessage('Παρακαλώ συμπληρώστε το ΑΦΜ πρώτα.', 'warning');
        return Promise.resolve(false);
    }
    
    // Αντιστοίχιση πεδίων API με πεδία φόρμας
    const fieldMapping = {
        'onomasia': 'school_name',
        'postal_address': 'address',
        'postal_address_no': 'street_number',
        'postal_zip_code': 'postal_code',
        'postal_area_description': 'city'
    };
    
    // Κλήση της μεθόδου autoFillForm του αντικειμένου AADEIntegration
    return AADEIntegration.autoFillForm(afm, fieldMapping);
}

// Προσθήκη του event listener για τα κουμπιά ανάκτησης στοιχείων ΑΑΔΕ όταν φορτωθεί η σελίδα
document.addEventListener('DOMContentLoaded', function() {
    // Κουμπιά ΑΑΔΕ
    const aadeButtons = document.querySelectorAll('.aade-button');
    
    aadeButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            
            // Έλεγχος αν υπάρχει data-afm attribute ή αν θα χρησιμοποιηθεί το πεδίο tax_id
            const afm = this.getAttribute('data-afm') || document.getElementById('tax_id')?.value;
            
            if (afm) {
                fetchCompanyInfo(afm);
            } else {
                AADEIntegration.showMessage('Δεν βρέθηκε ΑΦΜ για αναζήτηση.', 'warning');
            }
        });
    });
});