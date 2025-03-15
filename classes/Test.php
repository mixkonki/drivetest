<?php
// Διαδρομή: /classes/Test.php

class Test {
    /**
     * Ανακτά ένα τεστ από το ID
     * 
     * @param int $id Το ID του τεστ
     * @return array|null Τα στοιχεία του τεστ ή null αν δεν βρέθηκε
     */
    public static function getById($id) {
        return db_get_row("SELECT * FROM tests WHERE id = ?", [$id]);
    }
    
    /**
     * Ανακτά όλα τα τεστ
     * 
     * @param array $filters Φίλτρα για την ανάκτηση των τεστ
     * @param int $limit Ο μέγιστος αριθμός τεστ προς ανάκτηση
     * @param int $offset Η θέση εκκίνησης
     * @return array Τα τεστ
     */
    public static function getAll($filters = [], $limit = 100, $offset = 0) {
        $query = "SELECT t.*, tc.name as category_name, ts.name as subcategory_name 
                 FROM tests t 
                 LEFT JOIN test_categories tc ON t.category_id = tc.id
                 LEFT JOIN test_subcategories ts ON t.subcategory_id = ts.id
                 WHERE 1=1";
        $params = [];
        
        // Προσθήκη φίλτρων
        if (!empty($filters['category_id'])) {
            $query .= " AND t.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['subcategory_id'])) {
            $query .= " AND t.subcategory_id = ?";
            $params[] = $filters['subcategory_id'];
        }
        
        if (!empty($filters['type'])) {
            $query .= " AND t.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['difficulty'])) {
            $query .= " AND t.difficulty = ?";
            $params[] = $filters['difficulty'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND t.name LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }
        
        // Προσθήκη ταξινόμησης
        $query .= " ORDER BY t.created_at DESC";
        
        // Προσθήκη pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        return db_query($query, $params);
    }
    
    /**
     * Δημιουργεί ένα νέο τεστ
     * 
     * @param array $data Τα στοιχεία του τεστ
     * @return int|false Το ID του νέου τεστ ή false σε περίπτωση σφάλματος
     */
    public static function create($data) {
        // Προετοιμασία των παραμέτρων
        $params = [
            $data['name'],
            $data['category_id'],
            $data['subcategory_id'],
            json_encode($data['chapter_ids']),
            $data['question_count'],
            $data['pass_percentage'] ?? 70,
            $data['pass_count'] ?? null,
            $data['time_limit'] ?? null,
            $data['difficulty'] ?? 'medium',
            $data['type']
        ];
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            INSERT INTO tests (
                name, category_id, subcategory_id, chapter_ids, 
                question_count, pass_percentage, pass_count, time_limit, 
                difficulty, type, created_at
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", $params);
    }
    
    /**
     * Ενημερώνει τα στοιχεία ενός τεστ
     * 
     * @param int $id Το ID του τεστ
     * @param array $data Τα νέα στοιχεία
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function update($id, $data) {
        // Έλεγχος αν υπάρχει το τεστ
        $test = self::getById($id);
        if (!$test) {
            return false;
        }
        
        // Προετοιμασία των στοιχείων προς ενημέρωση
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['category_id'])) {
            $fields[] = "category_id = ?";
            $params[] = $data['category_id'];
        }
        
        if (isset($data['subcategory_id'])) {
            $fields[] = "subcategory_id = ?";
            $params[] = $data['subcategory_id'];
        }
        
        if (isset($data['chapter_ids'])) {
            $fields[] = "chapter_ids = ?";
            $params[] = json_encode($data['chapter_ids']);
        }
        
        if (isset($data['question_count'])) {
            $fields[] = "question_count = ?";
            $params[] = $data['question_count'];
        }
        
        if (isset($data['pass_percentage'])) {
            $fields[] = "pass_percentage = ?";
            $params[] = $data['pass_percentage'];
        }
        
        if (isset($data['pass_count'])) {
            $fields[] = "pass_count = ?";
            $params[] = $data['pass_count'];
        }
        
        if (isset($data['time_limit'])) {
            $fields[] = "time_limit = ?";
            $params[] = $data['time_limit'];
        }
        
        if (isset($data['difficulty'])) {
            $fields[] = "difficulty = ?";
            $params[] = $data['difficulty'];
        }
        
        if (isset($data['type'])) {
            $fields[] = "type = ?";
            $params[] = $data['type'];
        }
        
        if (empty($fields)) {
            return true; // Δεν υπάρχουν πεδία προς ενημέρωση
        }
        
        // Προσθήκη του ID στις παραμέτρους
        $params[] = $id;
        
        // Εκτέλεση του ερωτήματος
        return db_query("
            UPDATE tests 
            SET " . implode(", ", $fields) . ", updated_at = NOW()
            WHERE id = ?
        ", $params);
    }
    
    /**
     * Διαγράφει ένα τεστ
     * 
     * @param int $id Το ID του τεστ
     * @return boolean Επιτυχία ή αποτυχία
     */
    public static function delete($id) {
        // Έλεγχος αν υπάρχει το τεστ
        $test = self::getById($id);
        if (!$test) {
            return false;
        }
        
        // Διαγραφή του τεστ
        return db_query("DELETE FROM tests WHERE id = ?", [$id]);
    }
    
    /**
     * Δημιουργεί ένα τυχαίο τεστ
     * 
     * @param int $category_id Το ID της κατηγορίας
     * @param int $chapter_id Το ID του κεφαλαίου (προαιρετικό)
     * @param int $question_count Ο αριθμός των ερωτήσεων
     * @param string $difficulty Η δυσκολία των ερωτήσεων
     * @return array Τα στοιχεία του τεστ με τις ερωτήσεις
     */
    public static function generateRandomTest($category_id, $chapter_id = null, $question_count = 20, $difficulty = 'medium') {
        global $config;
        
        // Ερώτημα για την επιλογή των ερωτήσεων
        $query = "SELECT id FROM questions WHERE status = 'active'";
        $params = [];
        
        if ($chapter_id) {
            $query .= " AND chapter_id = ?";
            $params[] = $chapter_id;
        } else {
            $query .= " AND chapter_id IN (
                SELECT id FROM test_chapters 
                WHERE subcategory_id IN (
                    SELECT id FROM test_subcategories 
                    WHERE test_category_id = ?
                )
            )";
            $params[] = $category_id;
        }
        
        if ($difficulty !== 'all') {
            $query .= " AND difficulty_level = ?";
            $params[] = $difficulty;
        }
        
        $query .= " ORDER BY RAND() LIMIT ?";
        $params[] = (int)$question_count;
        
        // Εκτέλεση του ερωτήματος για την επιλογή των ερωτήσεων
        $question_ids
        /**
     * Δημιουργεί ένα τυχαίο τεστ
     * 
     * @param int $category_id Το ID της κατηγορίας
     * @param int $chapter_id Το ID του κεφαλαίου (προαιρετικό)
     * @param int $question_count Ο αριθμός των ερωτήσεων
     * @param string $difficulty Η δυσκολία των ερωτήσεων
     * @return array Τα στοιχεία του τεστ με τις ερωτήσεις
     */
    public static function generateRandomTest($category_id, $chapter_id = null, $question_count = 20, $difficulty = 'medium') {
        global $config;
        
        // Ερώτημα για την επιλογή των ερωτήσεων
        $query = "SELECT id FROM questions WHERE status = 'active'";
        $params = [];
        
        if ($chapter_id) {
            $query .= " AND chapter_id = ?";
            $params[] = $chapter_id;
        } else {
            $query .= " AND chapter_id IN (
                SELECT id FROM test_chapters 
                WHERE subcategory_id IN (
                    SELECT id FROM test_subcategories 
                    WHERE test_category_id = ?
                )
            )";
            $params[] = $category_id;
        }
        
        if ($difficulty !== 'all') {
            $query .= " AND difficulty_level = ?";
            $params[] = $difficulty;
        }
        
        $query .= " ORDER BY RAND() LIMIT ?";
        $params[] = (int)$question_count;
        
        // Εκτέλεση του ερωτήματος για την επιλογή των ερωτήσεων
        $question_ids_result = db_query($query, $params);
        
        // Έλεγχος αν βρέθηκαν αρκετές ερωτήσεις
        if (count($question_ids_result) < $question_count) {
            // Αν δεν βρέθηκαν αρκετές ερωτήσεις, δοκιμάζουμε χωρίς περιορισμό δυσκολίας
            if ($difficulty !== 'all') {
                return self::generateRandomTest($category_id, $chapter_id, $question_count, 'all');
            }
            
            // Αν ακόμα δεν βρέθηκαν αρκετές ερωτήσεις, επιστρέφουμε όσες έχουμε
            $question_count = count($question_ids_result);
        }
        
        // Δημιουργία πίνακα με τα IDs των ερωτήσεων
        $question_ids = [];
        foreach ($question_ids_result as $result) {
            $question_ids[] = $result['id'];
        }
        
        // Ανάκτηση των ερωτήσεων με τις απαντήσεις τους
        $questions = [];
        foreach ($question_ids as $id) {
            $question = db_get_row("SELECT * FROM questions WHERE id = ?", [$id]);
            
            // Ανάκτηση των απαντήσεων της ερώτησης
            $answers = db_query("SELECT * FROM test_answers WHERE question_id = ?", [$id]);
            
            $question['answers'] = $answers;
            $questions[] = $question;
        }
        
        // Πληροφορίες για το τεστ
        $test_info = [
            'category_id' => $category_id,
            'chapter_id' => $chapter_id,
            'question_count' => $question_count,
            'difficulty' => $difficulty,
            'time_limit' => $config['test_time_limit'] ?? 30, // Χρονικό όριο σε λεπτά
            'pass_percentage' => $config['success_percentage_threshold'] ?? 70, // Ελάχιστο ποσοστό επιτυχίας
            'questions' => $questions
        ];
        
        return $test_info;
    }
    
    /**
     * Αποθηκεύει τα αποτελέσματα ενός τεστ
     * 
     * @param int $user_id Το ID του χρήστη
     * @param array $test_data Τα δεδομένα του τεστ
     * @param array $user_answers Οι απαντήσεις του χρήστη
     * @param int $time_spent Ο χρόνος που δαπανήθηκε (σε δευτερόλεπτα)
     * @return int|false Το ID του αποτελέσματος ή false σε περίπτωση σφάλματος
     */
    public static function saveTestResult($user_id, $test_data, $user_answers, $time_spent) {
        // Υπολογισμός σκορ
        $total_questions = count($test_data['questions']);
        $correct_answers = 0;
        
        foreach ($user_answers as $question_id => $answer_id) {
            // Ανάκτηση της σωστής απάντησης
            $correct_answer = db_get_row("
                SELECT id FROM test_answers 
                WHERE question_id = ? AND is_correct = 1
            ", [$question_id]);
            
            if ($correct_answer && $answer_id == $correct_answer['id']) {
                $correct_answers++;
            }
        }
        
        // Υπολογισμός ποσοστού επιτυχίας
        $score = ($correct_answers / $total_questions) * 100;
        
        // Έλεγχος αν ο χρήστης πέρασε το τεστ
        $passed = ($score >= $test_data['pass_percentage']);
        
        // Αποθήκευση του αποτελέσματος
        $result_id = db_query("
            INSERT INTO test_results (
                user_id, test_type, test_category_id, chapter_id, 
                score, total_questions, time_spent, passed, 
                start_time, end_time, created_at
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? SECOND, NOW(), NOW())
        ", [
            $user_id, 
            'random', 
            $test_data['category_id'], 
            $test_data['chapter_id'] ?? 0, 
            $score, 
            $total_questions, 
            $time_spent, 
            $passed ? 1 : 0, 
            $time_spent
        ]);
        
        if (!$result_id) {
            return false;
        }
        
        // Αποθήκευση των απαντήσεων του χρήστη
        foreach ($user_answers as $question_id => $answer_id) {
            // Ανάκτηση της σωστής απάντησης
            $correct_answer = db_get_row("
                SELECT id FROM test_answers 
                WHERE question_id = ? AND is_correct = 1
            ", [$question_id]);
            
            $is_correct = ($correct_answer && $answer_id == $correct_answer['id']) ? 1 : 0;
            
            db_query("
                INSERT INTO test_results_answers (
                    test_result_id, question_id, user_answer_id, is_correct
                ) 
                VALUES (?, ?, ?, ?)
            ", [$result_id, $question_id, $answer_id, $is_correct]);
        }
        
        return $result_id;
    }
    
    /**
     * Ανακτά το ιστορικό των τεστ ενός χρήστη
     * 
     * @param int $user_id Το ID του χρήστη
     * @param array $filters Φίλτρα για την ανάκτηση των τεστ
     * @param int $limit Ο μέγιστος αριθμός τεστ προς ανάκτηση
     * @param int $offset Η θέση εκκίνησης
     * @return array Το ιστορικό των τεστ
     */
    public static function getUserHistory($user_id, $filters = [], $limit = 20, $offset = 0) {
        $query = "
            SELECT tr.*, tc.name as category_name, tch.name as chapter_name
            FROM test_results tr
            LEFT JOIN test_categories tc ON tr.test_category_id = tc.id
            LEFT JOIN test_chapters tch ON tr.chapter_id = tch.id
            WHERE tr.user_id = ?
        ";
        $params = [$user_id];
        
        // Προσθήκη φίλτρων
        if (!empty($filters['category_id'])) {
            $query .= " AND tr.test_category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['chapter_id'])) {
            $query .= " AND tr.chapter_id = ?";
            $params[] = $filters['chapter_id'];
        }
        
        if (isset($filters['passed'])) {
            $query .= " AND tr.passed = ?";
            $params[] = $filters['passed'] ? 1 : 0;
        }
        
        if (!empty($filters['from_date'])) {
            $query .= " AND DATE(tr.created_at) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $query .= " AND DATE(tr.created_at) <= ?";
            $params[] = $filters['to_date'];
        }
        
        // Προσθήκη ταξινόμησης
        $query .= " ORDER BY tr.created_at DESC";
        
        // Προσθήκη pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        return db_query($query, $params);
    }
    
    /**
     * Ανακτά τις λεπτομέρειες ενός αποτελέσματος τεστ
     * 
     * @param int $result_id Το ID του αποτελέσματος
     * @return array Οι λεπτομέρειες του τεστ
     */
    public static function getResultDetails($result_id) {
        // Ανάκτηση του αποτελέσματος
        $result = db_get_row("
            SELECT tr.*, tc.name as category_name, tch.name as chapter_name, u.fullname
            FROM test_results tr
            LEFT JOIN test_categories tc ON tr.test_category_id = tc.id
            LEFT JOIN test_chapters tch ON tr.chapter_id = tch.id
            LEFT JOIN users u ON tr.user_id = u.id
            WHERE tr.id = ?
        ", [$result_id]);
        
        if (!$result) {
            return null;
        }
        
        // Ανάκτηση των απαντήσεων
        $answers = db_query("
            SELECT tra.*, q.question_text, q.question_explanation, q.image as question_image,
                   ta.answer_text, ta.answer_media, tac.answer_text as correct_answer_text
            FROM test_results_answers tra
            JOIN questions q ON tra.question_id = q.id
            LEFT JOIN test_answers ta ON tra.user_answer_id = ta.id
            LEFT JOIN test_answers tac ON tac.question_id = q.id AND tac.is_correct = 1
            WHERE tra.test_result_id = ?
            ORDER BY tra.id
        ", [$result_id]);
        
        $result['answers'] = $answers;
        
        return $result;
    }
}