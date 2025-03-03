/// Password toggle
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const toggle = input.nextElementSibling;
    
    // Δημιουργία των απόλυτων διαδρομών
    const baseUrl = window.location.origin + '/drivetest';
    
    if (input.type === "password") {
        input.type = "text";
        toggle.querySelector('img').src = baseUrl + "/assets/images/eye_slash.png";
    } else {
        input.type = "password";
        toggle.querySelector('img').src = baseUrl + "/assets/images/eye.png";
    }
}

// Password validation
const passwordInput = document.querySelector('input[name="password"]');
const hints = {
    length: document.getElementById('hint-length'),
    uppercase: document.getElementById('hint-uppercase'),
    number: document.getElementById('hint-number'),
    special: document.getElementById('hint-special')
};

if (passwordInput) { // Προστασία από null errors
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        hints.length.textContent = password.length >= 8 && password.length <= 16 ? "✅ 8-16 χαρακτήρες" : "❌ 8-16 χαρακτήρες";
        hints.uppercase.textContent = /[A-Z]/.test(password) ? "✅ 1 κεφαλαίο γράμμα" : "❌ 1 κεφαλαίο γράμμα";
        hints.number.textContent = /\d/.test(password) ? "✅ 1 αριθμός" : "❌ 1 αριθμός";
        hints.special.textContent = /[\W_]/.test(password) ? "✅ 1 ειδικός χαρακτήρας" : "❌ 1 ειδικός χαρακτήρας";
    });
}

// Form validation with Bootstrap
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
})();

// Placeholder for Google Login (needs Google OAuth setup)
function googleLogin() {
    alert("Σύνδεση με Google δεν έχει ρυθμιστεί ακόμα. Χρειάζεται Google Client ID και OAuth setup.");
    // Θα προσθέσουμε τον κώδικα για Google Sign-In API εδώ, όταν έχουμε τα στοιχεία.
}