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
    
    // Εμφάνιση debug πληροφοριών
    console.log('User filter form found:', userFilterForm !== null);
    console.log('Users table body found:', usersTableBody !== null);
    console.log('Sortable headers found:', tableHeaders.length);
    
    // Καταγραφή των διαθέσιμων στηλών ταξινόμησης
    tableHeaders.forEach(header => {
        console.log('Sortable header:', header.textContent.trim(), 'data-sort:', header.getAttribute('data-sort'));
    });
    
    // Handler για ταξινόμηση με AJAX
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            if (!column) {
                console.error('Missing data-sort attribute on header:', this.textContent);
                return;
            }
            
            // Προσδιορισμός της διεύθυνσης ταξινόμησης
            let order = 'asc';
            if (this.classList.contains('sort-asc')) {
                order = 'desc';
            }
            
            console.log('Sorting by', column, 'in', order, 'order');
            
            // Αφαίρεση προηγούμενων κλάσεων ταξινόμησης
            tableHeaders.forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
                // Επαναφορά του κειμένου χωρίς βέλη
                if (th.getAttribute('data-original-text')) {
                    th.textContent = th.getAttribute('data-original-text');
                }
            });
            
            // Προσθήκη της κατάλληλης κλάσης στην τρέχουσα στήλη
            this.classList.add(order === 'asc' ? 'sort-asc' : 'sort-desc');
            
            // Προσθήκη του βέλους ένδειξης
            const arrow = order === 'asc' ? ' ↑' : ' ↓';
            if (!this.textContent.includes('↑') && !this.textContent.includes('↓')) {
                this.setAttribute('data-original-text', this.textContent);
                this.textContent = this.textContent + arrow;
            } else {
                const originalText = this.getAttribute('data-original-text') || this.textContent.replace(/[↑↓]/g, '').trim();
                this.textContent = originalText + arrow;
            }
            
            // Ανανέωση του URL για να διατηρηθεί η ταξινόμηση στην επαναφόρτωση
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('order', order);
            history.pushState({}, '', url);
            
            // Αποστολή αιτήματος AJAX για ταξινόμηση
            usersTableBody.innerHTML = '<tr><td colspan="9" class="loading-data">Φόρτωση δεδομένων...</td></tr>';
            
            fetch(`${getBaseUrl()}/admin/api/users.php?action=sort&column=${column}&order=${order}`, {
                method: 'GET'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    usersTableBody.innerHTML = data.html;
                    initializeTooltips(); // Επανεκκίνηση tooltips μετά την ενημέρωση του DOM
                } else {
                    usersTableBody.innerHTML = `<tr><td colspan="9" class="error-message">${data.message || 'Σφάλμα κατά την ταξινόμηση.'}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                usersTableBody.innerHTML = '<tr><td colspan="9" class="error-message">Σφάλμα επικοινωνίας με τον server.</td></tr>';
            });
        });
    });
    
    // Εφαρμογή στυλ στην τρέχουσα ταξινομημένη στήλη κατά τη φόρτωση
    function applyCurrentSortStyle() {
        const urlParams = new URLSearchParams(window.location.search);
        const currentSort = urlParams.get('sort');
        const currentOrder = urlParams.get('order');
        
        if (currentSort) {
            tableHeaders.forEach(header => {
                const sortColumn = header.getAttribute('data-sort');
                if (sortColumn === currentSort) {
                    header.classList.add(currentOrder === 'asc' ? 'sort-asc' : 'sort-desc');
                    
                    // Προσθήκη του βέλους ένδειξης
                    const arrow = currentOrder === 'asc' ? ' ↑' : ' ↓';
                    header.setAttribute('data-original-text', header.textContent);
                    header.textContent = header.textContent + arrow;
                }
            });
        }
    }
    
    // Χειρισμός φόρμας αναζήτησης με AJAX
    if (userFilterForm) {
        userFilterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Έλεγχος αν υπάρχουν παράμετροι ταξινόμησης στο URL
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentOrder = urlParams.get('order');
            
            // Εμφάνιση spinner ή κάποιου στοιχείου φόρτωσης
            usersTableBody.innerHTML = '<tr><td colspan="9" class="loading-data">Φόρτωση δεδομένων...</td></tr>';
            
            // Προσθήκη των παραμέτρων ταξινόμησης στο URL του αιτήματος AJAX
            let apiUrl = `${getBaseUrl()}/admin/api/users.php?action=filter`;
            if (currentSort) {
                apiUrl += `&sort=${currentSort}&order=${currentOrder || 'asc'}`;
            }
            
            fetch(apiUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
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
                usersTableBody.innerHTML = '<tr><td colspan="9" class="error-message">Σφάλμα επικοινωνίας με τον server.</td></tr>';
            });
        });
    }
    
    // Αρχικοποίηση tooltips
    function initializeTooltips() {
        console.log('Initializing tooltips');
        
        // Προσθήκη event listeners για hover στα user-info-tooltip elements
        const tooltips = document.querySelectorAll('.user-info-tooltip');
        tooltips.forEach(tooltip => {
            const content = tooltip.querySelector('.tooltip-content');
            if (!content) return;
            
            tooltip.addEventListener('mouseenter', function() {
                content.style.visibility = 'visible';
                content.style.opacity = '1';
            });
            
            tooltip.addEventListener('mouseleave', function() {
                content.style.visibility = 'hidden';
                content.style.opacity = '0';
            });
        });
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
    applyCurrentSortStyle();
    initializeTooltips();
});