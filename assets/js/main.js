/**
 * DriveTest - Main JavaScript
 * Περιέχει τις βασικές λειτουργίες JavaScript που χρησιμοποιούνται σε όλη την εφαρμογή
 */

document.addEventListener('DOMContentLoaded', function() {
    // Ενεργοποίηση όλων των tooltips
    initTooltips();
    
    // Ενεργοποίηση των dropdowns
    initDropdowns();
    
    // Ενεργοποίηση των tabs
    initTabs();
    
    // Ενεργοποίηση των alerts
    initAlerts();
    
    // Responsive menu
    initResponsiveMenu();
    
    // Ενεργοποίηση των timeago elements
    initTimeAgo();
    
    // Password visibility toggle
    initPasswordToggles();
});

/**
 * Ενεργοποίηση των tooltips
 */
function initTooltips() {
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
}

/**
 * Ενεργοποίηση των dropdowns
 */
function initDropdowns() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const dropdown = this.nextElementSibling;
            
            if (!dropdown) return;
            
            // Toggle του dropdown
            dropdown.classList.toggle('show');
            
            // Κλείσιμο άλλων ανοιχτών dropdowns
            dropdownToggles.forEach(otherToggle => {
                if (otherToggle !== toggle) {
                    const otherDropdown = otherToggle.nextElementSibling;
                    if (otherDropdown && otherDropdown.classList.contains('show')) {
                        otherDropdown.classList.remove('show');
                    }
                }
            });
        });
    });
    
    // Κλείσιμο των dropdowns όταν κάνουμε click έξω από αυτά
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
}

/**
 * Ενεργοποίηση των tabs
 */
function initTabs() {
    const tabs = document.querySelectorAll('.nav-link[data-tab]');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetTabId = this.getAttribute('data-tab');
            
            if (!targetTabId) return;
            
            // Αφαίρεση της κλάσης active από όλα τα tabs
            const allTabs = document.querySelectorAll('.nav-link[data-tab]');
            allTabs.forEach(t => t.classList.remove('active'));
            
            // Προσθήκη της κλάσης active στο επιλεγμένο tab
            this.classList.add('active');
            
            // Απόκρυψη όλων των tab panes
            const allTabPanes = document.querySelectorAll('.tab-pane');
            allTabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Εμφάνιση του επιλεγμένου tab pane
            const targetTabPane = document.getElementById(targetTabId);
            if (targetTabPane) {
                targetTabPane.classList.add('active');
            }
        });
    });
}

/**
 * Ενεργοποίηση των alerts
 */
function initAlerts() {
    // Κλείσιμο των alerts όταν πατάμε το κουμπί close
    const closeButtons = document.querySelectorAll('.alert .close-alert');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const alert = this.closest('.alert');
            
            if (!alert) return;
            
            // Animation για το κλείσιμο
            alert.style.opacity = '0';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        });
    });
    
    // Αυτόματο κλείσιμο των alerts μετά από 5 δευτερόλεπτα
    const autoCloseAlerts = document.querySelectorAll('.alert:not(.alert-persistent)');
    
    setTimeout(() => {
        autoCloseAlerts.forEach(alert => {
            alert.style.opacity = '0';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        });
    }, 5000);
}

/**
 * Ενεργοποίηση του responsive menu
 */
function initResponsiveMenu() {
    const navbarToggler = document.getElementById('navbar-toggler');
    const navbarMenu = document.getElementById('navbar-menu');
    
    if (!navbarToggler || !navbarMenu) return;
    
    navbarToggler.addEventListener('click', function() {
        navbarMenu.classList.toggle('active');
    });
    
    // Κλείσιμο του menu όταν πατάμε σε ένα link
    const navLinks = navbarMenu.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                navbarMenu.classList.remove('active');
            }
        });
    });
}

/**
 * Ενεργοποίηση των timeago elements
 */
