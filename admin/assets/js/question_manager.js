// ✅ Αρχικοποίηση προκαθορισμένων απαντήσεων
function initializeAnswers() {
    let answersContainer = document.getElementById("answers-container");
    answersContainer.innerHTML = "";

    for (let i = 0; i < 3; i++) {
        addAnswerField();
    }
    console.log("🔍 [INFO] Αρχικοποιήθηκαν 3 πεδία απαντήσεων.");
}

// ✅ Προσθήκη πεδίου απάντησης
function addAnswerField() {
    let answersContainer = document.getElementById("answers-container");
    let answerEntry = document.createElement("div");
    answerEntry.classList.add("answer-entry");
    answerEntry.innerHTML = `
        <div class="form-group">
            <label for="answer-text-${Date.now()}" class="sr-only">Απάντηση:</label>
            <input type="text" class="answer-text" id="answer-text-${Date.now()}" placeholder="Εισάγετε απάντηση..." required>
            <label for="answer-correct-${Date.now()}" class="sr-only">Σωστή Απάντηση:</label>
            <input type="checkbox" class="correct-answer" id="answer-correct-${Date.now()}"> Σωστή
            <label for="answer-media-${Date.now()}" class="sr-only">Multimedia Απάντησης:</label>
            <input type="file" class="answer-media" id="answer-media-${Date.now()}" accept="image/*,video/*,audio/*" class="file-input">
            <button type="button" class="delete-answer-btn btn-delete">Διαγραφή</button>
        </div>
    `;
    answersContainer.appendChild(answerEntry);
    console.log("🔍 [INFO] Προστέθηκε νέο πεδίο απάντησης.");
}

// ✅ Φόρτωση Υποκατηγοριών
function loadSubcategories() {
    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_subcategories"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let subcategorySelect = document.getElementById("subcategory-select");
            subcategorySelect.innerHTML = '<option value="">-- Επιλέξτε Υποκατηγορία --</option>';
            data.subcategories.forEach(sub => {
                let option = document.createElement("option");
                option.value = sub.id;
                option.textContent = `${sub.category_name} / ${sub.name}`;
                subcategorySelect.appendChild(option);
            });
            console.log("✅ [SUCCESS] Φορτώθηκαν υποκατηγορίες.");
        } else {
            console.error("❌ [ERROR] Σφάλμα φόρτωσης υποκατηγοριών:", data.message);
            logClientError("Σφάλμα φόρτωσης υποκατηγοριών: " + data.message);
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] AJAX Σφάλμα:", error);
        logClientError("AJAX σφάλμα κατά τη φόρτωση υποκατηγοριών: " + error.message);
    });
}

// ✅ Φόρτωση Κεφαλαίων
function loadChapters(subcategoryId) {
    if (!subcategoryId) {
        console.warn("⚠️ [WARNING] Δεν επιλέχθηκε Υποκατηγορία.");
        return Promise.reject(new Error("Δεν επιλέχθηκε υποκατηγορία."));
    }

    return fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=list_chapters&subcategory_id=${encodeURIComponent(subcategoryId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let chapterSelect = document.getElementById("chapter-select");
            chapterSelect.innerHTML = '<option value="">-- Επιλέξτε Κεφάλαιο --</option>';
            data.chapters.forEach(chap => {
                let option = document.createElement("option");
                option.value = chap.id;
                option.textContent = chap.name;
                chapterSelect.appendChild(option);
            });
            console.log("✅ [SUCCESS] Φορτώθηκαν κεφάλαια για υποκατηγορία ID: " + subcategoryId);
            return Promise.resolve(data);
        } else {
            console.error("❌ [ERROR] Σφάλμα φόρτωσης κεφαλαίων:", data.message);
            return Promise.reject(new Error(data.message));
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] AJAX σφάλμα:", error);
        logClientError("AJAX σφάλμα κατά τη φόρτωση κεφαλαίων: " + error.message);
        return Promise.reject(error);
    });
}

