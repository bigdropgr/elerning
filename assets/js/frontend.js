/* E-Learning Quiz System - Frontend JavaScript */
/* Interactive quiz functionality with accessibility support */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('E-Learning Quiz System frontend loaded');
    
    // Quiz state management
    let currentQuiz = {
        id: null,
        attemptId: null,
        currentQuestion: 0,
        totalQuestions: 0,
        answers: {},
        startTime: null,
        questionStartTime: null
    };
    
    // Initialize quiz functionality
    initializeQuiz();
    
    function initializeQuiz() {
        // Start quiz button
        $('.start-quiz-btn').on('click', handleStartQuiz);
        
        // Retake quiz button
        $('.retake-quiz-btn').on('click', handleRetakeQuiz);
        
        // Navigation buttons
        $('.prev-btn').on('click', handlePreviousQuestion);
        $('.next-btn').on('click', handleNextQuestion);
        $('.quiz-submit-btn').on('click', handleSubmitQuiz);
        
        // Answer change handlers
        $(document).on('change', '.quiz-question input[type="radio"], .quiz-question input[type="checkbox"]', handleAnswerChange);
        $(document).on('change', '.match-select', handleMatchingChange);
        
        // Drag and drop for fill-in-the-blanks
        initializeDragAndDrop();
        
        // Keyboard navigation
        $(document).on('keydown', handleKeyboardNavigation);
        
        // Modal handlers
        $('#confirm-submit').on('click', confirmSubmitQuiz);
        $('#cancel-submit').on('click', cancelSubmitQuiz);
        
        // Form submission prevention
        $('.elearning-quiz-form').on('submit', function(e) {
            e.preventDefault();
        });
        
        // Auto-save answers (accessibility feature)
        setInterval(autoSaveProgress, 30000); // Every 30 seconds
    }
    
    function handleStartQuiz() {
        console.log('Start quiz button clicked');
        console.log('elearningQuiz object:', elearningQuiz);
        
        const $btn = $(this);
        const quizId = $btn.data('quiz-id');
        
        if (!quizId) {
            console.error('No quiz ID found');
            return;
        }
        
        // Check if elearningQuiz is defined
        if (typeof elearningQuiz === 'undefined') {
            console.error('elearningQuiz object not found - AJAX will not work');
            showError('Quiz system not properly initialized');
            return;
        }
        
        $btn.prop('disabled', true).text(elearningQuiz.strings.loading || 'Loading...');
        
        // Start quiz attempt via AJAX
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_start_quiz',
                quiz_id: quizId,
                nonce: elearningQuiz.nonce
            },
            success: function(response) {
                console.log('Quiz start response:', response);
                if (response.success) {
                    currentQuiz.id = quizId;
                    currentQuiz.attemptId = response.data.attempt_id;
                    currentQuiz.totalQuestions = response.data.total_questions;
                    currentQuiz.startTime = new Date();
                    
                    // Update form with attempt ID
                    $('input[name="attempt_id"]').val(currentQuiz.attemptId);
                    
                    // Show quiz form, hide intro
                    $('.elearning-quiz-intro').slideUp();
                    $('.elearning-quiz-form').slideDown();
                    
                    // Initialize first question
                    showQuestion(0);
                    startQuestionTimer();
                    
                    // Focus first input for accessibility
                    setTimeout(() => {
                        $('.quiz-question.active').find('input, select').first().focus();
                    }, 500);
                    
                } else {
                    showError(response.data || elearningQuiz.strings.error || 'An error occurred');
                    $btn.prop('disabled', false).text($btn.data('original-text') || 'Start Quiz');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showError(elearningQuiz.strings.error || 'An error occurred');
                $btn.prop('disabled', false).text($btn.data('original-text') || 'Start Quiz');
            }
        });
    }
    
    function handleRetakeQuiz() {
        $('.elearning-quiz-passed').slideUp();
        $('.elearning-quiz-intro').slideDown();
        
        // Reset quiz state
        currentQuiz = {
            id: null,
            attemptId: null,
            currentQuestion: 0,
            totalQuestions: 0,
            answers: {},
            startTime: null,
            questionStartTime: null
        };
        
        // Reset form
        $('.elearning-quiz-form')[0].reset();
        $('.quiz-question').removeClass('active');
        $('.quiz-results').hide();
        
        // Clear any previous answers
        $('.option-label').removeClass('selected');
        $('.blank-space').empty().removeClass('filled');
        $('.word-item').removeClass('used');
        $('.match-select').val('');
    }
    
    function handlePreviousQuestion() {
        if (currentQuiz.currentQuestion > 0) {
            saveCurrentAnswer();
            showQuestion(currentQuiz.currentQuestion - 1);
        }
    }
    
    function handleNextQuestion() {
        saveCurrentAnswer();
        
        if (currentQuiz.currentQuestion < currentQuiz.totalQuestions - 1) {
            showQuestion(currentQuiz.currentQuestion + 1);
        }
    }
    
    function handleSubmitQuiz() {
        // Show confirmation modal
        $('#quiz-confirmation-modal').fadeIn();
    }
    
    function confirmSubmitQuiz() {
        $('#quiz-confirmation-modal').fadeOut();
        $('#quiz-loading-modal').fadeIn();
        
        saveCurrentAnswer();
        
        // Calculate question timings
        const questionTimings = calculateQuestionTimings();
        
        // Submit quiz via AJAX
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_submit_quiz',
                attempt_id: currentQuiz.attemptId,
                answers: JSON.stringify(currentQuiz.answers),
                question_timings: JSON.stringify(questionTimings),
                nonce: elearningQuiz.nonce
            },
            success: function(response) {
                $('#quiz-loading-modal').fadeOut();
                
                if (response.success) {
                    displayResults(response.data);
                } else {
                    showError(response.data || elearningQuiz.strings.error || 'An error occurred');
                }
            },
            error: function() {
                $('#quiz-loading-modal').fadeOut();
                showError(elearningQuiz.strings.error || 'An error occurred');
            }
        });
    }
    
    function cancelSubmitQuiz() {
        $('#quiz-confirmation-modal').fadeOut();
    }
    
    function showQuestion(questionIndex) {
        // Update current question
        currentQuiz.currentQuestion = questionIndex;
        
        // Hide all questions
        $('.quiz-question').removeClass('active');
        
        // Show current question
        $('.quiz-question').eq(questionIndex).addClass('active');
        
        // Update progress
        updateProgress();
        
        // Update navigation buttons
        updateNavigationButtons();
        
        // Load saved answer if exists
        loadSavedAnswer(questionIndex);
        
        // Start question timer
        startQuestionTimer();
        
        // Scroll to top
        $('.quiz-question.active')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Focus first input for accessibility
        setTimeout(() => {
            $('.quiz-question.active').find('input:not([type="hidden"]), select').first().focus();
        }, 300);
    }
    
    function updateProgress() {
        const percentage = ((currentQuiz.currentQuestion + 1) / currentQuiz.totalQuestions) * 100;
        $('.progress-fill').css('width', percentage + '%');
        $('.progress-text .current').text(currentQuiz.currentQuestion + 1);
        $('.progress-text .total').text(currentQuiz.totalQuestions);
    }
    
    function updateNavigationButtons() {
        // Previous button
        if (currentQuiz.currentQuestion === 0) {
            $('.prev-btn').prop('disabled', true);
        } else {
            $('.prev-btn').prop('disabled', false);
        }
        
        // Next/Submit button
        if (currentQuiz.currentQuestion === currentQuiz.totalQuestions - 1) {
            $('.next-btn').hide();
            $('.quiz-submit-btn').show();
        } else {
            $('.next-btn').show();
            $('.quiz-submit-btn').hide();
        }
    }
    
    function handleAnswerChange() {
        const $question = $(this).closest('.quiz-question');
        const questionIndex = $question.data('question-index');
        const questionType = $question.data('question-type');
        
        // Visual feedback
        $(this).closest('.option-label').addClass('selected');
        
        // Auto-advance for single-choice questions (optional UX enhancement)
        if (questionType === 'true_false' || (questionType === 'multiple_choice' && $question.find('input[type="radio"]').length > 0)) {
            setTimeout(() => {
                if (currentQuiz.currentQuestion < currentQuiz.totalQuestions - 1) {
                    $('.next-btn').focus().click();
                }
            }, 1000);
        }
    }
    
    function handleMatchingChange() {
        const $select = $(this);
        const $question = $select.closest('.quiz-question');
        
        // Visual feedback
        $select.closest('.match-item').addClass('answered');
    }
    
    
    function saveCurrentAnswer() {
        const $currentQuestion = $('.quiz-question.active');
        const questionIndex = parseInt($currentQuestion.data('question-index'));
        const questionType = $currentQuestion.data('question-type');
        
        let answer = null;
        
        switch (questionType) {
            case 'multiple_choice':
                const checkboxes = $currentQuestion.find('input[type="checkbox"]:checked');
                const radioButtons = $currentQuestion.find('input[type="radio"]:checked');
                
                if (checkboxes.length > 0) {
                    answer = [];
                    checkboxes.each(function() {
                        answer.push(parseInt($(this).val()));
                    });
                } else if (radioButtons.length > 0) {
                    answer = parseInt(radioButtons.val());
                }
                break;
                
            case 'true_false':
                const tfAnswer = $currentQuestion.find('input[type="radio"]:checked').val();
                if (tfAnswer !== undefined) {
                    answer = tfAnswer;
                }
                break;
                
            case 'fill_blanks':
                answer = [];
                $currentQuestion.find('.blank-answer').each(function() {
                    answer.push($(this).val() || '');
                });
                break;
                
            case 'matching':
                answer = {};
                $currentQuestion.find('.match-answer').each(function() {
                    const $input = $(this);
                    const inputName = $input.attr('name');
                    const value = $input.val();
                    
                    const leftIndexMatch = inputName.match(/\[(\d+)\]$/);
                    if (leftIndexMatch && value) {
                        const leftIndex = parseInt(leftIndexMatch[1]);
                        const rightIndex = parseInt(value);
                        answer[leftIndex] = rightIndex;
                    }
                });
                break;
        }
        
        if (answer !== null && answer !== undefined && answer !== '') {
            if (Array.isArray(answer) && answer.length === 0) {
                return;
            }
            if (typeof answer === 'object' && !Array.isArray(answer) && Object.keys(answer).length === 0) {
                return;
            }
            
            currentQuiz.answers[questionIndex] = answer;
        }
    }
    
    function loadSavedAnswer(questionIndex) {
        const $question = $('.quiz-question').eq(questionIndex);
        const questionType = $question.data('question-type');
        const savedAnswer = currentQuiz.answers[questionIndex];
        
        if (!savedAnswer) return;
        
        switch (questionType) {
            case 'multiple_choice':
                if (Array.isArray(savedAnswer)) {
                    // Multiple select
                    savedAnswer.forEach(value => {
                        $question.find(`input[type="checkbox"][value="${value}"]`).prop('checked', true);
                    });
                } else {
                    // Single select
                    $question.find(`input[type="radio"][value="${savedAnswer}"]`).prop('checked', true);
                }
                break;
                
            case 'true_false':
                $question.find(`input[type="radio"][value="${savedAnswer}"]`).prop('checked', true);
                break;
                
            case 'fill_blanks':
                if (Array.isArray(savedAnswer)) {
                    savedAnswer.forEach((value, index) => {
                        const $blank = $question.find(`.blank-space[data-blank-index="${index}"]`);
                        const $hiddenInput = $question.find(`.blank-answer[data-blank-index="${index}"]`);
                        
                        if (value) {
                            $blank.text(value).addClass('filled');
                            $hiddenInput.val(value);
                            
                            // Mark word as used
                            $question.find(`.word-item[data-word="${value}"]`).addClass('used');
                        }
                    });
                }
                break;
                
            case 'matching':
                Object.keys(savedAnswer).forEach(leftIndex => {
                    const rightIndex = savedAnswer[leftIndex];
                    const $dropZone = $question.find(`.drop-zone[data-left-index="${leftIndex}"]`);
                    const $draggableItem = $question.find(`.draggable-item[data-right-index="${rightIndex}"]`);
                    const itemText = $draggableItem.data('item-text');
                    
                    if ($dropZone.length && $draggableItem.length) {
                        // Add item to drop zone
                        const droppedItemHtml = `
                            <div class="dropped-item">
                                <span>${itemText}</span>
                                <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${rightIndex}">×</button>
                            </div>
                        `;
                        $dropZone.html(droppedItemHtml).addClass('has-item');
                        
                        // Update hidden input
                        $question.find(`.match-answer[name*="[${leftIndex}]"]`).val(rightIndex);
                        
                        // Mark draggable item as used
                        $draggableItem.addClass('used');
                    }
                });
                break;
        }
    }
    
    function startQuestionTimer() {
        currentQuiz.questionStartTime = new Date();
    }
    
    function calculateQuestionTimings() {
        // This would track time spent on each question
        // For now, return empty object - will be enhanced in future versions
        return {};
    }
    
    function initializeDragAndDrop() {
        // === FILL IN THE BLANKS DRAG & DROP ===
        // Make word items draggable
        $(document).on('dragstart', '.word-item', function(e) {
            if ($(this).hasClass('used')) {
                e.preventDefault();
                return false;
            }
            
            const word = $(this).data('word');
            e.originalEvent.dataTransfer.setData('text/plain', word);
            e.originalEvent.dataTransfer.setData('application/x-word-item', $(this).index());
            $(this).addClass('dragging');
        });
        
        $(document).on('dragend', '.word-item', function() {
            $(this).removeClass('dragging');
        });
        
        // Make blank spaces droppable
        $(document).on('dragover', '.blank-space', function(e) {
            e.preventDefault();
            $(this).addClass('drop-target');
        });
        
        $(document).on('dragleave', '.blank-space', function() {
            $(this).removeClass('drop-target');
        });
        
        $(document).on('drop', '.blank-space', function(e) {
            e.preventDefault();
            $(this).removeClass('drop-target');
            
            const word = e.originalEvent.dataTransfer.getData('text/plain');
            const blankIndex = $(this).data('blank-index');
            const $question = $(this).closest('.quiz-question');
            
            // Check if word is already used
            if ($question.find(`.word-item[data-word="${word}"]`).hasClass('used')) {
                return;
            }
            
            // Clear previous value if any
            const previousWord = $(this).text();
            if (previousWord) {
                $question.find(`.word-item[data-word="${previousWord}"]`).removeClass('used');
            }
            
            // Set new value
            $(this).text(word).addClass('filled');
            $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val(word);
            
            // Mark word as used
            $question.find(`.word-item[data-word="${word}"]`).addClass('used');
            
            // Provide audio feedback for screen readers
            announceToScreenReader(`${word} placed in blank ${blankIndex + 1}`);
        });
        
        // === MATCHING DRAG & DROP ===
        // Make matching items draggable
        $(document).on('dragstart', '.draggable-item', function(e) {
            if ($(this).hasClass('used')) {
                e.preventDefault();
                return false;
            }
            
            const rightIndex = $(this).data('right-index');
            const itemText = $(this).data('item-text');
            
            e.originalEvent.dataTransfer.setData('text/plain', itemText);
            e.originalEvent.dataTransfer.setData('application/x-right-index', rightIndex);
            $(this).addClass('dragging');
        });
        
        $(document).on('dragend', '.draggable-item', function() {
            $(this).removeClass('dragging');
        });
        
        // Make drop zones droppable
        $(document).on('dragover', '.drop-zone', function(e) {
            e.preventDefault();
            $(this).addClass('drag-over');
        });
        
        $(document).on('dragleave', '.drop-zone', function() {
            $(this).removeClass('drag-over');
        });
        
        $(document).on('drop', '.drop-zone', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            
            const itemText = e.originalEvent.dataTransfer.getData('text/plain');
            const rightIndex = e.originalEvent.dataTransfer.getData('application/x-right-index');
            const leftIndex = $(this).data('left-index');
            const $question = $(this).closest('.quiz-question');
            
            // Check if item is already used
            const $draggableItem = $question.find(`.draggable-item[data-right-index="${rightIndex}"]`);
            if ($draggableItem.hasClass('used')) {
                return;
            }
            
            // Clear previous match if any
            const $hiddenInput = $question.find(`.match-answer[name*="[${leftIndex}]"]`);
            const previousRightIndex = $hiddenInput.val();
            if (previousRightIndex) {
                $question.find(`.draggable-item[data-right-index="${previousRightIndex}"]`).removeClass('used');
            }
            
            // Clear this drop zone if it had an item
            $(this).find('.dropped-item').remove();
            $(this).removeClass('has-item');
            
            // Add new item to drop zone
            const droppedItemHtml = `
                <div class="dropped-item">
                    <span>${itemText}</span>
                    <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${rightIndex}">×</button>
                </div>
            `;
            $(this).html(droppedItemHtml).addClass('has-item');
            
            // Update hidden input
            $hiddenInput.val(rightIndex);
            
            // Mark draggable item as used
            $draggableItem.addClass('used');
            
            // Provide audio feedback for screen readers
            announceToScreenReader(`${itemText} matched with item ${parseInt(leftIndex) + 1}`);
        });
        
        // Handle remove match button
        $(document).on('click', '.remove-match', function(e) {
            e.preventDefault();
            
            const leftIndex = $(this).data('left-index');
            const rightIndex = $(this).data('right-index');
            const $question = $(this).closest('.quiz-question');
            
            // Clear the drop zone
            const $dropZone = $(this).closest('.drop-zone');
            $dropZone.html('<span class="drop-placeholder">Drop here</span>').removeClass('has-item');
            
            // Clear hidden input
            $question.find(`.match-answer[name*="[${leftIndex}]"]`).val('');
            
            // Mark draggable item as available again
            $question.find(`.draggable-item[data-right-index="${rightIndex}"]`).removeClass('used');
            
            // Provide audio feedback
            announceToScreenReader(`Match removed from item ${parseInt(leftIndex) + 1}`);
        });
        
        // Touch support for mobile drag and drop
        let touchItem = null;
        
        $(document).on('touchstart', '.word-item', function(e) {
            if ($(this).hasClass('used')) return;
            
            touchItem = {
                element: this,
                word: $(this).data('word'),
                startX: e.originalEvent.touches[0].clientX,
                startY: e.originalEvent.touches[0].clientY
            };
            
            $(this).addClass('dragging');
        });
        
        $(document).on('touchmove', function(e) {
            if (!touchItem) return;
            
            e.preventDefault();
            const touch = e.originalEvent.touches[0];
            
            // Find element under touch point
            const elementBelow = document.elementFromPoint(touch.clientX, touch.clientY);
            const $blankSpace = $(elementBelow).closest('.blank-space');
            
            // Remove previous highlights
            $('.blank-space').removeClass('drop-target');
            
            // Highlight current target
            if ($blankSpace.length > 0) {
                $blankSpace.addClass('drop-target');
            }
        });
        
        $(document).on('touchend', function(e) {
            if (!touchItem) return;
            
            const touch = e.originalEvent.changedTouches[0];
            const elementBelow = document.elementFromPoint(touch.clientX, touch.clientY);
            const $blankSpace = $(elementBelow).closest('.blank-space');
            
            $('.blank-space').removeClass('drop-target');
            $(touchItem.element).removeClass('dragging');
            
            if ($blankSpace.length > 0) {
                const blankIndex = $blankSpace.data('blank-index');
                const $question = $blankSpace.closest('.quiz-question');
                const word = touchItem.word;
                
                // Check if word is already used
                if (!$question.find(`.word-item[data-word="${word}"]`).hasClass('used')) {
                    // Clear previous value if any
                    const previousWord = $blankSpace.text();
                    if (previousWord) {
                        $question.find(`.word-item[data-word="${previousWord}"]`).removeClass('used');
                    }
                    
                    // Set new value
                    $blankSpace.text(word).addClass('filled');
                    $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val(word);
                    
                    // Mark word as used
                    $question.find(`.word-item[data-word="${word}"]`).addClass('used');
                    
                    // Haptic feedback for mobile
                    if (navigator.vibrate) {
                        navigator.vibrate(50);
                    }
                    
                    announceToScreenReader(`${word} placed in blank ${blankIndex + 1}`);
                }
            }
            
            touchItem = null;
        });
        
        // Double-tap to remove word from blank (accessibility feature)
        $(document).on('dblclick', '.blank-space.filled', function() {
            const word = $(this).text();
            const blankIndex = $(this).data('blank-index');
            const $question = $(this).closest('.quiz-question');
            
            $(this).text('').removeClass('filled');
            $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val('');
            $question.find(`.word-item[data-word="${word}"]`).removeClass('used');
            
            announceToScreenReader(`${word} removed from blank ${blankIndex + 1}`);
        });
    }
    
    function handleKeyboardNavigation(e) {
        // Handle keyboard navigation within quiz
        if (!$('.elearning-quiz-form').is(':visible')) return;
        
        switch (e.key) {
            case 'ArrowLeft':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    $('.prev-btn').click();
                }
                break;
                
            case 'ArrowRight':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    if ($('.next-btn').is(':visible')) {
                        $('.next-btn').click();
                    } else {
                        $('.quiz-submit-btn').click();
                    }
                }
                break;
                
            case 'Enter':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    if ($('.next-btn').is(':visible')) {
                        $('.next-btn').click();
                    } else {
                        $('.quiz-submit-btn').click();
                    }
                }
                break;
                
            case 'Escape':
                // Close modals
                $('.quiz-modal').fadeOut();
                break;
        }
    }
    
    function displayResults(resultData) {
        $('.elearning-quiz-form').slideUp();
        
        const passed = resultData.passed;
        const score = parseFloat(resultData.score);
        const correctAnswers = parseInt(resultData.correct_answers);
        const totalQuestions = parseInt(resultData.total_questions);
        const passingScore = parseFloat(resultData.passing_score);
        
        let html = '<div class="quiz-results ' + (passed ? 'passed' : 'failed') + '">';
        
        // Result icon and message
        html += '<div class="result-icon">' + (passed ? '🎉' : '😞') + '</div>';
        html += '<div class="result-message">';
        
        if (passed) {
            html += '<h3>' + (elearningQuiz.strings.congratulations || 'Congratulations!') + '</h3>';
            html += '<p>' + (elearningQuiz.strings.quiz_passed || 'You have passed this quiz!') + '</p>';
        } else {
            html += '<h3>' + (elearningQuiz.strings.try_again || 'Try Again') + '</h3>';
            html += '<p>' + (elearningQuiz.strings.quiz_failed || 'You did not pass this quiz.') + '</p>';
        }
        
        html += '</div>';
        
        // Score display
        html += '<div class="score-display">' + score.toFixed(1) + '%</div>';
        
        // Result details
        html += '<div class="result-details">';
        html += '<p><strong>' + (elearningQuiz.strings.correct_answers || 'Correct Answers') + ':</strong> ' + correctAnswers + ' / ' + totalQuestions + '</p>';
        html += '<p><strong>' + (elearningQuiz.strings.passing_score || 'Passing Score') + ':</strong> ' + passingScore + '%</p>';
        
        if (resultData.time_taken) {
            html += '<p><strong>' + (elearningQuiz.strings.time_taken || 'Time Taken') + ':</strong> ' + formatTime(resultData.time_taken) + '</p>';
        }
        
        html += '</div>';
        
        // Show detailed answers if enabled
        if (resultData.show_answers && resultData.detailed_results) {
            html += displayDetailedResults(resultData.detailed_results);
        }
        
        // Action buttons
        if (!passed) {
            html += '<button type="button" class="retry-btn" onclick="location.reload()">' + (elearningQuiz.strings.retry_quiz || 'Retry Quiz') + '</button>';
        }
        
        html += '</div>';
        
        $('.quiz-results').html(html).slideDown();
        
        // Scroll to results
        $('.quiz-results')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Focus on results for screen readers
        $('.quiz-results').attr('tabindex', '-1').focus();
        
        // Announce results to screen readers
        const announcement = passed ? 
            `Quiz completed successfully. Score: ${score}% out of ${passingScore}% required.` :
            `Quiz not passed. Score: ${score}% out of ${passingScore}% required. You can retry the quiz.`;
        
        announceToScreenReader(announcement);
    }
    
    function displayDetailedResults(detailedResults) {
        let html = '<div class="detailed-results">';
        html += '<h4>' + (elearningQuiz.strings.review_answers || 'Review Your Answers') + '</h4>';
        
        detailedResults.forEach((result, index) => {
            html += '<div class="question-result ' + (result.correct ? 'correct' : 'incorrect') + '">';
            html += '<div class="question-number">Question ' + (index + 1) + '</div>';
            html += '<div class="question-text">' + result.question + '</div>';
            
            html += '<div class="answer-comparison">';
            html += '<div class="user-answer">';
            html += '<strong>' + (elearningQuiz.strings.your_answer || 'Your Answer') + ':</strong> ';
            html += '<span class="answer-value ' + (result.correct ? 'correct' : 'incorrect') + '">';
            html += formatAnswerForDisplay(result.user_answer, result.question_type);
            html += '</span>';
            html += '</div>';
            
            if (!result.correct) {
                html += '<div class="correct-answer">';
                html += '<strong>' + (elearningQuiz.strings.correct_answer || 'Correct Answer') + ':</strong> ';
                html += '<span class="answer-value correct">';
                html += formatAnswerForDisplay(result.correct_answer, result.question_type);
                html += '</span>';
                html += '</div>';
            }
            html += '</div>';
            
            html += '</div>';
        });
        
        html += '</div>';
        return html;
    }
    
    function formatAnswerForDisplay(answer, questionType) {
        if (!answer) return elearningQuiz.strings.no_answer || 'No answer provided';
        
        switch (questionType) {
            case 'multiple_choice':
                if (Array.isArray(answer)) {
                    return answer.join(', ');
                }
                return answer;
                
            case 'fill_blanks':
                if (Array.isArray(answer)) {
                    return answer.filter(a => a).join(', ') || (elearningQuiz.strings.no_answer || 'No answer provided');
                }
                return answer;
                
            case 'matching':
                if (typeof answer === 'object') {
                    return Object.keys(answer).length + ' matches';
                }
                return answer;
                
            default:
                return answer;
        }
    }
    
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        
        if (minutes > 0) {
            return minutes + 'm ' + remainingSeconds + 's';
        }
        return remainingSeconds + 's';
    }
    
    function autoSaveProgress() {
        if (!currentQuiz.attemptId) return;
        
        saveCurrentAnswer();
        
        // Auto-save progress (silent background save)
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_save_progress',
                attempt_id: currentQuiz.attemptId,
                current_question: currentQuiz.currentQuestion,
                answers: JSON.stringify(currentQuiz.answers),
                nonce: elearningQuiz.nonce
            },
            success: function(response) {
                // Silent save - no user feedback needed
                console.log('Progress auto-saved');
            }
        });
    }
    
    function showError(message) {
        const $errorDiv = $('<div class="quiz-error" style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 15px; border-radius: 6px; margin: 20px 0;">' + message + '</div>');
        
        $('.elearning-quiz-container').prepend($errorDiv);
        
        // Auto-remove error after 5 seconds
        setTimeout(() => {
            $errorDiv.fadeOut(() => $errorDiv.remove());
        }, 5000);
        
        // Focus error for screen readers
        $errorDiv.attr('tabindex', '-1').focus();
    }
    
    function announceToScreenReader(message) {
        // Create temporary element for screen reader announcements
        const $announcement = $('<div>', {
            'class': 'sr-only',
            'aria-live': 'polite',
            'aria-atomic': 'true',
            'text': message
        });
        
        $('body').append($announcement);
        
        // Remove after announcement
        setTimeout(() => {
            $announcement.remove();
        }, 1000);
    }
    
    // Initialize accessibility features
    function initializeAccessibility() {
        // Add skip links
        const $skipLink = $('<a href="#quiz-content" class="skip-link">' + (elearningQuiz.strings.skip_to_quiz || 'Skip to quiz content') + '</a>');
        $('.elearning-quiz-container').prepend($skipLink);
        
        // Add landmark roles
        $('.elearning-quiz-form').attr('role', 'main').attr('id', 'quiz-content');
        $('.quiz-progress').attr('role', 'progressbar').attr('aria-label', 'Quiz Progress');
        
        // Update progress bar accessibility
        updateProgressBarAccessibility();
    }
    
    function updateProgressBarAccessibility() {
        const percentage = ((currentQuiz.currentQuestion + 1) / currentQuiz.totalQuestions) * 100;
        $('.quiz-progress').attr('aria-valuenow', currentQuiz.currentQuestion + 1)
                          .attr('aria-valuemin', 1)
                          .attr('aria-valuemax', currentQuiz.totalQuestions)
                          .attr('aria-valuetext', `Question ${currentQuiz.currentQuestion + 1} of ${currentQuiz.totalQuestions}`);
    }
    
    // Initialize when DOM is ready
    initializeAccessibility();
    
    // Handle page visibility change (pause/resume timers)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden - pause timers
            console.log('Quiz paused');
        } else {
            // Page is visible - resume timers
            console.log('Quiz resumed');
            if (currentQuiz.attemptId) {
                startQuestionTimer();
            }
        }
    });
    
    // Handle page unload (save progress)
    window.addEventListener('beforeunload', function(e) {
        if (currentQuiz.attemptId && Object.keys(currentQuiz.answers).length > 0) {
            autoSaveProgress();
            
            // Show warning if quiz is in progress
            const message = elearningQuiz.strings.leave_warning || 'You have unsaved progress. Are you sure you want to leave?';
            e.returnValue = message;
            return message;
        }
    });
    
    // Prevent context menu on quiz elements (prevent cheating)
    $('.elearning-quiz-container').on('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Prevent text selection on certain elements
    $('.word-item, .blank-space').css({
        '-webkit-user-select': 'none',
        '-moz-user-select': 'none',
        '-ms-user-select': 'none',
        'user-select': 'none'
    });
    
    console.log('Quiz system initialized successfully');
});