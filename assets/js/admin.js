/* E-Learning Quiz System - Admin JavaScript */
/* Working version based on successful debug version */

jQuery(document).ready(function($) {
    console.log('E-Learning Quiz System admin loaded - Working Version');
    
    var sectionIndex = $('#lesson-sections-container .lesson-section').length;
    var questionIndex = $('#quiz-questions-container .quiz-question').length;
    
    // Add Section functionality
    $('#add-section').on('click', function(e) {
        e.preventDefault();
        
        var container = $('#lesson-sections-container');
        var template = $('#section-template').html();
        
        var newSection = template.replace(/\{\{INDEX\}\}/g, sectionIndex);
        container.append(newSection);
        
        // Initialize TinyMCE for the new section
        initializeWpEditor('section_content_' + sectionIndex);
        
        // Update section numbers
        updateSectionNumbers();
        
        sectionIndex++;
    });
    
    // Remove Section functionality
    $(document).on('click', '.remove-section', function(e) {
        e.preventDefault();
        
        if (confirm(elearningQuizAdmin.strings.confirm_delete)) {
            var sectionDiv = $(this).closest('.lesson-section');
            var editorId = sectionDiv.find('textarea[id^="section_content_"]').attr('id');
            
            // Remove TinyMCE instance if it exists
            if (editorId && typeof tinymce !== 'undefined') {
                var editor = tinymce.get(editorId);
                if (editor) {
                    editor.remove();
                }
            }
            
            sectionDiv.remove();
            updateSectionNumbers();
        }
    });
    
    // Add Question functionality
    $('#add-question').on('click', function(e) {
        console.log('Add question clicked!');
        e.preventDefault();
        
        var container = $('#quiz-questions-container');
        var template = $('#question-template').html();
        
        var newQuestion = template.replace(/\{\{INDEX\}\}/g, questionIndex);
        container.append(newQuestion);
        
        // Update question numbers
        updateQuestionNumbers();
        
        questionIndex++;
    });
    
    // Remove Question functionality
    $(document).on('click', '.remove-question', function(e) {
        e.preventDefault();
        
        if (confirm(elearningQuizAdmin.strings.confirm_delete)) {
            $(this).closest('.quiz-question').remove();
            updateQuestionNumbers();
        }
    });
    
    // === CRITICAL: These handlers must be exactly as they were when working ===
    
    // Add Left Item functionality - EXACT COPY FROM WORKING VERSION
    $(document).on('click', '.add-left-item', function(e) {
        e.preventDefault();
        
        var container = $(this).siblings('.left-column').find('.match-items-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        // FIX: Use sequential indexing starting from 0
        var leftIndex = container.find('.match-item').length;
        
        var newLeftItem = '<div class="match-item">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][left_column][' + leftIndex + ']" placeholder="Left item" class="regular-text" />' +
            '<button type="button" class="remove-left-item button-link-delete">Remove</button>' +
            '</div>';
        
        container.append(newLeftItem);
        updateMatchingSelects(questionDiv);
    });
    
    // Add Right Item functionality - EXACT COPY FROM WORKING VERSION
    $(document).on('click', '.add-right-item', function(e) {
        e.preventDefault();
        
        var container = $(this).siblings('.right-column').find('.match-items-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        // FIX: Use sequential indexing starting from 0
        var rightIndex = container.find('.match-item').length;
        
        var newRightItem = '<div class="match-item">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][right_column][' + rightIndex + ']" placeholder="Right item" class="regular-text" />' +
            '<button type="button" class="remove-right-item button-link-delete">Remove</button>' +
            '</div>';
        
        container.append(newRightItem);
        updateMatchingSelects(questionDiv);
    });
    
    // Add Word functionality - EXACT COPY FROM WORKING VERSION
    $(document).on('click', '.add-word', function(e) {
        console.log('Add word clicked!');
        e.preventDefault();
        
        var container = $(this).siblings('.word-bank-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        var wordIndex = container.find('.word-row').length;
        
        var newWord = '<div class="word-row">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][word_bank][' + wordIndex + ']" placeholder="' + elearningQuizAdmin.strings.word + '" class="regular-text" />' +
            '<button type="button" class="remove-word button-link-delete">' + elearningQuizAdmin.strings.remove + '</button>' +
            '</div>';
        
        container.append(newWord);
    });
    
    // Add Match functionality
    $(document).on('click', '.add-match', function(e) {
        console.log('Add match clicked!');
        e.preventDefault();
        
        var container = $(this).siblings('.matches-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        var matchIndex = container.find('.match-row').length;
        
        var leftOptions = '';
        var rightOptions = '';
        
        // Get left column items
        questionDiv.find('.left-column input[type="text"]').each(function(index) {
            var value = $(this).val() || 'Left Item ' + (index + 1);
            leftOptions += '<option value="' + index + '">' + value + '</option>';
        });
        
        // Get right column items
        questionDiv.find('.right-column input[type="text"]').each(function(index) {
            var value = $(this).val() || 'Right Item ' + (index + 1);
            rightOptions += '<option value="' + index + '">' + value + '</option>';
        });
        
        var newMatch = '<div class="match-row">' +
            '<select name="quiz_questions[' + questionIdx + '][matches][' + matchIndex + '][left]" class="match-left-select">' +
            '<option value="">' + elearningQuizAdmin.strings.select_left + '</option>' +
            leftOptions +
            '</select>' +
            '<span>' + elearningQuizAdmin.strings.matches_with + '</span>' +
            '<select name="quiz_questions[' + questionIdx + '][matches][' + matchIndex + '][right]" class="match-right-select">' +
            '<option value="">' + elearningQuizAdmin.strings.select_right + '</option>' +
            rightOptions +
            '</select>' +
            '<button type="button" class="remove-match button-link-delete">' + elearningQuizAdmin.strings.remove + '</button>' +
            '</div>';
        
        container.append(newMatch);
    });
    
    // Add Option functionality
    $(document).on('click', '.add-option', function(e) {
        e.preventDefault();
        
        var container = $(this).siblings('.options-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        var optionIndex = container.find('.option-row').length;
        
        var newOption = '<div class="option-row">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][options][' + optionIndex + ']" placeholder="' + elearningQuizAdmin.strings.option_text + '" class="regular-text" />' +
            '<label><input type="checkbox" name="quiz_questions[' + questionIdx + '][correct_answers][]" value="' + optionIndex + '" /> ' + elearningQuizAdmin.strings.correct + '</label>' +
            '<button type="button" class="remove-option button-link-delete">' + elearningQuizAdmin.strings.remove + '</button>' +
            '</div>';
        
        container.append(newOption);
    });
    
    // Remove handlers
    $(document).on('click', '.remove-left-item', function(e) {
        e.preventDefault();
        var questionDiv = $(this).closest('.quiz-question');
        $(this).closest('.match-item').remove();
        updateMatchingSelects(questionDiv);
    });
    
    $(document).on('click', '.remove-right-item', function(e) {
        e.preventDefault();
        var questionDiv = $(this).closest('.quiz-question');
        $(this).closest('.match-item').remove();
        updateMatchingSelects(questionDiv);
    });
    
    $(document).on('click', '.remove-word', function(e) {
        e.preventDefault();
        $(this).closest('.word-row').remove();
    });
    
    $(document).on('click', '.remove-match', function(e) {
        e.preventDefault();
        $(this).closest('.match-row').remove();
    });
    
    $(document).on('click', '.remove-option', function(e) {
        e.preventDefault();
        $(this).closest('.option-row').remove();
    });
    
    // Update matching selects when items change
    $(document).on('input', '.left-column input[type="text"], .right-column input[type="text"]', function() {
        var questionDiv = $(this).closest('.quiz-question');
        updateMatchingSelects(questionDiv);
    });
    
    // Question type change handler
    $(document).on('change', '.question-type-select', function() {
        var questionContainer = $(this).closest('.quiz-question');
        var optionsContainer = questionContainer.find('.question-options');
        var questionType = $(this).val();
        var questionIdx = questionContainer.data('index');
        
        // Update options container data-type
        optionsContainer.attr('data-type', questionType);
        
        // Load appropriate options template based on question type
        loadQuestionTypeOptions(questionType, questionIdx, optionsContainer);
    });
    
    // Function to initialize WordPress editor
    function initializeWpEditor(editorId) {
        if (typeof tinymce === 'undefined') {
            console.log('TinyMCE not available, keeping textarea');
            return;
        }
        
        // Convert textarea to wp_editor via AJAX
        var textarea = $('#' + editorId);
        if (textarea.hasClass('wp-editor-placeholder')) {
            var data = {
                action: 'elearning_init_editor',
                editor_id: editorId,
                content: textarea.val(),
                nonce: elearningQuizAdmin.nonce
            };
            
            $.post(elearningQuizAdmin.ajaxUrl, data, function(response) {
                if (response.success) {
                    textarea.closest('td').html(response.data.editor_html);
                }
            });
        }
    }
    
    // Function to update section numbers
    function updateSectionNumbers() {
        $('#lesson-sections-container .lesson-section').each(function(index) {
            $(this).find('.section-header h4').text(elearningQuizAdmin.strings.section + ' ' + (index + 1));
            $(this).attr('data-index', index);
        });
    }
    
    // Function to update question numbers
    function updateQuestionNumbers() {
        $('#quiz-questions-container .quiz-question').each(function(index) {
            $(this).find('.question-header h4').text(elearningQuizAdmin.strings.question + ' ' + (index + 1));
            $(this).attr('data-index', index);
        });
    }
    
    // Function to update matching selects
    function updateMatchingSelects(questionDiv) {
        var questionIdx = questionDiv.data('index');
        
        // Update all match selects in this question
        questionDiv.find('.matches-container .match-row').each(function(matchIndex) {
            var leftSelect = $(this).find('.match-left-select');
            var rightSelect = $(this).find('.match-right-select');
            
            var leftCurrentValue = leftSelect.val();
            var rightCurrentValue = rightSelect.val();
            
            // Update left select options
            var leftOptions = '<option value="">' + elearningQuizAdmin.strings.select_left + '</option>';
            questionDiv.find('.left-column input[type="text"]').each(function(index) {
                var value = $(this).val() || 'Left Item ' + (index + 1);
                var selected = leftCurrentValue == index ? ' selected' : '';
                leftOptions += '<option value="' + index + '"' + selected + '>' + value + '</option>';
            });
            leftSelect.html(leftOptions);
            
            // Update right select options
            var rightOptions = '<option value="">' + elearningQuizAdmin.strings.select_right + '</option>';
            questionDiv.find('.right-column input[type="text"]').each(function(index) {
                var value = $(this).val() || 'Right Item ' + (index + 1);
                var selected = rightCurrentValue == index ? ' selected' : '';
                rightOptions += '<option value="' + index + '"' + selected + '>' + value + '</option>';
            });
            rightSelect.html(rightOptions);
        });
    }
    
    // Function to load question type options
    function loadQuestionTypeOptions(questionType, questionIdx, container) {
        var html = '';
        
        switch (questionType) {
            case 'multiple_choice':
                html = '<h5>' + elearningQuizAdmin.strings.options + '</h5>' +
                    '<div class="options-container">' +
                    '<div class="option-row">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][options][0]" placeholder="' + elearningQuizAdmin.strings.option_text + '" class="regular-text" />' +
                    '<label><input type="checkbox" name="quiz_questions[' + questionIdx + '][correct_answers][]" value="0" /> ' + elearningQuizAdmin.strings.correct + '</label>' +
                    '<button type="button" class="remove-option button-link-delete">' + elearningQuizAdmin.strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-option button">' + elearningQuizAdmin.strings.add_option + '</button>';
                break;
                
            case 'fill_blanks':
                html = '<h5>' + elearningQuizAdmin.strings.text_with_blanks + '</h5>' +
                    '<p class="description">' + elearningQuizAdmin.strings.blank_instruction + '</p>' +
                    '<textarea name="quiz_questions[' + questionIdx + '][text_with_blanks]" rows="4" class="large-text"></textarea>' +
                    '<h5>' + elearningQuizAdmin.strings.word_bank + '</h5>' +
                    '<div class="word-bank-container">' +
                    '<div class="word-row">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][word_bank][0]" placeholder="' + elearningQuizAdmin.strings.word + '" class="regular-text" />' +
                    '<button type="button" class="remove-word button-link-delete">' + elearningQuizAdmin.strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-word button">' + elearningQuizAdmin.strings.add_word + '</button>';
                break;
                
            case 'true_false':
                html = '<h5>' + elearningQuizAdmin.strings.correct_answer + '</h5>' +
                    '<label><input type="radio" name="quiz_questions[' + questionIdx + '][correct_answer]" value="true" checked /> ' + elearningQuizAdmin.strings.true_option + '</label><br>' +
                    '<label><input type="radio" name="quiz_questions[' + questionIdx + '][correct_answer]" value="false" /> ' + elearningQuizAdmin.strings.false_option + '</label>';
                break;
                
            case 'matching':
                html = '<div class="matching-columns">' +
                    '<div class="left-column">' +
                    '<h5>' + elearningQuizAdmin.strings.left_column + '</h5>' +
                    '<div class="match-items-container">' +
                    '<div class="match-item">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][left_column][0]" placeholder="' + elearningQuizAdmin.strings.left_item + '" class="regular-text" />' +
                    '<button type="button" class="remove-left-item button-link-delete">' + elearningQuizAdmin.strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-left-item button">' + elearningQuizAdmin.strings.add_left_item + '</button>' +
                    '</div>' +
                    '<div class="right-column">' +
                    '<h5>' + elearningQuizAdmin.strings.right_column + '</h5>' +
                    '<div class="match-items-container">' +
                    '<div class="match-item">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][right_column][0]" placeholder="' + elearningQuizAdmin.strings.right_item + '" class="regular-text" />' +
                    '<button type="button" class="remove-right-item button-link-delete">' + elearningQuizAdmin.strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-right-item button">' + elearningQuizAdmin.strings.add_right_item + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<h5>' + elearningQuizAdmin.strings.correct_matches + '</h5>' +
                    '<div class="matches-container"></div>' +
                    '<button type="button" class="add-match button">' + elearningQuizAdmin.strings.add_match + '</button>';
                break;
        }
        
        container.html(html);
    }
    
    // Form submission handler to sync TinyMCE content
    $('form').on('submit', function() {
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
    });
    
    // Initialize existing questions on page load
    $('.quiz-question').each(function() {
        var questionDiv = $(this);
        var questionType = questionDiv.find('.question-type-select').val();
        
        if (questionType === 'matching') {
            updateMatchingSelects(questionDiv);
        }
    });
    
    console.log('Admin JavaScript loaded - keeping console.log for debugging');
});