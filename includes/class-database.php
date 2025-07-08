<?php
/**
 * Database Class
 * 
 * Handles custom database tables creation and management
 * Simplified version focused on essential functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Database {
    
    public function __construct() {
        add_action('init', [$this, 'checkDatabaseVersion']);
    }
    
    /**
     * Check database version and update if needed
     */
    public function checkDatabaseVersion(): void {
        $installed_version = get_option('elearning_quiz_db_version', '0');
        
        if (version_compare($installed_version, ELEARNING_QUIZ_VERSION, '<')) {
            $this->createTables();
            update_option('elearning_quiz_db_version', ELEARNING_QUIZ_VERSION);
        }
    }
    
    /**
     * Create custom database tables
     */
    public static function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Quiz attempts table - Core functionality
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attempt_id varchar(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            user_session varchar(255) DEFAULT NULL,
            language varchar(5) DEFAULT 'en',
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            status enum('started', 'completed', 'abandoned') DEFAULT 'started',
            score decimal(5,2) DEFAULT NULL,
            total_questions int(11) DEFAULT NULL,
            correct_answers int(11) DEFAULT NULL,
            passed tinyint(1) DEFAULT 0,
            questions_shown text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY attempt_id (attempt_id),
            KEY quiz_id (quiz_id),
            KEY user_session (user_session),
            KEY status (status),
            KEY language (language),
            KEY passed (passed)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Quiz answers table - Detailed answer tracking
        $table_name = $wpdb->prefix . 'elearning_quiz_answers';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attempt_id varchar(20) NOT NULL,
            question_index int(11) NOT NULL,
            question_type varchar(50) NOT NULL,
            question_text text NOT NULL,
            user_answer text DEFAULT NULL,
            correct_answer text DEFAULT NULL,
            is_correct tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attempt_id (attempt_id),
            KEY question_type (question_type),
            KEY is_correct (is_correct)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Lesson progress table
        $table_name = $wpdb->prefix . 'elearning_lesson_progress';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            lesson_id bigint(20) NOT NULL,
            user_session varchar(255) NOT NULL,
            section_index int(11) NOT NULL,
            completed tinyint(1) DEFAULT 0,
            scroll_completed tinyint(1) DEFAULT 0,
            button_completed tinyint(1) DEFAULT 0,
            time_spent int(11) DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY lesson_user_section (lesson_id, user_session, section_index),
            KEY lesson_id (lesson_id),
            KEY user_session (user_session),
            KEY completed (completed)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Drop custom database tables
     */
    public static function dropTables(): void {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'elearning_quiz_attempts',
            $wpdb->prefix . 'elearning_quiz_answers',
            $wpdb->prefix . 'elearning_lesson_progress'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Generate unique attempt ID
     */
    public static function generateAttemptId(): string {
        global $wpdb;
        
        do {
            $attempt_id = 'tst' . wp_generate_password(10, false, false);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}elearning_quiz_attempts WHERE attempt_id = %s",
                $attempt_id
            ));
        } while ($exists > 0);
        
        return $attempt_id;
    }
    
    /**
     * Get or create user session
     */
    public static function getOrCreateUserSession(): string {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if we already have a session ID
        if (!empty($_SESSION['elearning_user_id'])) {
            return $_SESSION['elearning_user_id'];
        }
        
        // Generate new session ID
        $session_id = 'user_' . wp_generate_password(16, false, false) . '_' . time();
        $_SESSION['elearning_user_id'] = $session_id;
        
        return $session_id;
    }
    
    /**
     * Start a new quiz attempt
     */
    public static function startQuizAttempt($quiz_id, $questions_shown = []): string {
        global $wpdb;
        
        $attempt_id = self::generateAttemptId();
        $user_session = self::getOrCreateUserSession();
        $language = self::getCurrentLanguage();
        
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $result = $wpdb->insert($table_name, [
            'attempt_id' => $attempt_id,
            'quiz_id' => $quiz_id,
            'user_session' => $user_session,
            'language' => $language,
            'start_time' => current_time('mysql'),
            'status' => 'started',
            'questions_shown' => wp_json_encode($questions_shown)
        ]);
        
        if ($result === false) {
            return false;
        }
        
        return $attempt_id;
    }
    
    /**
     * Complete a quiz attempt
     */
    public static function completeQuizAttempt($attempt_id, $score, $total_questions, $correct_answers, $passing_score): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        $passed = ($score >= $passing_score) ? 1 : 0;
        
        $result = $wpdb->update(
            $table_name,
            [
                'end_time' => current_time('mysql'),
                'status' => 'completed',
                'score' => $score,
                'total_questions' => $total_questions,
                'correct_answers' => $correct_answers,
                'passed' => $passed
            ],
            ['attempt_id' => $attempt_id]
        );
        
        return $result !== false;
    }
    
    /**
     * Save quiz answer
     */
    public static function saveQuizAnswer($attempt_id, $question_index, $question_type, $question_text, $user_answer, $correct_answer, $is_correct): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elearning_quiz_answers';
        
        // Convert arrays to JSON for storage
        if (is_array($user_answer)) {
            $user_answer = wp_json_encode($user_answer);
        }
        if (is_array($correct_answer)) {
            $correct_answer = wp_json_encode($correct_answer);
        }
        
        $result = $wpdb->insert($table_name, [
            'attempt_id' => $attempt_id,
            'question_index' => $question_index,
            'question_type' => $question_type,
            'question_text' => wp_strip_all_tags($question_text),
            'user_answer' => $user_answer,
            'correct_answer' => $correct_answer,
            'is_correct' => $is_correct ? 1 : 0
        ]);
        
        return $result !== false;
    }
    
    /**
     * Update lesson progress
     */
    public static function updateLessonProgress($lesson_id, $section_index, $completed = false, $scroll_completed = false, $button_completed = false, $time_spent = null): bool {
        global $wpdb;
        
        $user_session = self::getOrCreateUserSession();
        $table_name = $wpdb->prefix . 'elearning_lesson_progress';
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE lesson_id = %d AND user_session = %s AND section_index = %d",
            $lesson_id, $user_session, $section_index
        ));
        
        $data = [
            'time_spent' => $time_spent,
            'scroll_completed' => $scroll_completed ? 1 : 0,
            'button_completed' => $button_completed ? 1 : 0
        ];
        
        // Mark as completed if both scroll and button are completed
        if ($scroll_completed && $button_completed) {
            $data['completed'] = 1;
            $data['completed_at'] = current_time('mysql');
        }
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                $data,
                [
                    'lesson_id' => $lesson_id,
                    'user_session' => $user_session,
                    'section_index' => $section_index
                ]
            );
        } else {
            // Insert new record
            $data = array_merge($data, [
                'lesson_id' => $lesson_id,
                'user_session' => $user_session,
                'section_index' => $section_index,
                'completed' => ($scroll_completed && $button_completed) ? 1 : 0
            ]);
            
            if ($scroll_completed && $button_completed) {
                $data['completed_at'] = current_time('mysql');
            }
            
            $result = $wpdb->insert($table_name, $data);
        }
        
        return $result !== false;
    }
    
    /**
     * Get lesson progress
     */
    public static function getLessonProgress($lesson_id, $user_session = null): array {
        global $wpdb;
        
        if (!$user_session) {
            $user_session = self::getOrCreateUserSession();
        }
        
        $table_name = $wpdb->prefix . 'elearning_lesson_progress';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE lesson_id = %d AND user_session = %s ORDER BY section_index",
            $lesson_id, $user_session
        ), ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Get previously used questions for a user session
     */
    public static function getPreviouslyUsedQuestions($quiz_id, $user_session = null): array {
        global $wpdb;
        
        if (!$user_session) {
            $user_session = self::getOrCreateUserSession();
        }
        
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT questions_shown FROM $attempts_table 
             WHERE quiz_id = %d AND user_session = %s AND status = 'completed'
             ORDER BY created_at DESC",
            $quiz_id, $user_session
        ), ARRAY_A);
        
        $used_questions = [];
        foreach ($results as $row) {
            $questions = json_decode($row['questions_shown'], true);
            if (is_array($questions)) {
                $used_questions = array_merge($used_questions, $questions);
            }
        }
        
        return array_unique($used_questions);
    }
    
    /**
     * Get quiz statistics
     */
    public static function getQuizStatistics($quiz_id): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_attempts,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_attempts,
                COUNT(CASE WHEN passed = 1 THEN 1 END) as passed_attempts,
                COUNT(CASE WHEN passed = 0 AND status = 'completed' THEN 1 END) as failed_attempts,
                AVG(CASE WHEN status = 'completed' THEN score END) as average_score,
                MAX(score) as highest_score,
                MIN(CASE WHEN status = 'completed' THEN score END) as lowest_score,
                COUNT(CASE WHEN language = 'en' THEN 1 END) as english_attempts,
                COUNT(CASE WHEN language = 'gr' THEN 1 END) as greek_attempts
             FROM $table_name 
             WHERE quiz_id = %d",
            $quiz_id
        ), ARRAY_A);
        
        return $stats ?: [];
    }
    
    /**
     * Get global statistics
     */
    public static function getGlobalStatistics(): array {
        global $wpdb;
        
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_quiz_attempts,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_quiz_attempts,
                COUNT(CASE WHEN passed = 1 THEN 1 END) as passed_quiz_attempts,
                COUNT(DISTINCT user_session) as unique_users,
                AVG(CASE WHEN status = 'completed' THEN score END) as global_average_score,
                COUNT(CASE WHEN language = 'en' THEN 1 END) as english_total,
                COUNT(CASE WHEN language = 'gr' THEN 1 END) as greek_total
             FROM $attempts_table",
            ARRAY_A
        );
        
        return $stats ?: [];
    }
    
    /**
     * Get user quiz attempts
     */
    public static function getUserQuizAttempts($user_session, $quiz_id = null): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $where_clause = "WHERE user_session = %s";
        $params = [$user_session];
        
        if ($quiz_id) {
            $where_clause .= " AND quiz_id = %d";
            $params[] = $quiz_id;
        }
        
        $sql = "SELECT * FROM $table_name $where_clause ORDER BY start_time DESC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Clean up old data (GDPR compliance)
     */
    public static function cleanupOldData(): int {
        global $wpdb;
        
        $settings = get_option('elearning_quiz_settings', []);
        $retention_days = $settings['data_retention_days'] ?? 365;
        
        if ($retention_days <= 0) {
            return 0; // Don't delete if set to 0 or negative
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        $total_deleted = 0;
        
        // Clean up quiz attempts
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $attempts_table WHERE created_at < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        // Clean up quiz answers
        $answers_table = $wpdb->prefix . 'elearning_quiz_answers';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $answers_table WHERE created_at < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        // Clean up lesson progress
        $progress_table = $wpdb->prefix . 'elearning_lesson_progress';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $progress_table WHERE created_at < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        return $total_deleted;
    }
    
    /**
     * Get current language for WPML compatibility
     */
    private static function getCurrentLanguage(): string {
        // Check for WPML
        if (function_exists('icl_get_current_language')) {
            return icl_get_current_language();
        }
        
        // Fallback to WordPress locale
        $locale = get_locale();
        if (strpos($locale, 'el') === 0) {
            return 'gr';
        }
        
        return 'en';
    }
    
    /**
     * Export quiz data to CSV
     */
    public static function exportQuizData($quiz_id, $start_date = null, $end_date = null): string {
        global $wpdb;
        
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $where_clause = "WHERE a.quiz_id = %d";
        $params = [$quiz_id];
        
        if ($start_date) {
            $where_clause .= " AND a.start_time >= %s";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where_clause .= " AND a.start_time <= %s";
            $params[] = $end_date;
        }
        
        $sql = "SELECT 
                    a.attempt_id,
                    a.language,
                    a.start_time,
                    a.end_time,
                    a.status,
                    a.score,
                    a.total_questions,
                    a.correct_answers,
                    a.passed,
                    TIMESTAMPDIFF(MINUTE, a.start_time, a.end_time) as duration_minutes
                FROM $attempts_table a
                $where_clause
                ORDER BY a.start_time DESC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        if (empty($results)) {
            return '';
        }
        
        // Create CSV content
        $csv_content = '';
        
        // Add headers
        $headers = array_keys($results[0]);
        $csv_content .= implode(',', $headers) . "\n";
        
        // Add data rows
        foreach ($results as $row) {
            $csv_content .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }
        
        return $csv_content;
    }
}