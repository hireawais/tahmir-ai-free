(function () {
    'use strict';

    var config = window.ugenticNavigationRuntime || {};
    var map = config.map || {};
    var homeUrl = config.homeUrl || '/';
    var scheduled = false;

    // A WordPress destination is terminal. The observer also sees our own href writes.
    var targets = new Map();
    var routes = config.pathMap || map;
    Object.keys(routes).forEach(function (key) {
        try {
            var target = new URL(routes[key], location.href);
            var path = target.origin + target.pathname;
            if (!targets.has(path)) targets.set(path, []);
            targets.get(path).push(target.searchParams);
        } catch (_) {}
    });

    function resolveLink(raw) {
        if (!raw || /^(?:#|\?|mailto:|tel:|sms:|javascript:|data:|blob:)/i.test(raw)) return null;
        try {
            var source = new URL(raw, location.origin + (config.sourceDir || '/'));
            if (source.origin !== location.origin) return null;
            var existing = targets.get(source.origin + source.pathname) || [];
            if (existing.some(function (params) {
                return Array.from(params).every(function (pair) {
                    return source.searchParams.getAll(pair[0]).includes(pair[1]);
                });
            })) return null;
            // A local WordPress object may belong to another import on this installation.
            if (source.searchParams.has('page_id') || source.searchParams.has('p') || source.searchParams.has('attachment_id')) return null;

            var route = decodeURIComponent(source.pathname).replace(/^\/+|\/+$/g, '');
            var key = config.pathMap
                ? route.replace(/(?:^|\/)index\.html?$/i, '').replace(/\.html?$/i, '').replace(/\/+$/g, '')
                : route.split('/').pop().toLowerCase();
            if (!Object.prototype.hasOwnProperty.call(routes, key)) return null;

            var target = new URL(routes[key], location.href);
            var reserved = new Set(target.searchParams.keys());
            source.searchParams.forEach(function (value, name) {
                if (!reserved.has(name)) target.searchParams.append(name, value);
            });
            if (source.hash) target.hash = source.hash;
            return target.href;
        } catch (_) { return null; }
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
