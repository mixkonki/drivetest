/**
 * DriveTest - Auth JavaScript
 * Περιέχει λειτουργίες για τις σελίδες αυθεντικοποίησης (login, register, κτλ.)
 */

document.addEventListener('DOMContentLoaded', function() {
    // Έλεγχος κωδικού σε πραγματικό χρόνο
    initPasswordChecker();
    
    // Εναλλαγή εμφάνισης κωδικού
    initPasswordToggles();
    
    // Επικύρωση φόρμας
    initFormValidation();
});

/**
 * Αρχικοποίηση του ελέγχου κωδικού σε πραγματικό χρόνο
 */
function initPasswordChecker() {
    const passwordInput = document.getElementById('password');
    if (!passwordInput) return;
    
    const lengthHint = document.getElementById('hint-length');
    const uppercaseHint = document.getElementById('hint-uppercase');
    const numberHint = document.getElementById('hint-number');
    const specialHint = document.getElementById('hint-special');
    
    // Έλεγχος αν υπάρχουν τα στοιχεία στο DOM
    if (!lengthHint || !uppercaseHint || !numberHint || !specialHint) return;
    
    // Αρχικός έλεγχος
    checkPassword();
    
    // Έλεγχος κατά την πληκτρολόγηση
    passwordInput.addEventListener('input', checkPassword);
    
    function checkPassword() {
        const password = passwordInput.value;
        
        // Έλεγχος μήκους
        if (password.length >= 8 && password.length <= 16) {
            lengthHint.innerHTML = '✅ 8-16 χαρακτήρες';
        } else {
            lengthHint.innerHTML = '❌ 8-16 χαρακτήρες';
        }
        
        // Έλεγχος για κεφαλαίο γράμμα
        if (/[A-Z]/.test(password)) {
            uppercaseHint.innerHTML = '✅ 1 κεφαλαίο γράμμα';
        } else {
            uppercaseHint.innerHTML = '❌ 1 κεφαλαίο γράμμα';
        }
        
        // Έλεγχος για αριθμό
        if (/\d/.test(password)) {
            numberHint.innerHTML = '✅ 1 αριθμός';
        } else {
            numberHint.innerHTML = '❌ 1 αριθμός';
        }
        
        // Έλεγχος για ειδικό χαρακτήρα
        if (/[\W_]/.test(password)) {
            specialHint.innerHTML = '✅ 1 ειδικός χαρακτήρας';
        } else {
            specialHint.innerHTML = '❌ 1 ειδικός χαρακτήρας';
        }
    }
}

/**
 * Αρχικοποίηση της εναλλαγής εμφάνισης κωδικού
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
 * Αρχικοποίηση της επικύρωσης φόρμας
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!validateForm(this)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });
    
    function validateForm(form) {
        let isValid = true;
        
        // Έλεγχος των υποχρεωτικών πεδίων
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                markFieldAsInvalid(field, 'Το πεδίο είναι υποχρεωτικό');
                isValid = false;
            } else {
                removeFieldError(field);
            }
        });
        
        // Έλεγχος email
        const emailField = form.querySelector('input[type="email"]');
        if (emailField && emailField.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailField.value.trim())) {
                markFieldAsInvalid(emailField, 'Παρακαλώ εισάγετε ένα έγκυρο email');
                isValid = false;
            }
        }
        
        // Έλεγχος αν οι κωδικοί ταιριάζουν
        const passwordField = form.querySelector('input[name="password"]');
        const confirmPasswordField = form.querySelector('input[name="confirm_password"]');
        
        if (passwordField && confirmPasswordField && 
            passwordField.value.trim() && confirmPasswordField.value.trim()) {
            if (passwordField.value !== confirmPasswordField.value) {
                markFieldAsInvalid(confirmPasswordField, 'Οι κωδικοί δεν ταιριάζουν');
                isValid = false;
            }
        }
        
        // Έλεγχος κριτηρίων ασφαλείας κωδικού
        if (passwordField && passwordField.value.trim()) {
            const password = passwordField.value;
            const meetsLength = password.length >= 8 && password.length <= 16;
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[\W_]/.test(password);
            
            if (!(meetsLength && hasUppercase && hasNumber && hasSpecial)) {
                markFieldAsInvalid(passwordField, 'Ο κωδικός δεν πληροί τα κριτήρια ασφαλείας');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    function markFieldAsInvalid(field, message) {
        field.classList.add('is-invalid');
        
        // Έλεγχος αν υπάρχει ήδη μήνυμα σφάλματος
        let feedback = field.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        
        feedback.textContent = message;
    }
    
    function removeFieldError(field) {
        field.classList.remove('is-invalid');
        
        // Αφαίρεση του μηνύματος σφάλματος αν υπάρχει
        const feedback = field.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = '';
        }
    }
}