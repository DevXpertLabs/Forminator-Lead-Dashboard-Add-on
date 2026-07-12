/**
 * Forminator Lead Dashboard — Front-end OTP verification widget
 * Injected into Forminator forms that have OTP enabled in plugin settings.
 */
(function ($) {
    'use strict';

    var cfg = window.dxleda_otp_config || {};

    // State per form element: 'idle' | 'sending' | 'awaiting_code' | 'verified'
    var formStates = new WeakMap();

    function init() {
        if (!cfg.enabled_forms || !cfg.enabled_forms.length) return;

        // Forminator renders forms after DOM ready via its own JS, so we also
        // observe for dynamically inserted forms.
        attachToForms();

        var observer = new MutationObserver(function () {
            attachToForms();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    function attachToForms() {
        // Forminator renders: <form id="forminator-module-{id}" data-form-id="{id}" class="forminator-ui forminator-custom-form ...">
        document.querySelectorAll('form[data-form-id]').forEach(function (form) {
            if (formStates.has(form)) return; // already wired

            var formId = parseInt(form.getAttribute('data-form-id'), 10);
            if (cfg.enabled_forms.indexOf(formId) === -1) return;

            formStates.set(form, 'idle');
            injectWidget(form, formId);
            wireSubmitGuard(form);
        });
    }

    // ------------------------------------------------------------------
    // Widget injection
    // ------------------------------------------------------------------

    function injectWidget(form, formId) {
        var widget = document.createElement('div');
        widget.className = 'fld-otp-widget';
        widget.setAttribute('data-form-id', formId);
        widget.innerHTML =
            '<p class="fld-otp-widget__title">' + esc(cfg.strings.send_otp) + '</p>' +
            '<div class="fld-otp-send-row">' +
                '<button type="button" class="fld-otp-btn fld-otp-btn--send fld-otp-send-btn">' +
                    esc(cfg.strings.send_otp) +
                '</button>' +
            '</div>' +
            '<div class="fld-otp-code-row" style="display:none;">' +
                '<input type="text" class="fld-otp-code-input" maxlength="6" placeholder="000000" autocomplete="one-time-code" inputmode="numeric">' +
                '<button type="button" class="fld-otp-btn fld-otp-btn--verify fld-otp-verify-btn">' +
                    esc(cfg.strings.verify) +
                '</button>' +
            '</div>' +
            '<div class="fld-otp-status"></div>' +
            '<div class="fld-otp-verified">' +
                '<span class="fld-otp-verified__icon">✓</span>' +
                '<span>' + esc(cfg.strings.verified) + '</span>' +
            '</div>' +
            '<button type="button" class="fld-otp-resend">' + esc(cfg.strings.resend) + '</button>';

        // Insert before the submit button wrapper
        var submitBtn = form.querySelector('.forminator-button-submit, [type="submit"], button.forminator-button');
        var insertBefore = submitBtn ? (submitBtn.closest('.forminator-row') || submitBtn.parentNode) : null;

        if (insertBefore && insertBefore.parentNode) {
            insertBefore.parentNode.insertBefore(widget, insertBefore);
        } else {
            form.appendChild(widget);
        }

        wireWidget(form, widget, formId);
    }

    function wireWidget(form, widget, formId) {
        var sendBtn   = widget.querySelector('.fld-otp-send-btn');
        var verifyBtn = widget.querySelector('.fld-otp-verify-btn');
        var codeInput = widget.querySelector('.fld-otp-code-input');
        var statusEl  = widget.querySelector('.fld-otp-status');
        var verifiedEl = widget.querySelector('.fld-otp-verified');
        var resendBtn = widget.querySelector('.fld-otp-resend');

        // -- Send OTP --
        sendBtn.addEventListener('click', function () {
            var email = getEmailValue(form);
            if (!email) {
                showStatus(statusEl, 'error', cfg.strings.enter_email);
                return;
            }
            setState(form, 'sending');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="fld-otp-spinner"></span>';
            clearStatus(statusEl);

            $.ajax({
                url: cfg.ajax_url,
                type: 'POST',
                data: {
                    action:  'dxleda_send_otp',
                    nonce:   cfg.nonce,
                    email:   email,
                    form_id: formId
                },
                success: function (res) {
                    sendBtn.innerHTML = esc(cfg.strings.send_otp);
                    if (res.success) {
                        setState(form, 'awaiting_code');
                        widget.querySelector('.fld-otp-code-row').style.display = 'flex';
                        codeInput.value = '';
                        codeInput.focus();
                        resendBtn.classList.add('is-visible');
                        showStatus(statusEl, 'info', cfg.strings.otp_sent);
                    } else {
                        setState(form, 'idle');
                        sendBtn.disabled = false;
                        showStatus(statusEl, 'error', res.data || cfg.strings.invalid_otp);
                    }
                },
                error: function () {
                    sendBtn.innerHTML = esc(cfg.strings.send_otp);
                    sendBtn.disabled = false;
                    setState(form, 'idle');
                    showStatus(statusEl, 'error', cfg.strings.invalid_otp);
                }
            });
        });

        // -- Resend --
        resendBtn.addEventListener('click', function () {
            setState(form, 'idle');
            widget.querySelector('.fld-otp-code-row').style.display = 'none';
            resendBtn.classList.remove('is-visible');
            sendBtn.disabled = false;
            sendBtn.innerHTML = esc(cfg.strings.send_otp);
            clearStatus(statusEl);
            sendBtn.click();
        });

        // -- Verify OTP --
        verifyBtn.addEventListener('click', function () {
            var code = codeInput.value.trim();
            if (code.length !== 6) return;
            var email = getEmailValue(form);

            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="fld-otp-spinner"></span>';

            $.ajax({
                url: cfg.ajax_url,
                type: 'POST',
                data: {
                    action:  'dxleda_verify_otp',
                    nonce:   cfg.nonce,
                    email:   email,
                    code:    code,
                    form_id: formId
                },
                success: function (res) {
                    if (res.success && res.data && res.data.token) {
                        setState(form, 'verified');
                        injectToken(form, res.data.token);
                        widget.querySelector('.fld-otp-code-row').style.display = 'none';
                        resendBtn.classList.remove('is-visible');
                        verifiedEl.classList.add('is-visible');
                        clearStatus(statusEl);
                    } else {
                        verifyBtn.disabled = false;
                        verifyBtn.innerHTML = esc(cfg.strings.verify);
                        showStatus(statusEl, 'error', cfg.strings.invalid_otp);
                    }
                },
                error: function () {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = esc(cfg.strings.verify);
                    showStatus(statusEl, 'error', cfg.strings.invalid_otp);
                }
            });
        });

        // Allow pressing Enter in the code input
        codeInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyBtn.click();
            }
        });

        // Reset if email changes after OTP was sent
        var emailField = getEmailField(form);
        if (emailField) {
            emailField.addEventListener('input', function () {
                var state = formStates.get(form);
                if (state === 'awaiting_code' || state === 'verified') {
                    resetWidget(form, widget, sendBtn, verifyBtn, codeInput, statusEl, verifiedEl, resendBtn);
                }
            });
        }
    }

    // ------------------------------------------------------------------
    // Submit guard
    // ------------------------------------------------------------------

    function wireSubmitGuard(form) {
        // Forminator submits via its own AJAX handler triggered by a button click.
        // We intercept the native submit event as a fallback and also the submit
        // button click before Forminator's listener fires.
        var submitBtn = form.querySelector('.forminator-button-submit, [type="submit"]');
        if (submitBtn) {
            submitBtn.addEventListener('click', function (e) {
                var state = formStates.get(form);
                if (state !== 'verified') {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    var statusEl = form.querySelector('.fld-otp-status');
                    if (statusEl) showStatus(statusEl, 'error', cfg.strings.otp_required);
                    var widget = form.querySelector('.fld-otp-widget');
                    if (widget) widget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, true); // capture phase so we fire before Forminator
        }

        form.addEventListener('submit', function (e) {
            var state = formStates.get(form);
            if (state !== 'verified') {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        }, true);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    function getEmailField(form) {
        return form.querySelector('input[type="email"]') ||
               form.querySelector('input[name*="email"]');
    }

    function getEmailValue(form) {
        var field = getEmailField(form);
        return field ? field.value.trim() : '';
    }

    function injectToken(form, token) {
        // Hidden field (for standard form posts)
        var existing = form.querySelector('input[name="dxleda_otp_token"]');
        if (existing) existing.remove();
        var hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = 'dxleda_otp_token';
        hidden.value = token;
        form.appendChild(hidden);

        // Cookie — Forminator submits via AJAX and only serializes its own
        // registered fields, so the hidden input above may be skipped.
        // A cookie is sent automatically with every request, including XHR.
        var expires = new Date(Date.now() + 1800000).toUTCString(); // 30 min
        document.cookie = 'dxleda_otp_token=' + encodeURIComponent(token) +
            '; expires=' + expires + '; path=/; SameSite=Strict';
    }

    function resetWidget(form, widget, sendBtn, verifyBtn, codeInput, statusEl, verifiedEl, resendBtn) {
        setState(form, 'idle');
        widget.querySelector('.fld-otp-code-row').style.display = 'none';
        verifiedEl.classList.remove('is-visible');
        resendBtn.classList.remove('is-visible');
        sendBtn.disabled = false;
        sendBtn.innerHTML = esc(cfg.strings.send_otp);
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = esc(cfg.strings.verify);
        codeInput.value = '';
        clearStatus(statusEl);

        // Remove hidden field and clear cookie
        var existing = form.querySelector('input[name="dxleda_otp_token"]');
        if (existing) existing.remove();
        document.cookie = 'dxleda_otp_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Strict';
    }

    function setState(form, state) {
        formStates.set(form, state);
    }

    function showStatus(el, type, msg) {
        el.className = 'fld-otp-status is-' + type;
        el.textContent = msg;
    }

    function clearStatus(el) {
        el.className = 'fld-otp-status';
        el.textContent = '';
    }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}(jQuery));
