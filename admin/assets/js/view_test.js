document.addEventListener('DOMContentLoaded', function() {
    console.log('View Test JS loaded');
    
    // Φόρτωση των απαντήσεων για κάθε ερώτηση
    const answerContainers = document.querySelectorAll('.question-answers');
    
    answerContainers.forEach(container => {
        const questionId = container.getAttribute('data-question-id');
        loadAnswers(questionId, container);
    });
    
    // Φορτώνει τις απαντήσεις για μια συγκεκριμένη ερώτηση
    function loadAnswers(questionId, container) {
        fetch(`${getBaseUrl()}/admin/test/get_answers.php?question_id=${questionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderAnswers(data.answers, container);
                } else {
                    container.innerHTML = `<p class="error-message">Σφάλμα: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error loading answers:', error);
                container.innerHTML = `<p class="error-message">Σφάλμα επικοινωνίας με τον server: ${error.message}</p>`;
            });
    }
    
    // Εμφανίζει τις απαντήσεις στο DOM
    function renderAnswers(answers, container) {
        if (answers.length === 0) {
            container.innerHTML = '<p>Δεν υπάρχουν διαθέσιμες απαντήσεις.</p>';
            return;
        }
        
        let html = '';
        answers.forEach(answer => {
            const isCorrect = answer.is_correct == 1;
            html += `
                <div class="answer-item ${isCorrect ? 'correct' : ''}">
                    <input type="checkbox" class="answer-checkbox" ${isCorrect ? 'checked' : ''} disabled>
                    <span class="answer-text">${htmlEscape(answer.answer_text)}</span>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // Κουμπί εκτύπωσης
    document.getElementById('print-test').addEventListener('click', function() {
        window.print();
    });
    
    // Κουμπί εξαγωγής PDF
    document.getElementById('export-test').addEventListener('click', function() {
        alert('Η λειτουργία εξαγωγής PDF θα είναι διαθέσιμη σύντομα.');
    });
    
    // Helper για ασφαλή εμφάνιση HTML
    function htmlEscape(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
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