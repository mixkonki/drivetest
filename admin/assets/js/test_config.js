document.addEventListener('DOMContentLoaded', function() {
    console.log('Test Config JS loaded');
    
    const categorySelect = document.getElementById('category_id');
    const selectionMethodSelect = document.getElementById('selection_method');
    const chapterDistributionContainer = document.getElementById('chapter_distribution_container');
    const chaptersList = document.getElementById('chapters_list');
    const isPracticeCheckbox = document.querySelector('input[name="test_practice"]'); // Προσαρμόστε αναλόγως το όνομα
    const isSimulationCheckbox = document.querySelector('input[name="test_simulation"]'); // Προσαρμόστε αναλόγως το όνομα
    const displayAnswersModeSelect = document.querySelector('select[name="display_answers_mode"]'); // Προσαρμόστε αναλόγως το όνομα
    
    // Αυτόματη ενημέρωση του display_answers_mode όταν αλλάζει το is_practice
    if (isPracticeCheckbox) {
        isPracticeCheckbox.addEventListener('change', function() {
            if (this.checked && displayAnswersModeSelect) {
                // Για τεστ εξάσκησης, επιλέγουμε αυτόματα "meta_apo_kathe_erotisi"
                displayAnswersModeSelect.value = 'meta_apo_kathe_erotisi';
                // Και απενεργοποιούμε το πεδίο για να μην αλλάξει
                displayAnswersModeSelect.disabled = true;
            } else if (displayAnswersModeSelect && !isSimulationCheckbox.checked) {
                // Επαναφορά της δυνατότητας επιλογής
                displayAnswersModeSelect.disabled = false;
            }
        });
    }
    
    // Αυτόματη ενημέρωση του display_answers_mode όταν αλλάζει το is_simulation
    if (isSimulationCheckbox) {
        isSimulationCheckbox.addEventListener('change', function() {
            if (this.checked && displayAnswersModeSelect) {
                // Για τεστ προσομοίωσης, επιλέγουμε αυτόματα "sto_telos"
                displayAnswersModeSelect.value = 'sto_telos';
                // Και απενεργοποιούμε το πεδίο για να μην αλλάξει
                displayAnswersModeSelect.disabled = true;
            } else if (displayAnswersModeSelect && !isPracticeCheckbox.checked) {
                // Επαναφορά της δυνατότητας επιλογής
                displayAnswersModeSelect.disabled = false;
            }
        });
    }
    
    // Εμφανίζει ή κρύβει την κατανομή κεφαλαίων ανάλογα με τη μέθοδο επιλογής
    if (selectionMethodSelect) {
        selectionMethodSelect.addEventListener('change', function() {
            if (this.value === 'proportional' || this.value === 'fixed') {
                chapterDistributionContainer.style.display = 'block';
                loadChapters();
            } else {
                chapterDistributionContainer.style.display = 'none';
            }
        });
    }
    
    // Φορτώνει τα κεφάλαια για την επιλεγμένη κατηγορία
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            if (selectionMethodSelect && (selectionMethodSelect.value === 'proportional' || selectionMethodSelect.value === 'fixed')) {
                loadChapters();
            }
        });
    }
    
    // Φορτώνει τα κεφάλαια από τον server
    function loadChapters() {
        return new Promise((resolve, reject) => {
            const categoryId = categorySelect.value;
            if (!categoryId) {
                resolve([]);
                return;
            }
            
            chaptersList.innerHTML = '<p>Φόρτωση κεφαλαίων...</p>';
            
            fetch(`${BASE_URL}/admin/test/get_chapters_for_category.php?category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderChapters(data.chapters);
                        resolve(data.chapters);
                    } else {
                        chaptersList.innerHTML = `<p class="error">Σφάλμα: ${data.message}</p>`;
                        reject(new Error(data.message));
                    }
                })
                .catch(error => {
                    console.error('Error loading chapters:', error);
                    chaptersList.innerHTML = `<p class="error">Σφάλμα επικοινωνίας με τον server: ${error.message}</p>`;
                    reject(error);
                });
        });
    }
    
    // Εμφανίζει τα κεφάλαια στη σελίδα
    function renderChapters(chapters) {
        if (chapters.length === 0) {
            chaptersList.innerHTML = '<p>Δεν βρέθηκαν κεφάλαια για αυτή την κατηγορία.</p>';
            return;
        }
        
        let html = '';
        const isProportional = selectionMethodSelect.value === 'proportional';
        
        chapters.forEach(chapter => {
            html += `
                <div class="chapter-item">
                    <div class="chapter-info">
                        <label>${htmlEscape(chapter.name)}</label>
                        <span class="chapter-description">${htmlEscape(chapter.description || '')}</span>
                    </div>
                    <div class="chapter-input">
                        <input type="number" name="chapter_distribution[${chapter.id}]" min="0" value="0" 
                               ${isProportional ? 'max="100"' : ''}>
                        <span class="unit">${isProportional ? '%' : ' ερωτήσεις'}</span>
                    </div>
                </div>
            `;
        });
        
        chaptersList.innerHTML = html;
        
        // Προσθήκη λειτουργικότητας αυτόματου υπολογισμού για proportional
        if (isProportional) {
            const inputs = chaptersList.querySelectorAll('input[type="number"]');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    validateTotalPercentage(inputs);
                });
            });
        }
    }
    
    // Έλεγχος ότι το συνολικό ποσοστό είναι 100% για το proportional
    function validateTotalPercentage(inputs) {
        let total = 0;
        inputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        
        if (total !== 100) {
            const warningElement = document.getElementById('percentage-warning') || document.createElement('div');
            warningElement.id = 'percentage-warning';
            warningElement.className = 'alert alert-warning';
            warningElement.textContent = `Προσοχή: Το συνολικό ποσοστό είναι ${total}%. Θα πρέπει να είναι ακριβώς 100%.`;
            
            if (!document.getElementById('percentage-warning')) {
                chapterDistributionContainer.appendChild(warningElement);
            }
        } else {
            const warningElement = document.getElementById('percentage-warning');
            if (warningElement) {
                warningElement.remove();
            }
        }
    }
    
    // Helper για ασφαλή εμφάνιση HTML
    function htmlEscape(str) {
        if (!str) return '';
        return String(str)
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
                        console.error('Error fetching config:', data.message);
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
        // Βασικά πεδία φόρμας
        if (document.getElementById('category_id')) 
            document.getElementById('category_id').value = config.category_id;
        
        if (document.getElementById('test_name')) 
            document.getElementById('test_name').value = config.test_name;
        
        if (document.getElementById('questions_count')) 
            document.getElementById('questions_count').value = config.questions_count;
        
        if (document.getElementById('time_limit')) 
            document.getElementById('time_limit').value = config.time_limit;
        
        if (document.getElementById('pass_percentage')) 
            document.getElementById('pass_percentage').value = config.pass_percentage;
        
        if (document.getElementById('selection_method')) 
            document.getElementById('selection_method').value = config.selection_method;
        
        // Νέα πεδία - προσαρμόστε τα ονόματα των πεδίων σύμφωνα με την HTML φόρμα σας
        const displayModeElement = document.querySelector('select[name="display_answers_mode"]');
        if (displayModeElement) {
            displayModeElement.value = config.display_answers_mode || 'sto_telos';
        }
        
        const practiceCheckbox = document.querySelector('input[name="test_practice"]');
        if (practiceCheckbox) {
            practiceCheckbox.checked = config.is_practice == 1;
        }
        
        const simulationCheckbox = document.querySelector('input[name="test_simulation"]');
        if (simulationCheckbox) {
            simulationCheckbox.checked = config.is_simulation == 1;
        }
        
        const explanationsCheckbox = document.querySelector('input[name="show_explanations"]');
        if (explanationsCheckbox) {
            explanationsCheckbox.checked = config.show_explanations == 1;
        }
        
        // Επιλογή φίλτρων δυσκολίας
        if (config.difficulty_filter) {
            const difficulties = config.difficulty_filter.split(',');
            
            const easyCheckbox = document.querySelector('input[name="difficulty_filter[]"][value="easy"]');
            if (easyCheckbox) easyCheckbox.checked = difficulties.includes('easy');
            
            const mediumCheckbox = document.querySelector('input[name="difficulty_filter[]"][value="medium"]');
            if (mediumCheckbox) mediumCheckbox.checked = difficulties.includes('medium');
            
            const hardCheckbox = document.querySelector('input[name="difficulty_filter[]"][value="hard"]');
            if (hardCheckbox) hardCheckbox.checked = difficulties.includes('hard');
        }
        
        // Εμφάνιση του container κατανομής εάν χρειάζεται
        if (config.selection_method === 'proportional' || config.selection_method === 'fixed') {
            if (chapterDistributionContainer) {
                chapterDistributionContainer.style.display = 'block';
            }
            
            // Φόρτωση κεφαλαίων και μετά ρύθμιση των τιμών
            loadChapters()
                .then(() => {
                    try {
                        const distribution = JSON.parse(config.chapter_distribution || '{}');
                        Object.keys(distribution).forEach(chapterId => {
                            const input = document.querySelector(`input[name="chapter_distribution[${chapterId}]"]`);
                            if (input) input.value = distribution[chapterId];
                        });
                        
                        // Έλεγχος συνολικού ποσοστού για proportional
                        if (config.selection_method === 'proportional') {
                            const inputs = chaptersList.querySelectorAll('input[type="number"]');
                            if (inputs && inputs.length > 0) {
                                validateTotalPercentage(inputs);
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing chapter distribution:', e);
                    }
                })
                .catch(error => {
                    console.error('Error loading chapters for distribution:', error);
                });
        } else if (chapterDistributionContainer) {
            chapterDistributionContainer.style.display = 'none';
        }
        
        // Ενημέρωση των εξαρτημένων πεδίων
        if (practiceCheckbox && displayModeElement) {
            if (config.is_practice == 1) {
                displayModeElement.value = 'meta_apo_kathe_erotisi';
                displayModeElement.disabled = true;
            } else if (config.is_simulation == 1) {
                displayModeElement.value = 'sto_telos';
                displayModeElement.disabled = true;
            } else {
                displayModeElement.disabled = false;
            }
        }
        
        // Scroll to form
        const formElement = document.querySelector('.admin-form');
        if (formElement) {
            formElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
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