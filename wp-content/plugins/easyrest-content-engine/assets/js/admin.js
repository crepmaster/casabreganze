/**
 * EasyRest Content Engine - Admin JavaScript
 */

(function($) {
    'use strict';

    var EasyRestCEAdmin = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Test API
            $('#test-api').on('click', function() {
                self.testApi($(this));
            });

            // Run Planner
            $('#run-planner').on('click', function() {
                self.runPlanner($(this));
            });

            // Run Worker
            $('#run-worker').on('click', function() {
                self.runWorker($(this));
            });

            // Regenerate Token
            $('#regenerate-token').on('click', function() {
                self.regenerateToken($(this));
            });

            // Process Item
            $(document).on('click', '.process-item', function() {
                self.processItem($(this));
            });

            // Retry Item
            $(document).on('click', '.retry-item', function() {
                self.retryItem($(this));
            });

            // Delete Item
            $(document).on('click', '.delete-item', function() {
                self.deleteItem($(this));
            });

            // Release Stale Locks
            $('#release-stale-locks').on('click', function() {
                self.releaseStaleLocks($(this));
            });

            // Auto-generate slug from name
            $('#name').on('blur', function() {
                var $slug = $('#slug');
                if (!$slug.val()) {
                    $slug.val(self.generateSlug($(this).val()));
                }
            });
        },

        /**
         * Test API connection
         */
        testApi: function($button) {
            var self = this;
            var $result = $('#api-test-result, #action-result');

            $button.prop('disabled', true).text(easyrestCE.i18n.processing);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'test_api',
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showResult($result, 'success', response.data.message);
                    } else {
                        self.showResult($result, 'error', response.data.message);
                    }
                },
                error: function() {
                    self.showResult($result, 'error', easyrestCE.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test API');
                }
            });
        },

        /**
         * Run planner
         */
        runPlanner: function($button) {
            var self = this;
            var $result = $('#action-result');

            $button.prop('disabled', true).text(easyrestCE.i18n.processing);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'run_planner',
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = 'Planner completed: ' + response.data.planned + ' items planned, ' + response.data.skipped + ' skipped.';
                        self.showResult($result, 'success', msg);
                    } else {
                        self.showResult($result, 'error', response.data.message || easyrestCE.i18n.error);
                    }
                },
                error: function() {
                    self.showResult($result, 'error', easyrestCE.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Run Planner');
                }
            });
        },

        /**
         * Run worker
         */
        runWorker: function($button) {
            var self = this;
            var $result = $('#action-result');

            $button.prop('disabled', true).text(easyrestCE.i18n.processing);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'run_worker',
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = 'Worker completed: ' + response.data.succeeded + ' succeeded, ' + response.data.failed + ' failed.';
                        self.showResult($result, 'success', msg);
                    } else {
                        self.showResult($result, 'error', response.data.message || easyrestCE.i18n.error);
                    }
                },
                error: function() {
                    self.showResult($result, 'error', easyrestCE.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Run Worker Batch');
                }
            });
        },

        /**
         * Release stale locks
         */
        releaseStaleLocks: function($button) {
            var self = this;
            var $result = $('#action-result');

            $button.prop('disabled', true).text(easyrestCE.i18n.processing);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'release_stale_locks',
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showResult($result, 'success', response.data.message);
                    } else {
                        self.showResult($result, 'error', response.data.message || easyrestCE.i18n.error);
                    }
                },
                error: function() {
                    self.showResult($result, 'error', easyrestCE.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Release Stale Locks');
                }
            });
        },

        /**
         * Regenerate worker token
         */
        regenerateToken: function($button) {
            var self = this;

            if (!confirm('Are you sure? Existing cron configurations will need to be updated.')) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'regenerate_token',
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#easyrest_ce_worker_token').val(response.data.token);
                        alert('Token regenerated successfully.');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Process single item
         */
        processItem: function($button) {
            var self = this;
            var itemId = $button.data('id');
            var $row = $button.closest('tr');

            $button.prop('disabled', true).text(easyrestCE.i18n.processing);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'process_item',
                    item_id: itemId,
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data.error || easyrestCE.i18n.error));
                        $button.prop('disabled', false).text('Process');
                    }
                },
                error: function() {
                    alert(easyrestCE.i18n.error);
                    $button.prop('disabled', false).text('Process');
                }
            });
        },

        /**
         * Retry item
         */
        retryItem: function($button) {
            var self = this;
            var itemId = $button.data('id');

            $button.prop('disabled', true);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'retry_item',
                    item_id: itemId,
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data.message || easyrestCE.i18n.error));
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(easyrestCE.i18n.error);
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Delete item
         */
        deleteItem: function($button) {
            var self = this;
            var itemId = $button.data('id');
            var $row = $button.closest('tr');

            if (!confirm(easyrestCE.i18n.confirm_delete)) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: easyrestCE.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'easyrest_ce_action',
                    ce_action: 'delete_item',
                    item_id: itemId,
                    nonce: easyrestCE.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + (response.data.message || easyrestCE.i18n.error));
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(easyrestCE.i18n.error);
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Show result message
         */
        showResult: function($element, type, message) {
            $element
                .removeClass('success error')
                .addClass(type)
                .html(message)
                .show();

            // Auto-hide after 10 seconds
            setTimeout(function() {
                $element.fadeOut();
            }, 10000);
        },

        /**
         * Generate slug from text
         */
        generateSlug: function(text) {
            return text
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        EasyRestCEAdmin.init();
    });

})(jQuery);
