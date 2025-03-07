document.addEventListener('DOMContentLoaded', function() {
    console.log('Bulk Import JS loaded');
    
    // Αναφορές DOM
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    const chapterSelect = document.getElementById('chapter_id');
    const csvFileInput = document.getElementById('csv_file');
    const zipFileInput = document.getElementById('zip_file');
    const importForm = document.getElementById('bulk-import-form');
    
    // Event Listeners
    categorySelect.addEventListener('change', loadSubcategories);
    subcategorySelect.addEventListener('change', loadChapters);
    csvFileInput.addEventListener('change', validateCSVFile);
    
    // Φόρτωση υποκατηγοριών με βάση την επιλεγμένη κατηγορία
    function loadSubcategories() {
        const categoryId = categorySelect.value;
        if (!categoryId) {
            resetSelect(subcategorySelect, '-- Επιλέξτε πρώτα Κατηγορία --');
            resetSelect(chapterSelect, '-- Επιλέξτε πρώτα Υποκατηγορία --');
            return;
        }
        
        // Ενεργοποίηση του dropdown υποκατηγοριών
        subcategorySelect.disabled = true;
        subcategorySelect.innerHTML = '<option value="">Φόρτωση υποκατηγοριών...</option>';
        
        fetch(`${getBaseUrl()}/admin/test/subcategory_actions.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=list`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                subcategorySelect.innerHTML = '<option value="">-- Επιλέξτε Υποκατηγορία --</option>';
                
                // Φιλτράρισμα μόνο των υποκατηγοριών που ανήκουν στην επιλεγμένη κατηγορία
                const filteredSubcategories = data.subcategories.filter(
                    subcategory => subcategory.test_category_id == categoryId
                );
                
                filteredSubcategories.forEach(subcategory => {
                    const option = document.createElement('option');
                    option.value = subcategory.id;
                    option.textContent = subcategory.name;
                    subcategorySelect.appendChild(option);
                });
                
                subcategorySelect.disabled = false;
            } else {
                console.error('Error loading subcategories:', data.message);
                resetSelect(subcategorySelect, 'Σφάλμα φόρτωσης υποκατηγοριών');
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            resetSelect(subcategorySelect, 'Σφάλμα σύνδεσης με τον server');
        });
    }
    
    // Φόρτωση κεφαλαίων με βάση την επιλεγμένη υποκατηγορία
    function loadChapters() {
        const subcategoryId = subcategorySelect.value;
        if (!subcategoryId) {
            resetSelect(chapterSelect, '-- Επιλέξτε πρώτα Υποκατηγορία --');
            return;
        }
        
        // Ενεργοποίηση του dropdown κεφαλαίων
        chapterSelect.disabled = true;
        chapterSelect.innerHTML = '<option value="">Φόρτωση κεφαλαίων...</option>';
        
        fetch(`${getBaseUrl()}/admin/test/chapter_actions.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=list_chapters&subcategory_id=${subcategoryId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                chapterSelect.innerHTML = '<option value="">-- Επιλέξτε Κεφάλαιο --</option>';
                
                data.chapters.forEach(chapter => {
                    const option = document.createElement('option');
                    option.value = chapter.id;
                    option.textContent = chapter.name;
                    chapterSelect.appendChild(option);
                });
                
                chapterSelect.disabled = false;
            } else {
                console.error('Error loading chapters:', data.message);
                resetSelect(chapterSelect, 'Σφάλμα φόρτωσης κεφαλαίων');
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            resetSelect(chapterSelect, 'Σφάλμα σύνδεσης με τον server');
        });
    }
    
    // Έλεγχος και προεπισκόπηση του CSV αρχείου
    function validateCSVFile() {
        if (!csvFileInput.files.length) return;
        
        const file = csvFileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const content = e.target.result;
            const lines = content.split(/\r\n|\n/);
            
            // Έλεγχος του αρχείου CSV
            if (lines.length < 2) {
                alert('Το αρχείο CSV πρέπει να περιέχει τουλάχιστον μια γραμμή επικεφαλίδας και μια γραμμή δεδομένων.');
                csvFileInput.value = '';
                return;
            }
            
            // Έλεγχος της επικεφαλίδας
            const header = lines[0];
            if (!header.includes('Ερώτηση') && !header.includes('Question')) {
                alert('Η πρώτη γραμμή του CSV πρέπει να περιέχει τις επικεφαλίδες. Δεν βρέθηκε η επικεφαλίδα "Ερώτηση" ή "Question".');
                csvFileInput.value = '';
                return;
            }
            
            // Εμφάνιση προεπισκόπησης δεδομένων
            const previewElement = document.getElementById('csv-preview');
            if (previewElement) {
                const previewRows = Math.min(lines.length, 5); // Δείχνουμε μέχρι 5 γραμμές
                let previewHTML = '<h4>Προεπισκόπηση CSV:</h4>';
                previewHTML += '<table class="preview-table">';
                
                for (let i = 0; i < previewRows; i++) {
                    const row = lines[i].split(/;|,/); // Δοκιμάζουμε διαχωριστικά ; και ,
                    
                    previewHTML += '<tr>';
                    for (const cell of row) {
                        previewHTML += `<td>${cell}</td>`;
                    }
                    previewHTML += '</tr>';
                }
                
                previewHTML += '</table>';
                previewElement.innerHTML = previewHTML;
                previewElement.style.display = 'block';
            }
        };
        
        reader.readAsText(file);
    }
    
    // Βοηθητική συνάρτηση για επαναφορά επιλογής select
    function resetSelect(selectElement, placeholder) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        selectElement.disabled = true;
    }
    
    // Βοηθητική συνάρτηση για την εύρεση του BASE_URL
    function getBaseUrl() {
        const baseElement = document.querySelector('base');
        if (baseElement) return baseElement.href;
        
        // Fallback - extract from link or script tags
        const scriptTags = document.querySelectorAll('script[src]');
        for (let i = 0; i < scriptTags.length; i++) {
            const src = scriptTags[i].getAttribute('src');
            if (src.includes('/admin/assets/js/')) {
                return src.split('/admin/assets/js/')[0];
            }
        }
        
        return '';
    }
});