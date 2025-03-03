/**
 * AADE Integration Client-side Script
 * Handles client-side functionality for AADE tax ID validation
 * 
 * @package DriveTest
 * @file assets/js/aade-integration.js
 */

// Ορισμός της βασικής διεύθυνσης (URL) της εφαρμογής
const baseUrl = window.location.origin + '/drivetest';

document.addEventListener('DOMContentLoaded', function() {
    // Έλεγχος για το κουμπί ΑΑΔΕ
    const aadeButtons = document.querySelectorAll('.aade-button');
    
    if (aadeButtons.length > 0) {
        aadeButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const taxIdField = document.getElementById('tax_id');
                
                if (!taxIdField || !taxIdField.value.trim()) {
                    showMessage('error', 'Παρακαλώ εισάγετε έγκυρο ΑΦΜ πρώτα.');
                    return;
                }
                
                const taxId = taxIdField.value.trim();
                fetchCompanyInfo(taxId);
            });
        });
    }
    
    // Προσθήκη επικύρωσης ΑΦΜ στη φόρμα εγγραφής σχολής
    const taxIdInput = document.getElementById('tax_id');
    if (taxIdInput) {
        taxIdInput.addEventListener('blur', function() {
            validateTaxId(this.value.trim());
        });
    }
});

/**
 * Επικύρωση ΑΦΜ με βάση τον αλγόριθμο
 * 
 * @param {string} taxId ΑΦΜ προς επικύρωση
 * @returns {boolean} Εάν το ΑΦΜ είναι έγκυρο
 */
function validateTaxId(taxId) {
    // Έλεγχος μορφής (9 ψηφία)
    if (!/^\d{9}$/.test(taxId)) {
        if (taxId) {
            showFieldError('tax_id', 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία.');
        }
        return false;
    }
    
    // Αλγόριθμος επικύρωσης ΑΦΜ
    let sum = 0;
    for (let i = 0; i < 8; i++) {
        sum += parseInt(taxId.charAt(i)) * Math.pow(2, 8 - i);
    }
    
    let checkDigit = sum % 11;
    if (checkDigit > 9) {
        checkDigit = 0;
    }
    
    if (checkDigit === parseInt(taxId.charAt(8))) {
        clearFieldError('tax_id');
        return true;
    } else {
        showFieldError('tax_id', 'Μη έγκυρο ΑΦΜ. Παρακαλώ ελέγξτε τα ψηφία.');
        return false;
    }
}

/**
 * Ανάκτηση πληροφοριών επιχείρησης από την ΑΑΔΕ
 * 
 * @param {string} taxId ΑΦΜ της επιχείρησης
 */
function fetchCompanyInfo(taxId) {
    // Έλεγχος εάν το ΑΦΜ είναι έγκυρο
    if (!validateTaxId(taxId)) {
        return;
    }
    
    // Εμφάνιση φόρτωσης
    const loader = showLoader();
    
    // Αποστολή αιτήματος στον server
    fetch(`${baseUrl}/api/aade_api.php?action=info&afm=${taxId}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'Σφάλμα κατά την επικοινωνία με την ΑΑΔΕ.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Συμπλήρωση των πεδίων της φόρμας με τα δεδομένα από την ΑΑΔΕ
                populateFormFields(data.data);
                showMessage('success', 'Τα στοιχεία ανακτήθηκαν επιτυχώς από την ΑΑΔΕ.');
            } else {
                showMessage('error', data.error || 'Άγνωστο σφάλμα κατά την ανάκτηση δεδομένων.');
            }
        })
        .catch(error => {
            showMessage('error', error.message);
        })
        .finally(() => {
            // Απόκρυψη φόρτωσης
            hideLoader(loader);
        });
}

/**
 * Ενημέρωση στοιχείων σχολής από την ΑΑΔΕ
 * 
 * @param {number} schoolId ID της σχολής
 */
function updateSchoolInfo(schoolId) {
    // Εμφάνιση φόρτωσης
    const loader = showLoader();
    
    // Αποστολή αιτήματος στον server
    fetch(`${baseUrl}/api/aade_api.php?action=update_school&school_id=${schoolId}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'Σφάλμα κατά την επικοινωνία με την ΑΑΔΕ.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Ανανέωση της σελίδας για εμφάνιση των ενημερωμένων στοιχείων
                showMessage('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showMessage('error', data.error || 'Άγνωστο σφάλμα κατά την ενημέρωση στοιχείων.');
            }
        })
        .catch(error => {
            showMessage('error', error.message);
        })
        .finally(() => {
            // Απόκρυψη φόρτωσης
            hideLoader(loader);
        });
}

