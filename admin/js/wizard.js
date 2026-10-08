/**
 * Ugentic Website Wizard – SPA Application
 * Handles all 8 wizard steps with conversational UI, live preview, and AI integration.
 *
 * @package Ugentic_Builder
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    const W = window.ugenticWizard || {};
    const AJAX = W.ajaxUrl;
    const NONCE = W.nonce;

    // ===== App State =====
    const state = {
        projectId: null,
        project: null,
        currentStep: 'discovery',
        pages: [],
        currentPageIdx: 0,
        activeContentTab: 0,
        selectedDesignStyle: 'minimal',
        selectedIconStyle: 'combination',
        selectedMood: 'minimal',
        selectedShape: 'geometric',
        logoHistory: [],
        currentLogoData: null,
        complianceAnswers: {},
        studioZoom: 0.52,
    };

    // Module-level canvas function references (assigned from nested initStart scope)
    var openCanvas, generatePageAtIndex, hydrateIframes, refreshSlot;
    var mockupEditorState = {
        selectedId: '',
        selectedLabel: '',
        dirty: false
    };

    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================

    function post(action, data) {
        return $.ajax({
            url: AJAX,
            type: 'POST',
            data: Object.assign({ action: action, nonce: NONCE }, data),
        });
    }

    function toast(msg, type) {
        type = type || 'info';
        const el = $('<div class="ugw-toast ugw-toast--' + type + '">' + msg + '</div>');
        $('#ugw-toasts').append(el);
        setTimeout(function () {
            el.animate({ opacity: 0, transform: 'translateY(10px)' }, 300, function () {
                el.remove();
            });
        }, 4000);
    }

    function addChatBubble(container, text, role) {
        var cls = role === 'user' ? 'ugw-chat-bubble--user' : 'ugw-chat-bubble--assistant';
        var html = '<div class="ugw-chat-bubble ' + cls + '">' + escHtml(text) + '</div>';
        $(container).append(html);
        container.scrollTop = container.scrollHeight;
    }

    function showTyping(container) {
        var html = '<div class="ugw-typing" id="ugw-typing"><div class="ugw-typing-dot"></div><div class="ugw-typing-dot"></div><div class="ugw-typing-dot"></div></div>';
        $(container).append(html);
        container.scrollTop = container.scrollHeight;
    }

    function hideTyping() {
        $('#ugw-typing').remove();
    }

    function escHtml(str) {
        if (typeof str !== 'string') return str;
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function escAttr(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function showClonePermissionModal(url, modeLabel, details, aiAssisted) {
        modeLabel = modeLabel || 'Website';
        details = details || '';
        aiAssisted = !!aiAssisted;
        var deferred = $.Deferred();
        $('#ugw-clone-permission-modal').remove();

        var html = '' +
            '<div id="ugw-clone-permission-modal" style="position:fixed;inset:0;background:rgba(15,23,42,0.58);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;">' +
            '  <div style="width:min(520px,100%);background:#fff;border-radius:12px;box-shadow:0 24px 70px rgba(15,23,42,0.25);padding:24px;color:#0f172a;">' +
            '    <h3 style="margin:0 0 8px;font-size:20px;font-weight:800;">Clone permission required</h3>' +
            '    <p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#475569;">Are you sure you have permission to clone this website?</p>' +
            '    <div style="font-size:13px;line-height:1.5;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:14px;word-break:break-word;">' + escHtml(url) + '</div>' +
            (details ? '    <div style="font-size:12px;line-height:1.45;color:#475569;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:8px;padding:10px;margin-bottom:14px;">' + escHtml(details) + '</div>' : '') +
            (aiAssisted ? '    <label style="display:flex;align-items:flex-start;gap:9px;font-size:12px;line-height:1.45;color:#475569;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px;margin-bottom:14px;"><input type="checkbox" id="ugw-clone-ai-consent" style="margin-top:2px;"><span>I understand that route paths and privacy-filtered form structure (labels, field types, choices, and step headings) may be sent to my selected AI provider. Entered/default values, hidden tokens, unrelated page copy, scripts, and styles stay local.</span></label>' : '') +
            '    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Type YES to confirm</label>' +
            '    <input type="text" id="ugw-clone-confirm-input" class="ugw-input" autocomplete="off" style="width:100%;margin-bottom:16px;" placeholder="YES">' +
            '    <div style="display:flex;gap:10px;justify-content:flex-end;">' +
            '      <button type="button" class="ugw-btn ugw-btn--ghost" id="ugw-clone-confirm-cancel">Cancel</button>' +
            '      <button type="button" class="ugw-btn ugw-btn--primary" id="ugw-clone-confirm-yes" disabled>Clone ' + escHtml(modeLabel) + '</button>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        $('body').append(html);
        var $modal = $('#ugw-clone-permission-modal');
        var $input = $('#ugw-clone-confirm-input').focus();
        var $confirm = $('#ugw-clone-confirm-yes');
        var $aiConsent = $('#ugw-clone-ai-consent');

        function consentReady() {
            return !aiAssisted || $aiConsent.is(':checked');
        }

        function updateConfirmState() {
            $confirm.prop('disabled', $input.val().trim() !== 'YES' || !consentReady());
        }

        $input.on('input', function () {
            updateConfirmState();
        });
        $aiConsent.on('change', updateConfirmState);
        $input.on('keydown', function (e) {
            if (e.key === 'Enter' && $input.val().trim() === 'YES' && consentReady()) {
                $modal.remove();
                deferred.resolve({ aiDataConsent: aiAssisted });
            }
            if (e.key === 'Escape') {
                $modal.remove();
                deferred.reject();
            }
        });
        $('#ugw-clone-confirm-cancel').on('click', function () {
            $modal.remove();
            deferred.reject();
        });
        $confirm.on('click', function () {
            if ($input.val().trim() !== 'YES' || !consentReady()) return;
            $modal.remove();
            deferred.resolve({ aiDataConsent: aiAssisted });
        });

        return deferred.promise();
    }

    function renderFaithfulCloneResult(data) {
        var pages = data.pages_imported || [];
        var warnings = data.warnings || [];
        var skipped = data.pages_skipped || [];
        var additional = data.additional_pages || [];
        var aiReport = data.ai_report || {};
        var pending = pages.filter(function (page) { return page.client_rendered_shell && !page.client_shell_materialized; }).length;
        var html = '<div style="font-weight:700;margin-bottom:10px;">Imported ' + pages.length + ' page' + (pages.length === 1 ? '' : 's') + (pending ? '; ' + pending + ' need rendering review.' : ' as editable content.') + '</div>';
        html += '<div style="font-size:12px;color:var(--ugw-text-secondary,#64748b);margin-bottom:10px;">Found ' + (data.pages_found || pages.length) + ' page(s), skipped ' + skipped.length + ', downloaded ' + (data.assets_downloaded || 0) + ' asset(s), failed ' + (data.assets_failed || 0) + ' asset(s), and updated ' + (data.links_replaced || 0) + ' internal link(s).</div>';
        if (data.ai_assisted) {
            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:7px;margin:0 0 12px;">' +
                '<span style="padding:8px 9px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:11px;font-weight:700;">' + (aiReport.headers_separated || 0) + ' header variant(s)</span>' +
                '<span style="padding:8px 9px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:11px;font-weight:700;">' + (aiReport.footers_separated || 0) + ' footer variant(s)</span>' +
                '<span style="padding:8px 9px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:11px;font-weight:700;">' + ((aiReport.blog_archives_created || 0) + (aiReport.blog_posts_created || 0)) + ' native blog route(s)</span>' +
                '<span style="padding:8px 9px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:11px;font-weight:700;">' + (aiReport.complex_forms_preserved || 0) + ' complex form(s)</span>' +
                '<span style="padding:8px 9px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:11px;font-weight:700;">' + (aiReport.ai_forms_planned || 0) + ' AI-planned form flow(s)</span>' +
                '</div>';
        }

        if (pages.length) {
            html += '<div style="display:grid;gap:8px;margin-bottom:10px;">';
            pages.forEach(function (page) {
                html += '<div style="display:flex;gap:8px;align-items:center;justify-content:space-between;background:#fff;border:1px solid var(--ugw-border,#e2e8f0);border-radius:8px;padding:10px;">' +
                    '<div style="min-width:0;">' +
                    '<div style="font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(page.title || 'Imported Page') + '</div>' +
                    '<div style="font-size:11px;color:var(--ugw-text-secondary,#64748b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(page.source_url || '') + '</div>' +
                    (page.capture_error ? '<div style="font-size:12px;color:#92400e;">' + escHtml(page.capture_error) + '</div>' : '') +
                    '</div>' +
                    '<div style="display:flex;gap:6px;flex-shrink:0;">' +
                    '<a class="ugw-btn ugw-btn--ghost ugw-btn--sm" href="' + escAttr(page.view_url || '#') + '" target="_blank" rel="noopener">View</a>' +
                    '<a class="ugw-btn ugw-btn--primary ugw-btn--sm" href="' + 'https://tahmir.com/upgrade' + '">Visual Editor · Pro</a>' +
                    '</div>' +
                    '</div>';
            });
            html += '</div>';
        }

        if (additional.length) {
            html += '<div class="ugw-card" style="padding:14px;margin:12px 0;"><strong>' + additional.length + ' additional public pages found after rendering</strong>' +
                '<p>These links were not present in the initial HTML. Run discovery again to select them for import.</p>' +
                '<ul>' + additional.map(function (page) { return '<li>' + escHtml(page.url) + '</li>'; }).join('') + '</ul>' +
                '<button type="button" class="ugw-btn ugw-btn--primary ugw-clone-discover-more">Discover additional pages</button></div>';
        }
        if (warnings.length) {
            html += '<details open style="font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px;">' +
                '<summary style="cursor:pointer;font-weight:700;">Import warnings (' + warnings.length + ')</summary>' +
                '<ul style="margin:8px 0 0 18px;padding:0;">';
            warnings.slice(0, 12).forEach(function (warning) {
                html += '<li>' + escHtml(warning) + '</li>';
            });
            if (warnings.length > 12) {
                html += '<li>' + (warnings.length - 12) + ' more warning(s)</li>';
            }
            html += '</ul></details>';
        }

        if (skipped.length) {
            html += '<details style="font-size:12px;color:#7f1d1d;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px;margin-top:8px;">' +
                '<summary style="cursor:pointer;font-weight:700;">Skipped pages (' + skipped.length + ')</summary>' +
                '<ul style="margin:8px 0 0 18px;padding:0;">';
            skipped.slice(0, 12).forEach(function (page) {
                html += '<li>' + escHtml(page.url || '') + (page.reason ? ': ' + escHtml(page.reason) : '') + '</li>';
            });
            if (skipped.length > 12) {
                html += '<li>' + (skipped.length - 12) + ' more skipped page(s)</li>';
            }
            html += '</ul></details>';
        }

        return html;
    }

    function ajaxErrorMessage(xhr, fallback) {
        var json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        if (json && json.data && json.data.message) {
            return json.data.message;
        }
        if (json && json.message) {
            return json.message;
        }
        if (xhr && xhr.status === 504) {
            return 'The server timed out before the clone finished. Try fewer pages or use Clone Page for this source.';
        }
        if (xhr && xhr.status === 505) {
            return 'The source server/proxy returned HTTP 505. The clone will retry temporary 5xx errors; if it still fails, try again or use Clone Page for this source.';
        }
        if (xhr && xhr.status >= 500) {
            return 'The server returned HTTP ' + xhr.status + '. This is usually temporary; the clone retries backend fetches automatically, but the admin request itself failed.';
        }
        if (xhr && xhr.status) {
            return fallback + ' (HTTP ' + xhr.status + ')';
        }
        return fallback;
    }

    function renderCloneProgress(job) {
        job = job || {};
        var progress = Math.max(5, Math.min(100, parseInt(job.progress || 15, 10)));
        var elapsed = job.started_at ? Math.max(1, Math.round(Date.now() / 1000 - parseInt(job.started_at, 10))) : 0;
        var message = job.message || 'Clone is running in the background...';
        var elapsedHtml = elapsed ? '<span style="color:var(--ugw-text-secondary,#64748b);">Elapsed: ' + elapsed + 's</span>' : '';
        var phase = job.phase || 'running';
        var phases = [
            ['fetch_source', 'Fetch source'],
            ['network_retry', 'Retry fetch'],
            ['ssl_fallback', 'SSL fallback'],
            ['client_app_shell', 'JS app shell'],
            ['discover_pages', 'Discover pages'],
            ['ai_plan', 'AI structure'],
            ['prepare_assets', 'Prepare assets'],
            ['import_page', 'Import pages'],
            ['download_assets', 'Download assets'],
            ['rewrite_links', 'Rewrite links'],
            ['detect_menus', 'Detect menus'],
            ['complete', 'Complete']
        ];
        var phaseOrder = {};
        phases.forEach(function (item, idx) { phaseOrder[item[0]] = idx; });
        var currentIdx = phaseOrder.hasOwnProperty(phase) ? phaseOrder[phase] : Math.max(0, Math.floor(progress / 15));
        var stageHtml = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:6px;margin:10px 0;">';
        phases.forEach(function (item, idx) {
            var done = progress >= 100 || idx < currentIdx;
            var active = idx === currentIdx && progress < 100;
            var bg = done ? '#dcfce7' : active ? '#e0f2fe' : '#f8fafc';
            var color = done ? '#166534' : active ? '#075985' : '#64748b';
            var mark = done ? '✓' : active ? '•' : '○';
            stageHtml += '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:6px 8px;background:' + bg + ';color:' + color + ';font-size:11px;font-weight:700;">' + mark + ' ' + escHtml(item[1]) + '</div>';
        });
        stageHtml += '</div>';
        var detailParts = [];
        if (job.pages_total) {
            var currentPage = typeof job.page_index === 'number' ? Math.min(job.pages_total, job.page_index + 1) : 0;
            detailParts.push('Pages: ' + (currentPage || '-') + ' / ' + job.pages_total);
        }
        if (job.assets_downloaded) {
            detailParts.push('Assets processed: ' + job.assets_downloaded);
        }
        if (job.retry_attempt && job.retry_attempts_total) {
            detailParts.push('Retry: ' + job.retry_attempt + ' / ' + job.retry_attempts_total);
        }
        if (job.retry_reason) {
            detailParts.push('Last issue: ' + job.retry_reason);
        }
        var details = detailParts.length
            ? '<div style="font-size:12px;color:var(--ugw-text-secondary,#64748b);margin-top:6px;">' + escHtml(detailParts.join(' · ')) + '</div>'
            : '';

        return '' +
            '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">' +
            '  <div class="ugw-qg-spinner" style="width:20px;height:20px;border-width:2px;margin:0;"></div>' +
            '  <span>' + escHtml(message) + '</span>' +
            '</div>' +
            '<div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-bottom:8px;">' +
            '  <div style="height:100%;width:' + progress + '%;background:linear-gradient(135deg,#22c55e 0%,#0ea5e9 100%);transition:width .25s ease;"></div>' +
            '</div>' +
            '<div style="display:flex;justify-content:space-between;font-size:12px;color:var(--ugw-text-secondary,#64748b);">' +
            '  <span>' + progress + '%</span>' + elapsedHtml +
            '</div>' + details + stageHtml;
    }

    function setCloneButtonLoading($btn, label) {
        if (!$btn.data('ugw-original-html')) {
            $btn.data('ugw-original-html', $btn.html());
        }
        $btn.addClass('ugw-clone-loading').prop('disabled', true).text(label);
    }

    function restoreCloneButton($btn, isSinglePage) {
        var original = $btn.data('ugw-original-html');
        $btn.removeClass('ugw-clone-loading').prop('disabled', false).html(original || (isSinglePage ? 'Clone Page' : 'Clone Website'));
    }

    /**
     * Turn client-rendered app shells into normal Tahmir HTML immediately after
     * import. Each draft preview runs its verified local bundle, then the server
     * removes all active content before saving the rendered DOM.
     */
    function materializeClientShellPages(data, $status) {
        data = data || {};
        var pages = (data.pages_imported || []).filter(function (page) {
            return page && page.client_rendered_shell && page.view_url && page.wp_page_id && page.ugentic_id;
        });
        var completed = $.Deferred();
        var failures = [];
        var index = 0;

        if (!pages.length) {
            completed.resolve(data);
            return completed.promise();
        }

        function finish() {
            var importedUrls = (data.pages_imported || []).map(function (page) { return String(page.source_url || '').replace(/\/$/, ''); });
            var seenUrls = {};
            data.additional_pages = (data.rendered_discovered_pages || []).filter(function (page) {
                var url = String(page.url || '').replace(/\/$/, '');
                if (!url || seenUrls[url] || importedUrls.indexOf(url) !== -1) return false;
                seenUrls[url] = true;
                return true;
            });
            data.capture_failures = failures.length;
            if (failures.length) {
                data.warnings = data.warnings || [];
                data.warnings.push(
                    failures.length + ' client-rendered page' + (failures.length === 1 ? '' : 's') +
                    ' could not be finalized as editable content. Review the page details below.'
                );
                failures.forEach(function (failure) { failure.page.capture_error = failure.message; });
            }
            completed.resolve(data);
        }

        function runNext() {
            if (index >= pages.length) {
                finish();
                return;
            }

            var page = pages[index++];
            var label = page.title || ('Page ' + index);
            $status.html(renderCloneProgress({
                phase: 'complete',
                progress: 96 + Math.round((index / pages.length) * 3),
                started_at: Math.round(Date.now() / 1000),
                message: 'Preparing editable layout: ' + label,
                page_index: index - 1,
                pages_total: pages.length
            }));

            var iframe = document.createElement('iframe');
            var settled = false;
            var stableChecks = 0;
            var lastLength = 0;
            var pollTimer = null;
            var timeoutTimer = null;
            var sweepStarted = false;
            var sweepFinished = false;
            var sweepFinishedAt = 0;
            var lastState = null;
            var sweepTimers = [];
            iframe.title = 'Tahmir client page renderer';
            iframe.setAttribute('aria-hidden', 'true');
            iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups');
            iframe.setAttribute('referrerpolicy', 'no-referrer');
            // Keep the renderer geometrically inside the viewport. Lovable uses
            // IntersectionObserver for entrance states; an off-canvas iframe
            // leaves the entire saved page at opacity:0.
            iframe.style.cssText = 'position:fixed;inset:0;width:1440px;height:1100px;opacity:0;pointer-events:none;border:0;z-index:-2147483647;';

            function cleanup() {
                if (pollTimer) window.clearInterval(pollTimer);
                if (timeoutTimer) window.clearTimeout(timeoutTimer);
                sweepTimers.forEach(function (timer) { window.clearTimeout(timer); });
                sweepTimers = [];
                if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
            }

            function after(delay, callback) {
                var timer = window.setTimeout(callback, delay);
                sweepTimers.push(timer);
                return timer;
            }

            function fail(message) {
                if (settled) return;
                settled = true;
                failures.push({ page: page, message: message || 'Snapshot timed out' });
                cleanup();
                runNext();
            }

            function sweepRenderedPage(doc) {
                if (sweepStarted || settled) return;
                sweepStarted = true;
                var win = iframe.contentWindow;
                var htmlEl = doc.documentElement;
                var body = doc.body;
                var priorScrollBehavior = htmlEl.style.scrollBehavior;
                var hadHtmlStyle = htmlEl.hasAttribute('style');
                htmlEl.style.scrollBehavior = 'auto';

                var documentHeight = Math.max(
                    htmlEl.scrollHeight || 0,
                    body ? body.scrollHeight : 0,
                    htmlEl.offsetHeight || 0,
                    body ? body.offsetHeight : 0
                );
                var viewportHeight = Math.max(600, win.innerHeight || 1100);
                var maxScroll = Math.max(0, documentHeight - viewportHeight);
                var steps = Math.max(1, Math.min(12, Math.ceil(documentHeight / Math.max(700, viewportHeight * 0.7))));
                var positions = [];
                for (var step = 0; step <= steps; step++) {
                    positions.push(Math.round(maxScroll * (step / steps)));
                }
                var positionIndex = 0;

                function visitNextViewport() {
                    if (settled) return;
                    if (positionIndex < positions.length) {
                        win.scrollTo(0, positions[positionIndex++]);
                        after(220, visitNextViewport);
                        return;
                    }
                    win.scrollTo(0, 0);
                    after(1300, function () {
                        htmlEl.style.scrollBehavior = priorScrollBehavior;
                        if (!hadHtmlStyle && !htmlEl.getAttribute('style')) {
                            htmlEl.removeAttribute('style');
                        }
                        stableChecks = 0;
                        lastLength = 0;
                        sweepFinished = true;
                        sweepFinishedAt = Date.now();
                    });
                }

                visitNextViewport();
            }

            function finalizeEntranceAnimations(doc) {
                Array.prototype.slice.call(doc.querySelectorAll('[style]')).forEach(function (element) {
                    var style = element.style;
                    var properties = String(style.transitionProperty || '').toLowerCase();
                    var willChange = String(style.willChange || '').toLowerCase();
                    if (!properties || !willChange || !/(?:opacity|transform)/.test(properties) || !/(?:opacity|transform)/.test(willChange)) {
                        return;
                    }
                    if (properties.indexOf('opacity') !== -1 && parseFloat(style.opacity) === 0) {
                        style.opacity = '1';
                    }
                    if (properties.indexOf('transform') !== -1 && style.transform) {
                        style.transform = style.transform
                            .replace(/translate3d\(\s*[^,]+,\s*[^,]+,\s*[^)]+\)/gi, 'translate3d(0px, 0px, 0px)')
                            .replace(/translate([XY])\(\s*[^)]+\)/gi, 'translate$1(0px)')
                            .replace(/translate\(\s*[^,]+,\s*[^)]+\)/gi, 'translate(0px, 0px)');
                    }
                });
            }

            function saveSnapshot(doc) {
                if (settled) return;
                finalizeEntranceAnimations(doc);
                var html = window.TahmirCloneCapture.serialize(doc);
                if (html.length > 8 * 1024 * 1024) {
                    fail('Rendered snapshot was too large');
                    return;
                }
                settled = true;

                $.ajax({
                    url: AJAX,
                    type: 'POST',
                    timeout: 240000,
                    data: {
                        action: 'ugentic_wizard_materialize_client_shell',
                        nonce: NONCE,
                        wp_page_id: page.wp_page_id,
                        ugentic_id: page.ugentic_id,
                        html: html,
                        snapshot_baseline: page.snapshot_baseline || ''
                    }
                }).done(function (res) {
                    if (res && res.success) {
                        page.client_shell_materialized = true;
                        data.rendered_discovered_pages = (data.rendered_discovered_pages || []).concat(res.data.discovered_pages || []);
                        page.snapshot_assets_downloaded = parseInt(res.data.assets_downloaded || 0, 10);
                        data.assets_downloaded = parseInt(data.assets_downloaded || 0, 10) + page.snapshot_assets_downloaded;
                        data.assets_failed = parseInt(data.assets_failed || 0, 10) + parseInt(res.data.assets_failed || 0, 10);
                        if (res.data.ai_assisted) {
                            data.ai_report = data.ai_report || {};
                            page.global_header_id = parseInt(res.data.global_header_id || 0, 10);
                            page.global_footer_id = parseInt(res.data.global_footer_id || 0, 10);
                            page.form_audit = res.data.form_audit || {};
                            if (page.global_header_id) data.ai_report.headers_separated = parseInt(data.ai_report.headers_separated || 0, 10) + 1;
                            if (page.global_footer_id) data.ai_report.footers_separated = parseInt(data.ai_report.footers_separated || 0, 10) + 1;
                            data.ai_report.complex_forms_preserved = parseInt(data.ai_report.complex_forms_preserved || 0, 10) + parseInt(page.form_audit.complex_forms || 0, 10);
                            data.ai_report.multi_step_forms_preserved = parseInt(data.ai_report.multi_step_forms_preserved || 0, 10) + parseInt(page.form_audit.multi_step_forms || 0, 10);
                            data.ai_report.ai_forms_planned = parseInt(data.ai_report.ai_forms_planned || 0, 10) + parseInt(page.form_audit.ai_forms_planned || 0, 10);
                        }
                        if (lastState && (lastState.failed || lastState.pending || !lastState.fontsReady)) {
                            data.warnings = data.warnings || [];
                            data.warnings.push(label + ': some source images or fonts did not finish loading. Check the imported preview.');
                        }
                        if (res.data.warnings && res.data.warnings.length) {
                            data.warnings = (data.warnings || []).concat(res.data.warnings);
                        }
                    } else {
                        failures.push({ page: page, message: (res && res.data && res.data.message) || 'Snapshot was rejected' });
                    }
                }).fail(function (xhr) {
                    failures.push({ page: page, message: ajaxErrorMessage(xhr, 'Could not save editable snapshot') });
                }).always(function () {
                    cleanup();
                    runNext();
                });
            }

            iframe.onload = function () {
                if (pollTimer) window.clearInterval(pollTimer);
                pollTimer = window.setInterval(function () {
                    try {
                        var doc = iframe.contentDocument;
                        if (!doc) return;
                        lastState = window.TahmirCloneCapture.inspect(doc);
                        if (!lastState.substantive || lastState.error) {
                            stableChecks = 0;
                            return;
                        }
                        if (!sweepStarted) {
                            sweepRenderedPage(doc);
                            return;
                        }
                        if (!sweepFinished) return;
                        stableChecks = lastState.fingerprint === lastLength ? stableChecks + 1 : 0;
                        lastLength = lastState.fingerprint;
                        if (window.TahmirCloneCapture.ready(lastState, stableChecks, Date.now() - sweepFinishedAt)) {
                            saveSnapshot(doc);
                        }
                    } catch (error) {
                        fail(error && error.message ? error.message : 'Could not inspect rendered page');
                    }
                }, 300);
            };

            timeoutTimer = window.setTimeout(function () {
                fail(lastState && lastState.error
                    ? 'The source application rendered an error: ' + lastState.error
                    : 'The application did not produce a complete layout within 90 seconds. Its runtime or data service may not work away from the source host.');
            }, 90000);
            iframe.src = page.view_url;
            document.body.appendChild(iframe);
        }

        runNext();
        return completed.promise();
    }

    window.TahmirMaterializeImportedPages = materializeClientShellPages;

    function pollFaithfulCloneJob(jobId, $status, $btn, isSinglePage) {
        var statusFailures = 0;
        function finishButton() {
            restoreCloneButton($btn, isSinglePage);
        }

        function poll() {
            post('ugentic_wizard_clone_status', { job_id: jobId })
                .done(function (res) {
                    statusFailures = 0;
                    var job = res && res.data ? res.data : {};
                    if (!res.success) {
                        $status.html('<div style="color:var(--ugw-error);">❌ ' + escHtml(job.message || 'Clone status could not be loaded.') + '</div>');
                        finishButton();
                        return;
                    }

                    if (job.status === 'complete' && job.result) {
                        materializeClientShellPages(job.result, $status).done(function (result) {
                            $status.html(renderFaithfulCloneResult(result));
                            toast(result.capture_failures ? 'Import finished with pages that need review.' : 'Import complete.', result.capture_failures ? 'warning' : 'success');
                            finishButton();
                        });
                        return;
                    }

                    if (job.status === 'failed') {
                        var warnings = job.warnings || (job.result && job.result.warnings ? job.result.warnings : []);
                        var warningHtml = warnings.length
                            ? '<ul style="margin:8px 0 0 18px;">' + warnings.slice(0, 8).map(function (w) { return '<li>' + escHtml(w) + '</li>'; }).join('') + '</ul>'
                            : '';
                        $status.html('<div style="color:var(--ugw-error);">❌ ' + escHtml(job.message || 'Clone failed') + warningHtml + '</div>');
                        finishButton();
                        return;
                    }

                    $status.html(renderCloneProgress(job));
                    window.setTimeout(poll, 3000);
                })
                .fail(function (xhr) {
                    statusFailures++;
                    if ((!xhr.status || xhr.status === 429 || xhr.status >= 500) && statusFailures <= 6) {
                        $status.html(renderCloneProgress({ message: 'Reconnecting to the import job...', progress: 90 }));
                        window.setTimeout(poll, Math.min(15000, statusFailures * 2500));
                        return;
                    }
                    $status.html('<div style="color:var(--ugw-error);">❌ ' + escHtml(ajaxErrorMessage(xhr, 'Could not check clone status.')) + '</div>');
                    finishButton();
                });
        }

        poll();
    }

    function getIndustryGroups() {
        if (W.industryGroups) return W.industryGroups;
        var questions = W.discoveryQuestions || [];
        for (var i = 0; i < questions.length; i++) {
            if (questions[i].field === 'business_type') {
                return questions[i].groups || {};
            }
        }
        return {};
    }

    function populateIndustrySelect(selector) {
        var $select = $(selector);
        if (!$select.length || $select.data('ugw-populated')) return;

        var groups = getIndustryGroups();
        $select.empty().append('<option value="">Select an industry...</option>');
        $.each(groups, function (groupName, items) {
            var $group = $('<optgroup>').attr('label', groupName);
            $.each(items, function (key, label) {
                $group.append($('<option>').attr('value', key).text(label));
            });
            $select.append($group);
        });
        $select.data('ugw-populated', true);
    }

    function getIndustryLabel(key) {
        var groups = getIndustryGroups();
        var label = '';
        $.each(groups, function (_, items) {
            if (items[key]) label = items[key];
        });
        return label || key || '';
    }

    function selectIndustryFromText(prefix, text) {
        populateIndustrySelect('#ugw-' + prefix + '-industry-key');
        var normalized = String(text || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
        var matched = '';
        var groups = getIndustryGroups();
        $.each(groups, function (_, items) {
            $.each(items, function (key, label) {
                var keyNorm = String(key).toLowerCase().replace(/[^a-z0-9]+/g, '');
                var labelNorm = String(label).toLowerCase().replace(/[^a-z0-9]+/g, '');
                if (!matched && (normalized === keyNorm || normalized === labelNorm || labelNorm.indexOf(normalized) !== -1 || normalized.indexOf(labelNorm) !== -1)) {
                    matched = key;
                }
            });
        });

        var $select = $('#ugw-' + prefix + '-industry-key');
        var $custom = $('#ugw-' + prefix + '-industry');
        if (matched) {
            $select.val(matched);
            $custom.val('').hide();
        } else if (text) {
            $select.val('other');
            $custom.val(text).show();
        }
    }

    function updateIndustryCustomField(prefix) {
        var key = $('#ugw-' + prefix + '-industry-key').val();
        $('#ugw-' + prefix + '-industry').toggle(key === 'other');
    }

    function collectIndustryPayload(prefix) {
        var key = $('#ugw-' + prefix + '-industry-key').val() || '';
        var custom = $('#ugw-' + prefix + '-industry').val() || '';
        var label = key === 'other' ? custom : getIndustryLabel(key);
        return {
            industry_key: key,
            industry_label: label,
            industry: custom || label,
            industry_niche: $('#ugw-' + prefix + '-industry-niche').val() || '',
            target_audience: $('#ugw-' + prefix + '-target-audience').val() || '',
            services_products: $('#ugw-' + prefix + '-services').val() || '',
            business_goals: $('#ugw-' + prefix + '-goals').val() || '',
            tone_of_voice: $('#ugw-' + prefix + '-tone').val() || 'professional',
        };
    }

    function hydrateIndustryFields(prefix, data) {
        data = data || {};
        function listText(value) {
            if (typeof value === 'string') {
                try {
                    var parsed = JSON.parse(value);
                    if (Array.isArray(parsed)) return parsed.join(', ');
                } catch (e) {}
                return value;
            }
            return Array.isArray(value) ? value.join(', ') : (value || '');
        }
        populateIndustrySelect('#ugw-' + prefix + '-industry-key');
        var key = data.industry_key || data.business_type || '';
        if (key) {
            $('#ugw-' + prefix + '-industry-key').val(key);
        }
        if (!$('#ugw-' + prefix + '-industry-key').val() && (data.industry_label || data.custom_industry)) {
            selectIndustryFromText(prefix, data.custom_industry || data.industry_label);
        }
        $('#ugw-' + prefix + '-industry').val(data.custom_industry || '');
        $('#ugw-' + prefix + '-industry-niche').val(data.industry_niche || '');
        $('#ugw-' + prefix + '-target-audience').val(data.target_audience || '');
        $('#ugw-' + prefix + '-services').val(listText(data.services_products));
        $('#ugw-' + prefix + '-goals').val(listText(data.business_goals));
        $('#ugw-' + prefix + '-tone').val(data.tone_of_voice || 'professional');
        updateIndustryCustomField(prefix);
    }

    // ========================================================================
    // NAVIGATION
    // ========================================================================

    function goToStep(step) {
        state.currentStep = step;
        $('.ugw-step-content').removeClass('ugw-step-content--active');
        $('#ugw-step-' + step).addClass('ugw-step-content--active');

        // Update sidebar
        var stepsOrder = ['discovery', 'brand', 'inspiration', 'pages', 'content', 'compliance', 'design', 'images'];
        var targetIdx = stepsOrder.indexOf(step);

        $('#ugw-steps-nav .ugw-step').each(function (i) {
            var $s = $(this);
            $s.removeClass('ugw-step--active ugw-step--complete');
            if (i < targetIdx) $s.addClass('ugw-step--complete');
            if (i === targetIdx) $s.addClass('ugw-step--active');
        });

        // Inject skip button for skippable steps (if not already present)
        var skippableSteps = ['discovery', 'brand', 'inspiration', 'pages', 'content', 'compliance', 'images'];
        var $stepPanel = $('#ugw-step-' + step);
        $stepPanel.find('.ugw-skip-step-btn').remove(); // Clean up any existing

        if (skippableSteps.indexOf(step) !== -1) {
            var skipBtn = $(
                '<button class="ugw-skip-step-btn ugw-btn ugw-btn--ghost ugw-btn--sm" style="' +
                'position:absolute;top:16px;right:16px;border:1px solid var(--ugw-border);' +
                'color:var(--ugw-text-secondary);padding:6px 16px;border-radius:20px;cursor:pointer;font-size:12px;' +
                'letter-spacing:0.5px;transition:all 0.2s;z-index:10;background:var(--ugw-bg-surface);' +
                '" data-skip-step="' + step + '">Skip this step →</button>'
            );
            skipBtn.on('mouseenter', function () {
                $(this).css({ 'border-color': 'var(--ugw-accent)', 'color': 'var(--ugw-accent)' });
            }).on('mouseleave', function () {
                $(this).css({ 'border-color': 'var(--ugw-border)', 'color': 'var(--ugw-text-secondary)' });
            });
            $stepPanel.css('position', 'relative').prepend(skipBtn);
        }

        // Initialize step (load data, render UI etc.)
        onStepEnter(step);
    }

    function nextStep() {
        var steps = ['discovery', 'brand', 'inspiration', 'pages', 'content', 'compliance', 'design', 'images'];
        var idx = steps.indexOf(state.currentStep);
        if (idx < steps.length - 1) {
            goToStep(steps[idx + 1]); // goToStep already calls onStepEnter
        } else {
            // Last step → launch
            showLaunch();
        }
    }

    /**
     * Universal step skip — calls the backend skip handler and advances.
     */
    function skipStep(step) {
        var $btn = $('[data-skip-step="' + step + '"]');
        $btn.text('Skipping...').prop('disabled', true);

        post('ugentic_wizard_skip_step', {
            project_id: state.projectId,
            step: step,
        }).done(function (res) {
            if (res.success) {
                toast(step.charAt(0).toUpperCase() + step.slice(1) + ' step skipped', 'info');
                nextStep();
            } else {
                toast(res.data && res.data.message || 'Failed to skip', 'error');
                $btn.text('Skip this step →').prop('disabled', false);
            }
        }).fail(function () {
            toast('Connection error', 'error');
            $btn.text('Skip this step →').prop('disabled', false);
        });
    }

    // Global click handler for skip buttons
    $(document).on('click', '.ugw-skip-step-btn', function () {
        var step = $(this).data('skip-step');
        if (step) skipStep(step);
    });

    function onStepEnter(step) {
        switch (step) {
            case 'discovery': initDiscoveryStep(); break;
            case 'brand': initBrandStep(); break;
            case 'inspiration': initInspirationStep(); break;
            case 'pages': initPagesStep(); break;
            case 'content': initContentStep(); break;
            case 'compliance': initComplianceStep(); break;
            case 'design': initDesignStep(); break;
            case 'images': initImagesStep(); break;
        }
    }

    // ========================================================================
    // START SCREEN
    // ========================================================================

    function initStart() {
        populateIndustrySelect('#ugw-qg-industry-key');
        populateIndustrySelect('#ugw-guided-industry-key');
        var projects = W.projects || [];
        if (projects.length > 0) {
            $('#ugw-existing-projects').show();
            var list = $('#ugw-projects-list');
            list.empty();
            projects.forEach(function (p) {
                list.append(
                    '<div class="ugw-project-card" data-id="' + p.id + '">' +
                    '  <div class="ugw-project-card-icon">📁</div>' +
                    '  <div class="ugw-project-card-info">' +
                    '    <div class="ugw-project-card-name">' + escHtml(p.name) + '</div>' +
                    '    <div class="ugw-project-card-status">' + escHtml(p.status || 'In progress') + '</div>' +
                    '  </div>' +
                    '  <button class="ugw-project-delete" data-id="' + p.id + '" title="Delete project" style="background:none;border:none;cursor:pointer;font-size:16px;opacity:0.4;padding:8px;margin-left:auto;transition:opacity 0.2s;">🗑️</button>' +
                    '</div>'
                );
            });
        }
    }

    function loadProject(projectId) {
        post('ugentic_wizard_get_state', { project_id: projectId })
            .done(function (res) {
                if (res.success) {
                    state.projectId = projectId;
                    state.project = res.data.project || {};
                    state.pages = res.data.pages || [];
                    state.projectName = res.data.project ? (res.data.project.name || res.data.project.project_name || res.data.project.business_name || 'My Website') : 'My Website';
                    state.industryOptimization = res.data.industry_optimization || {};
                    state.designTokens = res.data.design_tokens || {};
                    state.canvasHeader = (res.data.canvas_state && res.data.canvas_state.global_header_html) || '';
                    state.canvasFooter = (res.data.canvas_state && res.data.canvas_state.global_footer_html) || '';

                    var step = res.data.current_step || 'discovery';
                    var hasPages = state.pages && state.pages.length > 0;
                    var projectStatus = (res.data.project && res.data.project.status) || '';
                    // Canvas-ready: has pages AND at design-stage or later
                    var isCanvasReady = hasPages && (
                        step === 'design' || step === 'images' || step === 'complete' ||
                        projectStatus === 'pages_approved' ||
                        projectStatus === 'content_approved' ||
                        projectStatus === 'compliance_complete' ||
                        projectStatus === 'design_approved' ||
                        projectStatus === 'complete'
                    );

                    // Quick Generate projects or projects at design step → open canvas
                    if (isCanvasReady) {
                        var canvasPages = [];
                        state.pages.forEach(function (p) {
                            if (p.page_type === 'legal') return;
                            canvasPages.push({
                                id: p.id,
                                page_name: p.page_name,
                                page_slug: p.page_slug,
                                page_type: p.page_type,
                                generation_status: p.generation_status || (p.generated_html ? 'complete' : 'pending'),
                                has_design: !!(p.has_design || p.html || p.generated_html),
                                html: p.html || p.generated_html || '',
                                approved: !!p.approved,
                            });
                        });

                        // Restore colors
                        if (res.data.project) {
                            state.extractedPrimary = res.data.project.primary_color || '#1B2A4A';
                            state.extractedSecondary = res.data.project.secondary_color || '#00D4AA';
                            state.extractedLogo = res.data.project.logo_url || '';
                        }
                        if (state.designTokens.colors) {
                            state.extractedPrimary = state.designTokens.colors.primary || state.extractedPrimary;
                            state.extractedSecondary = state.designTokens.colors.secondary || state.extractedSecondary;
                        }

                        $('#ugw-start-screen').hide();
                        openCanvas(state.projectName, canvasPages);
                        return;
                    }

                    // Regular guided wizard flow
                    $('#ugw-start-screen').hide();
                    $('#ugw-wizard-layout').show();
                    hydrateIndustryFields('guided', Object.assign({}, state.industryOptimization, {
                        business_type: state.project.business_type,
                        target_audience: state.project.target_audience,
                        services_products: state.project.services_products,
                        business_goals: state.project.business_goals,
                        tone_of_voice: state.project.tone_of_voice,
                    }));

                    if (step === 'discovery') {
                        if (res.data.conversations && res.data.conversations.discovery) {
                            var chatEl = document.getElementById('ugw-discovery-chat');
                            if (chatEl) {
                                res.data.conversations.discovery.forEach(function (msg) {
                                    addChatBubble(chatEl, msg.message, msg.role);
                                });
                            }
                        }
                        if (res.data.next_question) {
                            showDiscoveryQuestion(res.data.next_question);
                        } else {
                            post('ugentic_wizard_complete_discovery', { project_id: state.projectId })
                                .done(function () {
                                    step = 'brand';
                                    goToStep(step);
                                });
                            return;
                        }
                    }

                    goToStep(step);
                } else {
                    toast('Failed to load project', 'error');
                }
            })
            .fail(function () {
                toast('Connection error', 'error');
            });
    }

    // ========================================================================
    // STEP 1: DISCOVERY
    // ========================================================================

    var currentQuestion = null;

    /**
     * Initialize discovery step — ensures the next question is loaded.
     * This is called by onStepEnter('discovery') and also when loading a project.
     */
    function initDiscoveryStep() {
        // If we already have a question displayed, don't re-fetch
        if (currentQuestion) return;

        // Ask the server for the current state to get the next question
        if (state.projectId) {
            post('ugentic_wizard_get_state', { project_id: state.projectId })
                .done(function (res) {
                    if (res.success) {
                        // Replay prior conversation messages
                        if (res.data.conversations && res.data.conversations.discovery) {
                            var chatEl = document.getElementById('ugw-discovery-chat');
                            // Only replay if chat is empty (avoid duplicates)
                            if (chatEl && chatEl.querySelectorAll('.ugw-chat-bubble').length === 0) {
                                res.data.conversations.discovery.forEach(function (msg) {
                                    addChatBubble(chatEl, msg.message, msg.role);
                                });
                            }
                        }

                        if (res.data.next_question) {
                            showDiscoveryQuestion(res.data.next_question);
                        } else if (res.data.current_step !== 'discovery') {
                            // Discovery is already complete, move to correct step
                            goToStep(res.data.current_step);
                        } else {
                            // Fallback: use first question from config
                            var questions = W.discoveryQuestions || [];
                            if (questions.length > 0) {
                                showDiscoveryQuestion(questions[0]);
                            }
                        }
                    }
                })
                .fail(function () {
                    // Fallback: use first question from config
                    var questions = W.discoveryQuestions || [];
                    if (questions.length > 0) {
                        showDiscoveryQuestion(questions[0]);
                    }
                });
        } else {
            // No project yet — use first question from config
            var questions = W.discoveryQuestions || [];
            if (questions.length > 0) {
                showDiscoveryQuestion(questions[0]);
            }
        }
    }

    function showDiscoveryQuestion(q) {
        currentQuestion = q;
        var chatEl = document.getElementById('ugw-discovery-chat');

        hideTyping();
        addChatBubble(chatEl, q.question, 'assistant');

        // Update progress
        var questions = W.discoveryQuestions || [];
        var qIdx = questions.findIndex(function (x) { return x.id === q.id; });
        var pct = Math.round((qIdx / questions.length) * 100);
        $('#ugw-discovery-progress .ugw-progress-bar').css('width', pct + '%');

        // Show appropriate input
        $('#ugw-discovery-chips').empty().hide();
        $('#ugw-discovery-multi-input').empty().hide();
        $('#ugw-discovery-contact-form').empty().hide();
        $('#ugw-discovery-input-area').show();
        $('#ugw-discovery-input').val('').attr('placeholder', q.placeholder || 'Type your answer...').focus();

        if (q.type === 'chips' || q.type === 'multi_select') {
            var $chips = $('#ugw-discovery-chips').show();
            $.each(q.options, function (key, label) {
                // For arrays, key is numeric index — use label as both value and display text
                var chipValue = (typeof key === 'number') ? label : key;
                var chipLabel = (typeof label === 'object' && label !== null) ? (label.label || key) : label;
                $chips.append('<span class="ugw-chip" data-value="' + escHtml(String(chipValue)) + '">' + escHtml(String(chipLabel)) + '</span>');
            });

            if (q.type === 'chips') {
                // Single select — clicking a chip sends the answer
                $('#ugw-discovery-input-area').hide();
            }

            if (q.type === 'multi_select') {
                // Multi select — user toggles chips then clicks Done
                $('#ugw-discovery-input-area').hide();
                $chips.after('<button class="ugw-btn ugw-btn--primary ugw-btn--sm ugw-multi-select-done" style="margin-top: 10px;">Done →</button>');
            }
        }

        if (q.type === 'multi_input') {
            $('#ugw-discovery-input-area').hide();
            var $mi = $('#ugw-discovery-multi-input').show();
            $mi.html(
                '<div class="ugw-multi-input" id="ugw-mi-wrapper">' +
                '  <input type="text" placeholder="' + escHtml(q.placeholder || 'Type and press Enter') + '" id="ugw-mi-input">' +
                '</div>' +
                '<div style="margin-top: 8px; font-size: 12px; color: var(--ugw-text-light);">Press Enter to add each item</div>' +
                '<button class="ugw-btn ugw-btn--primary ugw-btn--sm" id="ugw-mi-done" style="margin-top: 10px;">Done</button>'
            );
        }

        if (q.type === 'contact_form') {
            $('#ugw-discovery-input-area').hide();
            var $cf = $('#ugw-discovery-contact-form').show();
            var html = '<div class="ugw-contact-fields">';
            q.fields.forEach(function (f) {
                html += '<div class="ugw-form-group">' +
                    '<label class="ugw-form-label">' + escHtml(f.label) + (f.required ? '' : ' <span>(optional)</span>') + '</label>' +
                    '<input type="' + f.type + '" class="ugw-input ugw-contact-field" data-key="' + f.key + '" placeholder="' + escHtml(f.label) + '">' +
                    '</div>';
            });
            html += '</div>';
            html += '<button class="ugw-btn ugw-btn--primary" id="ugw-contact-done" style="margin-top: 16px;">Continue →</button>';
            $cf.html(html);
        }

        if (q.allow_skip) {
            var skipHtml = '<div class="ugw-skip" style="margin-top: 8px;"><a class="ugw-discovery-skip">Skip this question →</a></div>';
            if (q.type === 'chips') {
                $('#ugw-discovery-chips').after(skipHtml);
            } else {
                $('#ugw-discovery-input-area').after(skipHtml);
            }
        }
    }

    function sendDiscoveryAnswer(value) {
        if (!currentQuestion || !state.projectId) return;

        var chatEl = document.getElementById('ugw-discovery-chat');
        var displayVal = Array.isArray(value) ? value.join(', ') : value;
        addChatBubble(chatEl, displayVal, 'user');
        showTyping(chatEl);

        post('ugentic_wizard_send_discovery', {
            project_id: state.projectId,
            field: currentQuestion.field,
            value: value,
        }).done(function (res) {
            hideTyping();
            if (res.success) {
                if (res.data.step_complete) {
                    addChatBubble(chatEl, "Perfect! I've got everything I need. Let's define your brand identity! 🎨", 'assistant');
                    $('#ugw-discovery-progress .ugw-progress-bar').css('width', '100%');
                    $('#ugw-discovery-input-area').hide();
                    $('#ugw-discovery-chips').hide();
                    setTimeout(function () {
                        post('ugentic_wizard_complete_discovery', { project_id: state.projectId }).done(function () {
                            nextStep();
                        });
                    }, 1500);
                } else if (res.data.next_question) {
                    showDiscoveryQuestion(res.data.next_question);
                }
            } else {
                toast('Failed to save answer', 'error');
            }
        }).fail(function () {
            hideTyping();
            toast('Connection error', 'error');
        });
    }

    // ========================================================================
    // STEP 2: LOGO
    // ========================================================================

    function initLogoStep() {
        // Restore previously approved logo if resuming
        if (state.project && state.project.logo_url) {
            $('#ugw-logo-options').hide();
            $('#ugw-logo-preview-area').show();
            $('#ugw-logo-img').attr('src', state.project.logo_url).show();
            state.currentLogoData = null; // Already saved server-side
            $('.ugw-logo-actions').show();
        }

        // Populate option cards
        renderSelectCards('#ugw-logo-icon-styles', W.iconStyles || {}, 'icon_style', state.selectedIconStyle);
        renderSelectCards('#ugw-logo-moods', W.moods || {}, 'mood', state.selectedMood);
        renderSelectCards('#ugw-logo-shapes', W.shapeLanguages || {}, 'shape', state.selectedShape);
    }

    function renderSelectCards(container, items, type, selected) {
        var $c = $(container).empty();
        $.each(items, function (key, item) {
            var sel = key === selected ? ' ugw-card--selected' : '';
            // Handle both plain string labels and {label, description} objects
            var label = (typeof item === 'object' && item !== null) ? (item.label || key) : item;
            var desc = (typeof item === 'object' && item !== null && item.description) ? item.description : '';
            var descHtml = desc ? '<div class="ugw-card-desc" style="font-size:11px;opacity:0.7;margin-top:2px;">' + escHtml(desc) + '</div>' : '';
            $c.append(
                '<div class="ugw-card ugw-card--selectable' + sel + '" data-value="' + key + '" data-type="' + type + '">' +
                '<div class="ugw-card-title">' + escHtml(label) + '</div>' + descHtml + '</div>'
            );
        });
    }

    function generateLogo(action, extra) {
        var data = Object.assign({
            project_id: state.projectId,
            icon_style: state.selectedIconStyle,
            mood: state.selectedMood,
            shape_language: state.selectedShape,
            references: $('#ugw-logo-references').val() || '',
        }, extra || {});

        $('#ugw-logo-options').hide();
        $('#ugw-logo-preview-area').show();
        $('#ugw-logo-loading').show();
        $('#ugw-logo-img').hide();
        $('.ugw-logo-actions').hide();

        post(action, data)
            .done(function (res) {
                $('#ugw-logo-loading').hide();
                if (res.success && res.data.image_data) {
                    var src = 'data:' + (res.data.mime_type || 'image/png') + ';base64,' + res.data.image_data;
                    $('#ugw-logo-img').attr('src', src).show();
                    state.currentLogoData = res.data.image_data;
                    state.logoHistory.push(src);
                    updateLogoGallery();
                    $('.ugw-logo-actions').show();
                } else {
                    toast(res.data?.error || 'Logo generation failed', 'error');
                    $('#ugw-logo-options').show();
                    $('#ugw-logo-preview-area').hide();
                }
            })
            .fail(function () {
                $('#ugw-logo-loading').hide();
                toast('Connection error', 'error');
                $('#ugw-logo-options').show();
                $('#ugw-logo-preview-area').hide();
            });
    }

    function updateLogoGallery() {
        var $g = $('#ugw-logo-gallery').empty();
        state.logoHistory.forEach(function (src, i) {
            $g.append('<div class="ugw-logo-thumb" data-idx="' + i + '"><img src="' + src + '"></div>');
        });
    }

    // ========================================================================
    // STEP 3: BRAND
    // ========================================================================

    function initBrandStep() {
        populateIndustrySelect('#ugw-guided-industry-key');
        // Restore saved brand data if resuming
        if (state.project) {
            if (state.project.primary_color) {
                $('#ugw-brand-primary').val(state.project.primary_color);
            }
            if (state.project.secondary_color) {
                $('#ugw-brand-secondary').val(state.project.secondary_color);
            }
            if (state.project.design_style) {
                state.selectedDesignStyle = state.project.design_style;
                $('#ugw-design-styles .ugw-card').each(function () {
                    $(this).toggleClass('ugw-card--selected', $(this).data('style') === state.project.design_style);
                });
            }
            hydrateIndustryFields('guided', Object.assign({}, state.industryOptimization || {}, {
                business_type: state.project.business_type,
                target_audience: state.project.target_audience,
                services_products: state.project.services_products,
                business_goals: state.project.business_goals,
                tone_of_voice: state.project.tone_of_voice,
            }));
        }

        if (state.projectId) {
            post('ugentic_wizard_get_state', { project_id: state.projectId }).done(function (res) {
                if (!res.success) return;
                state.project = res.data.project || state.project || {};
                state.industryOptimization = res.data.industry_optimization || state.industryOptimization || {};
                hydrateIndustryFields('guided', Object.assign({}, state.industryOptimization, {
                    business_type: state.project.business_type,
                    target_audience: state.project.target_audience,
                    services_products: state.project.services_products,
                    business_goals: state.project.business_goals,
                    tone_of_voice: state.project.tone_of_voice,
                }));
            });
        }

        // Init color pickers if available
        if ($.fn.wpColorPicker) {
            $('#ugw-brand-primary, #ugw-brand-secondary').wpColorPicker();
        }
    }

    function initPagesStep() {
        // Show already-saved pages when resuming a project
        if (state.pages && state.pages.length > 0 && !state.generatedPages) {
            state.generatedPages = state.pages.map(function (p) {
                return {
                    page_name: p.page_name,
                    page_type: p.page_type,
                    page_slug: p.page_slug,
                    page_description: p.page_description,
                    estimated_sections: p.estimated_sections || 0,
                    id: p.id,
                };
            });
            renderPageList(state.generatedPages);
            $('#ugw-pages-result').show();
        }
    }

    // ========================================================================
    // STEP 5: CONTENT
    // ========================================================================

    function initContentStep() {
        // If pages list is empty, try fetching from server
        if (!state.pages || !state.pages.length) {
            post('ugentic_wizard_get_state', { project_id: state.projectId }).done(function (r) {
                if (r.success && r.data.pages && r.data.pages.length) {
                    state.pages = r.data.pages;
                    renderContentTabs();
                } else {
                    $('#ugw-content-sections').html(
                        '<div style="text-align:center;padding:48px 20px;color:var(--ugw-text-secondary);">' +
                        '<div style="font-size:48px;margin-bottom:16px;">📝</div>' +
                        '<h3 style="margin-bottom:8px;font-weight:600;">No Pages Yet</h3>' +
                        '<p>Go to the <strong>Pages</strong> step first to plan your website structure, then come back here to generate content.</p>' +
                        '</div>'
                    );
                }
            });
            return;
        }
        renderContentTabs();
    }

    function renderContentTabs() {
        var $tabs = $('#ugw-content-tabs').empty();
        state.pages.forEach(function (p, i) {
            if (p.page_type === 'legal') return;
            var statusClass = '';
            var statusIcon = '';
            if (p.copy_status === 'approved') {
                statusClass = ' ugw-content-tab--approved';
                statusIcon = ' ✓';
            } else if (p.copy_content || p.section_outline) {
                statusClass = ' ugw-content-tab--pending';
                statusIcon = ' ●';
            }

            var active = i === 0 ? ' ugw-content-tab--active' : '';
            $tabs.append(
                '<button class="ugw-content-tab' + active + statusClass + '" data-page-idx="' + i + '">' +
                escHtml(p.page_name) + statusIcon +
                '</button>'
            );
        });

        if (state.pages.length > 0) {
            loadContentForPage(0);
        }
    }

    function loadContentForPage(idx) {
        state.activeContentTab = idx;
        var page = state.pages[idx];
        if (!page) return;

        $('.ugw-content-tab').removeClass('ugw-content-tab--active');
        $('.ugw-content-tab[data-page-idx="' + idx + '"]').addClass('ugw-content-tab--active');

        if (page.section_outline) {
            // Content already generated — show it
            try {
                var sections = JSON.parse(page.section_outline);
                renderContentSections(sections);
                $('#ugw-content-actions').show();
            } catch (e) {
                renderContentSections([]);
                $('#ugw-content-actions').show();
            }
        } else {
            // Show generate prompt for this page
            $('#ugw-content-sections').html(
                '<div style="text-align:center;padding:40px 20px;border:2px dashed var(--ugw-border);border-radius:12px;margin:16px 0;">' +
                '<div style="font-size:36px;margin-bottom:12px;">✨</div>' +
                '<h3 style="margin-bottom:8px;font-weight:600;">' + escHtml(page.page_name) + '</h3>' +
                '<p style="color:var(--ugw-text-secondary);margin-bottom:20px;">Generate AI-powered content for this page. You can edit everything after.</p>' +
                '<div class="ugw-form-group" style="max-width:400px;margin:0 auto 16px;">' +
                '  <textarea class="ugw-textarea" id="ugw-content-instructions" placeholder="Optional: any specific instructions for this page\'s content..." style="min-height:60px;"></textarea>' +
                '</div>' +
                '<button class="ugw-btn ugw-btn--primary" id="ugw-content-generate-page">✨ Generate Content for ' + escHtml(page.page_name) + '</button>' +
                '</div>'
            );
            $('#ugw-content-actions').hide();
        }
    }

    function generateContentForCurrentPage() {
        var page = state.pages[state.activeContentTab];
        if (!page) return;

        var instructions = $('#ugw-content-instructions').val() || '';

        $('#ugw-content-sections').empty();
        $('#ugw-content-actions').hide();
        $('#ugw-content-loading').show();

        post('ugentic_wizard_generate_content', {
            project_id: state.projectId,
            page_id: page.id,
            instructions: instructions,
        }).done(function (res) {
            $('#ugw-content-loading').hide();
            if (res.success) {
                page.section_outline = JSON.stringify(res.data.sections);
                page.copy_content = res.data.copy_content;
                renderContentSections(res.data.sections);
                $('#ugw-content-actions').show();
                // Update tab to show pending status
                renderContentTabs();
                loadContentForPage(state.activeContentTab);
                toast('Content generated!', 'success');
            } else {
                var errMsg = 'Content generation failed';
                if (res.data) {
                    errMsg = res.data.error || res.data.message || errMsg;
                }
                toast(errMsg, 'error');
                loadContentForPage(state.activeContentTab);
            }
        }).fail(function () {
            $('#ugw-content-loading').hide();
            toast('Connection error', 'error');
            loadContentForPage(state.activeContentTab);
        });
    }

    function renderContentSections(sections) {
        var $el = $('#ugw-content-sections').empty();
        if (!sections || !sections.length) {
            $el.html('<p style="color:var(--ugw-text-secondary);text-align:center;padding:20px;">No sections generated.</p>');
            return;
        }

        sections.forEach(function (s, i) {
            var html = '<div class="ugw-section-card" data-section-idx="' + i + '" style="border:1px solid var(--ugw-border, #e2e8f0);border-radius:10px;padding:20px;margin-bottom:16px;background:var(--ugw-card-bg, #fff);">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
            html += '  <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:var(--ugw-text-secondary);font-weight:600;">' + escHtml(s.section_type || 'Section') + '</span>';
            html += '  <span style="font-size:11px;color:var(--ugw-text-secondary);">Section ' + (i + 1) + '</span>';
            html += '</div>';

            // Editable heading
            html += '<div class="ugw-editable-field" style="margin-bottom:8px;">';
            html += '  <input type="text" class="ugw-section-heading-input" data-idx="' + i + '" value="' + escHtml(s.section_heading || '') + '" style="width:100%;font-size:18px;font-weight:700;border:1px solid transparent;background:transparent;padding:4px 8px;border-radius:6px;transition:border-color 0.2s;" onfocus="this.style.borderColor=\'var(--ugw-primary, #3b82f6)\'" onblur="this.style.borderColor=\'transparent\'">';
            html += '</div>';

            // Editable subheading
            if (s.section_subheading) {
                html += '<div class="ugw-editable-field" style="margin-bottom:8px;">';
                html += '  <input type="text" class="ugw-section-subheading-input" data-idx="' + i + '" value="' + escHtml(s.section_subheading || '') + '" style="width:100%;font-size:14px;font-weight:500;color:var(--ugw-text-secondary);border:1px solid transparent;background:transparent;padding:4px 8px;border-radius:6px;transition:border-color 0.2s;" onfocus="this.style.borderColor=\'var(--ugw-primary, #3b82f6)\'" onblur="this.style.borderColor=\'transparent\'">';
                html += '</div>';
            }

            // Editable content
            html += '<div class="ugw-editable-field">';
            html += '  <textarea class="ugw-section-content-input" data-idx="' + i + '" style="width:100%;min-height:80px;font-size:14px;line-height:1.6;border:1px solid transparent;background:transparent;padding:8px;border-radius:6px;resize:vertical;transition:border-color 0.2s;font-family:inherit;" onfocus="this.style.borderColor=\'var(--ugw-primary, #3b82f6)\'" onblur="this.style.borderColor=\'transparent\'">' + escHtml(s.section_content || '') + '</textarea>';
            html += '</div>';

            html += '</div>';
            $el.append(html);
        });
    }

    // ========================================================================
    // STEP 6: COMPLIANCE
    // ========================================================================

    function initComplianceStep() {
        var questions = W.complianceQuestions || [];
        var $form = $('#ugw-compliance-form').empty();

        questions.forEach(function (category) {
            var html = '<div class="ugw-compliance-group">';
            html += '<div class="ugw-compliance-group-header">';
            html += '  <div class="ugw-compliance-group-icon">' + (category.icon || '📋') + '</div>';
            html += '  <div class="ugw-compliance-group-title">' + escHtml(category.category || category.title) + '</div>';
            html += '</div>';

            (category.questions || []).forEach(function (q) {
                html += '<div class="ugw-compliance-question">';
                html += '  <div class="ugw-compliance-question-text">' + escHtml(q.label || q.question) + '</div>';
                html += '  <label class="ugw-toggle">';
                html += '    <input type="checkbox" data-compliance-id="' + q.id + '">';
                html += '    <span class="ugw-toggle-slider"></span>';
                html += '  </label>';
                html += '</div>';
            });

            html += '</div>';
            $form.append(html);
        });
    }

    // ========================================================================
    // STEP 7: DESIGN
    // ========================================================================

    function initDesignStep() {
        // Pre-fetch design research in background (caches competitor analysis + trends)
        // This runs silently so data is ready when actual generation starts
        if (state.projectId) {
            post('ugentic_wizard_prefetch_research', {
                project_id: state.projectId
            }); // fire-and-forget — no .done() needed
        }

        // If pages not loaded yet, fetch them first
        if (!state.pages || !state.pages.length) {
            $('#ugw-design-loading').show();
            $('#ugw-design-iframe').hide();
            post('ugentic_wizard_get_state', { project_id: state.projectId }).done(function (r) {
                if (r.success && r.data.pages && r.data.pages.length) {
                    state.pages = r.data.pages;
                    setupDesignStep();
                } else {
                    $('#ugw-design-loading').hide();
                    toast('No pages found — go back to Pages step', 'error');
                }
            }).fail(function () {
                $('#ugw-design-loading').hide();
                toast('Connection error', 'error');
            });
            return;
        }

        setupDesignStep();
    }

    function setupDesignStep() {
        var $select = $('#ugw-design-page-select').empty();
        var designablePages = [];
        var hasUndesigned = false;

        state.pages.forEach(function (p, i) {
            if (p.page_type === 'legal') return;
            $select.append('<option value="' + i + '">' + escHtml(p.page_name) + '</option>');
            designablePages.push(i);
            if (!p.generated_html) hasUndesigned = true;
        });

        if (!designablePages.length) {
            toast('No pages to design', 'error');
            return;
        }

        // Find first page with a design to preview, or first page
        var previewIdx = designablePages[0];
        for (var i = 0; i < designablePages.length; i++) {
            var pg = state.pages[designablePages[i]];
            if (pg && pg.generated_html) {
                previewIdx = designablePages[i];
                break;
            }
        }

        state.currentPageIdx = previewIdx;
        $select.val(previewIdx);

        var $msgs = $('#ugw-design-messages');

        if (hasUndesigned) {
            // Some pages need design — show canvas button
            $msgs.html(
                '<div class="ugw-chat-bubble ugw-chat-bubble--assistant">' +
                '🎨 Ready to generate designs! Click below to open the <strong>AI Design Studio</strong> — you\'ll guide content, generated design concepts, high-fidelity code, and animations before approval.' +
                '</div>' +
                '<div style="padding:8px 16px;">' +
                '<button class="ugw-btn ugw-btn--primary ugw-btn--full" id="ugw-design-generate-all">✨ Open AI Design Studio</button>' +
                '</div>'
            );

            // If current page has a design, show it
            if (state.pages[previewIdx] && state.pages[previewIdx].generated_html) {
                loadDesignPreview(state.pages[previewIdx].generated_html);
            } else {
                $('#ugw-design-loading').hide();
                $('#ugw-design-iframe').hide();
            }
        } else {
            // All pages already have designs — just show preview
            $msgs.html(
                '<div class="ugw-chat-bubble ugw-chat-bubble--assistant">' +
                '✅ All designs ready! Use the dropdown to preview each page, send feedback, or approve to continue.' +
                '</div>'
            );
            loadDesignPreview(state.pages[previewIdx].generated_html);
        }
    }

    // Bound via delegation so it works even after re-render
    $(document).on('click', '#ugw-design-generate-all', function () {
        // Switch to the canvas view for design generation
        var canvasPages = [];
        state.pages.forEach(function (p) {
            if (p.page_type === 'legal') return;
            canvasPages.push({
                id: p.id,
                page_name: p.page_name,
                page_slug: p.page_slug,
                page_type: p.page_type,
                generation_status: p.generated_html ? 'complete' : 'pending',
                has_design: !!p.generated_html,
                html: p.generated_html || '',
                approved: !!p.generated_html,
            });
        });

        if (!canvasPages.length) {
            toast('No pages to design', 'error');
            return;
        }

        state.canvasPages = canvasPages;
        var projectName = state.projectName || 'My Website';

        // Hide wizard layout, show canvas
        $('#ugw-wizard-layout').hide();
        $('#ugw-start-screen').hide();
        openCanvas(projectName, canvasPages);
    });

    function generateAllDesignsSequentially(pageIndices, current) {
        if (current >= pageIndices.length) {
            // Check if any actually succeeded
            var successCount = 0;
            pageIndices.forEach(function (i) {
                if (state.pages[i] && state.pages[i].generated_html) successCount++;
            });

            var $msgs = $('#ugw-design-messages');
            if (successCount === pageIndices.length) {
                $msgs.append(
                    '<div class="ugw-chat-bubble ugw-chat-bubble--assistant">' +
                    '✅ All ' + pageIndices.length + ' pages designed! Use the dropdown to preview each page, or tell me what changes you\'d like.' +
                    '</div>'
                );
            } else if (successCount > 0) {
                $msgs.append(
                    '<div class="ugw-chat-bubble ugw-chat-bubble--assistant">' +
                    '⚠️ ' + successCount + '/' + pageIndices.length + ' pages designed. Failed pages can be retried with the 🔄 button.' +
                    '</div>'
                );
            } else {
                $msgs.append(
                    '<div class="ugw-chat-bubble ugw-chat-bubble--assistant">' +
                    '❌ Design generation failed. This is usually an API timeout — try again with the button below.' +
                    '</div>' +
                    '<div style="padding:8px 16px;">' +
                    '<button class="ugw-btn ugw-btn--primary ugw-btn--full" id="ugw-design-generate-all">🔄 Retry Design Generation</button>' +
                    '</div>'
                );
            }

            // Show first designed page if any
            for (var i = 0; i < pageIndices.length; i++) {
                var pg = state.pages[pageIndices[i]];
                if (pg && pg.generated_html) {
                    state.currentPageIdx = pageIndices[i];
                    $('#ugw-design-page-select').val(pageIndices[i]);
                    loadDesignPreview(pg.generated_html);
                    break;
                }
            }
            $('#ugw-design-loading').hide();
            return;
        }

        var idx = pageIndices[current];
        var page = state.pages[idx];
        if (!page) {
            generateAllDesignsSequentially(pageIndices, current + 1);
            return;
        }

        // Skip if already has HTML
        if (page.generated_html) {
            generateAllDesignsSequentially(pageIndices, current + 1);
            return;
        }

        // Show status
        var $msgs = $('#ugw-design-messages');
        var statusId = 'ugw-design-status-' + idx;
        $msgs.append(
            '<div class="ugw-chat-bubble ugw-chat-bubble--assistant" id="' + statusId + '" style="font-size:13px;">' +
            '<div class="ugw-spinner" style="width:14px;height:14px;display:inline-block;vertical-align:middle;margin-right:8px;"></div>' +
            'Generating <strong>' + escHtml(page.page_name) + '</strong> (' + (current + 1) + '/' + pageIndices.length + ')...' +
            '</div>'
        );
        $msgs[0].scrollTop = $msgs[0].scrollHeight;

        $('#ugw-design-loading').show();
        $('#ugw-design-iframe').hide();

        post('ugentic_wizard_generate_design', {
            project_id: state.projectId,
            page_id: page.id,
        }).done(function (res) {
            if (res.success) {
                page.generated_html = res.data.html;
                page.image_slots = res.data.image_slots;
                $('#' + statusId).html('✓ <strong>' + escHtml(page.page_name) + '</strong> — done!');
                // Show preview of latest page
                loadDesignPreview(res.data.html);
                state.currentPageIdx = idx;
                $('#ugw-design-page-select').val(idx);
            } else {
                var errMsg = (res.data && res.data.error) ? res.data.error : (res.data && res.data.message ? res.data.message : 'unknown error');
                $('#' + statusId).html('⚠️ <strong>' + escHtml(page.page_name) + '</strong> — ' + escHtml(errMsg));
            }
            generateAllDesignsSequentially(pageIndices, current + 1);
        }).fail(function (xhr) {
            var errMsg = 'connection error';
            try { var r = JSON.parse(xhr.responseText); errMsg = r.data && r.data.error ? r.data.error : errMsg; } catch (e) {}
            $('#' + statusId).html('⚠️ <strong>' + escHtml(page.page_name) + '</strong> — ' + escHtml(errMsg));
            generateAllDesignsSequentially(pageIndices, current + 1);
        });
    }

    function generateDesignForPage(idx) {
        var page = state.pages[idx];
        if (!page) return;

        state.currentPageIdx = idx;
        $('#ugw-design-page-select').val(idx);

        if (page.generated_html) {
            loadDesignPreview(page.generated_html);
            return;
        }

        $('#ugw-design-loading').show();
        $('#ugw-design-iframe').hide();

        post('ugentic_wizard_generate_design', {
            project_id: state.projectId,
            page_id: page.id,
        }).done(function (res) {
            $('#ugw-design-loading').hide();
            if (res.success) {
                page.generated_html = res.data.html;
                page.image_slots = res.data.image_slots;
                loadDesignPreview(res.data.html);
            } else {
                toast('Design generation failed', 'error');
            }
        }).fail(function () {
            $('#ugw-design-loading').hide();
            toast('Connection error', 'error');
        });
    }

    function loadDesignPreview(html) {
        var doc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>' + html + '</body></html>';
        $('#ugw-design-loading').hide();
        $('#ugw-design-iframe').show().attr('srcdoc', doc);
    }

    // ========================================================================
    // STEP 4: INSPIRATION
    // ========================================================================

    var quizState = { currentQ: 0, answers: {}, selectedSites: [] };
    var blueprintBound = false;

    function initInspirationStep() {
        var quiz = ugenticWizard.inspirationQuiz || [];
        if (!quiz.length && !$('#ugw-inspiration-modes').length) {
            nextStep();
            return;
        }
        quizState.currentQ = 0;
        quizState.answers = {};
        quizState.selectedSites = [];
        $('#ugw-inspiration-quiz').show();
        $('#ugw-inspiration-gallery-area').hide();

        // Show quiz panel by default
        switchInspirationMode('quiz');

        renderQuizQuestion();
        bindInspirationEvents();
        bindBlueprintEvents();
    }

    /**
     * Render the current quiz question as selectable option cards.
     */
    function renderQuizQuestion() {
        var quiz = ugenticWizard.inspirationQuiz || [];
        if (!quiz.length || quizState.currentQ >= quiz.length) {
            // Quiz complete — show gallery or skip to next step
            finishQuiz();
            return;
        }

        var q = quiz[quizState.currentQ];
        var $area = $('#ugw-quiz-question-area');
        var html = '<h3 style="font-size:18px;font-weight:700;margin-bottom:6px;">' + escHtml(q.question) + '</h3>';
        if (q.subtitle) {
            html += '<p style="color:var(--ugw-text-secondary);font-size:13px;margin-bottom:20px;">' + escHtml(q.subtitle) + '</p>';
        }
        html += '<div class="ugw-card-grid ugw-card-grid--2" id="ugw-quiz-options">';

        var opts = q.options || {};
        Object.keys(opts).forEach(function(key) {
            var opt = opts[key];
            var selected = quizState.answers[q.id] === key ? ' ugw-card--selected' : '';
            html += '<div class="ugw-card ugw-card--selectable' + selected + '" data-quiz-key="' + escHtml(key) + '">';
            if (opt.icon) html += '<div style="font-size:28px;margin-bottom:8px;">' + opt.icon + '</div>';
            html += '<div class="ugw-card-title">' + escHtml(opt.label || key) + '</div>';
            if (opt.desc) html += '<div class="ugw-card-desc">' + escHtml(opt.desc) + '</div>';
            html += '</div>';
        });
        html += '</div>';
        $area.html(html);

        // Update progress bar
        var pct = ((quizState.currentQ) / quiz.length) * 100;
        $('#ugw-quiz-bar').css('width', pct + '%');

        // Update button states
        $('#ugw-quiz-back').toggle(quizState.currentQ > 0);
        $('#ugw-quiz-next').prop('disabled', !quizState.answers[q.id]);
    }

    function finishQuiz() {
        // Show gallery via AJAX
        $('#ugw-inspiration-quiz').hide();
        $('#ugw-inspiration-gallery-area').show();
        $('#ugw-quiz-bar').css('width', '100%');

        // Fetch inspiration sites
        post('ugentic_wizard_get_inspirations', {
            project_id: state.projectId,
            preferences: JSON.stringify(quizState.answers),
        }).done(function(res) {
            if (res.success && res.data && res.data.sites) {
                var grid = $('#ugw-inspiration-grid');
                grid.empty();
                res.data.sites.forEach(function(site) {
                    var card = $(
                        '<div class="ugw-inspiration-card" data-site-url="' + escHtml(site.url || '') + '">' +
                        '  <div class="ugw-inspiration-card-img" style="background:linear-gradient(135deg,var(--ugw-primary),var(--ugw-accent));height:140px;border-radius:8px 8px 0 0;"></div>' +
                        '  <div style="padding:12px;">' +
                        '    <div style="font-weight:600;font-size:14px;">' + escHtml(site.name || site.url) + '</div>' +
                        '    <div style="font-size:12px;color:var(--ugw-text-secondary);margin-top:4px;">' + escHtml(site.style || '') + '</div>' +
                        '  </div>' +
                        '</div>'
                    );
                    grid.append(card);
                });
            }
        });
    }

    /**
     * Bind inspiration step event handlers (quiz clicks, gallery, save).
     */
    function bindInspirationEvents() {
        // Quiz option selection
        $(document).on('click', '#ugw-quiz-options .ugw-card--selectable', function() {
            var quiz = ugenticWizard.inspirationQuiz || [];
            var q = quiz[quizState.currentQ];
            var key = $(this).data('quiz-key');

            quizState.answers[q.id] = key;
            $(this).siblings().removeClass('ugw-card--selected');
            $(this).addClass('ugw-card--selected');
            $('#ugw-quiz-next').prop('disabled', false);
        });

        // Quiz next
        $('#ugw-quiz-next').on('click', function() {
            var quiz = ugenticWizard.inspirationQuiz || [];
            quizState.currentQ++;
            if (quizState.currentQ >= quiz.length) {
                finishQuiz();
            } else {
                renderQuizQuestion();
            }
        });

        // Quiz back
        $('#ugw-quiz-back').on('click', function() {
            if (quizState.currentQ > 0) {
                quizState.currentQ--;
                renderQuizQuestion();
            }
        });

        // Gallery card selection
        $(document).on('click', '.ugw-inspiration-card', function() {
            $(this).toggleClass('ugw-inspiration-card--selected');
            var count = $('.ugw-inspiration-card--selected').length;
            $('#ugw-inspiration-save').prop('disabled', count === 0);
        });

        // Save preferences
        $('#ugw-inspiration-save').on('click', function() {
            var selected = [];
            $('.ugw-inspiration-card--selected').each(function() {
                selected.push($(this).data('site-url'));
            });

            post('ugentic_wizard_save_design_preferences', {
                project_id: state.projectId,
                quiz_answers: JSON.stringify(quizState.answers),
                selected_sites: JSON.stringify(selected),
            }).done(function(res) {
                if (res.success) {
                    toast('Design preferences saved! ✨', 'success');
                    nextStep();
                } else {
                    toast('Failed to save preferences', 'error');
                }
            });
        });

        // Skip inspiration
        $('#ugw-inspiration-skip').on('click', function() {
            skipStep('inspiration');
        });
    }

    function switchInspirationMode(mode) {
        $('.ugw-insp-mode').removeClass('ugw-insp-mode--active');
        $('.ugw-insp-mode[data-mode="' + mode + '"]').addClass('ugw-insp-mode--active');
        $('.ugw-insp-panel').removeClass('ugw-insp-panel--active');
        $('#ugw-insp-panel-' + mode).addClass('ugw-insp-panel--active');
    }

    function renderBlueprintResult(container, blueprint) {
        var html = '<div class="ugw-blueprint-result-card">';
        html += '<div class="ugw-blueprint-result-header">';
        html += '<div style="font-size:28px;">✅</div>';
        html += '<div><h3 style="font-size:18px;font-weight:700;margin-bottom:4px;">Design Blueprint Generated!</h3>';
        html += '<p style="font-size:13px;color:var(--ugw-text-secondary);">Your website will use these extracted design properties.</p></div>';
        html += '</div>';

        // Colors
        if (blueprint.colors) {
            html += '<div class="ugw-blueprint-section">';
            html += '<h4>🎨 Color Palette</h4>';
            html += '<div class="ugw-blueprint-colors">';
            var colorKeys = ['primary', 'secondary', 'accent', 'background', 'text', 'muted'];
            colorKeys.forEach(function(k) {
                if (blueprint.colors[k]) {
                    html += '<div class="ugw-blueprint-color-swatch">';
                    html += '<div class="ugw-blueprint-swatch" style="background:' + escHtml(blueprint.colors[k]) + '"></div>';
                    html += '<span>' + k + '</span>';
                    html += '</div>';
                }
            });
            html += '</div></div>';
        }

        // Typography
        if (blueprint.typography) {
            html += '<div class="ugw-blueprint-section">';
            html += '<h4>📝 Typography</h4>';
            html += '<div class="ugw-blueprint-props">';
            if (blueprint.typography.heading_font) html += '<div class="ugw-blueprint-prop"><span>Heading</span><strong>' + escHtml(blueprint.typography.heading_font) + '</strong></div>';
            if (blueprint.typography.body_font) html += '<div class="ugw-blueprint-prop"><span>Body</span><strong>' + escHtml(blueprint.typography.body_font) + '</strong></div>';
            if (blueprint.typography.heading_weight) html += '<div class="ugw-blueprint-prop"><span>Weight</span><strong>' + escHtml(blueprint.typography.heading_weight) + '</strong></div>';
            html += '</div></div>';
        }

        // Borders
        if (blueprint.borders) {
            html += '<div class="ugw-blueprint-section">';
            html += '<h4>🔲 Borders & Shapes</h4>';
            html += '<div class="ugw-blueprint-props">';
            if (blueprint.borders.radius) html += '<div class="ugw-blueprint-prop"><span>Radius</span><strong>' + escHtml(blueprint.borders.radius) + '</strong></div>';
            if (blueprint.borders.style) html += '<div class="ugw-blueprint-prop"><span>Style</span><strong>' + escHtml(blueprint.borders.style) + '</strong></div>';
            html += '</div></div>';
        }

        // Layout
        if (blueprint.layout) {
            html += '<div class="ugw-blueprint-section">';
            html += '<h4>📐 Layout</h4>';
            html += '<div class="ugw-blueprint-props">';
            if (blueprint.layout.container_width) html += '<div class="ugw-blueprint-prop"><span>Max Width</span><strong>' + escHtml(blueprint.layout.container_width) + '</strong></div>';
            if (blueprint.layout.spacing_scale) html += '<div class="ugw-blueprint-prop"><span>Spacing</span><strong>' + escHtml(blueprint.layout.spacing_scale) + '</strong></div>';
            if (blueprint.layout.grid_columns) html += '<div class="ugw-blueprint-prop"><span>Grid</span><strong>' + escHtml(blueprint.layout.grid_columns + ' cols') + '</strong></div>';
            html += '</div></div>';
        }

        // Shadows
        if (blueprint.shadows) {
            html += '<div class="ugw-blueprint-section">';
            html += '<h4>🌑 Shadows & Effects</h4>';
            html += '<div class="ugw-blueprint-props">';
            if (blueprint.shadows.card_shadow) html += '<div class="ugw-blueprint-prop"><span>Card Shadow</span><strong style="font-size:11px;">' + escHtml(blueprint.shadows.card_shadow) + '</strong></div>';
            html += '</div></div>';
        }

        html += '<div class="ugw-btn-group" style="margin-top:24px;">';
        html += '<button class="ugw-btn ugw-btn--primary ugw-btn--lg" id="ugw-blueprint-continue">Use This Blueprint & Continue →</button>';
        html += '<button class="ugw-btn ugw-btn--ghost" id="ugw-blueprint-redo">Try Another</button>';
        html += '</div>';
        html += '</div>';

        $(container).html(html).show();
    }

    function bindBlueprintEvents() {
        if (blueprintBound) return;
        blueprintBound = true;

        // Mode switching
        $(document).on('click', '.ugw-insp-mode', function() {
            switchInspirationMode($(this).data('mode'));
        });

        // --- Upload handlers ---
        $('#ugw-blueprint-browse-btn').on('click', function() {
            $('#ugw-blueprint-file-input').click();
        });

        $('#ugw-blueprint-file-input').on('change', function() {
            var file = this.files[0];
            if (!file) return;
            handleBlueprintUpload(file);
        });

        // Drag and drop
        var $dropzone = $('#ugw-blueprint-dropzone');
        $dropzone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('ugw-blueprint-upload-zone--drag');
        });
        $dropzone.on('dragleave drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('ugw-blueprint-upload-zone--drag');
        });
        $dropzone.on('drop', function(e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0 && files[0].type.startsWith('image/')) {
                handleBlueprintUpload(files[0]);
            }
        });

        // --- URL handler ---
        $('#ugw-blueprint-url-go').on('click', function() {
            var url = $('#ugw-blueprint-url-input').val().trim();
            if (!url) { toast('Please enter a URL', 'error'); return; }
            handleBlueprintUrl(url);
        });

        $('#ugw-blueprint-url-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#ugw-blueprint-url-go').click();
            }
        });

        $(document).on('click', '.ugw-blueprint-url-example', function(e) {
            e.preventDefault();
            var url = $(this).data('url');
            $('#ugw-blueprint-url-input').val(url);
            handleBlueprintUrl(url);
        });

        // Result continue buttons
        $(document).on('click', '#ugw-blueprint-continue', function() {
            nextStep();
        });

        $(document).on('click', '#ugw-blueprint-redo', function() {
            // Reset upload and URL panels
            $('#ugw-blueprint-upload-preview').hide();
            $('#ugw-blueprint-result-upload').hide();
            $('#ugw-blueprint-dropzone').show();
            $('#ugw-blueprint-url-loading').hide();
            $('#ugw-blueprint-result-url').hide();
            $('.ugw-blueprint-url-card').show();
        });
    }

    function handleBlueprintUpload(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var dataUrl = e.target.result;
            var base64 = dataUrl.split(',')[1];
            var mime = file.type || 'image/png';

            // Show preview
            $('#ugw-blueprint-dropzone').hide();
            $('#ugw-blueprint-upload-preview').show();
            $('#ugw-blueprint-preview-img').attr('src', dataUrl);
            $('#ugw-blueprint-upload-status').show();
            $('#ugw-blueprint-result-upload').hide();

            post('ugentic_wizard_generate_blueprint_from_image', {
                project_id: state.projectId,
                image_data: base64,
                mime_type: mime,
            }).done(function(res) {
                $('#ugw-blueprint-upload-status').hide();
                if (res.success) {
                    renderBlueprintResult('#ugw-blueprint-result-upload', res.data.blueprint);
                    toast('Design Blueprint generated! 🎨', 'success');
                } else {
                    toast(res.data && res.data.message ? res.data.message : 'Blueprint generation failed', 'error');
                    $('#ugw-blueprint-dropzone').show();
                    $('#ugw-blueprint-upload-preview').hide();
                }
            }).fail(function() {
                $('#ugw-blueprint-upload-status').hide();
                toast('Connection error', 'error');
                $('#ugw-blueprint-dropzone').show();
                $('#ugw-blueprint-upload-preview').hide();
            });
        };
        reader.readAsDataURL(file);
    }

    function handleBlueprintUrl(url) {
        $('.ugw-blueprint-url-card').hide();
        $('#ugw-blueprint-url-loading').show();
        $('#ugw-blueprint-result-url').hide();

        post('ugentic_wizard_generate_blueprint_from_url', {
            project_id: state.projectId,
            url: url,
        }).done(function(res) {
            $('#ugw-blueprint-url-loading').hide();
            if (res.success) {
                renderBlueprintResult('#ugw-blueprint-result-url', res.data.blueprint);
                toast('Design Blueprint generated from URL! 🎨', 'success');
            } else {
                toast(res.data && res.data.message ? res.data.message : 'Blueprint generation failed', 'error');
                $('.ugw-blueprint-url-card').show();
            }
        }).fail(function() {
            $('#ugw-blueprint-url-loading').hide();
            toast('Connection error', 'error');
            $('.ugw-blueprint-url-card').show();
        });
    }

    // ========================================================================
    // STEP 9: IMAGES
    // ========================================================================

    function initImagesStep() {
        var $grid = $('#ugw-image-grid').empty();
        var hasSlots = false;

        state.pages.forEach(function (page) {
            var slots = [];
            try { slots = JSON.parse(page.image_slots || '[]'); } catch (e) { }
            if (!slots.length) return;

            slots.forEach(function (slot) {
                hasSlots = true;
                var preview = slot.image_url
                    ? '<img src="' + escHtml(slot.image_url) + '" alt="">'
                    : '🖼️';

                $grid.append(
                    '<div class="ugw-image-slot" data-page-id="' + page.id + '" data-slot-id="' + slot.slot_id + '">' +
                    '  <div class="ugw-image-slot-preview">' + preview + '</div>' +
                    '  <div class="ugw-image-slot-info">' +
                    '    <div class="ugw-image-slot-page">' + escHtml(page.page_name) + '</div>' +
                    '    <div class="ugw-image-slot-desc">' + escHtml(slot.description) + '</div>' +
                    '    <button class="ugw-btn ugw-btn--sm ugw-btn--' + (slot.image_url ? 'ghost' : 'primary') + ' ugw-gen-slot-img">' +
                    (slot.image_url ? '🔄 Regenerate' : '✨ Generate') +
                    '    </button>' +
                    '  </div>' +
                    '</div>'
                );
            });
        });

        if (!hasSlots) {
            $grid.append('<p style="color: var(--ugw-text-secondary); padding: 40px; text-align: center;">No image placeholders found. Your pages may not require generated images.</p>');
        }
    }

    // ========================================================================
    // LAUNCH
    // ========================================================================

    function showLaunch() {
        $('.ugw-step-content').removeClass('ugw-step-content--active');
        $('#ugw-step-launch').show();

        // If pages list is empty, try fetching fresh state from server
        if (!state.pages || !state.pages.length) {
            post('ugentic_wizard_get_state', { project_id: state.projectId }).done(function (r) {
                if (r.success && r.data.pages && r.data.pages.length) {
                    state.pages = r.data.pages;
                }
                renderLaunchStats();
            }).fail(function () {
                renderLaunchStats();
            });
        } else {
            renderLaunchStats();
        }
    }

    function renderLaunchStats() {
        var pageCount = state.pages ? state.pages.filter(function (p) { return p.page_type !== 'legal'; }).length : 0;
        var stats = '<div class="ugw-launch-stat"><div class="ugw-launch-stat-value">' + pageCount + '</div><div class="ugw-launch-stat-label">Pages</div></div>';

        var imgCount = 0;
        (state.pages || []).forEach(function (p) {
            try {
                var slots = JSON.parse(p.image_slots || '[]');
                slots.forEach(function (s) { if (s.image_url) imgCount++; });
            } catch (e) { }
        });

        stats += '<div class="ugw-launch-stat"><div class="ugw-launch-stat-value">' + imgCount + '</div><div class="ugw-launch-stat-label">Images</div></div>';
        stats += '<div class="ugw-launch-stat"><div class="ugw-launch-stat-value">✓</div><div class="ugw-launch-stat-label">AI-Powered</div></div>';

        $('#ugw-launch-stats').html(stats);
    }

    // ========================================================================
    // EVENT BINDINGS
    // ========================================================================

    $(document).ready(function () {
        initStart();

        // -- Start screen --
        $('#ugw-new-project-btn').on('click', function () {
            $(this).hide();
            $('#ugw-new-project-form').show();
            $('#ugw-new-project-name').focus();
        });

        $('#ugw-new-project-cancel').on('click', function () {
            $('#ugw-new-project-form').hide();
            $('#ugw-new-project-btn').show();
        });

        $('#ugw-new-project-name').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#ugw-new-project-submit').click();
            }
        });

        $('#ugw-new-project-submit').on('click', function () {
            var name = $('#ugw-new-project-name').val().trim();
            if (!name) {
                toast('Please enter a project name', 'error');
                $('#ugw-new-project-name').focus();
                return;
            }

            var $btn = $(this).prop('disabled', true).text('Creating...');

            post('ugentic_wizard_create_project', { name: name })
                .done(function (res) {
                    if (res.success) {
                        state.projectId = res.data.project_id;
                        $('#ugw-start-screen').hide();
                        $('#ugw-wizard-layout').show();

                        // Start discovery from server-determined state
                        if (res.data.state && res.data.state.next_question) {
                            showDiscoveryQuestion(res.data.state.next_question);
                        } else {
                            // Fallback: use first question from config
                            var questions = W.discoveryQuestions || [];
                            if (questions.length > 0) {
                                showDiscoveryQuestion(questions[0]);
                            }
                        }
                    } else {
                        toast('Failed to create project', 'error');
                        $btn.prop('disabled', false).text('🚀 Create Project');
                    }
                })
                .fail(function () {
                    toast('Connection error', 'error');
                    $btn.prop('disabled', false).text('🚀 Create Project');
                });
        });

        $(document).on('click', '.ugw-project-card', function (e) {
            // Don't load project if clicking the delete button
            if ($(e.target).closest('.ugw-project-delete').length) return;
            loadProject($(this).data('id'));
        });

        // Delete project
        $(document).on('click', '.ugw-project-delete', function (e) {
            e.stopPropagation();
            var $card = $(this).closest('.ugw-project-card');
            var projectId = $(this).data('id');
            var projectName = $card.find('.ugw-project-card-name').text();
            if (!confirm('Delete "' + projectName + '"? This will remove all pages, content, and designs for this project. This cannot be undone.')) return;

            $card.css('opacity', '0.4');
            post('ugentic_wizard_delete_project', { project_id: projectId })
                .done(function (res) {
                    if (res.success) {
                        $card.slideUp(300, function () { $card.remove(); });
                        toast('Project deleted', 'success');
                        // Remove from local data
                        W.projects = (W.projects || []).filter(function (p) { return p.id !== projectId; });
                        if (!W.projects.length) $('#ugw-existing-projects').hide();
                    } else {
                        $card.css('opacity', '1');
                        toast(res.data && res.data.message ? res.data.message : 'Failed to delete', 'error');
                    }
                })
                .fail(function () {
                    $card.css('opacity', '1');
                    toast('Connection error', 'error');
                });
        });

        $('#ugw-back-to-projects').on('click', function () {
            $('#ugw-wizard-layout').hide();
            $('#ugw-start-screen').show();
        });

        $(document).on('change', '#ugw-qg-industry-key', function () {
            updateIndustryCustomField('qg');
        });

        $(document).on('change', '#ugw-guided-industry-key', function () {
            updateIndustryCustomField('guided');
        });

        // -- URL Auto-Extract --
        $('#ugw-url-extract-btn').on('click', function () {
            var url = $('#ugw-url-input').val().trim();
            if (!url) {
                toast('Please enter a URL', 'error');
                $('#ugw-url-input').focus();
                return;
            }
            if (!url.match(/^https?:\/\//)) {
                url = 'https://' + url;
                $('#ugw-url-input').val(url);
            }

            var $btn = $(this).prop('disabled', true).text('Analyzing...');
            var $status = $('#ugw-url-extract-status').show().html(
                '<div style="display:flex;align-items:center;gap:8px;">' +
                '<div class="ugw-qg-spinner" style="width:20px;height:20px;border-width:2px;margin:0;"></div>' +
                '<span>Fetching logo, colors, and content from your website...</span></div>'
            );

            post('ugentic_wizard_extract_from_url', { url: url })
                .done(function (res) {
                    if (res.success && res.data.extracted) {
                        var d = res.data.extracted;

                        // Build rich status display
                        var statusHtml = '<div style="color:var(--ugw-success);margin-bottom:8px;">✅ Website analyzed successfully!</div>';
                        statusHtml += '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';

                        // Logo preview
                        if (d.logo_url) {
                            statusHtml += '<img src="' + d.logo_url + '" alt="Logo" style="max-height:40px;max-width:120px;border-radius:4px;background:#fff;padding:4px;">';
                            state.extractedLogo = d.logo_url;
                        }

                        statusHtml += '<div><strong>' + (d.business_name || '') + '</strong> — ' + (d.industry || '') + '</div>';

                        // Color swatches
                        if (d.primary_color || d.secondary_color) {
                            statusHtml += '<div style="display:flex;gap:4px;">';
                            if (d.primary_color) statusHtml += '<div style="width:24px;height:24px;border-radius:4px;background:' + d.primary_color + ';border:1px solid rgba(0,0,0,0.1);" title="Primary"></div>';
                            if (d.secondary_color) statusHtml += '<div style="width:24px;height:24px;border-radius:4px;background:' + d.secondary_color + ';border:1px solid rgba(0,0,0,0.1);" title="Secondary"></div>';
                            statusHtml += '</div>';
                        }

                        // Page count
                        if (d.pages_found && d.pages_found.length) {
                            statusHtml += '<span style="font-size:11px;color:var(--ugw-text-secondary);">' + d.pages_found.length + ' pages detected</span>';
                        }

                        statusHtml += '</div>';
                        $status.html(statusHtml);

                        // Auto-fill Quick Generate form
                        $('#ugw-qg-name').val(d.business_name || '');
                        selectIndustryFromText('qg', d.industry || '');
                        var desc = (d.description || '');
                        if (d.services && d.services.length) {
                            desc += '\nServices: ' + d.services.join(', ');
                            $('#ugw-qg-services').val(d.services.join(', '));
                        }
                        if (d.target_audience) {
                            desc += '\nTarget audience: ' + d.target_audience;
                            $('#ugw-qg-target-audience').val(d.target_audience);
                        }
                        if (d.tagline) desc += '\nTagline: ' + d.tagline;
                        if (d.contact_info) desc += '\nContact: ' + d.contact_info;
                        $('#ugw-qg-description').val(desc.trim());
                        if (d.tone_of_voice) $('#ugw-qg-tone').val(d.tone_of_voice);

                        // Store extracted colors for canvas
                        state.extractedPrimary = d.primary_color || '#1B2A4A';
                        state.extractedSecondary = d.secondary_color || '#00D4AA';
                        state.extractedSourceUrl = res.data.source_url || '';

                        // Auto-populate page list if pages were found
                        if (d.pages_found && d.pages_found.length > 0) {
                            $('#ugw-qg-pages-section').show();
                            $('#ugw-qg-toggle-pages').text('➖ Hide custom pages');
                            var $list = $('#ugw-qg-pages-list').empty();
                            d.pages_found.forEach(function (pageName) {
                                var html = '<div class="ugw-qg-page-row" style="display:grid;grid-template-columns:1fr 2fr auto;gap:8px;align-items:start;">' +
                                    '<input type="text" class="ugw-input ugw-qg-page-name" value="' + escHtml(pageName) + '" style="font-size:13px;">' +
                                    '<textarea class="ugw-input ugw-qg-page-content" placeholder="Content (optional)" style="font-size:13px;min-height:36px;resize:vertical;"></textarea>' +
                                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-qg-remove-page" style="padding:6px 8px;font-size:14px;color:#ef4444;" title="Remove">✕</button>' +
                                    '</div>';
                                $list.append(html);
                            });
                        }

                        // Show the quick gen form
                        $('#ugw-quick-gen-btn').hide();
                        $('#ugw-landing-gen-btn').hide();
                        $('#ugw-quick-gen-form').slideDown(200);

                        toast('Website analyzed! Review and click Generate.', 'success');
                    } else {
                        $status.html('<div style="color:var(--ugw-error);">❌ ' +
                            (res.data && res.data.message ? res.data.message : 'Analysis failed') + '</div>');
                    }
                })
                .fail(function () {
                    $status.html('<div style="color:var(--ugw-error);">❌ Connection error</div>');
                })
                .always(function () {
                    $btn.prop('disabled', false).text('Analyze');
                });
        });

        $(document).on('click', '.ugw-clone-discover-more', function () { runFaithfulClone('site'); });

        function runFaithfulClone(cloneMode) {
            cloneMode = cloneMode || 'site';
            var url = $('#ugw-url-input').val().trim();
            if (!url) {
                toast('Please enter a URL to clone', 'error');
                $('#ugw-url-input').focus();
                return;
            }
            if (!url.match(/^https?:\/\//)) {
                url = 'https://' + url;
                $('#ugw-url-input').val(url);
            }

            var isSinglePage = cloneMode === 'single_page';
            var cloneAssist = $('input[name="ugw-clone-assist"]:checked').val() || 'standard';
            var aiAssisted = cloneAssist === 'ai';
            if (aiAssisted && $('#ugw-wizard').attr('data-ai-clone-ready') !== '1') {
                toast('Add an AI provider in Tahmir AI Settings before using AI-assisted import.', 'error');
                return;
            }
            var cloneSource = $('#ugw-url-clone-source').val() || 'auto';
            var cloneSourceLabel = $('#ugw-url-clone-source option:selected').text() || 'Auto source';
            var maxPages = isSinglePage ? 1 : parseInt($('#ugw-url-clone-max-pages').val(), 10) || 50;
            maxPages = isSinglePage ? 1 : Math.max(1, Math.min(100, maxPages));
            var $btn = isSinglePage ? $('#ugw-url-clone-page-btn') : $('#ugw-url-clone-btn');
            var modeLabel = isSinglePage ? 'Page' : 'Website';
            var cloneDetails = isSinglePage
                ? 'Single-page mode imports only the submitted URL. Source preset: ' + cloneSourceLabel + '.'
                : 'Website mode discovers public same-domain pages from sitemap, navigation, homepage links, and recursive links. Page limit: ' + maxPages + '. Source preset: ' + cloneSourceLabel + '.';
            cloneDetails += aiAssisted
                ? ' AI-assisted faithful mode separates shared sections, enables native blog routes, and uses the selected backend model to plan form fields, steps, and thank-you flows.'
                : ' Faithful mode keeps the captured document structure intact.';
            showClonePermissionModal(url, modeLabel, cloneDetails, aiAssisted).done(function (permission) {
                var aiDataConsent = permission && permission.aiDataConsent ? 'yes' : 'no';
                var $status = $('#ugw-url-extract-status').show().html(
                    '<div style="display:flex;align-items:center;gap:8px;">' +
                    '<div class="ugw-qg-spinner" style="width:20px;height:20px;border-width:2px;margin:0;"></div>' +
                    '<span>' + (isSinglePage ? 'Importing this exact page as editable HTML/CSS/assets...' : 'Discovering pages for review before import...') + '</span></div>'
                );

                function startImport(selectedPages) {
                    setCloneButtonLoading($btn, 'Importing...');
                    $status.html(renderCloneProgress({
                        phase: 'fetch_source',
                        progress: 7,
                        started_at: Math.round(Date.now() / 1000),
                        message: isSinglePage ? 'Fetching this page and preparing assets...' : 'Fetching selected pages and preparing assets...',
                        pages_total: isSinglePage ? 1 : (selectedPages ? selectedPages.length : maxPages)
                    }));

                    post('ugentic_wizard_clone_website', {
                        url: url,
                        permission_confirmed: 'yes',
                        crawl_scope: isSinglePage ? 'single_page' : 'full_site',
                        clone_mode: cloneMode,
                        clone_assist: cloneAssist,
                        ai_data_consent: aiDataConsent,
                        clone_source: cloneSource,
                        max_pages: maxPages,
                        selected_pages: selectedPages && selectedPages.length ? JSON.stringify(selectedPages) : ''
                    })
                        .done(function (res) {
                            var data = res && res.data ? res.data : {};
                            if (res.success && data.mode === 'faithful_clone_job') {
                                $status.html(renderCloneProgress(data));
                                pollFaithfulCloneJob(data.job_id, $status, $btn, isSinglePage);
                                return;
                            }

                            if (res.success && (data.mode === 'faithful_import' || data.mode === 'faithful_page_import')) {
                                materializeClientShellPages(data, $status).done(function (result) {
                                    $status.html(renderFaithfulCloneResult(result));
                                    toast(result.capture_failures ? 'Import finished with pages that need review.' : 'Import complete.', result.capture_failures ? 'warning' : 'success');
                                    restoreCloneButton($btn, isSinglePage);
                                });
                                return;
                            } else {
                                var warnings = data.warnings ? data.warnings : [];
                                var msg = data.message ? data.message : 'Clone failed';
                                var warningHtml = warnings.length
                                    ? '<ul style="margin:8px 0 0 18px;">' + warnings.slice(0, 8).map(function (w) { return '<li>' + escHtml(w) + '</li>'; }).join('') + '</ul>'
                                    : '';
                                $status.html('<div style="color:var(--ugw-error);">❌ ' + escHtml(msg) + warningHtml + '</div>');
                            }
                            restoreCloneButton($btn, isSinglePage);
                        })
                        .fail(function (xhr) {
                            $status.html('<div style="color:var(--ugw-error);">❌ ' + escHtml(ajaxErrorMessage(xhr, 'Connection error')) + '</div>');
                            restoreCloneButton($btn, isSinglePage);
                        });
                }

                function renderPageSelection(data) {
                    var pages = data.pages || [];
                    if (!pages.length) {
                        $status.html('<div style="color:var(--ugw-error);">No importable pages were discovered.</div>');
                        restoreCloneButton($btn, false);
                        return;
                    }

                    var warnings = data.warnings || [];
                    var rows = pages.map(function (page, idx) {
                        var label = aiAssisted ? 'AI-assisted faithful page' : 'Faithful page';
                        var checked = page.default_selected === false ? '' : ' checked';
                        var muted = page.default_selected === false ? 'opacity:.68;' : '';
                        return '<label style="display:grid;grid-template-columns:22px 1fr;gap:10px;padding:10px 0;border-top:1px solid #e2e8f0;' + muted + '">' +
                            '<input type="checkbox" class="ugw-clone-page-check" data-url="' + escAttr(page.url || '') + '"' + checked + '>' +
                            '<span style="min-width:0;">' +
                            '<strong style="display:block;font-size:13px;color:#0f172a;">' + escHtml(page.title || ('Page ' + (idx + 1))) + '</strong>' +
                            '<span style="display:block;font-size:12px;color:#64748b;word-break:break-word;">' + escHtml(page.url || '') + '</span>' +
                            '<em style="display:block;font-size:11px;color:#475569;margin-top:4px;font-style:normal;">' + escHtml(label + (page.note ? ' - ' + page.note : '')) + '</em>' +
                            '</span>' +
                            '</label>';
                    }).join('');

                    var warningHtml = warnings.length
                        ? '<div style="font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 10px;margin:10px 0;">' + escHtml(warnings.slice(0, 3).join(' ')) + '</div>'
                        : '';

                    $status.html(
                        '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;color:#0f172a;">' +
                        '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">' +
                        '<div><strong style="font-size:14px;">Choose pages to clone</strong><span style="display:block;font-size:12px;color:#64748b;">' + (aiAssisted ? 'AI-assisted mode safely separates shared sections and converts confidently detected blog routes.' : 'Selected pages import as regular faithful pages.') + '</span></div>' +
                        '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-clone-toggle-pages">Toggle all</button>' +
                        '</div>' +
                        warningHtml +
                        '<div style="max-height:360px;overflow:auto;border-bottom:1px solid #e2e8f0;">' + rows + '</div>' +
                        '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">' +
                        '<button type="button" class="ugw-btn ugw-btn--ghost" id="ugw-clone-cancel-selection">Cancel</button>' +
                        '<button type="button" class="ugw-btn ugw-btn--primary" id="ugw-start-selected-clone">Import selected pages</button>' +
                        '</div>' +
                        '</div>'
                    );

                    restoreCloneButton($btn, false);

                    $('#ugw-clone-toggle-pages').on('click', function () {
                        var $checks = $status.find('.ugw-clone-page-check');
                        var allChecked = $checks.length === $checks.filter(':checked').length;
                        $checks.prop('checked', !allChecked);
                    });
                    $('#ugw-clone-cancel-selection').on('click', function () {
                        $status.empty().hide();
                    });
                    $('#ugw-start-selected-clone').on('click', function () {
                        var selected = [];
                        $status.find('.ugw-clone-page-check:checked').each(function () {
                            var selectedUrl = $(this).attr('data-url');
                            if (selectedUrl) {
                                selected.push(selectedUrl);
                            }
                        });
                        if (!selected.length) {
                            toast('Select at least one page to import', 'error');
                            return;
                        }
                        startImport(selected);
                    });
                }

                if (isSinglePage) {
                    startImport([]);
                    return;
                }

                setCloneButtonLoading($btn, 'Discovering...');
                $status.html(renderCloneProgress({
                    phase: 'discover_pages',
                    progress: 12,
                    started_at: Math.round(Date.now() / 1000),
                    message: 'Discovering sitemap, navigation, and public same-domain pages...'
                }));
                post('ugentic_wizard_clone_website', {
                    url: url,
                    permission_confirmed: 'yes',
                    crawl_scope: 'full_site',
                    clone_mode: cloneMode,
                    clone_assist: cloneAssist,
                    ai_data_consent: aiDataConsent,
                    clone_source: cloneSource,
                    max_pages: maxPages,
                    preview_pages: 'yes'
                })
                    .done(function (res) {
                        var data = res && res.data ? res.data : {};
                        if (res.success && data.mode === 'faithful_clone_discovery') {
                            renderPageSelection(data);
                        } else {
                            var warnings = data.warnings ? data.warnings : [];
                            var msg = data.message ? data.message : 'Could not discover pages';
                            var warningHtml = warnings.length
                                ? '<ul style="margin:8px 0 0 18px;">' + warnings.slice(0, 8).map(function (w) { return '<li>' + escHtml(w) + '</li>'; }).join('') + '</ul>'
                                : '';
                            $status.html('<div style="color:var(--ugw-error);">❌ ' + escHtml(msg) + warningHtml + '</div>');
                            restoreCloneButton($btn, false);
                        }
                    })
                    .fail(function (xhr) {
                        $status.html('<div style="color:var(--ugw-error);">❌ ' + escHtml(ajaxErrorMessage(xhr, 'Connection error')) + '</div>');
                        restoreCloneButton($btn, false);
                    });
            });
        }

        $(document).on('change', 'input[name="ugw-clone-assist"]', function () {
            $('.ug-clone-engine__option').removeClass('is-selected');
            $(this).closest('.ug-clone-engine__option').addClass('is-selected');
        });

        // -- Clone Website --
        $('#ugw-url-clone-btn').on('click', function () {
            runFaithfulClone('site');
        });

        $('#ugw-url-clone-page-btn').on('click', function () {
            runFaithfulClone('single_page');
        });

        // -- Quick Generate --
        $('#ugw-quick-gen-btn').on('click', function () {
            populateIndustrySelect('#ugw-qg-industry-key');
            $(this).hide();
            $('#ugw-landing-gen-btn').hide();
            $('#ugw-qg-single-landing').prop('checked', false).trigger('change');
            $('#ugw-quick-gen-form').slideDown(200);
            $('#ugw-qg-name').focus();
            // Hide the regular project form
            $('#ugw-new-project-form').hide();
            $('#ugw-new-project-btn').show();
        });

        $('#ugw-landing-gen-btn').on('click', function () {
            populateIndustrySelect('#ugw-qg-industry-key');
            $('#ugw-quick-gen-btn').hide();
            $(this).hide();
            $('#ugw-qg-single-landing').prop('checked', true).trigger('change');
            $('#ugw-quick-gen-form').slideDown(200);
            $('#ugw-qg-name').focus();
            $('#ugw-new-project-form').hide();
            $('#ugw-new-project-btn').show();
        });

        $('#ugw-qg-cancel').on('click', function () {
            $('#ugw-quick-gen-form').slideUp(200);
            $('#ugw-quick-gen-btn').show();
            $('#ugw-landing-gen-btn').show();
            $('#ugw-qg-single-landing').prop('checked', false).trigger('change');
        });

        $('#ugw-qg-single-landing').on('change', function () {
            var single = $(this).is(':checked');
            $('#ugw-qg-custom-pages-wrap').toggle(!single);
            if (single) {
                $('#ugw-qg-pages-section').hide();
            }
        });

        $('#ugw-qg-toggle-pages').on('click', function () {
            var $section = $('#ugw-qg-pages-section');
            if ($section.is(':visible')) {
                $section.slideUp(200);
                $(this).text('➕ Add custom pages (optional)');
            } else {
                $section.slideDown(200);
                $(this).text('➖ Hide custom pages');
                if ($('#ugw-qg-pages-list').children().length === 0) {
                    addQuickGenPage();
                }
            }
        });

        $('#ugw-qg-add-page').on('click', function () {
            addQuickGenPage();
        });

        function addQuickGenPage() {
            var idx = $('#ugw-qg-pages-list').children().length;
            var html = '<div class="ugw-qg-page-row" style="display:grid;grid-template-columns:1fr 2fr auto;gap:8px;align-items:start;">' +
                '<input type="text" class="ugw-input ugw-qg-page-name" placeholder="Page name (e.g. About Us)" style="font-size:13px;">' +
                '<textarea class="ugw-input ugw-qg-page-content" placeholder="Page content/description (optional)" style="font-size:13px;min-height:36px;resize:vertical;"></textarea>' +
                '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-qg-remove-page" style="padding:6px 8px;font-size:14px;color:#ef4444;" title="Remove">✕</button>' +
                '</div>';
            $('#ugw-qg-pages-list').append(html);
        }

        $(document).on('click', '.ugw-qg-remove-page', function () {
            $(this).closest('.ugw-qg-page-row').remove();
        });

        $('#ugw-qg-submit').on('click', function () {
            var name = $('#ugw-qg-name').val().trim();
            var industryPayload = collectIndustryPayload('qg');
            var industry = industryPayload.industry || industryPayload.industry_label || '';

            if (!name) {
                toast('Please enter a business name', 'error');
                $('#ugw-qg-name').focus();
                return;
            }
            if (!industryPayload.industry_key || (industryPayload.industry_key === 'other' && !industry)) {
                toast('Please select an industry', 'error');
                $('#ugw-qg-industry-key').focus();
                return;
            }

            var description = $('#ugw-qg-description').val().trim();
            var singleLandingPage = $('#ugw-qg-single-landing').is(':checked');

            // Collect custom pages
            var customPages = [];
            if (!singleLandingPage) {
                $('.ugw-qg-page-row').each(function () {
                    var pageName = $(this).find('.ugw-qg-page-name').val().trim();
                    if (pageName) {
                        customPages.push({
                            name: pageName,
                            content: $(this).find('.ugw-qg-page-content').val().trim()
                        });
                    }
                });
            }

            var $btn = $(this).prop('disabled', true).text('Creating Studio project...');

            // Step 1: Quick setup (fast — only creates project + page list)
            post('ugentic_wizard_quick_generate', {
                name: name,
                industry: industry,
                industry_key: industryPayload.industry_key,
                industry_label: industryPayload.industry_label,
                industry_niche: industryPayload.industry_niche,
                target_audience: industryPayload.target_audience,
                services_products: industryPayload.services_products,
                business_goals: industryPayload.business_goals,
                tone_of_voice: industryPayload.tone_of_voice,
                description: description,
                custom_pages: JSON.stringify(customPages),
                single_landing_page: singleLandingPage ? '1' : '0',
                primary_color: state.extractedPrimary || '#1B2A4A',
                secondary_color: state.extractedSecondary || '#00D4AA',
                logo_url: state.extractedLogo || '',
            }).done(function (res) {
                if (res.success && res.data.mode === 'canvas') {
                    state.projectId = res.data.project_id;
                    state.canvasPages = res.data.pages;
                    if (res.data.primary_color) state.extractedPrimary = res.data.primary_color;
                    if (res.data.secondary_color) state.extractedSecondary = res.data.secondary_color;
                    if (res.data.logo_url) state.extractedLogo = res.data.logo_url;
                    state.industryOptimization = res.data.industry_optimization || industryPayload;
                    toast('Project created! Opening AI Design Studio...', 'success');

                    // Switch to canvas view
                    $('#ugw-start-screen').hide();
                    openCanvas(name, res.data.pages);
                } else {
                    toast(res.data && res.data.message ? res.data.message : 'Setup failed', 'error');
                    $btn.prop('disabled', false).text('Open Design Studio');
                }
            }).fail(function () {
                toast('Connection error', 'error');
                $btn.prop('disabled', false).text('Open Design Studio');
            });
        });

        // ═══════════════════════════════════════════════════════════════
        // AI DESIGN STUDIO — staged Figma-like generation canvas
        // ═══════════════════════════════════════════════════════════════

        var STUDIO_STAGES = ['brief', 'outline', 'full_content', 'website_draft', 'high_fidelity', 'animations'];
        var STUDIO_LABELS = {
            brief: 'Brief',
            outline: 'Outline',
            full_content: 'Full Content',
            references: 'References',
            wireframe: 'Wireframe',
            website_draft: 'Design Concepts',
            style_system: 'Style System',
            high_fidelity: 'High Fidelity',
            animations: 'Animations'
        };
        var STUDIO_GUIDANCE = {
            brief: 'Upload the brand logo, then define the page goal, audience, conversion, offer angle, visual direction, and hard avoid rules.',
            outline: 'Choose a section count and generate only the editable structure first. No full paragraphs yet.',
            full_content: 'Expand the approved outline into editable page copy while preserving section order and CTAs.',
            references: 'Legacy reference board.',
            wireframe: 'Legacy wireframe.',
            website_draft: 'Generate 10 full-page design concepts with all page content. Select one to lock the site-wide style.',
            style_system: 'Generate the color, typography, spacing, image, icon, and motion system before compiling final design.',
            high_fidelity: 'Convert the approved mockup image into exact HTML/CSS and visually match it as closely as possible.',
            animations: 'Choose the animation intensity and apply a consistent motion system after High Fidelity.'
        };

        openCanvas = function(projectName, pages) {
            state.canvasPages = pages || [];
            state.canvasGenerated = {};
            state.canvasCurrentIndex = 0;
            state.studioActiveStage = 'brief';
            state.studioViewport = state.studioViewport || 'desktop';

            $('#ugw-canvas-title').text(projectName);
            if (state.extractedPrimary) $('#ugw-token-primary').val(state.extractedPrimary);
            if (state.extractedSecondary) $('#ugw-token-accent').val(state.extractedSecondary);

            $('#ugw-wizard-layout').hide();
            $('#ugw-start-screen').hide();
            $('body').addClass('ugw-studio-fullscreen-mode');
            $('#ugw-canvas-view').show();
            $('#ugw-canvas-grid').html('<div class="ugw-studio-loading"><div class="ugw-card-spinner"></div><span>Loading AI Design Studio...</span></div>');
            updateCanvasProgress();

            post('ugentic_wizard_design_studio_v2_get_state', {
                project_id: state.projectId
            }).done(function (res) {
                if (!res.success) {
                    $('#ugw-canvas-grid').html('<div class="ugw-studio-empty">Could not load Design Studio state.</div>');
                    toast(res.data && res.data.message ? res.data.message : 'Could not load Design Studio', 'error');
                    return;
                }

                var data = res.data || {};
                state.canvasPages = data.pages || state.canvasPages || [];
                state.designStudio = data.design_studio_v2 || data.design_studio || {};
                state.designTokens = data.design_tokens || state.designTokens || {};
                state.canvasHeader = (data.canvas_state && data.canvas_state.global_header_html) || state.canvasHeader || '';
                state.canvasFooter = (data.canvas_state && data.canvas_state.global_footer_html) || state.canvasFooter || '';

                if (state.designTokens.colors) {
                    state.extractedPrimary = state.designTokens.colors.primary || state.extractedPrimary;
                    state.extractedSecondary = state.designTokens.colors.secondary || state.extractedSecondary;
                    $('#ugw-token-primary').val(state.extractedPrimary || '#1B2A4A');
                    $('#ugw-token-accent').val(state.extractedSecondary || '#00D4AA');
                }

                var preferredPageId = state.designStudio.current_page_id || 0;
                var preferredIdx = findPageIndexById(preferredPageId);
                state.canvasCurrentIndex = preferredIdx >= 0 ? preferredIdx : firstUnapprovedPageIndex();
                state.studioActiveStage = resolveActiveStage(getCurrentCanvasPage());
                renderStudioCanvas();
            }).fail(function () {
                $('#ugw-canvas-grid').html('<div class="ugw-studio-empty">Connection error while loading the Studio canvas.</div>');
                toast('Connection error while loading Design Studio', 'error');
            });
        };

        function findPageIndexById(pageId) {
            pageId = parseInt(pageId || 0);
            for (var i = 0; i < (state.canvasPages || []).length; i++) {
                if (parseInt(state.canvasPages[i].id) === pageId) return i;
            }
            return -1;
        }

        function firstUnapprovedPageIndex() {
            for (var i = 0; i < (state.canvasPages || []).length; i++) {
                if (!state.canvasPages[i].approved) return i;
            }
            return 0;
        }

        function allCanvasPagesApproved() {
            return (state.canvasPages || []).length > 0 && (state.canvasPages || []).every(function (p) {
                return p.approved || isStageApproved(getStudioPage(p), 'animations');
            });
        }

        function getCurrentCanvasPage() {
            return (state.canvasPages || [])[state.canvasCurrentIndex] || null;
        }

        function getStudioPage(page) {
            if (!page) return {};
            page.studio = page.studio || {};
            page.studio.stage_status = page.studio.stage_status || {};
            page.studio.approved_stages = page.studio.approved_stages || [];
            page.studio.user_notes = page.studio.user_notes || {};
            return page.studio;
        }

        function resolveActiveStage(page) {
            var sp = getStudioPage(page);
            var approved = sp.approved_stages || [];
            var preferred = sp.stage || state.studioActiveStage || 'brief';
            if (preferred === 'approval') preferred = 'animations';
            if (preferred === 'content') preferred = stageHasOutput(sp, 'full_content') ? 'full_content' : 'outline';

            if (preferred && STUDIO_STAGES.indexOf(preferred) >= 0 && approved.indexOf(preferred) < 0) {
                var previous = previousStage(preferred);
                if (!previous || approved.indexOf(previous) >= 0) {
                    return preferred;
                }
            }
            for (var i = 0; i < STUDIO_STAGES.length; i++) {
                if (approved.indexOf(STUDIO_STAGES[i]) < 0) return STUDIO_STAGES[i];
            }
            return 'animations';
        }

        function stageHasOutput(sp, stage) {
            if (!sp) return false;
            if (stage === 'brief') return !!(sp.brief && sp.brief.data);
            if (stage === 'outline') return !!((sp.outline && sp.outline.data && sp.outline.data.sections && sp.outline.data.sections.length) || (sp.content && sp.content.data && sp.content.data.sections && sp.content.data.sections.length));
            if (stage === 'full_content') return !!((sp.full_content && sp.full_content.data && sp.full_content.data.sections && sp.full_content.data.sections.length) || (sp.content && sp.content.data && sp.content.data.content_mode === 'full'));
            if (stage === 'references') return !!(getReferenceBoard(sp).references || []).length;
            if (stage === 'wireframe') return !!(sp.wireframe && (sp.wireframe.html || sp.wireframe.data));
            if (stage === 'website_draft') return !!(sp.website_draft && (sp.website_draft.mockup_image_url || sp.website_draft.mockup_image_attachment_id || sp.website_draft.html || (sp.website_draft.design_concepts && sp.website_draft.design_concepts.length)));
            if (stage === 'style_system') return !!(sp.style_system && sp.style_system.data);
            if (stage === 'high_fidelity') {
                var html = (sp.high_fidelity && sp.high_fidelity.html) || '';
                return !!(sp.high_fidelity && sp.high_fidelity.compiler_version) || /data-ugentic-studio-version=["']2["']/i.test(html);
            }
            if (stage === 'animations') return !!(sp.animations && (sp.animations.html || sp.animations.animation_level || sp.animations.motion_system));
            return false;
        }

        function getContentSectionCount(page) {
            var sp = getStudioPage(page);
            var data = (sp.full_content && sp.full_content.data) || (sp.outline && sp.outline.data) || (sp.content && sp.content.data) || {};
            var count = parseInt(data.target_section_count || 0);
            if (!count && data.sections && data.sections.length) count = data.sections.length;
            return Math.max(1, Math.min(12, count || 6));
        }

        function getInspectorSectionCount(page) {
            var raw = parseInt($('#ugw-studio-section-count').val(), 10);
            if (!raw || isNaN(raw)) raw = getContentSectionCount(page);
            return Math.max(1, Math.min(12, raw));
        }

        function getContentMode(page) {
            var sp = getStudioPage(page);
            if (sp.full_content && sp.full_content.data) return 'full';
            if (sp.outline && sp.outline.data) return 'outline';
            var data = sp.content && sp.content.data ? sp.content.data : {};
            return data.content_mode === 'outline' ? 'outline' : (data.content_mode === 'full' ? 'full' : '');
        }

        function contentStageHasFullCopy(sp) {
            var data = sp && sp.full_content && sp.full_content.data ? sp.full_content.data : (sp && sp.content && sp.content.data ? sp.content.data : {});
            if (!data.sections || !data.sections.length) return false;
            if (data.content_mode === 'outline') return false;
            if (data.full_content_generated === false) return false;
            return true;
        }

        function isStageApproved(sp, stage) {
            return (sp.approved_stages || []).indexOf(stage) >= 0;
        }

        function getStageStatus(sp, stage) {
            var stored = (sp.stage_status && sp.stage_status[stage]) || '';
            if (stageHasOutput(sp, stage) && stored !== 'generating' && stored !== 'failed') {
                return 'complete';
            }
            return stored || 'pending';
        }

        function renderStudioCanvas() {
            var page = getCurrentCanvasPage();
            if (!page) {
                $('#ugw-canvas-grid').html('<div class="ugw-studio-empty">No pages are available for Design Studio.</div>');
                updateCanvasProgress();
                return;
            }

            var zoom = getStudioZoom();
            var html = '<div class="ugw-studio-shell ugw-studio-shell--visily">' +
                renderStudioSidebar() +
                '<main class="ugw-studio-main">' +
                    renderStudioTopControls(page, zoom) +
                    '<section class="ugw-studio-canvas-plane" aria-label="AI Design Studio canvas">' +
                        '<div class="ugw-studio-board-strip" style="--ugw-studio-zoom:' + escAttr(String(zoom)) + '">' +
                            renderStudioBoards() +
                        '</div>' +
                    '</section>' +
                    renderStudioCommandBar(page, state.studioActiveStage) +
                '</main>' +
                renderStudioInspector(page, state.studioActiveStage) +
                '</div>';

            $('#ugw-canvas-grid').html(html);
            $('.ugw-studio-viewport').removeClass('is-active');
            $('.ugw-studio-viewport[data-viewport="' + (state.studioViewport || 'desktop') + '"]').addClass('is-active');
            hydrateIframes();
            startConceptLoaderProgress();
            updateCanvasProgress();
        }

        function startConceptLoaderProgress() {
            $('.ugw-concept-loader').each(function () {
                var el = this;
                if (el.dataset.timerStarted) return;
                el.dataset.timerStarted = '1';
                var bar = el.querySelector('.ugw-concept-progress i');
                var labels = el.querySelectorAll('.ugw-concept-progress-steps em');
                var progress = 8;
                var tick = setInterval(function () {
                    if (!document.body.contains(el)) {
                        clearInterval(tick);
                        return;
                    }
                    progress = Math.min(94, progress + Math.max(1, Math.round((96 - progress) / 12)));
                    if (bar) bar.style.width = progress + '%';
                    Array.prototype.forEach.call(labels, function (label, idx) {
                        label.classList.toggle('is-active', progress >= [8, 28, 58, 82][idx]);
                    });
                }, 900);
            });
        }

        refreshSlot = function() {
            renderStudioCanvas();
        };

        function getStudioZoom() {
            var zoom = parseFloat(state.studioZoom || 0.52);
            if (isNaN(zoom)) zoom = 0.52;
            return Math.max(0.25, Math.min(1.25, zoom));
        }

        function renderStudioTopControls(page, zoom) {
            var pct = Math.round(zoom * 100);
            var pageCount = (state.canvasPages || []).length;
            var activeNumber = Math.max(1, (state.canvasCurrentIndex || 0) + 1);
            return '<div class="ugw-studio-top-float">' +
                '<div class="ugw-studio-tool-cluster" aria-label="Canvas tools">' +
                    '<button type="button" class="ugw-studio-tool is-active" title="Select">↖</button>' +
                    '<button type="button" class="ugw-studio-tool" title="Pan">✥</button>' +
                    '<button type="button" class="ugw-studio-tool ugw-studio-zoom-fit" title="Fit pages">Fit</button>' +
                '</div>' +
                renderStudioTabs(page) +
                '<span class="ugw-studio-page-count">' + activeNumber + ' / ' + pageCount + ' pages</span>' +
                '<div class="ugw-studio-tool-cluster" aria-label="Zoom controls">' +
                    '<button type="button" class="ugw-studio-tool ugw-studio-zoom" data-zoom="-0.1" title="Zoom out">−</button>' +
                    '<span class="ugw-studio-zoom-readout">' + escHtml(String(pct)) + '%</span>' +
                    '<button type="button" class="ugw-studio-tool ugw-studio-zoom" data-zoom="0.1" title="Zoom in">+</button>' +
                '</div>' +
                '</div>';
        }

        function renderStudioBoards() {
            var html = '';
            (state.canvasPages || []).forEach(function (page, idx) {
                var stage = idx === state.canvasCurrentIndex
                    ? (state.studioActiveStage || resolveActiveStage(page))
                    : resolveActiveStage(page);
                html += renderStudioArtboard(page, stage, idx);
            });
            return html;
        }

        function renderStudioSidebar() {
            var currentPage = getCurrentCanvasPage();
            var html = '<aside class="ugw-studio-sidebar"><div class="ugw-studio-sidebar-head"><span>Pages</span><strong>' + (state.canvasPages || []).length + '</strong></div><div class="ugw-studio-page-list">';
            (state.canvasPages || []).forEach(function (page, idx) {
                var sp = getStudioPage(page);
                var active = idx === state.canvasCurrentIndex ? ' is-active' : '';
                var approved = page.approved || isStageApproved(sp, 'animations');
                var doneCount = (sp.approved_stages || []).filter(function (s) { return STUDIO_STAGES.indexOf(s) >= 0; }).length;
                html += '<button type="button" class="ugw-studio-page-btn' + active + '" data-idx="' + idx + '">' +
                    '<span class="ugw-studio-page-index">' + (idx + 1) + '</span>' +
                    '<span class="ugw-studio-page-meta"><strong>' + escHtml(page.page_name) + '</strong><em>' + doneCount + ' / ' + STUDIO_STAGES.length + ' stages</em></span>' +
                    '<span class="ugw-studio-page-state ' + (approved ? 'is-approved' : '') + '">' + (approved ? 'Approved' : 'Open') + '</span>' +
                    '</button>';
            });
            html += '</div>' +
                renderStudioLayerRail(currentPage) +
                renderStudioAssetRail(currentPage) +
                renderStudioThemeRail() +
                '</aside>';
            return html;
        }

        function renderStudioLayerRail(page) {
            var sp = getStudioPage(page);
            var content = (sp.full_content && sp.full_content.data) || (sp.outline && sp.outline.data) || (sp.content && sp.content.data) || {};
            var sections = Array.isArray(content.sections) ? content.sections : [];
            var html = '<div class="ugw-studio-rail-group">' +
                '<div class="ugw-studio-rail-title"><span>Layers</span><strong>' + sections.length + '</strong></div>';
            if (!sections.length) {
                html += '<div class="ugw-studio-rail-empty">Generate the content outline to populate editable sections.</div>';
            } else {
                html += '<div class="ugw-studio-layer-list">';
                sections.slice(0, 10).forEach(function (section, idx) {
                    html += '<button type="button" class="ugw-studio-layer-item" data-section-idx="' + idx + '">' +
                        '<span>' + (idx + 1) + '</span>' +
                        '<strong>' + escHtml(section.heading || section.type || ('Section ' + (idx + 1))) + '</strong>' +
                        '<em>' + escHtml(section.type || 'section') + '</em>' +
                        '</button>';
                });
                html += '</div>';
            }
            html += '</div>';
            return html;
        }

        function getStudioDesignContract(page) {
            var sp = getStudioPage(page);
            return (sp.high_fidelity && sp.high_fidelity.design_contract) || sp.design_contract || {};
        }

        function getReferenceBoard(sp) {
            sp = sp || getStudioPage(getCurrentCanvasPage());
            var board = (sp.references && sp.references.reference_board) || sp.reference_board || {};
            board.references = Array.isArray(board.references) ? board.references : [];
            board.liked_reference_ids = Array.isArray(board.liked_reference_ids) ? board.liked_reference_ids : [];
            board.disliked_reference_ids = Array.isArray(board.disliked_reference_ids) ? board.disliked_reference_ids : [];
            board.pinned_reference_ids = Array.isArray(board.pinned_reference_ids) ? board.pinned_reference_ids : [];
            board.selected_references = Array.isArray(board.selected_references) ? board.selected_references : [];
            board.min_required = parseInt(board.min_required || 3, 10);
            (board.references || []).forEach(function (ref) {
                if (!ref || !ref.id) return;
                var kind = String(ref.kind || '');
                var queryUsed = String(ref.query_used || '').toLowerCase();
                if (queryUsed === 'user supplied' || kind === 'user_site' || kind === 'user_reference' || kind === 'design_image') {
                    if (board.liked_reference_ids.indexOf(ref.id) < 0) board.liked_reference_ids.push(ref.id);
                    board.disliked_reference_ids = board.disliked_reference_ids.filter(function (id) { return id !== ref.id; });
                }
            });
            return board;
        }

        function referenceSelectionCount(board) {
            board = board || getReferenceBoard();
            var visible = {};
            (board.references || []).forEach(function (ref) {
                if (ref && ref.id) visible[ref.id] = true;
            });
            var selected = {};
            (board.liked_reference_ids || []).forEach(function (id) { if (visible[id]) selected[id] = true; });
            (board.pinned_reference_ids || []).forEach(function (id) { if (visible[id]) selected[id] = true; });
            (board.references || []).forEach(function (ref) {
                if (!ref || !ref.id) return;
                var kind = String(ref.kind || '');
                var queryUsed = String(ref.query_used || '').toLowerCase();
                if (queryUsed === 'user supplied' || kind === 'user_site' || kind === 'user_reference' || kind === 'design_image') {
                    selected[ref.id] = true;
                }
            });
            return Object.keys(selected).length;
        }

        function referencesReady(board) {
            board = board || getReferenceBoard();
            return !!board.skipped || referenceSelectionCount(board) >= (parseInt(board.min_required || 3, 10) || 3);
        }

        function renderStudioAssetRail(page) {
            var sp = getStudioPage(page);
            var contract = getStudioDesignContract(page);
            var imageSlots = [];
            if (sp.high_fidelity && Array.isArray(sp.high_fidelity.image_slots)) {
                imageSlots = sp.high_fidelity.image_slots;
            } else if (Array.isArray(contract.image_slots)) {
                imageSlots = contract.image_slots;
            }
            var iconSlots = Array.isArray(contract.icon_slots) ? contract.icon_slots : [];
            var assetLog = (sp.high_fidelity && sp.high_fidelity.asset_log) || {};
            var downloaded = Array.isArray(assetLog.downloaded) ? assetLog.downloaded.length : 0;
            var failed = Array.isArray(assetLog.failed) ? assetLog.failed.length : 0;
            var html = '<div class="ugw-studio-rail-group">' +
                '<div class="ugw-studio-rail-title"><span>Images</span><strong>' + imageSlots.length + '</strong></div>';
            if (!imageSlots.length) {
                html += '<div class="ugw-studio-rail-empty">High Fidelity will assign royalty-free image slots.</div>';
            } else {
                html += '<div class="ugw-studio-asset-grid">';
                imageSlots.slice(0, 6).forEach(function (slot, idx) {
                    var label = slot.label || slot.alt || slot.section || ('Image ' + (idx + 1));
                    html += '<span class="ugw-studio-asset-pill is-image">' + escHtml(label) + '</span>';
                });
                html += '</div>';
            }
            html += '<div class="ugw-studio-asset-status"><span>' + downloaded + ' localized</span><span>' + failed + ' warnings</span></div>';
            html += '<div class="ugw-studio-rail-title"><span>Icons</span><strong>' + iconSlots.length + '</strong></div>';
            if (!iconSlots.length) {
                html += '<div class="ugw-studio-rail-empty">Lucide SVG icons are selected during design.</div>';
            } else {
                html += '<div class="ugw-studio-asset-grid">';
                iconSlots.slice(0, 10).forEach(function (slot) {
                    var name = typeof slot === 'string' ? slot : (slot.icon || slot.name || 'icon');
                    html += '<span class="ugw-studio-asset-pill">' + escHtml(name) + '</span>';
                });
                html += '</div>';
            }
            html += '</div>';
            return html;
        }

        function renderStudioThemeRail() {
            var colors = (state.designTokens && state.designTokens.colors) || {};
            var primary = colors.primary || state.extractedPrimary || $('#ugw-token-primary').val() || '#1B2A4A';
            var accent = colors.secondary || colors.accent || state.extractedSecondary || $('#ugw-token-accent').val() || '#00D4AA';
            var html = '<div class="ugw-studio-rail-group">' +
                '<div class="ugw-studio-rail-title"><span>Theme</span><strong>Live</strong></div>' +
                '<div class="ugw-studio-theme-swatches">' +
                    renderStudioSwatch(primary, 'Primary') +
                    renderStudioSwatch(accent, 'Accent') +
                    renderStudioSwatch('#0f172a', 'Ink') +
                    renderStudioSwatch('#f8fafc', 'Surface') +
                '</div>' +
                '<div class="ugw-studio-theme-note">Colors update future stages and publish output.</div>' +
                '</div>';
            return html;
        }

        function renderStudioSwatch(color, label) {
            return '<span class="ugw-studio-theme-swatch"><i style="background:' + escAttr(color) + '"></i>' + escHtml(label) + '</span>';
        }

        function renderStudioTabs(page) {
            var sp = getStudioPage(page);
            var html = '<div class="ugw-studio-tabs">';
            STUDIO_STAGES.forEach(function (stage) {
                var status = getStageStatus(sp, stage);
                var active = stage === state.studioActiveStage ? ' is-active' : '';
                var approved = isStageApproved(sp, stage) ? ' is-approved' : '';
                html += '<button type="button" class="ugw-studio-tab' + active + approved + '" data-stage="' + stage + '">' +
                    '<span class="ugw-studio-tab-dot is-' + escAttr(status) + '"></span>' +
                    '<span>' + STUDIO_LABELS[stage] + '</span>' +
                    '</button>';
            });
            html += '</div>';
            return html;
        }

        function renderStudioArtboard(page, stage, idx) {
            var sp = getStudioPage(page);
            var status = getStageStatus(sp, stage);
            var title = STUDIO_LABELS[stage] || stage;
            var viewport = state.studioViewport || 'desktop';
            var score = stage === 'high_fidelity' && sp.high_fidelity ? sp.high_fidelity.quality_score : null;
            var scoreHtml = score !== null && score !== undefined && score !== ''
                ? '<span class="ugw-studio-score">Quality ' + escHtml(String(score)) + '/10</span>'
                : '';
            if (stage === 'high_fidelity' && sp.high_fidelity && sp.high_fidelity.preview_label) {
                scoreHtml += '<span class="ugw-studio-score is-preview">' + escHtml(sp.high_fidelity.preview_label) + '</span>';
            }
            var active = idx === state.canvasCurrentIndex ? ' is-active' : '';

            var body = '';
            if (status === 'generating') {
                body = stage === 'website_draft'
                    ? renderDesignConceptLoader(sp)
                    : '<div class="ugw-studio-generating"><div class="ugw-card-spinner"></div><strong>Generating ' + escHtml(title) + '...</strong><span>Studio Quality uses industry playbooks and multiple AI checks, so this can take a little longer.</span></div>';
            } else if (status === 'failed') {
                var lastError = sp.last_error ? '<span class="ugw-studio-error-detail">' + escHtml(sp.last_error) + '</span>' : '';
                body = '<div class="ugw-studio-empty is-error"><strong>Generation failed.</strong><span>Add notes and try again.</span>' + lastError + '</div>';
            } else if (stage === 'brief') {
                body = renderBriefStage(sp.brief || {});
            } else if (stage === 'outline') {
                body = renderContentStage(sp.outline || sp.content || {});
            } else if (stage === 'full_content') {
                body = renderContentStage(sp.full_content || sp.content || {});
            } else if (stage === 'references') {
                body = renderReferenceStage(sp);
            } else if (stage === 'wireframe') {
                body = sp.wireframe && sp.wireframe.html ? renderHtmlStage(page, stage, sp.wireframe.html) : renderWireframeStage(sp.wireframe || {});
            } else if (stage === 'website_draft') {
                body = renderMockupImageStage(page, sp.website_draft || {});
            } else if (stage === 'style_system') {
                body = renderStyleSystemStage(sp.style_system || {});
            } else if (stage === 'high_fidelity') {
                body = renderHtmlStage(page, stage, (sp.high_fidelity && (sp.high_fidelity.preview_html || sp.high_fidelity.html)) || '');
            } else if (stage === 'animations') {
                body = renderHtmlStage(page, stage, (sp.animations && sp.animations.html) || (sp.high_fidelity && sp.high_fidelity.html) || '');
            }

            return '<article class="ugw-studio-artboard-node ugw-studio-artboard-node--' + escAttr(viewport) + active + '" data-idx="' + escAttr(String(idx || 0)) + '">' +
                '<div class="ugw-studio-artboard-head">' +
                    '<div><span class="ugw-studio-kicker">' + escHtml(page.page_name) + '</span><h3>' + escHtml(title) + '</h3></div>' +
                    scoreHtml +
                '</div>' +
                '<div class="ugw-studio-artboard ugw-studio-artboard--' + escAttr(viewport) + '">' + body + '</div>' +
                '</article>';
        }

        function renderStudioCommandBar(page, stage) {
            var sp = getStudioPage(page);
            var notes = (sp.user_notes && sp.user_notes[stage]) || '';
            var placeholder = stage === 'website_draft'
                ? 'Add art direction for the 10 generated concepts: bold, editorial, product-led, darker, more premium...'
                : (stage === 'animations'
                    ? 'Describe animation preferences: basic, balanced, advanced, premium, or page-specific motion notes...'
                    : 'Type what to edit on the selected page and stage...');
            return '<div class="ugw-studio-command-bar">' +
                '<span class="ugw-studio-command-chip">AI</span>' +
                '<textarea class="ugw-input ugw-studio-notes" id="ugw-studio-notes" placeholder="' + escAttr(placeholder) + '">' + escHtml(notes) + '</textarea>' +
                '<span class="ugw-studio-command-stage">' + escHtml(page.page_name) + ' · ' + escHtml(STUDIO_LABELS[stage] || stage) + '</span>' +
                '</div>';
        }

        function getStudioLogoAsset(page) {
            var sp = getStudioPage(page);
            var assets = getStudioMediaAssets(page);
            var logo = sp.brand_logo_asset || null;
            if (!logo) {
                assets.some(function (asset) {
                    if (asset && (asset.kind === 'logo' || asset.role === 'logo' || asset.role === 'brand')) {
                        logo = asset;
                        return true;
                    }
                    return false;
                });
            }
            if (logo && logo.url) {
                sp.brand_logo_asset = logo;
            }
            return logo || null;
        }

        function hasStudioLogoAsset(page) {
            var logo = getStudioLogoAsset(page);
            return !!(logo && (logo.attachment_id || logo.url));
        }

        function renderStudioLogoInput(page) {
            var logo = getStudioLogoAsset(page);
            return '<div class="ugw-studio-logo-input' + (logo ? ' has-logo' : '') + '">' +
                '<div class="ugw-studio-media-head"><strong>Brand Logo</strong><button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-studio-add-logo">' + (logo ? 'Change Logo' : 'Add Logo') + '</button></div>' +
                (logo
                    ? '<div class="ugw-studio-logo-preview"><img src="' + escAttr(logo.url || '') + '" alt="' + escAttr(logo.title || 'Brand logo') + '"><span>' + escHtml(logo.title || 'Brand logo') + '</span><button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-remove-logo">Remove</button></div>'
                    : '<p>Add the official logo before generating or approving the Brief. It will be carried into every design concept and final page.</p>') +
                '</div>';
        }

        function renderBriefStage(stageData) {
            var data = stageData.data || {};
            if (!Object.keys(data).length) {
                return '<div class="ugw-studio-empty"><strong>Brief is ready to generate.</strong><span>Add the logo in the inspector, then generate the strategic brief before outlining sections.</span></div>';
            }
            var includes = Array.isArray(data.must_include) ? data.must_include : [];
            var avoid = Array.isArray(data.avoid) ? data.avoid : [];
            return '<div class="ugw-studio-brief-editor">' +
                '<label>Goal<textarea class="ugw-input ugw-brief-field" data-brief-field="goal">' + escHtml(data.goal || '') + '</textarea></label>' +
                '<label>Audience<textarea class="ugw-input ugw-brief-field" data-brief-field="audience">' + escHtml(data.audience || '') + '</textarea></label>' +
                '<label>Conversion<input class="ugw-input ugw-brief-field" data-brief-field="conversion" value="' + escAttr(data.conversion || '') + '"></label>' +
                '<label>Offer Angle<textarea class="ugw-input ugw-brief-field" data-brief-field="offer_angle">' + escHtml(data.offer_angle || '') + '</textarea></label>' +
                '<label>Visual Direction<textarea class="ugw-input ugw-brief-field" data-brief-field="visual_direction">' + escHtml(data.visual_direction || '') + '</textarea></label>' +
                '<label>Must Include<textarea class="ugw-input ugw-brief-field" data-brief-field="must_include">' + escHtml(includes.join('\\n')) + '</textarea></label>' +
                '<label>Avoid<textarea class="ugw-input ugw-brief-field" data-brief-field="avoid">' + escHtml(avoid.join('\\n')) + '</textarea></label>' +
                '</div>';
        }

        function renderDesignConceptLoader(sp) {
            var locked = !!(sp && sp.global_design_style_source && sp.global_design_style_source.mockup_image_url);
            return '<div class="ugw-concept-loader" data-progress="0">' +
                '<div class="ugw-concept-orbit"><span></span><span></span><span></span></div>' +
                '<strong>' + (locked ? 'Composing a page in the selected visual system' : 'Composing your design gallery') + '</strong>' +
                '<span>' + (locked ? 'The approved visual direction is being translated into a consistent page concept.' : 'Several complete directions are being prepared with the approved content and brand assets.') + '</span>' +
                '<div class="ugw-concept-progress"><i style="width:8%"></i></div>' +
                '<div class="ugw-concept-progress-steps">' +
                    '<em>Brand alignment</em><em>Visual exploration</em><em>Composition pass</em><em>Gallery preparation</em>' +
                '</div>' +
            '</div>';
        }


        function renderStyleSystemStage(stageData) {
            var data = stageData.data || {};
            if (!Object.keys(data).length) {
                return '<div class="ugw-studio-empty"><strong>Style System is ready to generate.</strong><span>Studio will lock colors, typography, image treatment, icons, spacing, and motion before compiling High Fidelity.</span></div>';
            }
            var palette = data.palette || {};
            var motion = Array.isArray(data.motion) ? data.motion : [];
            var html = '<div class="ugw-style-system-preview">' +
                '<div class="ugw-style-swatches">';
            ['background', 'surface', 'ink', 'primary', 'accent', 'contrast'].forEach(function (key) {
                var value = palette[key] || '#ffffff';
                html += '<label><span style="background:' + escAttr(value) + '"></span>' + escHtml(key) + '<input class="ugw-input ugw-style-field" data-style-path="palette.' + escAttr(key) + '" value="' + escAttr(value) + '"></label>';
            });
            html += '</div>' +
                '<label>Display Font<input class="ugw-input ugw-style-field" data-style-path="typography.display" value="' + escAttr((data.typography && data.typography.display) || '') + '"></label>' +
                '<label>Body Font<input class="ugw-input ugw-style-field" data-style-path="typography.body" value="' + escAttr((data.typography && data.typography.body) || '') + '"></label>' +
                '<label>Image Treatment<textarea class="ugw-input ugw-style-field" data-style-path="image_treatment">' + escHtml(data.image_treatment || '') + '</textarea></label>' +
                '<label>Icon Treatment<textarea class="ugw-input ugw-style-field" data-style-path="icon_treatment">' + escHtml(data.icon_treatment || '') + '</textarea></label>' +
                '<label>Motion<textarea class="ugw-input ugw-style-field" data-style-path="motion">' + escHtml(motion.join('\\n')) + '</textarea></label>' +
                '</div>';
            return html;
        }

        function renderContentStage(stageData) {
            var data = stageData.data || {};
            if (!data.sections || !data.sections.length) {
                return '<div class="ugw-studio-empty"><strong>Content outline is ready to generate.</strong><span>Choose the section count in the inspector, add direction, then generate the outline first.</span></div>';
            }

            var mode = data.content_mode === 'outline' ? 'outline' : 'full';
            var modeLabel = mode === 'outline' ? 'Outline' : 'Full Content';
            var bodyLabel = mode === 'outline' ? 'Body Brief' : 'Body';
            var html = '<div class="ugw-studio-content-preview ugw-studio-content-editor" data-content-mode="' + escAttr(mode) + '">';
            html += '<div class="ugw-content-mode-row"><span class="ugw-content-mode-badge">' + escHtml(modeLabel) + '</span><span>' + escHtml(String(data.sections.length)) + ' sections</span></div>';
            html += '<div class="ugw-content-editor-top">' +
                '<label>Page Goal<textarea class="ugw-input ugw-content-field" data-content-field="page_goal">' + escHtml(data.page_goal || '') + '</textarea></label>' +
                '<label>Narrative Angle<textarea class="ugw-input ugw-content-field" data-content-field="narrative_angle">' + escHtml(data.narrative_angle || '') + '</textarea></label>' +
                '<label>Primary CTA<input class="ugw-input ugw-content-field" data-content-field="primary_cta" value="' + escAttr(data.primary_cta || '') + '"></label>' +
                '<label>Secondary CTA<input class="ugw-input ugw-content-field" data-content-field="secondary_cta" value="' + escAttr(data.secondary_cta || '') + '"></label>' +
                '</div>';
            html += '<div class="ugw-studio-content-toolbar"><strong>Editable Sections</strong><button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-content-add-section">Add Section</button></div>';
            html += '<div class="ugw-studio-section-grid ugw-content-sections">';
            data.sections.forEach(function (section, idx) {
                html += '<article class="ugw-studio-section-card ugw-content-section" data-section-idx="' + idx + '">' +
                    '<span class="ugw-studio-section-num">' + (idx + 1) + '</span>' +
                    '<label>Type<input class="ugw-input ugw-content-section-field" data-section-field="type" value="' + escAttr(section.type || 'content') + '"></label>' +
                    '<label>Heading<input class="ugw-input ugw-content-section-field" data-section-field="heading" value="' + escAttr(section.heading || '') + '"></label>' +
                    '<label>Subheading<input class="ugw-input ugw-content-section-field" data-section-field="subheading" value="' + escAttr(section.subheading || '') + '"></label>' +
                    '<label>' + escHtml(bodyLabel) + '<textarea class="ugw-input ugw-content-section-field" data-section-field="body">' + escHtml(section.body || '') + '</textarea></label>' +
                    '<label>CTA<input class="ugw-input ugw-content-section-field" data-section-field="cta" value="' + escAttr(section.cta || '') + '"></label>' +
                    '<label>Trust Signal<input class="ugw-input ugw-content-section-field" data-section-field="trust_signal" value="' + escAttr(section.trust_signal || '') + '"></label>' +
                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-content-remove-section">Remove</button>' +
                    '</article>';
            });
            html += '</div></div>';
            return html;
        }

        function renderReferenceStage(sp) {
            var board = getReferenceBoard(sp);
            var refs = board.references || [];
            if (!refs.length) {
                return '<div class="ugw-studio-empty"><strong>Reference Board is ready.</strong><span>Search real design sources, then like at least 3 references before high fidelity.</span></div>';
            }
            var selectedCount = referenceSelectionCount(board);
            var html = '<div class="ugw-reference-board-preview">' +
                '<div class="ugw-reference-board-head">' +
                    '<div><strong>' + selectedCount + ' / ' + (board.min_required || 3) + ' selected</strong><span>' + escHtml(board.query || 'Real-source design references') + '</span></div>' +
                    (board.skipped ? '<em>Skipped with warning</em>' : (referencesReady(board) ? '<em>Ready</em>' : '<em>Select more</em>')) +
                '</div>' +
                '<div class="ugw-reference-card-grid">';
            refs.forEach(function (ref) {
                var autoRef = String(ref.query_used || '').toLowerCase() === 'user supplied' || ['user_site', 'user_reference', 'design_image'].indexOf(String(ref.kind || '')) >= 0;
                var liked = board.liked_reference_ids.indexOf(ref.id) >= 0;
                var pinned = board.pinned_reference_ids.indexOf(ref.id) >= 0;
                var disliked = board.disliked_reference_ids.indexOf(ref.id) >= 0;
                var classes = 'ugw-reference-card' + (liked ? ' is-liked' : '') + (pinned ? ' is-pinned' : '') + (disliked ? ' is-disliked' : '');
                var fallback = '<div class="ugw-reference-thumb-fallback"><span>' + escHtml(ref.source || 'Source') + '</span></div>';
                var hiddenFallback = '<div class="ugw-reference-thumb-fallback" style="display:none;"><span>' + escHtml(ref.source || 'Source') + '</span></div>';
                html += '<article class="' + classes + '" data-reference-id="' + escAttr(ref.id || '') + '">' +
                    '<div class="ugw-reference-thumb">' +
                        (ref.thumbnail_url
                            ? '<img src="' + escAttr(ref.thumbnail_url) + '" alt="' + escAttr(ref.title || 'Design reference') + '" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.nextElementSibling){this.nextElementSibling.style.display=\'flex\';}this.remove();">' + hiddenFallback
                            : fallback) +
                    '</div>' +
                    '<div class="ugw-reference-card-body">' +
                        '<div class="ugw-reference-meta"><span>' + escHtml(ref.source || 'Source') + '</span><em>' + escHtml(autoRef ? 'auto-counted' : (ref.inspect_status || 'queued')) + '</em></div>' +
                        '<h4>' + escHtml(ref.title || 'Design reference') + '</h4>' +
                        '<p>' + escHtml(ref.observed_summary || 'Reference will be inspected when selected.') + '</p>' +
                        '<div class="ugw-reference-actions">' +
                            '<button type="button" class="ugw-reference-vote" data-vote="like" title="Like">Like</button>' +
                            '<button type="button" class="ugw-reference-vote" data-vote="pin" title="Pin">Pin</button>' +
                            '<button type="button" class="ugw-reference-vote" data-vote="dislike" title="Dislike">No</button>' +
                            (ref.live_url ? '<a href="' + escAttr(ref.live_url) + '" target="_blank" rel="noopener">Live</a>' : '') +
                            (ref.source_url ? '<a href="' + escAttr(ref.source_url) + '" target="_blank" rel="noopener">Source</a>' : '') +
                        '</div>' +
                    '</div>' +
                '</article>';
            });
            html += '</div></div>';
            return html;
        }

        function renderWireframeStage(stageData) {
            var data = stageData.data || {};
            if (!data.sections || !data.sections.length) {
                return '<div class="ugw-studio-empty"><strong>Wireframe is ready to generate.</strong><span>Approve content first, then create the layout blueprint.</span></div>';
            }

            var html = '<div class="ugw-studio-wireframe">';
            html += '<div class="ugw-studio-wire-rationale">' + escHtml(data.layout_rationale || 'Layout blueprint') + '</div>';
            data.sections.forEach(function (section, idx) {
                html += '<div class="ugw-wireframe-section">' +
                    '<div class="ugw-wireframe-section-head"><span>' + (idx + 1) + '</span><strong>' + escHtml(section.label || section.id || 'Section') + '</strong><em>' + escHtml(section.layout || 'layout') + '</em></div>' +
                    '<div class="ugw-wireframe-blocks">';
                (section.blocks || [{ type: 'headline' }, { type: 'copy' }, { type: 'visual' }]).forEach(function (block) {
                    html += '<div class="ugw-wireframe-block is-' + escAttr(block.type || 'block') + '">' + escHtml(block.type || 'block') + '</div>';
                });
                html += '</div><p>' + escHtml(section.interaction_intent || section.responsive || '') + '</p></div>';
            });
            html += '</div>';
            return html;
        }

        function renderMockupImageStage(page, stageData) {
            stageData = stageData || {};
            var concepts = Array.isArray(stageData.design_concepts) ? stageData.design_concepts : [];
            var selectedId = stageData.selected_design_id || '';
            if (concepts.length) {
                var assets = Array.isArray(stageData.assets_used) ? stageData.assets_used.length : 0;
                var html = '<div class="ugw-design-concepts-stage">' +
                    '<div class="ugw-design-concepts-head"><div><strong>' + concepts.length + ' generated designs</strong><span>Choose the visual direction that should guide every page.</span></div><em>' + assets + ' uploaded assets</em></div>' +
                    '<div class="ugw-design-concept-grid">';
                concepts.forEach(function (concept) {
                    var isSelected = selectedId && selectedId === concept.id;
                    var styleLabel = concept.style_label || ('Direction ' + (concept.variant || ''));
                    html += '<article class="ugw-design-concept-card' + (isSelected ? ' is-selected' : '') + '" data-concept-id="' + escAttr(concept.id || '') + '">' +
                        '<button type="button" class="ugw-design-concept-select" data-concept-id="' + escAttr(concept.id || '') + '">' +
                            '<img src="' + escAttr(concept.image_url || '') + '" alt="' + escAttr(concept.title || 'Generated design concept') + '" loading="lazy">' +
                            '<span>' + escHtml(isSelected ? 'Selected' : 'Select Design') + '</span>' +
                        '</button>' +
                        '<div class="ugw-design-concept-meta"><strong>' + escHtml(concept.title || 'Design Concept') + '</strong><em>' + escHtml(styleLabel + (concept.style_locked ? ' · style locked' : '')) + '</em></div>' +
                    '</article>';
                });
                html += '</div></div>';
                return html;
            }
            var imageUrl = stageData.mockup_image_url || '';
            if (imageUrl) {
                return '<div class="ugw-mockup-image-stage"><div class="ugw-mockup-image-frame"><img src="' + escAttr(imageUrl) + '" alt="' + escAttr(page.page_name + ' mockup image') + '" loading="eager"></div></div>';
            }
            if (stageData.html) {
                return renderHtmlStage(page, 'website_draft', stageData.html);
            }
            return '<div class="ugw-studio-empty"><strong>Design Concepts are ready to generate.</strong><span>Studio will generate 10 full-page website images with all approved content, then use your selected style for every other page.</span></div>';
        }

        function getStudioMediaAssets(page) {
            var sp = getStudioPage(page);
            sp.media_assets = Array.isArray(sp.media_assets) ? sp.media_assets : [];
            return sp.media_assets;
        }

        function renderStudioMediaTray(page) {
            var assets = getStudioMediaAssets(page);
            var html = '<div class="ugw-studio-media-tray">' +
                '<div class="ugw-studio-media-head"><strong>Required Page Media</strong><button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-studio-add-media">Add Media</button></div>' +
                '<p>Uploaded product photos, videos, screenshots, and brand imagery are sent into Mockup generation and preserved through High Fidelity.</p>';
            if (!assets.length) {
                html += '<div class="ugw-studio-media-empty">No uploaded page media yet.</div>';
            } else {
                html += '<div class="ugw-studio-media-grid">';
                assets.forEach(function (asset, idx) {
                    var isVideo = /^video\//i.test(asset.mime_type || '') || asset.kind === 'video';
                    html += '<article class="ugw-studio-media-card" data-media-idx="' + idx + '">' +
                        '<div class="ugw-studio-media-thumb">' +
                            (isVideo
                                ? '<span class="dashicons dashicons-video-alt3"></span>'
                                : '<img src="' + escAttr(asset.url || '') + '" alt="' + escAttr(asset.title || 'Uploaded media') + '">') +
                        '</div>' +
                        '<label>Kind<select class="ugw-input ugw-media-field" data-media-field="kind">' +
                            ['photo', 'product_image', 'video', 'logo', 'screenshot'].map(function (kind) {
                                return '<option value="' + kind + '"' + (asset.kind === kind ? ' selected' : '') + '>' + kind.replace('_', ' ') + '</option>';
                            }).join('') +
                        '</select></label>' +
                        '<label>Role<select class="ugw-input ugw-media-field" data-media-field="role">' +
                            ['logo', 'hero', 'gallery', 'product', 'background', 'proof', 'general'].map(function (role) {
                                return '<option value="' + role + '"' + (asset.role === role ? ' selected' : '') + '>' + role + '</option>';
                            }).join('') +
                        '</select></label>' +
                        '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-remove-media">Remove</button>' +
                    '</article>';
                });
                html += '</div>';
            }
            html += '</div>';
            return html;
        }

        function renderAnimationOptions(sp) {
            var current = (sp.animations && sp.animations.animation_level) || 'balanced';
            var levels = [
                { id: 'basic', label: 'Basic', text: 'Fades, small hover states, smooth scrolling.' },
                { id: 'balanced', label: 'Balanced', text: 'Section reveals, staggered cards, CTA motion.' },
                { id: 'advanced', label: 'Advanced', text: 'Parallax, magnetic CTAs, animated media masks.' },
                { id: 'premium', label: 'Premium', text: 'Creative scroll choreography and page-specific motion.' }
            ];
            var html = '<div class="ugw-animation-options"><strong>Animation Level</strong>';
            levels.forEach(function (level) {
                html += '<label class="ugw-animation-option">' +
                    '<input type="radio" name="ugw-animation-level" value="' + level.id + '"' + (current === level.id ? ' checked' : '') + '>' +
                    '<span><b>' + escHtml(level.label) + '</b><em>' + escHtml(level.text) + '</em></span>' +
                '</label>';
            });
            html += '</div>';
            return html;
        }

        function renderHtmlStage(page, stage, html) {
            if (!html) {
                return '<div class="ugw-studio-empty"><strong>' + escHtml(STUDIO_LABELS[stage]) + ' is ready to generate.</strong><span>Use the inspector to send notes and generate this stage.</span></div>';
            }
            return '<div class="ugw-studio-frame-wrap"><iframe class="ugw-studio-preview-frame" data-page-id="' + escAttr(page.id) + '" data-stage="' + escAttr(stage) + '" sandbox="allow-same-origin allow-scripts"></iframe></div>';
        }

        function renderStudioInspector(page, stage) {
            var sp = getStudioPage(page);
            var status = getStageStatus(sp, stage);
            var approved = isStageApproved(sp, stage);
            var hasOutput = stageHasOutput(sp, stage);
            var canApprove = status === 'complete' && hasOutput && !approved;
            var previous = previousStage(stage);
            var gated = previous && !isStageApproved(sp, previous);
            var quality = sp.high_fidelity || {};
            var referenceBoard = getReferenceBoard(sp);
            var generateLabel = hasOutput ? 'Regenerate ' + STUDIO_LABELS[stage] : 'Generate ' + STUDIO_LABELS[stage];
            var sectionCount = (stage === 'outline' || stage === 'full_content') ? getContentSectionCount(page) : 0;
            var approveLabel = approved
                ? 'Approved'
                : (stage === 'animations' ? 'Approve Final Page' : 'Approve & Continue');
            var next = nextStage(stage);
            var nextHint = approved
                ? (next ? 'Next: ' + STUDIO_LABELS[next] : 'Final page is approved.')
                : (next ? 'After approval you will move to ' + STUDIO_LABELS[next] + '.' : 'Approving this stage completes the page.');
            if (stage === 'references' && hasOutput && !referencesReady(referenceBoard)) {
                canApprove = false;
            }
            if (stage === 'brief' && !hasStudioLogoAsset(page)) {
                canApprove = false;
                gated = true;
            }
            if (stage === 'website_draft' && hasOutput) {
                canApprove = !!(sp.website_draft && sp.website_draft.selected_design_id);
            }

            var html = '<aside class="ugw-studio-inspector">' +
                '<div class="ugw-studio-panel">' +
                    '<span class="ugw-studio-kicker">Stage Input</span>' +
                    '<h3>' + escHtml(STUDIO_LABELS[stage]) + '</h3>' +
                    '<p>' + escHtml(STUDIO_GUIDANCE[stage]) + '</p>' +
                    (stage === 'brief'
                        ? renderStudioLogoInput(page)
                        : '') +
                    ((stage === 'outline' || stage === 'full_content')
                        ? '<div class="ugw-studio-content-options">' +
                            '<label>Sections to generate<input type="number" min="1" max="12" step="1" class="ugw-input" id="ugw-studio-section-count" value="' + escAttr(sectionCount) + '"></label>' +
                            '<span>' + (stage === 'outline' ? 'Generate and edit structure only.' : 'Generate polished copy from the approved outline.') + '</span>' +
                        '</div>'
                        : '') +
                    (stage === 'references'
                        ? '<div class="ugw-studio-content-options">' +
                            '<label>Search query<input class="ugw-input" id="ugw-reference-query" value="' + escAttr(referenceBoard.query || '') + '" placeholder="interactive SaaS landing page, restaurant web design..."></label>' +
                            '<label>Inspiration URLs<textarea class="ugw-input" id="ugw-reference-urls" rows="4" placeholder="Paste live websites, Awwwards/Behance/Dribbble pages, or direct design image URLs, one per line...">' + escHtml(referenceBoard.user_reference_urls || '') + '</textarea></label>' +
                            '<span>' + escHtml(referenceSelectionCount(referenceBoard) + ' selected. Minimum ' + (referenceBoard.min_required || 3) + ' required unless skipped.') + '</span>' +
                        '</div>'
                        : '') +
                    (stage === 'website_draft'
                        ? renderStudioMediaTray(page)
                        : '') +
                    (stage === 'animations'
                        ? renderAnimationOptions(sp)
                        : '') +
                    '<div class="ugw-studio-next-hint">' + escHtml(nextHint) + '</div>' +
                    '<div class="ugw-studio-actions">' +
                        '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-studio-save-input">Save Input</button>' +
                        ((stage === 'outline' || stage === 'full_content')
                            ? '<button type="button" class="ugw-btn ugw-btn--primary ugw-btn--sm" id="ugw-studio-generate-stage" ' + (gated ? 'disabled' : '') + '>' + escHtml(generateLabel) + '</button>'
                            : (stage === 'references'
                                ? '<button type="button" class="ugw-btn ugw-btn--primary ugw-btn--sm" id="ugw-reference-search">Search References</button>' +
                                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-reference-skip">Skip References</button>'
                                    + '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-reference-clear">Clear Selection</button>'
                            : '<button type="button" class="ugw-btn ugw-btn--primary ugw-btn--sm" id="ugw-studio-generate-stage" ' + (gated ? 'disabled' : '') + '>' + escHtml(generateLabel) + '</button>') +
                            '') +
                    '</div>';

            if (gated) {
                html += '<div class="ugw-studio-warning">' + (stage === 'brief' && !hasStudioLogoAsset(page) ? 'Add the brand logo before generating or approving the Brief.' : 'Approve ' + escHtml(STUDIO_LABELS[previous]) + ' before generating this stage.') + '</div>';
            }

            if (stage === 'references' && hasOutput && !referencesReady(referenceBoard)) {
                html += '<div class="ugw-studio-warning">Like or pin at least 3 references before approving. Skipping is allowed, but High Fidelity will rely on the industry playbook instead of selected source analysis.</div>';
            }

            if (hasOutput) {
                html += '<div class="ugw-studio-actions">' +
                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-studio-revise-stage">Revise With Notes</button>' +
                    '<button type="button" class="ugw-btn ugw-btn--accent ugw-btn--sm" id="ugw-studio-approve-stage" ' + (!canApprove ? 'disabled' : '') + '>' + escHtml(approveLabel) + '</button>' +
                    '</div>';
            }

            html += '<div class="ugw-studio-divider"></div>' +
                '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-btn--full" id="ugw-studio-fast-draft">Use Fast Draft Instead</button>' +
                '</div>';

            if (stage === 'website_draft' && hasOutput && sp.website_draft && sp.website_draft.html) {
                html += renderMockupEditorPanel(page);
            }

            if (stage === 'high_fidelity') {
                html += '<div class="ugw-studio-panel">' +
                    '<span class="ugw-studio-kicker">Quality Review</span>' +
                    '<h3>' + (quality.quality_score !== undefined ? escHtml(String(quality.quality_score)) + '/10' : 'Not scored yet') + '</h3>' +
                    renderQualityLog(quality.quality_log || [], quality.round_previews || []) +
                    renderWarnings(quality.warnings || []) +
                    '</div>';
            }

            html += renderStudioDiagnostics(page, stage);
            html += renderDesignContractPanel(page);

            html += '<div class="ugw-studio-panel">' +
                '<span class="ugw-studio-kicker">Page Status</span>' +
                renderStageChecklist(sp) +
                '</div>' +
                '</aside>';

            return html;
        }

        function renderStudioDiagnostics(page, stage) {
            var sp = getStudioPage(page);
            var htmlBody = getStageHtmlForIframe(page, 'high_fidelity') || getStageHtmlForIframe(page, stage) || '';
            var assetLog = (sp.high_fidelity && sp.high_fidelity.asset_log) || {};
            var imageCount = (htmlBody.match(/<img\b/gi) || []).length;
            var iconCount = (htmlBody.match(/\b(?:lucide-[a-z0-9-]+|fa-(?:solid|regular|brands|[a-z0-9-]+))\b|<svg\b[^>]*\blucide\b/gi) || []).length;
            var localCount = (htmlBody.match(/\/wp-content\/uploads\//gi) || []).length;
            var hasScopedCss = /<style\b/i.test(htmlBody) && /ugentic-self-contained-design-css|ugp-|#ugentic-page-content/i.test(htmlBody);
            var hasPlaceholders = /via\.placeholder|Loading\.\.\.|Content is loading|lorem ipsum/i.test(htmlBody);
            var hasHeroImage = /ugp-section--hero[\s\S]{0,6500}<img\b/i.test(htmlBody) || imageCount > 0;
            var downloaded = Array.isArray(assetLog.downloaded) ? assetLog.downloaded.length : 0;
            var failed = Array.isArray(assetLog.failed) ? assetLog.failed.length : 0;
            var criteria = (sp.high_fidelity && sp.high_fidelity.reference_criteria) || {};
            var qualityLog = (sp.high_fidelity && Array.isArray(sp.high_fidelity.quality_log)) ? sp.high_fidelity.quality_log : [];
            var latestQuality = qualityLog.length ? qualityLog[qualityLog.length - 1] : {};
            var checks = [
                { label: 'Hero image', value: hasHeroImage ? 'Ready' : 'Missing', ok: hasHeroImage },
                { label: 'Images', value: String(imageCount) + ' / 3+', ok: imageCount >= 3 },
                { label: 'Icons', value: String(iconCount) + ' / 8+', ok: iconCount >= 8 },
                { label: 'Scoped CSS', value: hasScopedCss ? 'Present' : 'Missing', ok: hasScopedCss },
                { label: 'Placeholders', value: hasPlaceholders ? 'Found' : 'Clean', ok: !hasPlaceholders },
                { label: 'Local media', value: String(localCount || downloaded), ok: (localCount + downloaded) > 0 || imageCount === 0 },
                { label: 'Asset warnings', value: String(failed), ok: failed === 0 }
            ];
            var out = '<div class="ugw-studio-panel">' +
                '<span class="ugw-studio-kicker">Visual QA</span>' +
                '<div class="ugw-studio-diag-grid">';
            checks.forEach(function (check) {
                out += '<div class="ugw-studio-diag ' + (check.ok ? 'is-ok' : 'is-warn') + '">' +
                    '<span>' + escHtml(check.label) + '</span><strong>' + escHtml(check.value) + '</strong>' +
                    '</div>';
            });
            out += '</div>' + renderReferenceCriteriaSummary(criteria, latestQuality) + '</div>';
            return out;
        }

        function renderReferenceCriteriaSummary(criteria, latestQuality) {
            criteria = criteria || {};
            latestQuality = latestQuality || {};
            var sections = Array.isArray(criteria.sections) ? criteria.sections : [];
            var summary = latestQuality.criteria_summary || {};
            var checklist = Array.isArray(latestQuality.section_checklist) ? latestQuality.section_checklist : [];
            if (!sections.length && !checklist.length) {
                return '<div class="ugw-reference-criteria"><strong>Reference criteria</strong><p>High Fidelity will build section-level criteria from selected references before scoring and revisions.</p></div>';
            }
            var misses = summary.criteria_misses !== undefined ? summary.criteria_misses : checklist.reduce(function (total, section) {
                return total + (Array.isArray(section.misses) ? section.misses.length : 0);
            }, 0);
            var titles = Array.isArray(criteria.selected_reference_titles) ? criteria.selected_reference_titles : [];
            var html = '<div class="ugw-reference-criteria">' +
                '<strong>Reference Criteria</strong>' +
                '<div class="ugw-reference-criteria-meta">' +
                '<span>' + escHtml(String(criteria.source_count || summary.source_count || 0)) + ' sources</span>' +
                '<span>' + escHtml(String(sections.length || summary.sections_checked || 0)) + ' sections</span>' +
                '<span>' + escHtml(String(misses)) + ' misses</span>' +
                '</div>';
            if (titles.length) {
                html += '<p>' + escHtml(titles.slice(0, 3).join(', ')) + '</p>';
            }
            var rows = checklist.length ? checklist : sections;
            html += '<div class="ugw-reference-criteria-list">';
            rows.slice(0, 5).forEach(function (section) {
                var sectionMisses = Array.isArray(section.misses) ? section.misses : [];
                var criteriaList = Array.isArray(section.criteria) ? section.criteria : []
                    .concat([section.layout_model, section.media_treatment, section.icon_placement, section.cta_placement, section.motion_expectation, section.anti_generic_rule].filter(Boolean))
                    .concat(section.layout_criteria || [])
                    .concat(section.media_criteria || [])
                    .concat(section.icon_criteria || [])
                    .concat(section.cta_criteria || [])
                    .concat(section.interaction_criteria || []);
                html += '<article class="' + (sectionMisses.length ? 'is-warn' : 'is-ok') + '">' +
                    '<b>' + escHtml(section.section || 'Section') + '</b>' +
                    '<span>' + escHtml(sectionMisses.length ? sectionMisses.slice(0, 2).join(' · ') : criteriaList.slice(0, 2).join(' · ') || 'Criteria matched') + '</span>' +
                    '</article>';
            });
            html += '</div></div>';
            return html;
        }

        function renderDesignContractPanel(page) {
            var contract = getStudioDesignContract(page);
            if (!contract || !Object.keys(contract).length) {
                return '<div class="ugw-studio-panel"><span class="ugw-studio-kicker">Design Contract</span><p>High Fidelity will create a page-level contract for theme tokens, images, icons, motion, and responsive layout before HTML is accepted.</p></div>';
            }
            var sections = Array.isArray(contract.sections) ? contract.sections.length : 0;
            var images = Array.isArray(contract.image_slots) ? contract.image_slots.length : 0;
            var icons = Array.isArray(contract.icon_slots) ? contract.icon_slots.length : 0;
            var motion = contract.motion_style || contract.motion || 'Industry-specific motion';
            return '<div class="ugw-studio-panel">' +
                '<span class="ugw-studio-kicker">Design Contract</span>' +
                '<div class="ugw-studio-contract-grid">' +
                    '<span><strong>' + sections + '</strong> sections</span>' +
                    '<span><strong>' + images + '</strong> images</span>' +
                    '<span><strong>' + icons + '</strong> icons</span>' +
                '</div>' +
                '<p>' + escHtml(Array.isArray(motion) ? motion.join(', ') : String(motion)) + '</p>' +
                '</div>';
        }

        function renderInspirationBriefPanel(page) {
            var sp = getStudioPage(page);
            var brief = (sp.high_fidelity && sp.high_fidelity.inspiration_brief) || (sp.website_draft && sp.website_draft.inspiration_brief) || sp.inspiration_brief || null;
            if (!brief || !Object.keys(brief).length) {
                return '<div class="ugw-studio-panel"><span class="ugw-studio-kicker">Inspiration Match</span><p>High Fidelity will fetch public inspiration sources and convert them into a section-by-section design DNA checklist.</p></div>';
            }
            var sources = Array.isArray(brief.sources) ? brief.sources : [];
            var principles = Array.isArray(brief.layout_principles) ? brief.layout_principles : [];
            var observed = Array.isArray(brief.observed_sections) ? brief.observed_sections : [];
            var icons = Array.isArray(brief.icon_placements) ? brief.icon_placements : [];
            var images = Array.isArray(brief.image_placements) ? brief.image_placements : [];
            var components = Array.isArray(brief.component_patterns) ? brief.component_patterns : [];
            var html = '<div class="ugw-studio-panel">' +
                '<span class="ugw-studio-kicker">Inspiration Match</span>' +
                '<p>' + escHtml(brief.copy_policy || 'Use inspiration as transformed design DNA only.') + '</p>';
            if (sources.length) {
                html += '<div class="ugw-inspiration-source-list">';
                sources.slice(0, 5).forEach(function (source) {
                    var sections = Array.isArray(source.section_analysis) ? source.section_analysis.length : 0;
                    var patterns = Array.isArray(source.component_patterns) ? source.component_patterns.slice(0, 3).join(', ') : '';
                    var liveSites = Array.isArray(source.derived_live_sites) ? source.derived_live_sites.length : 0;
                    html += '<div class="ugw-inspiration-source">' +
                        '<strong>' + escHtml(source.platform || 'Source') + '</strong>' +
                        '<span>' + escHtml(source.title || source.description || source.url || 'Reference source') + '</span>' +
                        '<small>' + escHtml(sections ? (sections + ' sections inspected' + (liveSites ? ' from ' + liveSites + ' live site(s)' : '') + (patterns ? ' · ' + patterns : '')) : (patterns || source.url || '')) + '</small>' +
                        (source.fetched ? '<em>Fetched + inspected</em>' : '<em>Reference only</em>') +
                    '</div>';
                });
                html += '</div>';
            }
            if (observed.length || icons.length || images.length) {
                html += '<div class="ugw-inspiration-observed-grid">' +
                    '<span><strong>' + observed.length + '</strong> sections</span>' +
                    '<span><strong>' + icons.length + '</strong> icon placements</span>' +
                    '<span><strong>' + images.length + '</strong> media placements</span>' +
                    '<span><strong>' + components.length + '</strong> patterns</span>' +
                '</div>';
            }
            if (observed.length) {
                html += '<div class="ugw-inspiration-section-list">';
                observed.slice(0, 5).forEach(function (section) {
                    var hints = Array.isArray(section.layout_hints) ? section.layout_hints.slice(0, 2).join(', ') : '';
                    var treatment = Array.isArray(section.visual_treatment) ? section.visual_treatment.slice(0, 2).join(', ') : '';
                    html += '<article>' +
                        '<b>' + escHtml(section.role || 'section') + '</b>' +
                        '<span>' + escHtml((hints || treatment || 'source structure') + ' · ' + (section.image_count || 0) + ' media · ' + (section.icon_count || 0) + ' icons · ' + (section.cta_count || 0) + ' CTAs') + '</span>' +
                    '</article>';
                });
                html += '</div>';
            }
            if (principles.length) {
                html += '<div class="ugw-inspiration-principles">';
                principles.slice(0, 5).forEach(function (item) {
                    html += '<span>' + escHtml(item) + '</span>';
                });
                html += '</div>';
            }
            html += renderWarnings(brief.warnings || []);
            html += '</div>';
            return html;
        }

        function getReferenceDna(page) {
            var sp = getStudioPage(page);
            var board = getReferenceBoard(sp);
            return (sp.high_fidelity && sp.high_fidelity.reference_section_dna) ||
                (sp.website_draft && sp.website_draft.reference_section_dna) ||
                (sp.references && sp.references.reference_section_dna) ||
                board.reference_section_dna ||
                null;
        }

        function renderReferenceDnaPanel(page) {
            var dna = getReferenceDna(page);
            if (!dna || !Object.keys(dna).length) {
                return '<div class="ugw-studio-panel"><span class="ugw-studio-kicker">Reference DNA</span><p>Selected references will be analyzed into section-level visual DNA before Mockup generation.</p></div>';
            }
            var sources = Array.isArray(dna.sources) ? dna.sources : [];
            var patterns = Array.isArray(dna.global_patterns) ? dna.global_patterns : [];
            var html = '<div class="ugw-studio-panel">' +
                '<span class="ugw-studio-kicker">Reference DNA</span>' +
                '<p>' + escHtml(dna.copy_policy || 'Transform selected sources into original section rhythm, media, motion, and CTA guidance.') + '</p>' +
                '<div class="ugw-inspiration-observed-grid">' +
                    '<span><strong>' + escHtml(String(dna.selected_reference_count || sources.length || 0)) + '</strong> selected</span>' +
                    '<span><strong>' + escHtml(String(dna.ai_image_context_count || 0)) + '</strong> images sent</span>' +
                    '<span><strong>' + escHtml(String(sources.reduce(function (total, source) { return total + ((source.sections || []).length || 0); }, 0))) + '</strong> sections</span>' +
                    '<span><strong>' + escHtml(String(patterns.length || 0)) + '</strong> patterns</span>' +
                '</div>';
            if (sources.length) {
                html += '<div class="ugw-reference-dna-source-list">';
                sources.slice(0, 6).forEach(function (source) {
                    var sections = Array.isArray(source.sections) ? source.sections : [];
                    var flags = [];
                    if (source.auto_counted) flags.push('auto-counted');
                    if (source.included_in_ai_context) flags.push('sent to AI');
                    html += '<article>' +
                        (source.image_url ? '<img src="' + escAttr(source.image_url) + '" alt="' + escAttr(source.title || 'Reference') + '" loading="lazy" onerror="this.remove();">' : '') +
                        '<div><b>' + escHtml(source.title || 'Reference') + '</b>' +
                        '<span>' + escHtml((source.ai_visual_summary || source.summary || source.source_url || '') + (flags.length ? ' · ' + flags.join(' · ') : '')) + '</span>' +
                        '<small>' + escHtml(String(sections.length) + ' section DNA entries' + (source.kind ? ' · ' + source.kind : '')) + '</small></div>' +
                    '</article>';
                });
                html += '</div>';
            }
            if (patterns.length) {
                html += '<div class="ugw-inspiration-principles">' + patterns.slice(0, 7).map(function (pattern) {
                    return '<span>' + escHtml(pattern) + '</span>';
                }).join('') + '</div>';
            }
            html += renderWarnings(dna.warnings || []);
            html += '</div>';
            return html;
        }

        function renderMockupCreativeBriefPanel(page) {
            var sp = getStudioPage(page);
            var brief = (sp.high_fidelity && sp.high_fidelity.mockup_creative_brief) || (sp.website_draft && sp.website_draft.mockup_creative_brief) || null;
            var mapping = (sp.high_fidelity && sp.high_fidelity.reference_mapping) || (sp.website_draft && sp.website_draft.reference_mapping) || [];
            if (!brief || !Object.keys(brief).length) {
                return '<div class="ugw-studio-panel"><span class="ugw-studio-kicker">Mockup Creative Brief</span><p>Mockup will turn the approved wireframe into section-specific reference-directed design instructions.</p></div>';
            }
            var suggestions = Array.isArray(brief.section_suggestions) ? brief.section_suggestions : [];
            mapping = Array.isArray(mapping) ? mapping : [];
            var html = '<div class="ugw-studio-panel">' +
                '<span class="ugw-studio-kicker">Mockup Creative Brief</span>' +
                '<div class="ugw-reference-criteria-meta">' +
                    '<span>' + escHtml(String(brief.reference_count || 0)) + ' refs</span>' +
                    '<span>' + escHtml(String(brief.reference_image_count || 0)) + ' images</span>' +
                    '<span>' + escHtml(String(suggestions.length)) + ' sections</span>' +
                '</div>' +
                '<div class="ugw-reference-criteria-list">';
            suggestions.slice(0, 6).forEach(function (item, idx) {
                var map = mapping[idx] || {};
                html += '<article class="is-ok">' +
                    '<b>' + escHtml(item.section || item.section_id || 'Section') + '</b>' +
                    '<span>' + escHtml((item.source_reference || map.source_reference || 'Reference DNA') + ' · ' + (item.layout_model || '') + ' · ' + (item.media_strategy || '')) + '</span>' +
                    '<small>' + escHtml(item.motion_hook || item.interaction_idea || 'section-specific motion hook') + '</small>' +
                '</article>';
            });
            html += '</div></div>';
            return html;
        }

        function renderQualityLog(log, previews) {
            if (!log.length) {
                return '<p>No critique rounds yet. High Fidelity generation will run up to 5 automatic revisions.</p>';
            }
            previews = Array.isArray(previews) ? previews : [];
            var previewByRound = {};
            previews.forEach(function (preview) {
                if (preview && preview.round !== undefined) {
                    previewByRound[String(preview.round)] = preview;
                }
            });
            var html = '<div class="ugw-studio-quality-log">';
            log.forEach(function (item) {
                var round = String(item.round || '');
                var preview = previewByRound[round];
                var checklist = Array.isArray(item.section_checklist) ? item.section_checklist : [];
                html += '<div class="ugw-studio-quality-round">' +
                    '<strong>Round ' + escHtml(round) + ': ' + escHtml(String(item.score || 0)) + '/10</strong>' +
                    '<span>' + escHtml(item.feedback || '') + '</span>';
                if (checklist.length) {
                    html += '<div class="ugw-section-checklist">';
                    checklist.slice(0, 4).forEach(function (section) {
                        var misses = Array.isArray(section.misses) ? section.misses : [];
                        var changes = Array.isArray(section.required_next_changes) ? section.required_next_changes : [];
                        var criteria = Array.isArray(section.criteria) ? section.criteria : [];
                        var evidence = Array.isArray(section.reference_evidence) ? section.reference_evidence : [];
                        html += '<article>' +
                            '<b>' + escHtml(section.section || section.id || 'Section') + '</b>' +
                            '<small>' + escHtml(section.visual_delta || misses.concat(changes).slice(0, 2).join(' · ') || 'Matched against reference criteria.') + '</small>' +
                            (criteria.length ? '<div class="ugw-quality-criteria-pills">' + criteria.slice(0, 4).map(function (criterion) { return '<span>' + escHtml(criterion) + '</span>'; }).join('') + '</div>' : '') +
                            (misses.length ? '<em>' + escHtml('Next: ' + misses.concat(changes).slice(0, 3).join(' · ')) + '</em>' : '') +
                            (evidence.length ? '<small>' + escHtml(evidence.slice(0, 2).join(' · ')) + '</small>' : '') +
                        '</article>';
                    });
                    html += '</div>';
                }
                html += '<div class="ugw-round-preview-actions">';
                if (preview && preview.html) {
                    html += '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-round-preview-btn" data-round="' + escAttr(round) + '">Preview Round</button>';
                }
                html += '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-round-final-btn">Show Final</button>' +
                    '</div></div>';
            });
            html += '</div>';
            return html;
        }

        function renderWarnings(warnings) {
            if (!warnings.length) return '';
            var html = '<div class="ugw-studio-warning">';
            warnings.forEach(function (warning) {
                html += '<div>' + escHtml(warning) + '</div>';
            });
            html += '</div>';
            return html;
        }

        function renderStageChecklist(sp) {
            var html = '<div class="ugw-studio-checklist">';
            STUDIO_STAGES.forEach(function (stage) {
                var status = getStageStatus(sp, stage);
                var approved = isStageApproved(sp, stage);
                html += '<div class="ugw-studio-check-row">' +
                    '<span class="ugw-studio-tab-dot is-' + escAttr(status) + '"></span>' +
                    '<strong>' + escHtml(STUDIO_LABELS[stage]) + '</strong>' +
                    '<em>' + (approved ? 'Approved' : status) + '</em>' +
                    '</div>';
            });
            html += '</div>';
            return html;
        }

        function previousStage(stage) {
            var idx = STUDIO_STAGES.indexOf(stage);
            return idx > 0 ? STUDIO_STAGES[idx - 1] : null;
        }

        function nextStage(stage) {
            var idx = STUDIO_STAGES.indexOf(stage);
            return idx >= 0 && idx < STUDIO_STAGES.length - 1 ? STUDIO_STAGES[idx + 1] : null;
        }

        function escAttr(s) {
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function getStageHtmlForIframe(page, stage) {
            var sp = getStudioPage(page);
            if (stage === 'wireframe') {
                return (sp.wireframe && sp.wireframe.html) || '';
            }
            if (stage === 'website_draft') {
                return (sp.website_draft && sp.website_draft.html) || '';
            }
            if (stage === 'high_fidelity') {
                return (sp.high_fidelity && (sp.high_fidelity.preview_html || sp.high_fidelity.html)) || '';
            }
            if (stage === 'animations') {
                return (sp.animations && sp.animations.html) || (sp.high_fidelity && sp.high_fidelity.html) || '';
            }
            return '';
        }

        function studioHash(value) {
            value = String(value || '');
            var hash = 0;
            for (var i = 0; i < value.length; i++) {
                hash = ((hash << 5) - hash) + value.charCodeAt(i);
                hash |= 0;
            }
            return String(hash);
        }

        function getMockupIframe(page) {
            page = page || getCurrentCanvasPage();
            if (!page) return null;
            return document.querySelector('.ugw-studio-preview-frame[data-stage="website_draft"][data-page-id="' + String(page.id) + '"]');
        }

        function inferMockupKind(el) {
            if (!el || !el.tagName) return 'group';
            var tag = el.tagName.toLowerCase();
            if (tag === 'section') return 'section';
            if (tag === 'img') return 'image';
            if (tag === 'a' || tag === 'button') return 'button';
            if (/^h[1-6]$/.test(tag) || tag === 'p' || tag === 'span' || tag === 'li') return 'text';
            if (tag === 'figure') return 'media';
            return 'group';
        }

        function ensureMockupEditableMarkers(doc) {
            if (!doc) return;
            var candidates = doc.querySelectorAll('[data-ug-edit-id], [data-ugentic-editable], section, article, figure, h1, h2, h3, h4, p, a, button, img, [class*="card"], [class*="group"], [class*="media"]');
            Array.prototype.forEach.call(candidates, function (el, idx) {
                if (!el.dataset.ugEditId) {
                    el.dataset.ugEditId = 'ugm-client-' + idx + '-' + Math.random().toString(16).slice(2, 7);
                }
                if (!el.dataset.ugEditableKind) {
                    el.dataset.ugEditableKind = inferMockupKind(el);
                }
                if ((el.dataset.ugEditableKind === 'text' || el.dataset.ugEditableKind === 'button' || el.dataset.ugEditableKind === 'image') && !el.dataset.ugenticEditable) {
                    el.dataset.ugenticEditable = 'true';
                }
            });
        }

        function mockupElementLabel(el) {
            if (!el) return 'Element';
            var kind = el.dataset.ugEditableKind || inferMockupKind(el);
            var tag = el.tagName ? el.tagName.toLowerCase() : 'element';
            var text = (el.getAttribute('aria-label') || el.getAttribute('alt') || el.textContent || '').replace(/\s+/g, ' ').trim();
            return (kind.charAt(0).toUpperCase() + kind.slice(1)) + ' · ' + tag + (text ? ' · ' + text.slice(0, 36) : '');
        }

        function rgbToHex(value) {
            value = String(value || '').trim();
            if (/^#[0-9a-f]{6}$/i.test(value)) return value;
            var match = value.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
            if (!match) return '#ffffff';
            return '#' + [match[1], match[2], match[3]].map(function (part) {
                var hex = Math.max(0, Math.min(255, parseInt(part, 10) || 0)).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }).join('');
        }

        function cssEscapeIdent(value) {
            value = String(value || '');
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }
            return value.replace(/["\\]/g, '\\$&');
        }

        function getMockupElementMeta(el) {
            if (!el) return null;
            var computed = el.ownerDocument.defaultView.getComputedStyle(el);
            var linkEl = el.tagName && el.tagName.toLowerCase() === 'a' ? el : el.closest('a');
            return {
                id: el.dataset.ugEditId || '',
                label: mockupElementLabel(el),
                kind: el.dataset.ugEditableKind || inferMockupKind(el),
                tag: el.tagName ? el.tagName.toLowerCase() : 'element',
                text: (el.tagName && el.tagName.toLowerCase() === 'img') ? (el.getAttribute('alt') || '') : (el.textContent || '').trim(),
                src: el.tagName && el.tagName.toLowerCase() === 'img' ? (el.getAttribute('src') || '') : '',
                href: linkEl ? (linkEl.getAttribute('href') || '') : '',
                padding: el.style.padding || computed.padding || '',
                marginTop: el.style.marginTop || computed.marginTop || '',
                marginBottom: el.style.marginBottom || computed.marginBottom || '',
                align: el.style.textAlign || computed.textAlign || '',
                background: rgbToHex(el.style.backgroundColor || computed.backgroundColor),
                color: rgbToHex(el.style.color || computed.color),
                radius: el.style.borderRadius || computed.borderRadius || '',
                visible: computed.display !== 'none'
            };
        }

        function highlightMockupSelection(doc) {
            if (!doc) return;
            Array.prototype.forEach.call(doc.querySelectorAll('.ugw-mockup-edit-selected'), function (el) {
                el.classList.remove('ugw-mockup-edit-selected');
            });
            if (!mockupEditorState.selectedId) return;
            var selected = doc.querySelector('[data-ug-edit-id="' + cssEscapeIdent(mockupEditorState.selectedId) + '"]');
            if (selected) selected.classList.add('ugw-mockup-edit-selected');
        }

        function syncMockupHtmlFromIframe(page) {
            page = page || getCurrentCanvasPage();
            var iframe = getMockupIframe(page);
            if (!page || !iframe || !iframe.contentDocument || !iframe.contentDocument.body) return '';
            var doc = iframe.contentDocument;
            var selected = [];
            Array.prototype.forEach.call(doc.querySelectorAll('.ugw-mockup-edit-selected'), function (el) {
                selected.push(el);
                el.classList.remove('ugw-mockup-edit-selected');
            });
            var html = doc.body.innerHTML || '';
            selected.forEach(function (el) {
                el.classList.add('ugw-mockup-edit-selected');
            });
            var sp = getStudioPage(page);
            sp.website_draft = sp.website_draft || {};
            sp.website_draft.html = html;
            sp.website_draft.edit_manifest = sp.website_draft.edit_manifest || {};
            sp.website_draft.edit_manifest.saved_from_inspector = true;
            sp.website_draft.edit_manifest.updated_at = new Date().toISOString();
            return html;
        }

        function selectMockupElement(page, iframe, el) {
            if (!page || !iframe || !el) return;
            var doc = iframe.contentDocument;
            ensureMockupEditableMarkers(doc);
            mockupEditorState.selectedId = el.dataset.ugEditId || '';
            mockupEditorState.selectedLabel = mockupElementLabel(el);
            var sp = getStudioPage(page);
            sp.website_draft = sp.website_draft || {};
            sp.website_draft.edit_selection = getMockupElementMeta(el);
            highlightMockupSelection(doc);
            renderStudioCanvas();
        }

        function wireMockupIframeEditor(iframe, page) {
            if (!iframe || !page || !iframe.contentDocument) return;
            var doc = iframe.contentDocument;
            ensureMockupEditableMarkers(doc);
            if (!doc.getElementById('ugw-mockup-editor-style')) {
                var style = doc.createElement('style');
                style.id = 'ugw-mockup-editor-style';
                style.textContent = '[data-ug-edit-id]{cursor:pointer}.ugw-mockup-edit-selected{outline:2px solid #2563eb!important;outline-offset:3px!important;box-shadow:0 0 0 6px rgba(37,99,235,.14)!important}';
                doc.head.appendChild(style);
            }
            if (iframe.__ugwMockupEditorDoc !== doc) {
                iframe.__ugwMockupEditorDoc = doc;
                doc.addEventListener('click', function (evt) {
                    var target = evt.target && evt.target.closest ? evt.target.closest('[data-ug-edit-id], [data-ugentic-editable], section, article, figure, h1, h2, h3, h4, p, a, button, img') : null;
                    if (!target) return;
                    evt.preventDefault();
                    evt.stopPropagation();
                    selectMockupElement(page, iframe, target);
                }, true);
            }
            highlightMockupSelection(doc);
        }

        function findSelectedMockupElement() {
            var page = getCurrentCanvasPage();
            var iframe = getMockupIframe(page);
            if (!iframe || !iframe.contentDocument || !mockupEditorState.selectedId) return null;
            return iframe.contentDocument.querySelector('[data-ug-edit-id="' + cssEscapeIdent(mockupEditorState.selectedId) + '"]');
        }

        function renderMockupEditorPanel(page) {
            var sp = getStudioPage(page);
            var draft = sp.website_draft || {};
            var selection = draft.edit_selection || {};
            var hasSelection = !!(selection && selection.id);
            var html = '<div class="ugw-studio-panel ugw-mockup-editor-panel">' +
                '<span class="ugw-studio-kicker">Mockup Inspector</span>' +
                '<h3>' + (hasSelection ? escHtml(selection.label || 'Selected element') : 'Select an element') + '</h3>' +
                '<p>' + (hasSelection ? 'Edit the selected element, then save the Mockup before generating High Fidelity.' : 'Click a section, heading, button, image, card, or group inside the Mockup preview.') + '</p>';
            if (hasSelection) {
                html += '<div class="ugw-mockup-field-grid">' +
                    '<label>Text<textarea class="ugw-input ugw-mockup-edit-field" data-field="text" rows="3">' + escHtml(selection.text || '') + '</textarea></label>' +
                    '<label>Image URL<input class="ugw-input ugw-mockup-edit-field" data-field="src" value="' + escAttr(selection.src || '') + '"></label>' +
                    '<label>Link URL<input class="ugw-input ugw-mockup-edit-field" data-field="href" value="' + escAttr(selection.href || '') + '"></label>' +
                    '<label>Padding<input class="ugw-input ugw-mockup-edit-field" data-field="padding" value="' + escAttr(selection.padding || '') + '" placeholder="24px"></label>' +
                    '<label>Top Margin<input class="ugw-input ugw-mockup-edit-field" data-field="marginTop" value="' + escAttr(selection.marginTop || '') + '" placeholder="0px"></label>' +
                    '<label>Bottom Margin<input class="ugw-input ugw-mockup-edit-field" data-field="marginBottom" value="' + escAttr(selection.marginBottom || '') + '" placeholder="24px"></label>' +
                    '<label>Align<select class="ugw-input ugw-mockup-edit-field" data-field="align">' +
                        ['left', 'center', 'right'].map(function (value) { return '<option value="' + value + '"' + (selection.align === value ? ' selected' : '') + '>' + value + '</option>'; }).join('') +
                    '</select></label>' +
                    '<label>Background<input type="color" class="ugw-input ugw-mockup-edit-field" data-field="background" value="' + escAttr(selection.background || '#ffffff') + '"></label>' +
                    '<label>Text Color<input type="color" class="ugw-input ugw-mockup-edit-field" data-field="color" value="' + escAttr(selection.color || '#111827') + '"></label>' +
                    '<label>Radius<input class="ugw-input ugw-mockup-edit-field" data-field="radius" value="' + escAttr(selection.radius || '') + '" placeholder="12px"></label>' +
                    '<label class="ugw-mockup-toggle"><input type="checkbox" class="ugw-mockup-edit-field" data-field="visible" ' + (selection.visible === false ? '' : 'checked') + '> Visible</label>' +
                '</div>' +
                '<div class="ugw-mockup-editor-actions">' +
                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" data-mockup-action="duplicate">Duplicate</button>' +
                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" data-mockup-action="remove">Remove</button>' +
                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" data-mockup-action="move-up">Section Up</button>' +
                    '<button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" data-mockup-action="move-down">Section Down</button>' +
                '</div>';
            }
            html += '<button type="button" class="ugw-btn ugw-btn--primary ugw-btn--sm ugw-btn--full" id="ugw-mockup-save-edits">' + (mockupEditorState.dirty ? 'Save Edited Mockup' : 'Save Mockup') + '</button>' +
                '</div>';
            return html;
        }

        function applyMockupEdit(field, value, isChecked) {
            var page = getCurrentCanvasPage();
            var el = findSelectedMockupElement();
            if (!page || !el) return;
            var tag = el.tagName ? el.tagName.toLowerCase() : '';
            var kind = el.dataset.ugEditableKind || inferMockupKind(el);
            if (field === 'text' && tag === 'img') {
                el.setAttribute('alt', value);
            } else if (field === 'text') {
                if (kind !== 'text' && kind !== 'button') return;
                el.textContent = value;
            } else if (field === 'src' && tag === 'img') {
                el.setAttribute('src', value);
            } else if (field === 'href') {
                var link = tag === 'a' ? el : el.closest('a');
                if (link) link.setAttribute('href', value);
            } else if (field === 'padding') {
                el.style.padding = value;
            } else if (field === 'marginTop') {
                el.style.marginTop = value;
            } else if (field === 'marginBottom') {
                el.style.marginBottom = value;
            } else if (field === 'align') {
                el.style.textAlign = value;
            } else if (field === 'background') {
                el.style.backgroundColor = value;
            } else if (field === 'color') {
                el.style.color = value;
            } else if (field === 'radius') {
                el.style.borderRadius = value;
            } else if (field === 'visible') {
                el.style.display = isChecked ? '' : 'none';
            }
            var sp = getStudioPage(page);
            sp.website_draft = sp.website_draft || {};
            sp.website_draft.edit_selection = getMockupElementMeta(el);
            mockupEditorState.dirty = true;
            syncMockupHtmlFromIframe(page);
        }

        function refreshMockupEditableIds(root) {
            if (!root) return;
            var all = root.querySelectorAll('[data-ug-edit-id]');
            Array.prototype.forEach.call(all, function (el, idx) {
                el.dataset.ugEditId = 'ugm-client-copy-' + Date.now() + '-' + idx;
            });
        }

        function runMockupAction(action) {
            var page = getCurrentCanvasPage();
            var el = findSelectedMockupElement();
            if (!page || !el) return;
            var nextSelection = el;
            if (action === 'duplicate') {
                var clone = el.cloneNode(true);
                refreshMockupEditableIds(clone);
                el.parentNode.insertBefore(clone, el.nextSibling);
                nextSelection = clone;
            } else if (action === 'remove') {
                var parent = el.parentElement;
                el.remove();
                nextSelection = parent && parent.closest('[data-ug-edit-id]');
            } else if (action === 'move-up' || action === 'move-down') {
                var section = el.closest('section') || el;
                if (action === 'move-up' && section.previousElementSibling) {
                    section.parentNode.insertBefore(section, section.previousElementSibling);
                } else if (action === 'move-down' && section.nextElementSibling) {
                    section.parentNode.insertBefore(section.nextElementSibling, section);
                }
                nextSelection = section;
            }
            mockupEditorState.dirty = true;
            syncMockupHtmlFromIframe(page);
            if (nextSelection && nextSelection.dataset) {
                mockupEditorState.selectedId = nextSelection.dataset.ugEditId || '';
                getStudioPage(page).website_draft.edit_selection = getMockupElementMeta(nextSelection);
            }
            renderStudioCanvas();
        }

        // Write HTML into Studio preview iframes after DOM insertion.
        hydrateIframes = function() {
            $('.ugw-studio-preview-frame').each(function () {
                var iframe = this;
                var pageId = parseInt($(iframe).data('page-id'));
                var stage = String($(iframe).data('stage') || 'high_fidelity');
                var idx = findPageIndexById(pageId);
                var page = idx >= 0 ? state.canvasPages[idx] : null;
                if (!page) return;

                var html = getStageHtmlForIframe(page, stage);
                if (!html) return;

                var fullHtml = html;
                var doc = '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@latest/font/lucide.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></head><body style="margin:0">' + fullHtml + '</body></html>';
                var hash = studioHash(doc);
                iframe.onload = function () {
                    if (stage === 'website_draft') {
                        wireMockupIframeEditor(iframe, page);
                    }
                };
                if (iframe.dataset.renderHash !== hash) {
                    iframe.dataset.renderHash = hash;
                    iframe.srcdoc = doc;
                } else if (stage === 'website_draft') {
                    wireMockupIframeEditor(iframe, page);
                }
            });
        };

        function updateCanvasProgress() {
            var total = (state.canvasPages || []).length;
            var done = (state.canvasPages || []).filter(function(p) {
                return p.approved || isStageApproved(getStudioPage(p), 'animations');
            }).length;
            var pct = total > 0 ? Math.round((done / total) * 100) : 0;
            $('#ugw-canvas-progress-text').text(done + ' / ' + total);
            $('#ugw-canvas-progress-fill').css('width', pct + '%');
            $('#ugw-canvas-publish-all').prop('disabled', !(done >= total && total > 0));
        }

        var tokenSaveTimer = null;
        function persistCanvasTokens() {
            if (!state.projectId) return;
            state.extractedPrimary = $('#ugw-token-primary').val() || '#1B2A4A';
            state.extractedSecondary = $('#ugw-token-accent').val() || '#00D4AA';
            clearTimeout(tokenSaveTimer);
            tokenSaveTimer = setTimeout(function () {
                post('ugentic_wizard_update_design_tokens', {
                    project_id: state.projectId,
                    tokens: JSON.stringify({
                        'colors.primary': state.extractedPrimary,
                        'colors.secondary': state.extractedSecondary,
                        'colors.accent': state.extractedSecondary,
                    }),
                }).done(function (res) {
                    if (res.success) {
                        state.designTokens = res.data.tokens || state.designTokens || {};
                    }
                });
            }, 350);
        }

        $('#ugw-token-primary, #ugw-token-accent').on('input change', persistCanvasTokens);

        function mergeStudioResponse(data, pageId, stage) {
            data = data || {};
            if (data.design_studio_v2 || data.design_studio) {
                state.designStudio = data.design_studio_v2 || data.design_studio;
                if (state.designStudio && state.designStudio.pages) {
                    (state.canvasPages || []).forEach(function (p) {
                        var key = String(p.id || '');
                        if (state.designStudio.pages[key]) {
                            p.studio = state.designStudio.pages[key];
                        }
                    });
                }
            }

            var idx = findPageIndexById(pageId);
            if (idx < 0) return;
            var page = state.canvasPages[idx];
            if (data.studio_page) {
                page.studio = data.studio_page;
            }

            var sp = getStudioPage(page);
            if (stage === 'high_fidelity' && data.html) {
                page.html = data.html;
                page.has_design = true;
                page.generation_status = 'complete';
                sp.high_fidelity = sp.high_fidelity || {};
                sp.high_fidelity.html = data.html;
            }
            if (stage === 'animations' && data.html) {
                page.html = data.html;
                page.has_design = true;
                page.generation_status = 'complete';
                sp.animations = sp.animations || {};
                sp.animations.html = data.html;
            }

            if (sp.high_fidelity) {
                if (sp.high_fidelity.header_html) state.canvasHeader = sp.high_fidelity.header_html;
                if (sp.high_fidelity.footer_html) state.canvasFooter = sp.high_fidelity.footer_html;
            }
        }

        function collectStudioStageData(stage) {
            if (stage === 'animations') {
                return {
                    animation_level: $('input[name="ugw-animation-level"]:checked').val() || ((getStudioPage(getCurrentCanvasPage()).animations || {}).animation_level) || 'balanced'
                };
            }

            if (stage === 'website_draft') {
                var page = getCurrentCanvasPage();
                if (!page) return null;
                var sp = getStudioPage(page);
                var draft = sp.website_draft || {};
                var html = syncMockupHtmlFromIframe(page) || (sp.website_draft && sp.website_draft.html) || '';
                return {
                    html: html,
                    selected_design_id: draft.selected_design_id || '',
                    mockup_image_url: draft.mockup_image_url || '',
                    mockup_image_attachment_id: draft.mockup_image_attachment_id || 0,
                    edited_by_user: !!mockupEditorState.dirty,
                    edit_manifest: Object.assign({}, (sp.website_draft && sp.website_draft.edit_manifest) || {}, {
                        selected_id: mockupEditorState.selectedId || '',
                        inspector_saved: true,
                        saved_at: new Date().toISOString()
                    }),
                    style_system: (sp.website_draft && sp.website_draft.style_system) || (sp.style_system && sp.style_system.data) || {}
                };
            }

            if (stage === 'brief' && $('.ugw-studio-brief-editor').length) {
                var brief = {};
                $('.ugw-brief-field').each(function () {
                    var field = String($(this).data('brief-field') || '');
                    if (!field) return;
                    if (field === 'must_include' || field === 'avoid') {
                        brief[field] = String($(this).val() || '').split(/\r?\n/).map(function (item) { return item.trim(); }).filter(Boolean);
                    } else {
                        brief[field] = $(this).val() || '';
                    }
                });
                brief.brand_logo_asset = getStudioLogoAsset(getCurrentCanvasPage()) || {};
                return brief;
            }

            if (stage === 'style_system' && $('.ugw-style-system-preview').length) {
                var style = { palette: {}, typography: {}, spacing: {}, radii: {} };
                $('.ugw-style-field').each(function () {
                    var path = String($(this).data('style-path') || '');
                    var value = $(this).val() || '';
                    if (path === 'motion') {
                        style.motion = String(value).split(/\r?\n/).map(function (item) { return item.trim(); }).filter(Boolean);
                    } else if (path.indexOf('.') > 0) {
                        var parts = path.split('.');
                        style[parts[0]] = style[parts[0]] || {};
                        style[parts[0]][parts[1]] = value;
                    } else if (path) {
                        style[path] = value;
                    }
                });
                return style;
            }

            if ((stage !== 'outline' && stage !== 'full_content') || !$('.ugw-studio-content-editor').length) {
                return null;
            }
            var contentMode = stage === 'outline' ? 'outline' : 'full';

            var data = {
                content_mode: contentMode,
                target_section_count: getInspectorSectionCount(getCurrentCanvasPage()),
                outline_generated: true,
                full_content_generated: contentMode !== 'outline',
                page_goal: $('[data-content-field="page_goal"]').val() || '',
                narrative_angle: $('[data-content-field="narrative_angle"]').val() || '',
                primary_cta: $('[data-content-field="primary_cta"]').val() || '',
                secondary_cta: $('[data-content-field="secondary_cta"]').val() || '',
                sections: []
            };

            $('.ugw-content-section').each(function (idx) {
                var section = {
                    id: '',
                    type: '',
                    heading: '',
                    subheading: '',
                    body: '',
                    cta: '',
                    trust_signal: '',
                    seo_keywords: []
                };
                $(this).find('.ugw-content-section-field').each(function () {
                    var field = String($(this).data('section-field') || '');
                    if (!field) return;
                    section[field] = $(this).val() || '';
                });
                section.id = slugify(section.heading || section.type || ('section-' + (idx + 1)));
                data.sections.push(section);
            });

            return data;
        }

        function slugify(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'section';
        }

        function syncContentEditorToState() {
            var page = getCurrentCanvasPage();
            if (!page || (state.studioActiveStage !== 'outline' && state.studioActiveStage !== 'full_content')) return;
            var data = collectStudioStageData(state.studioActiveStage);
            if (!data) return;
            var sp = getStudioPage(page);
            var key = state.studioActiveStage;
            sp[key] = sp[key] || {};
            sp[key].data = data;
            sp.content = sp[key];
            sp.stage_status = sp.stage_status || {};
            sp.stage_status[key] = 'complete';
        }

        function saveReferenceVotes(skipReferences) {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var sp = getStudioPage(page);
            var board = getReferenceBoard(sp);
            post('ugentic_wizard_design_studio_v2_save_reference_votes', {
                project_id: state.projectId,
                page_id: page.id,
                liked_reference_ids: JSON.stringify(board.liked_reference_ids || []),
                disliked_reference_ids: JSON.stringify(board.disliked_reference_ids || []),
                pinned_reference_ids: JSON.stringify(board.pinned_reference_ids || []),
                reference_board: JSON.stringify(board || {}),
                skip_references: skipReferences ? 'yes' : ''
            }).done(function (res) {
                if (res.success) {
                    mergeStudioResponse(res.data, page.id, 'references');
                    renderStudioCanvas();
                } else {
                    toast(res.data && (res.data.message || res.data.error) ? (res.data.message || res.data.error) : 'Could not save reference choices', 'error');
                    renderStudioCanvas();
                }
            }).fail(function () {
                toast('Connection error while saving reference choices', 'error');
                renderStudioCanvas();
            });
        }

        function toggleReferenceVote(referenceId, vote) {
            var page = getCurrentCanvasPage();
            if (!page || !referenceId) return;
            var sp = getStudioPage(page);
            sp.references = sp.references || {};
            var board = getReferenceBoard(sp);
            function toggle(list, id) {
                list = Array.isArray(list) ? list : [];
                var idx = list.indexOf(id);
                if (idx >= 0) {
                    list.splice(idx, 1);
                } else {
                    list.push(id);
                }
                return list;
            }
            function remove(list, id) {
                list = Array.isArray(list) ? list : [];
                return list.filter(function (item) { return item !== id; });
            }

            if (vote === 'like') {
                board.liked_reference_ids = toggle(board.liked_reference_ids, referenceId);
                board.disliked_reference_ids = remove(board.disliked_reference_ids, referenceId);
            } else if (vote === 'pin') {
                board.pinned_reference_ids = toggle(board.pinned_reference_ids, referenceId);
                board.disliked_reference_ids = remove(board.disliked_reference_ids, referenceId);
            } else if (vote === 'dislike') {
                board.disliked_reference_ids = toggle(board.disliked_reference_ids, referenceId);
                board.liked_reference_ids = remove(board.liked_reference_ids, referenceId);
                board.pinned_reference_ids = remove(board.pinned_reference_ids, referenceId);
            }
            board.skipped = false;
            sp.references.reference_board = board;
            sp.reference_board = board;
            renderStudioCanvas();
            saveReferenceVotes(false);
        }

        function saveStudioInput(callback, errorCallback) {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var stage = state.studioActiveStage || 'brief';
            var notes = $('#ugw-studio-notes').val() || '';
            var stageData = collectStudioStageData(stage);
            stageData = stageData || {};
            stageData.media_assets = getStudioMediaAssets(page);

            var payload = {
                project_id: state.projectId,
                page_id: page.id,
                artifact: stage,
                stage: stage,
                user_notes: notes
            };
            payload.artifact_data = JSON.stringify(stageData);

            post('ugentic_wizard_design_studio_v2_save_artifact', payload).done(function (res) {
                if (res.success) {
                    mergeStudioResponse(res.data, page.id, stage);
                    if (callback) callback();
                } else {
                    toast(res.data && (res.data.message || res.data.error) ? (res.data.message || res.data.error) : 'Could not save Studio input', 'error');
                    if (errorCallback) errorCallback();
                }
            }).fail(function () {
                toast('Connection error while saving Studio input', 'error');
                if (errorCallback) errorCallback();
            });
        }

        function generateStudioStage(revisionNotes, options) {
            var page = getCurrentCanvasPage();
            if (!page) return;
            options = options || {};
            var stage = state.studioActiveStage || 'brief';
            var sp = getStudioPage(page);
            var sectionCount = options.sectionCount || ((stage === 'outline' || stage === 'full_content') ? getInspectorSectionCount(page) : 0);
            sp.stage_status = sp.stage_status || {};
            sp.stage_status[stage] = 'generating';
            renderStudioCanvas();

            var payload = {
                project_id: state.projectId,
                page_id: page.id,
                artifact: stage,
                stage: stage,
                quality_mode: 'studio',
                revision_target: revisionNotes || ''
            };
            if (stage === 'outline' || stage === 'full_content') {
                payload.stage_variant = stage === 'outline' ? 'outline' : 'full';
                payload.section_count = sectionCount;
            }

            post('ugentic_wizard_design_studio_v2_generate_artifact', payload).done(function (res) {
                if (res.success) {
                    mergeStudioResponse(res.data, page.id, stage);
                    toast((res.data && res.data.message) ? res.data.message : STUDIO_LABELS[stage] + ' generated', 'success');
                } else {
                    sp.stage_status[stage] = 'failed';
                    toast(res.data && (res.data.message || res.data.error) ? (res.data.message || res.data.error) : 'Stage generation failed', 'error');
                }
                renderStudioCanvas();
            }).fail(function () {
                sp.stage_status[stage] = 'pending';
                renderStudioCanvas();
                toast('Connection timed out while generating ' + STUDIO_LABELS[stage] + '. Refreshing Studio state...', 'error');
                refreshStudioState(function () {
                    renderStudioCanvas();
                });
            });
        }

        function refreshStudioState(callback) {
            post('ugentic_wizard_design_studio_v2_get_state', {
                project_id: state.projectId
            }).done(function (res) {
                if (res.success) {
                    state.canvasPages = res.data.pages || state.canvasPages || [];
                    state.designStudio = res.data.design_studio_v2 || res.data.design_studio || state.designStudio || {};
                    state.canvasHeader = (res.data.canvas_state && res.data.canvas_state.global_header_html) || state.canvasHeader || '';
                    state.canvasFooter = (res.data.canvas_state && res.data.canvas_state.global_footer_html) || state.canvasFooter || '';
                    if (callback) callback();
                    renderStudioCanvas();
                }
            });
        }

        $(document).on('click', '.ugw-studio-page-btn', function () {
            syncContentEditorToState();
            state.canvasCurrentIndex = parseInt($(this).data('idx'));
            state.studioActiveStage = resolveActiveStage(getCurrentCanvasPage());
            renderStudioCanvas();
        });

        $(document).on('click', '.ugw-studio-tab', function () {
            syncContentEditorToState();
            state.studioActiveStage = String($(this).data('stage') || 'brief');
            renderStudioCanvas();
        });

        $(document).on('click', '.ugw-studio-artboard-head', function () {
            syncContentEditorToState();
            var idx = parseInt($(this).closest('.ugw-studio-artboard-node').data('idx'), 10);
            if (!isNaN(idx)) {
                state.canvasCurrentIndex = Math.max(0, Math.min(idx, (state.canvasPages || []).length - 1));
                state.studioActiveStage = resolveActiveStage(getCurrentCanvasPage());
                renderStudioCanvas();
            }
        });

        $(document).on('click', '.ugw-studio-zoom', function () {
            var delta = parseFloat($(this).data('zoom') || 0);
            state.studioZoom = Math.max(0.25, Math.min(1.25, getStudioZoom() + delta));
            renderStudioCanvas();
        });

        $(document).on('click', '.ugw-studio-zoom-fit', function () {
            state.studioZoom = state.studioViewport === 'mobile' ? 0.88 : (state.studioViewport === 'tablet' ? 0.62 : 0.52);
            renderStudioCanvas();
        });

        $(document).on('click', '.ugw-studio-viewport', function () {
            state.studioViewport = String($(this).data('viewport') || 'desktop');
            $('.ugw-studio-viewport').removeClass('is-active');
            $(this).addClass('is-active');
            renderStudioCanvas();
        });

        $(document).on('click', '.ugw-design-concept-select', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var sp = getStudioPage(page);
            var draft = sp.website_draft || {};
            var conceptId = String($(this).data('concept-id') || '');
            var concepts = Array.isArray(draft.design_concepts) ? draft.design_concepts : [];
            var selected = null;
            concepts.forEach(function (concept) {
                concept.selected = concept.id === conceptId;
                if (concept.selected) selected = concept;
            });
            if (!selected) return;
            draft.selected_design_id = conceptId;
            draft.mockup_image_url = selected.image_url || '';
            draft.mockup_image_attachment_id = selected.attachment_id || 0;
            draft.mockup_visual_prompt = selected.prompt || '';
            draft.mockup_generation_provider = selected.provider || '';
            draft.mockup_image_model = selected.model || '';
            sp.website_draft = draft;
            toast('Design selected. This style will guide the rest of the pages.', 'success');
            renderStudioCanvas();
            saveStudioInput();
        });

        $(document).on('click', '#ugw-studio-add-media', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            if (!window.wp || !wp.media) {
                toast('WordPress media library is not available on this screen.', 'error');
                return;
            }
            var frame = wp.media({
                title: 'Select page media',
                multiple: true,
                library: { type: ['image', 'video'] },
                button: { text: 'Use media' }
            });
            frame.on('select', function () {
                var assets = getStudioMediaAssets(page);
                frame.state().get('selection').each(function (item) {
                    var a = item.toJSON();
                    assets.push({
                        attachment_id: a.id || 0,
                        url: a.url || '',
                        mime_type: a.mime || '',
                        kind: /^video\//i.test(a.mime || '') ? 'video' : 'product_image',
                        role: assets.length === 0 ? 'hero' : 'general',
                        title: a.title || a.filename || 'Uploaded media',
                        poster_url: a.image && a.image.src ? a.image.src : '',
                        ai_usage_status: 'required',
                        warnings: []
                    });
                });
                renderStudioCanvas();
                saveStudioInput();
            });
            frame.open();
        });

        $(document).on('click', '#ugw-studio-add-logo', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            if (!window.wp || !wp.media) {
                toast('WordPress media library is not available on this screen.', 'error');
                return;
            }
            var frame = wp.media({
                title: 'Select brand logo',
                multiple: false,
                library: { type: ['image'] },
                button: { text: 'Use logo' }
            });
            frame.on('select', function () {
                var item = frame.state().get('selection').first();
                if (!item) return;
                var a = item.toJSON();
                var logo = {
                    attachment_id: a.id || 0,
                    url: a.url || '',
                    mime_type: a.mime || '',
                    kind: 'logo',
                    role: 'logo',
                    title: a.title || a.filename || 'Brand logo',
                    poster_url: '',
                    ai_usage_status: 'required_brand_logo',
                    warnings: []
                };
                var sp = getStudioPage(page);
                sp.brand_logo_asset = logo;
                var assets = getStudioMediaAssets(page).filter(function (asset) {
                    return !(asset && (asset.kind === 'logo' || asset.role === 'logo' || asset.role === 'brand'));
                });
                assets.unshift(logo);
                sp.media_assets = assets;
                renderStudioCanvas();
                saveStudioInput();
            });
            frame.open();
        });

        $(document).on('click', '#ugw-remove-logo', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var sp = getStudioPage(page);
            sp.brand_logo_asset = null;
            sp.media_assets = getStudioMediaAssets(page).filter(function (asset) {
                return !(asset && (asset.kind === 'logo' || asset.role === 'logo' || asset.role === 'brand'));
            });
            renderStudioCanvas();
            saveStudioInput();
        });

        $(document).on('change input', '.ugw-media-field', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var idx = parseInt($(this).closest('.ugw-studio-media-card').data('media-idx'), 10);
            var field = String($(this).data('media-field') || '');
            var assets = getStudioMediaAssets(page);
            if (!isNaN(idx) && assets[idx] && field) {
                assets[idx][field] = $(this).val() || '';
            }
        });

        $(document).on('click', '.ugw-remove-media', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var idx = parseInt($(this).closest('.ugw-studio-media-card').data('media-idx'), 10);
            var assets = getStudioMediaAssets(page);
            if (!isNaN(idx)) {
                assets.splice(idx, 1);
                renderStudioCanvas();
                saveStudioInput();
            }
        });

        $(document).on('click', '.ugw-round-preview-btn', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var round = String($(this).data('round') || '');
            var sp = getStudioPage(page);
            var previews = (sp.high_fidelity && Array.isArray(sp.high_fidelity.round_previews)) ? sp.high_fidelity.round_previews : [];
            var preview = previews.find(function (item) {
                return String(item.round || '') === round;
            });
            if (!preview || !preview.html) {
                toast('Round preview is not available yet', 'error');
                return;
            }
            sp.high_fidelity.preview_html = preview.html;
            sp.high_fidelity.preview_label = 'Viewing round ' + round;
            renderStudioCanvas();
        });

        $(document).on('click', '.ugw-round-final-btn', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var sp = getStudioPage(page);
            if (sp.high_fidelity) {
                delete sp.high_fidelity.preview_html;
                delete sp.high_fidelity.preview_label;
            }
            renderStudioCanvas();
        });

        $(document).on('input change', '.ugw-mockup-edit-field', function () {
            var field = String($(this).data('field') || '');
            if (!field) return;
            applyMockupEdit(field, $(this).val() || '', $(this).is(':checked'));
        });

        $(document).on('click', '[data-mockup-action]', function () {
            runMockupAction(String($(this).data('mockup-action') || ''));
        });

        $(document).on('click', '#ugw-mockup-save-edits', function () {
            var $btn = $(this).prop('disabled', true).text('Saving...');
            saveStudioInput(function () {
                mockupEditorState.dirty = false;
                $btn.prop('disabled', false).text('Save Mockup');
                toast('Edited Mockup saved', 'success');
                refreshStudioState(function () {
                    renderStudioCanvas();
                });
            }, function () {
                $btn.prop('disabled', false).text('Save Mockup');
            });
        });

        $(document).on('click', '#ugw-studio-save-input', function () {
            var $btn = $(this).prop('disabled', true).text('Saving...');
            saveStudioInput(function () {
                if ((state.studioActiveStage || '') === 'website_draft') {
                    mockupEditorState.dirty = false;
                }
                $btn.prop('disabled', false).text('Save Input');
                toast('Stage input saved', 'success');
            });
            setTimeout(function () {
                $btn.prop('disabled', false).text('Save Input');
            }, 3000);
        });

        $(document).on('click', '#ugw-studio-generate-stage', function () {
            saveStudioInput(function () {
                generateStudioStage('');
            });
        });

        $(document).on('click', '#ugw-studio-generate-outline', function () {
            state.studioActiveStage = 'outline';
            saveStudioInput(function () {
                generateStudioStage('', {
                    stageVariant: 'outline',
                    sectionCount: getInspectorSectionCount(getCurrentCanvasPage())
                });
            });
        });

        $(document).on('click', '#ugw-studio-generate-full-content', function () {
            state.studioActiveStage = 'full_content';
            saveStudioInput(function () {
                generateStudioStage('', {
                    stageVariant: 'full',
                    sectionCount: getInspectorSectionCount(getCurrentCanvasPage())
                });
            });
        });

        $(document).on('click', '#ugw-reference-search', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var sp = getStudioPage(page);
            var query = $('#ugw-reference-query').val() || '';
            var inspirationUrls = $('#ugw-reference-urls').val() || '';
            sp.references = sp.references || {};
            sp.references.reference_board = {
                query: query,
                user_reference_urls: inspirationUrls,
                references: [],
                liked_reference_ids: [],
                disliked_reference_ids: [],
                pinned_reference_ids: [],
                selected_references: [],
                selection_count: 0,
                min_required: 3,
                skipped: false
            };
            sp.reference_board = sp.references.reference_board;
            sp.stage_status.references = 'generating';
            renderStudioCanvas();
            post('ugentic_wizard_design_studio_v2_search_references', {
                project_id: state.projectId,
                page_id: page.id,
                query: query,
                inspiration_urls: inspirationUrls,
                limit: 6,
                sources: 'google'
            }).done(function (res) {
                if (res.success) {
                    mergeStudioResponse(res.data, page.id, 'references');
                    toast('Reference Board ready', 'success');
                } else {
                    sp.stage_status.references = 'failed';
                    toast(res.data && (res.data.message || res.data.error) ? (res.data.message || res.data.error) : 'Reference search failed', 'error');
                }
                renderStudioCanvas();
            }).fail(function () {
                sp.stage_status.references = 'failed';
                renderStudioCanvas();
                toast('Connection error while searching references', 'error');
            });
        });

        $(document).on('click', '.ugw-reference-vote', function () {
            var refId = $(this).closest('.ugw-reference-card').data('reference-id');
            var vote = String($(this).data('vote') || '');
            toggleReferenceVote(String(refId || ''), vote);
        });

        $(document).on('click', '#ugw-reference-skip', function () {
            if (!confirm('Skip reference selection? High Fidelity will use industry playbooks and may be less specific.')) return;
            var page = getCurrentCanvasPage();
            if (!page) return;
            var sp = getStudioPage(page);
            var board = getReferenceBoard(sp);
            board.skipped = true;
            sp.references = sp.references || {};
            sp.references.reference_board = board;
            sp.reference_board = board;
            saveReferenceVotes(true);
        });

        $(document).on('click', '#ugw-reference-clear', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var sp = getStudioPage(page);
            var board = getReferenceBoard(sp);
            board.liked_reference_ids = [];
            board.pinned_reference_ids = [];
            board.disliked_reference_ids = [];
            board.selected_references = [];
            board.selection_count = 0;
            board.skipped = false;
            (board.references || []).forEach(function (ref) {
                ref.liked = false;
                ref.pinned = false;
                ref.disliked = false;
            });
            sp.references = sp.references || {};
            sp.references.reference_board = board;
            sp.reference_board = board;
            renderStudioCanvas();
            saveReferenceVotes(false);
        });

        $(document).on('input change', '.ugw-content-field, .ugw-content-section-field, #ugw-studio-section-count', function () {
            syncContentEditorToState();
        });

        $(document).on('click', '#ugw-content-add-section', function () {
            syncContentEditorToState();
            var page = getCurrentCanvasPage();
            var sp = getStudioPage(page);
            var key = state.studioActiveStage === 'full_content' ? 'full_content' : 'outline';
            sp[key] = sp[key] || {};
            sp[key].data = sp[key].data || {};
            sp[key].data.sections = sp[key].data.sections || [];
            sp[key].data.sections.push({
                id: 'new-section',
                type: 'content',
                heading: 'New Section',
                subheading: '',
                body: 'Add the section copy here.',
                cta: '',
                trust_signal: '',
                seo_keywords: []
            });
            sp.content = sp[key];
            renderStudioCanvas();
        });

        $(document).on('click', '.ugw-content-remove-section', function () {
            syncContentEditorToState();
            var idx = parseInt($(this).closest('.ugw-content-section').data('section-idx'));
            var page = getCurrentCanvasPage();
            var sp = getStudioPage(page);
            var key = state.studioActiveStage === 'full_content' ? 'full_content' : 'outline';
            if (sp[key] && sp[key].data && sp[key].data.sections && sp[key].data.sections.length > 1) {
                sp[key].data.sections.splice(idx, 1);
                sp.content = sp[key];
                renderStudioCanvas();
            } else {
                toast('Keep at least one content section', 'error');
            }
        });

        $(document).on('click', '#ugw-studio-revise-stage', function () {
            var notes = $('#ugw-studio-notes').val().trim();
            if (!notes) {
                toast('Add revision notes first', 'error');
                $('#ugw-studio-notes').focus();
                return;
            }

            var page = getCurrentCanvasPage();
            var stage = state.studioActiveStage || 'brief';
            var sp = getStudioPage(page);
            sp.stage_status[stage] = 'generating';
            renderStudioCanvas();

            post('ugentic_wizard_design_studio_v2_generate_artifact', {
                project_id: state.projectId,
                page_id: page.id,
                artifact: stage,
                stage: stage,
                revision_target: notes
            }).done(function (res) {
                if (res.success) {
                    mergeStudioResponse(res.data, page.id, stage);
                    toast(STUDIO_LABELS[stage] + ' revised', 'success');
                } else {
                    sp.stage_status[stage] = 'failed';
                    toast(res.data && res.data.message ? res.data.message : 'Revision failed', 'error');
                }
                renderStudioCanvas();
            }).fail(function () {
                sp.stage_status[stage] = 'pending';
                renderStudioCanvas();
                toast('Connection timed out while revising stage. Refreshing Studio state...', 'error');
                refreshStudioState(function () {
                    renderStudioCanvas();
                });
            });
        });

        $(document).on('click', '#ugw-studio-approve-stage', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            var stage = state.studioActiveStage || 'brief';
            var $btn = $(this).prop('disabled', true).text('Approving...');

            saveStudioInput(function () {
                post('ugentic_wizard_design_studio_v2_approve_artifact', {
                    project_id: state.projectId,
                    page_id: page.id,
                    artifact: stage,
                    stage: stage
                }).done(function (res) {
                    if (res.success) {
                        mergeStudioResponse(res.data, page.id, stage);
                        var sp = getStudioPage(page);
                        if (stage === 'animations') {
                            page.approved = true;
                            if (!allCanvasPagesApproved()) {
                                var nextIdx = firstUnapprovedPageIndex();
                                if (nextIdx !== state.canvasCurrentIndex) {
                                    state.canvasCurrentIndex = nextIdx;
                                }
                            }
                            state.studioActiveStage = resolveActiveStage(getCurrentCanvasPage());
                        } else {
                            state.studioActiveStage = nextStage(stage) || 'animations';
                            sp.stage = state.studioActiveStage;
                        }
                        toast(STUDIO_LABELS[stage] + ' approved', 'success');
                        renderStudioCanvas();
                    } else {
                        $btn.prop('disabled', false).text('Approve Stage');
                        toast(res.data && (res.data.message || res.data.error) ? (res.data.message || res.data.error) : 'Could not approve stage', 'error');
                    }
                }).fail(function () {
                    $btn.prop('disabled', false).text('Approve Stage');
                    toast('Connection error while approving stage', 'error');
                });
            }, function () {
                $btn.prop('disabled', false).text('Approve Stage');
            });
        });

        $(document).on('click', '#ugw-studio-fast-draft', function () {
            var page = getCurrentCanvasPage();
            if (!page) return;
            if (!confirm('Fast Draft skips the Studio critique loop and may be less unique. Continue?')) return;

            var sp = getStudioPage(page);
            state.studioActiveStage = 'high_fidelity';
            sp.stage_status.high_fidelity = 'generating';
            renderStudioCanvas();

            post('ugentic_wizard_generate_single_page', {
                project_id: state.projectId,
                page_id: page.id
            }).done(function (res) {
                if (res.success) {
                    page.generation_status = 'complete';
                    page.has_design = true;
                    page.html = res.data.html || '';
                    if (state.canvasCurrentIndex === 0) {
                        state.canvasHeader = res.data.header_html || state.canvasHeader || '';
                        state.canvasFooter = res.data.footer_html || state.canvasFooter || '';
                    }
                    refreshStudioState(function () {
                        state.studioActiveStage = 'high_fidelity';
                    });
                    toast('Fast Draft generated', 'success');
                } else {
                    sp.stage_status.high_fidelity = 'failed';
                    renderStudioCanvas();
                    toast(res.data && res.data.message ? res.data.message : 'Fast Draft failed', 'error');
                }
            }).fail(function () {
                sp.stage_status.high_fidelity = 'failed';
                renderStudioCanvas();
                toast('Connection error during Fast Draft', 'error');
            });
        });

        generatePageAtIndex = function(idx) {
            state.canvasCurrentIndex = Math.max(0, Math.min(idx || 0, (state.canvasPages || []).length - 1));
            state.studioActiveStage = resolveActiveStage(getCurrentCanvasPage());
            renderStudioCanvas();
        };

        function closeCanvasView() {
            $('body').removeClass('ugw-studio-fullscreen-mode');
            $('#ugw-canvas-view').hide();
            if (state.projectId && state.pages && state.pages.length) {
                $('#ugw-wizard-layout').show();
            } else {
                $('#ugw-start-screen').show();
            }
        }

        // Back button — return to wizard or start screen
        $('#ugw-canvas-back').on('click', function () {
            if (!confirm('Exit Studio? Your progress is saved.')) return;
            closeCanvasView();
        });

        $(document).on('keydown', function (event) {
            if (event.key !== 'Escape' || !$('body').hasClass('ugw-studio-fullscreen-mode')) return;
            var target = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
            if (target === 'textarea' || target === 'input' || target === 'select') return;
            closeCanvasView();
        });

        // Publish All
        $('#ugw-canvas-publish-all').on('click', function () {
            var $btn = $(this).prop('disabled', true).text('Publishing...');
            post('ugentic_wizard_publish_prepare', { project_id: state.projectId })
                .done(function (prepRes) {
                    if (!prepRes.success) {
                        toast('Preparation failed', 'error');
                        $btn.prop('disabled', false).text('🚀 Publish All');
                        return;
                    }
                    var pages = prepRes.data.pages || [];
                    var hId = prepRes.data.header_asset_id || 0;
                    var fId = prepRes.data.footer_asset_id || 0;

                    function pubNext(i) {
                        if (i >= pages.length) {
                            post('ugentic_wizard_publish', { project_id: state.projectId, finalize_only: 1 })
                                .always(function () {
                                    $btn.text('✅ Published!');
                                    toast('All pages published to WordPress! 🎉', 'success');
                                });
                            return;
                        }
                        $btn.text('Publishing ' + (i + 1) + '/' + pages.length + '...');
                        post('ugentic_wizard_publish_page', {
                            project_id: state.projectId,
                            page_id: pages[i].id,
                            header_asset_id: hId,
                            footer_asset_id: fId,
                        }).done(function () { pubNext(i + 1); })
                          .fail(function () { pubNext(i + 1); });
                    }
                    pubNext(0);
                })
                .fail(function () {
                    toast('Connection error', 'error');
                    $btn.prop('disabled', false).text('🚀 Publish All');
                });
        });

        // -- Step nav --
        $(document).on('click', '#ugw-steps-nav .ugw-step', function () {
            var step = $(this).data('step');
            goToStep(step);
        });

        // -- Discovery ---
        $('#ugw-discovery-send').on('click', function () {
            var val = $('#ugw-discovery-input').val().trim();
            if (!val) return;
            sendDiscoveryAnswer(val);
        });

        $('#ugw-discovery-input').on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $('#ugw-discovery-send').click();
            }
        });

        // Chip selection
        $(document).on('click', '#ugw-discovery-chips .ugw-chip', function () {
            if (currentQuestion && currentQuestion.type === 'multi_select') {
                $(this).toggleClass('ugw-chip--selected');
            } else {
                sendDiscoveryAnswer($(this).data('value'));
            }
        });

        // Multi-select done → collect selected chips
        $(document).on('click', '#ugw-discovery-chips .ugw-chip--selected', function () {
            // Toggle handled above
        });

        // Check if multi_select and auto-send handled by a Done button
        // Actually, for multi_select, add a "Done" button under chips
        // Multi-select done → collect selected chips
        $(document).on('click', '.ugw-multi-select-done', function () {
            var selected = [];
            $('#ugw-discovery-chips .ugw-chip--selected').each(function () {
                selected.push($(this).data('value'));
            });
            if (selected.length) sendDiscoveryAnswer(selected);
        });

        // Multi-input
        $(document).on('keydown', '#ugw-mi-input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var val = $(this).val().trim();
                if (!val) return;
                $(this).val('');
                var tag = '<span class="ugw-multi-tag">' + escHtml(val) + '<button class="ugw-multi-tag-remove" type="button">×</button></span>';
                $(tag).insertBefore($(this));
            }
        });

        $(document).on('click', '.ugw-multi-tag-remove', function () {
            $(this).parent('.ugw-multi-tag').remove();
        });

        $(document).on('click', '#ugw-mi-done', function () {
            var items = [];
            $('#ugw-mi-wrapper .ugw-multi-tag').each(function () {
                items.push($(this).contents().first().text().trim());
            });
            if (items.length) sendDiscoveryAnswer(items);
        });

        // Contact form done
        $(document).on('click', '#ugw-contact-done', function () {
            var data = {};
            $('.ugw-contact-field').each(function () {
                var key = $(this).data('key');
                var val = $(this).val().trim();
                if (val) data[key] = val;
            });
            sendDiscoveryAnswer(data);
        });

        // Discovery skip
        $(document).on('click', '.ugw-discovery-skip', function () {
            sendDiscoveryAnswer('');
        });

        // -- Logo --
        $(document).on('click', '#ugw-logo-icon-styles .ugw-card, #ugw-logo-moods .ugw-card, #ugw-logo-shapes .ugw-card', function () {
            var $this = $(this);
            var type = $this.data('type');
            $this.siblings().removeClass('ugw-card--selected');
            $this.addClass('ugw-card--selected');
            if (type === 'icon_style') state.selectedIconStyle = $this.data('value');
            if (type === 'mood') state.selectedMood = $this.data('value');
            if (type === 'shape') state.selectedShape = $this.data('value');
        });

        $('#ugw-logo-generate').on('click', function () {
            generateLogo('ugentic_wizard_generate_logo');
        });

        $('#ugw-logo-love').on('click', function () {
            if (!state.currentLogoData) return;
            post('ugentic_wizard_approve_logo', {
                project_id: state.projectId,
                image_data: state.currentLogoData,
                mime_type: state.currentLogoMime || 'image/png',
            }).done(function (res) {
                if (res.success) {
                    toast('Logo approved! 🎉', 'success');
                    nextStep();
                }
            });
        });

        $('#ugw-logo-change').on('click', function () {
            $('#ugw-logo-feedback-area').show();
        });

        $('#ugw-logo-cancel-feedback').on('click', function () {
            $('#ugw-logo-feedback-area').hide();
        });

        $('#ugw-logo-apply-changes').on('click', function () {
            var feedback = $('#ugw-logo-feedback').val().trim();
            if (!feedback) return;
            $('#ugw-logo-feedback-area').hide();
            generateLogo('ugentic_wizard_iterate_logo', {
                feedback: feedback,
                current_image: $('#ugw-logo-img').attr('src'),
            });
        });

        $('#ugw-logo-retry').on('click', function () {
            generateLogo('ugentic_wizard_fresh_logo');
        });

        $('#ugw-logo-skip').on('click', function () {
            post('ugentic_wizard_skip_logo', { project_id: state.projectId }).done(function () {
                nextStep();
            });
        });

        // Logo upload
        $('#ugw-logo-upload-btn').on('click', function () {
            $('#ugw-logo-file-input').click();
        });

        $('#ugw-logo-file-input').on('change', function () {
            var file = this.files[0];
            if (!file) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                var dataUrl = e.target.result;
                var base64 = dataUrl.split(',')[1];
                var mime = file.type || 'image/png';

                // Show in preview
                $('#ugw-logo-options').hide();
                $('#ugw-logo-preview-area').show();
                $('#ugw-logo-img').attr('src', dataUrl).show();
                $('#ugw-logo-loading').hide();

                state.currentLogoData = base64;
                state.currentLogoMime = mime;

                toast('Logo uploaded! Click "Love it!" to use it.', 'success');
            };
            reader.readAsDataURL(file);
        });

        $(document).on('click', '.ugw-logo-thumb', function () {
            var idx = $(this).data('idx');
            var src = state.logoHistory[idx];
            if (src) {
                $('#ugw-logo-img').attr('src', src);
                // Update currentLogoData
                var base64 = src.split(',')[1];
                if (base64) state.currentLogoData = base64;
            }
        });

        // -- Brand --
        $(document).on('click', '#ugw-design-styles .ugw-card', function () {
            $(this).siblings().removeClass('ugw-card--selected');
            $(this).addClass('ugw-card--selected');
            state.selectedDesignStyle = $(this).data('style');
        });

        $('#ugw-suggest-palettes').on('click', function () {
            $('#ugw-palettes-loading').show();
            post('ugentic_wizard_suggest_palettes', { project_id: state.projectId })
                .done(function (res) {
                    $('#ugw-palettes-loading').hide();
                    if (res.success) {
                        var $list = $('#ugw-palettes-list').empty();
                        (res.data.palettes || []).forEach(function (p) {
                            $list.append(
                                '<div class="ugw-palette-card" data-primary="' + p.primary + '" data-secondary="' + p.secondary + '">' +
                                '  <div class="ugw-palette-swatches">' +
                                '    <div class="ugw-palette-swatch" style="background:' + p.primary + '"></div>' +
                                '    <div class="ugw-palette-swatch" style="background:' + p.secondary + '"></div>' +
                                '  </div>' +
                                '  <div class="ugw-palette-info">' +
                                '    <div class="ugw-palette-name">' + escHtml(p.name) + '</div>' +
                                '    <div class="ugw-palette-desc">' + escHtml(p.description) + '</div>' +
                                '  </div>' +
                                '</div>'
                            );
                        });
                    }
                })
                .fail(function () {
                    $('#ugw-palettes-loading').hide();
                    toast('Failed to generate palettes', 'error');
                });
        });

        $(document).on('click', '.ugw-palette-card', function () {
            $('.ugw-palette-card').removeClass('ugw-palette-card--selected');
            $(this).addClass('ugw-palette-card--selected');
            var primary = $(this).data('primary');
            var secondary = $(this).data('secondary');
            $('#ugw-brand-primary').val(primary);
            $('#ugw-brand-secondary').val(secondary);
            if ($.fn.wpColorPicker) {
                $('#ugw-brand-primary').wpColorPicker('color', primary);
                $('#ugw-brand-secondary').wpColorPicker('color', secondary);
            }
        });

        $('#ugw-brand-save').on('click', function () {
            var $btn = $(this).prop('disabled', true).text('Saving...');
            var industryPayload = collectIndustryPayload('guided');

            post('ugentic_wizard_save_industry_optimization', Object.assign({
                project_id: state.projectId,
                description: state.project && state.project.description ? state.project.description : '',
            }, industryPayload)).done(function (industryRes) {
                if (!industryRes.success) {
                    toast(industryRes.data && industryRes.data.message ? industryRes.data.message : 'Failed to save industry details', 'error');
                    $btn.prop('disabled', false).text('Continue →');
                    return;
                }
                state.industryOptimization = (industryRes.data && industryRes.data.industry_optimization) || industryPayload;

                post('ugentic_wizard_save_brand', {
                    project_id: state.projectId,
                    primary_color: $('#ugw-brand-primary').val(),
                    secondary_color: $('#ugw-brand-secondary').val(),
                    design_style: state.selectedDesignStyle,
                }).done(function (res) {
                    if (res.success) {
                        toast('Brand saved!', 'success');
                        nextStep();
                    } else {
                        toast('Failed to save brand', 'error');
                    }
                }).fail(function () {
                    toast('Connection error', 'error');
                }).always(function () {
                    $btn.prop('disabled', false).text('Continue →');
                });
            }).fail(function () {
                toast('Connection error while saving industry details', 'error');
                $btn.prop('disabled', false).text('Continue →');
            });
        });

        // -- Pages --
        var pagesXhr = null;
        var pagesTimer = null;
        var pagesElapsed = 0;

        function startPagesTimer() {
            pagesElapsed = 0;
            $('#ugw-pages-elapsed').text('');
            pagesTimer = setInterval(function () {
                pagesElapsed++;
                var min = Math.floor(pagesElapsed / 60);
                var sec = pagesElapsed % 60;
                var text = min > 0 ? min + 'm ' + sec + 's' : sec + 's';
                $('#ugw-pages-elapsed').text('Elapsed: ' + text);
                if (pagesElapsed >= 45 && pagesXhr) {
                    // Timeout — abort
                    pagesXhr.abort();
                    clearInterval(pagesTimer);
                    $('#ugw-pages-loading').hide();
                    toast('AI generation timed out. Try again or add pages manually.', 'error');
                }
            }, 1000);
        }

        function stopPagesTimer() {
            if (pagesTimer) { clearInterval(pagesTimer); pagesTimer = null; }
        }

        $('#ugw-pages-generate, #ugw-pages-regenerate').on('click', function () {
            $('#ugw-pages-result').hide();
            $('#ugw-pages-loading').show();
            $('#ugw-manual-page-form').hide();
            startPagesTimer();

            pagesXhr = post('ugentic_wizard_generate_pages', {
                project_id: state.projectId,
                user_input: $('#ugw-pages-input').val() || '',
            }).done(function (res) {
                stopPagesTimer();
                $('#ugw-pages-loading').hide();
                if (res.success) {
                    renderPageList(res.data.pages || []);
                    $('#ugw-pages-result').show();
                } else {
                    toast('Failed to generate pages. Try again or add manually.', 'error');
                }
            }).fail(function (jqXHR, textStatus) {
                stopPagesTimer();
                $('#ugw-pages-loading').hide();
                if (textStatus === 'abort') return; // user cancelled
                toast('Connection error. Try adding pages manually.', 'error');
            });
        });

        // Cancel in-flight generation
        $('#ugw-pages-cancel-gen').on('click', function () {
            if (pagesXhr) pagesXhr.abort();
            stopPagesTimer();
            $('#ugw-pages-loading').hide();
            toast('Generation cancelled', 'info');
        });

        // -- Manual page add --
        function showManualPageForm() {
            $('#ugw-manual-page-form').slideDown(200);
            $('#ugw-manual-page-name').val('').focus();
            $('#ugw-manual-page-type').val('about');
            $('#ugw-manual-page-desc').val('');
        }

        $('#ugw-pages-add-manual, #ugw-pages-add-more').on('click', function () {
            showManualPageForm();
        });

        $('#ugw-manual-page-cancel').on('click', function () {
            $('#ugw-manual-page-form').slideUp(200);
        });

        $('#ugw-manual-page-submit').on('click', function () {
            var name = $('#ugw-manual-page-name').val().trim();
            if (!name) { toast('Please enter a page name', 'error'); return; }

            var type = $('#ugw-manual-page-type').val();
            var desc = $('#ugw-manual-page-desc').val().trim();
            var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

            var page = {
                page_name: name,
                page_type: type,
                page_slug: slug,
                page_description: desc || name + ' page',
                estimated_sections: type === 'homepage' ? 6 : 4,
            };

            if (!state.generatedPages) state.generatedPages = [];
            state.generatedPages.push(page);
            renderPageList(state.generatedPages);
            $('#ugw-pages-result').show();
            $('#ugw-manual-page-form').slideUp(200);
            toast(name + ' added!', 'success');
        });

        function renderPageList(pages) {
            var $list = $('#ugw-page-list').empty();
            pages.forEach(function (p, i) {
                var badge = '';
                if (p.page_type === 'homepage') badge = '<span class="ugw-page-badge ugw-page-badge--homepage">Home</span>';

                $list.append(
                    '<li class="ugw-page-item" data-idx="' + i + '" draggable="true">' +
                    '  <span class="ugw-page-drag-handle" title="Drag to reorder">☰</span>' +
                    '  <div class="ugw-page-info">' +
                    '    <div class="ugw-page-name">' + escHtml(p.page_name) + '</div>' +
                    '    <div class="ugw-page-meta">' + escHtml(p.page_type) + ' · ' + (p.estimated_sections || 0) + ' sections</div>' +
                    '  </div>' +
                    '  ' + badge +
                    '  <button class="ugw-page-remove" title="Remove">×</button>' +
                    '</li>'
                );
            });

            state.generatedPages = pages;

            // ---- HTML5 Drag-and-Drop ----
            var dragSrcIdx = null;

            $list.find('.ugw-page-item').each(function () {
                var el = this;

                el.addEventListener('dragstart', function (e) {
                    dragSrcIdx = parseInt(el.dataset.idx, 10);
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', dragSrcIdx);
                    el.style.opacity = '0.4';
                });

                el.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    // Visual drop indicator
                    $list.find('.ugw-page-item').removeClass('ugw-page-item--dragover');
                    el.classList.add('ugw-page-item--dragover');
                });

                el.addEventListener('dragleave', function () {
                    el.classList.remove('ugw-page-item--dragover');
                });

                el.addEventListener('drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.remove('ugw-page-item--dragover');

                    var dropIdx = parseInt(el.dataset.idx, 10);
                    if (dragSrcIdx !== null && dragSrcIdx !== dropIdx && state.generatedPages) {
                        // Move item in the array
                        var arr = state.generatedPages;
                        var moved = arr.splice(dragSrcIdx, 1)[0];
                        arr.splice(dropIdx, 0, moved);
                        renderPageList(arr);
                    }
                });

                el.addEventListener('dragend', function () {
                    el.style.opacity = '1';
                    $list.find('.ugw-page-item').removeClass('ugw-page-item--dragover');
                });
            });
        }

        $(document).on('click', '.ugw-page-remove', function () {
            var idx = $(this).closest('.ugw-page-item').data('idx');
            if (state.generatedPages) {
                state.generatedPages.splice(idx, 1);
                renderPageList(state.generatedPages);
            }
        });

        $('#ugw-pages-approve').on('click', function () {
            if (!state.generatedPages || !state.generatedPages.length) return;

            post('ugentic_wizard_save_pages', {
                project_id: state.projectId,
                pages: JSON.stringify(state.generatedPages),
            }).done(function (res) {
                if (res.success) {
                    // Reload state to get page IDs
                    post('ugentic_wizard_get_state', { project_id: state.projectId }).done(function (r) {
                        if (r.success) {
                            state.pages = r.data.pages || [];
                            toast('Pages saved!', 'success');
                            nextStep();
                        }
                    });
                }
            });
        });

        // -- Content --
        $(document).on('click', '.ugw-content-tab', function () {
            loadContentForPage($(this).data('page-idx'));
        });

        // Per-page generate button
        $(document).on('click', '#ugw-content-generate-page', function () {
            generateContentForCurrentPage();
        });

        // Helper to collect edited sections from the UI
        function collectEditedSections() {
            var sections = [];
            $('#ugw-content-sections .ugw-section-card').each(function () {
                var $card = $(this);
                var idx = $card.data('section-idx');
                sections.push({
                    section_heading: $card.find('.ugw-section-heading-input').val() || '',
                    section_subheading: $card.find('.ugw-section-subheading-input').val() || '',
                    section_content: $card.find('.ugw-section-content-input').val() || '',
                    section_type: $card.find('[style*="text-transform"]').first().text().trim() || '',
                });
            });
            return sections;
        }

        $('#ugw-content-approve').on('click', function () {
            var page = state.pages[state.activeContentTab];
            if (!page) return;

            // Collect any user edits
            var editedSections = collectEditedSections();
            if (editedSections.length) {
                page.section_outline = JSON.stringify(editedSections);
            }

            post('ugentic_wizard_approve_content', {
                project_id: state.projectId,
                page_id: page.id,
                sections: JSON.stringify(editedSections),
            }).done(function (res) {
                if (res.success) {
                    page.copy_status = 'approved';
                    toast('Page content approved! ✓', 'success');

                    // Update tabs
                    renderContentTabs();

                    if (res.data.all_approved) {
                        setTimeout(function () { nextStep(); }, 1000);
                    } else {
                        // Move to next un-approved tab
                        for (var i = 0; i < state.pages.length; i++) {
                            if (state.pages[i].copy_status !== 'approved' && state.pages[i].page_type !== 'legal') {
                                loadContentForPage(i);
                                break;
                            }
                        }
                    }
                }
            });
        });

        $('#ugw-content-revise').on('click', function () {
            var page = state.pages[state.activeContentTab];
            var feedback = $('#ugw-content-feedback').val().trim();
            if (!page || !feedback) {
                toast('Please describe the changes you want', 'info');
                return;
            }

            // Collect current edits to include as context
            var editedSections = collectEditedSections();

            $('#ugw-content-loading').show();
            $('#ugw-content-sections').hide();
            $('#ugw-content-actions').hide();

            post('ugentic_wizard_revise_content', {
                project_id: state.projectId,
                page_id: page.id,
                feedback: feedback,
                current_sections: JSON.stringify(editedSections),
            }).done(function (res) {
                $('#ugw-content-loading').hide();
                $('#ugw-content-sections').show();
                if (res.success) {
                    page.section_outline = JSON.stringify(res.data.sections);
                    page.copy_content = res.data.copy_content;
                    renderContentSections(res.data.sections);
                    $('#ugw-content-feedback').val('');
                    $('#ugw-content-actions').show();
                    toast('Content revised!', 'success');
                }
            }).fail(function () {
                $('#ugw-content-loading').hide();
                $('#ugw-content-sections').show();
                toast('Connection error', 'error');
            });
        });

        // -- Compliance --
        $('#ugw-compliance-save').on('click', function () {
            var answers = {};
            $('[data-compliance-id]').each(function () {
                answers[$(this).data('compliance-id')] = $(this).is(':checked');
            });

            post('ugentic_wizard_save_compliance', {
                project_id: state.projectId,
                answers: JSON.stringify(answers),
            }).done(function (res) {
                if (res.success) {
                    var required = res.data.required_pages || [];
                    if (required.length > 0) {
                        var html = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">Required Legal Pages</h3>';
                        html += '<div class="ugw-page-list">';
                        required.forEach(function (r) {
                            html += '<div class="ugw-page-item"><div class="ugw-page-info"><div class="ugw-page-name">' + escHtml(r.title || r) + '</div></div><span class="ugw-page-badge ugw-page-badge--legal">Legal</span></div>';
                        });
                        html += '</div>';
                        html += '<div class="ugw-btn-group" style="margin-top: 16px;"><button class="ugw-btn ugw-btn--primary" id="ugw-compliance-gen-all">Generate All & Continue →</button></div>';
                        $('#ugw-compliance-result').html(html).show();

                        state.requiredLegalPages = required;
                    } else {
                        toast('No legal pages required!', 'success');
                        nextStep();
                    }
                }
            });
        });

        $('#ugw-compliance-skip').on('click', function () {
            post('ugentic_wizard_save_compliance', {
                project_id: state.projectId,
                answers: JSON.stringify({}),
            }).done(function () {
                nextStep();
            });
        });

        $(document).on('click', '#ugw-compliance-gen-all', function () {
            if (!state.requiredLegalPages) return;
            var queue = state.requiredLegalPages.slice();
            var $btn = $(this).prop('disabled', true).text('Generating...');

            function genNext() {
                if (!queue.length) {
                    toast('All legal pages generated!', 'success');
                    nextStep();
                    return;
                }
                var page = queue.shift();
                var type = page.slug || page;
                post('ugentic_wizard_generate_legal', {
                    project_id: state.projectId,
                    page_type: type,
                }).done(function () {
                    $btn.text('Generating... (' + (state.requiredLegalPages.length - queue.length) + '/' + state.requiredLegalPages.length + ')');
                    genNext();
                }).fail(function () {
                    toast('Failed to generate ' + type, 'error');
                    genNext();
                });
            }

            genNext();
        });

        // -- Design --
        $('#ugw-design-page-select').on('change', function () {
            generateDesignForPage(parseInt($(this).val()));
        });

        $('#ugw-design-send').on('click', function () {
            var feedback = $('#ugw-design-input').val().trim();
            if (!feedback) return;
            var page = state.pages[state.currentPageIdx];
            if (!page) return;

            addChatBubble(document.getElementById('ugw-design-messages'), feedback, 'user');
            showTyping(document.getElementById('ugw-design-messages'));
            $('#ugw-design-input').val('');

            post('ugentic_wizard_design_feedback', {
                project_id: state.projectId,
                page_id: page.id,
                feedback: feedback,
            }).done(function (res) {
                hideTyping();
                if (res.success) {
                    page.generated_html = res.data.html;
                    loadDesignPreview(res.data.html);
                    addChatBubble(document.getElementById('ugw-design-messages'), 'Done! I\'ve updated the design.', 'assistant');
                } else {
                    addChatBubble(document.getElementById('ugw-design-messages'), 'Sorry, I couldn\'t apply that change. Please try again.', 'assistant');
                }
            });
        });

        $('#ugw-design-input').on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $('#ugw-design-send').click();
            }
        });

        $('#ugw-design-approve').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Approving...');

            // Approve all pages at once
            var approveQueue = [];
            state.pages.forEach(function (p) {
                if (p.page_type !== 'legal' && p.generated_html) {
                    approveQueue.push(p.id);
                }
            });

            if (!approveQueue.length) {
                toast('No designs to approve — generate designs first', 'error');
                $btn.prop('disabled', false).text('✓ Approve Design');
                return;
            }

            // Approve each page then advance
            var completed = 0;
            approveQueue.forEach(function (pageId) {
                post('ugentic_wizard_approve_design', {
                    project_id: state.projectId,
                    page_id: pageId,
                }).always(function () {
                    completed++;
                    if (completed >= approveQueue.length) {
                        toast('All designs approved!', 'success');
                        nextStep();
                    }
                });
            });
        });

        $('#ugw-design-regenerate').on('click', function () {
            var page = state.pages[state.currentPageIdx];
            if (!page) return;
            page.generated_html = null;
            generateDesignForPage(state.currentPageIdx);
        });

        // -- Images --
        $(document).on('click', '.ugw-gen-slot-img', function () {
            var $slot = $(this).closest('.ugw-image-slot');
            var pageId = $slot.data('page-id');
            var slotId = $slot.data('slot-id');
            var $btn = $(this).prop('disabled', true).text('Generating...');

            post('ugentic_wizard_generate_image', {
                project_id: state.projectId,
                page_id: pageId,
                slot_id: slotId,
            }).done(function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    $slot.find('.ugw-image-slot-preview').html('<img src="' + res.data.url + '" alt="">');
                    $btn.text('🔄 Regenerate').removeClass('ugw-btn--primary').addClass('ugw-btn--ghost');
                    toast('Image generated!', 'success');
                } else {
                    $btn.text('✨ Generate');
                    toast('Image generation failed', 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('✨ Generate');
                toast('Connection error', 'error');
            });
        });

        $('#ugw-images-generate-all').on('click', function () {
            var $btn = $(this).prop('disabled', true);
            
            // Collect all un-generated slots across all pages
            var queue = [];
            state.pages.forEach(function (page) {
                var slots = [];
                try { slots = JSON.parse(page.image_slots || '[]') || []; } catch(e) {}
                slots.forEach(function (slot) {
                    if (!slot.is_generated) {
                        queue.push({ page_id: page.id, page_name: page.page_name, slot_id: slot.slot_id, description: slot.description });
                    }
                });
            });

            if (queue.length === 0) {
                toast('All images already generated!', 'success');
                $btn.prop('disabled', false);
                return;
            }

            var total = queue.length;
            var done = 0;
            $btn.text('Generating 0 / ' + total + '...');

            function generateNext() {
                if (done >= total) {
                    $btn.prop('disabled', false).text('✨ Generate All Images');
                    toast('Generated ' + total + ' images!', 'success');
                    // Refresh the images step
                    post('ugentic_wizard_get_state', { project_id: state.projectId }).done(function (r) {
                        if (r.success) {
                            state.pages = r.data.pages || [];
                            initImagesStep();
                        }
                    });
                    return;
                }

                var item = queue[done];
                $btn.text('Generating ' + (done + 1) + ' / ' + total + ': ' + (item.description || item.slot_id).substring(0, 30) + '...');

                post('ugentic_wizard_generate_image', {
                    project_id: state.projectId,
                    page_id: item.page_id,
                    slot_id: item.slot_id,
                }).done(function (res) {
                    if (res.success) {
                        // Update the slot UI if visible
                        var $slot = $('.ugw-image-slot[data-slot-id="' + item.slot_id + '"]');
                        if ($slot.length) {
                            $slot.find('.ugw-image-slot-preview').html('<img src="' + res.data.url + '" alt="">');
                            $slot.find('.ugw-gen-slot-img').text('🔄 Regenerate').removeClass('ugw-btn--primary').addClass('ugw-btn--ghost');
                        }
                    }
                    done++;
                    generateNext();
                }).fail(function () {
                    done++;
                    generateNext();
                });
            }

            generateNext();
        });

        $('#ugw-images-skip').on('click', function () {
            post('ugentic_wizard_skip_images', { project_id: state.projectId }).done(function () {
                showLaunch();
            });
        });

        $('#ugw-images-continue').on('click', function () {
            showLaunch();
        });

        // -- Launch --
        $('#ugw-launch-publish').on('click', function () {
            var $btn = $(this).prop('disabled', true).text('Preparing...');
            var $progress = $('#ugw-publish-progress').show();
            var $bar = $('#ugw-publish-bar');
            var $label = $('#ugw-publish-step-label');
            var $count = $('#ugw-publish-step-count');
            var $currentPage = $('#ugw-publish-current-page');
            $('#ugw-launch-result').empty();

            // Step 1: Prepare — save header/footer, get page list
            $label.text('Preparing header & footer...');
            $bar.css('width', '5%');

            post('ugentic_wizard_publish_prepare', { project_id: state.projectId })
                .done(function (prepRes) {
                    if (!prepRes.success) {
                        $btn.prop('disabled', false).text('🚀 Publish All Pages');
                        $progress.hide();
                        toast('Preparation failed: ' + (prepRes.data && prepRes.data.error || 'Unknown error'), 'error');
                        return;
                    }

                    var pages = prepRes.data.pages || [];
                    var headerAssetId = prepRes.data.header_asset_id || 0;
                    var footerAssetId = prepRes.data.footer_asset_id || 0;
                    var total = pages.length;
                    var published = [];
                    var errors = [];

                    $bar.css('width', '10%');
                    $count.text('0 / ' + total);
                    $label.text('Publishing pages...');

                    // Step 2: Publish pages sequentially
                    function publishNext(index) {
                        if (index >= total) {
                            // Done!
                            $bar.css('width', '100%');
                            $label.text('Published!');
                            $count.text(total + ' / ' + total);
                            $currentPage.text('');
                            $btn.prop('disabled', false).text('🚀 Publish All Pages');

                            // Show results
                            var html = '<div style="text-align: left;">';
                            if (published.length > 0) {
                                html += '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: var(--ugw-success);">✓ Published ' + published.length + ' page' + (published.length > 1 ? 's' : '') + ' successfully!</h3>';
                                published.forEach(function (p) {
                                    html += '<div style="margin-bottom: 8px;"><strong>' + escHtml(p.page_name) + '</strong> — <a href="' + p.url + '" target="_blank" style="color: var(--ugw-primary);">View Page ↗</a></div>';
                                });
                            }
                            if (errors.length > 0) {
                                html += '<div style="margin-top: 12px; color: var(--ugw-error);">';
                                errors.forEach(function (e) {
                                    html += '<div>⚠ ' + escHtml(e) + '</div>';
                                });
                                html += '</div>';
                            }
                            html += '</div>';
                            $('#ugw-launch-result').html(html);
                            toast('Website published! 🎉', 'success');

                            // Finalize menus, homepage, metadata, and nav links without publishing pages twice.
                            post('ugentic_wizard_publish', { project_id: state.projectId, finalize_only: 1 });
                            return;
                        }

                        var p = pages[index];
                        var pct = Math.round(10 + (index / total) * 85);
                        $bar.css('width', pct + '%');
                        $label.text('Publishing: ' + p.page_name);
                        $count.text((index + 1) + ' / ' + total);
                        $currentPage.text('Creating WordPress page for "' + p.page_name + '"...');

                        post('ugentic_wizard_publish_page', {
                            project_id: state.projectId,
                            page_id: p.id,
                            header_asset_id: headerAssetId,
                            footer_asset_id: footerAssetId,
                        }).done(function (pageRes) {
                            if (pageRes.success) {
                                published.push(pageRes.data);
                                $currentPage.text('✓ ' + p.page_name + ' published');
                            } else {
                                errors.push(p.page_name + ': ' + (pageRes.data && pageRes.data.error || 'Unknown error'));
                                $currentPage.text('⚠ ' + p.page_name + ' failed');
                            }
                            publishNext(index + 1);
                        }).fail(function () {
                            errors.push(p.page_name + ': Connection error');
                            $currentPage.text('⚠ ' + p.page_name + ' — connection error');
                            publishNext(index + 1);
                        });
                    }

                    publishNext(0);
                })
                .fail(function () {
                    $btn.prop('disabled', false).text('🚀 Publish All Pages');
                    $progress.hide();
                    toast('Connection error during preparation', 'error');
                });
        });

        $('#ugw-launch-sitemap').on('click', function () {
            post('ugentic_wizard_generate_sitemap_xml', { project_id: state.projectId })
                .done(function (res) {
                    if (res.success) {
                        // Download as file
                        var blob = new Blob([res.data.sitemap_xml], { type: 'application/xml' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'sitemap.xml';
                        a.click();
                        URL.revokeObjectURL(url);
                        toast('Sitemap downloaded!', 'success');
                    }
                });
        });
    });

})(jQuery);
