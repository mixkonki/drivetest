/**
 * DriveTest - Subscription Management JavaScript
 * Κώδικας JavaScript για τη σελίδα διαχείρισης συνδρομών
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Subscription Management JS loaded');
    
    // Ταξινόμηση πινάκων
    const sortableThs = document.querySelectorAll('th.sortable');
    
    sortableThs.forEach(th => {
        th.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const columnIndex = Array.from(this.parentNode.children).indexOf(this);
            const sortDirection = this.classList.contains('sorted-asc') ? 'desc' : 'asc';
            
            // Αφαίρεση προηγούμενων κλάσεων ταξινόμησης
            sortableThs.forEach(header => {
                header.classList.remove('sorted-asc', 'sorted-desc');
            });
            
            this.classList.add(sortDirection === 'asc' ? 'sorted-asc' : 'sorted-desc');
            
            // Ταξινόμηση των γραμμών
            rows.sort((a, b) => {
                const cellA = a.querySelectorAll('td')[columnIndex].textContent.trim();
                const cellB = b.querySelectorAll('td')[columnIndex].textContent.trim();
                
                // Ειδική περίπτωση για αριθμούς (τιμή, μήνες)
                if (!isNaN(parseFloat(cellA)) && !isNaN(parseFloat(cellB))) {
                    return sortDirection === 'asc' 
                        ? parseFloat(cellA) - parseFloat(cellB)
                        : parseFloat(cellB) - parseFloat(cellA);
                }
                
                // Για κείμενο
                return sortDirection === 'asc'
                    ? cellA.localeCompare(cellB, 'el')
                    : cellB.localeCompare(cellA, 'el');
            });
            
            // Αναδιάταξη των γραμμών
            rows.forEach(row => tbody.appendChild(row));
        });
    });
    
    // Διαχείριση της αναζήτησης
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            // Αν το πεδίο αναζήτησης είναι κενό, αποτρέπουμε την υποβολή
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput && searchInput.value.trim() === '') {
                e.preventDefault();
                searchInput.focus();
            }
        });
    }
    
    // Επιβεβαίωση διαγραφής
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Σίγουρα θέλετε να διαγράψετε αυτό το στοιχείο;')) {
                e.preventDefault();
            }
        });
    });
});