function initTimeAgo() {
    const timeElements = document.querySelectorAll('.timeago');
    
    timeElements.forEach(element => {
        const timestamp = element.getAttribute('data-timestamp');
        
        if (!timestamp) return;
        
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        let timeAgo;
        
        if (diffInSeconds < 60) {
            timeAgo = 'μόλις τώρα';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            timeAgo = minutes + ' λεπτ' + (minutes === 1 ? 'ό' : 'ά') + ' πριν';
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            timeAgo = hours + ' ώρ' + (hours === 1 ? 'α' : 'ες') + ' πριν';
        } else if (diffInSeconds < 604800) {
            const days = Math.floor(diffInSeconds / 86400);
            timeAgo = days + ' μέρ' + (days === 1 ? 'α' : 'ες') + ' πριν';
        } else if (diffInSeconds < 2592000) {
            const weeks = Math.floor(diffInSeconds / 604800);
            timeAgo = weeks + ' εβδομάδ' + (weeks === 1 ? 'α' : 'ες') + ' πριν';
        } else if (diffInSeconds < 31536000) {
            const months = Math.floor(diffInSeconds / 2592000);
            timeAgo = months + ' μήν' + (months === 1 ? 'ας' : 'ες') + ' πριν';
        } else {
            const years = Math.floor(diffInSeconds / 31536000);
            timeAgo = years + ' χρόν' + (years === 1 ? 'ος' : 'ια') + ' πριν';
        }
        
        element.textContent = timeAgo;
    });
}

/**
 * Ενεργοποίηση των password toggles
 */
function initPasswordToggles() {
    const passwordToggles = document.querySelectorAll('.password-toggle');
    
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const inputField = this.previousElementSibling;
            
            if (!inputField || inputField.tagName !== 'INPUT') return;
            
            // Toggle του τύπου του input
            if (inputField.type === 'password') {
                inputField.type = 'text';
                
                // Αλλαγή του εικονιδίου εφόσον υπάρχει
                const icon = this.querySelector('img, i');
                if (icon) {
                    if (icon.tagName === 'IMG') {
                        const src = icon.src;
                        if (src.includes('eye.png')) {
                            icon.src = src.replace('eye.png', 'eye_slash.png');
                        }
                    } else if (icon.classList.contains('fa-eye')) {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    }
                }
            } else {
                inputField.type = 'password';
                
                // Αλλαγή του εικονιδίου εφόσον υπάρχει
                const icon = this.querySelector('img, i');
                if (icon) {
                    if (icon.tagName === 'IMG') {
                        const src = icon.src;
                        if (src.includes('eye_slash.png')) {
                            icon.src = src.replace('eye_slash.png', 'eye.png');
                        }
                    } else if (icon.classList.contains('fa-eye-slash')) {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            }
        });
    });
}

/**
 * Προσθήκη CSS κλάσης στο DOM
 */
function addClass(element, className) {
    if (element.classList) {
        element.classList.add(className);
    } else {
        element.className += ' ' + className;
    }
}

/**
 * Αφαίρεση CSS κλάσης από το DOM
 */
function removeClass(element, className) {
    if (element.classList) {
        element.classList.remove(className);
    } else {
        element.className = element.className.replace(new RegExp('(^|\\b)' + className.split(' ').join('|') + '(\\b|$)', 'gi'), ' ');
    }
}

/**
 * Έλεγχος αν ένα element έχει μια συγκεκριμένη κλάση
 */
function hasClass(element, className) {
    if (element.classList) {
        return element.classList.contains(className);
    } else {
        return new RegExp('(^| )' + className + '( |$)', 'gi').test(element.className);
    }
}

/**
 * Υποστήριξη για AJAX
 */
function ajax(options) {
    const xhr = new XMLHttpRequest();
    
    xhr.open(options.method || 'GET', options.url, true);
    
    if (options.headers) {
        Object.keys(options.headers).forEach(key => {
            xhr.setRequestHeader(key, options.headers[key]);
        });
    }
    
    // Default content type
    if (!options.headers || !options.headers['Content-Type']) {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            if (options.success) {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (e) {
                    response = xhr.responseText;
                }
                options.success(response, xhr.status, xhr);
            }
        } else {
            if (options.error) {
                options.error(xhr.responseText, xhr.status, xhr);
            }
        }
    };
    
    xhr.onerror = function() {
        if (options.error) {
            options.error(xhr.responseText, xhr.status, xhr);
        }
    };
    
    xhr.send(options.data);
    
    return xhr;
}

/**
 * Υποστήριξη για φυλλομετρητές που δεν έχουν το closest()
 */
if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
}

if (!Element.prototype.closest) {
    Element.prototype.closest = function(s) {
        let el = this;
        
        do {
            if (el.matches(s)) return el;
            el = el.parentElement || el.parentNode;
        } while (el !== null && el.nodeType === 1);
        
        return null;
    };
}