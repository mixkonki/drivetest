/**
 * DriveTest - Admin Dashboard JavaScript
 * Ειδικές λειτουργίες για το dashboard του admin panel
 * Διαδρομή: /admin/assets/js/dashboard.js
 */
document.addEventListener('DOMContentLoaded', function() {
    // Αρχικοποίηση γραφημάτων αν χρειάζεται
    initDashboardCharts();
    
    // Animation για τις κάρτες στατιστικών
    animateStatsCards();
    
    // Αναζήτηση στις γρήγορες ενέργειες (αν προστεθεί αργότερα)
    setupQuickLinksSearch();
    
    // Έλεγχος αν υπάρχει η φόρμα user-filter-form πριν προσθέσουμε event listeners
    const form = document.getElementById('user-filter-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            
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
                    console.error('Σφάλμα:', data.message);
                }
            })
            .catch(error => console.error('AJAX Error:', error));
        });
    }
});

/**
 * Αρχικοποίηση γραφημάτων στο dashboard
 */
function initDashboardCharts() {
    // Έλεγχος αν υπάρχει η βιβλιοθήκη Chart.js
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js δεν είναι διαθέσιμο. Τα γραφήματα δεν θα εμφανιστούν.');
        return;
    }
    
    // Έλεγχος αν υπάρχει το canvas για τα γραφήματα
    const userStatsCanvas = document.getElementById('userStatsChart');
    if (userStatsCanvas) {
        // Παράδειγμα: Δημιουργία γραφήματος χρηστών
        new Chart(userStatsCanvas, {
            type: 'bar',
            data: {
                labels: ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαϊ', 'Ιουν'],
                datasets: [{
                    label: 'Νέοι Χρήστες',
                    data: [12, 19, 3, 5, 2, 3],
                    backgroundColor: 'rgba(170, 54, 54, 0.6)',
                    borderColor: 'rgba(170, 54, 54, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,  // Επιτρέπει το προσαρμοστικό ύψος
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end'
                    },
                    title: {
                        display: false
                    }
                }
            }
        });
    }
}

/**
 * Animation για την εμφάνιση των καρτών στατιστικών
 */
function animateStatsCards() {
    const statsCards = document.querySelectorAll('.stats-card');
    
    // Προσθήκη animation για κάθε κάρτα με καθυστέρηση
    statsCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * index);
    });
}

/**
 * Ρύθμιση αναζήτησης στις γρήγορες ενέργειες
 */
function setupQuickLinksSearch() {
    const searchInput = document.getElementById('quickLinksSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const quickLinks = document.querySelectorAll('.quick-link');
        
        quickLinks.forEach(link => {
            const linkText = link.querySelector('.quick-link-label').textContent.toLowerCase();
            if (linkText.includes(searchTerm)) {
                link.style.display = 'flex';
            } else {
                link.style.display = 'none';
            }
        });
    });
}