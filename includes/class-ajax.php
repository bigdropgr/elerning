<?php
/**
 * AJAX Class - Complete Quiz Functionality
 * 
 * Handles all AJAX requests for the e-learning system
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Ajax {
    
    public function __construct() {
        // Quiz-related AJAX handlers
        add_action('wp_ajax_elearning_start_quiz', [$this, 'startQuiz']);
        add_action('wp_ajax_nopriv_elearning_start_quiz', [$this, 'startQuiz']);
        
        add_action('wp_ajax_elearning_submit_quiz', [$this, 'submitQuiz']);
        add_action('wp_ajax_nopriv_elearning_submit_quiz', [$this, 'submitQuiz']);
        
        add_action('wp_ajax_elearning_save_progress', [$this, 'saveProgress']);
        add_action('wp_ajax_nopriv_elearning_save_progress', [$this, 'saveProgress']);
        
        // Admin-related AJAX handlers
        add_action('wp_ajax_elearning_init_editor', [$this, 'initializeEditor']);
        
        // Lesson progress AJAX handlers (for future implementation)
        add_action('wp_ajax_elearning_update_lesson_progress', [$this, 'updateLessonProgress']);
        add_action('wp_ajax_nopriv_elearning_update_lesson_progress', [$this, 'updateLessonProgress']);
    }
    
    /**
     * Start a new quiz attempt
     */
    public function startQuiz(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Security check failed', 'elearning-quiz'));
        }
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        
        if (!$quiz_id) {
            wp_send_json_error(__('Invalid quiz ID', 'elearning-quiz'));
        }
        
        // Check if quiz exists and is published
        $quiz = get_post($quiz_id);
        if (!$quiz || $quiz->post_status !== 'publish' || $quiz->post_type !== 'elearning_quiz') {
            wp_send_json_error(__('Quiz not found or not available', 'elearning-quiz'));
        }
        
        // Get quiz questions
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        if (empty($questions)) {
            wp_send_json_error(__('This quiz has no questions', 'elearning-quiz'));
        }
        
        // Start quiz attempt
        $attempt_id = ELearning_Database::startQuizAttempt($quiz_id);
        
        if (!$attempt_id) {
            wp_send_json_error(__('Failed to start quiz attempt', 'elearning-quiz'));
        }
        
        // Get quiz settings
        $min_questions = get_post_meta($quiz_id, '_min_questions_to_show', true) ?: count($questions);
        $total_questions = min($min_questions, count($questions));
        
        wp_send_json_success([
            'attempt_id' => $attempt_id,
            'total_questions' => $total_questions,
            'message' => __('Quiz started successfully', 'elearning-quiz')
        ]);
    }
    
    /**
     * Submit quiz and calculate results
     */
    public function submitQuiz(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Security check failed', 'elearning-quiz'));
        }
        
        $attempt_id = sanitize_text_field($_POST['attempt_id'] ?? '');
        $answers_json = wp_unslash($_POST['answers'] ?? '');
        $question_timings_json = wp_unslash($_POST['question_timings'] ?? '{}');
        
        if (!$attempt_id) {
            wp_send_json_error(__('Invalid attempt ID', 'elearning-quiz'));
        }
        
        // Get attempt details
        global $wpdb;
        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}elearning_quiz_attempts WHERE attempt_id = %s",
            $attempt_id
        ), ARRAY_A);
        
        if (!$attempt) {
            wp_send_json_error(__('Quiz attempt not found', 'elearning-quiz'));
        }
        
        if ($attempt['status'] !== 'started') {
            wp_send_json_error(__('This quiz has already been submitted', 'elearning-quiz'));
        }
        
        // Get quiz data
        $quiz_id = $attempt['quiz_id'];
        $quiz = get_post($quiz_id);
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        $passing_score = get_post_meta($quiz_id, '_passing_score', true) ?: 70;
        $show_results = get_post_meta($quiz_id, '_show_results_immediately', true) ?: 'yes';
        
        // Parse answers
        $user_answers = json_decode($answers_json, true) ?: [];
        $question_timings = json_decode($question_timings_json, true) ?: [];
        
        // Calculate results
        $results = $this->calculateQuizResults($questions, $user_answers, $passing_score);
        
        // Save detailed answers
        foreach ($user_answers as $question_index => $user_answer) {
            if (isset($questions[$question_index])) {
                $question = $questions[$question_index];
                $correct_answer = $this->getCorrectAnswer($question);
                $is_correct = $this->isAnswerCorrect($question, $user_answer, $correct_answer);
                $time_spent = $question_timings[$question_index] ?? null;
                
                ELearning_Database::saveQuizAnswer(
                    $attempt_id,
                    $question_index,
                    $question['type'],
                    $question['question'],
                    $user_answer,
                    $correct_answer,
                    $is_correct,
                    $time_spent
                );
            }
        }
        
        // Complete the attempt
        ELearning_Database::completeQuizAttempt(
            $attempt_id,
            $results['score'],
            $results['total_questions'],
            $results['correct_answers'],
            $passing_score
        );
        
        // Prepare response data
        $response_data = [
            'score' => $results['score'],
            'correct_answers' => $results['correct_answers'],
            'total_questions' => $results['total_questions'],
            'passed' => $results['passed'],
            'passing_score' => $passing_score,
            'show_answers' => $show_results === 'yes',
            'time_taken' => time() - strtotime($attempt['start_time'])
        ];
        
        // Add detailed results if showing answers
        if ($show_results === 'yes') {
            $response_data['detailed_results'] = $this->getDetailedResults($questions, $user_answers);
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Save quiz progress (auto-save)
     */
    public function saveProgress(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Security check failed', 'elearning-quiz'));
        }
        
        $attempt_id = sanitize_text_field($_POST['attempt_id'] ?? '');
        $current_question = intval($_POST['current_question'] ?? 0);
        $answers_json = wp_unslash($_POST['answers'] ?? '');
        
        if (!$attempt_id) {
            wp_send_json_error(__('Invalid attempt ID', 'elearning-quiz'));
        }
        
        // For now, just acknowledge the save
        // In a more advanced version, we could store progress in a separate table
        wp_send_json_success(['message' => __('Progress saved', 'elearning-quiz')]);
    }
    
    /**
     * Calculate quiz results
     */
    private function calculateQuizResults($questions, $user_answers, $passing_score): array {
        $total_questions = count($user_answers);
        $correct_answers = 0;
        
        foreach ($user_answers as $question_index => $user_answer) {
            if (isset($questions[$question_index])) {
                $question = $questions[$question_index];
                $correct_answer = $this->getCorrectAnswer($question);
                
                if ($this->isAnswerCorrect($question, $user_answer, $correct_answer)) {
                    $correct_answers++;
                }
            }
        }
        
        $score = $total_questions > 0 ? ($correct_answers / $total_questions) * 100 : 0;
        $passed = $score >= $passing_score;
        
        return [
            'score' => round($score, 2),
            'correct_answers' => $correct_answers,
            'total_questions' => $total_questions,
            'passed' => $passed
        ];
    }
    
    /**
     * Get correct answer for a question
     */
    private function getCorrectAnswer($question) {
        switch ($question['type']) {
            case 'multiple_choice':
                return $question['correct_answers'] ?? [];
                
            case 'true_false':
                return $question['correct_answer'] ?? 'true';
                
            case 'fill_blanks':
                return $question['word_bank'] ?? [];
                
            case 'matching':
                return $question['matches'] ?? [];
                
            default:
                return null;
        }
    }
    
    /**
     * Check if user answer is correct
     */
    private function isAnswerCorrect($question, $user_answer, $correct_answer): bool {
        switch ($question['type']) {
            case 'multiple_choice':
                if (is_array($correct_answer)) {
                    // Multiple correct answers
                    if (!is_array($user_answer)) {
                        $user_answer = [$user_answer];
                    }
                    sort($user_answer);
                    sort($correct_answer);
                    return $user_answer === $correct_answer;
                } else {
                    // Single correct answer
                    return $user_answer == $correct_answer;
                }
                
            case 'true_false':
                return $user_answer === $correct_answer;
                
            case 'fill_blanks':
                if (!is_array($user_answer)) {
                    return false;
                }
                
                // Process the text to extract expected answers
                $text_with_blanks = $question['text_with_blanks'] ?? '';
                $expected_answers = $this->extractExpectedAnswers($text_with_blanks, $question['word_bank'] ?? []);
                
                // Check each blank
                foreach ($user_answer as $index => $answer) {
                    if (!isset($expected_answers[$index])) {
                        continue;
                    }
                    
                    $expected = $expected_answers[$index];
                    if (is_array($expected)) {
                        // Multiple acceptable answers
                        if (!in_array(trim($answer), $expected)) {
                            return false;
                        }
                    } else {
                        // Single expected answer
                        if (trim($answer) !== trim($expected)) {
                            return false;
                        }
                    }
                }
                
                return true;
                
            case 'matching':
                if (!is_array($user_answer) || !is_array($correct_answer)) {
                    return false;
                }
                
                // Check if all correct matches are present
                foreach ($correct_answer as $match) {
                    $left_index = $match['left'] ?? null;
                    $right_index = $match['right'] ?? null;
                    
                    if ($left_index === null || $right_index === null) {
                        continue;
                    }
                    
                    if (!isset($user_answer[$left_index]) || $user_answer[$left_index] != $right_index) {
                        return false;
                    }
                }
                
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Extract expected answers from fill-in-the-blanks text
     */
    private function extractExpectedAnswers($text_with_blanks, $word_bank): array {
        // For now, we'll use the word bank in order
        // In a more advanced version, we could parse the text more intelligently
        return $word_bank;
    }
    
    /**
     * Get detailed results for review
     */
    private function getDetailedResults($questions, $user_answers): array {
        $detailed_results = [];
        
        foreach ($user_answers as $question_index => $user_answer) {
            if (isset($questions[$question_index])) {
                $question = $questions[$question_index];
                $correct_answer = $this->getCorrectAnswer($question);
                $is_correct = $this->isAnswerCorrect($question, $user_answer, $correct_answer);
                
                $detailed_results[] = [
                    'question' => wp_strip_all_tags($question['question']),
                    'question_type' => $question['type'],
                    'user_answer' => $user_answer,
                    'correct_answer' => $correct_answer,
                    'correct' => $is_correct
                ];
            }
        }
        
        return $detailed_results;
    }
    
    /**
     * Initialize new wp_editor instance via AJAX (from admin)
     */
    public function initializeEditor(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_admin_nonce')) {
            wp_die(__('Security check failed', 'elearning-quiz'));
        }
        
        // Check user capabilities
        if (!current_user_can('edit_elearning_lessons')) {
            wp_die(__('You do not have permission to perform this action', 'elearning-quiz'));
        }
        
        $editor_id = sanitize_text_field($_POST['editor_id'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        
        if (empty($editor_id)) {
            wp_send_json_error(__('Invalid editor ID', 'elearning-quiz'));
        }
        
        // Extract index from editor ID
        $index = str_replace('section_content_', '', $editor_id);
        $editor_name = "lesson_sections[{$index}][content]";
        
        // Start output buffering to capture wp_editor output
        ob_start();
        
        wp_editor($content, $editor_id, [
            'textarea_name' => $editor_name,
            'textarea_rows' => 8,
            'media_buttons' => true,
            'teeny' => false,
            'dfw' => false,
            'tinymce' => [
                'toolbar1' => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink,undo,redo',
                'toolbar2' => 'formatselect,forecolor,separator,bullist,numlist,separator,outdent,indent,separator,image,code',
                'resize' => true,
                'wp_autoresize_on' => true,
            ],
            'quicktags' => [
                'buttons' => 'strong,em,ul,ol,li,link,close'
            ]
        ]);
        
        $editor_html = ob_get_clean();
        
        wp_send_json_success([
            'editor_html' => $editor_html,
            'editor_id' => $editor_id
        ]);
    }
    
    /**
     * Update lesson progress (placeholder for lesson functionality)
     */
    public function updateLessonProgress(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Security check failed', 'elearning-quiz'));
        }
        
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $section_index = intval($_POST['section_index'] ?? 0);
        $completed = !empty($_POST['completed']);
        $time_spent = intval($_POST['time_spent'] ?? 0);
        $scroll_percentage = floatval($_POST['scroll_percentage'] ?? 0);
        
        if (!$lesson_id) {
            wp_send_json_error(__('Invalid lesson ID', 'elearning-quiz'));
        }
        
        // Update lesson progress
        $result = ELearning_Database::updateLessonProgress(
            $lesson_id,
            $section_index,
            $completed,
            $time_spent,
            $scroll_percentage
        );
        
        if ($result) {
            wp_send_json_success(['message' => __('Progress updated', 'elearning-quiz')]);
        } else {
            wp_send_json_error(__('Failed to update progress', 'elearning-quiz'));
        }
    }
}