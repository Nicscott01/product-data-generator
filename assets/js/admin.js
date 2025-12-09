/**
 * Product Data Generator - Admin Scripts
 *
 * @package ProductDataGenerator
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        /**
         * Handle temperature slider changes
         */
        $(document).on('input', '.pdg-temp-slider', function() {
            const $slider = $(this);
            const $item = $slider.closest('.pdg-template-item');
            const $valueDisplay = $item.find('.pdg-temp-value');
            
            $valueDisplay.text($slider.val());
        });
        
        /**
         * Handle Generate button clicks
         */
        $(document).on('click', '.pdg-generate-btn', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $item = $button.closest('.pdg-template-item');
            const $status = $item.find('.pdg-status');
            const $result = $item.find('.pdg-result');
            const $resultContent = $item.find('.pdg-result-content');
            
            const templateId = $button.data('template-id');
            const productId = $button.data('product-id');
            const temperature = parseFloat($item.find('.pdg-temp-slider').val()) || 0.7;

            // Reset status
            $status.removeClass('success error').text('');
            $result.hide();
            
            // Add loading state
            $item.addClass('is-loading');
            $button.prop('disabled', true);
            $status.text(pdgAdmin.i18n.generating);

            // Make AJAX request
            $.ajax({
                url: pdgAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdg_generate_content',
                    nonce: pdgAdmin.nonce,
                    product_id: productId,
                    template_id: templateId,
                    temperature: temperature
                },
                success: function(response) {
                    if (response.success) {
                        // Check if this is an auto-apply template (custom templates that handle their own saving)
                        const autoApplyTemplates = pdgAdmin.autoApplyTemplates || [];
                        
                        if (autoApplyTemplates.includes(templateId)) {
                            // For auto-apply templates, just show success and update timestamp
                            $status.addClass('success').text('✓ Applied successfully!');
                            
                            // Show a more prominent success notice
                            showNotice('Content generated and applied successfully! Reloading...', 'success');
                            
                            // Reload the page after a brief delay
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                            
                        } else {
                            // For manual templates (description, short description, etc), show preview
                            $status.addClass('success').text(pdgAdmin.i18n.success);
                            
                            // Update last generated time
                            const $lastGenerated = $item.find('.pdg-last-generated');
                            if ($lastGenerated.length) {
                                $lastGenerated.text('Generated just now');
                            } else {
                                $item.find('.pdg-template-header').append(
                                    '<span class="pdg-last-generated">Generated just now</span>'
                                );
                            }
                            
                            // Show result for manual application
                            $resultContent.val(response.data.content);
                            $result.slideDown();
                            
                            // Store content for apply action
                            $item.data('generated-content', response.data.content);
                        }
                        
                    } else {
                        // Show error status
                        const errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : pdgAdmin.i18n.error;
                        $status.addClass('error').text(errorMsg);
                        
                        console.error('Generation error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    $status.addClass('error').text(pdgAdmin.i18n.error + ': ' + error);
                    console.error('AJAX error:', xhr, status, error);
                },
                complete: function() {
                    // Remove loading state
                    $item.removeClass('is-loading');
                    $button.prop('disabled', false);
                }
            });
        });

        /**
         * Handle Apply button clicks
         */
        $(document).on('click', '.pdg-apply-btn', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $item = $button.closest('.pdg-template-item');
            const templateId = $item.data('template-id');
            const content = $item.data('generated-content');

            if (!content) {
                alert('No content to apply.');
                return;
            }

            // Apply content based on template ID
            if (templateId === 'product_description') {
                // Check if user wants to replace existing content
                const currentContent = getEditorContent('content');
                if (currentContent && currentContent.trim() !== '') {
                    if (!confirm(pdgAdmin.i18n.confirmApply)) {
                        return;
                    }
                }
                setEditorContent('content', content);
                showNotice('Product description updated!', 'success');
                
            } else if (templateId === 'product_short_description') {
                const currentContent = getEditorContent('excerpt');
                if (currentContent && currentContent.trim() !== '') {
                    if (!confirm(pdgAdmin.i18n.confirmApply)) {
                        return;
                    }
                }
                setEditorContent('excerpt', content);
                showNotice('Short description updated!', 'success');
                
            } else if (templateId === 'product_seo') {
                // Handle SEO meta (if Yoast or RankMath is installed)
                handleSEOContent(content);
                
            } else {
                // For custom templates, try to find a matching field or fire custom event
                const applied = applyCustomTemplateContent(templateId, content);
                if (applied) {
                    showNotice('Content applied successfully!', 'success');
                } else {
                    // Copy to clipboard as fallback
                    copyToClipboard(content);
                    showNotice('Content copied to clipboard!', 'info');
                }
            }

            // Hide result area
            $item.find('.pdg-result').slideUp();
        });

        /**
         * Handle Cancel button clicks
         */
        $(document).on('click', '.pdg-cancel-btn', function(e) {
            e.preventDefault();
            
            const $item = $(this).closest('.pdg-template-item');
            $item.find('.pdg-result').slideUp();
        });

        /**
         * Get content from editor (TinyMCE or Block Editor)
         */
        function getEditorContent(editorId) {
            // Try TinyMCE first
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                return tinymce.get(editorId).getContent();
            }
            
            // Try Block Editor
            if (editorId === 'content' && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent();
            }
            
            // Fallback to textarea
            return $('#' + editorId).val();
        }

        /**
         * Set content in editor (TinyMCE or Block Editor)
         */
        function setEditorContent(editorId, content) {
            // Try TinyMCE first
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                tinymce.get(editorId).setContent(content);
                return true;
            }
            
            // Try Block Editor
            if (editorId === 'content' && typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                wp.data.dispatch('core/editor').editPost({ content: content });
                return true;
            }
            
            // Fallback to textarea
            $('#' + editorId).val(content);
            return true;
        }

        /**
         * Handle SEO content (Yoast/RankMath)
         */
        function handleSEOContent(content) {
            try {
                const seoData = JSON.parse(content);
                
                // Try Yoast SEO
                if (typeof YoastSEO !== 'undefined') {
                    if (seoData.meta_title) {
                        $('#yoast_wpseo_title').val(seoData.meta_title);
                    }
                    if (seoData.meta_description) {
                        $('#yoast_wpseo_metadesc').val(seoData.meta_description);
                    }
                    showNotice('SEO data applied!', 'success');
                    return true;
                }
                
                // Try RankMath
                if (typeof rankMath !== 'undefined') {
                    if (seoData.meta_title) {
                        $('#rank_math_title').val(seoData.meta_title);
                    }
                    if (seoData.meta_description) {
                        $('#rank_math_description').val(seoData.meta_description);
                    }
                    showNotice('SEO data applied!', 'success');
                    return true;
                }
                
                // No SEO plugin found
                copyToClipboard(content);
                showNotice('SEO plugin not found. Content copied to clipboard.', 'info');
                return false;
                
            } catch (e) {
                console.error('Error parsing SEO content:', e);
                copyToClipboard(content);
                showNotice('Error applying SEO data. Content copied to clipboard.', 'warning');
                return false;
            }
        }

        /**
         * Apply custom template content
         * Fires a custom event that other plugins can hook into
         */
        function applyCustomTemplateContent(templateId, content) {
            // Fire custom event
            const event = new CustomEvent('pdg_apply_content', {
                detail: {
                    templateId: templateId,
                    content: content
                }
            });
            
            document.dispatchEvent(event);
            
            // Check if event was handled
            if (event.defaultPrevented) {
                return true;
            }
            
            // Try to find a matching meta field
            const $metaField = $('[name="_' + templateId + '"], [name="' + templateId + '"]');
            if ($metaField.length) {
                $metaField.val(content);
                return true;
            }
            
            return false;
        }

        /**
         * Copy text to clipboard
         */
        function copyToClipboard(text) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        }

        /**
         * Show admin notice
         */
        function showNotice(message, type) {
            type = type || 'success';
            
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.pdg-metabox').before($notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }

    });

})(jQuery);
