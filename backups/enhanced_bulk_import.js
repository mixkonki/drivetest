/**
 * DriveTest Enhanced Bulk Import Scripts
 * JavaScript για τη μαζική εισαγωγή ερωτήσεων
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log("✅ Enhanced Bulk Import JS φορτώθηκε");
    
    // Διαχείριση αλυσιδωτών dropdowns για κατηγορίες/υποκατηγορίες/κεφάλαια
    initCategoryChain();
    
    // Διαχείριση του αρχείου CSV
    initFileUpload();
    
    // Διαχείριση αντιστοίχισης στηλών
    initColumnMapping();
    
    // Διαχείριση αρχείων πολυμέσων
    initMediaHandling();
});

/**
 * Αρχικοποίηση αλυσιδωτών dropdowns για κατηγορίες/υποκατηγορίες/κεφάλαια
 */
function initCategoryChain() {
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    const chapterSelect = document.getElementById('chapter_id');
    
    if (!categorySelect || !subcategorySelect || !chapterSelect) {
        console.log("❗ Ένα ή περισσότερα dropdown δεν βρέθηκαν.");
        return;
    }
    
    console.log("✅ Αρχικοποίηση αλυσιδωτών dropdowns");
    
    // Δημιουργία cache για τα δεδομένα
    const subcategoriesData = {};
    const chaptersData = {};
    
    // Αρχικοποίηση υποκατηγοριών
    document.querySelectorAll('#subcategory_id > option[data-category]').forEach(option => {
        const categoryId = option.getAttribute('data-category');
        if (!subcategoriesData[categoryId]) {
            subcategoriesData[categoryId] = [];
        }
        subcategoriesData[categoryId].push({
            value: option.value,
            text: option.textContent
        });
    });
    
    // Αρχικοποίηση κεφαλαίων
    document.querySelectorAll('#chapter_id > option[data-subcategory]').forEach(option => {
        const subcategoryId = option.getAttribute('data-subcategory');
        if (!chaptersData[subcategoryId]) {
            chaptersData[subcategoryId] = [];
        }
        chaptersData[subcategoryId].push({
            value: option.value,
            text: option.textContent
        });
    });
    
    // Event listener για επιλογή κατηγορίας
    categorySelect.addEventListener('change', function() {
        const categoryId = this.value;
        
        // Εκκαθάριση και απενεργοποίηση των dropdown που εξαρτώνται
        subcategorySelect.innerHTML = '';
        subcategorySelect.disabled = true;
        chapterSelect.innerHTML = '';
        chapterSelect.disabled = true;
        
        // Προσθήκη του προεπιλεγμένου option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Επιλέξτε Υποκατηγορία --';
        subcategorySelect.appendChild(defaultOption);
        
        // Προσθήκη των υποκατηγοριών που αντιστοιχούν στην κατηγορία
        if (categoryId && subcategoriesData[categoryId]) {
            subcategoriesData[categoryId].forEach(item => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                option.setAttribute('data-category', categoryId);
                subcategorySelect.appendChild(option);
            });
            
            subcategorySelect.disabled = false;
            
            console.log(`✅ Φορτώθηκαν ${subcategoriesData[categoryId].length} υποκατηγορίες για κατηγορία ID:${categoryId}`);
        } else {
            console.log(`❗ Δεν βρέθηκαν υποκατηγορίες για κατηγορία ID:${categoryId}`);
        }
        
        // Ενημέρωση του dropdown κεφαλαίων
        const chapterDefaultOption = document.createElement('option');
        chapterDefaultOption.value = '';
        chapterDefaultOption.textContent = '-- Επιλέξτε πρώτα Υποκατηγορία --';
        chapterSelect.innerHTML = '';
        chapterSelect.appendChild(chapterDefaultOption);
    });
    
    // Event listener για επιλογή υποκατηγορίας
    subcategorySelect.addEventListener('change', function() {
        const subcategoryId = this.value;
        
        // Εκκαθάριση και απενεργοποίηση του dropdown κεφαλαίων
        chapterSelect.innerHTML = '';
        chapterSelect.disabled = true;
        
        // Προσθήκη του προεπιλεγμένου option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Επιλέξτε Κεφάλαιο --';
        chapterSelect.appendChild(defaultOption);
        
        // Προσθήκη των κεφαλαίων που αντιστοιχούν στην υποκατηγορία
        if (subcategoryId && chaptersData[subcategoryId]) {
            chaptersData[subcategoryId].forEach(item => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                option.setAttribute('data-subcategory', subcategoryId);
                chapterSelect.appendChild(option);
            });
            
            chapterSelect.disabled = false;
            
            console.log(`✅ Φορτώθηκαν ${chaptersData[subcategoryId].length} κεφάλαια για υποκατηγορία ID:${subcategoryId}`);
        } else {
            console.log(`❗ Δεν βρέθηκαν κεφάλαια για υποκατηγορία ID:${subcategoryId}`);
            
            // Αν δεν υπάρχουν κεφάλαια, φορτώνουμε από τον server
            if (subcategoryId) {
                loadChaptersAjax(subcategoryId);
            }
        }
    });
    
    // Φόρτωση κεφαλαίων με AJAX αν δεν είναι προ-φορτωμένα
    function loadChaptersAjax(subcategoryId) {
        console.log(`🔄 Φόρτωση κεφαλαίων με AJAX για υποκατηγορία ID:${subcategoryId}`);
        
        fetch('question_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list_chapters&subcategory_id=' + subcategoryId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`✅ Ελήφθησαν ${data.chapters.length} κεφάλαια από τον server`);
                
                // Αποθήκευση στο cache
                chaptersData[subcategoryId] = data.chapters;
                
                // Ενημέρωση του dropdown κεφαλαίων
                chapterSelect.innerHTML = '';
                
                // Προσθήκη του προεπιλεγμένου option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = '-- Επιλέξτε Κεφάλαιο --';
                chapterSelect.appendChild(defaultOption);
                
                // Προσθήκη των κεφαλαίων
                data.chapters.forEach(chapter => {
                    const option = document.createElement('option');
                    option.value = chapter.id;
                    option.textContent = chapter.name;
                    option.setAttribute('data-subcategory', subcategoryId);
                    chapterSelect.appendChild(option);
                });
                
                chapterSelect.disabled = false;
                
                // Αν δεν βρέθηκαν κεφάλαια
                if (data.chapters.length === 0) {
                    const noDataOption = document.createElement('option');
                    noDataOption.value = '';
                    noDataOption.textContent = '-- Δεν βρέθηκαν κεφάλαια --';
                    chapterSelect.innerHTML = '';
                    chapterSelect.appendChild(noDataOption);
                }
            } else {
                console.error(`❌ Σφάλμα: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('❌ Σφάλμα δικτύου:', error);
        });
    }
    
    // Αρχικοποίηση των dropdowns αν έχουν ήδη τιμές
    if (categorySelect.value) {
        const event = new Event('change');
        categorySelect.dispatchEvent(event);
        
        if (subcategorySelect.value) {
            const event = new Event('change');
            subcategorySelect.dispatchEvent(event);
        }
    }
}

/**
 * Αρχικοποίηση της διαχείρισης του αρχείου CSV
 */
function initFileUpload() {
    const fileInput = document.getElementById('csv_file');
    const previewForm = document.getElementById('preview-form');
    const hiddenFileInput = document.getElementById('csv_file_hidden');
    
    if (!fileInput) {
        console.log("❗ Το input αρχείου CSV δεν βρέθηκε");
        return;
    }
    
    console.log("✅ Αρχικοποίηση διαχείρισης αρχείου CSV");
    
    // Αυτόματη υποβολή της φόρμας προεπισκόπησης όταν επιλεγεί αρχείο
    if (fileInput && previewForm) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                console.log(`🔄 Επιλέχθηκε αρχείο: ${this.files[0].name} (${formatBytes(this.files[0].size)})`);
                
                // Έλεγχος μεγέθους αρχείου
                const maxFileSize = 10 * 1024 * 1024; // 10MB
                if (this.files[0].size > maxFileSize) {
                    alert('Το αρχείο είναι πολύ μεγάλο. Το μέγιστο επιτρεπόμενο μέγεθος είναι 10MB.');
                    this.value = '';
                    return;
                }
                
                // Έλεγχος τύπου αρχείου
                const fileType = this.files[0].type;
                const isCSV = fileType === 'text/csv' || 
                             fileType === 'application/vnd.ms-excel' || 
                             fileType === 'application/csv' ||
                             this.files[0].name.toLowerCase().endsWith('.csv');
                
                if (!isCSV) {
                    alert('Παρακαλώ επιλέξτε έγκυρο αρχείο CSV.');
                    this.value = '';
                    return;
                }
                
                console.log("🔄 Υποβολή φόρμας προεπισκόπησης...");
                previewForm.submit();
            }
        });
    }
    
    // Μεταφορά του επιλεγμένου αρχείου στο κρυφό input
    if (hiddenFileInput && fileInput && fileInput.files.length > 0) {
        try {
            // Δημιουργία ενός νέου DataTransfer αντικειμένου
            const dataTransfer = new DataTransfer();
            // Προσθήκη του επιλεγμένου αρχείου
            dataTransfer.items.add(fileInput.files[0]);
            // Ορισμός των αρχείων στο κρυφό input
            hiddenFileInput.files = dataTransfer.files;
            console.log("✅ Το αρχείο μεταφέρθηκε στο κρυφό input");
        } catch (error) {
            console.error("❌ Σφάλμα κατά τη μεταφορά του αρχείου:", error);
        }
    }
    
    // Φορματάρισμα του μεγέθους σε KB, MB
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
}

/**
 * Αρχικοποίηση της αντιστοίχισης στηλών
 */
function initColumnMapping() {
    const columnSelects = document.querySelectorAll('select[name^="map_"]');
    if (columnSelects.length === 0) {
        console.log("❗ Δεν βρέθηκαν dropdowns αντιστοίχισης στηλών");
        return;
    }
    
    console.log(`✅ Αρχικοποίηση διαχείρισης ${columnSelects.length} dropdowns αντιστοίχισης`);
    
    // Παρακολούθηση των αλλαγών στις αντιστοιχίσεις στηλών
    columnSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Αποφυγή διπλών αντιστοιχίσεων
            const selectedValue = this.value;
            if (selectedValue) {
                columnSelects.forEach(otherSelect => {
                    if (otherSelect !== this && otherSelect.value === selectedValue) {
                        console.log(`🔄 Αφαίρεση διπλής αντιστοίχισης από ${otherSelect.name}`);
                        // Αν βρούμε άλλο select με την ίδια τιμή, το καθαρίζουμε
                        otherSelect.value = '';
                    }
                });
            }
        });
    });
}

/**
 * Αρχικοποίηση της διαχείρισης αρχείων πολυμέσων
 */
function initMediaHandling() {
    const zipFileInput = document.getElementById('zip_file');
    if (!zipFileInput) {
        console.log("❗ Το input αρχείου ZIP δεν βρέθηκε");
        return;
    }
    
    console.log("✅ Αρχικοποίηση διαχείρισης αρχείων πολυμέσων");
    
    zipFileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            console.log(`🔄 Επιλέχθηκε αρχείο ZIP: ${this.files[0].name} (${formatBytes(this.files[0].size)})`);
            
            // Έλεγχος μεγέθους αρχείου
            const maxFileSize = 50 * 1024 * 1024; // 50MB
            if (this.files[0].size > maxFileSize) {
                alert('Το αρχείο ZIP είναι πολύ μεγάλο. Το μέγιστο επιτρεπόμενο μέγεθος είναι 50MB.');
                this.value = '';
                return;
            }
            
            // Έλεγχος τύπου αρχείου
            const fileType = this.files[0].type;
            const isZip = fileType === 'application/zip' || 
                        fileType === 'application/x-zip-compressed' || 
                        fileType === 'multipart/x-zip' ||
                        this.files[0].name.toLowerCase().endsWith('.zip');
            
            if (!isZip) {
                alert('Παρακαλώ επιλέξτε έγκυρο αρχείο ZIP.');
                this.value = '';
                return;
            }
        }
    });
    
    // Φορματάρισμα του μεγέθους σε KB, MB
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
}

/**
 * Βοηθητική συνάρτηση καταγραφής σφαλμάτων
 */
function logError(message, error) {
    console.error(`❌ ${message}:`, error);
    
    // Προαιρετικά: Αποστολή σφάλματος στον server για καταγραφή
    fetch('question_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=log_client_error&message=' + encodeURIComponent(message + ': ' + error.toString())
    }).catch(e => console.error('Σφάλμα κατά την καταγραφή σφάλματος:', e));
}