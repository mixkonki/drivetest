/**
 * DriveTest - Chapter Upload JavaScript
 * Κώδικας JavaScript για τη διαχείριση του ανεβάσματος εικόνας κεφαλαίου
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Chapter Upload JS loaded');
    
    // Αναφορές στα στοιχεία του DOM
    const uploadTabBtn = document.getElementById('upload-tab-btn');
    const urlTabBtn = document.getElementById('url-tab-btn');
    const uploadTab = document.getElementById('upload-tab');
    const urlTab = document.getElementById('url-tab');
    const iconFile = document.getElementById('icon_file');
    const iconUrl = document.getElementById('icon');
    const previewImage = document.getElementById('preview-image');
    const iconUrlPreview = document.getElementById('icon-url-preview');
    const uploadPreview = document.getElementById('icon-preview');
    const fileInfoContainer = document.createElement('div');
    
    // Προσθήκη κλάσης στο fileInfoContainer
    fileInfoContainer.className = 'file-info';
    fileInfoContainer.style.display = 'none';
    
    // Προσθέτουμε το container για τις πληροφορίες αρχείου μετά από το κουμπί ανεβάσματος
    const uploadControls = document.querySelector('.upload-controls');
    if (uploadControls) {
        uploadControls.appendChild(fileInfoContainer);
    }
    
    // Προσθήκη αρχικού μηνύματος στην περιοχή προεπισκόπησης
    if (uploadPreview && !uploadPreview.querySelector('.upload-message')) {
        const uploadMessage = document.createElement('div');
        uploadMessage.className = 'upload-message';
        uploadMessage.innerHTML = '<span class="icon-emoji">📷</span>Σύρετε εδώ την εικόνα<br>ή πατήστε το κουμπί';
        uploadPreview.appendChild(uploadMessage);
    }
    
    // Εναλλαγή μεταξύ των καρτελών μεταφόρτωσης και URL
    if (uploadTabBtn) {
        uploadTabBtn.addEventListener('click', function() {
            activateTab(uploadTabBtn, urlTabBtn, uploadTab, urlTab);
            // Καθαρισμός του πεδίου URL όταν επιλέγεται η καρτέλα ανεβάσματος
            if (iconUrl) iconUrl.value = '';
        });
    }
    
    if (urlTabBtn) {
        urlTabBtn.addEventListener('click', function() {
            activateTab(urlTabBtn, uploadTabBtn, urlTab, uploadTab);
            // Καθαρισμός του πεδίου αρχείου όταν επιλέγεται η καρτέλα URL
            if (iconFile) iconFile.value = '';
            // Καθαρισμός των πληροφοριών αρχείου
            fileInfoContainer.style.display = 'none';
            fileInfoContainer.innerHTML = '';
            // Επαναφορά της προεπισκόπησης στην προεπιλεγμένη εικόνα
            if (previewImage) {
                previewImage.src = getBaseUrl() + '/assets/images/default.png';
                // Εμφάνιση του μηνύματος στην περιοχή προεπισκόπησης
                const uploadMessage = uploadPreview.querySelector('.upload-message');
                if (uploadMessage) uploadMessage.style.display = 'block';
            }
        });
    }
    
    // Συνάρτηση για την εναλλαγή των καρτελών
    function activateTab(activeBtn, inactiveBtn, activeContent, inactiveContent) {
        if (activeBtn) activeBtn.classList.add('active');
        if (inactiveBtn) inactiveBtn.classList.remove('active');
        if (activeContent) activeContent.classList.remove('hidden');
        if (inactiveContent) inactiveContent.classList.add('hidden');
    }
    
    // Προεπισκόπηση της επιλεγμένης εικόνας
    if (iconFile) {
        iconFile.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                console.log('File selected:', file.name);
                // Έλεγχος αν το αρχείο είναι εικόνα
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (previewImage) {
                            previewImage.src = e.target.result;
                            // Απόκρυψη του μηνύματος στην περιοχή προεπισκόπησης
                            const uploadMessage = uploadPreview.querySelector('.upload-message');
                            if (uploadMessage) uploadMessage.style.display = 'none';
                        }
                        
                        // Εμφάνιση πληροφοριών αρχείου
                        updateFileInfo(file);
                    };
                    reader.readAsDataURL(file);
                } else {
                    console.error('Selected file is not an image');
                    alert('Παρακαλώ επιλέξτε έγκυρο αρχείο εικόνας (jpg, jpeg, png, gif, svg)');
                    this.value = '';
                    if (previewImage) {
                        previewImage.src = getBaseUrl() + '/assets/images/default.png';
                        // Εμφάνιση του μηνύματος στην περιοχή προεπισκόπησης
                        const uploadMessage = uploadPreview.querySelector('.upload-message');
                        if (uploadMessage) uploadMessage.style.display = 'block';
                    }
                    // Καθαρισμός πληροφοριών αρχείου
                    fileInfoContainer.style.display = 'none';
                    fileInfoContainer.innerHTML = '';
                }
            } else {
                // Καθαρισμός πληροφοριών αρχείου αν δεν επιλέχθηκε αρχείο
                fileInfoContainer.style.display = 'none';
                fileInfoContainer.innerHTML = '';
                if (previewImage) {
                    previewImage.src = getBaseUrl() + '/assets/images/default.png';
                    // Εμφάνιση του μηνύματος στην περιοχή προεπισκόπησης
                    const uploadMessage = uploadPreview.querySelector('.upload-message');
                    if (uploadMessage) uploadMessage.style.display = 'block';
                }
            }
        });
    }
    
    // Συνάρτηση για την ενημέρωση των πληροφοριών αρχείου
    function updateFileInfo(file) {
        if (!fileInfoContainer) return;
        
        // Μετατροπή του μεγέθους αρχείου σε κατάλληλη μορφή
        const fileSize = formatFileSize(file.size);
        
        fileInfoContainer.innerHTML = `
            <span class="file-name">${file.name}</span>
            <span class="file-size">(${fileSize})</span>
            <button type="button" class="remove-file" title="Αφαίρεση αρχείου">❌</button>
        `;
        fileInfoContainer.style.display = 'flex';
        
        // Προσθήκη λειτουργικότητας στο κουμπί αφαίρεσης
        const removeButton = fileInfoContainer.querySelector('.remove-file');
        if (removeButton) {
            removeButton.addEventListener('click', function(e) {
                e.preventDefault();
                if (iconFile) iconFile.value = '';
                fileInfoContainer.style.display = 'none';
                fileInfoContainer.innerHTML = '';
                if (previewImage) {
                    previewImage.src = getBaseUrl() + '/assets/images/default.png';
                    // Εμφάνιση του μηνύματος στην περιοχή προεπισκόπησης
                    const uploadMessage = uploadPreview.querySelector('.upload-message');
                    if (uploadMessage) uploadMessage.style.display = 'block';
                }
            });
        }
    }
    
    // Συνάρτηση μετατροπής μεγέθους αρχείου
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Έλεγχος εγκυρότητας φόρμας πριν την υποβολή
    const chapterForm = document.querySelector('form');
    if (chapterForm) {
        chapterForm.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const subcategory_id = document.getElementById('subcategory_id').value;
            
            if (!name || !subcategory_id) {
                e.preventDefault();
                alert('Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία (Όνομα, Υποκατηγορία)');
            }
            
            // Έλεγχος αν έχει επιλεγεί αρχείο ή URL
            if (uploadTab && uploadTab.classList.contains('hidden') && iconUrl) {
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
    }
    
    // Drag & Drop λειτουργικότητα για την περιοχή προεπισκόπησης
    if (uploadPreview) {
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
    }
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlight() {
        if (uploadPreview) uploadPreview.classList.add('highlighted');
    }
    
    function unhighlight() {
        if (uploadPreview) uploadPreview.classList.remove('highlighted');
    }
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length && iconFile) {
            iconFile.files = files;
            // Πυροδότηση του συμβάντος change για να ενημερωθεί η προεπισκόπηση
            const event = new Event('change', { 'bubbles': true });
            iconFile.dispatchEvent(event);
            
            // Ενεργοποίηση της καρτέλας ανεβάσματος
            activateTab(uploadTabBtn, urlTabBtn, uploadTab, urlTab);
        }
    }
    
    // Διαχείριση προεπισκόπησης URL εικόνας
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
                    } else if (url.startsWith('chapters/')) {
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
                    const wrapper = iconUrlPreview.parentElement;
                    if (wrapper) wrapper.classList.remove('error');
                };
                img.onerror = function() {
                    // Αποτυχία φόρτωσης - εμφάνιση προεπιλεγμένης εικόνας
                    iconUrlPreview.src = getBaseUrl() + '/assets/images/default.png';
                    iconUrlPreview.style.display = 'block';
                    const wrapper = iconUrlPreview.parentElement;
                    if (wrapper) wrapper.classList.add('error');
                };
                img.src = imageUrl;
            } else {
                // Αν το URL είναι κενό, εμφάνιση προεπιλεγμένης εικόνας
                iconUrlPreview.src = getBaseUrl() + '/assets/images/default.png';
                iconUrlPreview.style.display = 'block';
                const wrapper = iconUrlPreview.parentElement;
                if (wrapper) wrapper.classList.remove('error');
            }
        });
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
    
    // Ενημέρωση του κειμένου κουμπιού ανεβάσματος για περισσότερο στυλ
    const uploadBtnLabel = document.querySelector('.upload-btn');
    if (uploadBtnLabel) {
        uploadBtnLabel.innerHTML = '<span class="icon">📤</span> Επιλογή Εικόνας';
    }
 });