/**
 * Tahmir AI Admin JavaScript
 */

/* ==========================================================================
   GLOBAL: UgenticToast — inline toast notifications
   ========================================================================== */
window.UgenticToast = (function () {
    var container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'ugentic-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function show(message, type, duration) {
        type = type || 'info';
        duration = duration || 4000;
        var icons = { success: '✓', error: '✗', warning: '⚠', info: 'ℹ' };

        var toast = document.createElement('div');
        toast.className = 'ugentic-toast ugentic-toast--' + type;
        toast.innerHTML =
            '<span class="ug-toast-icon">' + (icons[type] || '') + '</span>' +
            '<span class="ug-toast-message">' + message + '</span>' +
            '<button class="ug-toast-close" aria-label="Dismiss">&times;</button>';

        toast.querySelector('.ug-toast-close').addEventListener('click', function () { dismiss(toast); });

        getContainer().appendChild(toast);

        if (duration > 0) {
            setTimeout(function () { dismiss(toast); }, duration);
        }
        return toast;
    }

    function dismiss(toast) {
        if (!toast || toast.classList.contains('ug-toast-leaving')) return;
        toast.classList.add('ug-toast-leaving');
        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
    }

    return {
        success: function (msg, dur) { return show(msg, 'success', dur); },
        error: function (msg, dur) { return show(msg, 'error', dur); },
        warning: function (msg, dur) { return show(msg, 'warning', dur); },
        info: function (msg, dur) { return show(msg, 'info', dur); }
    };
})();

/* ==========================================================================
   GLOBAL: UgenticConfirm — confirmation modal (supports typed confirmation)
   Usage: UgenticConfirm.show({ title, message, confirmText, type, typed })
          .then(function() { // confirmed })
          .catch(function() { // cancelled });
   ========================================================================== */
window.UgenticConfirm = (function () {
    function show(opts) {
        opts = opts || {};
        var title = opts.title || 'Are you sure?';
        var message = opts.message || '';
        var confirmText = opts.confirmText || 'Confirm';
        var type = opts.type || 'danger';       // 'danger' | 'primary'
        var typed = opts.typed || null;           // e.g. 'DELETE' — user must type this

        return new Promise(function (resolve, reject) {
            var overlay = document.createElement('div');
            overlay.className = 'ugentic-confirm-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'ug-confirm-title');

            var typedHtml = '';
            if (typed) {
                typedHtml =
                    '<div class="ug-confirm-typed">' +
                    '  <label for="ug-confirm-input">Type <strong>' + typed + '</strong> to confirm:</label>' +
                    '  <input type="text" id="ug-confirm-input" autocomplete="off" spellcheck="false">' +
                    '</div>';
            }

            overlay.innerHTML =
                '<div class="ugentic-confirm-box">' +
                '  <div class="ugentic-confirm-header"><h3 id="ug-confirm-title">' + title + '</h3></div>' +
                '  <div class="ugentic-confirm-body"><p>' + message + '</p>' + typedHtml + '</div>' +
                '  <div class="ugentic-confirm-footer">' +
                '    <button class="ug-confirm-cancel">Cancel</button>' +
                '    <button class="ug-confirm-action ug-confirm-action--' + type + '"' + (typed ? ' disabled' : '') + '>' + confirmText + '</button>' +
                '  </div>' +
                '</div>';

            document.body.appendChild(overlay);

            var actionBtn = overlay.querySelector('.ug-confirm-action');
            var cancelBtn = overlay.querySelector('.ug-confirm-cancel');
            var inputEl = overlay.querySelector('#ug-confirm-input');

            function cleanup() { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }

            cancelBtn.addEventListener('click', function () { cleanup(); reject(); });
            overlay.addEventListener('click', function (e) { if (e.target === overlay) { cleanup(); reject(); } });

            if (inputEl && typed) {
                inputEl.addEventListener('input', function () {
                    actionBtn.disabled = (inputEl.value !== typed);
                });
                inputEl.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && inputEl.value === typed) { cleanup(); resolve(); }
                });
                setTimeout(function () { inputEl.focus(); }, 100);
            }

            actionBtn.addEventListener('click', function () {
                if (typed && inputEl && inputEl.value !== typed) return;
                cleanup();
                resolve();
            });

            // Escape key to cancel
            function onKey(e) { if (e.key === 'Escape') { cleanup(); reject(); document.removeEventListener('keydown', onKey); } }
            document.addEventListener('keydown', onKey);
        });
    }

    return { show: show };
})();

