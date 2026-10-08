(function ($) {
    'use strict';

    var $settings = $('.ugentic-settings-tabs');

    function activateSettingsTab($tab, focusPanel) {
        if (!$settings.length || !$tab.length) {
            return;
        }

        var target = $tab.attr('href');
        var $panel = target ? $settings.find(target) : $();
        if (!$panel.length) {
            return;
        }

        $settings.find('.ug-settings-nav .nav-tab')
            .removeClass('nav-tab-active')
            .attr('aria-selected', 'false')
            .attr('tabindex', '-1');
        $tab
            .addClass('nav-tab-active')
            .attr('aria-selected', 'true')
            .attr('tabindex', '0');

        $settings.find('.ugentic-tab-content').removeClass('active').attr('hidden', true);
        $panel.addClass('active').removeAttr('hidden');

        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', target);
        }

        if (focusPanel) {
            var $heading = $panel.find('h2, h3').first();
            if ($heading.length) {
                $heading.attr('tabindex', '-1').trigger('focus');
            }
        }
    }

    function initSettingsNavigation() {
        if (!$settings.length) {
            return;
        }

        var $tabs = $settings.find('.ug-settings-nav .nav-tab');
        $tabs.attr({
            role: 'tab',
            'aria-selected': 'false',
            tabindex: '-1'
        }).each(function () {
            var target = $(this).attr('href');
            if (target) {
                $(this).attr('aria-controls', target.slice(1));
                $settings.find(target).attr({
                    role: 'tabpanel',
                    'aria-labelledby': $(this).attr('id') || ''
                });
            }
        });

        $settings.find('.ug-settings-nav').attr('role', 'tablist');

        $settings.on('click', '.ug-settings-nav .nav-tab', function (event) {
            event.preventDefault();
            activateSettingsTab($(this), false);
        });

        $settings.on('keydown', '.ug-settings-nav .nav-tab', function (event) {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();
            var visibleTabs = $tabs.filter(':visible').toArray();
            var current = visibleTabs.indexOf(this);
            var next = current;

            if (event.key === 'ArrowDown') next = Math.min(visibleTabs.length - 1, current + 1);
            if (event.key === 'ArrowUp') next = Math.max(0, current - 1);
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = visibleTabs.length - 1;

            $(visibleTabs[next]).trigger('focus');
        });

        var moduleContext = $settings.data('module-context') || '';
        var legacyModelsHash = window.location.hash === '#tab-models';
        var hashTab = window.location.hash
            ? $tabs.filter('[href="' + window.location.hash.replace(/"/g, '\\"') + '"]')
            : $();
        if (legacyModelsHash) {
            hashTab = $tabs.filter('[href="#tab-api-keys"]');
        } else if (moduleContext === 'mcp_conversion') {
            hashTab = $tabs.filter('[href="#tab-mcp"]');
        }
        activateSettingsTab(hashTab.length ? hashTab : $tabs.first(), false);

        if (legacyModelsHash) {
            window.setTimeout(function () {
                $('[data-ai-settings-view="models"]').trigger('click');
            }, 0);
        }

        $('#ug-settings-search').on('input', function () {
            var query = String($(this).val() || '').trim().toLowerCase();
            var visibleCount = 0;

            $tabs.each(function () {
                var $tab = $(this);
                var haystack = ($tab.text() + ' ' + ($tab.data('keywords') || '')).toLowerCase();
                var matches = !query || haystack.indexOf(query) !== -1;
                $tab.toggle(matches);
                if (matches) visibleCount += 1;
            });

            $('.ug-settings-nav__section').each(function () {
                var $section = $(this);
                var hasVisible = $section.nextUntil('.ug-settings-nav__section', '.nav-tab').filter(':visible').length > 0;
                $section.toggle(hasVisible);
            });

            $('#ug-settings-search-empty').toggle(visibleCount === 0);
        });
    }

    function initAISettingsViews() {
        var $buttons = $('[data-ai-settings-view]');
        var $panes = $('[data-ai-settings-pane]');
        if (!$buttons.length || !$panes.length) return;

        function show(view) {
            $buttons.each(function () {
                var active = $(this).data('ai-settings-view') === view;
                $(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
            });
            $panes.each(function () {
                var active = $(this).data('ai-settings-pane') === view;
                $(this).toggleClass('is-active', active).prop('hidden', !active);
            });
        }

        $buttons.on('click', function () {
            show($(this).data('ai-settings-view'));
        });
        show('connections');
    }

    function initSettingsSectionPickers() {
        var containers = [];
        $('.ugentic-tab-content').each(function () {
            if (this.id !== 'tab-api-keys') containers.push(this);
        });
        $('[data-ai-settings-pane]').each(function () { containers.push(this); });

        containers.forEach(function (container, containerIndex) {
            var cards = Array.prototype.filter.call(container.children, function (child) {
                return child.classList && child.classList.contains('ugentic-card');
            });
            if (cards.length < 2) return;

            var picker = document.createElement('div');
            picker.className = 'ug-settings-section-picker';
            var label = document.createElement('label');
            var labelText = document.createElement('span');
            var select = document.createElement('select');
            var key = container.id || container.getAttribute('data-ai-settings-pane') || ('settings-' + containerIndex);
            var savedIndex = Number(window.sessionStorage.getItem('tahmir-settings-section-' + key) || 0);
            if (savedIndex < 0 || savedIndex >= cards.length) savedIndex = 0;

            labelText.textContent = 'Show section';
            cards.forEach(function (card, cardIndex) {
                var heading = card.querySelector('h2, h3');
                var option = document.createElement('option');
                option.value = String(cardIndex);
                option.textContent = heading ? heading.textContent.trim() : ('Section ' + (cardIndex + 1));
                select.appendChild(option);
            });
            select.value = String(savedIndex);
            label.appendChild(labelText);
            label.appendChild(select);
            picker.appendChild(label);

            var helper = document.createElement('small');
            helper.textContent = 'One focused section at a time. Your other settings remain unchanged.';
            picker.appendChild(helper);
            container.insertBefore(picker, cards[0]);

            function showCard(index) {
                cards.forEach(function (card, cardIndex) {
                    var active = cardIndex === index;
                    card.classList.toggle('is-settings-card-active', active);
                    card.hidden = !active;
                });
                window.sessionStorage.setItem('tahmir-settings-section-' + key, String(index));
            }

            select.addEventListener('change', function () {
                showCard(Number(select.value));
            });
            showCard(savedIndex);
        });
    }

    function initSimplifiedSEOSettings() {
        var $robotsPreset = $('[data-seo-robots-preset]');
        var $robotsInput = $('#seo_aiseo_default_robots');
        var $customRobots = $('[data-seo-robots-custom]');
        if ($robotsPreset.length && $robotsInput.length) {
            $robotsPreset.on('change', function () {
                var preset = $(this).val();
                if (preset === 'recommended') {
                    $robotsInput.val($robotsInput.data('recommended-value'));
                    $customRobots.prop('hidden', true);
                } else if (preset === 'private') {
                    $robotsInput.val('noindex, nofollow');
                    $customRobots.prop('hidden', true);
                } else {
                    $customRobots.prop('hidden', false);
                    window.setTimeout(function () { $robotsInput.trigger('focus'); }, 0);
                }
                $robotsInput.trigger('change');
            });
        }

        $('[data-settings-media-picker]').on('click', function () {
            if (!window.wp || !wp.media) return;
            var $button = $(this);
            var $target = $($button.data('media-target'));
            var $preview = $($button.data('media-preview'));
            var frame = wp.media({
                title: 'Choose an image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $target.val(attachment.url || '').trigger('input').trigger('change');
                $preview.attr('src', attachment.url || '').prop('hidden', !attachment.url);
            });
            frame.open();
        });

        $('#seo_aiseo_default_social_image, #seo_aiseo_org_logo').on('input change', function () {
            var previewSelector = this.id === 'seo_aiseo_org_logo' ? '#seo-org-logo-preview' : '#seo-social-image-preview';
            var value = String($(this).val() || '').trim();
            $(previewSelector).attr('src', value).prop('hidden', !value);
        });
    }

    function runMaintenanceAction(config) {
        if (typeof window.UgenticConfirm === 'undefined') {
            return;
        }

        UgenticConfirm.show({
            title: config.title,
            message: config.message,
            confirmText: config.confirmText,
            type: config.type,
            typed: config.typed
        }).then(function () {
            var $button = $(config.selector);
            var original = $button.html();
            $button.prop('disabled', true).addClass('is-busy');

            $.post(ugenticAdmin.ajaxUrl, {
                action: config.action,
                nonce: ugenticAdmin.nonce
            }).done(function (response) {
                if (response.success) {
                    UgenticToast.success(response.data.message || config.success);
                    if (config.reload) {
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 650);
                    }
                } else {
                    UgenticToast.error(response.data && response.data.message ? response.data.message : config.error);
                }
            }).fail(function () {
                UgenticToast.error(config.error);
            }).always(function () {
                $button.prop('disabled', false).removeClass('is-busy').html(original);
            });
        }).catch(function () {});
    }

    function initMaintenanceActions() {
        $('#ug-maintenance-cleanup').on('click', function () {
            runMaintenanceAction({
                selector: '#ug-maintenance-cleanup',
                title: 'Clean orphaned page records',
                message: 'Remove Tahmir AI page records whose matching WordPress page no longer exists?',
                confirmText: 'Run cleanup',
                type: 'primary',
                action: 'ugentic_cleanup_orphans',
                success: 'Orphaned page records cleaned up.',
                error: 'Could not clean orphaned page records.',
                reload: false
            });
        });

        $('#ug-maintenance-delete-pages').on('click', function () {
            runMaintenanceAction({
                selector: '#ug-maintenance-delete-pages',
                title: 'Delete all Tahmir AI pages',
                message: 'This permanently deletes every Tahmir AI page and its associated WordPress page. Forms and global assets are not removed.',
                confirmText: 'Delete all pages',
                type: 'danger',
                typed: 'DELETE',
                action: 'ugentic_delete_all_pages',
                success: 'All Tahmir AI pages were deleted.',
                error: 'Could not delete all pages.',
                reload: true
            });
        });

        $('#ug-maintenance-reset').on('click', function () {
            runMaintenanceAction({
                selector: '#ug-maintenance-reset',
                title: 'Reset the Tahmir AI database',
                message: 'This permanently removes Tahmir AI pages, CSS and JavaScript assets, headers, footers, and history. This cannot be undone.',
                confirmText: 'Reset everything',
                type: 'danger',
                typed: 'RESET',
                action: 'ugentic_reset_database',
                success: 'The Tahmir AI database was reset.',
                error: 'Could not reset the Tahmir AI database.',
                reload: true
            });
        });
    }

    function initSettingsSaveState() {
        var $form = $('#ugentic-settings-form');
        if (!$form.length) {
            return;
        }

        $form.on('input change', ':input', function () {
            if (!$(this).closest('#tab-license, #tab-maintenance').length) {
                $form.addClass('has-unsaved-changes');
                $('#ug-settings-save-state').text('Unsaved changes');
            }
        });

        $form.on('submit', function () {
            $form.addClass('is-saving');
            $('#ug-settings-save-state').text('Saving…');
        });

        var result = document.getElementById('save-result');
        if (result && window.MutationObserver) {
            new MutationObserver(function () {
                var text = result.textContent || '';
                if (/saved/i.test(text)) {
                    $form.removeClass('has-unsaved-changes is-saving');
                    $('#ug-settings-save-state').text('All changes saved');
                } else if (/error|failed/i.test(text)) {
                    $form.removeClass('is-saving');
                    $('#ug-settings-save-state').text('Save failed');
                }
            }).observe(result, { childList: true, subtree: true, characterData: true });
        }
    }

    $(function () {
        initSettingsNavigation();
        initAISettingsViews();
        initSettingsSectionPickers();
        initSimplifiedSEOSettings();
        initMaintenanceActions();
        initSettingsSaveState();
    });
})(jQuery);
