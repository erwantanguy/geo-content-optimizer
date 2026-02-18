(function($) {
    'use strict';

    if (typeof gcoSettings === 'undefined') {
        return;
    }

    function getEditorContent() {
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            var content = wp.data.select('core/editor').getEditedPostContent();
            if (content) return content;
        }
        
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
            return tinymce.activeEditor.getContent();
        }
        
        var $textarea = $('#content');
        if ($textarea.length) {
            return $textarea.val();
        }
        
        return '';
    }

    function getEditorTitle() {
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            var title = wp.data.select('core/editor').getEditedPostAttribute('title');
            if (title) return title;
        }
        
        var $titleField = $('#title');
        if ($titleField.length) {
            return $titleField.val();
        }
        
        return '';
    }

    function updateMetabox(results) {
        var $metabox = $('.gco-metabox');
        if (!$metabox.length) return;

        var scoreClass = getScoreClass(results.score);
        
        $metabox.find('.gco-score-circle')
            .removeClass('gco-score-none gco-score-excellent gco-score-good gco-score-fair gco-score-poor')
            .addClass(scoreClass);
        
        $metabox.find('.gco-score-value').text(results.score);
        
        if (results.grade) {
            var $grade = $metabox.find('.gco-grade');
            if ($grade.length) {
                $grade.find('strong').text(results.grade);
            } else {
                var $gradeDiv = $('<div class="gco-grade">Note : <strong></strong></div>');
                $gradeDiv.find('strong').text(results.grade);
                $metabox.find('.gco-score-container').append($gradeDiv);
            }
        }
        
        if (results.subscores) {
            var $subscores = $metabox.find('.gco-subscores');
            if (!$subscores.length) {
                $subscores = $('<div class="gco-subscores"></div>');
                $metabox.find('.gco-score-container').after($subscores);
            }
            
            var subscoresHtml = '';
            var labels = {
                citability: 'Citabilité',
                clarity: 'Clarté',
                structure: 'Structure',
                factuality: 'Factualité'
            };
            
            for (var key in results.subscores) {
                var value = parseInt(results.subscores[key], 10) || 0;
                var subScoreClass = getScoreClass(value);
                subscoresHtml += '<div class="gco-subscore">' +
                    '<span class="gco-subscore-label">' + escapeHtml(labels[key] || key) + '</span>' +
                    '<div class="gco-progress">' +
                    '<div class="gco-progress-bar ' + subScoreClass + '" style="width: ' + value + '%"></div>' +
                    '</div>' +
                    '<span class="gco-subscore-value">' + value + '</span>' +
                    '</div>';
            }
            
            $subscores.html(subscoresHtml);
        }
        
        if (results.geo_blocks && results.geo_blocks.length > 0) {
            var $geoBlocks = $metabox.find('.gco-geo-blocks');
            if (!$geoBlocks.length) {
                $geoBlocks = $('<div class="gco-geo-blocks"><h4>Blocs GEO détectés</h4><div class="gco-geo-blocks-list"></div></div>');
                $metabox.find('.gco-subscores').after($geoBlocks);
            }
            
            var geoBlocksHtml = '';
            for (var i = 0; i < results.geo_blocks.length; i++) {
                geoBlocksHtml += '<span class="gco-geo-block-tag">' + escapeHtml(results.geo_blocks[i]) + '</span>';
            }
            
            if (results.geo_blocks_bonus > 0) {
                geoBlocksHtml += '<span class="gco-geo-bonus">+' + results.geo_blocks_bonus + ' pts</span>';
            }
            
            $geoBlocks.find('.gco-geo-blocks-list').html(geoBlocksHtml);
        } else {
            $metabox.find('.gco-geo-blocks').remove();
        }
        
        if (results.suggestions && results.suggestions.length > 0) {
            var $suggestions = $metabox.find('.gco-suggestions');
            if (!$suggestions.length) {
                $suggestions = $('<div class="gco-suggestions"><h4>Suggestions</h4><ul></ul></div>');
                $metabox.find('.gco-subscores').after($suggestions);
            }
            
            var suggestionsHtml = '';
            var maxSuggestions = Math.min(results.suggestions.length, 3);
            var allowedPriorities = ['high', 'medium', 'low'];
            
            for (var i = 0; i < maxSuggestions; i++) {
                var suggestion = results.suggestions[i];
                var priority = allowedPriorities.indexOf(suggestion.priority) !== -1 ? suggestion.priority : 'medium';
                suggestionsHtml += '<li class="gco-suggestion gco-suggestion-' + priority + '">' +
                    '<span class="gco-suggestion-icon"></span>' +
                    '<span class="gco-suggestion-text">' + escapeHtml(suggestion.message) + '</span>' +
                    '</li>';
            }
            
            $suggestions.find('ul').html(suggestionsHtml);
        }
        
        $metabox.find('.gco-last-analysis').text('Analyse terminée');
    }

    function getScoreClass(score) {
        if (score === '' || score === null || score === undefined) return 'gco-score-none';
        if (score >= 80) return 'gco-score-excellent';
        if (score >= 60) return 'gco-score-good';
        if (score >= 40) return 'gco-score-fair';
        return 'gco-score-poor';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $(document).ready(function() {
        $('.gco-analyze-btn').on('click', function() {
            var $btn = $(this);
            var postId = $btn.data('post-id');
            var content = getEditorContent();
            var title = getEditorTitle();
            
            if (!content || content.trim() === '') {
                alert('Le contenu est vide. Ajoutez du texte avant d\'analyser.');
                return;
            }
            
            $btn.prop('disabled', true).text(gcoSettings.analyzing);
            
            $.ajax({
                url: gcoSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gco_analyze_content',
                    nonce: gcoSettings.nonce,
                    post_id: postId,
                    content: content,
                    title: title
                },
                success: function(response) {
                    if (response.success) {
                        updateMetabox(response.data);
                    } else {
                        alert(response.data.message || gcoSettings.error);
                    }
                },
                error: function() {
                    alert(gcoSettings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Analyser maintenant');
                }
            });
        });
    });

})(jQuery);