/**
 * Συμπλήρωση των πεδίων της φόρμας με τα δεδομένα από την ΑΑΔΕ
 * 
 * @param {object} data Δεδομένα επιχείρησης
 */
function populateFormFields(data) {
    // Συμπλήρωση του ονόματος της σχολής
    const schoolNameField = document.getElementById('school_name');
    if (schoolNameField) {
        schoolNameField.value = data.name;
    }
    
    // Συμπλήρωση της διεύθυνσης
    const addressField = document.getElementById('address');
    if (addressField) {
        addressField.value = data.address.street;
    }
    
    // Συμπλήρωση του αριθμού
    const streetNumberField = document.getElementById('street_number');
    if (streetNumberField) {
        streetNumberField.value = data.address.streetNumber;
    }
    
    // Συμπλήρωση του ταχυδρομικού κώδικα
    const postalCodeField = document.getElementById('postal_code');
    if (postalCodeField) {
        postalCodeField.value = data.address.postalCode;
    }
    
    // Συμπλήρωση της πόλης
    const cityField = document.getElementById('city');
    if (cityField) {
        cityField.value = data.address.city;
    }
    
    // Συμπλήρωση του υπεύθυνου (αν υπάρχει πεδίο)
    const responsiblePersonField = document.getElementById('responsible_person');
    if (responsiblePersonField && responsiblePersonField.value === '') {
        // Προτείνουμε το όνομα της εταιρείας ως υπεύθυνο εάν δεν έχει οριστεί
        responsiblePersonField.value = data.name;
    }
    
    // Ενημέρωση του label της νομικής μορφής αν υπάρχει
    const legalFormLabel = document.getElementById('legal_form_label');
    if (legalFormLabel) {
        legalFormLabel.textContent = data.legalForm || 'Μη διαθέσιμο';
    }
    
    // Αποθήκευση των δεδομένων στο session storage για μελλοντική χρήση
    sessionStorage.setItem('aadeCompanyData', JSON.stringify(data));
    
    // Εμφάνιση επιπλέον πληροφοριών στο UI
    showCompanyInfoPanel(data);
}

/**
 * Εμφάνιση πληροφοριών επιχείρησης σε πάνελ
 * 
 * @param {object} data Δεδομένα επιχείρησης
 */
function showCompanyInfoPanel(data) {
    // Έλεγχος αν υπάρχει ή δημιουργία του πάνελ
    let infoPanel = document.getElementById('aade_info_panel');
    
    if (!infoPanel) {
        infoPanel = document.createElement('div');
        infoPanel.id = 'aade_info_panel';
        infoPanel.className = 'aade-info-panel';
        
        // Προσθήκη στη σελίδα μετά το πεδίο ΑΦΜ
        const taxIdField = document.getElementById('tax_id');
        if (taxIdField && taxIdField.parentNode) {
            taxIdField.parentNode.insertAdjacentElement('afterend', infoPanel);
        }
    }
    
    // Δημιουργία περιεχομένου πάνελ
    let statusClass = data.status.isActive ? 'active-status' : 'inactive-status';
    let statusText = data.status.isActive ? 'Ενεργή Επιχείρηση' : 'Ανενεργή Επιχείρηση';
    
    infoPanel.innerHTML = `
        <div class="aade-header">
            <h4>Στοιχεία από ΑΑΔΕ</h4>
            <span class="status-badge ${statusClass}">${statusText}</span>
        </div>
        <div class="aade-body">
            <p><strong>Επωνυμία:</strong> ${data.name}</p>
            <p><strong>Διεύθυνση:</strong> ${data.address.street} ${data.address.streetNumber}, ${data.address.postalCode} ${data.address.city}</p>
            <p><strong>Νομική Μορφή:</strong> ${data.legalForm || 'Μη διαθέσιμο'}</p>
            <p><strong>Ημ. Έναρξης:</strong> ${formatDate(data.registrationDate)}</p>
            ${data.deactivationDate ? `<p><strong>Ημ. Διακοπής:</strong> ${formatDate(data.deactivationDate)}</p>` : ''}
            ${data.activities && data.activities.length > 0 ? `
                <p><strong>Κύρια Δραστηριότητα:</strong> ${data.activities[0].code} - ${data.activities[0].description}</p>
            ` : ''}
        </div>
    `;
    
    // Εμφάνιση του πάνελ
    infoPanel.style.display = 'block';
}

