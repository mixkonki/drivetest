/**
 * DriveTest - Duration Form JavaScript
 * Κώδικας JavaScript για τη διαχείριση της φόρμας διάρκειας συνδρομής
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Duration Form JS loaded');
    
    // Αναφορές στα στοιχεία του DOM
    const monthsSlider = document.getElementById('months-slider');
    const monthsInput = document.getElementById('months');
    const durationText = document.getElementById('duration-text');
    const durationUnit = document.querySelector('.duration-unit');
    
    // Αρχικοποίηση των πεδίων
    updateDurationText(monthsInput.value);
    
    // Συγχρονισμός του slider με το input πεδίο
    if (monthsSlider && monthsInput) {
        // Ενημέρωση του input όταν αλλάζει το slider
        monthsSlider.addEventListener('input', function() {
            monthsInput.value = this.value;
            updateDurationText(this.value);
        });
        
        // Ενημέρωση του slider όταν αλλάζει το input
        monthsInput.addEventListener('input', function() {
            // Επιβεβαίωση ότι η τιμή είναι εντός των ορίων
            let value = parseInt(this.value);
            if (isNaN(value)) value = 1;
            if (value < 1) value = 1;
            if (value > 12) value = 12;
            
            // Ενημέρωση των πεδίων
            this.value = value;
            monthsSlider.value = value;
            updateDurationText(value);
        });
    }
    
    // Προσθήκη κλάσης ενεργής κατάστασης στο slider
    if (monthsSlider) {
        // Ενημέρωση του progress του slider
        updateSliderProgress(monthsSlider);
        
        // Ενημέρωση του progress όταν αλλάζει η τιμή
        monthsSlider.addEventListener('input', function() {
            updateSliderProgress(this);
        });
    }
    
    // Επικύρωση της φόρμας πριν την υποβολή
    const adminForm = document.querySelector('.admin-form');
    if (adminForm) {
        adminForm.addEventListener('submit', function(e) {
            const months = parseInt(monthsInput.value);
            
            if (isNaN(months) || months < 1 || months > 12) {
                e.preventDefault();
                alert('Παρακαλώ εισάγετε έγκυρη διάρκεια (1-12 μήνες)');
            }
        });
    }
    
    // Συνάρτηση για την ενημέρωση του κειμένου διάρκειας
    function updateDurationText(months) {
        if (!durationText) return;
        
        months = parseInt(months);
        let text = '';
        
        if (months === 1) {
            text = '1 μήνας';
            if (durationUnit) durationUnit.textContent = 'μήνας';
        } else {
            text = months + ' μήνες';
            if (durationUnit) durationUnit.textContent = 'μήνες';
        }
        
        durationText.textContent = text;
        
        // Προσθήκη περιγραφής διάρκειας
        const durationDescription = document.getElementById('duration-description');
        if (durationDescription) {
            let description = 'Διάρκεια: <strong>' + text + '</strong>';
            
            if (months === 1) {
                description += ' <span class="duration-tag">Μηνιαία</span>';
            } else if (months === 3) {
                description += ' <span class="duration-tag">Τριμηνιαία</span>';
            } else if (months === 6) {
                description += ' <span class="duration-tag">Εξαμηνιαία</span>';
            } else if (months === 12) {
                description += ' <span class="duration-tag">Ετήσια</span>';
            }
            
            durationDescription.innerHTML = description;
        }
    }
    
    // Συνάρτηση για την ενημέρωση του progress του slider
    function updateSliderProgress(slider) {
        if (!slider) return;
        
        const value = slider.value;
        const min = slider.min || 1;
        const max = slider.max || 12;
        const percent = ((value - min) / (max - min)) * 100;
        
        // Χρωματισμός του μέρους του slider που έχει επιλεγεί
        slider.style.background = `linear-gradient(to right, var(--primary-color) 0%, var(--primary-color) ${percent}%, #ddd ${percent}%, #ddd 100%)`;
    }
});