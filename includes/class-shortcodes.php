<?php
/**
 * Shortcodes Class - Placeholder
 * 
 * This is a placeholder file to prevent fatal errors during plugin activation
 * Full implementation will be added in Phase 2
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Shortcodes {
    
    public function __construct() {
        // Placeholder - full implementation coming in Phase 2
        add_shortcode('loan_calculator', [$this, 'loanCalculatorPlaceholder']);
        add_shortcode('display_lesson', [$this, 'displayLessonPlaceholder']);
        add_shortcode('display_quiz', [$this, 'displayQuizPlaceholder']);
    }
    
    /**
     * Loan calculator placeholder
     */
    public function loanCalculatorPlaceholder($atts): string {
        return '<div class="elearning-placeholder"><p>' . __('Loan Calculator coming in Phase 2!', 'elearning-quiz') . '</p></div>';
    }
    
    /**
     * Display lesson placeholder
     */
    public function displayLessonPlaceholder($atts): string {
        return '<div class="elearning-placeholder"><p>' . __('Lesson shortcode coming in Phase 2!', 'elearning-quiz') . '</p></div>';
    }
    
    /**
     * Display quiz placeholder
     */
    public function displayQuizPlaceholder($atts): string {
        return '<div class="elearning-placeholder"><p>' . __('Quiz shortcode coming in Phase 2!', 'elearning-quiz') . '</p></div>';
    }
}