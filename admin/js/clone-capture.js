/* Shared readiness rules for editable client-rendered snapshots. */
(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.TahmirCloneCapture = api;
}(typeof window !== 'undefined' ? window : this, function () {
    'use strict';
    function errorHeading(text) {
        return /^(?:404(?:\s*[-:]\s*.*)?|page not found|not found|application error(?::.*)?|something went wrong|just a moment[.!]*|vercel security checkpoint|access denied)$/i.test(String(text || '').replace(/\s+/g, ' ').trim());
    }
    function inspect(doc) {
        var root = doc.querySelector('#root, #app, #__next, #__nuxt, #___gatsby, #gatsby-focus-wrapper, [data-reactroot], [ng-version], app-root') || doc.body;
        if (!root) return { substantive: false, error: '' };
        var text = String(root.innerText || '').replace(/\s+/g, ' ').trim();
        var tags = root.querySelectorAll('*').length;
        var images = Array.prototype.slice.call(root.querySelectorAll('img'));
        var heading = root.querySelector('h1, h2, [role="alert"]');
        var error = errorHeading(doc.title) ? doc.title : (heading && errorHeading(heading.textContent) ? heading.textContent : '');
        var pending = images.filter(function (img) { return (img.currentSrc || img.getAttribute('src')) && !img.complete; }).length;
        var failed = images.filter(function (img) { return (img.currentSrc || img.getAttribute('src')) && img.complete && !img.naturalWidth; }).length;
        return {
            substantive: tags >= 12 && (text.length >= 80 || (images.length >= 3 && text.length >= 20)),
            error: error,
            pending: pending,
            failed: failed,
            fontsReady: !doc.fonts || doc.fonts.status === 'loaded',
            // Animation transforms and ticking text should not block capture.
            fingerprint: [tags, images.length, root.querySelectorAll('main, section, header, footer').length].join(':')
        };
    }
    function ready(state, stableChecks, elapsed) {
        return !!(state.substantive && !state.error
            && (stableChecks >= 3 || elapsed >= 12000)
            && ((!state.pending && state.fontsReady) || elapsed >= 20000));
    }
    function serialize(doc) {
        var copy = doc.documentElement.cloneNode(true);
        var originals = doc.querySelectorAll('style');
        var copies = copy.querySelectorAll('style');
        Array.prototype.forEach.call(originals, function (style, index) {
            // CSS-in-JS often inserts rules through CSSOM, leaving empty tags.
            try {
                if (style.sheet && copies[index]) {
                    copies[index].textContent = Array.prototype.map.call(style.sheet.cssRules, function (rule) {
                        return rule.cssText;
                    }).join('\n');
                }
            } catch (ignore) { /* Cross-origin stylesheet rules are not readable. */ }
        });
        copy.querySelectorAll('[data-ugentic-no-save], #wpadminbar, #ugentic-global-footer, #ugentic-global-header').forEach(function (node) { node.remove(); });
        return '<!DOCTYPE html>\n' + copy.outerHTML;
    }
    return { inspect: inspect, ready: ready, serialize: serialize, errorHeading: errorHeading };
}));
