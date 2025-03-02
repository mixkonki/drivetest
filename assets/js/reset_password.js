// reset_password.js
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const toggle = input.nextElementSibling;
    if (input.type === "password") {
        input.type = "text";
        toggle.querySelector('img').src = "<?= BASE_URL ?>/assets/images/eye_slash.png";
    } else {
        input.type = "password";
        toggle.querySelector('img').src = "<?= BASE_URL ?>/assets/images/eye.png";
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

passwordInput.addEventListener('input', function() {
    const password = this.value;
    hints.length.textContent = password.length >= 8 && password.length <= 16 ? "✅ 8-16 χαρακτήρες" : "❌ 8-16 χαρακτήρες";
    hints.uppercase.textContent = /[A-Z]/.test(password) ? "✅ 1 κεφαλαίο γράμμα" : "❌ 1 κεφαλαίο γράμμα";
    hints.number.textContent = /\d/.test(password) ? "✅ 1 αριθμός" : "❌ 1 αριθμός";
    hints.special.textContent = /[\W_]/.test(password) ? "✅ 1 ειδικός χαρακτήρας" : "❌ 1 ειδικός χαρακτήρας";
});