<?php
/**
 * Database Class
 * 
 * Handles custom database tables creation and management
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
        
        // Quiz attempts table
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attempt_id varchar(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            user_session varchar(255) DEFAULT NULL,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            status enum('started', 'completed', 'abandoned') DEFAULT 'started',
            score decimal(5,2) DEFAULT NULL,
            total_questions int(11) DEFAULT NULL,
            correct_answers int(11) DEFAULT NULL,
            passed tinyint(1) DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY attempt_id (attempt_id),
            KEY quiz_id (quiz_id),
            KEY status (status),
            KEY start_time (start_time),
            KEY passed (passed)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Quiz answers table (detailed answer tracking)
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
            time_spent int(11) DEFAULT NULL,
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
            time_spent int(11) DEFAULT NULL,
            scroll_percentage decimal(5,2) DEFAULT NULL,
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
        
        // Quiz question bank table (for randomization)
        $table_name = $wpdb->prefix . 'elearning_quiz_question_usage';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attempt_id varchar(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            question_index int(11) NOT NULL,
            question_order int(11) NOT NULL,
            shown tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attempt_id (attempt_id),
            KEY quiz_id (quiz_id),
            KEY question_index (question_index)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Analytics summary table (for performance)
        $table_name = $wpdb->prefix . 'elearning_analytics_summary';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            date_calculated date NOT NULL,
            total_attempts int(11) DEFAULT 0,
            total_completed int(11) DEFAULT 0,
            total_passed int(11) DEFAULT 0,
            total_failed int(11) DEFAULT 0,
            average_score decimal(5,2) DEFAULT NULL,
            highest_score decimal(5,2) DEFAULT NULL,
            lowest_score decimal(5,2) DEFAULT NULL,
            average_time_minutes decimal(8,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY quiz_date (quiz_id, date_calculated),
            KEY quiz_id (quiz_id),
            KEY date_calculated (date_calculated)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // User session tracking (GDPR compliant - no personal data)
        $table_name = $wpdb->prefix . 'elearning_user_sessions';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            session_hash varchar(64) NOT NULL,
            first_visit datetime NOT NULL,
            last_activity datetime NOT NULL,
            total_lessons_started int(11) DEFAULT 0,
            total_lessons_completed int(11) DEFAULT 0,
            total_quizzes_attempted int(11) DEFAULT 0,
            total_quizzes_passed int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY session_hash (session_hash),
            KEY last_activity (last_activity)
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
            $wpdb->prefix . 'elearning_lesson_progress',
            $wpdb->prefix . 'elearning_quiz_question_usage',
            $wpdb->prefix . 'elearning_analytics_summary',
            $wpdb->prefix . 'elearning_user_sessions'
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
        if (!session_id()) {
            session_start();
        }
        
        $session_id = session_id();
        
        if (empty($session_id)) {
            $session_id = wp_generate_password(32, false, false);
            session_id($session_id);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'elearning_user_sessions';
        
        // Check if session exists
        $existing_session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s",
            $session_id
        ));
        
        if (!$existing_session) {
            // Create new session record
            $wpdb->insert($table_name, [
                'session_id' => $session_id,
                'session_hash' => hash('sha256', $session_id . wp_salt()),
                'first_visit' => current_time('mysql'),
                'last_activity' => current_time('mysql')
            ]);
        } else {
            // Update last activity
            $wpdb->update(
                $table_name,
                ['last_activity' => current_time('mysql')],
                ['session_id' => $session_id]
            );
        }
        
        return $session_id;
    }
    
    /**
     * Start a new quiz attempt
     */
    public static function startQuizAttempt($quiz_id): string {
        global $wpdb;
        
        $attempt_id = self::generateAttemptId();
        $user_session = self::getOrCreateUserSession();
        
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $result = $wpdb->insert($table_name, [
            'attempt_id' => $attempt_id,
            'quiz_id' => $quiz_id,
            'user_session' => $user_session,
            'start_time' => current_time('mysql'),
            'status' => 'started',
            'ip_address' => self::getAnonymizedIp(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
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
        
        // Update user session stats
        if ($result !== false) {
            self::updateUserSessionStats($attempt_id, $passed);
        }
        
        return $result !== false;
    }
    
    /**
     * Save quiz answer
     */
    public static function saveQuizAnswer($attempt_id, $question_index, $question_type, $question_text, $user_answer, $correct_answer, $is_correct, $time_spent = null): bool {
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
            'is_correct' => $is_correct ? 1 : 0,
            'time_spent' => $time_spent
        ]);
        
        return $result !== false;
    }
    
    /**
     * Update lesson progress
     */
    public static function updateLessonProgress($lesson_id, $section_index, $completed = false, $time_spent = null, $scroll_percentage = null): bool {
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
            'scroll_percentage' => $scroll_percentage
        ];
        
        if ($completed) {
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
                'completed' => $completed ? 1 : 0
            ]);
            
            if ($completed) {
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
     * Track question usage for randomization
     */
    public static function trackQuestionUsage($attempt_id, $quiz_id, $question_index, $question_order): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elearning_quiz_question_usage';
        
        $result = $wpdb->insert($table_name, [
            'attempt_id' => $attempt_id,
            'quiz_id' => $quiz_id,
            'question_index' => $question_index,
            'question_order' => $question_order
        ]);
        
        return $result !== false;
    }
    
    /**
     * Get previously used questions for a user session
     */
    public static function getPreviouslyUsedQuestions($quiz_id, $user_session): array {
        global $wpdb;
        
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        $usage_table = $wpdb->prefix . 'elearning_quiz_question_usage';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.question_index 
             FROM $usage_table u 
             INNER JOIN $attempts_table a ON u.attempt_id = a.attempt_id 
             WHERE u.quiz_id = %d AND a.user_session = %s",
            $quiz_id, $user_session
        ), ARRAY_A);
        
        return array_column($results, 'question_index');
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
                AVG(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(MINUTE, start_time, end_time) END) as average_time_minutes
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
        $sessions_table = $wpdb->prefix . 'elearning_user_sessions';
        
        $stats = $wpdb->get_row(
            "SELECT 
                (SELECT COUNT(*) FROM $attempts_table) as total_quiz_attempts,
                (SELECT COUNT(*) FROM $attempts_table WHERE status = 'completed') as completed_quiz_attempts,
                (SELECT COUNT(*) FROM $attempts_table WHERE passed = 1) as passed_quiz_attempts,
                (SELECT COUNT(*) FROM $sessions_table) as total_unique_sessions,
                (SELECT AVG(score) FROM $attempts_table WHERE status = 'completed') as global_average_score
            ",
            ARRAY_A
        );
        
        return $stats ?: [];
    }
    
    /**
     * Get most popular quizzes
     */
    public static function getMostPopularQuizzes($limit = 10): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                quiz_id,
                COUNT(*) as attempt_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completion_count,
                COUNT(CASE WHEN passed = 1 THEN 1 END) as pass_count,
                AVG(CASE WHEN status = 'completed' THEN score END) as average_score
             FROM $table_name 
             GROUP BY quiz_id 
             ORDER BY attempt_count DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        // Get quiz titles
        foreach ($results as &$result) {
            $quiz = get_post($result['quiz_id']);
            $result['quiz_title'] = $quiz ? $quiz->post_title : __('Unknown Quiz', 'elearning-quiz');
        }
        
        return $results;
    }
    
    /**
     * Get quiz attempts by user session
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
    public static function cleanupOldData($days = 365): int {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
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
        
        // Clean up question usage
        $usage_table = $wpdb->prefix . 'elearning_quiz_question_usage';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $usage_table WHERE created_at < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        // Clean up inactive user sessions
        $sessions_table = $wpdb->prefix . 'elearning_user_sessions';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $sessions_table WHERE last_activity < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        return $total_deleted;
    }
    
    /**
     * Update analytics summary (run daily via cron)
     */
    public static function updateAnalyticsSummary(): void {
        global $wpdb;
        
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        $summary_table = $wpdb->prefix . 'elearning_analytics_summary';
        $today = current_time('Y-m-d');
        
        // Get all quiz IDs that have attempts
        $quiz_ids = $wpdb->get_col(
            "SELECT DISTINCT quiz_id FROM $attempts_table WHERE DATE(start_time) = '$today'"
        );
        
        foreach ($quiz_ids as $quiz_id) {
            $stats = self::getQuizStatistics($quiz_id);
            
            // Insert or update summary
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $summary_table 
                (quiz_id, date_calculated, total_attempts, total_completed, total_passed, total_failed, 
                 average_score, highest_score, lowest_score, average_time_minutes)
                VALUES (%d, %s, %d, %d, %d, %d, %f, %f, %f, %f)
                ON DUPLICATE KEY UPDATE
                total_attempts = VALUES(total_attempts),
                total_completed = VALUES(total_completed),
                total_passed = VALUES(total_passed),
                total_failed = VALUES(total_failed),
                average_score = VALUES(average_score),
                highest_score = VALUES(highest_score),
                lowest_score = VALUES(lowest_score),
                average_time_minutes = VALUES(average_time_minutes)",
                $quiz_id,
                $today,
                $stats['total_attempts'] ?? 0,
                $stats['completed_attempts'] ?? 0,
                $stats['passed_attempts'] ?? 0,
                $stats['failed_attempts'] ?? 0,
                $stats['average_score'] ?? 0,
                $stats['highest_score'] ?? 0,
                $stats['lowest_score'] ?? 0,
                $stats['average_time_minutes'] ?? 0
            ));
        }
    }
    
    /**
     * Update user session statistics
     */
    private static function updateUserSessionStats($attempt_id, $passed): void {
        global $wpdb;
        
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        $sessions_table = $wpdb->prefix . 'elearning_user_sessions';
        
        // Get user session from attempt
        $user_session = $wpdb->get_var($wpdb->prepare(
            "SELECT user_session FROM $attempts_table WHERE attempt_id = %s",
            $attempt_id
        ));
        
        if ($user_session) {
            // Update session stats
            $wpdb->query($wpdb->prepare(
                "UPDATE $sessions_table SET 
                total_quizzes_attempted = total_quizzes_attempted + 1,
                total_quizzes_passed = total_quizzes_passed + %d
                WHERE session_id = %s",
                $passed ? 1 : 0,
                $user_session
            ));
        }
    }
    
    /**
     * Get anonymized IP address (GDPR compliant)
     */
    private static function getAnonymizedIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (empty($ip)) {
            return '';
        }
        
        // Anonymize IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0'; // Remove last octet
            return implode('.', $parts);
        }
        
        // Anonymize IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            // Keep only first 4 groups (64 bits)
            return implode(':', array_slice($parts, 0, 4)) . '::';
        }
        
        return '';
    }
    
    /**
     * Export quiz data to CSV
     */
    public static function exportQuizData($quiz_id, $start_date = null, $end_date = null): string {
        global $wpdb;
        
        $attempts_table = $wpdb->prefix . 'elearning_quiz_attempts';
        $answers_table = $wpdb->prefix . 'elearning_quiz_answers';
        
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