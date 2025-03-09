document.addEventListener("DOMContentLoaded", function () {
    console.log("🔄 [INFO] question_manager.js Loaded");

    // Φόρτωση λίστας ερωτήσεων αν βρισκόμαστε στη σελίδα διαχείρισης
    const questionsTableBody = document.getElementById("questions-table-body");
    if (questionsTableBody) {
        loadQuestions();
    }

    // Προσθήκη listener για το κουμπί προσθήκης ερώτησης (αν υπάρχει)
    const addButton = document.getElementById("add-question-btn");
    if (addButton) {
        addButton.addEventListener("click", function () {
            showQuestionForm();
        });
    }

    // Προσθήκη listener για το κουμπί επιστροφής στη λίστα (αν υπάρχει)
    const backButton = document.getElementById("back-to-list-btn");
    if (backButton) {
        backButton.addEventListener("click", function () {
            showQuestionsList();
        });
    }

    // Προσθήκη listener για επιλογή υποκατηγορίας (αν υπάρχει)
    const subcategorySelect = document.getElementById("subcategory-select");
    if (subcategorySelect) {
        subcategorySelect.addEventListener("change", function () {
            loadChapters(this.value);
        });
    }

    // Προσθήκη listener για την φόρμα ερώτησης (αν υπάρχει)
    const questionForm = document.getElementById("question-form");
    if (questionForm) {
        questionForm.addEventListener("submit", function (e) {
            e.preventDefault();
            saveQuestion();
        });
    }

    // Προσθήκη listener για το κουμπί προσθήκης απάντησης (αν υπάρχει)
    const addAnswerBtn = document.getElementById("add-answer-btn");
    if (addAnswerBtn) {
        addAnswerBtn.addEventListener("click", function () {
            addAnswerField();
        });
    }

    // Προσθήκη listener για επιλογή τύπου ερώτησης (αν υπάρχει)
    const questionType = document.getElementById("question-type");
    if (questionType) {
        questionType.addEventListener("change", function () {
            updateAnswersContainer(this.value);
        });
    }

    // Φόρτωση υποκατηγοριών κατά την αρχικοποίηση
    loadSubcategories();
});

