<?php
/**
 * Shortcodes Class
 * 
 * Handles all shortcodes for the e-learning system including loan calculator
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Shortcodes {
    
    public function __construct() {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueShortcodeAssets']);
    }
    
    /**
     * Register all shortcodes
     */
    public function registerShortcodes(): void {
        add_shortcode('loan_calculator', [$this, 'loanCalculator']);
        add_shortcode('display_lesson', [$this, 'displayLesson']);
        add_shortcode('display_quiz', [$this, 'displayQuiz']);
        add_shortcode('quiz_stats', [$this, 'displayQuizStats']);
        add_shortcode('user_progress', [$this, 'displayUserProgress']);
    }
    
    /**
     * Enqueue assets when shortcodes are used
     */
    public function enqueueShortcodeAssets(): void {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $content = $post->post_content;
        
        // Check if any of our shortcodes are used
        $shortcodes = ['loan_calculator', 'display_lesson', 'display_quiz', 'quiz_stats', 'user_progress'];
        $has_shortcode = false;
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                $has_shortcode = true;
                break;
            }
        }
        
        if ($has_shortcode) {
            // Enqueue calculator styles and scripts
            wp_enqueue_style(
                'elearning-shortcodes',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/shortcodes.css',
                [],
                ELEARNING_QUIZ_VERSION
            );
            
            wp_enqueue_script(
                'elearning-shortcodes',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/shortcodes.js',
                ['jquery'],
                ELEARNING_QUIZ_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('elearning-shortcodes', 'elearningShortcodes', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('elearning_shortcode_nonce'),
                'strings' => [
                    'calculate' => __('Calculate', 'elearning-quiz'),
                    'monthly_payment' => __('Monthly Payment', 'elearning-quiz'),
                    'total_payment' => __('Total Payment', 'elearning-quiz'),
                    'total_interest' => __('Total Interest', 'elearning-quiz'),
                    'error_invalid_input' => __('Please enter valid numbers', 'elearning-quiz'),
                    'error_negative_values' => __('Values cannot be negative', 'elearning-quiz'),
                    'loading' => __('Loading...', 'elearning-quiz')
                ]
            ]);
        }
    }
    
    /**
     * Loan Calculator Shortcode
     * Usage: [loan_calculator]
     * Attributes: 
     *   - title: Custom title for the calculator
     *   - currency: Currency symbol (default: €)
     *   - theme: light or dark (default: light)
     */
    public function loanCalculator($atts): string {
        $atts = shortcode_atts([
            'title' => __('Loan Calculator', 'elearning-quiz'),
            'currency' => '€',
            'theme' => 'light'
        ], $atts, 'loan_calculator');
        
        $calculator_id = 'loan-calculator-' . wp_generate_password(8, false, false);
        $theme_class = $atts['theme'] === 'dark' ? 'loan-calculator-dark' : 'loan-calculator-light';
        
        ob_start();
        ?>
        <div class="loan-calculator-container <?php echo esc_attr($theme_class); ?>" id="<?php echo esc_attr($calculator_id); ?>">
            <div class="loan-calculator">
                <h3 class="calculator-title"><?php echo esc_html($atts['title']); ?></h3>
                
                <form class="loan-form" data-currency="<?php echo esc_attr($atts['currency']); ?>">
                    <div class="form-group">
                        <label for="loan-amount-<?php echo esc_attr($calculator_id); ?>">
                            <?php _e('Loan Amount', 'elearning-quiz'); ?>
                            <span class="currency">(<?php echo esc_html($atts['currency']); ?>)</span>
                        </label>
                        <input 
                            type="number" 
                            id="loan-amount-<?php echo esc_attr($calculator_id); ?>" 
                            name="loan_amount" 
                            class="loan-input" 
                            placeholder="100000" 
                            min="1" 
                            step="1"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label for="interest-rate-<?php echo esc_attr($calculator_id); ?>">
                            <?php _e('Annual Interest Rate', 'elearning-quiz'); ?>
                            <span class="unit">(%)</span>
                        </label>
                        <input 
                            type="number" 
                            id="interest-rate-<?php echo esc_attr($calculator_id); ?>" 
                            name="interest_rate" 
                            class="loan-input" 
                            placeholder="3.5" 
                            min="0.01" 
                            max="50" 
                            step="0.01"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loan-term-<?php echo esc_attr($calculator_id); ?>">
                            <?php _e('Loan Term', 'elearning-quiz'); ?>
                            <span class="unit">(<?php _e('years', 'elearning-quiz'); ?>)</span>
                        </label>
                        <input 
                            type="number" 
                            id="loan-term-<?php echo esc_attr($calculator_id); ?>" 
                            name="loan_term" 
                            class="loan-input" 
                            placeholder="20" 
                            min="1" 
                            max="50" 
                            step="1"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="calculate-btn" onclick="calculateLoan('<?php echo esc_js($calculator_id); ?>')">
                            <span class="btn-text"><?php _e('Calculate', 'elearning-quiz'); ?></span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner"></span>
                                <?php _e('Calculating...', 'elearning-quiz'); ?>
                            </span>
                        </button>
                    </div>
                </form>
                
                <div class="loan-results" style="display: none;">
                    <h4><?php _e('Calculation Results', 'elearning-quiz'); ?></h4>
                    
                    <div class="results-grid">
                        <div class="result-item highlight">
                            <span class="result-label"><?php _e('Monthly Payment', 'elearning-quiz'); ?></span>
                            <span class="result-value monthly-payment">-</span>
                        </div>
                        
                        <div class="result-item">
                            <span class="result-label"><?php _e('Total Payment', 'elearning-quiz'); ?></span>
                            <span class="result-value total-payment">-</span>
                        </div>
                        
                        <div class="result-item">
                            <span class="result-label"><?php _e('Total Interest', 'elearning-quiz'); ?></span>
                            <span class="result-value total-interest">-</span>
                        </div>
                        
                        <div class="result-item">
                            <span class="result-label"><?php _e('Interest Rate', 'elearning-quiz'); ?></span>
                            <span class="result-value interest-display">-</span>
                        </div>
                    </div>
                    
                    <div class="amortization-summary">
                        <button type="button" class="toggle-breakdown" onclick="toggleBreakdown('<?php echo esc_js($calculator_id); ?>')">
                            <?php _e('Show Payment Breakdown', 'elearning-quiz'); ?>
                        </button>
                        
                        <div class="payment-breakdown" style="display: none;">
                            <div class="breakdown-chart">
                                <div class="chart-container">
                                    <div class="chart-bar">
                                        <div class="principal-bar" style="width: 0%"></div>
                                        <div class="interest-bar" style="width: 0%"></div>
                                    </div>
                                    <div class="chart-legend">
                                        <div class="legend-item">
                                            <span class="legend-color principal"></span>
                                            <span class="legend-text"><?php _e('Principal', 'elearning-quiz'); ?></span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color interest"></span>
                                            <span class="legend-text"><?php _e('Interest', 'elearning-quiz'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="error-message" style="display: none;">
                    <span class="error-text"></span>
                </div>
            </div>
        </div>
        
        <script>
        function calculateLoan(calculatorId) {
            const container = document.getElementById(calculatorId);
            const form = container.querySelector('.loan-form');
            const resultsDiv = container.querySelector('.loan-results');
            const errorDiv = container.querySelector('.error-message');
            const button = container.querySelector('.calculate-btn');
            const btnText = button.querySelector('.btn-text');
            const btnLoading = button.querySelector('.btn-loading');
            
            // Get form values
            const loanAmount = parseFloat(form.querySelector('[name="loan_amount"]').value);
            const interestRate = parseFloat(form.querySelector('[name="interest_rate"]').value);
            const loanTerm = parseFloat(form.querySelector('[name="loan_term"]').value);
            const currency = form.dataset.currency;
            
            // Hide previous results/errors
            resultsDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            
            // Validate inputs
            if (isNaN(loanAmount) || isNaN(interestRate) || isNaN(loanTerm)) {
                showError(errorDiv, '<?php echo esc_js(__('Please enter valid numbers', 'elearning-quiz')); ?>');
                return;
            }
            
            if (loanAmount <= 0 || interestRate < 0 || loanTerm <= 0) {
                showError(errorDiv, '<?php echo esc_js(__('Please enter positive values', 'elearning-quiz')); ?>');
                return;
            }
            
            // Show loading state
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';
            button.disabled = true;
            
            // Simulate calculation delay for better UX
            setTimeout(() => {
                try {
                    // Calculate loan
                    const monthlyRate = interestRate / 100 / 12;
                    const numPayments = loanTerm * 12;
                    
                    const monthlyPayment = (loanAmount * monthlyRate * Math.pow(1 + monthlyRate, numPayments)) / 
                                         (Math.pow(1 + monthlyRate, numPayments) - 1);
                    
                    const totalPayment = monthlyPayment * numPayments;
                    const totalInterest = totalPayment - loanAmount;
                    
                    // Display results
                    displayResults(container, {
                        monthlyPayment: monthlyPayment,
                        totalPayment: totalPayment,
                        totalInterest: totalInterest,
                        interestRate: interestRate,
                        loanAmount: loanAmount,
                        currency: currency
                    });
                    
                    resultsDiv.style.display = 'block';
                    
                } catch (error) {
                    showError(errorDiv, '<?php echo esc_js(__('Calculation error occurred', 'elearning-quiz')); ?>');
                }
                
                // Reset button state
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                button.disabled = false;
            }, 500);
        }
        
        function displayResults(container, results) {
            const formatCurrency = (amount, currency) => {
                return new Intl.NumberFormat('en-US', {
                    style: 'decimal',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(amount) + ' ' + currency;
            };
            
            container.querySelector('.monthly-payment').textContent = formatCurrency(results.monthlyPayment, results.currency);
            container.querySelector('.total-payment').textContent = formatCurrency(results.totalPayment, results.currency);
            container.querySelector('.total-interest').textContent = formatCurrency(results.totalInterest, results.currency);
            container.querySelector('.interest-display').textContent = results.interestRate + '%';
            
            // Update breakdown chart
            const principalPercentage = (results.loanAmount / results.totalPayment) * 100;
            const interestPercentage = (results.totalInterest / results.totalPayment) * 100;
            
            container.querySelector('.principal-bar').style.width = principalPercentage + '%';
            container.querySelector('.interest-bar').style.width = interestPercentage + '%';
        }
        
        function showError(errorDiv, message) {
            errorDiv.querySelector('.error-text').textContent = message;
            errorDiv.style.display = 'block';
        }
        
        function toggleBreakdown(calculatorId) {
            const container = document.getElementById(calculatorId);
            const breakdown = container.querySelector('.payment-breakdown');
            const button = container.querySelector('.toggle-breakdown');
            
            if (breakdown.style.display === 'none') {
                breakdown.style.display = 'block';
                button.textContent = '<?php echo esc_js(__('Hide Payment Breakdown', 'elearning-quiz')); ?>';
            } else {
                breakdown.style.display = 'none';
                button.textContent = '<?php echo esc_js(__('Show Payment Breakdown', 'elearning-quiz')); ?>';
            }
        }
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display Lesson Shortcode
     * Usage: [display_lesson id="123"]
     */
    public function displayLesson($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'show_progress' => 'true',
            'show_quiz_link' => 'true'
        ], $atts, 'display_lesson');
        
        $lesson_id = intval($atts['id']);
        
        if (!$lesson_id) {
            return '<div class="elearning-error">' . __('Please specify a lesson ID.', 'elearning-quiz') . '</div>';
        }
        
        $lesson = get_post($lesson_id);
        
        if (!$lesson || $lesson->post_type !== 'elearning_lesson') {
            return '<div class="elearning-error">' . __('Lesson not found.', 'elearning-quiz') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="embedded-lesson" data-lesson-id="<?php echo esc_attr($lesson_id); ?>">
            <div class="lesson-header">
                <h3 class="lesson-title">
                    <a href="<?php echo get_permalink($lesson_id); ?>"><?php echo esc_html($lesson->post_title); ?></a>
                </h3>
                
                <?php if ($atts['show_progress'] === 'true'): ?>
                    <div class="lesson-progress-indicator">
                        <?php echo $this->getLessonProgressIndicator($lesson_id); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="lesson-excerpt">
                <?php echo wp_trim_words($lesson->post_excerpt ?: $lesson->post_content, 30); ?>
            </div>
            
            <div class="lesson-actions">
                <a href="<?php echo get_permalink($lesson_id); ?>" class="button lesson-btn">
                    <?php _e('View Lesson', 'elearning-quiz'); ?>
                </a>
                
                <?php if ($atts['show_quiz_link'] === 'true'): ?>
                    <?php
                    $associated_quiz = get_post_meta($lesson_id, '_associated_quiz', true);
                    if ($associated_quiz):
                    ?>
                        <a href="<?php echo get_permalink($associated_quiz); ?>" class="button quiz-btn">
                            <?php _e('Take Quiz', 'elearning-quiz'); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display Quiz Shortcode
     * Usage: [display_quiz id="123"]
     */
    public function displayQuiz($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'show_stats' => 'false',
            'show_description' => 'true'
        ], $atts, 'display_quiz');
        
        $quiz_id = intval($atts['id']);
        
        if (!$quiz_id) {
            return '<div class="elearning-error">' . __('Please specify a quiz ID.', 'elearning-quiz') . '</div>';
        }
        
        $quiz = get_post($quiz_id);
        
        if (!$quiz || $quiz->post_type !== 'elearning_quiz') {
            return '<div class="elearning-error">' . __('Quiz not found.', 'elearning-quiz') . '</div>';
        }
        
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        $passing_score = get_post_meta($quiz_id, '_passing_score', true) ?: 70;
        
        ob_start();
        ?>
        <div class="embedded-quiz" data-quiz-id="<?php echo esc_attr($quiz_id); ?>">
            <div class="quiz-header">
                <h3 class="quiz-title">
                    <a href="<?php echo get_permalink($quiz_id); ?>"><?php echo esc_html($quiz->post_title); ?></a>
                </h3>
                
                <div class="quiz-meta">
                    <span class="quiz-questions"><?php echo count($questions); ?> <?php _e('Questions', 'elearning-quiz'); ?></span>
                    <span class="quiz-passing"><?php echo $passing_score; ?>% <?php _e('to Pass', 'elearning-quiz'); ?></span>
                </div>
            </div>
            
            <?php if ($atts['show_description'] === 'true' && $quiz->post_excerpt): ?>
                <div class="quiz-description">
                    <?php echo wp_kses_post($quiz->post_excerpt); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_stats'] === 'true'): ?>
                <div class="quiz-stats-summary">
                    <?php echo $this->getQuizStatsSummary($quiz_id); ?>
                </div>
            <?php endif; ?>
            
            <div class="quiz-actions">
                <a href="<?php echo get_permalink($quiz_id); ?>" class="button quiz-start-btn">
                    <?php _e('Start Quiz', 'elearning-quiz'); ?>
                </a>
                
                <?php
                $associated_lesson = get_post_meta($quiz_id, '_associated_lesson', true);
                if ($associated_lesson):
                ?>
                    <a href="<?php echo get_permalink($associated_lesson); ?>" class="button lesson-link-btn">
                        <?php _e('Study Lesson First', 'elearning-quiz'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display Quiz Statistics Shortcode
     * Usage: [quiz_stats id="123" type="basic"]
     */
    public function displayQuizStats($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'type' => 'basic', // basic, detailed, chart
            'show_language' => 'true'
        ], $atts, 'quiz_stats');
        
        $quiz_id = intval($atts['id']);
        
        if (!$quiz_id) {
            return '<div class="elearning-error">' . __('Please specify a quiz ID.', 'elearning-quiz') . '</div>';
        }
        
        $stats = ELearning_Database::getQuizStatistics($quiz_id);
        
        if (empty($stats)) {
            return '<div class="elearning-notice">' . __('No statistics available for this quiz yet.', 'elearning-quiz') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="quiz-statistics" data-quiz-id="<?php echo esc_attr($quiz_id); ?>">
            <h4><?php _e('Quiz Statistics', 'elearning-quiz'); ?></h4>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['total_attempts']); ?></span>
                    <span class="stat-label"><?php _e('Total Attempts', 'elearning-quiz'); ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['passed_attempts']); ?></span>
                    <span class="stat-label"><?php _e('Passed', 'elearning-quiz'); ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['average_score'], 1); ?>%</span>
                    <span class="stat-label"><?php _e('Average Score', 'elearning-quiz'); ?></span>
                </div>
                
                <?php if ($atts['show_language'] === 'true'): ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($stats['english_attempts']); ?> / <?php echo number_format($stats['greek_attempts']); ?></span>
                        <span class="stat-label"><?php _e('EN / GR', 'elearning-quiz'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($atts['type'] === 'detailed'): ?>
                <div class="detailed-stats">
                    <div class="stat-row">
                        <span class="stat-label"><?php _e('Completion Rate:', 'elearning-quiz'); ?></span>
                        <span class="stat-value">
                            <?php 
                            $completion_rate = $stats['total_attempts'] > 0 ? ($stats['completed_attempts'] / $stats['total_attempts']) * 100 : 0;
                            echo number_format($completion_rate, 1); 
                            ?>%
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label"><?php _e('Pass Rate:', 'elearning-quiz'); ?></span>
                        <span class="stat-value">
                            <?php 
                            $pass_rate = $stats['completed_attempts'] > 0 ? ($stats['passed_attempts'] / $stats['completed_attempts']) * 100 : 0;
                            echo number_format($pass_rate, 1); 
                            ?>%
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label"><?php _e('Highest Score:', 'elearning-quiz'); ?></span>
                        <span class="stat-value"><?php echo number_format($stats['highest_score'], 1); ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label"><?php _e('Lowest Score:', 'elearning-quiz'); ?></span>
                        <span class="stat-value"><?php echo number_format($stats['lowest_score'], 1); ?>%</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display User Progress Shortcode
     * Usage: [user_progress]
     */
    public function displayUserProgress($atts): string {
        $atts = shortcode_atts([
            'show_quizzes' => 'true',
            'show_lessons' => 'true',
            'limit' => 5
        ], $atts, 'user_progress');
        
        $user_session = ELearning_Database::getOrCreateUserSession();
        $limit = intval($atts['limit']);
        
        ob_start();
        ?>
        <div class="user-progress-widget">
            <h4><?php _e('Your Progress', 'elearning-quiz'); ?></h4>
            
            <?php if ($atts['show_quizzes'] === 'true'): ?>
                <div class="progress-section quiz-progress">
                    <h5><?php _e('Recent Quiz Attempts', 'elearning-quiz'); ?></h5>
                    <?php echo $this->getUserRecentQuizzes($user_session, $limit); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_lessons'] === 'true'): ?>
                <div class="progress-section lesson-progress">
                    <h5><?php _e('Lesson Progress', 'elearning-quiz'); ?></h5>
                    <?php echo $this->getUserLessonProgress($user_session, $limit); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Helper: Get lesson progress indicator
     */
    private function getLessonProgressIndicator($lesson_id): string {
        $user_session = ELearning_Database::getOrCreateUserSession();
        $progress = ELearning_Database::getLessonProgress($lesson_id, $user_session);
        
        $total_sections = count(get_post_meta($lesson_id, '_lesson_sections', true) ?: []);
        $completed_sections = 0;
        
        foreach ($progress as $section_progress) {
            if (!empty($section_progress['completed'])) {
                $completed_sections++;
            }
        }
        
        $percentage = $total_sections > 0 ? ($completed_sections / $total_sections) * 100 : 0;
        
        return sprintf(
            '<div class="progress-bar"><div class="progress-fill" style="width: %d%%"></div></div><span class="progress-text">%d/%d %s</span>',
            $percentage,
            $completed_sections,
            $total_sections,
            __('sections', 'elearning-quiz')
        );
    }
    
    /**
     * Helper: Get quiz stats summary
     */
    private function getQuizStatsSummary($quiz_id): string {
        $stats = ELearning_Database::getQuizStatistics($quiz_id);
        
        if (empty($stats)) {
            return '<p>' . __('No attempts yet.', 'elearning-quiz') . '</p>';
        }
        
        $pass_rate = $stats['completed_attempts'] > 0 ? ($stats['passed_attempts'] / $stats['completed_attempts']) * 100 : 0;
        
        return sprintf(
            '<div class="stats-mini"><span>%d %s</span> <span>%.1f%% %s</span> <span>%.1f%% %s</span></div>',
            $stats['total_attempts'],
            __('attempts', 'elearning-quiz'),
            $pass_rate,
            __('pass rate', 'elearning-quiz'),
            $stats['average_score'],
            __('avg score', 'elearning-quiz')
        );
    }
    
    /**
     * Helper: Get user recent quizzes
     */
    private function getUserRecentQuizzes($user_session, $limit): string {
        $attempts = ELearning_Database::getUserQuizAttempts($user_session);
        $attempts = array_slice($attempts, 0, $limit);
        
        if (empty($attempts)) {
            return '<p>' . __('No quiz attempts yet.', 'elearning-quiz') . '</p>';
        }
        
        $output = '<ul class="progress-list">';
        foreach ($attempts as $attempt) {
            $quiz_title = get_the_title($attempt['quiz_id']);
            $status_class = $attempt['passed'] ? 'passed' : 'failed';
            $status_text = $attempt['passed'] ? __('Passed', 'elearning-quiz') : __('Failed', 'elearning-quiz');
            
            $output .= sprintf(
                '<li class="progress-item %s"><span class="item-title">%s</span><span class="item-status">%s (%.1f%%)</span></li>',
                $status_class,
                esc_html($quiz_title),
                $status_text,
                $attempt['score']
            );
        }
        $output .= '</ul>';
        
        return $output;
    }
    
    /**
     * Helper: Get user lesson progress
     */
    private function getUserLessonProgress($user_session, $limit): string {
        global $wpdb;
        
        $progress_table = $wpdb->prefix . 'elearning_lesson_progress';
        
        $lesson_progress = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                lesson_id,
                COUNT(*) as total_sections,
                COUNT(CASE WHEN completed = 1 THEN 1 END) as completed_sections
             FROM $progress_table 
             WHERE user_session = %s
             GROUP BY lesson_id
             ORDER BY MAX(updated_at) DESC
             LIMIT %d",
            $user_session,
            $limit
        ), ARRAY_A);
        
        if (empty($lesson_progress)) {
            return '<p>' . __('No lessons started yet.', 'elearning-quiz') . '</p>';
        }
        
        $output = '<ul class="progress-list">';
        foreach ($lesson_progress as $progress) {
            $lesson_title = get_the_title($progress['lesson_id']);
            $percentage = $progress['total_sections'] > 0 ? ($progress['completed_sections'] / $progress['total_sections']) * 100 : 0;
            $status_class = $percentage == 100 ? 'completed' : 'in-progress';
            
            $output .= sprintf(
                '<li class="progress-item %s"><span class="item-title">%s</span><span class="item-progress">%d%% (%d/%d)</span></li>',
                $status_class,
                esc_html($lesson_title),
                $percentage,
                $progress['completed_sections'],
                $progress['total_sections']
            );
        }
        $output .= '</ul>';
        
        return $output;
    }
}