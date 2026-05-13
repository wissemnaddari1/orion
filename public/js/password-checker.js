/**
 * Orion – Reusable password checklist (register, reset password, change password).
 * Live validation UX; server-side validation remains authoritative.
 */
(function () {
    'use strict';

    function getPasswordRules(value) {
        var s = String(value || '');
        return {
            min8: s.length >= 8,
            startsUppercase: /^[A-Z]/.test(s),
            hasUppercase: /[A-Z]/.test(s),
            hasLowercase: /[a-z]/.test(s),
            hasDigit: /\d/.test(s),
            hasSpecial: /[^A-Za-z0-9]/.test(s)
        };
    }

    function allPasswordRulesPass(value) {
        var r = getPasswordRules(value);
        return r.min8 && r.startsUppercase && r.hasUppercase && r.hasLowercase && r.hasDigit && r.hasSpecial;
    }

    function updateChecklist(list, value) {
        if (!list) return;
        var rules = getPasswordRules(value);
        var validIcon = 'text-emerald-500 dark:text-emerald-400';
        var invalidIcon = 'text-slate-400 dark:text-slate-500';
        var validText = 'text-emerald-600 dark:text-emerald-400';
        var invalidText = 'text-slate-500 dark:text-slate-400';
        list.querySelectorAll('[data-rule]').forEach(function (li) {
            var ruleName = li.getAttribute('data-rule');
            var ok = rules[ruleName];
            var icon = li.querySelector('.rule-icon');
            var text = li.querySelector('.rule-text');
            if (icon) {
                icon.textContent = ok ? '\u2714\uFE0F' : '\u274C';
                icon.className = 'rule-icon flex-shrink-0 w-4 h-4 ' + (ok ? validIcon : invalidIcon);
            }
            if (text) {
                text.className = 'rule-text ' + (ok ? validText : invalidText);
            }
        });
    }

    /**
     * Init password checklist on a form.
     * - checklist: element with .password-checklist (or #password-checklist)
     * - passwordInput: first password field (plainPassword first, or selector)
     * - submitBtn: optional submit button to enable/disable
     */
    function initPasswordChecklist(options) {
        var form = options.form;
        var checklist = options.checklist || (form && form.querySelector('.password-checklist')) || (form && form.querySelector('#password-checklist'));
        var passwordInput = options.passwordInput || (form && form.querySelector('input[name*="[plainPassword][first]"]')) || (form && form.querySelector('input[name*="plainPassword"][name*="first"]'));
        var submitBtn = options.submitBtn || (form && form.querySelector('button[type="submit"]'));

        if (!checklist || !passwordInput) return;

        function onInput() {
            var value = passwordInput.value;
            updateChecklist(checklist, value);
            if (submitBtn) {
                var matchOk = true;
                var second = form.querySelector('input[name*="[plainPassword][second]"]') || form.querySelector('input[name*="plainPassword"][name*="second"]');
                if (second) matchOk = value === second.value;
                submitBtn.disabled = !allPasswordRulesPass(value) || !matchOk;
            }
        }

        onInput();
        passwordInput.addEventListener('input', onInput);
        passwordInput.addEventListener('blur', onInput);

        var second = form.querySelector('input[name*="[plainPassword][second]"]') || form.querySelector('input[name*="plainPassword"][name*="second"]');
        if (second && submitBtn) {
            second.addEventListener('input', onInput);
        }
    }

    window.passwordChecker = {
        getPasswordRules: getPasswordRules,
        allPasswordRulesPass: allPasswordRulesPass,
        updateChecklist: updateChecklist,
        initPasswordChecklist: initPasswordChecklist
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form .password-checklist').forEach(function (list) {
            var form = list.closest('form');
            if (form && !form.dataset.passwordChecklistInit) {
                form.dataset.passwordChecklistInit = '1';
                initPasswordChecklist({ form: form });
            }
        });
    });
})();
