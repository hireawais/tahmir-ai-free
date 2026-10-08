/**
 * Page Conversion Script
 * Converts any WordPress page to Tahmir AI.
 */

(function () {
    let progressTimer = null;
    let currentProgress = 0;

    function getStrings() {
        return window.ugenticConvert && window.ugenticConvert.strings
            ? window.ugenticConvert.strings
            : {};
    }

    function ensureLoadingBar(message) {
        let bar = document.getElementById('ugentic-page-loading-bar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'ugentic-page-loading-bar';
            bar.innerHTML = '<div class="ugentic-page-loading-fill"></div><div class="ugentic-page-loading-label"></div>';
            document.body.appendChild(bar);
        }

        const label = bar.querySelector('.ugentic-page-loading-label');
        if (label) {
            label.textContent = message || 'Loading...';
        }

        bar.classList.add('is-active');
        return bar;
    }

    function setLoadingProgress(percent) {
        currentProgress = Math.max(currentProgress, percent);
        const fill = document.querySelector('#ugentic-page-loading-bar .ugentic-page-loading-fill');
        if (fill) {
            fill.style.width = Math.min(currentProgress, 100) + '%';
        }
    }

    function startLoading(message) {
        ensureLoadingBar(message);
        currentProgress = 0;
        setLoadingProgress(8);

        if (progressTimer) {
            clearInterval(progressTimer);
        }

        progressTimer = setInterval(function () {
            if (currentProgress < 88) {
                setLoadingProgress(currentProgress + Math.max(2, Math.round((88 - currentProgress) * 0.18)));
            }
        }, 280);
    }

    function finishLoading(keepVisible) {
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }

        setLoadingProgress(100);

        if (keepVisible) {
            return;
        }

        window.setTimeout(function () {
            const bar = document.getElementById('ugentic-page-loading-bar');
            if (bar) {
                bar.classList.remove('is-active');
            }
        }, 450);
    }

    function resetButton(button, originalText) {
        if (!button) {
            return;
        }

        button.innerHTML = originalText;
        button.style.pointerEvents = '';
        button.removeAttribute('aria-busy');
    }

    window.ugenticConvertPage = function (pageId) {
        const strings = getStrings();
        if (!confirm('Convert this page to Tahmir AI? This will allow you to edit it with AI.')) {
            return;
        }

        const convertBtn = document.querySelector('#wp-admin-bar-ugentic-convert a');
        const originalText = convertBtn ? convertBtn.innerHTML : '';
        const convertingText = strings.converting || 'Converting page to Tahmir AI...';

        startLoading(convertingText);

        if (convertBtn) {
            convertBtn.innerHTML = '<span class="ab-icon dashicons dashicons-update spin"></span> ' + convertingText;
            convertBtn.style.pointerEvents = 'none';
            convertBtn.setAttribute('aria-busy', 'true');
        }

        fetch(window.ugenticConvert.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'ugentic_convert_to_ugentic',
                nonce: window.ugenticConvert.nonce,
                page_id: pageId
            })
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    const convertedText = strings.converted || 'Page converted. Opening editor...';
                    const editUrl = data.data && data.data.edit_url ? data.data.edit_url : '';

                    if (convertBtn) {
                        convertBtn.innerHTML = '<span class="ab-icon dashicons dashicons-yes"></span> ' + convertedText;
                    }

                    ensureLoadingBar(convertedText);
                    finishLoading(true);

                    window.setTimeout(function () {
                        if (editUrl) {
                            window.location.href = editUrl;
                        } else if (data.data && data.data.reload) {
                            window.location.reload();
                        }
                    }, 350);
                    return;
                }

                finishLoading(false);
                alert((data.data && data.data.message) || strings.error || 'Error converting page');
                resetButton(convertBtn, originalText);
            })
            .catch(function (error) {
                console.error('Conversion error:', error);
                finishLoading(false);
                alert(strings.error || 'Error converting page');
                resetButton(convertBtn, originalText);
            });
    };

    const style = document.createElement('style');
    style.textContent = `
        @keyframes ugentic-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .dashicons.spin {
            animation: ugentic-spin 1s linear infinite;
        }
        #ugentic-page-loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            z-index: 2147483647;
            opacity: 0;
            pointer-events: none;
            transition: opacity 160ms ease;
        }
        #ugentic-page-loading-bar.is-active {
            opacity: 1;
        }
        .ugentic-page-loading-fill {
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, #2563eb, #ea580c, #16a34a);
            box-shadow: 0 0 14px rgba(37, 99, 235, 0.45);
            transition: width 280ms ease;
        }
        .ugentic-page-loading-label {
            position: absolute;
            top: 9px;
            right: 12px;
            max-width: min(360px, calc(100vw - 24px));
            padding: 6px 10px;
            border-radius: 4px;
            background: rgba(17, 24, 39, 0.94);
            color: #fff;
            font: 600 12px/1.3 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.22);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    `;
    document.head.appendChild(style);
})();