// ✅ Αποθήκευση Ερώτησης
document.getElementById("question-form").addEventListener("submit", function (e) {
    e.preventDefault();

    let formData = new FormData();
    formData.append("action", "save_question");
    formData.append("question_text", document.getElementById("question-text").value.trim());
    formData.append("question_type", document.getElementById("question-type").value);
    formData.append("chapter_id", document.getElementById("chapter-select").value);
    formData.append("explanation", document.getElementById("question-explanation").value.trim());

    // Multimedia για ερώτηση
    let mediaInputs = {
        'question_image': document.getElementById("question-image").files[0],
        'question_video': document.getElementById("question-video").files[0],
        'question_audio': document.getElementById("question-audio").files[0],
        'explanation_image': document.getElementById("explanation-image").files[0],
        'explanation_video': document.getElementById("explanation-video").files[0],
        'explanation_audio': document.getElementById("explanation-audio").files[0]
    };
    for (let [key, file] of Object.entries(mediaInputs)) {
        if (file) formData.append(key, file);
    }

    // Απαντήσεις
    let answers = [];
    let correctAnswers = [];
    let answerMedias = [];
    document.querySelectorAll(".answer-entry").forEach((entry, index) => {
        let answerText = entry.querySelector(".answer-text").value.trim();
        let isCorrect = entry.querySelector(".correct-answer").checked;
        let answerMedia = entry.querySelector(".answer-media").files[0];
        if (answerText) {
            answers.push(answerText);
            if (isCorrect) correctAnswers.push(answerText);
            if (answerMedia) answerMedias.push(answerMedia);
        }
    });
    formData.append("answers", JSON.stringify(answers));
    formData.append("correct_answers", JSON.stringify(correctAnswers));
    if (answerMedias.length) {
        answerMedias.forEach((media, i) => {
            formData.append(`answer_medias[${i}]`, media);
        });
    }

    fetch("question_actions.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("✅ Η ερώτηση αποθηκεύτηκε με επιτυχία!");
            showQuestionList();
            loadQuestions();
            console.log("✅ [SUCCESS] Ερώτηση αποθηκεύτηκε με ID: " + data.question_id);
        } else {
            alert("❌ Σφάλμα αποθήκευσης ερώτησης: " + data.message);
            console.error("❌ [ERROR] Σφάλμα αποθήκευσης:", data.message);
            logClientError("Σφάλμα αποθήκευσης ερώτησης: " + data.message);
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] AJAX Σφάλμα:", error);
        logClientError("AJAX σφάλμα κατά την αποθήκευση ερώτησης: " + error.message);
    });
});

// ✅ Φόρτωση Ερωτήσεων
function loadQuestions() {
    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_questions"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let tableBody = document.getElementById("questions-table-body");
            tableBody.innerHTML = "";
            data.questions.forEach(question => {
                let row = document.createElement("tr");
                row.setAttribute("data-id", question.id);
                row.innerHTML = `
                    <td><a href="#" class="edit-question" data-id="${question.id}">${question.question_text.substring(0, 50)}${question.question_text.length > 50 ? '...' : ''}</a></td>
                    <td>${question.category_name || '-'}</td>
                    <td>${question.answers_count || 0}</td>
                    <td>${question.question_type}</td>
                    <td>${new Date(question.created_at).toLocaleDateString('el-GR')}</td>
                    <td>${question.status || 'active'}</td>
                    <td>${question.author || '-'}</td>
                    <td>${question.used ? 'Ναι' : 'Όχι'}</td>
                    <td>${question.id}</td>
                `;
                tableBody.appendChild(row);
            });
            console.log("✅ [SUCCESS] Φορτώθηκαν " + data.questions.length + " ερωτήσεις.");
        } else {
            console.error("❌ [ERROR] Σφάλμα φόρτωσης ερωτήσεων:", data.message);
            logClientError("Σφάλμα φόρτωσης ερωτήσεων: " + data.message);
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] AJAX Σφάλμα:", error);
        logClientError("AJAX σφάλμα κατά τη φόρτωση ερωτήσεων: " + error.message);
    });
}

