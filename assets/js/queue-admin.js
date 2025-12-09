/**
 * Queue Admin JavaScript
 */

(function($) {
    'use strict';

    const QueueAdmin = {
        init: function() {
            this.bindEvents();
            this.checkQueueLock();
        },

        bindEvents: function() {
            $('#pdg-preview-btn').on('click', this.handlePreview.bind(this));
            $('#pdg-start-btn').on('click', this.handleStart.bind(this));
            $('#pdg-pause-btn').on('click', this.handlePause.bind(this));
        },

        checkQueueLock: function() {
            // Check if another queue is processing
            $.ajax({
                url: pdgQueue.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdg_check_queue_lock',
                    nonce: pdgQueue.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_lock) {
                        $('#pdg-start-btn').prop('disabled', true);
                        
                        const $notice = $('<div class="notice notice-warning inline"><p>' + 
                            'Another queue is currently processing. Please wait for it to complete.' + 
                            '</p></div>');
                        $('.pdg-queue-preview').prepend($notice);
                    }
                }
            });
        },

        handlePreview: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const queueId = $btn.data('queue-id');

            // Disable button and show loading
            $btn.prop('disabled', true);
            $('#pdg-preview-loading').show();
            $('#pdg-preview-content').hide();

            $.ajax({
                url: pdgQueue.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdg_preview_queue',
                    nonce: pdgQueue.nonce,
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        QueueAdmin.renderPreview(response.data);
                    } else {
                        alert(pdgQueue.i18n.previewError + ': ' + (response.data.message || pdgQueue.i18n.error));
                    }
                },
                error: function() {
                    alert(pdgQueue.i18n.previewError);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $('#pdg-preview-loading').hide();
                }
            });
        },

        renderPreview: function(data) {
            // Update stats
            $('#pdg-preview-products').text(data.product_count);
            $('#pdg-preview-templates').text(data.template_count);
            $('#pdg-preview-total').text(data.total_generations);

            // Render product preview table
            if (data.preview_products && data.preview_products.length > 0) {
                const $tbody = $('#pdg-preview-tbody');
                $tbody.empty();

                data.preview_products.forEach(function(product) {
                    const templateNames = product.templates.map(function(tid) {
                        return '<code>' + tid + '</code>';
                    }).join(', ');

                    const $row = $('<tr>' +
                        '<td><a href="' + product.edit_link + '" target="_blank">' + 
                        product.name + '</a></td>' +
                        '<td>' + templateNames + '</td>' +
                        '</tr>');

                    $tbody.append($row);
                });

                $('.pdg-preview-table-wrapper').show();
            }

            $('#pdg-preview-content').show();
        },

        handleStart: function(e) {
            e.preventDefault();

            if (!confirm(pdgQueue.i18n.confirmStart)) {
                return;
            }

            const $btn = $(e.currentTarget);
            const queueId = $btn.data('queue-id');

            // Disable button and show loading
            $btn.prop('disabled', true).text('Starting...');

            $.ajax({
                url: pdgQueue.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdg_start_queue',
                    nonce: pdgQueue.nonce,
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        const $notice = $('<div class="notice notice-success is-dismissible"><p>' + 
                            response.data.message + 
                            '</p></div>');
                        
                        $('.pdg-queue-preview').prepend($notice);

                        // Reload page after short delay to show updated status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert(pdgQueue.i18n.startError + ': ' + (response.data.message || pdgQueue.i18n.error));
                        $btn.prop('disabled', false).text('Start Queue');
                    }
                },
                error: function() {
                    alert(pdgQueue.i18n.startError);
                    $btn.prop('disabled', false).text('Start Queue');
                }
            });
        },

        handlePause: function(e) {
            e.preventDefault();

            if (!confirm(pdgQueue.i18n.confirmPause)) {
                return;
            }

            const $btn = $(e.currentTarget);
            const queueId = $('#pdg-start-btn').data('queue-id');

            // Disable button and show loading
            $btn.prop('disabled', true).text('Pausing...');

            $.ajax({
                url: pdgQueue.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdg_pause_queue',
                    nonce: pdgQueue.nonce,
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message and reload
                        const $notice = $('<div class="notice notice-info is-dismissible"><p>' + 
                            response.data.message + 
                            '</p></div>');
                        
                        $('.pdg-queue-preview').prepend($notice);

                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert(pdgQueue.i18n.pauseError + ': ' + (response.data.message || pdgQueue.i18n.error));
                        $btn.prop('disabled', false).text('Pause Queue');
                    }
                },
                error: function() {
                    alert(pdgQueue.i18n.pauseError);
                    $btn.prop('disabled', false).text('Pause Queue');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        QueueAdmin.init();
    });

})(jQuery);
