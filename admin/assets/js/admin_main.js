/**
 * Βασικές λειτουργίες JavaScript για το admin panel
 * Διαδρομή: /admin/assets/js/admin_main.js
 */
document.addEventListener('DOMContentLoaded', function() {
    // Αυτόματο κλείσιμο των alerts μετά από 5 δευτερόλεπτα
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.no-auto-close)');
        alerts.forEach(alert => {
            if (alert.querySelector('.btn-close')) {
                alert.querySelector('.btn-close').click();
            } else {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            }
        });
    }, 5000);
    
    // Επιβεβαίωση διαγραφής στοιχείων
    setupDeleteConfirmations();
    
    // Ενεργοποίηση των tooltips αν υπάρχει το Bootstrap
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(tooltip => {
            new bootstrap.Tooltip(tooltip);
        });
    }
    
    // Φόρμα φιλτραρίσματος χρηστών (αν υπάρχει)
    const userFilterForm = document.getElementById('user-filter-form');
    if (userFilterForm) {
        userFilterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(userFilterForm);
            
            // Χρήση σχετικής διαδρομής αντί για στατική
            const baseUrl = document.querySelector('base')?.getAttribute('href') || '';
            const apiUrl = baseUrl + '/admin/api/users.php?action=filter';
            
            fetch(apiUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tableBody = document.getElementById('users-table-body');
                    if (tableBody) {
                        tableBody.innerHTML = data.html;
                    }
                } else {
                    showNotification(data.message || 'Προέκυψε ένα σφάλμα', 'danger');
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                showNotification('Προέκυψε ένα σφάλμα επικοινωνίας', 'danger');
            });
        });
    }
});

/**
 * Προσθέτει επιβεβαίωση στις ενέργειες διαγραφής
 */
function setupDeleteConfirmations() {
    const deleteLinks = document.querySelectorAll('a[data-confirm], button[data-confirm]');
    
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const confirmMessage = this.getAttribute('data-confirm') || 'Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτό το στοιχείο;';
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Εμφανίζει μια ειδοποίηση στον χρήστη
 * 
 * @param {string} message - Το μήνυμα της ειδοποίησης
 * @param {string} type - Ο τύπος της ειδοποίησης (success, danger, warning, info)
 */
function showNotification(message, type = 'success') {
    // Δημιουργία του στοιχείου ειδοποίησης
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Προσθήκη στο DOM
    const container = document.querySelector('.admin-container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Αυτόματο κλείσιμο μετά από 5 δευτερόλεπτα
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}