document.addEventListener('DOMContentLoaded', function() {
    console.log('Test Config JS loaded');
    
    const categorySelect = document.getElementById('category_id');
    const selectionMethodSelect = document.getElementById('selection_method');
    const chapterDistributionContainer = document.getElementById('chapter_distribution_container');
    const chaptersList = document.getElementById('chapters_list');
    
    // Εμφανίζει ή κρύβει την κατανομή κεφαλαίων ανάλογα με τη μέθοδο επιλογής
    selectionMethodSelect.addEventListener('change', function() {
        if (this.value === 'proportional' || this.value === 'fixed') {
            chapterDistributionContainer.style.display = 'block';
            loadChapters();
        } else {
            chapterDistributionContainer.style.display = 'none';
        }
    });
    
    // Φορτώνει τα κεφάλαια για την επιλεγμένη κατηγορία
    categorySelect.addEventListener('change', function() {
        if (selectionMethodSelect.value === 'proportional' || selectionMethodSelect.value === 'fixed') {
            loadChapters();
        }
    });
    
    // Φορτώνει τα κεφάλαια από τον server
    function loadChapters() {
        const categoryId = categorySelect.value;
        if (!categoryId) return;
        
        chaptersList.innerHTML = '<p>Φόρτωση κεφαλαίων...</p>';
        
        fetch(`${BASE_URL}/admin/test/get_chapters_for_category.php?category_id=${categoryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderChapters(data.chapters);
                } else {
                    chaptersList.innerHTML = `<p class="error">Σφάλμα: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error loading chapters:', error);
                chaptersList.innerHTML = `<p class="error">Σφάλμα επικοινωνίας με τον server: ${error.message}</p>`;
            });
    }
    
    // Εμφανίζει τα κεφάλαια στη σελίδα
    function renderChapters(chapters) {
        if (chapters.length === 0) {
            chaptersList.innerHTML = '<p>Δεν βρέθηκαν κεφάλαια για αυτή την κατηγορία.</p>';
            return;
        }
        
        let html = '';
        chapters.forEach(chapter => {
            html += `
                <div class="chapter-item">
                    <label>${htmlEscape(chapter.name)}</label>
                    <input type="number" name="chapter_distribution[${chapter.id}]" min="0" value="0" 
                           ${selectionMethodSelect.value === 'proportional' ? 'max="100"' : ''}>
                    ${selectionMethodSelect.value === 'proportional' ? '%' : ' ερωτήσεις'}
                </div>
            `;
        });
        
        chaptersList.innerHTML = html;
    }
    
    // Helper για ασφαλή εμφάνιση HTML
    function htmlEscape(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    
    // Διαχείριση των κουμπιών επεξεργασίας
    document.querySelectorAll('.edit-config').forEach(button => {
        button.addEventListener('click', function() {
            const configId = this.getAttribute('data-id');
            
            fetch(`${BASE_URL}/admin/test/get_test_config.php?id=${configId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateForm(data.config);
                    } else {
                        alert(`Σφάλμα: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error fetching config:', error);
                    alert(`Σφάλμα επικοινωνίας με τον server: ${error.message}`);
                });
        });
    });
    
    // Συμπληρώνει τη φόρμα με τα δεδομένα της ρύθμισης
    function populateForm(config) {
        document.getElementById('category_id').value = config.category_id;
        document.getElementById('test_name').value = config.test_name;
        document.getElementById('questions_count').value = config.questions_count;
        document.getElementById('time_limit').value = config.time_limit;
        document.getElementById('pass_percentage').value = config.pass_percentage;
        document.getElementById('selection_method').value = config.selection_method;
        
        if (config.selection_method === 'proportional' || config.selection_method === 'fixed') {
            chapterDistributionContainer.style.display = 'block';
            loadChapters().then(() => {
                try {
                    const distribution = JSON.parse(config.chapter_distribution);
                    Object.keys(distribution).forEach(chapterId => {
                        const input = document.querySelector(`input[name="chapter_distribution[${chapterId}]"]`);
                        if (input) input.value = distribution[chapterId];
                    });
                } catch (e) {
                    console.error('Error parsing chapter distribution:', e);
                }
            });
        } else {
            chapterDistributionContainer.style.display = 'none';
        }
        
        // Scroll to form
        document.querySelector('.admin-form').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
    
    // Διαχείριση των κουμπιών διαγραφής
    document.querySelectorAll('.delete-config').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτή τη ρύθμιση;')) {
                const configId = this.getAttribute('data-id');
                
                fetch(`${BASE_URL}/admin/test/delete_test_config.php?id=${configId}`, { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(`Σφάλμα: ${data.message}`);
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting config:', error);
                        alert(`Σφάλμα επικοινωνίας με τον server: ${error.message}`);
                    });
            }
        });
    });
    
    // Ορισμός του BASE_URL για το JavaScript
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
    
    const BASE_URL = getBaseUrl() || window.location.origin;
});