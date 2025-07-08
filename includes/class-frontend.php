<?php
/**
 * Frontend Class - FIXED VERSION
 * 
 * Fixed the True/False HTML structure to prevent grid layout issues
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Frontend {
    
    public function __construct() {
        add_filter('the_content', [$this, 'addQuizContent']);
        add_filter('the_content', [$this, 'addLessonContent']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueQuizAssets']);
        add_action('wp_footer', [$this, 'addQuizModalStructure']);
    }
    
    /**
     * Add quiz content to single quiz pages
     */
    public function addQuizContent($content) {
        if (!is_singular('elearning_quiz') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        global $post;
        
        // Get quiz data
        $questions = get_post_meta($post->ID, '_quiz_questions', true) ?: [];
        $passing_score = get_post_meta($post->ID, '_passing_score', true) ?: 70;
        $min_questions = get_post_meta($post->ID, '_min_questions_to_show', true) ?: count($questions);
        $show_results = get_post_meta($post->ID, '_show_results_immediately', true) ?: 'yes';
        
        if (empty($questions)) {
            $content .= '<div class="elearning-quiz-notice">';
            $content .= '<p>' . __('This quiz has no questions yet.', 'elearning-quiz') . '</p>';
            $content .= '</div>';
            return $content;
        }
        
        // FORCE FRESH START - Clear any existing session data
        $this->clearQuizSession($post->ID);
        
        // Quiz interface - ALWAYS show intro, never cached results
        $content .= '<div class="elearning-quiz-container" data-quiz-id="' . esc_attr($post->ID) . '">';
        $content .= $this->renderQuizInterface($post->ID, $questions, $passing_score, $min_questions, $show_results);
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Clear quiz session data to force fresh start
     */
    private function clearQuizSession($quiz_id) {
        // Clear any session data that might show cached results
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear quiz-specific session data
        unset($_SESSION['quiz_' . $quiz_id]);
        unset($_SESSION['quiz_attempt_' . $quiz_id]);
        
        // Clear any transients
        delete_transient('quiz_result_' . $quiz_id);
        delete_transient('quiz_state_' . $quiz_id);
    }
    
    /**
     * Add lesson content placeholder
     */
    public function addLessonContent($content) {
        if (!is_singular('elearning_lesson') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        $content .= '<div class="elearning-lesson-notice">';
        $content .= '<p><em>' . __('Lesson display functionality coming next in Phase 2!', 'elearning-quiz') . '</em></p>';
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Render quiz interface - ALWAYS starts fresh
     */
    private function renderQuizInterface($quiz_id, $questions, $passing_score, $min_questions, $show_results) {
        // Select questions for this attempt
        $selected_questions = $this->selectQuizQuestions($quiz_id, $questions, $min_questions);
        
        $html = '<div class="elearning-quiz-intro">';
        $html .= '<div class="quiz-info">';
        $html .= '<div class="quiz-stat"><span class="label">' . __('Questions:', 'elearning-quiz') . '</span> <span class="value">' . count($selected_questions) . '</span></div>';
        $html .= '<div class="quiz-stat"><span class="label">' . __('Passing Score:', 'elearning-quiz') . '</span> <span class="value">' . $passing_score . '%</span></div>';
        $html .= '<div class="quiz-stat"><span class="label">' . __('Time Limit:', 'elearning-quiz') . '</span> <span class="value">' . __('None', 'elearning-quiz') . '</span></div>';
        $html .= '</div>';
        $html .= '<button type="button" class="start-quiz-btn" data-quiz-id="' . esc_attr($quiz_id) . '">' . __('Start Quiz', 'elearning-quiz') . '</button>';
        $html .= '</div>';
        
        // Quiz form (hidden initially)
        $html .= '<form class="elearning-quiz-form" style="display: none;" data-passing-score="' . esc_attr($passing_score) . '" data-show-results="' . esc_attr($show_results) . '">';
        $html .= wp_nonce_field('elearning_quiz_submit', 'quiz_nonce', true, false);
        $html .= '<input type="hidden" name="quiz_id" value="' . esc_attr($quiz_id) . '" />';
        $html .= '<input type="hidden" name="attempt_id" value="" />';
        
        // Progress indicator
        $html .= '<div class="quiz-progress">';
        $html .= '<div class="progress-bar"><div class="progress-fill" style="width: 0%"></div></div>';
        $html .= '<div class="progress-text"><span class="current">1</span> / <span class="total">' . count($selected_questions) . '</span></div>';
        $html .= '</div>';
        
        // Questions container
        $html .= '<div class="quiz-questions-container">';
        
        foreach ($selected_questions as $index => $question) {
            $html .= $this->renderQuestion($index, $question);
        }
        
        $html .= '</div>';
        
        // Navigation buttons
        $html .= '<div class="quiz-navigation">';
        $html .= '<button type="button" class="quiz-nav-btn prev-btn" disabled>' . __('Previous', 'elearning-quiz') . '</button>';
        $html .= '<button type="button" class="quiz-nav-btn next-btn">' . __('Next', 'elearning-quiz') . '</button>';
        $html .= '<button type="button" class="quiz-submit-btn" style="display: none;">' . __('Submit Quiz', 'elearning-quiz') . '</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        
        // Results container (hidden initially)
        $html .= '<div class="quiz-results" style="display: none;"></div>';
        
        return $html;
    }
    
    /**
     * Select questions for quiz attempt
     */
    private function selectQuizQuestions($quiz_id, $all_questions, $min_questions) {
        if (count($all_questions) <= $min_questions) {
            return $all_questions;
        }
        
        // For now, just return first N questions to avoid session issues
        return array_slice($all_questions, 0, $min_questions);
    }
    
    /**
     * Render individual question
     */
    private function renderQuestion($index, $question) {
        $question_class = $index === 0 ? 'quiz-question active' : 'quiz-question';
        
        $html = '<div class="' . $question_class . '" data-question-index="' . esc_attr($index) . '" data-question-type="' . esc_attr($question['type']) . '">';
        $html .= '<div class="question-header">';
        $html .= '<h3 class="question-title">' . sprintf(__('Question %d', 'elearning-quiz'), $index + 1) . '</h3>';
        $html .= '</div>';
        
        $html .= '<div class="question-content">';
        $html .= '<div class="question-text">' . wp_kses_post($question['question']) . '</div>';
        
        switch ($question['type']) {
            case 'multiple_choice':
                $html .= $this->renderMultipleChoice($index, $question);
                break;
            case 'fill_blanks':
                $html .= $this->renderFillBlanks($index, $question);
                break;
            case 'true_false':
                $html .= $this->renderTrueFalse($index, $question);
                break;
            case 'matching':
                $html .= $this->renderMatching($index, $question);
                break;
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render multiple choice question
     */
    private function renderMultipleChoice($index, $question) {
        $options = $question['options'] ?? [];
        $correct_answers = $question['correct_answers'] ?? [];
        $is_multi_select = count($correct_answers) > 1;
        
        $html = '<p class="instruction">';
        if ($is_multi_select) {
            $html .= __('Select all correct answers:', 'elearning-quiz');
        } else {
            $html .= __('Select the correct answer:', 'elearning-quiz');
        }
        $html .= '</p>';
        
        $html .= '<div class="multiple-choice-options">';
        
        foreach ($options as $opt_index => $option) {
            $input_type = $is_multi_select ? 'checkbox' : 'radio';
            $input_name = $is_multi_select ? "questions[{$index}][answers][]" : "questions[{$index}][answer]";
            
            $html .= '<label class="option-label">';
            $html .= '<input type="' . $input_type . '" name="' . $input_name . '" value="' . esc_attr($opt_index) . '" />';
            $html .= '<span class="option-text">' . esc_html($option) . '</span>';
            $html .= '</label>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render fill in the blanks question
     */
    private function renderFillBlanks($index, $question) {
        $text_with_blanks = $question['text_with_blanks'] ?? '';
        $word_bank = $question['word_bank'] ?? [];
        
        // Process text to create blanks
        $blank_count = 0;
        $processed_text = preg_replace_callback('/\{\{blank\}\}/', function($matches) use (&$blank_count) {
            return '<span class="blank-space" data-blank-index="' . $blank_count++ . '"></span>';
        }, $text_with_blanks);
        
        $html = '<p class="instruction">' . __('Drag words to fill the blanks:', 'elearning-quiz') . '</p>';
        
        $html .= '<div class="fill-blanks-container">';
        
        $html .= '<div class="text-with-blanks">' . wp_kses_post($processed_text) . '</div>';
        
        if (!empty($word_bank)) {
            $html .= '<div class="word-bank">';
            $html .= '<h4>' . __('Word Bank:', 'elearning-quiz') . '</h4>';
            $html .= '<div class="word-bank-items">';
            
            // Shuffle word bank
            $shuffled_words = $word_bank;
            shuffle($shuffled_words);
            
            foreach ($shuffled_words as $word_index => $word) {
                $html .= '<span class="word-item" draggable="true" data-word="' . esc_attr($word) . '">' . esc_html($word) . '</span>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
            
            // Hidden inputs for answers
            for ($i = 0; $i < $blank_count; $i++) {
                $html .= '<input type="hidden" name="questions[' . $index . '][answers][' . $i . ']" value="" class="blank-answer" data-blank-index="' . $i . '" />';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render true/false question - FIXED STRUCTURE
     */
    private function renderTrueFalse($index, $question) {
        // FIXED: Move instruction outside the grid container
        $html = '<p class="instruction">' . __('Select True or False:', 'elearning-quiz') . '</p>';
        
        // FIXED: Only the two label elements should be in the grid
        $html .= '<div class="true-false-options">';
        
        $html .= '<label class="option-label neutral-option">';
        $html .= '<input type="radio" name="questions[' . $index . '][answer]" value="true" />';
        $html .= '<span class="option-text">' . __('True', 'elearning-quiz') . '</span>';
        $html .= '</label>';
        
        $html .= '<label class="option-label neutral-option">';
        $html .= '<input type="radio" name="questions[' . $index . '][answer]" value="false" />';
        $html .= '<span class="option-text">' . __('False', 'elearning-quiz') . '</span>';
        $html .= '</label>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render matching question - Drag & Drop Implementation
     */
    private function renderMatching($index, $question) {
        $left_column = $question['left_column'] ?? [];
        $right_column = $question['right_column'] ?? [];
        
        // Shuffle right column for display
        $shuffled_right = $right_column;
        shuffle($shuffled_right);
        
        $html = '<p class="instruction">' . __('Drag items from the right column to match with items in the left column:', 'elearning-quiz') . '</p>';
        
        $html .= '<div class="matching-container">';
        
        $html .= '<div class="matching-columns">';
        
        // Left column - Drop zones
        $html .= '<div class="left-column">';
        $html .= '<h4>' . __('Match These:', 'elearning-quiz') . '</h4>';
        foreach ($left_column as $left_index => $left_item) {
            $html .= '<div class="match-item left-item" data-left-index="' . esc_attr($left_index) . '">';
            $html .= '<div class="item-text">' . esc_html($left_item) . '</div>';
            $html .= '<div class="drop-zone" data-left-index="' . esc_attr($left_index) . '">';
            $html .= '<span class="drop-placeholder">' . __('Drop here', 'elearning-quiz') . '</span>';
            $html .= '</div>';
            $html .= '<input type="hidden" name="questions[' . $index . '][answers][' . $left_index . ']" value="" class="match-answer" />';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // Right column - Draggable items
        $html .= '<div class="right-column">';
        $html .= '<h4>' . __('Available Options:', 'elearning-quiz') . '</h4>';
        $html .= '<div class="draggable-items">';
        foreach ($shuffled_right as $right_index => $right_item) {
            // Find original index
            $original_index = array_search($right_item, $right_column);
            $html .= '<div class="match-item right-item draggable-item" draggable="true" data-right-index="' . esc_attr($original_index) . '" data-item-text="' . esc_attr($right_item) . '">';
            $html .= '<span class="item-text">' . esc_html($right_item) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Enqueue quiz-specific assets
     */
    public function enqueueQuizAssets() {
        if (is_singular(['elearning_quiz', 'elearning_lesson'])) {
            // jQuery UI for drag and drop
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('jquery-ui-sortable');
        }
    }
    
    /**
     * Add quiz modal structure to footer
     */
    public function addQuizModalStructure() {
        if (!is_singular('elearning_quiz')) {
            return;
        }
        ?>
        <div id="quiz-loading-modal" class="quiz-modal" style="display: none;">
            <div class="modal-content">
                <div class="loading-spinner"></div>
                <p><?php _e('Processing your answers...', 'elearning-quiz'); ?></p>
            </div>
        </div>
        
        <div id="quiz-confirmation-modal" class="quiz-modal" style="display: none;">
            <div class="modal-content">
                <h3><?php _e('Submit Quiz?', 'elearning-quiz'); ?></h3>
                <p><?php _e('Are you sure you want to submit your answers? You cannot change them after submission.', 'elearning-quiz'); ?></p>
                <div class="modal-buttons">
                    <button type="button" class="button secondary" id="cancel-submit"><?php _e('Cancel', 'elearning-quiz'); ?></button>
                    <button type="button" class="button primary" id="confirm-submit"><?php _e('Submit Quiz', 'elearning-quiz'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}