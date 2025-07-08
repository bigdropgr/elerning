<?php
/**
 * Admin Class - Placeholder
 * 
 * This is a placeholder file to prevent fatal errors during plugin activation
 * Full implementation will be added in Phase 2
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Admin {
    
    public function __construct() {
        // Placeholder - full implementation coming in Phase 2
        add_action('admin_menu', [$this, 'addAdminMenus']);
    }
    
    /**
     * Add admin menus - basic placeholder
     */
    public function addAdminMenus(): void {
        add_menu_page(
            __('E-Learning Quiz', 'elearning-quiz'),
            __('E-Learning Quiz', 'elearning-quiz'),
            'manage_options',
            'elearning-quiz',
            [$this, 'renderDashboard'],
            'dashicons-welcome-learn-more',
            30
        );
    }
    
    /**
     * Render dashboard placeholder
     */
    public function renderDashboard(): void {
        echo '<div class="wrap">';
        echo '<h1>' . __('E-Learning Quiz System', 'elearning-quiz') . '</h1>';
        echo '<p>' . __('Plugin activated successfully! Full admin interface coming in Phase 2.', 'elearning-quiz') . '</p>';
        echo '<p>' . __('You can now create Lessons and Quizzes from the WordPress admin menu.', 'elearning-quiz') . '</p>';
        echo '</div>';
    }
}