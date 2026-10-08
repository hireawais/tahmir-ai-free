/* Presentation locks for the Free edition. Authorization is also enforced in PHP. */
(function () {
    'use strict';
    if (window.TahmirFreeEdition) return;
    var allowedPages = ['ugentic-builder', 'ugentic-build', 'ugentic-manage', 'ugentic-import', 'ugentic-clone', 'ugentic-pages','ugentic-upgrade'];
    var paidControls = '#btn-menu,[data-help-action="ai"],.ug-ai-text-action:not(#iconbox-image-apply),[data-ug-v2-mode="ai"],#prop-smart-analyze,.ugentic-seo-review-btn,.ugentic-seo-rescan-btn,#ugentic-seo-apply-all-btn,#ugentic-seo-rescan-all-btn,#seo-aiseo-rescan-current,#seo-aiseo-apply-current,.analyze-dynamic-btn,.remove-dynamic-btn,.export-page-btn,#export-site-btn,.view-dna-btn,.view-history-btn,.webhook-settings-btn,#regenerate-dna-btn,#save-webhook-settings,.uf-forms-page a[href*="action=new"],#uf-create-form,.uf-card__title a,.uf-card a[href*="form_id"]:not([href*="ugentic-form-entries"]),#export-csv,[data-left-tab="advanced"],[data-ug-v2-tool="assets"],[data-ug-v2-tool="ai"],#btn-switch-to-ai,#btn-streamline-ai,#btn-history,#ug-left-ai-text,[data-action="ai-edit"],#ugentic-ai-chat-toggle,#anim-ai-generate,#instant-ai-apply,[data-module],input[name="ugw-clone-assist"][value="ai"],input[name="import_mode"][value="advanced"],.widget-item[data-widget="form"]';
    function show(feature) {
        var dialog = document.getElementById('tahmir-pro-dialog');
        if (!dialog) {
            dialog = document.createElement('dialog'); dialog.id = 'tahmir-pro-dialog';
            dialog.setAttribute('data-ugentic-no-save', 'true');
            dialog.classList.add('ug-v2-chrome');
            dialog.innerHTML = '<h2>Tahmir AI Pro</h2><p></p><a href="https://tahmir.com/upgrade" target="_blank" rel="noopener noreferrer">Explore Pro</a><button type="button">Back to imports</button>';
            var config=window.ugenticEditorConfig||window.ugenticAdmin||{};
            dialog.querySelector('button').addEventListener('click', function () { dialog.close(); });
            document.body.appendChild(dialog);
        }
        dialog.querySelector('p').textContent = (feature || 'This feature') + ' is included in Pro. Install the Pro add-on to unlock it here. Website cloning, HTML import and imported page publishing remain available for free.';
        var previous = dialog.querySelector('.tahmir-upgrade-details');
        if (previous) previous.remove();
        var formCopy = document.getElementById('tahmir-form-upgrade-copy');
        var isForm = !!formCopy && /form/i.test(feature || '');
        dialog.classList.toggle('tahmir-form-upgrade-dialog', isForm);
        dialog.querySelector('h2').textContent = isForm ? 'Form Builder — Tahmir AI Pro' : 'Tahmir AI Pro';
        dialog.querySelector('h2').id = 'tahmir-pro-dialog-title';
        dialog.setAttribute('aria-labelledby', 'tahmir-pro-dialog-title');
        dialog.querySelector('p').hidden = isForm;
        dialog.querySelector('button').textContent = isForm ? 'Back to forms' : 'Back to imports';
        dialog.querySelector('a').textContent = isForm ? 'Upgrade to Pro' : 'Explore Pro';
        if (isForm) {
            var details = document.createElement('div');
            details.className = 'tahmir-upgrade-details';
            details.appendChild(formCopy.content.cloneNode(true));
            dialog.insertBefore(details, dialog.querySelector('a'));
        }
        if (!dialog.open) dialog.showModal();
    }
    window.TahmirFreeEdition = { show: show };
    function mark(el, label) {
        if (el.hasAttribute('data-tahmir-pro')) return;
        el.setAttribute('data-tahmir-pro', label || el.title || el.getAttribute('aria-label') || el.textContent.trim() || 'This feature');
        el.setAttribute('aria-description', 'Requires Tahmir AI Pro');
        if (el.matches('.widget-item[data-widget]')) el.setAttribute('draggable', 'false');
        var host = /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) ? el.closest('label') : el;
        if (host && !host.querySelector('.tahmir-pro-badge')) {
            var badge = document.createElement('span'); badge.className='tahmir-pro-badge'; badge.textContent='Pro'; host.appendChild(badge);
        }
    }
    function scan() {
        var advanced=document.querySelector('#import-mode option[value="advanced"]');
        if(advanced){ advanced.disabled=true;if(!advanced.textContent.includes('Pro'))advanced.textContent+=' · Pro'; }

        document.querySelectorAll(paidControls).forEach(function (el) { mark(el); });
        document.querySelectorAll('.ugentic-wrap a[href],.ugentic-workspace-bar a[href],#toplevel_page_ugentic-builder a[href]').forEach(function (link) {
            if (link.closest('.tahmir-free-settings') && link.getAttribute('href').charAt(0) === '#') return;
            var route = new URL(link.href, location.href).searchParams.get('page');
            if (route && route.indexOf('ugentic-') === 0 && allowedPages.indexOf(route) < 0) {
                // Navigation stays usable so the original locked workspace can be viewed.
                if (!link.querySelector('.tahmir-pro-badge')) { var b=document.createElement('span'); b.className='tahmir-pro-badge'; b.textContent='Pro'; link.appendChild(b); }
            }
        });
        document.querySelectorAll('[data-tahmir-pro-preview] input,[data-tahmir-pro-preview] textarea,[data-tahmir-pro-preview] select,[data-tahmir-pro-preview] button').forEach(function(el){mark(el);});
        document.querySelectorAll('input[name="ugw-clone-assist"][value="standard"],input[name="import_mode"][value="faithful"]').forEach(function(el){
            if (el.form && el.form.querySelector('input[data-tahmir-pro]:checked')) el.checked=true;
        });
    }
    document.addEventListener('click', function (event) {
        var control=event.target.closest('[data-tahmir-pro],' + paidControls);
        if (!control) return;
        event.preventDefault(); event.stopImmediatePropagation(); mark(control); show(control.getAttribute('data-tahmir-pro'));
    }, true);
    ['dragstart', 'keydown'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            if (type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
            var control = event.target.closest('.widget-item[data-tahmir-pro]');
            if (!control) return;
            event.preventDefault(); event.stopImmediatePropagation();
            show(control.getAttribute('data-tahmir-pro'));
        }, true);
    });
    document.addEventListener('submit', function(event){
        if(event.target.closest('[data-tahmir-pro-preview]')) { event.preventDefault();event.stopImmediatePropagation();show('These settings'); }
    },true);
    function init() {
        scan();
        var pending=false;
        new MutationObserver(function(){if(pending)return;pending=true;requestAnimationFrame(function(){pending=false;scan();});}).observe(document.body,{childList:true,subtree:true});

    }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
