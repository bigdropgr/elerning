<?php
/**
 * Debug Helper - Temporary file to help identify issues
 * Add this to your wp-config.php to enable debugging:
 * 
 * define('WP_DEBUG', true);
 * define('WP_DEBUG_LOG', true);
 * define('WP_DEBUG_DISPLAY', false);
 */

// Enable error reporting for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// Simple function to log debug info
function elearning_debug_log($message) {
    if (function_exists('error_log')) {
        error_log('E-Learning Debug: ' . print_r($message, true));
    }
}

// Add action to check if meta boxes are loading
add_action('add_meta_boxes', function() {
    elearning_debug_log('Meta boxes action triggered');
});

// Check if post types are registered
add_action('init', function() {
    if (post_type_exists('elearning_lesson')) {
        elearning_debug_log('Lesson post type registered successfully');
    } else {
        elearning_debug_log('ERROR: Lesson post type NOT registered');
    }
    
    if (post_type_exists('elearning_quiz')) {
        elearning_debug_log('Quiz post type registered successfully');
    } else {
        elearning_debug_log('ERROR: Quiz post type NOT registered');
    }
}, 999);