// ✅ Φόρτωση Ερώτησης για Επεξεργασία
function loadQuestionForEdit(questionId) {
    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=get_question&question_id=${encodeURIComponent(questionId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let question = data.question;

            // Έλεγχος αν τα elements υπάρχουν
            const questionIdElement = document.getElementById("question-id");
            const questionText = document.getElementById("question-text");
            const questionType = document.getElementById("question-type");
            const questionExplanation = document.getElementById("question-explanation");
            const subcategorySelect = document.getElementById("subcategory-select");
            const chapterSelect = document.getElementById("chapter-select");

            if (!questionIdElement || !questionText || !questionType || !questionExplanation || !subcategorySelect || !chapterSelect) {
                console.error("❌ [ERROR] Ένα ή περισσότερα DOM elements δεν βρέθηκαν!");
                logClientError("DOM elements δεν βρέθηκαν για ερώτηση ID: " + questionId);
                return;
            }

            // Ρύθμιση δεδομένων
            questionIdElement.textContent = `#${question.id}`;
            questionText.value = question.question_text || "";
            questionType.value = question.question_type || "single_choice";
            questionExplanation.value = question.question_explanation || "";

            // Εμφάνιση υποκατηγορίας και κεφαλαίου
            if (question.subcategory_name && subcategorySelect) {
                let found = false;
                Array.from(subcategorySelect.options).forEach(option => {
                    if (option.textContent.includes(question.subcategory_name)) {
                        option.selected = true;
                        found = true;
                    }
                });
                if (!found) {
                    console.warn("⚠️ [WARNING] Δεν βρέθηκε υποκατηγορία: " + question.subcategory_name);
                }
                console.log("🔍 [INFO] Επιλέχθηκε υποκατηγορία: " + (question.subcategory_name || 'Κενό'));
            } else {
                console.warn("⚠️ [WARNING] Δεν υπάρχει υποκατηγορία ή subcategorySelect για ερώτηση ID: " + questionId);
            }

            // Φόρτωση κεφαλαίων για την επιλεγμένη υποκατηγορία
            let subcategoryId = Array.from(subcategorySelect.options).find(option => 
                option.textContent.includes(question.subcategory_name))?.value;
            if (subcategoryId) {
                loadChapters(subcategoryId)
                    .then(() => {
                        if (question.chapter_name && chapterSelect) {
                            let foundChapter = false;
                            Array.from(chapterSelect.options).forEach(option => {
                                if (option.textContent === question.chapter_name) {
                                    option.selected = true;
                                    foundChapter = true;
                                }
                            });
                            if (!foundChapter) {
                                console.warn("⚠️ [WARNING] Δεν βρέθηκε κεφάλαιο: " + question.chapter_name);
                            }
                            console.log("🔍 [INFO] Επιλέχθηκε κεφάλαιο: " + (question.chapter_name || 'Κενό'));
                        } else {
                            console.warn("⚠️ [WARNING] Δεν υπάρχει κεφάλαιο ή chapterSelect για ερώτηση ID: " + questionId);
                        }
                    })
                    .catch(error => {
                        console.error("❌ [ERROR] Σφάλμα φόρτωσης κεφαλαίων:", error);
                        logClientError("Σφάλμα φόρτωσης κεφαλαίων για ερώτηση ID: " + questionId + " - " + error.message);
                    });
            } else {
                console.error("❌ [ERROR] Δεν βρέθηκε ID υποκατηγορίας για: " + question.subcategory_name);
                logClientError("Δεν βρέθηκε ID υποκατηγορίας για: " + question.subcategory_name);
            }

            // Εμφάνιση multimedia αν υπάρχει
            let mediaInputs = {
                'question-image': document.getElementById("question-image"),
                'question-video': document.getElementById("question-video"),
                'question-audio': document.getElementById("question-audio"),
                'explanation-image': document.getElementById("explanation-image"),
                'explanation-video': document.getElementById("explanation-video"),
                'explanation-audio': document.getElementById("explanation-audio")
            };
            for (let [id, input] of Object.entries(mediaInputs)) {
                if (input) input.value = ''; // Reset
            }
            if (question.question_media) console.log("🔍 [INFO] Βρέθηκε multimedia για ερώτηση: " + question.question_media);
            if (question.explanation_media) console.log("🔍 [INFO] Βρέθηκε multimedia για επεξήγηση: " + question.explanation_media);
            if (question.image) console.log("🔍 [INFO] Βρέθηκε εικόνα για ερώτηση: " + question.image);
            if (question.video) console.log("🔍 [INFO] Βρέθηκε βίντεο για ερώτηση: " + question.video);
            if (question.audio) console.log("🔍 [INFO] Βρέθηκε ήχος για ερώτηση: " + question.audio);
            if (question.explanation_image) console.log("🔍 [INFO] Βρέθηκε εικόνα για επεξήγηση: " + question.explanation_image);
            if (question.explanation_video) console.log("🔍 [INFO] Βρέθηκε βίντεο για επεξήγηση: " + question.explanation_video);
            if (question.explanation_audio) console.log("🔍 [INFO] Βρέθηκε ήχος για επεξήγηση: " + question.explanation_audio);

            // Καθαρισμός και φόρτωση απαντήσεων
            let answersContainer = document.getElementById("answers-container");
            if (!answersContainer) {
                console.error("❌ [ERROR] Το answers-container δεν βρέθηκε!");
                logClientError("Το answers-container δεν βρέθηκε για ερώτηση ID: " + questionId);
                return;
            }
            answersContainer.innerHTML = "";

            if (question.answers && Array.isArray(question.answers) && question.answers.length > 0) {
                console.log("✅ [SUCCESS] Βρέθηκαν " + question.answers.length + " απαντήσεις για ερώτηση ID: " + questionId);
                question.answers.forEach(answer => {
                    addAnswerField();
                    let entries = document.querySelectorAll(".answer-entry");
                    let lastEntry = entries[entries.length - 1];
                    if (lastEntry) {
                        lastEntry.querySelector(".answer-text").value = answer.answer_text || "";
                        lastEntry.querySelector(".correct-answer").checked = !!answer.is_correct;
                        if (answer.answer_media) {
                            console.log("🔍 [INFO] Βρέθηκε multimedia για απάντηση: " + answer.answer_media);
                        }
                    } else {
                        console.error("❌ [ERROR] Δεν βρέθηκε τελευταίο πεδίο απάντησης!");
                        logClientError("Δεν βρέθηκε τελευταίο πεδίο απάντησης για ερώτηση ID: " + questionId);
                    }
                });
            } else {
                console.warn("⚠️ [WARNING] Δεν βρέθηκαν απαντήσεις για ερώτηση ID: " + questionId);
                for (let i = 0; i < 3; i++) {
                    addAnswerField();
                }
            }

            showQuestionForm();
            console.log("✅ [SUCCESS] Φορτώθηκε ερώτηση με ID: " + questionId);
        } else {
            alert("❌ Σφάλμα φόρτωσης ερώτησης: " + data.message);
            console.error("❌ [ERROR] Σφάλμα φόρτωσης ερώτησης:", data.message);
            logClientError("Σφάλμα φόρτωσης ερώτησης ID: " + questionId + " - " + data.message);
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] AJAX Σφάλμα:", error);
        logClientError("AJAX σφάλμα κατά τη φόρτωση ερώτησης ID: " + questionId + " - " + error.message);
    });
}

