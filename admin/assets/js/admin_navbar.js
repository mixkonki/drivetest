/**
 * JavaScript για το responsive navbar και τα dropdowns στο admin panel
 * Διαδρομή: /admin/assets/js/admin_navbar.js
 */
document.addEventListener('DOMContentLoaded', function() {
    // Toggle για το navbar στις κινητές συσκευές
    const navbarToggler = document.getElementById('navbar-toggler');
    const navbarMenu = document.getElementById('navbar-menu');
    
    if (navbarToggler && navbarMenu) {
        navbarToggler.addEventListener('click', function() {
            navbarMenu.classList.toggle('active');
        });
    }
    
    // Διαχείριση των dropdowns
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.nav-dropdown');
            if (!parent) return;
            
            const menu = parent.querySelector('.dropdown-menu');
            if (!menu) return;
            
            // Κλείσιμο όλων των άλλων dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(dropdownMenu => {
                if (dropdownMenu !== menu) {
                    dropdownMenu.classList.remove('show');
                }
            });
            
            // Toggle του τρέχοντος dropdown
            menu.classList.toggle('show');
        });
    });
    
    // Κλείσιμο των dropdowns όταν κάνουμε κλικ έξω από αυτά
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    // Κλείσιμο του mobile menu όταν κάνουμε κλικ σε ένα link
    const navLinks = document.querySelectorAll('.navbar-menu .nav-link:not(.dropdown-toggle)');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992 && navbarMenu) {
                navbarMenu.classList.remove('active');
            }
        });
    });
    
    // Επισήμανση του ενεργού στοιχείου στο μενού
    highlightActiveMenuItem();
});

/**
 * Επισημαίνει το ενεργό στοιχείο στο μενού πλοήγησης
 */
function highlightActiveMenuItem() {
    // Λήψη του τρέχοντος URL
    const currentUrl = window.location.pathname;
    
    // Εύρεση του στοιχείου μενού που αντιστοιχεί στην τρέχουσα σελίδα
    const menuLinks = document.querySelectorAll('.nav-link');
    menuLinks.forEach(link => {
        // Έλεγχος αν το URL του link ταιριάζει με το τρέχον URL
        const href = link.getAttribute('href');
        if (href && currentUrl.includes(href.split('/').pop())) {
            link.classList.add('active');
            
            // Αν το link είναι σε dropdown, επίσης ενεργοποίηση του parent dropdown
            const parentDropdown = link.closest('.nav-dropdown');
            if (parentDropdown) {
                const dropdownToggle = parentDropdown.querySelector('.dropdown-toggle');
                if (dropdownToggle) {
                    dropdownToggle.classList.add('active');
                }
            }
        }
    });
}