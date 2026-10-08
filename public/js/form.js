/**
 * Ugentic shared form runtime.
 *
 * One vanilla, idempotent handler for shortcode, widget, and faithful forms.
 */
(function () {
    'use strict';

    var FORM_SELECTOR = '.ugentic-form__form, form[data-ugentic-form-id], form.form-container[data-nonce]';
    var initializedForms = typeof WeakSet !== 'undefined' ? new WeakSet() : null;
    var strings = (window.ugenticForm && window.ugenticForm.strings) || {};
    var ajaxUrl = (window.ugenticForm && window.ugenticForm.ajaxUrl) ||
        (window.ugenticAjax && window.ugenticAjax.url) ||
        '/wp-admin/admin-ajax.php';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    ready(function () {
        initAll(document);
        observeNewForms();
    });

    // Own managed submissions at the document capture boundary. Imported
    // source bundles often register their own delegated submit handler later;
    // allowing both runtimes to run creates duplicate requests and stale
    // validation after one handler resets the form.
    document.addEventListener('submit', function (event) {
        var form = event.target && event.target.matches && event.target.matches(FORM_SELECTOR)
            ? event.target
            : null;
        if (!form) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (form.__ugenticWizard && !form.__ugenticWizard.atEnd()) {
            form.__ugenticWizard.next();
            return;
        }
        submitForm(form);
    }, true);

    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest
            ? event.target.closest('button, input[type="button"], input[type="submit"], input[type="image"]')
            : null;
        var form = button && button.form;
        if (!form || !form.matches(FORM_SELECTOR)) {
            return;
        }
        if (form.__ugenticWizard && form.__ugenticWizard.handleClick(button, event)) {
            return;
        }
        // Non-submit controls must reach date pickers, Next/Back and other UI.
        if (button.type !== 'submit' && button.type !== 'image') {
            return;
        }
        // Keep the browser's default submit action; only prevent a preserved
        // source click listener from dispatching a second request.
        event.stopImmediatePropagation();
    }, true);

    function initAll(root) {
        toArray(root.querySelectorAll(FORM_SELECTOR)).forEach(initForm);
        initRatingFields(root);
        initConditionalLogic(root);
        initMultiStep(root);
        initSignatureFields(root);
        initInlineValidation(root);
        initFileUploadEnhancement(root);
        initMobileInputModes(root);
        initAccessibility(root);
    }

    function observeNewForms() {
        if (!window.MutationObserver) {
            return;
        }
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                toArray(mutation.addedNodes).forEach(function (node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }
                    if (node.matches && node.matches(FORM_SELECTOR)) {
                        initAll(node.parentNode || document);
                    } else if (node.querySelector && node.querySelector(FORM_SELECTOR)) {
                        initAll(node);
                    }
                });
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    function initForm(form) {
        if (!form) {
            return;
        }

        if (initializedForms) {
            if (initializedForms.has(form)) {
                return;
            }
            initializedForms.add(form);
        } else if (form.__ugenticFormInitialized) {
            return;
        } else {
            form.__ugenticFormInitialized = true;
        }

        form.dataset.ugenticHandled = '1';

        if (!form.hasAttribute('novalidate')) {
            form.setAttribute('novalidate', 'novalidate');
        }

    }

    function submitForm(form) {
        if (form.dataset.ugenticSubmitting === '1') {
            return;
        }

        clearMessages(form);
        clearFieldErrors(form);

        if (!(form.__ugenticWizard ? form.__ugenticWizard.validateAll() : validateForm(form))) {
            focusFirstError(form);
            return;
        }

        var buttonState = setLoading(form, true);
        form.dataset.ugenticSubmitting = '1';

        var formData = new FormData(form);
        formData.set('action', getAction(form));
        formData.set('page_url', window.location.href);

        if (isFaithfulForm(form) && form.dataset.ugenticFormId && !formData.has('ugentic_form_id')) {
            formData.set('ugentic_form_id', form.dataset.ugenticFormId);
        }
        if (isFaithfulForm(form) && form.dataset.ugenticFormId && !formData.has('form_id')) {
            formData.set('form_id', form.dataset.ugenticFormId);
        }
        if (form.dataset.nonce && !formData.has('nonce')) {
            formData.set('nonce', form.dataset.nonce);
        }
        if (form.dataset.emailTo && !formData.has('email_to')) {
            formData.set('email_to', form.dataset.emailTo);
        }
        if (form.dataset.emailSubject && !formData.has('email_subject')) {
            formData.set('email_subject', form.dataset.emailSubject);
        }
        if (form.dataset.emailSignature && !formData.has('email_signature')) {
            formData.set('email_signature', form.dataset.emailSignature);
        }
        appendFormDataset(form, formData);
        appendSubmissionContract(form, formData);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (response) {
                form.dataset.ugenticSubmitting = '0';

                if (response && response.success) {
                    var data = response.data || {};
                    form.dispatchEvent(new CustomEvent('tahmir:form-success', {
                        bubbles: true,
                        detail: {
                            form_id: form.dataset.ugenticFormId || '',
                            mode: form.dataset.ugenticFormMode || ''
                        }
                    }));
                    var redirect = data.redirect || form.dataset.redirect || '';
                    if (redirect) {
                        window.location.href = redirect;
                        return;
                    }
                    setLoading(form, false, buttonState);
                    showSuccess(form, data.message || form.dataset.success || 'Thank you! Your submission has been received.');
                    resetForm(form);
                    return;
                }

                var errorData = (response && response.data) || {};
                showServerErrors(form, errorData.field_errors || {});
                showError(form, errorData.message || strings.error || 'An error occurred. Please try again.');
                focusFirstError(form);
                setLoading(form, false, buttonState);
            })
            .catch(function () {
                form.dataset.ugenticSubmitting = '0';
                showError(form, strings.error || 'An error occurred. Please try again.');
                setLoading(form, false, buttonState);
            });
    }

    function getAction(form) {
        if (isFaithfulForm(form)) {
            return 'ugentic_faithful_form_submit';
        }
        if (form.dataset.action) {
            return form.dataset.action;
        }
        return 'ugentic_form_submit';
    }

    function isFaithfulForm(form) {
        return form.matches('form[data-ugentic-form-id]') && !form.classList.contains('ugentic-form__form');
    }

    function appendFormDataset(form, formData) {
        if (!form || !form.dataset) {
            return;
        }

        Object.keys(form.dataset).forEach(function (key) {
            if (isInternalDatasetKey(key)) {
                return;
            }

            var fieldName = datasetKeyToFieldName(key);
            if (!fieldName || formData.has(fieldName)) {
                return;
            }

            var value = form.dataset[key];
            if (value === undefined || value === null || value === '') {
                return;
            }

            formData.set(fieldName, value);
        });
    }

    /**
     * Tell the server which faithful controls actually exist and are active in
     * this rendered form. This prevents hidden conditional fields, responsive
     * clones, and stale imported schema fields from blocking valid submissions.
     */
    function appendSubmissionContract(form, formData) {
        if (!isFaithfulForm(form)) {
            return;
        }

        var present = {};
        var active = {};
        var collect = function () { toArray(form.querySelectorAll('input, textarea, select')).forEach(function (field) {
            if (
                !field.name
                || field.disabled
                || field.type === 'hidden'
                || field.type === 'submit'
                || field.type === 'button'
                || field.type === 'reset'
                || field.type === 'image'
            ) {
                return;
            }

            present[field.name] = true;
            if (isVisible(field)) {
                active[field.name] = true;
            }
        }); };
        if (form.__ugenticWizard) {
            form.__ugenticWizard.visitSteps(collect);
        } else {
            collect();
        }

        formData.set('_ugentic_form_contract', JSON.stringify({
            version: 1,
            present: Object.keys(present).slice(0, 250),
            active: Object.keys(active).slice(0, 250)
        }));
    }

    function isInternalDatasetKey(key) {
        if (!key || key.indexOf('ugentic') === 0) {
            return true;
        }

        return [
            'action',
            'nonce',
            'emailTo',
            'emailSubject',
            'emailSignature',
            'redirect',
            'success',
            'submitting',
            'handled',
            'inlineReady'
        ].indexOf(key) !== -1;
    }

    function datasetKeyToFieldName(key) {
        return String(key)
            .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
            .replace(/[^a-zA-Z0-9_]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .toLowerCase();
    }

    function validateForm(form, scope) {
        scope = scope || form;
        var valid = true;
        var checkedGroups = {};

        toArray(scope.querySelectorAll('.ugentic-form__field--required, .form-field.is-required')).forEach(function (wrapper) {
            if (!isVisible(wrapper) || hasGroupValue(wrapper)) {
                return;
            }
            valid = false;
            setFieldError(wrapper, 'This field is required.');
        });

        toArray(scope.querySelectorAll('input, textarea, select')).forEach(function (field) {
            if (!shouldValidate(field)) {
                return;
            }

            var wrapper = fieldWrapper(field);
            var value = fieldValue(field);
            var required = isRequiredField(field);

            if (required && isCheckable(field)) {
                var groupKey = (field.type || '') + ':' + (field.name || field.id || '');
                if (!checkedGroups[groupKey]) {
                    checkedGroups[groupKey] = true;
                    if (!checkableGroupHasValue(field)) {
                        valid = false;
                        setFieldError(wrapper, 'This field is required.');
                    }
                }
                return;
            }

            if (required && value === '') {
                valid = false;
                setFieldError(wrapper, 'This field is required.');
                return;
            }
            if (value === '') {
                return;
            }
            if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                valid = false;
                setFieldError(wrapper, 'Please enter a valid email address.');
            } else if (field.type === 'url' && !isUrl(value)) {
                valid = false;
                setFieldError(wrapper, 'Please enter a valid URL.');
            } else if (field.type === 'tel' && !/^[\d\s()+.\-]{7,}$/.test(value)) {
                valid = false;
                setFieldError(wrapper, 'Please enter a valid phone number.');
            } else if (field.type === 'number') {
                var num = Number(value);
                if (Number.isNaN(num)) {
                    valid = false;
                    setFieldError(wrapper, 'Please enter a number.');
                } else if (field.min !== '' && num < Number(field.min)) {
                    valid = false;
                    setFieldError(wrapper, 'Value is below the minimum.');
                } else if (field.max !== '' && num > Number(field.max)) {
                    valid = false;
                    setFieldError(wrapper, 'Value is above the maximum.');
                }
            }

            var minLength = field.dataset.minlength || field.getAttribute('minlength');
            var maxLength = field.getAttribute('maxlength');
            if (minLength && value.length < parseInt(minLength, 10)) {
                valid = false;
                setFieldError(wrapper, 'Minimum ' + minLength + ' characters.');
            }
            if (maxLength && value.length > parseInt(maxLength, 10)) {
                valid = false;
                setFieldError(wrapper, 'Maximum ' + maxLength + ' characters.');
            }
            if ((field.dataset.pattern || field.getAttribute('pattern')) && !matchesPattern(value, field.dataset.pattern || field.getAttribute('pattern'))) {
                valid = false;
                setFieldError(wrapper, 'Please match the required format.');
            }
            if (field.type === 'file' && !validateFileField(field, wrapper)) {
                valid = false;
            }
            if (field.validity && !field.validity.valid && !field.validity.valueMissing) {
                valid = false;
                setFieldError(wrapper, field.validationMessage || 'Please enter a valid value.');
            }
        });

        return valid;
    }

    function shouldValidate(field) {
        if (!field || field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
            return false;
        }
        return isVisible(field);
    }

    function isRequiredField(field) {
        return !!(field.required || field.dataset.ugenticRequired === '1' || field.getAttribute('aria-required') === 'true');
    }

    function isCheckable(field) {
        return field && (field.type === 'checkbox' || field.type === 'radio');
    }

    function checkableGroupHasValue(field) {
        if (!field.name || !field.form) {
            return field.checked;
        }
        var selector = 'input[type="' + field.type + '"][name="' + cssEscape(field.name) + '"]';
        return toArray(field.form.querySelectorAll(selector)).some(function (item) {
            return item.checked;
        });
    }

    function hasGroupValue(wrapper) {
        var fields = toArray(wrapper.querySelectorAll('input, textarea, select')).filter(shouldValidate);
        if (!fields.length) {
            return true;
        }
        var checkable = fields.filter(function (field) {
            return field.type === 'checkbox' || field.type === 'radio';
        });
        if (checkable.length) {
            return checkable.some(function (field) { return field.checked; });
        }
        return fields.some(function (field) { return fieldValue(field) !== ''; });
    }

    function validateFileField(field, wrapper) {
        if (!field.files || !field.files.length) {
            return true;
        }
        var maxMb = parseInt(field.dataset.maxSize || field.dataset.max_size || '0', 10);
        var accept = (field.getAttribute('accept') || '').toLowerCase();
        var allowed = accept ? accept.split(',').map(function (item) { return item.trim(); }).filter(Boolean) : [];
        var valid = true;

        toArray(field.files).forEach(function (file) {
            if (maxMb && file.size > maxMb * 1024 * 1024) {
                setFieldError(wrapper, 'File is larger than ' + maxMb + ' MB.');
                valid = false;
            }
            if (allowed.length && !fileMatchesAccept(file, allowed)) {
                setFieldError(wrapper, 'File type is not allowed.');
                valid = false;
            }
        });

        return valid;
    }

    function fileMatchesAccept(file, allowed) {
        var name = (file.name || '').toLowerCase();
        var type = (file.type || '').toLowerCase();
        return allowed.some(function (rule) {
            if (rule.charAt(0) === '.') {
                return name.endsWith(rule);
            }
            if (rule.endsWith('/*')) {
                return type.indexOf(rule.slice(0, -1)) === 0;
            }
            return type === rule;
        });
    }

    function fieldValue(field) {
        if (field.type === 'checkbox') {
            return field.checked ? (field.value || '1') : '';
        }
        if (field.type === 'radio') {
            var checked = field.form ? field.form.querySelector('input[type="radio"][name="' + cssEscape(field.name) + '"]:checked') : null;
            return checked ? checked.value : '';
        }
        if (field.tagName && field.tagName.toLowerCase() === 'select' && field.multiple) {
            return toArray(field.selectedOptions).map(function (option) { return option.value; }).join(', ');
        }
        return (field.value || '').trim();
    }

    function fieldWrapper(field) {
        return field.closest('.ugentic-form__field, .form-field, fieldset, label') || field.parentNode || field;
    }

    function setFieldError(wrapper, message) {
        if (!wrapper) {
            return;
        }
        wrapper.classList.add('ugentic-form__field--error');
        wrapper.classList.add('has-error');

        var error = wrapper.querySelector('.ugentic-form__field-error, .field-error');
        if (!error) {
            error = document.createElement('span');
            error.className = wrapper.classList.contains('form-field') ? 'field-error' : 'ugentic-form__field-error';
            error.setAttribute('role', 'alert');
            wrapper.appendChild(error);
        }
        error.hidden = false;
        error.textContent = message;
    }

    function clearFieldErrors(form) {
        toArray(form.querySelectorAll('.ugentic-form__field--error, .has-error')).forEach(function (wrapper) {
            wrapper.classList.remove('ugentic-form__field--error');
            wrapper.classList.remove('has-error');
        });
        toArray(form.querySelectorAll('.ugentic-form__field-error, .field-error')).forEach(function (error) {
            error.hidden = true;
            error.textContent = '';
        });
    }

    function showServerErrors(form, errors) {
        Object.keys(errors || {}).forEach(function (name) {
            var field = form.querySelector('[name="' + cssEscape(name) + '"], [name="' + cssEscape(name) + '[]"]');
            setFieldError(fieldWrapper(field), errors[name]);
        });
    }

    function focusFirstError(form) {
        var first = form.querySelector('.ugentic-form__field--error input, .ugentic-form__field--error textarea, .ugentic-form__field--error select, .has-error input, .has-error textarea, .has-error select');
        if (first && typeof first.focus === 'function') {
            if (form.__ugenticWizard) form.__ugenticWizard.reveal(first);
            first.focus({ preventScroll: false });
        }
    }

    function setLoading(form, loading, state) {
        var button = form.querySelector('.ugentic-form__button, .form-button, button[type="submit"], input[type="submit"], button:not([type])');
        if (!button) {
            return {};
        }
        if (loading) {
            var buttonText = button.querySelector('.form-btn-text');
            var currentText = buttonText ? buttonText.textContent : button.value || button.textContent;
            var currentHtml = buttonText || button.tagName.toLowerCase() === 'input' ? '' : button.innerHTML;
            button.disabled = true;
            button.classList.add('is-loading');
            if (buttonText) {
                buttonText.textContent = form.dataset.loadingText || strings.submitting || 'Submitting...';
            } else if (button.tagName.toLowerCase() === 'input') {
                button.value = form.dataset.loadingText || strings.submitting || 'Submitting...';
            } else {
                button.textContent = form.dataset.loadingText || strings.submitting || 'Submitting...';
            }
            return { button: button, text: currentText, html: currentHtml };
        }
        if (state && state.button) {
            state.button.disabled = false;
            state.button.classList.remove('is-loading');
            var stateText = state.button.querySelector('.form-btn-text');
            if (stateText) {
                stateText.textContent = state.text;
            } else if (state.button.tagName.toLowerCase() === 'input') {
                state.button.value = state.text;
            } else if (state.html) {
                state.button.innerHTML = state.html;
            } else {
                state.button.textContent = state.text;
            }
        }
        return state || {};
    }

    function showSuccess(form, message) {
        clearMessages(form);

        var success = form.querySelector('.ugentic-form__success, .form-message--success, .form-status');
        if (success) {
            var text = success.querySelector('.form-message-text');
            if (text) {
                text.textContent = message;
            } else {
                success.innerHTML = message;
            }
            success.hidden = false;
            success.style.display = '';
            success.setAttribute('role', 'status');
            success.classList.remove('is-error');
            success.classList.add('is-success');
        } else {
            success = document.createElement('div');
            success.className = 'ugentic-form__faithful-message ugentic-form__faithful-message--success';
            success.setAttribute('role', 'status');
            success.style.cssText = 'padding:16px;margin:16px 0;border-radius:8px;background:#d1fae5;color:#065f46;';
            success.textContent = message;
            form.parentNode.insertBefore(success, form);
        }

        var messages = form.querySelector('.ugentic-form__messages');
        if (messages) {
            messages.style.display = '';
        }
        scrollToMessage(success);
    }

    function showError(form, message) {
        var error = form.querySelector('.ugentic-form__error, .form-message--error, .form-status');
        if (error) {
            var text = error.querySelector('.form-message-text');
            if (text) {
                text.textContent = message;
            } else {
                error.textContent = message;
            }
            error.hidden = false;
            error.style.display = '';
            error.setAttribute('role', 'alert');
            error.classList.remove('is-success');
            error.classList.add('is-error');
        } else {
            error = document.createElement('div');
            error.className = 'ugentic-form__faithful-message ugentic-form__faithful-message--error';
            error.setAttribute('role', 'alert');
            error.style.cssText = 'padding:16px;margin:16px 0;border-radius:8px;background:#fee2e2;color:#991b1b;';
            error.textContent = message;
            form.insertBefore(error, form.firstChild);
        }

        var messages = form.querySelector('.ugentic-form__messages');
        if (messages) {
            messages.style.display = '';
        }
        scrollToMessage(error);
    }

    function clearMessages(form) {
        toArray(form.querySelectorAll('.ugentic-form__faithful-message')).forEach(function (message) {
            message.remove();
        });
        toArray(form.querySelectorAll('.ugentic-form__success, .ugentic-form__error, .form-message--success, .form-message--error, .form-status')).forEach(function (message) {
            message.classList.remove('is-success', 'is-error');
            if (message.classList.contains('ugentic-form__success') || message.classList.contains('ugentic-form__error')) {
                message.innerHTML = '';
                message.style.display = 'none';
            } else {
                message.hidden = true;
                message.style.display = 'none';
                message.textContent = '';
            }
        });
        var messages = form.querySelector('.ugentic-form__messages');
        if (messages) {
            messages.style.display = 'none';
        }
    }

    function resetControls(form) {
        toArray(form.elements).forEach(function (field) {
            if (!field || !field.tagName) {
                return;
            }

            var tagName = field.tagName.toLowerCase();
            var type = String(field.type || '').toLowerCase();

            if (type === 'checkbox' || type === 'radio') {
                field.checked = field.defaultChecked;
                field.indeterminate = false;
            } else if (type === 'file') {
                try {
                    field.value = '';
                } catch (error) {
                    // Browsers may reject programmatic file-field assignment.
                }
            } else if (tagName === 'select') {
                toArray(field.options).forEach(function (option) {
                    option.selected = option.defaultSelected;
                });
                if (!field.multiple && field.selectedIndex < 0 && field.options.length) {
                    field.selectedIndex = 0;
                }
            } else if (type !== 'button' && type !== 'submit' && type !== 'reset' && type !== 'image') {
                field.value = field.defaultValue;
            }

            if (typeof field.setCustomValidity === 'function') {
                field.setCustomValidity('');
            }
        });
    }

    function resetForm(form) {
        form.dataset.ugenticResetting = '1';
        clearFieldErrors(form);
        // Restore native defaults without dispatching a reset event into a
        // preserved source bundle that may immediately validate empty fields.
        resetControls(form);
        if (form.__ugenticWizard) form.__ugenticWizard.reset();
        form.dataset.ugenticSubmitting = '0';
        toArray(form.querySelectorAll('.ugentic-form__rating-star')).forEach(function (star) {
            star.classList.remove('active', 'hover');
        });
        toArray(form.querySelectorAll('.ugentic-form__slider')).forEach(function (slider) {
            if (slider.nextElementSibling) {
                slider.nextElementSibling.textContent = slider.value;
            }
        });
        toArray(form.querySelectorAll('.ugentic-form__signature-canvas')).forEach(function (canvas) {
            clearSignature(canvas);
        });
        toArray(form.querySelectorAll('.ugentic-form__file-preview, .form-file-preview')).forEach(function (preview) {
            preview.remove();
        });
        clearFieldErrors(form);

        // Clear any late validation queued by other imported field listeners,
        // then release the reset guard.
        window.requestAnimationFrame(function () {
            clearFieldErrors(form);
            window.setTimeout(function () {
                clearFieldErrors(form);
                delete form.dataset.ugenticResetting;
            }, 80);
        });
    }

    function scrollToMessage(element) {
        if (!element || !element.getBoundingClientRect) {
            return;
        }
        var top = element.getBoundingClientRect().top + window.pageYOffset - 100;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    function initConditionalLogic(root) {
        toArray(root.querySelectorAll('.ugentic-form__field[data-conditions]')).forEach(function (fieldWrapperEl) {
            if (fieldWrapperEl.dataset.ugenticConditionsReady === '1') {
                return;
            }
            fieldWrapperEl.dataset.ugenticConditionsReady = '1';

            var conditions;
            try {
                conditions = JSON.parse(fieldWrapperEl.getAttribute('data-conditions') || '{}');
            } catch (error) {
                return;
            }
            if (!conditions || !conditions.enabled) {
                return;
            }

            var form = fieldWrapperEl.closest('form');
            if (!form) {
                return;
            }
            var fieldId = conditions.fieldId || conditions.field || '';
            var sourceWrapper = form.querySelector('[data-field-id="' + cssEscape(fieldId) + '"]');
            if (!sourceWrapper) {
                sourceWrapper = form.querySelector('[name="' + cssEscape(fieldId) + '"]') ? fieldWrapper(form.querySelector('[name="' + cssEscape(fieldId) + '"]')) : null;
            }
            if (!sourceWrapper) {
                return;
            }

            var inputs = toArray(sourceWrapper.querySelectorAll('input, textarea, select'));
            if (!inputs.length) {
                return;
            }

            var evaluate = function () {
                var value = inputs.map(fieldValue).filter(Boolean).join(', ');
                var expected = String(conditions.value || '');
                var met = false;
                switch (conditions.operator || 'equals') {
                    case 'not_equals':
                        met = value !== expected;
                        break;
                    case 'contains':
                        met = value.indexOf(expected) !== -1;
                        break;
                    case 'not_empty':
                        met = value !== '';
                        break;
                    case 'empty':
                        met = value === '';
                        break;
                    case 'equals':
                    default:
                        met = value === expected;
                        break;
                }
                var show = (conditions.action || 'show') === 'show' ? met : !met;
                fieldWrapperEl.hidden = !show;
                toArray(fieldWrapperEl.querySelectorAll('input, textarea, select')).forEach(function (input) {
                    input.disabled = !show;
                });
            };

            inputs.forEach(function (input) {
                input.addEventListener('change', evaluate);
                input.addEventListener('input', evaluate);
            });
            evaluate();
        });
    }

    function initMultiStep(root) {
        toArray(root.querySelectorAll(FORM_SELECTOR)).forEach(function (form) {
            if (form.__ugenticWizard) return;
            var selector = '.ugentic-form__step, [data-ugentic-step], [data-step], .form-step, .wizard-step';
            var candidates = toArray(form.querySelectorAll(selector)).filter(function (step) {
                return step.querySelector('input,select,textarea') && !step.parentElement.closest(selector);
            });
            // Ordinary visible fieldsets are groups, not necessarily a wizard.
            if (candidates.length < 2) {
                var fieldsets = toArray(form.querySelectorAll('fieldset')).filter(function (step) {
                    return !step.parentElement.closest('fieldset') && step.querySelector('input,select,textarea');
                });
                if (fieldsets.length > 1 && (fieldsets.some(function (step) { return !isVisible(step); })
                    || form.querySelector('[data-next], [data-prev], .next-step, .prev-step'))) candidates = fieldsets;
            }
            // AI-assisted imports mark the original controls instead of
            // rewriting their visual DOM. If the source wizard containers
            // disappeared with its JavaScript bundle, rebuild only the step
            // wrappers while leaving every field node and its styling intact.
            if (candidates.length < 2 && form.dataset.ugenticAiFlow === 'multi_step') {
                var marked = toArray(form.querySelectorAll('[data-ugentic-ai-step]'));
                var grouped = {};
                var usedWrappers = [];
                marked.forEach(function (control) {
                    var index = parseInt(control.dataset.ugenticAiStep || '0', 10);
                    if (!Number.isFinite(index) || index < 0) index = 0;
                    var wrapper = control.closest('[data-field], .form-group, .field-group, .field-wrapper, .form-field, label, p, li');
                    if (!wrapper || wrapper === form || !form.contains(wrapper)) wrapper = control;
                    var wrapperMarkers = toArray(wrapper.querySelectorAll('[data-ugentic-ai-step]'));
                    if (wrapperMarkers.some(function (item) {
                        return parseInt(item.dataset.ugenticAiStep || '0', 10) !== index;
                    })) wrapper = control;
                    if (usedWrappers.indexOf(wrapper) !== -1) return;
                    usedWrappers.push(wrapper);
                    grouped[index] = grouped[index] || {
                        label: control.dataset.ugenticAiStepLabel || ('Step ' + (index + 1)),
                        items: []
                    };
                    grouped[index].items.push(wrapper);
                });
                var groupIndexes = Object.keys(grouped).map(function (key) { return parseInt(key, 10); }).sort(function (a, b) { return a - b; });
                if (groupIndexes.length > 1) {
                    var firstItem = grouped[groupIndexes[0]].items[0];
                    var parent = firstItem && firstItem.parentNode;
                    if (parent) {
                        var insertionPoint = document.createComment('Tahmir AI form steps');
                        parent.insertBefore(insertionPoint, firstItem);
                        var generatedSteps = [];
                        groupIndexes.forEach(function (index) {
                            var group = grouped[index];
                            var step = document.createElement('div');
                            step.className = 'ugentic-form__step ugentic-form__step--ai';
                            step.dataset.ugenticStep = String(index);
                            step.dataset.stepLabel = group.label;
                            parent.insertBefore(step, insertionPoint);
                            group.items.forEach(function (item) {
                                if (item && form.contains(item)) step.appendChild(item);
                            });
                            generatedSteps.push(step);
                        });
                        insertionPoint.remove();
                        candidates = generatedSteps.filter(function (step) {
                            return step.querySelector('input,select,textarea');
                        });
                    }
                }
            }
            if (candidates.length < 2) return;
            var steps = candidates, current = 0;
            toArray(form.querySelectorAll('[data-ugentic-generated-progress]')).forEach(function (node) { node.remove(); });
            form.dataset.ugenticStepsReady = '1';
            var progress = document.createElement('div');
            progress.className = 'ugentic-form__progress';
            progress.dataset.ugenticGeneratedProgress = '1';
            progress.setAttribute('aria-label', 'Form progress');
            steps.forEach(function (step, index) {
                step.dataset.ugenticStep = String(index);
                var item = document.createElement('div');
                item.className = 'ugentic-form__progress-step';
                var dot = document.createElement('div');
                dot.className = 'ugentic-form__progress-dot';
                dot.textContent = index + 1;
                item.appendChild(dot);
                progress.appendChild(item);
            });
            steps[0].parentNode.insertBefore(progress, steps[0]);

            function direction(button) {
                if (button.matches('[data-ugentic-step-next], [data-next], .next-step, .ugentic-form__step-btn--next')) return 'next';
                if (button.matches('[data-ugentic-step-prev], [data-prev], .prev-step, .ugentic-form__step-btn--prev')) return 'prev';
                var text = (button.value || button.textContent || '').trim().toLowerCase();
                if (/^(next|continue|next step)$/.test(text)) return 'next';
                if (/^(back|previous|previous step)$/.test(text)) return 'prev';
                return '';
            }
            steps.forEach(function (step, index) {
                var buttons = toArray(step.querySelectorAll('button,input[type="button"],input[type="submit"]'));
                var nav = document.createElement('div');
                nav.className = 'ugentic-form__step-nav';
                if (index > 0 && !buttons.some(function (button) { return direction(button) === 'prev'; }))
                    nav.appendChild(stepButton('prev', 'Previous'));
                if (index < steps.length - 1 && !buttons.some(function (button) { return direction(button) === 'next'; }))
                    nav.appendChild(stepButton('next', 'Next'));
                if (nav.children.length) step.appendChild(nav);
            });
            var submitButtons = toArray(form.querySelectorAll('button,input[type="submit"],input[type="image"]')).filter(function (button) {
                return (button.type === 'submit' || button.type === 'image') && !direction(button);
            });
            if (!submitButtons.length) {
                var submitButton = stepButton('submit', 'Submit');
                submitButton.type = 'submit';
                steps[steps.length - 1].appendChild(submitButton);
                submitButtons.push(submitButton);
            }

            function renderStep() {
                steps.forEach(function (step, index) {
                    var active = index === current;
                    step.classList.toggle('active', active);
                    step.hidden = !active;
                    step.setAttribute('aria-hidden', active ? 'false' : 'true');
                    step.removeAttribute('inert');
                    // Source frameworks often leave hidden classes/inline styles.
                    step.style.setProperty('display', active ? (step.dataset.ugenticStepDisplay || 'block') : 'none', 'important');
                    step.style.setProperty('visibility', 'visible');
                });
                toArray(progress.children).forEach(function (step, index) {
                    step.classList.toggle('active', index === current);
                    step.classList.toggle('completed', index < current);
                    if (index === current) step.setAttribute('aria-current', 'step');
                    else step.removeAttribute('aria-current');
                });
                submitButtons.forEach(function (button) { button.hidden = current !== steps.length - 1; });
                var wrap = form.querySelector('.ugentic-form__submit, .form-submit-wrap');
                if (wrap) wrap.hidden = current !== steps.length - 1;
            }
            steps.forEach(function (step) {
                var display = window.getComputedStyle(step).display;
                if (step.parentElement.classList.contains('ugentic-form__fields')) step.dataset.ugenticStepDisplay = 'grid';
                else if (display !== 'none') step.dataset.ugenticStepDisplay = display;
            });
            function next() {
                if (form.dataset.ugenticSubmitting === '1') return;
                clearFieldErrors(form);
                if (!validateForm(form, steps[current])) { focusFirstError(form); return; }
                if (current < steps.length - 1) { current++; renderStep(); }
                else submitForm(form);
            }
            form.__ugenticWizard = {
                next: next,
                atEnd: function () { return current === steps.length - 1; },
                reset: function () { current = 0; renderStep(); },
                reveal: function (field) {
                    var index = steps.findIndex(function (step) { return step.contains(field); });
                    if (index >= 0) { current = index; renderStep(); }
                },
                visitSteps: function (callback) {
                    var saved = current;
                    try { steps.forEach(function (step, index) { current = index; renderStep(); callback(); }); }
                    finally { current = saved; renderStep(); }
                },
                validateAll: function () {
                    var saved = current;
                    for (var index = 0; index < steps.length; index++) {
                        current = index; renderStep();
                        if (!validateForm(form)) return false;
                    }
                    current = saved; renderStep();
                    return true;
                },
                handleClick: function (button, event) {
                    var action = direction(button);
                    if (!action) return false;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    if (form.dataset.ugenticSubmitting === '1') return true;
                    if (action === 'next') next();
                    else { current = Math.max(0, current - 1); clearFieldErrors(form); renderStep(); }
                    return true;
                }
            };
            form.addEventListener('reset', function () {
                window.setTimeout(function () { clearFieldErrors(form); form.__ugenticWizard.reset(); }, 0);
            });
            renderStep();
        });
    }

    function stepButton(direction, text) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'ugentic-form__step-btn ugentic-form__step-btn--' + direction;
        button.textContent = text;
        return button;
    }

    function initSignatureFields(root) {
        toArray(root.querySelectorAll('.ugentic-form__signature-canvas')).forEach(function (canvas) {
            if (canvas.dataset.ugenticSignatureReady === '1') {
                return;
            }
            canvas.dataset.ugenticSignatureReady = '1';

            var input = canvas.parentNode.querySelector('input[type="hidden"]');
            var ctx = canvas.getContext('2d');
            var drawing = false;

            function resize() {
                var ratio = window.devicePixelRatio || 1;
                var rect = canvas.getBoundingClientRect();
                var data = ctx.getImageData(0, 0, canvas.width, canvas.height);
                canvas.width = Math.max(1, Math.round(rect.width * ratio));
                canvas.height = Math.max(1, Math.round(rect.height * ratio));
                ctx.scale(ratio, ratio);
                try {
                    ctx.putImageData(data, 0, 0);
                } catch (error) {}
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111827';
            }

            function point(event) {
                var touch = event.touches && event.touches[0];
                var source = touch || event;
                var rect = canvas.getBoundingClientRect();
                return { x: source.clientX - rect.left, y: source.clientY - rect.top };
            }

            function start(event) {
                drawing = true;
                var p = point(event);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                event.preventDefault();
            }

            function move(event) {
                if (!drawing) {
                    return;
                }
                var p = point(event);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                if (input) {
                    input.value = canvas.toDataURL('image/png');
                }
                event.preventDefault();
            }

            function stop() {
                drawing = false;
            }

            resize();
            window.addEventListener('resize', resize);
            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            canvas.addEventListener('mouseup', stop);
            canvas.addEventListener('mouseleave', stop);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', stop);

            var clear = canvas.parentNode.querySelector('.ugentic-form__signature-clear');
            if (clear) {
                clear.addEventListener('click', function () {
                    clearSignature(canvas);
                });
            }
        });
    }

    function clearSignature(canvas) {
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        var input = canvas.parentNode.querySelector('input[type="hidden"]');
        if (input) {
            input.value = '';
        }
    }

    function initRatingFields(root) {
        toArray(root.querySelectorAll('.ugentic-form__rating')).forEach(function (rating) {
            if (rating.dataset.ugenticRatingReady === '1') {
                return;
            }
            rating.dataset.ugenticRatingReady = '1';
            var stars = toArray(rating.querySelectorAll('.ugentic-form__rating-star'));
            stars.forEach(function (star, index) {
                star.addEventListener('click', function () {
                    stars.forEach(function (item, itemIndex) {
                        item.classList.toggle('active', itemIndex <= index);
                    });
                });
                star.addEventListener('mouseenter', function () {
                    stars.forEach(function (item, itemIndex) {
                        item.classList.toggle('hover', itemIndex <= index);
                    });
                });
                star.addEventListener('mouseleave', function () {
                    stars.forEach(function (item) { item.classList.remove('hover'); });
                });
            });
        });
    }

    function initInlineValidation(root) {
        toArray(root.querySelectorAll(FORM_SELECTOR)).forEach(function (form) {
            if (form.dataset.ugenticInlineReady === '1') {
                return;
            }
            form.dataset.ugenticInlineReady = '1';
            form.addEventListener('input', function (event) {
                var wrapper = fieldWrapper(event.target);
                if (wrapper) {
                    wrapper.classList.remove('ugentic-form__field--error', 'has-error');
                    var error = wrapper.querySelector('.ugentic-form__field-error, .field-error');
                    if (error) {
                        error.hidden = true;
                        error.textContent = '';
                    }
                }
            });
        });
    }

    function initFileUploadEnhancement(root) {
        toArray(root.querySelectorAll('input[type="file"]')).forEach(function (field) {
            if (field.dataset.ugenticFileReady === '1') {
                return;
            }
            field.dataset.ugenticFileReady = '1';
            field.addEventListener('change', function () {
                var wrapper = field.closest('.ugentic-form__field, .form-field, .form-file-wrap') || field.parentNode;
                toArray(wrapper.querySelectorAll('.ugentic-form__file-preview, .form-file-preview')).forEach(function (preview) {
                    preview.remove();
                });
                if (!field.files || !field.files.length) {
                    return;
                }
                var preview = document.createElement('div');
                preview.className = wrapper.classList.contains('form-file-wrap') ? 'form-file-preview' : 'ugentic-form__file-preview';
                preview.textContent = toArray(field.files).map(function (file) {
                    return file.name;
                }).join(', ');
                wrapper.appendChild(preview);
            });
        });
    }

    function initMobileInputModes(root) {
        toArray(root.querySelectorAll('input')).forEach(function (input) {
            if (input.type === 'email' && !input.inputMode) {
                input.inputMode = 'email';
            } else if (input.type === 'tel' && !input.inputMode) {
                input.inputMode = 'tel';
            } else if (input.type === 'url' && !input.inputMode) {
                input.inputMode = 'url';
            } else if (input.type === 'number' && !input.inputMode) {
                input.inputMode = 'decimal';
            }
        });
    }

    function initAccessibility(root) {
        toArray(root.querySelectorAll(FORM_SELECTOR)).forEach(function (form) {
            if (!form.getAttribute('aria-live')) {
                form.setAttribute('aria-live', 'polite');
            }
        });
    }

    function matchesPattern(value, pattern) {
        try {
            return new RegExp(pattern).test(value);
        } catch (error) {
            return true;
        }
    }

    function isUrl(value) {
        try {
            new URL(value);
            return true;
        } catch (error) {
            return false;
        }
    }

    function isVisible(element) {
        if (!element || element.hidden) {
            return false;
        }
        var node = element;
        while (node && node !== document.documentElement) {
            if (
                node.hidden
                || node.getAttribute('aria-hidden') === 'true'
                || node.hasAttribute('inert')
                || (node.style && (node.style.display === 'none' || node.style.visibility === 'hidden'))
            ) {
                return false;
            }
            if (window.getComputedStyle) {
                var computed = window.getComputedStyle(node);
                if (
                    computed.display === 'none'
                    || computed.visibility === 'hidden'
                    || computed.visibility === 'collapse'
                    || computed.contentVisibility === 'hidden'
                ) {
                    return false;
                }
            }
            node = node.parentElement;
        }
        return true;
    }

    function cssEscape(value) {
        value = String(value || '');
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return value.replace(/["\\]/g, '\\$&');
    }

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }
})();
