/**
 * DriveTest - Dashboard JavaScript
 * Περιέχει λειτουργίες για τις σελίδες dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Διαγράμματα
    initCharts();
    
    // Dropdowns και άλλα UI components
    initUIComponents();
    
    // Ειδοποιήσεις
    initNotifications();
});

/**
 * Αρχικοποίηση των διαγραμμάτων
 */
function initCharts() {
    // Έλεγχος αν υπάρχει το Chart.js
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded');
        return;
    }
    
    // Performance Chart (αν υπάρχει)
    const performanceChartEl = document.getElementById('performanceChart');
    if (performanceChartEl) {
        const ctx = performanceChartEl.getContext('2d');
        
        // Τα δεδομένα θα εισαχθούν από τη σελίδα
        // Βλέπε το inline script στο τέλος του dashboard.php
    }
    
    // Progress Chart (αν υπάρχει)
    const progressChartEl = document.getElementById('progressChart');
    if (progressChartEl) {
        const ctx = progressChartEl.getContext('2d');
        
        // Παράδειγμα δεδομένων για το διάγραμμα προόδου
        const categories = progressChartEl.getAttribute('data-categories');
        const progress = progressChartEl.getAttribute('data-progress');
        
        if (categories && progress) {
            const categoriesArray = categories.split(',');
            const progressArray = progress.split(',').map(Number);
            
            const progressChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: categoriesArray,
                    datasets: [{
                        label: 'Πρόοδος (%)',
                        data: progressArray,
                        backgroundColor: 'rgba(170, 54, 54, 0.7)',
                        borderColor: 'rgba(170, 54, 54, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }
    
    // Activity Chart (αν υπάρχει)
    const activityChartEl = document.getElementById('activityChart');
    if (activityChartEl) {
        const ctx = activityChartEl.getContext('2d');
        
        // Παράδειγμα δεδομένων για το διάγραμμα δραστηριότητας
        const dates = activityChartEl.getAttribute('data-dates');
        const activity = activityChartEl.getAttribute('data-activity');
        
        if (dates && activity) {
            const datesArray = dates.split(',');
            const activityArray = activity.split(',').map(Number);
            
            const activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: datesArray,
                    datasets: [{
                        label: 'Τεστ ανά ημέρα',
                        data: activityArray,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }
                }
            });
        }
    }
}

/**
 * Αρχικοποίηση των UI components
 */
function initUIComponents() {
    // Tooltips
    const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-title') || this.getAttribute('title');
            
            if (!tooltipText) return;
            
            // Δημιουργία του tooltip element
            const tooltipEl = document.createElement('div');
            tooltipEl.className = 'tooltip-text';
            tooltipEl.textContent = tooltipText;
            
            // Προσθήκη του tooltip στο DOM
            this.appendChild(tooltipEl);
            
            // Εμφάνιση του tooltip
            setTimeout(() => {
                tooltipEl.style.opacity = '1';
            }, 10);
            
            // Αφαίρεση του title attribute για αποφυγή του default tooltip
            const title = this.getAttribute('title');
            if (title) {
                this.setAttribute('data-original-title', title);
                this.removeAttribute('title');
            }
        });
        
        tooltip.addEventListener('mouseleave', function() {
            // Αφαίρεση του tooltip
            const tooltipEl = this.querySelector('.tooltip-text');
            if (tooltipEl) {
                tooltipEl.style.opacity = '0';
                setTimeout(() => {
                    tooltipEl.remove();
                }, 300);
            }
            
            // Επαναφορά του title attribute
            const originalTitle = this.getAttribute('data-original-title');
            if (originalTitle) {
                this.setAttribute('title', originalTitle);
                this.removeAttribute('data-original-title');
            }
        });
    });
    
    // Dropdowns
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                menu.classList.toggle('show');
                
                // Κλείσιμο άλλων ανοιχτών dropdowns
                dropdowns.forEach(item => {
                    if (item !== dropdown) {
                        const otherMenu = item.nextElementSibling;
                        if (otherMenu && otherMenu.classList.contains('dropdown-menu') && otherMenu.classList.contains('show')) {
                            otherMenu.classList.remove('show');
                        }
                    }
                });
            }
        });
    });
    
    // Κλείσιμο dropdowns όταν κάνουμε κλικ έξω από αυτά
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
    
    // Tabs
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Αφαίρεση του active class από όλα τα tabs
            tabs.forEach(t => {
                t.classList.remove('active');
            });
            
            // Προσθήκη του active class στο επιλεγμένο tab
            this.classList.add('active');
            
            // Εμφάνιση του αντίστοιχου περιεχομένου
            const tabId = this.getAttribute('data-tab');
            const tabContents = document.querySelectorAll('.tab-pane');
            
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            const activeContent = document.getElementById(tabId);
            if (activeContent) {
                activeContent.classList.add('active');
            }
        });
    });
}

/**
 * Αρχικοποίηση των ειδοποιήσεων
 */
function initNotifications() {
    // Ανάκτηση ειδοποιήσεων από το server (αν υπάρχει endpoint)
    const notificationsElement = document.getElementById('notifications-container');
    if (!notificationsElement) return;
    
    const userId = notificationsElement.getAttribute('data-user-id');
    if (!userId) return;
    
    // Ενημέρωση των ειδοποιήσεων (προαιρετικό)
    // fetchNotifications(userId);
    
    // Διαβάστηκε μια ειδοποίηση
    const markAsReadButtons = document.querySelectorAll('.mark-as-read');
    markAsReadButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const notificationId = this.getAttribute('data-notification-id');
            if (!notificationId) return;
            
            // Ενημέρωση του server (προαιρετικό)
            // markNotificationAsRead(notificationId);
            
            // Ανανέωση του UI
            const notification = this.closest('.notification-item');
            if (notification) {
                notification.classList.add('read');
            }
        });
    });
}

/**
 * Ανάκτηση ειδοποιήσεων από το server
 */
function fetchNotifications(userId) {
    fetch(`${BASE_URL}/api/notifications.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Ενημέρωση του UI με τις νέες ειδοποιήσεις
                updateNotificationsUI(data.notifications);
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

/**
 * Ενημέρωση του UI με τις νέες ειδοποιήσεις
 */
function updateNotificationsUI(notifications) {
    const notificationsContainer = document.getElementById('notifications-container');
    if (!notificationsContainer) return;
    
    // Καθαρισμός του container
    notificationsContainer.innerHTML = '';
    
    if (notifications.length === 0) {
        notificationsContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>Δεν έχετε ειδοποιήσεις.</p>
            </div>
        `;
        return;
    }
    
    // Προσθήκη των νέων ειδοποιήσεων
    notifications.forEach(notification => {
        const notificationElement = document.createElement('div');
        notificationElement.className = `notification-item ${notification.read ? 'read' : ''}`;
        
        notificationElement.innerHTML = `
            <div class="notification-icon">
                <i class="fas ${notification.icon || 'fa-bell'}"></i>
            </div>
            <div class="notification-info">
                <div class="notification-title">${notification.title}</div>
                <div class="notification-meta">${notification.date}</div>
                <div class="notification-content">${notification.content}</div>
            </div>
            <div class="notification-action">
                <button class="btn btn-sm btn-link mark-as-read" data-notification-id="${notification.id}">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        `;
        
        notificationsContainer.appendChild(notificationElement);
    });
    
    // Επανασύνδεση των event listeners
    initNotifications();
}