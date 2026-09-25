/**
 * Inline form validation with text messages.
 *
 * Opt a form in with [data-validate]. Rules are read from the markup that is
 * already there (required, type=email/tel, minlength, min/max, pattern) plus a
 * few extras: data-rule="phone|email|password", data-match="<selector>",
 * data-label, data-message, data-match-message, data-max-message.
 *
 * Loaded BEFORE shop.js on purpose: both listen for submit on document, and an
 * invalid form calls stopImmediatePropagation() so the AJAX handlers in shop.js
 * never fire for it.
 */
(function () {
    'use strict';

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;
    var IN_MOBILE_RE = /^[6-9]\d{9}$/;

    /* A password has to survive more than a length check: "12345678" and
       "password" both clear 8 characters and are still trivially guessable. */
    var PW_COMMON = [
        'password', 'passw0rd', 'p@ssword', '12345678', '123456789', '1234567890',
        'qwertyui', 'qwerty123', 'iloveyou', 'admin123', 'welcome1', 'abc12345',
        'letmein1', 'jewellery', 'shivani123'
    ];

    /** @return '' when the password is acceptable, else the reason it is not. */
    function passwordProblem(value) {
        if (value.length < 8) {
            return 'Password must be at least 8 characters.';
        }
        var lower = value.toLowerCase();
        if (PW_COMMON.indexOf(lower) !== -1) {
            return 'That password is too common. Please choose something harder to guess.';
        }
        if (/^(.)\1+$/.test(value)) {
            return 'Password cannot be the same character repeated.';
        }
        if (/^\d+$/.test(value)) {
            return 'Password cannot be digits only — add letters too.';
        }
        if (!/[A-Za-z]/.test(value) || !/\d/.test(value)) {
            return 'Password must include at least one letter and one number.';
        }
        return '';
    }

    /* ---------- helpers ---------- */

    function labelFor(field) {
        var explicit = field.getAttribute('data-label');
        if (explicit) return explicit;

        var lab = null;
        if (field.id) {
            try { lab = document.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(field.id) : field.id) + '"]'); } catch (e) { lab = null; }
        }
        if (!lab) {
            // .field is the admin console's wrapper; the rest are storefront
            var group = field.closest('.field, .contact__form--list, .col, .mb-20, .mb-15, .mb-10');
            if (group) lab = group.querySelector('label');
        }
        if (lab) {
            var t = lab.textContent
                .replace(/\*/g, '')
                .replace(/\((optional|required)[^)]*\)/gi, '')
                .trim();
            if (t) return t;
        }
        return field.getAttribute('placeholder') || 'This field';
    }

    /** Where the message goes: inside the field's group, after the control. */
    function anchorFor(field) {
        // [data-error-anchor] lets a page say exactly where the message belongs
        // (e.g. the product page's buy row, so it doesn't land inside the
        // quantity stepper). .field is the admin's wrapper.
        return field.closest('[data-error-anchor], .field, .contact__form--list') || field.parentElement;
    }

    function setError(field, message) {
        var anchor = anchorFor(field);
        if (!anchor) return;
        var err = anchor.querySelector(':scope > .field__error');

        if (!message) {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
            if (err) err.remove();
            return;
        }
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (!err) {
            err = document.createElement('span');
            err.className = 'field__error';
            anchor.appendChild(err);
        }
        err.textContent = message;
    }

    /* ---------- the rules ---------- */

    /**
     * The real value of a control.
     *
     * datepicker.js moves the `name` onto a hidden shadow input and leaves the
     * visible box as a read-only label, so `field.value` is a formatted string
     * and `field.name` is gone. It hangs that shadow input off `.dpValue`;
     * reading through it is what lets `required` apply to a picked date.
     */
    function fieldValue(field) {
        if (field.dpValue) return (field.dpValue.value || '').trim();
        return (field.value || '').trim();
    }

    /** Skip controls that carry no value of their own. */
    function isValidatable(field) {
        if (field.disabled || field.type === 'submit' || field.type === 'button' || field.type === 'reset') {
            return false;
        }
        // a date picker's label input has no name; its shadow input has it
        if (field.dpValue) return true;
        return field.type !== 'hidden' && !!field.name;
    }

    function validateField(field) {
        if (!isValidatable(field)) {
            return '';
        }

        var label = labelFor(field);
        var rule  = field.getAttribute('data-rule') || '';
        var value = fieldValue(field);
        var isCheckbox = field.type === 'checkbox' || field.type === 'radio';

        if (field.hasAttribute('required')) {
            if (isCheckbox ? !field.checked : value === '') {
                if (isCheckbox) return 'Please select ' + label.toLowerCase() + '.';
                if (field.tagName === 'SELECT') return 'Please choose ' + label.toLowerCase() + '.';
                if (field.dpValue) return 'Please choose ' + label.toLowerCase() + '.';
                return label + ' is required.';
            }
        }

        // Everything past here only applies to a field that has a value, so
        // optional fields stay silent while empty.
        if (value === '' || isCheckbox) return '';

        if (field.type === 'email' || rule === 'email') {
            if (!EMAIL_RE.test(value)) return 'Enter a valid email address, like name@example.com.';
        }

        if (field.type === 'tel' || rule === 'phone') {
            var digits = value.replace(/\D/g, '');
            if (rule === 'phone' || field.hasAttribute('pattern')) {
                if (!IN_MOBILE_RE.test(digits)) return 'Enter a valid 10-digit mobile number starting with 6, 7, 8 or 9.';
            } else if (digits.length < 10) {
                return 'Enter a valid mobile number (at least 10 digits).';
            }
            return '';
        }

        var minLen = field.getAttribute('minlength');
        if (minLen && value.length < Number(minLen)) {
            return label + ' must be at least ' + minLen + ' characters.';
        }
        var maxLen = field.getAttribute('maxlength');
        if (maxLen && value.length > Number(maxLen)) {
            return label + ' cannot be longer than ' + maxLen + ' characters.';
        }

        if (rule === 'password') {
            var pwMsg = passwordProblem(value);
            if (pwMsg) return pwMsg;
        }

        var pattern = field.getAttribute('pattern');
        if (pattern) {
            var re;
            try { re = new RegExp('^(?:' + pattern + ')$'); } catch (e) { re = null; }
            if (re && !re.test(value)) {
                return field.getAttribute('data-message') || field.getAttribute('title') || ('Enter a valid ' + label.toLowerCase() + '.');
            }
        }

        if (field.type === 'number') {
            var n = Number(value);
            if (isNaN(n)) return 'Enter a number.';
            var lo = field.getAttribute('min');
            var hi = field.getAttribute('max');
            if (lo !== null && lo !== '' && n < Number(lo)) {
                return field.getAttribute('data-min-message') || (label + ' must be at least ' + lo + '.');
            }
            if (hi !== null && hi !== '' && n > Number(hi)) {
                return field.getAttribute('data-max-message') || (label + ' cannot be more than ' + hi + '.');
            }
        }

        var matchSel = field.getAttribute('data-match');
        if (matchSel && field.form) {
            var other = field.form.querySelector(matchSel);
            if (other && other.value !== field.value) {
                return field.getAttribute('data-match-message') || 'The two entries do not match.';
            }
        }

        /* data-date-not-after: a date pair that must stay in order.
           The selector names the OTHER picker's submitted value — after
           datepicker.js runs, the visible box is a read-only label and the name
           has moved to a hidden input, so `[name=expires_at]` resolves to the
           value that will actually be saved. Compared as YYYY-MM-DD strings,
           which sorts correctly and lets the two be the same day. */
        var notAfterSel = field.getAttribute('data-date-not-after');
        if (notAfterSel && field.form) {
            var laterField = field.form.querySelector(notAfterSel);
            var laterVal = laterField ? (laterField.dpValue ? laterField.dpValue.value : laterField.value) : '';
            if (laterVal && value.slice(0, 10) > String(laterVal).slice(0, 10)) {
                return field.getAttribute('data-date-message')
                    || 'This date cannot be later than ' + (laterField.getAttribute('data-label') || 'the other date') + '.';
            }
        }

        // data-lt: this value must stay below another field's (sale vs regular price)
        var ltSel = field.getAttribute('data-lt');
        if (ltSel && field.form) {
            var ceiling = field.form.querySelector(ltSel);
            if (ceiling && ceiling.value !== '' && Number(field.value) >= Number(ceiling.value)) {
                return field.getAttribute('data-lt-message') || 'Must be lower than the other value.';
            }
        }

        return '';
    }

    /** @return the first invalid field, or null when the form is clean. */
    function validateForm(form) {
        var first = null;
        Array.prototype.forEach.call(form.querySelectorAll('input, select, textarea'), function (f) {
            /* Skip controls that carry no value of their own instead of calling
               setError('') on them. Two fields can share one error anchor —
               the date picker's hidden shadow input sits in the same group as
               its visible label input — and setError('') clears whatever
               message is already in that anchor. In document order the hidden
               input comes second, so it was erasing the "Please choose
               preferred date." that the visible input had just written: the
               field went red, but the message vanished in the same pass. */
            if (!isValidatable(f)) {
                return;
            }
            var msg = validateField(f);
            setError(f, msg);
            if (msg && !first) first = f;
        });
        return first;
    }

    /* ---------- wiring ---------- */

    function isWatched(field) {
        return field.form && field.form.hasAttribute('data-validate');
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-validate')) return;

        var first = validateForm(form);
        if (first) {
            e.preventDefault();
            e.stopImmediatePropagation();
            try { first.focus({ preventScroll: true }); } catch (err) { first.focus(); }
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });

    /* blur doesn't bubble, so capture it */
    document.addEventListener('blur', function (e) {
        var f = e.target;
        if (f && f.form && isWatched(f)) setError(f, validateField(f));
    }, true);

    /* once a field is flagged, correct the message as the user types */
    document.addEventListener('input', function (e) {
        var f = e.target;
        if (f && isWatched(f) && f.classList.contains('is-invalid')) setError(f, validateField(f));
    });

    document.addEventListener('change', function (e) {
        var f = e.target;
        if (!f || !isWatched(f)) return;
        if (f.dpValue || f.tagName === 'SELECT' || f.type === 'checkbox' || f.type === 'radio' || f.classList.contains('is-invalid')) {
            setError(f, validateField(f));
        }
    });

    /* suppress the browser's own bubbles so only our inline messages show */
    function disableNative() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-validate]'), function (f) {
            f.noValidate = true;
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', disableNative);
    } else {
        disableNative();
    }

    /* let page scripts reuse the same rules (product-details uses this) */
    window.formValidate = { field: validateField, form: validateForm, setError: setError, passwordProblem: passwordProblem };
})();