(function ($) {
    'use strict';

    const UgenticAdmin = {
        init: function () {
            this.bindEvents();
            this.initTabs();
            this.initCodeEditors();
            if (!document.querySelector('[data-tahmir-pro-preview]')) this.initMenuManager();
            this.initSettingsTabs();
        },

        // Settings tab navigation
        initSettingsTabs: function () {
            var $wrap = $('.ugentic-settings-tabs');
            if (!$wrap.length) return;

            $wrap.find('.nav-tab-wrapper .nav-tab').on('click', function (e) {
                e.preventDefault();
                var target = $(this).attr('href');
                $wrap.find('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $wrap.find('.ugentic-tab-content').removeClass('active');
                $wrap.find(target).addClass('active');
            });

            if (window.location.hash && $wrap.find(window.location.hash).length) {
                $wrap.find('.nav-tab[href="' + window.location.hash + '"]').trigger('click');
            }
        },

        bindEvents: function () {
            // Settings form
            $('#ugentic-settings-form').on('submit', this.saveSettings.bind(this));
            $('#test-connection').on('click', this.testConnection.bind(this));

            // Assets
            $('.add-asset-btn').on('click', this.openAssetModal.bind(this));
            $('.edit-asset-btn').on('click', this.editAsset.bind(this));
            $('.delete-asset-btn').on('click', this.deleteAsset.bind(this));
            $('#asset-form').on('submit', this.saveAsset.bind(this));
            $('#header-form, #footer-form').on('submit', this.saveAsset.bind(this));

            // Bulk delete for assets
            $('.select-all-css, .select-all-js').on('change', this.toggleSelectAll.bind(this));
            $(document).on('change', '.asset-checkbox', this.updateBulkDeleteBtn.bind(this));
            $('.bulk-delete-btn').on('click', this.bulkDeleteAssets.bind(this));

            // Generate page
            $('#generate-form').on('submit', this.generatePage.bind(this));
            $('.template-btn, .template-chip').on('click', this.useTemplate.bind(this));

            // Match style toggle
            $('#match-style-toggle').on('change', function () {
                $('#reference-page-selector').toggle($(this).is(':checked'));
            });

            // Import
            this.initImportDragDrop();
            $('#website-zip').on('change', this.handleFileSelect.bind(this));
            $('#back-to-upload').on('click', this.backToUpload.bind(this));
            $('#continue-to-review').on('click', this.continueToReview.bind(this));
            $('#back-to-upload-from-analyze').on('click', this.backToUpload.bind(this));
            $('#start-import').on('click', this.startImport.bind(this));

            // Toggle global header sub-option visibility
            $('#import-detect-header-footer').on('change', function () {
                $('#global-header-option').toggle($(this).is(':checked'));
            });
            $('#import-another').on('click', this.resetImport.bind(this));

            // Modal
            $('.close-modal').on('click', this.closeModal.bind(this));
            $(document).on('click', '.ugentic-modal', function (e) {
                if ($(e.target).hasClass('ugentic-modal')) {
                    UgenticAdmin.closeModal();
                }
            });

            // History
            $('.view-history-btn').on('click', this.viewHistory.bind(this));
            $(document).on('click', '.ug-restore-revision-btn', function () {
                UgenticAdmin.restoreRevision($(this));
            });

            // Auto Generate Links
            $('#auto-generate-links').on('click', this.autoGenerateLinks.bind(this));

            // AI Scan Header/Footer
            $('#ai-scan-header-footer').on('click', this.aiScanHeaderFooter.bind(this));

            // Strip Headers/Footers
            $('#strip-headers-footers').on('click', this.stripHeadersFooters.bind(this));

            // Visual Editor for Header/Footer
            this.initVisualEditor();
        },

        // Initialize Visual Editor functionality
        initVisualEditor: function () {
            const self = this;

            // Open Visual Editor
            $('#visual-edit-header, #switch-to-visual-header').on('click', function () {
                const html = $('#header-content').val();
                $('#header-preview').html(html);
                $('#header-visual-editor').slideDown(200);
                $('#header-code-wrapper').hide();
                self.bindHeaderPreviewEvents();
            });

            // Close Visual Editor
            $('#header-close-visual').on('click', function () {
                const updatedHtml = $('#header-preview').html();
                $('#header-content').val(updatedHtml);
                // Trigger CodeMirror update if available
                if (typeof wp !== 'undefined' && wp.codeEditor) {
                    const cm = $('#header-content').next('.CodeMirror');
                    if (cm.length && cm[0].CodeMirror) {
                        cm[0].CodeMirror.setValue(updatedHtml);
                    }
                }
                $('#header-visual-editor').slideUp(200);
                $('#header-code-wrapper').show();
            });

            // Save Visual Changes
            $('#header-save-visual').on('click', function () {
                const updatedHtml = $('#header-preview').html();
                $('#header-content').val(updatedHtml);
                $('#header-form').trigger('submit');
            });

            // Add Menu Item
            $('#header-add-menu-item').on('click', function () {
                const menuName = prompt('Enter menu item text:', 'New Page');
                const menuUrl = prompt('Enter URL (or leave blank for #):', '');

                if (menuName) {
                    // Find existing nav or ul
                    const $preview = $('#header-preview');
                    let $nav = $preview.find('nav ul, ul.menu, ul.nav, .nav-menu').first();

                    if ($nav.length) {
                        // Clone existing menu item structure
                        const $existing = $nav.find('li').first();
                        if ($existing.length) {
                            const $clone = $existing.clone();
                            $clone.find('a').attr('href', menuUrl || '#').text(menuName);
                            $nav.append($clone);
                        } else {
                            $nav.append('<li><a href="' + (menuUrl || '#') + '">' + menuName + '</a></li>');
                        }
                    } else {
                        UgenticToast.warning('Could not find navigation menu structure. Please add manually in code view.');
                    }
                }
            });

            // Apply edit
            $('#header-edit-apply').on('click', function () {
                if (self.selectedHeaderElement) {
                    const newText = $('#header-edit-text').val();
                    $(self.selectedHeaderElement).text(newText);
                    $('#header-edit-tooltip').hide();
                    self.selectedHeaderElement = null;
                }
            });

            // Store reference
            this.selectedHeaderElement = null;
        },

        // Bind preview events for clicking elements
        bindHeaderPreviewEvents: function () {
            const $preview = $('#header-preview');
            const $tooltip = $('#header-edit-tooltip');
            const $textInput = $('#header-edit-text');
            const self = this;

            // Remove previous bindings
            $preview.off('click.visualEdit');

            // Click on any text element
            $preview.on('click.visualEdit', 'a, span, p, h1, h2, h3, h4, h5, h6, button, li, strong, em', function (e) {
                e.preventDefault();
                e.stopPropagation();

                self.selectedHeaderElement = this;
                const text = $(this).text();
                $textInput.val(text);

                // Position tooltip near element
                const rect = this.getBoundingClientRect();
                const containerRect = $('#header-preview-container')[0].getBoundingClientRect();
                $tooltip.css({
                    top: (rect.bottom - containerRect.top + 10) + 'px',
                    left: (rect.left - containerRect.left) + 'px'
                }).show();

                $textInput.focus().select();
            });

            // For links, also allow href editing
            $preview.on('dblclick.visualEdit', 'a', function (e) {
                e.preventDefault();
                const newUrl = prompt('Edit link URL:', $(this).attr('href'));
                if (newUrl !== null) {
                    $(this).attr('href', newUrl);
                }
            });

            // Highlight on hover
            $preview.on('mouseenter.visualEdit', 'a, span, p, h1, h2, h3, h4, h5, h6, button, li', function () {
                $(this).css('outline', '2px solid #f97316');
            }).on('mouseleave.visualEdit', 'a, span, p, h1, h2, h3, h4, h5, h6, button, li', function () {
                $(this).css('outline', 'none');
            });

            // Enter key applies edit
            $textInput.off('keydown.visualEdit').on('keydown.visualEdit', function (e) {
                if (e.key === 'Enter') {
                    $('#header-edit-apply').click();
                }
            });
        },

        // Tabs
        initTabs: function () {
            $('.ugentic-tabs .nav-tab-wrapper .nav-tab').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');
                const $tabs = $(this).closest('.ugentic-tabs');

                $tabs.find('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                $tabs.find('.ugentic-tab-content').removeClass('active');
                $tabs.find(target).addClass('active');
            });
        },

        // Code Editors
        initCodeEditors: function () {
            if (typeof wp !== 'undefined' && wp.codeEditor) {
                $('textarea[id$="-content"]').each(function () {
                    const settings = wp.codeEditor.defaultSettings || {};
                    settings.codemirror = $.extend({}, settings.codemirror, {
                        mode: 'htmlmixed',
                        lineNumbers: true,
                        lineWrapping: true
                    });
                    wp.codeEditor.initialize($(this), settings);
                });
            }
        },

        // Settings
        saveSettings: function (e) {
            e.preventDefault();

            const $form = $(e.target);
            const $btn = $form.find('button[type="submit"]');
            const $result = $('#save-result');

            $btn.prop('disabled', true);
            $result.html('<span class="spinner-inline"></span> ' + ugenticAdmin.strings.saving);

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=ugentic_save_settings',
                success: function (response) {
                    if (response.success) {
                        $form.find('input[type="password"]').val('');
                        $form.find('input[name^="clear_"]').prop('checked', false);
                        Object.entries(response.data.api_key_status || {}).forEach(function ([key, status]) {
                            if (!['api_key', 'anthropic_api_key', 'openai_api_key'].includes(key)) return;
                            const $input = $form.find('#' + key);
                            $input.attr('placeholder', status.placeholder);
                            $input.closest('td').find('code').text(status.mask).closest('p').toggle(Boolean(status.mask));
                        });
                        $result.css('color', '#46b450').text('✓ ' + response.data.message);
                    } else {
                        $result.css('color', '#dc3232').text('✗ ' + (response.data?.message || 'Error'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Save error:', error);
                    $result.html('<span style="color:#dc3232">✗ ' + ugenticAdmin.strings.error + '</span>');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () { $result.html(''); }, 3000);
                }
            });
        },

        testConnection: function () {
            const $btn = $('#test-connection');
            const $result = $('#test-result');

            $btn.prop('disabled', true);
            $result.html('<span class="spinner-inline"></span> ' + ugenticAdmin.strings.testing);

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_test_connection',
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $result.css('color', '#46b450').text('✓ ' + response.data.message);
                    } else {
                        $result.css('color', '#dc3232').text('✗ ' + response.data.message);
                    }
                },
                error: function () {
                    $result.html('<span style="color:#dc3232">✗ ' + ugenticAdmin.strings.error + '</span>');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        },

        // Assets
        openAssetModal: function (e) {
            const type = $(e.target).closest('button').data('type');

            $('#modal-title').text('Add New ' + type.toUpperCase() + ' File');
            $('#asset-id').val('');
            $('#asset-type').val(type);
            $('#asset-name').val('');
            $('#asset-content').val('');
            $('#asset-active').prop('checked', true);

            $('#asset-modal').show();
        },

        editAsset: function (e) {
            const $btn = $(e.target).closest('button');
            const id = $btn.data('id');
            const type = $btn.data('type');

            // In production, would fetch asset data via AJAX
            // For now, just show the modal
            $('#modal-title').text('Edit ' + type.toUpperCase() + ' File');
            $('#asset-id').val(id);
            $('#asset-type').val(type);

            $('#asset-modal').show();
        },

        // Helper to sync CodeMirror content to textarea
        syncCodeMirrorToTextarea: function ($textarea) {
            if (!$textarea.length) return;

            // WordPress wp.codeEditor stores the instance in the sibling .CodeMirror element
            const $cm = $textarea.next('.CodeMirror');
            if ($cm.length && $cm[0].CodeMirror) {
                $textarea.val($cm[0].CodeMirror.getValue());
            }
        },

        saveAsset: function (e) {
            e.preventDefault();

            const $form = $(e.target);
            const $btn = $form.find('button[type="submit"]');
            const $status = $form.find('.save-status');

            // Get content from CodeMirror if available
            const $textarea = $form.find('textarea[name="content"]');
            this.syncCodeMirrorToTextarea($textarea);

            $btn.prop('disabled', true);
            if ($status.length) {
                $status.html('<span class="spinner-inline"></span>');
            }

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=ugentic_save_asset&nonce=' + ugenticAdmin.nonce,
                success: function (response) {
                    if (response.success) {
                        // DEBUG: Show save verification
                        console.log('Save response:', response.data);
                        if (response.data.verified === false) {
                            UgenticToast.warning('Save may have failed. DB Result: ' + response.data.db_result + '. This might be a permissions issue.');
                        }

                        if ($status.length) {
                            $status.html('<span style="color:#46b450">✓ ' + response.data.message + '</span>');
                        } else {
                            UgenticAdmin.closeModal();
                            location.reload();
                        }
                    } else {
                        if ($status.length) {
                            $status.html('<span style="color:#dc3232">✗ ' + response.data.message + '</span>');
                        } else {
                            UgenticToast.error(response.data.message);
                        }
                    }
                },
                error: function () {
                    if ($status.length) {
                        $status.html('<span style="color:#dc3232">✗ ' + ugenticAdmin.strings.error + '</span>');
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        },

        deleteAsset: function (e) {
            UgenticConfirm.show({
                title: 'Delete Asset',
                message: 'Are you sure you want to delete this asset? This cannot be undone.',
                confirmText: 'Delete',
                type: 'danger'
            }).then(function () {
                self._doDeleteAsset($(e.target).closest('button'));
            }).catch(function () { });
        },

        _doDeleteAsset: function ($btn) {
            var id = $btn.data('id');

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_delete_asset',
                    id: id,
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(function () {
                            $(this).remove();
                        });
                    } else {
                        UgenticToast.error(response.data.message);
                    }
                }
            });
        },

        // Bulk Delete Methods
        toggleSelectAll: function (e) {
            const $checkbox = $(e.target);
            const type = $checkbox.hasClass('select-all-css') ? 'css' : 'js';
            const $container = $checkbox.closest('.ugentic-card');
            $container.find('.asset-checkbox').prop('checked', $checkbox.is(':checked'));
            this.updateBulkDeleteBtn();
        },

        updateBulkDeleteBtn: function () {
            // CSS
            const cssChecked = $('#tab-css .asset-checkbox:checked').length;
            $('#tab-css .bulk-delete-btn').toggle(cssChecked > 0);

            // JS
            const jsChecked = $('#tab-js .asset-checkbox:checked').length;
            $('#tab-js .bulk-delete-btn').toggle(jsChecked > 0);
        },

        bulkDeleteAssets: function (e) {
            const type = $(e.target).closest('button').data('type');
            const $container = type === 'css' ? $('#tab-css') : $('#tab-js');
            const ids = [];

            $container.find('.asset-checkbox:checked').each(function () {
                ids.push($(this).val());
            });

            if (ids.length === 0) return;

            var self = this;
            UgenticConfirm.show({
                title: 'Delete ' + ids.length + ' Assets',
                message: 'Are you sure you want to delete ' + ids.length + ' selected assets? This cannot be undone.',
                confirmText: 'Delete All',
                type: 'danger'
            }).then(function () {
                self._doBulkDeleteAssets($(e.target).closest('button'), ids, type);
            }).catch(function () { });
        },

        _doBulkDeleteAssets: function ($btn, ids, type) {
            var $container = type === 'css' ? $('#tab-css') : $('#tab-js');
            $btn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_bulk_delete_assets',
                    ids: ids,
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        ids.forEach(function (id) {
                            $container.find('tr[data-id="' + id + '"]').fadeOut(function () {
                                $(this).remove();
                            });
                        });
                        $container.find('.select-all-css, .select-all-js').prop('checked', false);
                        $btn.hide();
                    } else {
                        UgenticToast.error(response.data?.message || 'Error deleting assets');
                    }
                },
                error: function () {
                    UgenticToast.error('Error deleting assets');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delete Selected');
                }
            });
        },

        // Generate Page
        generatePage: function (e) {
            e.preventDefault();

            const $form = $(e.target);
            const $btn = $('#generate-btn');
            const $status = $('#generate-status');

            $btn.prop('disabled', true);
            $status.html('<span class="spinner-inline"></span> ' + ugenticAdmin.strings.generating);

            // Build data with optional reference_page_id
            let formData = $form.serialize() + '&action=ugentic_generate_page&nonce=' + ugenticAdmin.nonce;

            // Include reference_page_id if style matching is enabled
            const $matchToggle = $('#match-style-toggle');
            if ($matchToggle.length && $matchToggle.is(':checked')) {
                const refId = $('#reference-page').val();
                if (refId && refId !== '0') {
                    formData += '&reference_page_id=' + refId;
                }
            }

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                timeout: 180000, // 3 minute timeout for page generation
                success: function (response) {
                    if (response.success) {
                        $status.html('<span style="color:#46b450">✓ ' + response.data.message + '</span>');

                        // Show preview
                        $('#preview-frame').attr('srcdoc', response.data.html_preview);
                        $('#view-page-btn').attr('href', response.data.view_url);
                        $('#edit-page-btn').attr('href', response.data.edit_url);
                        $('#preview-container').show();

                        // Scroll to preview
                        $('html, body').animate({
                            scrollTop: $('#preview-container').offset().top - 50
                        }, 500);
                    } else {
                        $status.html('<span style="color:#dc3232">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function (xhr, status, error) {
                    let msg = ugenticAdmin.strings.error;
                    if (status === 'timeout') {
                        msg = 'Request timed out. The AI may be taking too long. Please try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        msg = xhr.responseJSON.data.message || msg;
                    }
                    $status.html('<span style="color:#dc3232">✗ ' + msg + '</span>');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        },

        useTemplate: function (e) {
            const $chip = $(e.target).closest('.template-btn, .template-chip');
            const prompt = $chip.data('prompt');
            if (prompt) {
                $('#page-prompt').val(prompt).focus();
            }
        },

        getAjaxErrorMessage: function (xhr, status, fallback) {
            fallback = fallback || 'Network error';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                return xhr.responseJSON.data.message;
            }
            if (status === 'timeout') {
                return 'Request timed out. The import may still be busy; retrying can help.';
            }
            if (xhr && xhr.status === 505) {
                return 'HTTP 505 from the server/proxy. This is usually temporary; retrying the request.';
            }
            if (xhr && xhr.status >= 500) {
                return 'HTTP ' + xhr.status + ' from the server. This is usually temporary; retrying the request.';
            }
            if (xhr && xhr.status) {
                return fallback + ' (HTTP ' + xhr.status + ')';
            }
            return fallback;
        },

        appendImportLog: function (message, type) {
            type = type || 'info';
            const $entry = $('<div class="log-entry"></div>').addClass('log-' + type).text(message);
            $('#import-log').append($entry);
            const log = $('#import-log')[0];
            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        },

        updateImportProgress: function (stage, percent, status) {
            percent = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
            $('#import-progress-fill').css('width', percent + '%');
            if (status) {
                $('#import-status').text(status);
            }

            const order = ['prepare', 'pages', 'links', 'menus', 'complete'];
            const activeIndex = Math.max(0, order.indexOf(stage));
            $('#import-progress-stages span').each(function () {
                const itemStage = $(this).data('stage');
                const itemIndex = order.indexOf(itemStage);
                $(this)
                    .toggleClass('is-active', itemStage === stage)
                    .toggleClass('is-done', itemIndex >= 0 && itemIndex < activeIndex);
            });
        },

        // Import
        initImportDragDrop: function () {
            const $zone = $('#upload-zone');

            $zone.on('dragover dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });

            $zone.on('dragleave dragend drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });

            $zone.on('drop', function (e) {
                const files = e.originalEvent.dataTransfer.files;
                if (files.length) {
                    $('#website-zip')[0].files = files;
                    UgenticAdmin.handleFileSelect();
                }
            });

            // Import mode toggle: show/hide advanced options
            $('#import-mode').on('change', function () {
                if ($(this).val() === 'advanced') {
                    $('#advanced-import-options').slideDown(200);
                } else {
                    $('#advanced-import-options').slideUp(200);
                }
            });
        },

        handleFileSelect: function () {
            const file = $('#website-zip')[0].files[0];
            if (!file) return;

            const $zone = $('#upload-zone');
            const $content = $zone.find('.upload-zone-content');
            const $progress = $zone.find('.upload-progress');

            $content.hide();
            $progress.show();

            const formData = new FormData();
            formData.append('website_zip', file);
            formData.append('action', 'ugentic_upload_website');
            formData.append('nonce', ugenticAdmin.nonce);

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            $zone.find('.progress-fill').css('width', percent + '%');
                        }
                    });
                    return xhr;
                },
                success: function (response) {
                    if (response.success) {
                        UgenticAdmin.importData = response.data;
                        UgenticAdmin.showAnalysisStep(response.data);
                    } else {
                        var errorMsg = response.data.message;
                        // If the error is about a stuck import lock, show a Stop Import button
                        if (errorMsg && errorMsg.toLowerCase().indexOf('already in progress') !== -1) {
                            $('#upload-error').html(
                                '<p>' + errorMsg + '</p>' +
                                '<button type="button" class="button button-secondary" id="force-clear-import-lock" style="margin-top:8px;">' +
                                '<span class="dashicons dashicons-no" style="margin-top:4px;"></span> Stop Import &amp; Clear Lock' +
                                '</button>'
                            ).show();
                            // Bind the stop-import button
                            $('#force-clear-import-lock').off('click').on('click', function () {
                                var $btn = $(this);
                                $btn.prop('disabled', true).text('Clearing...');
                                $.ajax({
                                    url: ugenticAdmin.ajaxUrl,
                                    type: 'POST',
                                    data: {
                                        action: 'ugentic_force_clear_import_lock',
                                        nonce: ugenticAdmin.nonce
                                    },
                                    success: function (resp) {
                                        if (resp.success) {
                                            $('#upload-error').html('<p style="color:#46b450;">✓ ' + resp.data.message + '</p>').show();
                                            setTimeout(function () { $('#upload-error').fadeOut(); }, 3000);
                                        } else {
                                            $btn.prop('disabled', false).text('Stop Import & Clear Lock');
                                            UgenticToast.error(resp.data?.message || 'Failed to clear lock');
                                        }
                                    },
                                    error: function () {
                                        $btn.prop('disabled', false).text('Stop Import & Clear Lock');
                                        UgenticToast.error('Failed to clear import lock');
                                    }
                                });
                            });
                        } else {
                            $('#upload-error').html(errorMsg).show();
                        }
                        $content.show();
                        $progress.hide();
                    }
                },
                error: function (xhr, status) {
                    $('#upload-error').html(UgenticAdmin.getAjaxErrorMessage(xhr, status, ugenticAdmin.strings.error)).show();
                    $content.show();
                    $progress.hide();
                }
            });
        },

        showAnalysisStep: function (data) {
            const analysis = data.ai_analysis || {};
            const files = data.files || {};

            // Hide upload, show analysis step
            $('#step-upload').removeClass('active').hide();
            $('#step-analyze').addClass('active').show();

            // Simulate analysis progress
            const steps = ['analyze-html', 'analyze-css', 'analyze-js', 'analyze-images'];
            steps.forEach(function (step, i) {
                setTimeout(function () {
                    const $el = $('#' + step);
                    $el.find('.dashicons').removeClass('dashicons-clock dashicons-update spin').addClass('dashicons-yes-alt').css('color', '#46b450');
                }, (i + 1) * 400);
            });

            // Show results after animation
            setTimeout(function () {
                $('#analysis-results').show();
                $('#analyze-actions').show();

                // Populate results cards
                $('#detected-libs').text((analysis.dependencies || []).length);
                $('#detected-issues').text((analysis.warnings || []).length);
                $('#detected-optimizations').text(analysis.ai_powered ? '✓ AI' : '—');

                // Show detected libraries
                const $libList = $('#lib-list');
                $libList.empty();
                if (analysis.dependencies && analysis.dependencies.length > 0) {
                    analysis.dependencies.forEach(function (dep) {
                        $libList.append('<span class="lib-badge">' + dep + '</span> ');
                    });
                }

                // Framework detection
                if (analysis.framework && analysis.framework !== 'Unknown') {
                    $libList.prepend('<div style="margin-bottom:8px;"><strong>Framework:</strong> ' + analysis.framework + '</div>');
                }

                // AI recommendation
                if (analysis.suggestion || (analysis.warnings && analysis.warnings.length > 0)) {
                    const $recs = $('#ai-recommendations');
                    const $recList = $('#rec-list');
                    $recList.empty();

                    if (analysis.suggestion) {
                        $recList.append('<li><strong>💡 </strong>' + analysis.suggestion + '</li>');
                    }
                    (analysis.warnings || []).forEach(function (w) {
                        $recList.append('<li><strong>⚠️ </strong>' + w + '</li>');
                    });
                    $recs.show();
                }
            }, steps.length * 400 + 200);
        },

        continueToReview: function () {
            if (this.importData) {
                this.showReviewStep(this.importData.files);
                // Also hide analysis step
                $('#step-analyze').removeClass('active').hide();
            }
        },

        showReviewStep: function (files) {
            // Populate file lists
            const $htmlList = $('#html-files');
            const $cssList = $('#css-files');
            const $jsList = $('#js-files');
            const $imageList = $('#image-files');

            $htmlList.empty();
            $cssList.empty();
            $jsList.empty();
            $imageList.empty();

            // HTML files
            files.html_files.forEach(function (file) {
                $htmlList.append(`
                    <div class="file-item">
                        <input type="checkbox" name="html_files[]" value="${file.path}" checked>
                        <span>${file.title || file.name}</span>
                    </div>
                `);
            });
            $('#html-count').text(files.html_files.length);

            // CSS files
            files.css_files.forEach(function (file) {
                $cssList.append(`
                    <div class="file-item">
                        <input type="checkbox" name="css_files[]" value="${file.path}" checked>
                        <span>${file.name}</span>
                    </div>
                `);
            });
            $('#css-count').text(files.css_files.length);

            // JS files
            files.js_files.forEach(function (file) {
                $jsList.append(`
                    <div class="file-item">
                        <input type="checkbox" name="js_files[]" value="${file.path}" checked>
                        <span>${file.name}</span>
                    </div>
                `);
            });
            $('#js-count').text(files.js_files.length);

            // Images
            $('#image-count').text(files.images.length);

            // Show review step
            $('#step-upload').removeClass('active').hide();
            $('#step-review').addClass('active').show();
        },

        backToUpload: function () {
            $('#step-review').removeClass('active').hide();
            $('#step-analyze').removeClass('active').hide();
            $('#step-upload').addClass('active').show();

            // Reset upload zone
            const $zone = $('#upload-zone');
            $zone.find('.upload-zone-content').show();
            $zone.find('.upload-progress').hide();
            $zone.find('.progress-fill').css('width', '0%');
            $('#website-zip').val('');
        },

        startImport: function () {
            // Prevent double import
            if (this.isImporting) {
                return;
            }
            this.isImporting = true;
            this.dynamicSuggestions = [];

            const selectedFiles = [];
            $('input[name="html_files[]"]:checked').each(function () {
                selectedFiles.push($(this).val());
            });

            if (selectedFiles.length === 0) {
                UgenticToast.warning('Please select at least one HTML file to import.');
                this.isImporting = false;
                return;
            }

            // Disable the import button
            $('#start-import').prop('disabled', true);

            // Show progress step
            $('#step-review').removeClass('active').hide();
            $('#step-progress').addClass('active').show();
            $('#import-log').empty();
            this.updateImportProgress('prepare', 5, 'Preparing import session...');
            this.appendImportLog('Preparing to import ' + selectedFiles.length + ' page(s).', 'info');

            // Import files one by one
            UgenticAdmin.importFilesReliable(selectedFiles, 0, 0);
        },

        importFilesReliable: function (files, index, attempt) {
            attempt = attempt || 0;
            if (index >= files.length) {
                UgenticAdmin.importComplete();
                return;
            }

            const file = files[index];
            const maxAttempts = 3;
            const progress = 8 + ((index) / files.length) * 70;
            const nextProgress = 8 + ((index + 1) / files.length) * 70;
            const shortName = file.split(/[\\/]/).pop();

            this.updateImportProgress('pages', progress, 'Importing page ' + (index + 1) + ' of ' + files.length + (attempt ? ' (retry ' + (attempt + 1) + '/' + maxAttempts + ')' : ''));

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                timeout: 180000,
                data: {
                    action: 'ugentic_import_page',
                    defer_link_rewrite: 1,
                    html_file: file,
                    import_mode: $('#import-mode').val() || 'faithful',
                    css_handling: $('#import-css-handling').val(),
                    js_handling: $('#import-js-handling').val(),
                    image_handling: $('#import-image-handling').val(),
                    detect_header_footer: $('#import-detect-header-footer').is(':checked') ? 1 : 0,
                    make_header_global: $('#import-make-header-global').is(':checked') ? 1 : 0,
                    publish: $('#import-publish').is(':checked') ? 1 : 0,
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        UgenticAdmin.updateImportProgress('pages', nextProgress, 'Imported page ' + (index + 1) + ' of ' + files.length);
                        UgenticAdmin.appendImportLog('Imported: ' + (response.data?.message || shortName), 'success');
                        UgenticAdmin.reportBlogDetection(response.data && response.data.blog_detection, response.data?.page_title || shortName);

                        if (response.data && response.data.dynamic_suggestion) {
                            UgenticAdmin.dynamicSuggestions.push({
                                page_title: response.data.page_title || shortName.replace(/\.html?$/i, ''),
                                page_id: response.data.ugentic_id || 0,
                                wp_page_id: response.data.page_id || 0,
                                detection: response.data.dynamic_suggestion
                            });
                        }
                    } else {
                        UgenticAdmin.appendImportLog('Failed: ' + (response.data?.message || 'Unknown import error'), 'error');
                    }

                    UgenticAdmin.importFilesReliable(files, index + 1, 0);
                },
                error: function (xhr, status) {
                    const msg = UgenticAdmin.getAjaxErrorMessage(xhr, status, 'Error importing file');
                    if (attempt + 1 < maxAttempts) {
                        UgenticAdmin.appendImportLog('Temporary error importing ' + shortName + ': ' + msg + ' Retrying attempt ' + (attempt + 2) + ' of ' + maxAttempts + '...', 'warning');
                        UgenticAdmin.updateImportProgress('pages', progress, 'Retrying page ' + (index + 1) + ' of ' + files.length);
                        setTimeout(function () {
                            UgenticAdmin.importFilesReliable(files, index, attempt + 1);
                        }, 900 * (attempt + 1));
                        return;
                    }

                    UgenticAdmin.appendImportLog('Error importing ' + shortName + ' after ' + maxAttempts + ' attempts: ' + msg, 'error');
                    UgenticAdmin.importFilesReliable(files, index + 1, 0);
                }
            });
        },

        importFiles: function (files, index, attempt) {
            attempt = attempt || 0;
            if (index >= files.length) {
                UgenticAdmin.importComplete();
                return;
            }

            const file = files[index];
            const maxAttempts = 3;
            const progress = 8 + ((index) / files.length) * 70;
            const nextProgress = 8 + ((index + 1) / files.length) * 70;

            this.updateImportProgress('pages', progress, 'Importing page ' + (index + 1) + ' of ' + files.length + (attempt ? ' (retry ' + (attempt + 1) + '/' + maxAttempts + ')' : ''));

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                timeout: 180000,
                data: {
                    action: 'ugentic_import_page',
                    defer_link_rewrite: 1,
                    html_file: file,
                    import_mode: $('#import-mode').val() || 'faithful',
                    css_handling: $('#import-css-handling').val(),
                    js_handling: $('#import-js-handling').val(),
                    image_handling: $('#import-image-handling').val(),
                    detect_header_footer: $('#import-detect-header-footer').is(':checked') ? 1 : 0,
                    make_header_global: $('#import-make-header-global').is(':checked') ? 1 : 0,
                    publish: $('#import-publish').is(':checked') ? 1 : 0,
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#import-log').append('<div class="log-entry log-success">✓ Imported: ' + response.data.message + '</div>');

                        UgenticAdmin.reportBlogDetection(response.data.blog_detection, response.data.page_title || file.split('/').pop());

                        // Capture dynamic content suggestions
                        if (response.data.dynamic_suggestion) {
                            UgenticAdmin.dynamicSuggestions.push({
                                page_title: response.data.page_title || file.split('/').pop().replace('.html', ''),
                                page_id: response.data.ugentic_id || 0,
                                wp_page_id: response.data.page_id || 0,
                                detection: response.data.dynamic_suggestion
                            });
                        }
                    } else {
                        $('#import-log').append('<div class="log-entry log-error">✗ Failed: ' + response.data.message + '</div>');
                    }

                    // Continue with next file
                    UgenticAdmin.importFiles(files, index + 1);
                },
                error: function () {
                    $('#import-log').append('<div class="log-entry log-error">✗ Error importing file</div>');
                    UgenticAdmin.importFiles(files, index + 1);
                }
            });
        },

        reportBlogDetection: function (detection, pageTitle) {
            if (!detection || ['archive', 'single'].indexOf(detection.blog_role) === -1) {
                return;
            }
            var confidence = Math.round((parseFloat(detection.confidence) || 0) * 100);
            var role = detection.blog_role === 'single' ? 'single post' : 'archive';
            this.appendImportLog(
                'Blog ' + role + ' detected on ' + (pageTitle || 'imported page')
                + ' (' + confidence + '%). Review it in Settings > Blog before conversion.',
                'info'
            );
        },

        importComplete: function () {
            var finalizeWarning = false;
            this.updateImportProgress('links', 84, 'Replacing internal links...');

            // Post-import: replace .html links with WordPress page URLs
            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_replace_internal_links',
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (!response || !response.success) {
                        finalizeWarning = true;
                        UgenticAdmin.appendImportLog('Pages were imported, but internal links need another pass. ' + (response && response.data && response.data.message || 'Please retry link replacement from Manage.'), 'warning');
                        return;
                    }
                    if (response.success && response.data.links_replaced > 0) {
                        $('#import-log').append('<div class="log-entry log-success">✓ Replaced ' + response.data.links_replaced + ' internal link(s) across ' + response.data.pages_updated + ' page(s)</div>');
                    }
                },
                error: function () {
                    finalizeWarning = true;
                    UgenticAdmin.appendImportLog('Pages were imported, but link replacement could not finish. Please retry from Manage.', 'warning');
                },
                complete: function () {
                    // Step 2: Detect and import menus into WordPress native menus
                    UgenticAdmin.updateImportProgress('menus', 92, 'Detecting navigation menus...');
                    $.ajax({
                        url: ugenticAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ugentic_detect_import_menus',
                            nonce: ugenticAdmin.nonce
                        },
                        success: function (menuResponse) {
                            if (!menuResponse || !menuResponse.success) {
                                finalizeWarning = true;
                                UgenticAdmin.appendImportLog('Pages were imported, but menu detection did not finish. ' + (menuResponse && menuResponse.data && menuResponse.data.message || 'Please review navigation from Manage.'), 'warning');
                                return;
                            }
                            if (menuResponse.success && menuResponse.data.menus_created > 0) {
                                $('#import-log').append('<div class="log-entry log-success">✓ ' + menuResponse.data.message + '</div>');
                            } else if (menuResponse.success) {
                                $('#import-log').append('<div class="log-entry log-info" style="color:#666;">ℹ ' + menuResponse.data.message + '</div>');
                            }
                        },
                        error: function () {
                            finalizeWarning = true;
                            UgenticAdmin.appendImportLog('Pages were imported, but menu detection could not finish. Please review navigation from Manage.', 'warning');
                        },
                        complete: function () {
                            UgenticAdmin.isImporting = false;
                            UgenticAdmin.updateImportProgress('complete', 100, finalizeWarning ? 'Pages imported. Review the warnings below.' : 'Import complete.');
                            $('#start-import').prop('disabled', false);
                            $('#step-progress').removeClass('active').hide();
                            $('#step-complete').addClass('active').show();

                            // Show dynamic content suggestions if any were detected
                            UgenticAdmin.renderDynamicSuggestions();
                        }
                    });
                }
            });
        },

        renderDynamicSuggestions: function () {
            var suggestions = (this.dynamicSuggestions || []).filter(function (s) {
                var d = s.detection || {};
                return d.content_type && (!d.blog_role || d.blog_role === 'archive');
            });
            if (suggestions.length === 0) return;

            var $container = $('#dynamic-suggestion-cards');
            $container.empty();

            suggestions.forEach(function (s) {
                var d = s.detection;
                var confidence = d.confidence || 0;
                var confidenceLabel = confidence >= 0.7 ? 'High' : confidence >= 0.4 ? 'Medium' : 'Low';
                var confidenceColor = confidence >= 0.7 ? '#46b450' : confidence >= 0.4 ? '#f0b849' : '#999';
                var icon = d.icon || 'dashicons-portfolio';

                var cardHtml = '<div class="dynamic-suggestion-card" data-page-id="' + s.page_id + '">' +
                    '<div class="suggestion-card-header">' +
                    '<span class="dashicons ' + icon + '"></span>' +
                    '<div class="suggestion-card-info">' +
                    '<strong>' + (s.page_title || 'Untitled') + '</strong>' +
                    '<span class="suggestion-type">' + (d.label || d.content_type || 'Items') + '</span>' +
                    '</div>' +
                    '<span class="confidence-badge" style="background:' + confidenceColor + ';">' + confidenceLabel + '</span>' +
                    '</div>' +
                    '<div class="suggestion-card-body">' +
                    '<p>Detected <strong>' + (d.content_type || 'repeating content') + '</strong> pattern' +
                    (d.item_count ? ' (' + d.item_count + ' items)' : '') + '</p>' +
                    '</div>' +
                    '<div class="suggestion-card-actions">' +
                    '<button type="button" class="button button-primary button-small map-visually-btn" ' +
                    'data-page-id="' + s.page_id + '" data-wp-page-id="' + (s.wp_page_id || 0) + '" ' +
                    'data-page-title="' + (s.page_title || '').replace(/"/g, '&quot;') + '" ' +
                    'data-detection=\'' + JSON.stringify(d) + '\'>' +
                    '<span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> Map Visually' +
                    '</button>' +
                    '<button type="button" class="button button-small convert-to-dynamic-btn" ' +
                    'data-page-id="' + s.page_id + '" data-detection=\'' + JSON.stringify(d) + '\'>' +
                    '<span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> Auto Convert' +
                    '</button>' +
                    '<button type="button" class="button button-small dismiss-suggestion-btn">Dismiss</button>' +
                    '</div>' +
                    '</div>';

                $container.append(cardHtml);
            });

            // Bind visual map buttons
            $container.find('.map-visually-btn').on('click', function () {
                var $btn = $(this);
                if (typeof UgenticDataMapper !== 'undefined') {
                    UgenticDataMapper.open({
                        pageId: $btn.data('page-id'),
                        wpPageId: $btn.data('wp-page-id'),
                        pageTitle: $btn.attr('data-page-title'),
                        detection: $btn.data('detection'),
                        $card: $btn.closest('.dynamic-suggestion-card')
                    });
                } else {
                    UgenticToast.error('Visual mapper not loaded. Please refresh the page.');
                }
            });

            // Bind convert buttons
            $container.find('.convert-to-dynamic-btn').on('click', function () {
                UgenticAdmin.convertToDynamic($(this));
            });

            // Bind dismiss buttons
            $container.find('.dismiss-suggestion-btn').on('click', function () {
                $(this).closest('.dynamic-suggestion-card').fadeOut(300, function () {
                    $(this).remove();
                    if ($('#dynamic-suggestion-cards').children(':visible').length === 0) {
                        $('#dynamic-suggestions').fadeOut();
                    }
                });
            });

            $('#dynamic-suggestions').show();
        },

        convertToDynamic: function ($btn) {
            var pageId = $btn.data('page-id');
            var detection = $btn.data('detection');
            var $card = $btn.closest('.dynamic-suggestion-card');

            $btn.prop('disabled', true).html('<span class="spinner-inline"></span> Converting...');

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_convert_to_dynamic',
                    page_id: pageId,
                    analysis: JSON.stringify(detection),
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $card.addClass('converted');
                        $card.find('.suggestion-card-actions').html(
                            '<div class="conversion-success">' +
                            '<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ' +
                            response.data.message +
                            '</div>'
                        );
                        UgenticToast.success(response.data.message);
                    } else {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> Convert to Dynamic');
                        UgenticToast.error(response.data?.message || 'Conversion failed');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> Convert to Dynamic');
                    UgenticToast.error('Error converting to dynamic content');
                }
            });
        },

        resetImport: function () {
            this.isImporting = false;
            this.dynamicSuggestions = [];
            $('#start-import').prop('disabled', false);
            $('#step-complete').removeClass('active').hide();
            $('#step-upload').addClass('active').show();
            $('#dynamic-suggestions').hide();
            $('#dynamic-suggestion-cards').empty();

            // Reset everything
            const $zone = $('#upload-zone');
            $zone.find('.upload-zone-content').show();
            $zone.find('.upload-progress').hide();
            $zone.find('.progress-fill').css('width', '0%');
            $('#website-zip').val('');
            $('#import-log').empty();
            this.updateImportProgress('prepare', 0, '');
        },

        // Modal
        closeModal: function () {
            $('.ugentic-modal').hide();
        },

        // History
        viewHistory: function (e) {
            const self = this;
            const pageId = $(e.target).closest('.view-history-btn').data('id');
            const $content = $('#history-content');
            $content.html('<p>Loading history...</p>');
            $('#history-modal').show();

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_get_page_history',
                    nonce: ugenticAdmin.nonce,
                    page_id: pageId
                },
                success: function (response) {
                    if (!response.success) {
                        $content.html('<p style="color:#dc3232;">' + self.escHtml(response.data?.message || 'Could not load page history.') + '</p>');
                        return;
                    }

                    const history = response.data.history || [];
                    if (!history.length) {
                        $content.html('<p>No revisions saved for this page yet. Save the page once after editing to create the first revision.</p>');
                        return;
                    }

                    let html = '<div class="ug-history-list" style="display:grid;gap:10px;">';
                    history.forEach(function (item, index) {
                        const title = item.action ? item.action.replace(/_/g, ' ') : 'revision';
                        const prompt = item.prompt || '';
                        const user = item.user_name || 'Unknown user';
                        const created = item.created_at || '';
                        const oldSize = item.old_content ? item.old_content.length : 0;
                        const newSize = item.new_content ? item.new_content.length : 0;
                        const delta = oldSize && newSize ? (newSize - oldSize) : 0;
                        html += '<div class="ug-history-row" style="border:1px solid #dcdcde;border-radius:8px;padding:12px;background:#fff;">' +
                            '<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">' +
                                '<div style="min-width:0;">' +
                                    '<strong style="text-transform:capitalize;">' + self.escHtml(title) + '</strong>' +
                                    '<div style="color:#646970;font-size:12px;margin-top:3px;">' + self.escHtml(created) + ' by ' + self.escHtml(user) + '</div>' +
                                    '<div style="color:#50575e;font-size:12px;margin-top:6px;">' + self.escHtml(prompt.substring(0, 160)) + (prompt.length > 160 ? '&hellip;' : '') + '</div>' +
                                    '<div style="color:#646970;font-size:11px;margin-top:6px;">Revision #' + (history.length - index) + (delta ? ' · ' + (delta > 0 ? '+' : '') + Math.round(delta / 1024) + 'KB' : '') + '</div>' +
                                '</div>' +
                                '<button type="button" class="button button-small ug-restore-revision-btn" data-history-id="' + item.id + '" data-page-id="' + pageId + '">Restore this revision</button>' +
                            '</div>' +
                        '</div>';
                    });
                    html += '</div>';
                    $content.html(html);
                },
                error: function () {
                    $content.html('<p style="color:#dc3232;">Network error while loading page history.</p>');
                }
            });
        },

        restoreRevision: function ($btn) {
            const self = this;
            const historyId = $btn.data('history-id');
            const pageId = $btn.data('page-id');
            if (!historyId || !pageId) return;
            if (!confirm('Restore this saved revision? The current page content will become a new restore entry in history.')) return;

            $btn.prop('disabled', true).text('Restoring...');
            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_restore_from_history',
                    nonce: ugenticAdmin.nonce,
                    history_id: historyId,
                    page_id: pageId,
                    revision_side: 'new'
                },
                success: function (response) {
                    if (response.success) {
                        $('#history-content').prepend('<div class="notice notice-success inline"><p>Revision restored. Reloading page list...</p></div>');
                        setTimeout(function () { window.location.reload(); }, 800);
                    } else {
                        alert(response.data?.message || 'Could not restore revision.');
                        $btn.prop('disabled', false).text('Restore this revision');
                    }
                },
                error: function () {
                    alert('Network error while restoring revision.');
                    $btn.prop('disabled', false).text('Restore this revision');
                }
            });
        },

        // Auto Generate Links
        autoGenerateLinks: function () {
            const $btn = $('#auto-generate-links');
            const $status = $('.auto-link-status');

            $btn.prop('disabled', true);
            $status.text('Processing...').css('color', '#666');

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_auto_generate_links',
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $status.text(response.data.message).css('color', '#46b450');

                        // DEBUG: Log what was found
                        if (response.data.debug_hrefs_in_header) {
                            console.log('Hrefs found in header:', response.data.debug_hrefs_in_header);
                            console.log('Page slugs:', response.data.debug_page_slugs);

                            // Show alert with debug info if 0 replacements
                            if (response.data.count === 0) {
                                alert('DEBUG INFO:\n\nHrefs in header:\n' + response.data.debug_hrefs_in_header.join('\n') + '\n\nPage slugs:\n' + response.data.debug_page_slugs.join('\n'));
                            }
                        }

                        // Update the textarea with new content
                        const $textarea = $('#header-content');
                        if ($textarea.length) {
                            $textarea.val(response.data.content);

                            // If CodeMirror is active, update it too
                            const cmInstance = $textarea.next('.CodeMirror');
                            if (cmInstance.length && cmInstance[0].CodeMirror) {
                                cmInstance[0].CodeMirror.setValue(response.data.content);
                            }
                        }
                    } else {
                        $status.text(response.data.message).css('color', '#dc3232');
                    }
                },
                error: function () {
                    $status.text('Error occurred').css('color', '#dc3232');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () { $status.text(''); }, 5000);
                }
            });
        },

        // AI Scan Header/Footer
        aiScanHeaderFooter: function () {
            const $btn = $('#ai-scan-header-footer');
            const $status = $('#ai-scan-status');
            const stripFromPages = $('#strip-from-pages').is(':checked');

            $btn.prop('disabled', true).text('Scanning...');
            $status.show().html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Scanning all pages for header and footer patterns...');

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_ai_scan_header_footer',
                    nonce: ugenticAdmin.nonce,
                    strip_from_pages: stripFromPages ? 1 : 0
                },
                success: function (response) {
                    if (response.success) {
                        $status.html('<span class="dashicons dashicons-yes-alt" style="color: #90EE90;"></span> ' + response.data.message + ' Reloading page...');
                        // Reload to show updated content
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        $status.html('<span class="dashicons dashicons-warning" style="color: #ffcccb;"></span> ' + response.data.message);
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Scan All Pages');
                    }
                },
                error: function () {
                    $status.html('<span class="dashicons dashicons-warning" style="color: #ffcccb;"></span> Error occurred while scanning');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Scan All Pages');
                }
            });
        },

        // Strip Headers/Footers from all pages
        stripHeadersFooters: function () {
            var self = this;
            UgenticConfirm.show({
                title: 'Strip Headers & Footers',
                message: 'This will remove all header and footer elements from the page content. The global header/footer will still be displayed on the frontend.',
                confirmText: 'Strip All',
                type: 'danger'
            }).then(function () {
                self._doStripHeadersFooters();
            }).catch(function () { });
        },

        _doStripHeadersFooters: function () {
            const $btn = $('#strip-headers-footers');
            const $status = $('#ai-scan-status');

            $btn.prop('disabled', true).text('Stripping...');
            $status.show().html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Removing headers/footers from all pages...');

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_strip_headers_footers',
                    nonce: ugenticAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $status.html('<span class="dashicons dashicons-yes-alt" style="color: #90EE90;"></span> ' + response.data.message);
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 4px;"></span> Strip Headers/Footers from All Pages');
                    } else {
                        $status.html('<span class="dashicons dashicons-warning" style="color: #ffcccb;"></span> ' + response.data.message);
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 4px;"></span> Strip Headers/Footers from All Pages');
                    }
                },
                error: function () {
                    $status.html('<span class="dashicons dashicons-warning" style="color: #ffcccb;"></span> Error occurred');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 4px;"></span> Strip Headers/Footers from All Pages');
                }
            });
        },

        // ==================== MENU MANAGER ====================

        menuData: null,
        menuPages: [],
        menuDirty: false,
        menuLocation: 'header',

        initMenuManager: function () {
            // Only init on the menus page
            if ($('#menu-items-list').length === 0) return;

            var self = this;
            this.loadMenuItems();

            // Location selector
            $('#menu-location-select').on('change', function () {
                self.menuLocation = $(this).val();
                $('#menu-preview-title').text(self.menuLocation === 'footer' ? 'Footer Preview' : 'Header Preview');
                self.loadMenuItems();
            });

            // Add item button
            $('#menu-add-item').on('click', this.openMenuItemModal.bind(this));

            // Menu item form submit
            $('#menu-item-form').on('submit', this.submitMenuItemForm.bind(this));

            // Save menu
            $('#menu-save-btn').on('click', this.saveMenuItems.bind(this));

            // Auto-link pages
            $('#menu-auto-link-pages').on('click', this.menuAutoLinkPages.bind(this));

            // Page selector fills URL
            $('#menu-item-page-select').on('change', function () {
                var url = $(this).val();
                if (url) {
                    $('#menu-item-url').val(url);
                }
            });
        },

        loadMenuItems: function () {
            var self = this;
            $('#menu-loading').show();
            $('#menu-items-list, #menu-empty, #menu-save-bar').hide();

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_get_menu_items',
                    nonce: ugenticAdmin.nonce,
                    location: self.menuLocation
                },
                success: function (response) {
                    $('#menu-loading').hide();
                    if (response.success) {
                        self.menuData = response.data.items || [];
                        self.menuPages = response.data.pages || [];
                        self.renderMenuItems();
                        self.renderMenuPreview(response.data.header_html || '');
                        self.populatePageSelector();
                    } else {
                        var message = response.data && response.data.message ? response.data.message : 'Failed to load menu items.';
                        $('#menu-empty').show().find('p:first').text(message);
                    }
                },
                error: function () {
                    $('#menu-loading').hide();
                    $('#menu-empty').show().find('p:first').text('Failed to load menu items.');
                }
            });
        },

        renderMenuItems: function () {
            var self = this;
            var $list = $('#menu-items-list');
            $list.empty();

            if (!this.menuData || this.menuData.length === 0) {
                $list.hide();
                $('#menu-empty').show();
                $('#menu-save-bar').hide();
                return;
            }

            $('#menu-empty').hide();
            $list.show();
            $('#menu-save-bar').css('display', 'flex');

            this.menuData.forEach(function (item, index) {
                var $item = self.buildMenuItemEl(item, index, -1);
                $list.append($item);

                // Children
                if (item.children && item.children.length > 0) {
                    var $childList = $('<ul class="menu-children-list" data-parent="' + index + '"></ul>');
                    item.children.forEach(function (child, ci) {
                        var $childItem = self.buildMenuItemEl(child, ci, index);
                        $childList.append($childItem);
                    });
                    $list.append($childList);
                }
            });

            // Init sortable
            $list.sortable({
                items: '> .menu-item',
                handle: '.drag-handle',
                placeholder: 'menu-item ui-sortable-placeholder',
                tolerance: 'pointer',
                update: function () {
                    self.reorderFromDOM();
                }
            });
        },

        buildMenuItemEl: function (item, index, parentIndex) {
            var self = this;
            var childCount = (item.children && item.children.length > 0) ?
                '<span class="children-badge">' + item.children.length + ' sub</span>' : '';

            var $el = $('<li class="menu-item" data-index="' + index + '" data-parent="' + parentIndex + '"></li>');
            $el.html(
                '<span class="drag-handle dashicons dashicons-menu"></span>' +
                '<span class="item-text">' + self.escHtml(item.text) + '</span>' +
                '<span class="item-url" title="' + self.escHtml(item.url) + '">' + self.escHtml(item.url) + '</span>' +
                childCount +
                '<span class="item-actions">' +
                '<button type="button" class="button button-small menu-edit-item" title="Edit"><span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;"></span></button>' +
                (parentIndex < 0 ? '<button type="button" class="button button-small menu-add-child" title="Add sub-item"><span class="dashicons dashicons-plus" style="font-size:14px;width:14px;height:14px;"></span></button>' : '') +
                '<button type="button" class="button button-small menu-delete-item" title="Delete"><span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;color:#d63638;"></span></button>' +
                '</span>'
            );

            // Edit handler
            $el.find('.menu-edit-item').on('click', function () {
                self.inlineEditItem($el, item, index, parentIndex);
            });

            // Add child handler
            $el.find('.menu-add-child').on('click', function () {
                self.openMenuItemModal(null, index);
            });

            // Delete handler
            $el.find('.menu-delete-item').on('click', function () {
                UgenticConfirm.show({
                    title: 'Delete Menu Item',
                    message: 'Delete "' + item.text + '"?',
                    confirmText: 'Delete',
                    type: 'danger'
                }).then(function () {
                    if (parentIndex >= 0) {
                        self.menuData[parentIndex].children.splice(index, 1);
                    } else {
                        self.menuData.splice(index, 1);
                    }
                    self.menuDirty = true;
                    self.renderMenuItems();
                    self.updateSaveStatus('Unsaved changes');
                }).catch(function () { });
            });

            return $el;
        },

        inlineEditItem: function ($el, item, index, parentIndex) {
            var self = this;
            var $text = $el.find('.item-text');
            var $url = $el.find('.item-url');

            var origText = item.text;
            var origUrl = item.url;

            $text.html('<input type="text" class="edit-inline" value="' + self.escHtml(origText) + '" style="width:150px;">');
            $url.html('<input type="text" class="edit-inline" value="' + self.escHtml(origUrl) + '" style="width:180px;"> <button class="button button-small" style="padding:2px 6px;">✓</button>');

            var $saveBtn = $url.find('button');

            function saveEdit() {
                var newText = $text.find('input').val().trim();
                var newUrl = $url.find('input').val().trim();

                if (newText) {
                    var target = (parentIndex >= 0) ? self.menuData[parentIndex].children[index] : self.menuData[index];
                    target.text = newText;
                    target.url = newUrl || '#';
                    self.menuDirty = true;
                    self.renderMenuItems();
                    self.updateSaveStatus('Unsaved changes');
                }
            }

            $saveBtn.on('click', saveEdit);
            $url.find('input').on('keypress', function (e) {
                if (e.which === 13) { e.preventDefault(); saveEdit(); }
            });
            $text.find('input').on('keypress', function (e) {
                if (e.which === 13) { e.preventDefault(); saveEdit(); }
            });

            $text.find('input').focus();
        },

        reorderFromDOM: function () {
            var self = this;
            var $items = $('#menu-items-list > .menu-item');
            var newOrder = [];

            $items.each(function () {
                var idx = parseInt($(this).data('index'));
                if (self.menuData[idx]) {
                    newOrder.push(self.menuData[idx]);
                }
            });

            self.menuData = newOrder;
            self.menuDirty = true;
            self.updateSaveStatus('Unsaved changes');
        },

        saveMenuItems: function () {
            var self = this;
            var $btn = $('#menu-save-btn');
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: ugenticAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ugentic_save_menu_items',
                    nonce: ugenticAdmin.nonce,
                    items: JSON.stringify(self.menuData),
                    location: self.menuLocation
                },
                success: function (response) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:4px;"></span> Save Menu');
                    if (response.success) {
                        self.menuData = response.data.items || self.menuData;
                        self.menuDirty = false;
                        self.renderMenuItems();
                        self.renderMenuPreview(response.data.header_html || '');
                        self.updateSaveStatus('✓ Saved!');
                    } else {
                        self.updateSaveStatus('Error: ' + (response.data.message || 'Save failed'));
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:4px;"></span> Save Menu');
                    self.updateSaveStatus('Error saving menu');
                }
            });
        },

        openMenuItemModal: function (e, parentIndex) {
            var isChild = (typeof parentIndex === 'number' && parentIndex >= 0);
            $('#menu-modal-title').text(isChild ? 'Add Sub-item' : 'Add Menu Item');
            $('#menu-item-text').val('');
            $('#menu-item-url').val('');
            $('#menu-item-page-select').val('');
            $('#menu-item-modal').data('parentIndex', isChild ? parentIndex : -1);
            $('#menu-item-modal').show();
            $('#menu-item-text').focus();
        },

        submitMenuItemForm: function (e) {
            e.preventDefault();
            var text = $('#menu-item-text').val().trim();
            var url = $('#menu-item-url').val().trim() || '#';
            var storedParentIndex = $('#menu-item-modal').data('parentIndex');
            var parentIndex = typeof storedParentIndex === 'undefined' ? -1 : parseInt(storedParentIndex, 10);

            if (!text) return;

            var newItem = {
                text: text,
                url: url,
                classes: '',
                link_classes: '',
                children: []
            };

            if (parentIndex >= 0 && this.menuData[parentIndex]) {
                if (!this.menuData[parentIndex].children) {
                    this.menuData[parentIndex].children = [];
                }
                this.menuData[parentIndex].children.push(newItem);
            } else {
                if (!this.menuData) this.menuData = [];
                this.menuData.push(newItem);
            }

            this.menuDirty = true;
            this.renderMenuItems();
            this.updateSaveStatus('Unsaved changes');
            this.closeModal();
        },

        menuAutoLinkPages: function () {
            if (!this.menuPages || this.menuPages.length === 0) {
                UgenticToast.warning('No pages found. Import or create pages first.');
                return;
            }

            var self = this;
            UgenticConfirm.show({
                title: 'Auto-Link Pages',
                message: 'This will add all your pages as menu items. Continue?',
                confirmText: 'Add Pages',
                type: 'primary'
            }).then(function () {
                self._doAutoLinkPages();
            }).catch(function () { });
        },

        _doAutoLinkPages: function () {
            var self = this;

            this.menuPages.forEach(function (page) {
                // Check if already in menu
                var exists = (self.menuData || []).some(function (item) {
                    return item.url === page.url || item.text.toLowerCase() === page.title.toLowerCase();
                });
                if (!exists) {
                    if (!self.menuData) self.menuData = [];
                    self.menuData.push({
                        text: page.title,
                        url: page.url,
                        classes: '',
                        link_classes: '',
                        children: []
                    });
                }
            });

            this.menuDirty = true;
            this.renderMenuItems();
            this.updateSaveStatus('Unsaved changes — click Save');
        },

        populatePageSelector: function () {
            var $select = $('#menu-item-page-select');
            $select.find('option:not(:first)').remove();

            (this.menuPages || []).forEach(function (page) {
                $select.append('<option value="' + page.url + '">' + page.title + '</option>');
            });
        },

        renderMenuPreview: function (html) {
            var $preview = $('#menu-preview-content');
            if (html) {
                $preview.html(html);
                // Disable link clicks in preview
                $preview.find('a').on('click', function (e) { e.preventDefault(); });
            } else {
                $preview.html('<p style="padding:20px;color:#999;text-align:center;">No header content to preview.</p>');
            }
        },

        updateSaveStatus: function (msg) {
            $('#menu-save-status').text(msg);
        },

        escHtml: function (str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };

    $(document).ready(function () {
        UgenticAdmin.init();
    });

})(jQuery);
