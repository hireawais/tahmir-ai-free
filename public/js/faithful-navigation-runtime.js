(function () {
    'use strict';

    var config = window.ugenticNavigationRuntime || {};
    var map = config.map || {};
    var homeUrl = config.homeUrl || '/';
    var scheduled = false;

    function importedRoute(raw) {
        if (!config.pathMap || !raw || /^(?:#|\?|mailto:|tel:|javascript:)/i.test(raw)) return null;
        try {
            var url = new URL(raw, location.origin + (config.sourceDir || '/'));
            if (url.origin !== location.origin) return null;
            var route = decodeURIComponent(url.pathname).replace(/^\/+|\/+$/g, '').replace(/(?:^|\/)index\.html?$/i, '').replace(/\.html?$/i, '').replace(/\/+$/g, '');
            return Object.prototype.hasOwnProperty.call(config.pathMap, route) ? config.pathMap[route] + url.search + url.hash : null;
        } catch (_) { return null; }
    }

    function resolveLink(raw) {
        var suffixIndex;
        var base;
        var suffix;
        var path;
        var parsed;
        var key;

        if (!raw || raw.charAt(0) === '#' || raw.charAt(0) === '?') {
            return null;
        }

        if (config.pathMap) return importedRoute(raw);

        suffixIndex = raw.search(/[?#]/);
        base = suffixIndex === -1 ? raw : raw.slice(0, suffixIndex);
        suffix = suffixIndex === -1 ? '' : raw.slice(suffixIndex);
        path = base;

        if (/^https?:\/\//i.test(base)) {
            try {
                parsed = new URL(base, window.location.href);
            } catch (error) {
                return null;
            }

            if (parsed.hostname.replace(/^www\./i, '') !== window.location.hostname.replace(/^www\./i, '')) {
                return null;
            }
            path = parsed.pathname;
        }

        path = path.split(String.fromCharCode(92)).join('/').replace(/^(?:\.\/|\/)+/, '');
        key = path.split('/').pop().toLowerCase();
        return map[key] ? map[key] + suffix : null;
    }

    function repairNavigation() {
        var anchors = document.querySelectorAll('a[href]');

        Array.prototype.forEach.call(anchors, function (anchor) {
            var raw = anchor.getAttribute('href');
            var target = anchor.classList.contains('brand') ? homeUrl : resolveLink(raw);

            if (target && target !== raw) {
                anchor.setAttribute('href', target);
            }
        });

        Array.prototype.forEach.call(document.querySelectorAll('header'), function (header) {
            var seen = Object.create(null);

            Array.prototype.forEach.call(header.querySelectorAll('a[href]'), function (anchor) {
                var classes = String(anchor.className || '').toLowerCase().trim().split(/\s+/).filter(Boolean).sort().join(' ');
                var label;
                var identity;

                if (!classes || !/(?:^|\s)(?:button|btn|cta|desktop[\w-]*|mobile[\w-]*|menu-(?:cta|contact)|nav-(?:cta|contact))(?:\s|$)/i.test(classes)) {
                    return;
                }

                label = String(anchor.textContent || '').toLowerCase().replace(/\s+/g, ' ').trim();
                identity = label + '|' + String(anchor.getAttribute('href') || '').toLowerCase() + '|' + classes;

                if (seen[identity]) {
                    anchor.remove();
                    return;
                }
                seen[identity] = true;
            });
        });
    }

    function scheduleRepair() {
        if (scheduled) {
            return;
        }

        scheduled = true;
        window.requestAnimationFrame(function () {
            scheduled = false;
            repairNavigation();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleRepair, {once: true});
    } else {
        scheduleRepair();
    }

    new MutationObserver(scheduleRepair).observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['href']
    });
    window.addEventListener('pageshow', scheduleRepair);
})();
