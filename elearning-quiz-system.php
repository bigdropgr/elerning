<?php
/**
 * Plugin Name: E-Learning Quiz System
 * Plugin URI: https://yoursite.com
 * Description: A comprehensive e-learning system with lessons, quizzes, and analytics for WordPress.
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: elearning-quiz
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.6
 * Requires PHP: 8.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELEARNING_QUIZ_VERSION', '1.1.0'); // Increment this
define('ELEARNING_QUIZ_PLUGIN_FILE', __FILE__);
define('ELEARNING_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ELEARNING_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ELEARNING_QUIZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check PHP version
if (version_compare(PHP_VERSION, '8.2', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__('E-Learning Quiz System requires PHP 8.2 or higher. You are running PHP %s.', 'elearning-quiz'),
            PHP_VERSION
        );
        echo '</p></div>';
    });
    return;
}

/**
 * Main plugin class
 */
class ELearningQuizSystem {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init(): void {
        // Load plugin dependencies
        $this->loadDependencies();
        
        // Initialize hooks
        $this->initHooks();
        
        // Initialize components
        $this->initComponents();
    }
    
    /**
     * Load plugin dependencies
     */
    private function loadDependencies(): void {
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-database.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-admin.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-user-roles.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-accessibility.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void {
        register_activation_hook(ELEARNING_QUIZ_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(ELEARNING_QUIZ_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(ELEARNING_QUIZ_PLUGIN_FILE, [__CLASS__, 'uninstall']);
        
        add_action('init', [$this, 'loadTextdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }
    
    /**
     * Initialize plugin components
     */
    private function initComponents(): void {
        // Initialize post types
        new ELearning_Post_Types();
        
        // Initialize database
        new ELearning_Database();
        
        // Initialize admin interface
        if (is_admin()) {
            new ELearning_Admin();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            new ELearning_Frontend();
        }
        
        // Initialize AJAX handlers
        new ELearning_Ajax();
        
        // Initialize shortcodes
        new ELearning_Shortcodes();
        
        // Initialize user roles
        new ELearning_User_Roles();
        
        // Initialize analytics
        new ELearning_Analytics();
        
        // Initialize accessibility features
        new ELearning_Accessibility();
    }
    
    /**
     * Plugin activation
     */
    public function activate(): void {
        // Create database tables
        ELearning_Database::createTables();
        
        // Add user roles
        ELearning_User_Roles::addRoles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set plugin version
        update_option('elearning_quiz_version', ELEARNING_QUIZ_VERSION);
        
        // FORCE CACHE CLEAR ON ACTIVATION
        $this->clearAllCaches();
    }
    
    /**
     * Clear all caches when plugin is updated
     */
    private function clearAllCaches(): void {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear any persistent caches if available
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // Clear opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Set a transient to force frontend cache clearing
        set_transient('elearning_quiz_force_cache_clear', time(), 3600); // 1 hour
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall(): void {
        // Remove database tables
        ELearning_Database::dropTables();
        
        // Remove user roles
        ELearning_User_Roles::removeRoles();
        
        // Remove plugin options
        delete_option('elearning_quiz_version');
        delete_option('elearning_quiz_settings');
        
        // Clear any cached data
        wp_cache_flush();
    }
    
    /**
     * Load plugin textdomain
     */
    public function loadTextdomain(): void {
        load_plugin_textdomain(
            'elearning-quiz',
            false,
            dirname(ELEARNING_QUIZ_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueueScripts(): void {
        // Use filemtime for automatic cache busting based on file modification time
        $css_version = $this->getFileVersion('assets/css/frontend.css');
        $js_version = $this->getFileVersion('assets/js/frontend.js');
        
        // Main frontend stylesheet
        wp_enqueue_style(
            'elearning-quiz-frontend',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            $css_version
        );
        
        // Main frontend script
        wp_enqueue_script(
            'elearning-quiz-frontend',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'],
            $js_version,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('elearning-quiz-frontend', 'elearningQuiz', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elearning_quiz_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'elearning-quiz'),
                'error' => __('An error occurred. Please try again.', 'elearning-quiz'),
                'confirm_submit' => __('Are you sure you want to submit your answers?', 'elearning-quiz'),
                'drag_here' => __('Drag answer here', 'elearning-quiz'),
                'correct_answer' => __('Correct Answer', 'elearning-quiz'),
                'wrong_answer' => __('Wrong Answer', 'elearning-quiz'),
                'congratulations' => __('Congratulations!', 'elearning-quiz'),
                'quiz_passed' => __('You have successfully passed this quiz.', 'elearning-quiz'),
                'try_again' => __('Try Again', 'elearning-quiz'),
                'quiz_failed' => __('You did not pass this quiz. Please review the material and try again.', 'elearning-quiz'),
                'correct_answers' => __('Correct Answers', 'elearning-quiz'),
                'passing_score' => __('Passing Score', 'elearning-quiz'),
                'time_taken' => __('Time Taken', 'elearning-quiz'),
                'retry_quiz' => __('Retry Quiz', 'elearning-quiz'),
                'review_answers' => __('Review Your Answers', 'elearning-quiz'),
                'your_answer' => __('Your Answer', 'elearning-quiz'),
                'no_answer' => __('No answer provided', 'elearning-quiz'),
                'skip_to_quiz' => __('Skip to quiz content', 'elearning-quiz'),
                'leave_warning' => __('You have unsaved progress. Are you sure you want to leave?', 'elearning-quiz'),
            ]
        ]);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts($hook): void {
        // Only load on our plugin pages
        if (strpos($hook, 'elearning-quiz') === false && 
            !in_array(get_post_type(), ['elearning_lesson', 'elearning_quiz'])) {
            return;
        }
        
        // Get file versions for cache busting
        $admin_css_version = $this->getFileVersion('assets/css/admin.css');
        $admin_js_version = $this->getFileVersion('assets/js/admin.js');
        
        // Admin stylesheet
        wp_enqueue_style(
            'elearning-quiz-admin',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $admin_css_version
        );
        
        // Admin script
        wp_enqueue_script(
            'elearning-quiz-admin',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            $admin_js_version,
            true
        );
        
        // Chart.js for analytics
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Localize admin script
        wp_localize_script('elearning-quiz-admin', 'elearningQuizAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elearning_quiz_admin_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'elearning-quiz'),
                'add_question' => __('Add Question', 'elearning-quiz'),
                'remove_question' => __('Remove Question', 'elearning-quiz'),
                'add_option' => __('Add Option', 'elearning-quiz'),
                'remove_option' => __('Remove Option', 'elearning-quiz'),
                'add_word' => __('Add Word', 'elearning-quiz'),
                'remove' => __('Remove', 'elearning-quiz'),
                'option_text' => __('Option text', 'elearning-quiz'),
                'correct' => __('Correct', 'elearning-quiz'),
                'word' => __('Word', 'elearning-quiz'),
                'section' => __('Section', 'elearning-quiz'),
                'question' => __('Question', 'elearning-quiz'),
                'options' => __('Options', 'elearning-quiz'),
                'text_with_blanks' => __('Text with Blanks', 'elearning-quiz'),
                'blank_instruction' => __('Use {{blank}} to mark where blanks should appear.', 'elearning-quiz'),
                'word_bank' => __('Word Bank', 'elearning-quiz'),
                'correct_answer' => __('Correct Answer', 'elearning-quiz'),
                'true_option' => __('True', 'elearning-quiz'),
                'false_option' => __('False', 'elearning-quiz'),
                'matching_instruction' => __('Matching question setup coming in Phase 2!', 'elearning-quiz'),
                'left_column' => __('Left Column', 'elearning-quiz'),
                'right_column' => __('Right Column', 'elearning-quiz'),
                'left_item' => __('Left item', 'elearning-quiz'),
                'right_item' => __('Right item', 'elearning-quiz'),
                'add_left_item' => __('Add Left Item', 'elearning-quiz'),
                'add_right_item' => __('Add Right Item', 'elearning-quiz'),
                'correct_matches' => __('Correct Matches', 'elearning-quiz'),
                'add_match' => __('Add Match', 'elearning-quiz'),
                'select_left' => __('Select left item', 'elearning-quiz'),
                'select_right' => __('Select right item', 'elearning-quiz'),
                'matches_with' => __('matches with', 'elearning-quiz'),
            ]
        ]);
    }
    
    /**
     * Get file version for cache busting
     * Combines plugin version with file modification time
     */
    private function getFileVersion(string $file_path): string {
        $full_path = ELEARNING_QUIZ_PLUGIN_DIR . $file_path;
        
        if (file_exists($full_path)) {
            // Use plugin version + file modification time for maximum cache busting
            return ELEARNING_QUIZ_VERSION . '-' . filemtime($full_path);
        }
        
        // Fallback to plugin version if file doesn't exist
        return ELEARNING_QUIZ_VERSION;
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    ELearningQuizSystem::getInstance();
});

// Plugin activation hook - needs to be outside the class for proper execution
register_activation_hook(__FILE__, function() {
    // Ensure the class is loaded
    if (!class_exists('ELearningQuizSystem')) {
        require_once __FILE__;
    }
    ELearningQuizSystem::getInstance()->activate();
});

// Additional cache busting functions outside the class
add_action('init', 'elearning_quiz_check_version_update');

function elearning_quiz_check_version_update() {
    $current_version = get_option('elearning_quiz_version', '0');
    
    if (version_compare($current_version, ELEARNING_QUIZ_VERSION, '<')) {
        // Version has been updated, clear caches
        wp_cache_flush();
        
        // Update stored version
        update_option('elearning_quiz_version', ELEARNING_QUIZ_VERSION);
        
        // Set transient to force cache clearing on frontend
        set_transient('elearning_quiz_cache_bust', time(), 3600);
        
        // Show admin notice
        if (is_admin() && !wp_doing_ajax()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>E-Learning Quiz System:</strong> Plugin updated to version ' . ELEARNING_QUIZ_VERSION . '! Cache cleared automatically.</p>';
                echo '</div>';
            });
        }
    }
}

// Emergency cache clearing function
function elearning_quiz_force_cache_clear() {
    // Clear WordPress caches
    wp_cache_flush();
    
    // Clear transients
    delete_transient('elearning_quiz_cache_bust');
    set_transient('elearning_quiz_cache_bust', time(), 3600);
    
    // Clear any plugin-specific caches
    delete_option('elearning_quiz_cached_data');
    
    return true;
}

// Development helper - add admin bar link to clear cache
add_action('admin_bar_menu', 'elearning_quiz_add_cache_clear_button', 999);

function elearning_quiz_add_cache_clear_button($wp_admin_bar) {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node([
        'id' => 'elearning-cache-clear',
        'title' => 'Clear E-Learning Cache',
        'href' => wp_nonce_url(admin_url('admin.php?action=elearning_clear_cache'), 'elearning_clear_cache'),
        'meta' => ['class' => 'elearning-cache-clear']
    ]);
}

// Handle cache clear action
add_action('admin_action_elearning_clear_cache', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'elearning_clear_cache')) {
        wp_die('Security check failed');
    }
    
    elearning_quiz_force_cache_clear();
    
    // Show success message
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Cache cleared successfully!</strong> All plugin caches have been cleared.</p>';
        echo '</div>';
    });
    
    wp_redirect(wp_get_referer() ?: admin_url());
    exit;
});