/**
 * Μορφοποίηση ημερομηνίας σε ελληνική μορφή
 * 
 * @param {string} dateStr Συμβολοσειρά ημερομηνίας
 * @returns {string} Μορφοποιημένη ημερομηνία
 */
function formatDate(dateStr) {
    if (!dateStr) return 'Μη διαθέσιμο';
    
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr; // Επιστροφή ως έχει εάν δεν είναι έγκυρη ημερομηνία
    
    return date.toLocaleDateString('el-GR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Εμφάνιση μηνύματος στο χρήστη
 * 
 * @param {string} type Τύπος μηνύματος ('success', 'error', 'warning', 'info')
 * @param {string} message Κείμενο μηνύματος
 */
function showMessage(type, message) {
    // Έλεγχος αν υπάρχει container μηνυμάτων
    let messagesContainer = document.querySelector('.messages-container');
    
    if (!messagesContainer) {
        messagesContainer = document.createElement('div');
        messagesContainer.className = 'messages-container';
        document.body.insertBefore(messagesContainer, document.body.firstChild);
    }
    
    // Δημιουργία του στοιχείου μηνύματος
    const messageElement = document.createElement('div');
    messageElement.className = `alert alert-${type} alert-dismissible fade show`;
    messageElement.role = 'alert';
    
    messageElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Προσθήκη στο container
    messagesContainer.appendChild(messageElement);
    
    // Αυτόματη απόκρυψη μετά από 5 δευτερόλεπτα
    setTimeout(() => {
        if (messageElement.parentNode) {
            messageElement.remove();
        }
    }, 5000);
}

/**
 * Εμφάνιση σφάλματος στο πεδίο φόρμας
 * 
 * @param {string} fieldId ID του πεδίου
 * @param {string} errorMessage Μήνυμα σφάλματος
 */
function showFieldError(fieldId, errorMessage) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Προσθήκη κλάσης σφάλματος στο πεδίο
    field.classList.add('is-invalid');
    
    // Έλεγχος αν υπάρχει ήδη στοιχείο μηνύματος σφάλματος
    let errorElement = field.nextElementSibling;
    if (!errorElement || !errorElement.classList.contains('invalid-feedback')) {
        errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        field.parentNode.insertBefore(errorElement, field.nextSibling);
    }
    
    errorElement.textContent = errorMessage;
    errorElement.style.display = 'block';
}

/**
 * Καθαρισμός σφάλματος πεδίου
 * 
 * @param {string} fieldId ID του πεδίου
 */
function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Αφαίρεση κλάσης σφάλματος
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    
    // Αφαίρεση μηνύματος σφάλματος
    const errorElement = field.nextElementSibling;
    if (errorElement && errorElement.classList.contains('invalid-feedback')) {
        errorElement.style.display = 'none';
    }
}

/**
 * Εμφάνιση ένδειξης φόρτωσης
 * 
 * @returns {HTMLElement} Το στοιχείο φόρτωσης
 */
function showLoader() {
    const loader = document.createElement('div');
    loader.className = 'loader-overlay';
    loader.innerHTML = '<div class="loader-spinner"><i class="fas fa-circle-notch fa-spin"></i></div>';
    document.body.appendChild(loader);
    return loader;
}

/**
 * Απόκρυψη ένδειξης φόρτωσης
 * 
 * @param {HTMLElement} loader Το στοιχείο φόρτωσης
 */
function hideLoader(loader) {
    if (loader && loader.parentNode) {
        loader.parentNode.removeChild(loader);
    }
}