// ✅ Εμφάνιση φόρμας και απόκρυψη λίστας
function showQuestionForm() {
    document.getElementById("question-list-container").style.display = "none";
    document.getElementById("question-form-container").style.display = "block";
    console.log("🔍 [INFO] Εμφανίστηκε η φόρμα επεξεργασίας.");
}

// ✅ Απόκρυψη φόρμας και εμφάνιση λίστας
function showQuestionList() {
    document.getElementById("question-list-container").style.display = "block";
    document.getElementById("question-form-container").style.display = "none";
    console.log("🔍 [INFO] Εμφανίστηκε η λίστα ερωτήσεων.");
}

// ✅ Κλικ σε ερώτηση για επεξεργασία
document.getElementById("questions-table-body").addEventListener("click", function (e) {
    if (e.target.classList.contains("edit-question")) {
        e.preventDefault();
        let questionId = e.target.getAttribute("data-id");

        if (!questionId) {
            console.error("❌ [ERROR] Το ID της ερώτησης δεν βρέθηκε!");
            logClientError("Το ID της ερώτησης δεν βρέθηκε κατά το κλικ.");
            return;
        }

        console.log("🔍 [INFO] Επιλέχθηκε Ερώτηση με ID:", questionId);
        loadQuestionForEdit(questionId);
    }
});

// ✅ Εμφάνιση φόρμας για προσθήκη νέας ερώτησης
document.getElementById("add-question-btn").addEventListener("click", function () {
    document.getElementById("question-form").reset();
    document.getElementById("question-id").textContent = "Νέα Ερώτηση";
    showQuestionForm();
    console.log("🔍 [INFO] Ανοιχτή η φόρμα για νέα ερώτηση.");
});

// ✅ Επιστροφή στη λίστα ερωτήσεων
document.getElementById("back-to-list-btn").addEventListener("click", showQuestionList);

// ✅ Προσθήκη/Διαγραφή απαντήσεων
document.getElementById("add-answer-btn").addEventListener("click", addAnswerField);

document.addEventListener("click", function (e) {
    if (e.target.classList.contains("delete-answer-btn")) {
        e.target.closest(".answer-entry").remove();
        console.log("🔍 [INFO] Διαγράφηκε πεδίο απάντησης.");
    }
});

