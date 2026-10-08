/* This file is packaged with a per-document config by Ugentic_Zip_Assets. */
'use strict';
var sourceBase = 'https://tahmir-import.invalid' + config.sourceDir;
var map = config.map || {};

function resource(raw) {
    if (!raw || /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(raw)) return raw;
    try {
        var url = new URL(raw, sourceBase);
        var path = decodeURIComponent(url.pathname).replace(/^\/+/, '');
        return Object.prototype.hasOwnProperty.call(map, path) ? map[path] + url.search + url.hash : raw;
    } catch (_) { return raw; }
}

function css(raw) {
    return raw.replace(/url\(\s*(?:"([^"]*)"|'([^']*)'|([^\s)]*))\s*\)/gi, function (whole, doubleQuoted, singleQuoted, plain) {
        var old = doubleQuoted === undefined ? (singleQuoted === undefined ? plain : singleQuoted) : doubleQuoted;
        var next = resource(old);
        return next === old ? whole : 'url("' + next.replace(/"/g, '%22') + '")';
    });
}

function repair(element) {
    if (!element || element.nodeType !== 1 || element.closest('[data-ugentic-no-save]')) return;
    var media = /^(?:IMG|SOURCE|VIDEO|AUDIO|TRACK|IMAGE|USE)$/.test(element.tagName);
    var attrs = ['poster', 'background', 'data-src', 'data-lazy-src', 'data-bg', 'data-background', 'data-background-image'];
    if (media) attrs.push('src', 'href', 'xlink:href');
    attrs.forEach(function (attr) {
        var old = element.getAttribute(attr);
        if (!old) return;
        var next = resource(old);
        if (next !== old) {
            element.setAttribute(attr, next);
            if (element.tagName === 'SOURCE' && element.parentElement && typeof element.parentElement.load === 'function') element.parentElement.load();
        }
    });
    ['srcset', 'data-srcset', 'data-lazy-srcset'].forEach(function (attr) {
        var old = element.getAttribute(attr);
        if (!old || /data:/i.test(old)) return;
        var next = old.split(/,\s+(?=\S)/).map(function (entry) {
            var match = entry.trim().match(/^(.*?)(\s+\d+(?:\.\d+)?[wx])?$/);
            return resource(match[1]) + (match[2] || '');
        }).join(', ');
        if (next !== old) element.setAttribute(attr, next);
    });
    var style = element.getAttribute('style');
    if (style) {
        var nextStyle = css(style);
        if (nextStyle !== style) element.setAttribute('style', nextStyle);
    }
}

function tree(root) {
    repair(root);
    if (root.querySelectorAll) root.querySelectorAll('*').forEach(repair);
}

// Observe only changed subtrees/attributes, never rescan the whole page for
// every image event. Source scripts retain their filename data unchanged.
new MutationObserver(function (changes) {
    changes.forEach(function (change) {
        if (change.type === 'attributes') repair(change.target);
        else change.addedNodes.forEach(tree);
    });
}).observe(document.documentElement, {subtree: true, childList: true, attributes: true,
    attributeFilter: ['src', 'href', 'xlink:href', 'srcset', 'poster', 'style', 'background', 'data-src', 'data-lazy-src', 'data-srcset', 'data-lazy-srcset', 'data-bg', 'data-background', 'data-background-image']});
tree(document.documentElement);

// Let path-based source routers select the imported route while they boot.
// Restore the WordPress address immediately after the initial load.
var originalPath = location.pathname + location.search + location.hash;
try {
    history.replaceState(history.state, document.title, config.sourcePath + location.search + location.hash);
    var restored = false;
    function restorePath() {
        if (restored) return;
        restored = true;
        history.replaceState(history.state, document.title, originalPath);
    }
    window.addEventListener('load', restorePath, {once: true});
    setTimeout(restorePath, 4000);
} catch (_) {}
