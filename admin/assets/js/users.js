/**
 * DriveTest - Admin Users JavaScript
 * JavaScript για τη σελίδα διαχείρισης χρηστών
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Users.js loaded successfully');
    
    // Αναφορές DOM
    const userFilterForm = document.getElementById('user-filter-form');
    const usersTableBody = document.getElementById('users-table-body');
    const tableHeaders = document.querySelectorAll('.users-table th.sortable');
    
    // Handlers για ταξινόμηση
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            let order = this.getAttribute('data-order') === 'asc' ? 'desc' : 'asc';
            
            // Αφαιρεί προηγούμενα data-order
            tableHeaders.forEach(th => th.removeAttribute('data-order'));
            
            // Προσθέτει το νέο data-order
            this.setAttribute('data-order', order);
            
            sortTable(column, order);
        });
    });
    
    // Λειτουργία ταξινόμησης με AJAX
    function sortTable(column, order) {
        // Ανανέωση του URL για να διατηρηθεί η ταξινόμηση στην επαναφόρτωση
        const url = new URL(window.location);
        url.searchParams.set('sort', column);
        url.searchParams.set('order', order);
        history.pushState({}, '', url);
        
        // Αποστολή αιτήματος AJAX
        fetch(`${getBaseUrl()}/admin/api/users.php?action=sort&column=${column}&order=${order}`, {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                usersTableBody.innerHTML = data.html;
                initializeTooltips(); // Επανεκκίνηση tooltips μετά την ενημέρωση του DOM
            } else {
                console.error('Error sorting data:', data.message);
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
        });
    }
    
    // Χειρισμός φόρμας αναζήτησης
    if (userFilterForm) {
        userFilterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Εμφάνιση spinner ή κάποιου στοιχείου φόρτωσης
            usersTableBody.innerHTML = '<tr><td colspan="9" class="loading-data">Φόρτωση δεδομένων...</td></tr>';
            
            fetch(`${getBaseUrl()}/admin/api/users.php?action=filter`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    usersTableBody.innerHTML = data.html;
                    initializeTooltips(); // Επανεκκίνηση tooltips μετά την ενημέρωση του DOM
                } else {
                    usersTableBody.innerHTML = `<tr><td colspan="9" class="no-results">${data.message || 'Δεν βρέθηκαν αποτελέσματα.'}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                usersTableBody.innerHTML = '<tr><td colspan="9" class="error-message">Σφάλμα κατά την ανάκτηση δεδομένων.</td></tr>';
            });
        });
    }
    
    // Αρχικοποίηση tooltips
    function initializeTooltips() {
        // Μπορεί να προσθέσετε εδώ κώδικα για tooltips αν χρησιμοποιείτε κάποια βιβλιοθήκη
        // Για το απλό HTML tooltip που έχετε ορίσει, δεν χρειάζεται κάποια ιδιαίτερη αρχικοποίηση
    }
    
    // Βοηθητική συνάρτηση για την εύρεση του Base URL
    function getBaseUrl() {
        // Προσπαθήστε να πάρετε το base URL από κάποιο μεταδεδομένο της σελίδας
        const baseElement = document.querySelector('base');
        if (baseElement) return baseElement.href;
        
        // Δεύτερη προσπάθεια: από link ή script tags
        const scriptTags = document.querySelectorAll('script[src]');
        for (let i = 0; i < scriptTags.length; i++) {
            const src = scriptTags[i].getAttribute('src');
            if (src.includes('/admin/assets/js/')) {
                return src.split('/admin/assets/js/')[0];
            }
        }
        
        // Fallback στο τρέχον domain (λιγότερο αξιόπιστο)
        return window.location.origin;
    }
    
    // Κλήση αρχικοποίησης
    initializeTooltips();
});