// ✅ Φόρτωση όλων των ερωτήσεων με AJAX
function loadQuestions() {
    console.log("🔄 [INFO] Φόρτωση ερωτήσεων...");

    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_questions"
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            let tableBody = document.getElementById("questions-table-body");
            if (!tableBody) {
                console.error("❌ [ERROR] Element with ID 'questions-table-body' not found");
                return;
            }
            
            tableBody.innerHTML = "";

            if (data.questions.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="9" class="text-center">Δεν βρέθηκαν ερωτήσεις.</td></tr>`;
                return;
            }

            data.questions.forEach(question => {
                let row = document.createElement("tr");
                
                // Εμφάνιση των πρώτων 60 χαρακτήρων της ερώτησης
                let questionText = question.question_text.length > 60 
                    ? question.question_text.substring(0, 60) + "..." 
                    : question.question_text;
                
                // Προετοιμασία HTML για εικονίδιο πολυμέσων
                let mediaHtml = '';
                if (question.question_media) {
                    mediaHtml = '<span title="Περιλαμβάνει πολυμέσα">🖼️</span>';
                }
                
                row.innerHTML = `
                    <td>${questionText} ${mediaHtml}</td>
                    <td>${question.category_name}<br>${question.subcategory_name}<br>${question.chapter_name}</td>
                    <td>${question.answers_count}</td>
                    <td>${getQuestionType(question.question_type)}</td>
                    <td>${formatDate(question.created_at)}</td>
                    <td>${question.status === 'active' ? '<span class="status-active">Ενεργή</span>' : '<span class="status-inactive">Ανενεργή</span>'}</td>
                    <td>${question.author}</td>
                    <td>-</td>
                    <td>${question.id}</td>
                    <td>
                        <button class="edit-question-btn" data-id="${question.id}">✏️</button>
                        <button class="delete-question-btn" data-id="${question.id}">❌</button>
                    </td>
                `;

                tableBody.appendChild(row);
                
                // Προσθήκη listeners για τα κουμπιά επεξεργασίας/διαγραφής
                const editBtn = row.querySelector(".edit-question-btn");
                if (editBtn) {
                    editBtn.addEventListener("click", function() {
                        const id = this.getAttribute("data-id");
                        editQuestion(id);
                    });
                }
                
                const deleteBtn = row.querySelector(".delete-question-btn");
                if (deleteBtn) {
                    deleteBtn.addEventListener("click", function() {
                        const id = this.getAttribute("data-id");
                        deleteQuestion(id);
                    });
                }
            });
            
            console.log("✅ [INFO] Ερωτήσεις φορτώθηκαν επιτυχώς");
        } else {
            console.error("❌ [ERROR] Σφάλμα στη φόρτωση ερωτήσεων:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// ✅ Φόρτωση υποκατηγοριών
function loadSubcategories() {
    console.log("🔄 [INFO] Φόρτωση υποκατηγοριών...");

    // Έλεγχος αν το select υπάρχει στη σελίδα
    const subcategorySelect = document.getElementById("subcategory-select");
    if (!subcategorySelect) {
        console.log("ℹ️ [INFO] Select υποκατηγοριών δεν βρέθηκε στη σελίδα.");
        return;
    }

    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_subcategories"
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            subcategorySelect.innerHTML = `<option value="">-- Επιλέξτε Υποκατηγορία --</option>`;
            
            data.subcategories.forEach(subcategory => {
                subcategorySelect.innerHTML += `
                    <option value="${subcategory.id}">${subcategory.category_name} - ${subcategory.name}</option>
                `;
            });
            
            console.log("✅ [SUCCESS] Βρέθηκαν " + data.subcategories.length + " υποκατηγορίες.");
        } else {
            console.error("❌ [ERROR] Σφάλμα στη φόρτωση υποκατηγοριών:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// ✅ Φόρτωση κεφαλαίων με βάση την επιλεγμένη υποκατηγορία
function loadChapters(subcategoryId) {
    console.log("🔄 [INFO] Φόρτωση κεφαλαίων για υποκατηγορία ID:", subcategoryId);

    if (!subcategoryId) {
        console.log("ℹ️ [INFO] Δεν επιλέχθηκε υποκατηγορία.");
        const chapterSelect = document.getElementById("chapter-select");
        if (chapterSelect) {
            chapterSelect.innerHTML = `<option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>`;
            chapterSelect.disabled = true;
        }
        return;
    }

    const chapterSelect = document.getElementById("chapter-select");
    if (!chapterSelect) {
        console.error("❌ [ERROR] Element with ID 'chapter-select' not found");
        return;
    }

    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=list_chapters&subcategory_id=${subcategoryId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            chapterSelect.innerHTML = `<option value="">-- Επιλέξτε Κεφάλαιο --</option>`;
            
            data.chapters.forEach(chapter => {
                chapterSelect.innerHTML += `
                    <option value="${chapter.id}">${chapter.name}</option>
                `;
            });
            
            chapterSelect.disabled = false;
            console.log("✅ [SUCCESS] Βρέθηκαν " + data.chapters.length + " κεφάλαια.");
        } else {
            console.error("❌ [ERROR] Σφάλμα στη φόρτωση κεφαλαίων:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// ✅ Εμφάνιση φόρμας προσθήκης/επεξεργασίας ερώτησης
function showQuestionForm() {
    const listContainer = document.getElementById("question-list-container");
    const formContainer = document.getElementById("question-form-container");
    
    if (listContainer && formContainer) {
        listContainer.style.display = 'none';
        formContainer.style.display = 'block';
        
        // Καθαρισμός της φόρμας
        resetQuestionForm();
    } else {
        console.error("❌ [ERROR] Container elements not found");
    }
}

// ✅ Εμφάνιση λίστας ερωτήσεων
function showQuestionsList() {
    const listContainer = document.getElementById("question-list-container");
    const formContainer = document.getElementById("question-form-container");
    
    if (listContainer && formContainer) {
        formContainer.style.display = 'none';
        listContainer.style.display = 'block';
        
        // Ανανέωση λίστας ερωτήσεων
        loadQuestions();
    } else {
        console.error("❌ [ERROR] Container elements not found");
    }
}

// ✅ Επεξεργασία ερώτησης
function editQuestion(questionId) {
    console.log("✏️ [INFO] Επεξεργασία ερώτησης ID:", questionId);

    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=get_question&question_id=${questionId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            // Εμφάνιση φόρμας
            showQuestionForm();
            
            const question = data.question;
            
            // Συμπλήρωση πεδίων φόρμας
            document.getElementById("question-id").textContent = question.id;
            
            // Επιλογή της υποκατηγορίας
            const subcategorySelect = document.getElementById("subcategory-select");
            if (subcategorySelect) {
                for (let i = 0; i < subcategorySelect.options.length; i++) {
                    const option = subcategorySelect.options[i];
                    if (option.text.includes(question.subcategory_name)) {
                        subcategorySelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Ενεργοποίηση του event για να φορτωθούν τα κεφάλαια
                const event = new Event('change');
                subcategorySelect.dispatchEvent(event);
                
                // Ορισμός timeout για να περιμένει να φορτωθούν τα κεφάλαια
                setTimeout(() => {
                    // Επιλογή του σωστού κεφαλαίου
                    const chapterSelect = document.getElementById("chapter-select");
                    if (chapterSelect) {
                        for (let i = 0; i < chapterSelect.options.length; i++) {
                            const option = chapterSelect.options[i];
                            if (option.text === question.chapter_name) {
                                chapterSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }, 500);
            }
            
            // Συμπλήρωση κειμένου ερώτησης
            const questionTextArea = document.getElementById("question-text");
            if (questionTextArea) questionTextArea.value = question.question_text;
            
            // Επιλογή τύπου ερώτησης
            const questionTypeSelect = document.getElementById("question-type");
            if (questionTypeSelect) {
                for (let i = 0; i < questionTypeSelect.options.length; i++) {
                    const option = questionTypeSelect.options[i];
                    if (option.value === question.question_type) {
                        questionTypeSelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Ενημέρωση του container απαντήσεων
                updateAnswersContainer(question.question_type);
            }
            
            // Συμπλήρωση επεξήγησης
            const explanationTextArea = document.getElementById("question-explanation");
            if (explanationTextArea) explanationTextArea.value = question.question_explanation;
            
            // Δημιουργία των πεδίων απαντήσεων
            const answersContainer = document.getElementById("answers-container");
            if (answersContainer) {
                answersContainer.innerHTML = '';
                
                question.answers.forEach((answer, index) => {
                    const answerHtml = `
                        <div class="answer-entry">
                            <textarea class="answer-text" name="answers[]">${answer.answer_text}</textarea>
                            <div class="answer-checkbox">
                                <input type="checkbox" name="correct_answers[]" value="${index}" ${answer.is_correct == 1 ? 'checked' : ''}>
                                <label>Σωστή</label>
                            </div>
                            <button type="button" class="remove-answer-btn">❌</button>
                        </div>
                    `;
                    
                    answersContainer.insertAdjacentHTML('beforeend', answerHtml);
                    
                    // Προσθήκη event listener για το κουμπί διαγραφής
                    const removeBtn = answersContainer.lastElementChild.querySelector(".remove-answer-btn");
                    if (removeBtn) {
                        removeBtn.addEventListener("click", function() {
                            this.closest(".answer-entry").remove();
                        });
                    }
                });
            }
        } else {
            console.error("❌ [ERROR] Σφάλμα στη φόρτωση ερώτησης:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// ✅ Διαγραφή ερώτησης
function deleteQuestion(questionId) {
    console.log("🗑️ [INFO] Διαγραφή ερώτησης ID:", questionId);
    
    if (!confirm(`❓ Είστε σίγουροι ότι θέλετε να διαγράψετε την ερώτηση με ID: ${questionId};`)) {
        return;
    }

    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=delete_question&id=${questionId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            alert("✅ Η ερώτηση διαγράφηκε επιτυχώς!");
            loadQuestions();
        } else {
            alert("❌ Σφάλμα: " + data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// ✅ Καθαρισμός/επαναφορά της φόρμας
function resetQuestionForm() {
    const subcategorySelect = document.getElementById("subcategory-select");
    const chapterSelect = document.getElementById("chapter-select");
    const questionTextArea = document.getElementById("question-text");
    const questionTypeSelect = document.getElementById("question-type");
    const explanationTextArea = document.getElementById("question-explanation");
    const questionId = document.getElementById("question-id");
    const answersContainer = document.getElementById("answers-container");
    
    if (subcategorySelect) subcategorySelect.selectedIndex = 0;
    if (chapterSelect) {
        chapterSelect.innerHTML = '<option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>';
        chapterSelect.disabled = true;
    }
    if (questionTextArea) questionTextArea.value = '';
    if (questionTypeSelect) questionTypeSelect.selectedIndex = 0;
    if (explanationTextArea) explanationTextArea.value = '';
    if (questionId) questionId.textContent = '#';
    if (answersContainer) {
        answersContainer.innerHTML = '';
        
        // Προσθήκη προεπιλεγμένων πεδίων απαντήσεων
        for (let i = 0; i < 3; i++) {
            addAnswerField();
        }
    }
}

// ✅ Προσθήκη πεδίου απάντησης
function addAnswerField() {
    const answersContainer = document.getElementById("answers-container");
    if (!answersContainer) {
        console.error("❌ [ERROR] Element with ID 'answers-container' not found");
        return;
    }
    
    const index = answersContainer.children.length;
    
    const answerHtml = `
        <div class="answer-entry">
            <textarea class="answer-text" name="answers[]" placeholder="Απάντηση ${index + 1}"></textarea>
            <div class="answer-checkbox">
                <input type="checkbox" name="correct_answers[]" value="${index}">
                <label>Σωστή</label>
            </div>
            <button type="button" class="remove-answer-btn">❌</button>
        </div>
    `;
    
    answersContainer.insertAdjacentHTML('beforeend', answerHtml);
    
    // Προσθήκη event listener για το κουμπί διαγραφής
    const removeBtn = answersContainer.lastElementChild.querySelector(".remove-answer-btn");
    if (removeBtn) {
        removeBtn.addEventListener("click", function() {
            this.closest(".answer-entry").remove();
            
            // Ενημέρωση των δεικτών των υπόλοιπων απαντήσεων
            const answerEntries = answersContainer.querySelectorAll(".answer-entry");
            answerEntries.forEach((entry, idx) => {
                const checkbox = entry.querySelector("input[type='checkbox']");
                if (checkbox) checkbox.value = idx;
                
                const textarea = entry.querySelector("textarea");
                if (textarea) textarea.placeholder = `Απάντηση ${idx + 1}`;
            });
        });
    }
}

// ✅ Αποθήκευση ερώτησης
function saveQuestion() {
    console.log("💾 [INFO] Αποθήκευση ερώτησης...");
    
    const questionForm = document.getElementById("question-form");
    if (!questionForm) {
        console.error("❌ [ERROR] Form not found");
        return;
    }
    
    const formData = new FormData(questionForm);
    
    // Έλεγχος υποχρεωτικών πεδίων
    const chapterSelect = document.getElementById("chapter-select");
    const questionText = document.getElementById("question-text");
    const answersContainer = document.getElementById("answers-container");
    
    let errors = [];
    
    if (!chapterSelect || chapterSelect.value === "") {
        errors.push("Πρέπει να επιλέξετε κεφάλαιο");
    }
    
    if (!questionText || questionText.value.trim() === "") {
        errors.push("Το κείμενο της ερώτησης είναι υποχρεωτικό");
    }
    
    const answerEntries = answersContainer ? answersContainer.querySelectorAll(".answer-entry") : [];
    if (answerEntries.length === 0) {
        errors.push("Πρέπει να προσθέσετε τουλάχιστον μία απάντηση");
    }
    
    let hasCorrectAnswer = false;
    answerEntries.forEach(entry => {
        const checkbox = entry.querySelector("input[type='checkbox']");
        if (checkbox && checkbox.checked) {
            hasCorrectAnswer = true;
        }
    });
    
    if (!hasCorrectAnswer) {
        errors.push("Πρέπει να επιλέξετε τουλάχιστον μία σωστή απάντηση");
    }
    
    if (errors.length > 0) {
        alert("❌ Σφάλμα: " + errors.join(". "));
        return;
    }
    
    // Προσθήκη του chapter_id από το select
    if (chapterSelect) {
        formData.append("chapter_id", chapterSelect.value);
    }
    
    // Προσθήκη του action
    const questionId = document.getElementById("question-id");
    if (questionId && questionId.textContent !== '#') {
        formData.append("action", "update_question");
        formData.append("id", questionId.textContent);
    } else {
        formData.append("action", "save_question");
    }
    
    // Προσθήκη του question_text
    if (questionText) {
        formData.append("question_text", questionText.value);
    }
    
    // Μετατροπή των απαντήσεων σε JSON
    const answers = [];
    const correctAnswers = [];
    
    answerEntries.forEach((entry, index) => {
        const answerText = entry.querySelector(".answer-text").value;
        const isCorrect = entry.querySelector("input[type='checkbox']").checked;
        
        if (answerText.trim() !== "") {
            answers.push(answerText);
            if (isCorrect) correctAnswers.push(index.toString());
        }
    });
    
    // Αντικατάσταση των πεδίων απαντήσεων με τα JSON strings
    formData.delete("answers[]");
    formData.delete("correct_answers[]");
    formData.append("answers", JSON.stringify(answers));
    formData.append("correct_answers", JSON.stringify(correctAnswers));
    
    // Log για debugging των δεδομένων
    const formObject = {};
    formData.forEach((value, key) => {
        formObject[key] = value;
    });
    console.log("📤 [DEBUG] Form data:", formObject);
    
    // Υποβολή των δεδομένων
    fetch("question_actions.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            alert("✅ Η ερώτηση αποθηκεύτηκε επιτυχώς!");
            showQuestionsList();
        } else {
            alert("❌ Σφάλμα: " + data.message);
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] Σφάλμα AJAX:", error);
        alert("❌ Προέκυψε σφάλμα κατά την αποθήκευση. Παρακαλώ δοκιμάστε ξανά.");
    });
}

// ✅ Ενημέρωση του container απαντήσεων με βάση τον τύπο ερώτησης
function updateAnswersContainer(questionType) {
    console.log("🔄 [INFO] Ενημέρωση container απαντήσεων για τύπο:", questionType);
    
    // Υλοποίηση για διαφορετικούς τύπους ερωτήσεων
    // Προς το παρόν υποστηρίζουμε μόνο single_choice
}

// ✅ Βοηθητικές συναρτήσεις
function getQuestionType(type) {
    const types = {
        'single_choice': 'Μονής Επιλογής',
        'multiple_choice': 'Πολλαπλής Επιλογής',
        'true_false': 'Σωστό/Λάθος',
        'fill_in_blank': 'Συμπλήρωση Κενών',
        'matching': 'Αντιστοίχιση',
        'ordering': 'Ταξινόμηση',
        'short_answer': 'Σύντομης Απάντησης',
        'essay': 'Ανάπτυξης'
    };
    
    return types[type] || type;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('el-GR') + ' ' + date.toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' });
}
// Έλεγχος για URL παράμετρο που υποδεικνύει άμεση προσθήκη ερώτησης
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('action') === 'add') {
    // Καθυστέρηση για να βεβαιωθούμε ότι η σελίδα έχει φορτωθεί πλήρως
    setTimeout(() => {
        const addButton = document.getElementById("add-question-btn");
        if (addButton) {
            addButton.click();
        }
    }, 300);
} else if (urlParams.get('action') === 'edit' && urlParams.get('id')) {
    // Επεξεργασία συγκεκριμένης ερώτησης
    const questionId = urlParams.get('id');
    setTimeout(() => {
        editQuestion(questionId);
    }, 300);
}