/**
 * DriveTest - Category Upload JavaScript
 * Κώδικας JavaScript για τη διαχείριση του ανεβάσματος εικόνας κατηγορίας
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Category Upload JS loaded');
    
    // Αναφορές στα στοιχεία του DOM
    const uploadTabBtn = document.getElementById('upload-tab-btn');
    const urlTabBtn = document.getElementById('url-tab-btn');
    const uploadTab = document.getElementById('upload-tab');
    const urlTab = document.getElementById('url-tab');
    const iconFile = document.getElementById('icon_file');
    const iconUrl = document.getElementById('icon_url');
    const previewImage = document.getElementById('preview-image');
    
    // Εναλλαγή μεταξύ των καρτελών μεταφόρτωσης και URL
    uploadTabBtn.addEventListener('click', function() {
        activateTab(uploadTabBtn, urlTabBtn, uploadTab, urlTab);
        // Καθαρισμός του πεδίου URL όταν επιλέγεται η καρτέλα ανεβάσματος
        iconUrl.value = '';
    });
    
    urlTabBtn.addEventListener('click', function() {
        activateTab(urlTabBtn, uploadTabBtn, urlTab, uploadTab);
        // Καθαρισμός του πεδίου αρχείου όταν επιλέγεται η καρτέλα URL
        iconFile.value = '';
        // Επαναφορά της προεπισκόπησης στην προεπιλεγμένη εικόνα
        previewImage.src = getBaseUrl() + '/assets/images/default.png';
    });
    
    // Συνάρτηση για την εναλλαγή των καρτελών
    function activateTab(activeBtn, inactiveBtn, activeContent, inactiveContent) {
        activeBtn.classList.add('active');
        inactiveBtn.classList.remove('active');
        activeContent.classList.remove('hidden');
        inactiveContent.classList.add('hidden');
    }
    
    // Προεπισκόπηση της επιλεγμένης εικόνας
    iconFile.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            console.log('File selected:', file.name);
            // Έλεγχος αν το αρχείο είναι εικόνα
            if (file.type.match('image.*')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                console.error('Selected file is not an image');
                alert('Παρακαλώ επιλέξτε έγκυρο αρχείο εικόνας (jpg, jpeg, png, gif, svg)');
                this.value = '';
                previewImage.src = getBaseUrl() + '/assets/images/default.png';
            }
        }
    });
    
    // Έλεγχος εγκυρότητας φόρμας πριν την υποβολή
    document.querySelector('.admin-form').addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const price = document.getElementById('price').value.trim();
        
        if (!name || !price) {
            e.preventDefault();
            alert('Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία (Όνομα, Τιμή)');
        }
        
        // Έλεγχος αν έχει επιλεγεί αρχείο ή URL
        if (uploadTab.classList.contains('hidden')) {
            // Είμαστε στην καρτέλα URL
            if (iconUrl.value.trim() !== '') {
                // Έλεγχος αν το URL εικόνας είναι έγκυρο
                const validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                const fileExt = iconUrl.value.trim().split('.').pop().toLowerCase();
                
                if (!validExtensions.includes(fileExt)) {
                    e.preventDefault();
                    alert('Παρακαλώ εισάγετε έγκυρο URL εικόνας με κατάληξη: ' + validExtensions.join(', '));
                }
            }
        }
    });
    
    // Drag & Drop λειτουργικότητα για την περιοχή προεπισκόπησης
    const uploadPreview = document.getElementById('icon-preview');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadPreview.addEventListener(eventName, preventDefaults, false);
    });
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadPreview.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadPreview.addEventListener(eventName, unhighlight, false);
    });
    
    uploadPreview.addEventListener('drop', handleDrop, false);
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlight() {
        uploadPreview.classList.add('highlighted');
    }
    
    function unhighlight() {
        uploadPreview.classList.remove('highlighted');
    }
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            iconFile.files = files;
            // Πυροδότηση του συμβάντος change για να ενημερωθεί η προεπισκόπηση
            const event = new Event('change', { 'bubbles': true });
            iconFile.dispatchEvent(event);
            
            // Ενεργοποίηση της καρτέλας ανεβάσματος
            activateTab(uploadTabBtn, urlTabBtn, uploadTab, urlTab);
        }
    }
    
    // Βοηθητική συνάρτηση για την εύρεση του base URL
    function getBaseUrl() {
        const baseElement = document.querySelector('base');
        if (baseElement) {
            return baseElement.href;
        }
        
        const scriptTags = document.querySelectorAll('script[src]');
        for (let i = 0; i < scriptTags.length; i++) {
            const src = scriptTags[i].getAttribute('src');
            if (src.includes('/admin/assets/js/')) {
                return src.split('/admin/assets/js/')[0];
            }
        }
        
        return window.location.origin;
    }
    
    // Προσθήκη CSS κλάσης για την επισήμανση του πεδίου όταν γίνεται σύρσιμο (drag)
    const style = document.createElement('style');
    style.textContent = `
        .upload-preview.highlighted {
            border-color: var(--primary-color, #aa3636);
            background-color: rgba(170, 54, 54, 0.1);
        }
    `;
    document.head.append(style);
});
// Προσθέστε αυτό το κομμάτι κώδικα στο category_upload.js μέσα στην συνάρτηση DOMContentLoaded

// Διαχείριση προεπισκόπησης URL εικόνας
const iconUrlPreview = document.getElementById('icon-url-preview');
if (iconUrl && iconUrlPreview) {
    iconUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            // Δημιουργία του πλήρους URL για την προεπισκόπηση
            let imageUrl = url;
            
            // Αν δεν είναι απόλυτο URL, συνδυάζουμε με το base URL
            if (!(url.startsWith('http://') || url.startsWith('https://'))) {
                if (url.startsWith('assets/images/')) {
                    imageUrl = getBaseUrl() + '/' + url;
                } else if (url.startsWith('images/')) {
                    imageUrl = getBaseUrl() + '/assets/' + url;
                } else if (url.startsWith('categories/')) {
                    imageUrl = getBaseUrl() + '/assets/images/' + url;
                } else {
                    imageUrl = getBaseUrl() + '/assets/images/' + url;
                }
            }
            
            // Έλεγχος αν το URL είναι έγκυρη εικόνα
            const img = new Image();
            img.onload = function() {
                // Επιτυχής φόρτωση - εμφάνιση προεπισκόπησης
                iconUrlPreview.src = imageUrl;
                iconUrlPreview.style.display = 'block';
                iconUrlPreview.parentElement.classList.remove('error');
            };
            img.onerror = function() {
                // Αποτυχία φόρτωσης - εμφάνιση προεπιλεγμένης εικόνας
                iconUrlPreview.src = getBaseUrl() + '/assets/images/default.png';
                iconUrlPreview.style.display = 'block';
                iconUrlPreview.parentElement.classList.add('error');
            };
            img.src = imageUrl;
        } else {
            // Αν το URL είναι κενό, εμφάνιση προεπιλεγμένης εικόνας
            iconUrlPreview.src = getBaseUrl() + '/assets/images/default.png';
            iconUrlPreview.style.display = 'block';
            iconUrlPreview.parentElement.classList.remove('error');
        }
    });
}