// ✅ Αρχικοποίηση της σελίδας
document.addEventListener("DOMContentLoaded", function () {
    initializeAnswers();
    loadSubcategories();
    loadQuestions();

    // Φόρτωση κεφαλαίων όταν αλλάζει η υποκατηγορία
    document.getElementById("subcategory-select").addEventListener("change", function () {
        let subcategoryId = this.value;
        loadChapters(subcategoryId)
            .then(() => console.log("🔍 [INFO] Αλλαγή υποκατηγορίας σε ID: " + subcategoryId))
            .catch(error => {
                console.error("❌ [ERROR] Σφάλμα φόρτωσης κεφαλαίων:", error);
                logClientError("Σφάλμα φόρτωσης κεφαλαίων για υποκατηγορία ID: " + subcategoryId + " - " + error.message);
            });
    });

    // Προσθήκη απάντησης
    let addAnswerBtn = document.getElementById("add-answer-btn");
    if (addAnswerBtn) {
        addAnswerBtn.addEventListener("click", addAnswerField);
    } else {
        console.error("❌ [ERROR] Το στοιχείο με ID 'add-answer-btn' δεν βρέθηκε!");
        logClientError("Το στοιχείο με ID 'add-answer-btn' δεν βρέθηκε στη σελίδα.");
    }

    // Διαγραφή απάντησης
    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("delete-answer-btn")) {
            e.target.closest(".answer-entry").remove();
            console.log("🔍 [INFO] Διαγράφηκε πεδίο απάντησης.");
        }
    });

    // Εμφάνιση φόρμας για προσθήκη νέας ερώτησης
    document.getElementById("add-question-btn").addEventListener("click", function () {
        document.getElementById("question-form").reset();
        document.getElementById("question-id").textContent = "Νέα Ερώτηση";
        showQuestionForm();
        console.log("🔍 [INFO] Ανοιχτή η φόρμα για νέα ερώτηση.");
    });

    // Επιστροφή στη λίστα ερωτήσεων
    document.getElementById("back-to-list-btn").addEventListener("click", showQuestionList);

    // Κλικ σε ερώτηση για επεξεργασία
    document.getElementById("questions-table-body").addEventListener("click", function (e) {
        if (e.target.classList.contains("edit-question")) {
            e.preventDefault();
            let questionId = e.target.getAttribute("data-id");

            if (!questionId) {
                console.error("❌ [ERROR] Το ID της ερώτησης δεν βρέθηκε!");
                logClientError("Το ID της ερώτησης δεν βρέθηκε κατά το κλικ.");
                return;
            }

            console.log("🔍 [INFO] Επιλέχθηκε Ερώτηση με ID:", questionId);
            loadQuestionForEdit(questionId);
        }
    });
});

// ✅ Καταγραφή σφαλμάτων πελάτη
function logClientError(errorMessage) {
    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=log_client_error&message=${encodeURIComponent("[" + new Date().toLocaleString() + "] " + errorMessage)}`
    })
    .catch(error => console.error("❌ [ERROR] Απέτυχε η καταγραφή σφάλματος πελάτη:", error));
}
function loadQuestions() {
    fetch("question_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_questions"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let tableBody = document.getElementById("questions-table-body");
            tableBody.innerHTML = "";
            data.questions.forEach(question => {
                let row = document.createElement("tr");
                row.setAttribute("data-id", question.id);
                row.innerHTML = `
                    <td><a href="#" class="edit-question" data-id="${question.id}">${question.question_text.substring(0, 50)}${question.question_text.length > 50 ? '...' : ''}</a>
                        <noscript><a href="edit_question.php?id=${question.id}">Επεξεργασία</a></noscript></td>
                    <td>${question.category_name || '-'}</td>
                    <td>${question.answers_count || 0}</td>
                    <td>${question.question_type}</td>
                    <td>${new Date(question.created_at).toLocaleDateString('el-GR')}</td>
                    <td>${question.status || 'active'}</td>
                    <td>${question.author || '-'}</td>
                    <td>${question.used ? 'Ναι' : 'Όχι'}</td>
                    <td>${question.id}</td>
                `;
                tableBody.appendChild(row);
            });
            console.log("✅ [SUCCESS] Φορτώθηκαν " + data.questions.length + " ερωτήσεις.");
        }
    });
}