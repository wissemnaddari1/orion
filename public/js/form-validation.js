/**
 * Orion – Universal Form Validation
 * Real-time client-side validation with instant feedback (no page refresh needed).
 * Errors appear on blur/change per-field AND on submit.
 * Backed by server-side PHP validation as fallback.
 *
 * Supported data-validate values:
 *   contract, admin-contract, contract-offer, milestone,
 *   login, register, forgot-password, face-login,
 *   admin-ticket-reply, admin-ticket-create, admin-ticket-edit, admin-category,
 *   admin-ticket-status, ticket-create, ticket-reply, profile-picture
 */
function initValidation() {

    /* ═══════════════════════  REGEX helpers  ═══════════════════════ */

    var HTML_RE      = /<[^>]*>/;
    var REPEAT5_RE   = /(.)\1{4,}/;
    var REPEAT9_RE   = /(.)\1{9,}/;
    var LETTER_RE    = /[a-zA-ZÀ-ÿ]/;
    var DATE_RE      = /^\d{4}-\d{2}-\d{2}$/;
    var DECIMALS3_RE = /\.\d{3,}/;
    var EMAIL_RE     = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    var USERNAME_RE  = /^[A-Za-z0-9._-]+$/;

    function today() {
        var d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }

    /* ═══════════════════════  DOM helpers  ═══════════════════════ */

    function clearErrors(form) {
        form.querySelectorAll('.js-field-error').forEach(function (el) { el.remove(); });
        form.querySelectorAll('.js-live-error').forEach(function (el) {
            el.classList.add('hidden');
            el.textContent = '';
        });
        form.querySelectorAll('.border-red-500').forEach(function (el) {
            el.classList.remove('border-red-500');
            el.classList.add('border-gray-300', 'dark:border-slate-600');
        });
        form.querySelectorAll('.js-helper-hidden').forEach(function (el) {
            el.style.display = '';
            el.classList.remove('js-helper-hidden');
        });
    }

    function clearFieldError(field) {
        if (!field) return;
        field.classList.remove('border-red-500');
        field.classList.add('border-gray-300', 'dark:border-slate-600');
        var sib = field.nextElementSibling;
        while (sib && sib.classList.contains('js-field-error')) {
            var next = sib.nextElementSibling;
            sib.remove();
            sib = next;
        }
        var helper = field.nextElementSibling;
        if (helper && helper.classList.contains('js-helper-hidden')) {
            helper.style.display = '';
            helper.classList.remove('js-helper-hidden');
        }
    }

    function showError(field, message) {
        if (!field) return;
        field.classList.remove('border-gray-300', 'dark:border-slate-600');
        field.classList.add('border-red-500');
        var next = field.nextElementSibling;
        if (next && next.classList.contains('invalid-feedback')) {
            next.remove();
            next = field.nextElementSibling;
        }
        if (next && next.tagName === 'P' && !next.classList.contains('js-field-error') && !next.classList.contains('text-red-600')) {
            next.style.display = 'none';
            next.classList.add('js-helper-hidden');
        }
        var p = document.createElement('p');
        p.className = 'mt-1 text-xs text-red-600 dark:text-red-400 js-field-error';
        p.textContent = message;
        field.parentNode.insertBefore(p, field.nextSibling);
    }

    function val(form, name) {
        var el = form.querySelector('[name="' + name + '"]');
        return el ? el.value.trim() : '';
    }
    function fld(form, name) {
        return form.querySelector('[name="' + name + '"]');
    }
    function wordCount(str) {
        return str.split(/\s+/).filter(function(w) { return w.length > 0; }).length;
    }

    /* ═══════════════════════════════════════════════════════════════
       PER-FIELD validators — return error message or '' if valid
       ═══════════════════════════════════════════════════════════════ */

    var fieldRules = {};

    // ── shared title (contract) ──
    function vTitle(v) {
        if (v === '') return 'Title is required.';
        if (v.length < 3) return 'Title must be at least 3 characters.';
        if (v.length > 255) return 'Title must not exceed 255 characters.';
        if (HTML_RE.test(v)) return 'Title must not contain HTML tags.';
        if (!LETTER_RE.test(v)) return 'Title must contain at least one letter.';
        if (REPEAT5_RE.test(v)) return 'Title contains suspicious repeated characters.';
        return '';
    }
    // ── shared title (milestone) ──
    function vMilestoneTitle(v) {
        if (v === '') return 'Title is required.';
        if (v.length < 3) return 'Title must be at least 3 characters.';
        if (v.length > 200) return 'Title must not exceed 200 characters.';
        if (HTML_RE.test(v)) return 'Title must not contain HTML tags.';
        if (!LETTER_RE.test(v)) return 'Title must contain at least one letter.';
        if (REPEAT5_RE.test(v)) return 'Title contains suspicious repeated characters.';
        return '';
    }
    function vSelect(v, label) {
        if (!v || v === '' || v === '0') return 'Please select a ' + label + '.';
        return '';
    }
    function vPrice(v) {
        if (v === '') return 'Price is required.';
        if (isNaN(v) || parseFloat(v) <= 0) return 'Price must be a number greater than 0.';
        if (parseFloat(v) > 9999999.99) return 'Price must not exceed 9,999,999.99.';
        if (parseFloat(v) < 1) return 'Minimum price is 1.';
        if (DECIMALS3_RE.test(v)) return 'Price can have at most 2 decimal places.';
        return '';
    }
    function vCurrency(v) {
        if (['USD', 'EUR', 'TND', 'MAD'].indexOf(v) === -1) return 'Invalid currency.';
        return '';
    }
    function vStartDate(v, allowPast) {
        if (v === '') return 'Start date is required.';
        if (!DATE_RE.test(v) || isNaN(Date.parse(v))) return 'Invalid date format (YYYY-MM-DD).';
        if (!allowPast && new Date(v + 'T00:00:00') < today()) return 'Start date cannot be in the past.';
        return '';
    }
    function vEndDate(v, startVal, allowPast) {
        if (v === '') return 'End date is required.';
        if (!DATE_RE.test(v) || isNaN(Date.parse(v))) return 'Invalid date format (YYYY-MM-DD).';
        if (startVal && DATE_RE.test(startVal)) {
            var dS = new Date(startVal + 'T00:00:00'), dE = new Date(v + 'T00:00:00');
            if (dE <= dS) return 'End date must be after start date.';
            if ((dE - dS) > 5 * 365 * 24 * 3600000) return 'Contract duration cannot exceed 5 years.';
        }
        return '';
    }
    function vScope(v) {
        if (v === '') return 'Scope of work is required.';
        if (v.length < 10) return 'Scope must be at least 10 characters.';
        if (v.length > 5000) return 'Scope must not exceed 5000 characters.';
        if (HTML_RE.test(v)) return 'Scope must not contain HTML tags.';
        if (wordCount(v) < 3) return 'Scope must contain at least 3 words.';
        if (REPEAT9_RE.test(v)) return 'Scope contains suspicious repeated text.';
        return '';
    }
    function vEmail(v) {
        if (v === '') return 'Email is required.';
        if (!EMAIL_RE.test(v)) return 'Please enter a valid email address.';
        return '';
    }
    function vPassword(v) {
        if (v === '') return 'Password is required.';
        if (v.length < 6) return 'Password must be at least 6 characters.';
        return '';
    }
    function vDescription(v, max) {
        if (v !== '') {
            if (v.length > (max || 2000)) return 'Must not exceed ' + (max || 2000) + ' characters.';
            if (HTML_RE.test(v)) return 'Must not contain HTML tags.';
            if (REPEAT9_RE.test(v)) return 'Contains suspicious repeated text.';
        }
        return '';
    }

    /* ═══════════════════════════════════════════════════════════════
       FORM VALIDATORS — each returns error count
       ═══════════════════════════════════════════════════════════════ */

    /* ── Contract (client / worker edit) ── */
    function validateContract(form) {
        clearErrors(form);
        var n = 0, f, msg;

        // Title
        msg = vTitle(val(form, 'title'));
        if (msg) { showError(fld(form, 'title'), msg); n++; }

        // Worker (client forms)
        f = fld(form, 'worker_id');
        if (f) { msg = vSelect(f.value, 'worker'); if (msg) { showError(f, msg); n++; } }

        // Price
        msg = vPrice(val(form, 'agreed_price'));
        if (msg) { showError(fld(form, 'agreed_price'), msg); n++; }

        // Currency
        f = fld(form, 'currency');
        if (f && f.tagName === 'SELECT') { msg = vCurrency(f.value); if (msg) { showError(f, msg); n++; } }

        // Start Date
        msg = vStartDate(val(form, 'start_date'), false);
        if (msg) { showError(fld(form, 'start_date'), msg); n++; }

        // End Date
        msg = vEndDate(val(form, 'end_date'), val(form, 'start_date'), false);
        if (msg) { showError(fld(form, 'end_date'), msg); n++; }

        // Scope
        msg = vScope(val(form, 'scope'));
        if (msg) { showError(fld(form, 'scope'), msg); n++; }

        return n === 0;
    }

    /* ── Admin Contract (create / edit — allows past dates for existing contracts) ── */
    function validateAdminContract(form) {
        clearErrors(form);
        var n = 0, f, msg;

        // Title
        msg = vTitle(val(form, 'title'));
        if (msg) { showError(fld(form, 'title'), msg); n++; }

        // Client (admin create)
        f = fld(form, 'client_id');
        if (f) { msg = vSelect(f.value, 'client'); if (msg) { showError(f, msg); n++; } }

        // Worker (admin create)
        f = fld(form, 'worker_id');
        if (f) { msg = vSelect(f.value, 'worker'); if (msg) { showError(f, msg); n++; } }

        // Price
        msg = vPrice(val(form, 'agreed_price'));
        if (msg) { showError(fld(form, 'agreed_price'), msg); n++; }

        // Currency
        f = fld(form, 'currency');
        if (f && f.tagName === 'SELECT') { msg = vCurrency(f.value); if (msg) { showError(f, msg); n++; } }

        // Start Date (allow past for admin)
        var sd = val(form, 'start_date');
        if (sd === '') { showError(fld(form, 'start_date'), 'Start date is required.'); n++; }
        else if (!DATE_RE.test(sd) || isNaN(Date.parse(sd))) { showError(fld(form, 'start_date'), 'Invalid date format.'); n++; }

        // End Date
        var ed = val(form, 'end_date');
        if (ed === '') { showError(fld(form, 'end_date'), 'End date is required.'); n++; }
        else if (!DATE_RE.test(ed) || isNaN(Date.parse(ed))) { showError(fld(form, 'end_date'), 'Invalid date format.'); n++; }
        else if (sd && DATE_RE.test(sd)) {
            var dS = new Date(sd + 'T00:00:00'), dE = new Date(ed + 'T00:00:00');
            if (dE <= dS) { showError(fld(form, 'end_date'), 'End date must be after start date.'); n++; }
        }

        // Status
        f = fld(form, 'status');
        if (f) {
            var valid = ['DRAFT','PENDING_SIGN','ACTIVE','IN_PROGRESS','COMPLETED','CANCELLED','DISPUTED'];
            if (valid.indexOf(f.value) === -1) { showError(f, 'Invalid status.'); n++; }
        }

        // Scope
        msg = vScope(val(form, 'scope'));
        if (msg) { showError(fld(form, 'scope'), msg); n++; }

        return n === 0;
    }

    /* ── Contract from Offer (client) ── */
    function validateContractOffer(form) {
        clearErrors(form);
        var n = 0, msg;

        msg = vPrice(val(form, 'agreed_price'));
        if (msg) { showError(fld(form, 'agreed_price'), msg); n++; }

        msg = vScope(val(form, 'scope'));
        if (msg) { showError(fld(form, 'scope'), msg); n++; }

        return n === 0;
    }

    /* ── Milestone (worker) ── */
    function validateMilestone(form) {
        clearErrors(form);
        var n = 0, f, msg;

        // Title
        msg = vMilestoneTitle(val(form, 'title'));
        if (msg) { showError(fld(form, 'title'), msg); n++; }

        // Description (optional)
        msg = vDescription(val(form, 'description'), 2000);
        if (msg) { showError(fld(form, 'description'), msg); n++; }

        // Due Date
        var dd = val(form, 'due_date');
        f = fld(form, 'due_date');
        if (dd === '') { showError(f, 'Due date is required.'); n++; }
        else if (!DATE_RE.test(dd) || isNaN(Date.parse(dd))) { showError(f, 'Invalid date format (YYYY-MM-DD).'); n++; }
        else if (new Date(dd + 'T00:00:00') < today()) { showError(f, 'Due date cannot be in the past.'); n++; }

        // Order Index
        var oi = val(form, 'order_index');
        f = fld(form, 'order_index');
        if (oi === '') { showError(f, 'Order index is required.'); n++; }
        else if (isNaN(oi) || parseInt(oi) < 1) { showError(f, 'Must be an integer ≥ 1.'); n++; }
        else if (parseInt(oi) > 100) { showError(f, 'Must not exceed 100.'); n++; }
        else if (oi.indexOf('.') !== -1) { showError(f, 'Must be a whole number.'); n++; }

        // Status
        var st = val(form, 'status');
        if (['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'].indexOf(st) === -1) {
            showError(fld(form, 'status'), 'Invalid status.'); n++;
        }

        // Amount (optional)
        var amt = val(form, 'amount');
        if (amt !== '') {
            f = fld(form, 'amount');
            if (isNaN(amt) || parseFloat(amt) < 0) { showError(f, 'Must be a positive number or zero.'); n++; }
            else if (parseFloat(amt) > 9999999.99) { showError(f, 'Must not exceed 9,999,999.99.'); n++; }
            else if (DECIMALS3_RE.test(amt)) { showError(f, 'Max 2 decimal places.'); n++; }
        }

        return n === 0;
    }

    /* ── Login ── */
    function validateLogin(form) {
        clearErrors(form);
        var n = 0;

        var email = val(form, 'email');
        var f = fld(form, 'email');
        if (email === '') { showError(f, 'Email is required.'); n++; }
        else if (!EMAIL_RE.test(email)) { showError(f, 'Please enter a valid email address.'); n++; }

        var pw = val(form, 'password');
        f = fld(form, 'password');
        if (pw === '') { showError(f, 'Password is required.'); n++; }
        else if (pw.length < 6) { showError(f, 'Password must be at least 6 characters.'); n++; }

        return n === 0;
    }

    /* ── Forgot Password ── */
    function validateForgotPassword(form) {
        clearErrors(form);
        var n = 0;

        var email = val(form, 'email');
        var f = fld(form, 'email');
        if (email === '') { showError(f, 'Email is required.'); n++; }
        else if (!EMAIL_RE.test(email)) { showError(f, 'Please enter a valid email address.'); n++; }

        return n === 0;
    }

    /* ── Face Login ── */
    function validateFaceLogin(form) {
        clearErrors(form);
        var n = 0;

        // Email is optional, but if present must be valid
        var email = val(form, 'email');
        if (email !== '' && !EMAIL_RE.test(email)) {
            showError(fld(form, 'email'), 'Please enter a valid email address.'); n++;
        }

        // Face image required
        var fi = fld(form, 'face_image');
        if (fi && fi.files.length === 0) {
            showError(fi.closest('div.mt-2') || fi, 'Please upload a face image.'); n++;
        } else if (fi && fi.files.length > 0) {
            var file = fi.files[0];
            if (!file.type.startsWith('image/')) {
                showError(fi.closest('div.mt-2') || fi, 'File must be an image.'); n++;
            } else if (file.size > 5 * 1024 * 1024) {
                showError(fi.closest('div.mt-2') || fi, 'Image must be under 5MB.'); n++;
            }
        }

        return n === 0;
    }

    /* ── Register (Freelancer + Client) ── */
    function validateRegister(form) {
        clearErrors(form);
        var n = 0, f, v;

        // Helper: find Symfony widget by partial name
        function sfld(partialName) {
            return form.querySelector('[name*="' + partialName + '"]');
        }
        function sval(partialName) {
            var el = sfld(partialName);
            return el ? el.value.trim() : '';
        }
        // Use .js-live-error when present to avoid duplicate messages with live validation
        function showSfError(partialName, msg, fieldKey) {
            var el = sfld(partialName);
            if (!el) return;
            var liveErr = (fieldKey && form.querySelector('.js-live-error[data-field="' + fieldKey + '"]')) || null;
            if (liveErr) {
                var wrapper = liveErr.parentElement;
                if (wrapper) {
                    wrapper.querySelectorAll('.invalid-feedback').forEach(function (fb) { fb.remove(); });
                }
                liveErr.classList.remove('hidden');
                liveErr.classList.add('validation-error-text');
                liveErr.textContent = msg;
            } else {
                showError(el, msg);
            }
        }

        // First name
        v = sval('[firstName]');
        if (v === '') { showSfError('[firstName]', 'First name is required.', 'firstName'); n++; }
        else if (v.length < 2) { showSfError('[firstName]', 'Must be at least 2 characters.', 'firstName'); n++; }

        // Last name
        v = sval('[lastName]');
        if (v === '') { showSfError('[lastName]', 'Last name is required.', 'lastName'); n++; }
        else if (v.length < 2) { showSfError('[lastName]', 'Must be at least 2 characters.', 'lastName'); n++; }

        // Username
        v = sval('[username]');
        if (v === '') { showSfError('[username]', 'Username is required.', 'username'); n++; }
        else if (v.length < 3) { showSfError('[username]', 'Must be at least 3 characters.', 'username'); n++; }
        else if (v.length > 50) { showSfError('[username]', 'Must not exceed 50 characters.', 'username'); n++; }
        else if (!USERNAME_RE.test(v)) { showSfError('[username]', 'Only letters, numbers, dots, underscores and dashes.', 'username'); n++; }

        // Email
        v = sval('[email]');
        if (v === '') { showSfError('[email]', 'Email is required.', 'email'); n++; }
        else if (!EMAIL_RE.test(v)) { showSfError('[email]', 'Please enter a valid email.', 'email'); n++; }

        // Password
        var pw1 = sval('[plainPassword][first]');
        var pw2 = sval('[plainPassword][second]');
        if (pw1 === '') { showSfError('[plainPassword][first]', 'Password is required.', 'plainPassword'); n++; }
        else if (pw1.length < 8) { showSfError('[plainPassword][first]', 'Must be at least 8 characters.', 'plainPassword'); n++; }
        if (pw2 === '') { showSfError('[plainPassword][second]', 'Please confirm your password.', 'plainPasswordSecond'); n++; }
        else if (pw1 !== '' && pw1 !== pw2) { showSfError('[plainPassword][second]', 'Passwords do not match.', 'plainPasswordSecond'); n++; }

        // Certificate file
        var cf = form.querySelector('[name*="[certificateFile]"]');
        if (cf && cf.files.length === 0) {
            showSfError('[certificateFile]', 'Certificate file is required.', 'certificateFile'); n++;
        } else if (cf && cf.files.length > 0) {
            var file = cf.files[0];
            var allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (allowed.indexOf(file.type) === -1) {
                showSfError('[certificateFile]', 'File must be PDF, JPEG, PNG or WebP.', 'certificateFile'); n++;
            } else if (file.size > 5 * 1024 * 1024) {
                showSfError('[certificateFile]', 'File must be under 5MB.', 'certificateFile'); n++;
            }
        }

        // Agree terms
        var at = form.querySelector('[name*="[agreeTerms]"]');
        if (at && !at.checked) {
            showSfError('[agreeTerms]', 'You must agree to the terms.', 'agreeTerms'); n++;
        }

        return n === 0;
    }

    /* ── Admin Ticket Create ── */
    function validateAdminTicketCreate(form) {
        clearErrors(form);
        var n = 0;

        // User (createdBy) — hidden select
        var userSelect = form.querySelector('select[name*="[createdBy]"]');
        if (userSelect) {
            if (!userSelect.value || userSelect.value === '') {
                // Show error on the search input instead (visible field)
                var searchInput = document.getElementById('user-search');
                if (searchInput) { showError(searchInput, 'Please select a user.'); }
                else { showError(userSelect, 'Please select a user.'); }
                n++;
            }
        }

        // Subject
        var subjectField = form.querySelector('input[name*="[subject]"]');
        if (subjectField) {
            var sv = subjectField.value.trim();
            if (sv === '') { showError(subjectField, 'Please enter a subject for the ticket.'); n++; }
            else if (sv.length < 5) { showError(subjectField, 'Subject must be at least 5 characters.'); n++; }
            else if (sv.length > 255) { showError(subjectField, 'Subject cannot be longer than 255 characters.'); n++; }
            else if (HTML_RE.test(sv)) { showError(subjectField, 'Subject must not contain HTML tags.'); n++; }
            else if (REPEAT5_RE.test(sv)) { showError(subjectField, 'Subject contains suspicious repeated characters.'); n++; }
        }

        // Category
        var catField = form.querySelector('select[name*="[category]"]');
        if (catField) {
            if (!catField.value || catField.value === '') { showError(catField, 'Please select a category.'); n++; }
        }

        // Priority
        var prioField = form.querySelector('select[name*="[priority]"]');
        if (prioField) {
            var validPrio = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
            if (!prioField.value || validPrio.indexOf(prioField.value) === -1) { showError(prioField, 'Please select a priority level.'); n++; }
        }

        // Message
        var msgField = form.querySelector('textarea[name*="[message]"]');
        if (msgField) {
            var mv = msgField.value.trim();
            if (mv === '') { showError(msgField, 'Please enter a message.'); n++; }
            else if (mv.length < 10) { showError(msgField, 'Message must be at least 10 characters.'); n++; }
            else if (mv.length > 5000) { showError(msgField, 'Message cannot be longer than 5000 characters.'); n++; }
            else if (HTML_RE.test(mv)) { showError(msgField, 'Message must not contain HTML tags.'); n++; }
        }

        // Attachment (optional) — validate size/type if present
        var fileInput = form.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
            var file = fileInput.files[0];
            var allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'image/jpeg', 'image/png', 'application/zip', 'application/x-rar-compressed'];
            if (file.size > 10 * 1024 * 1024) {
                showError(fileInput, 'Attachment must be under 10MB.'); n++;
            } else if (allowedTypes.indexOf(file.type) === -1) {
                showError(fileInput, 'Please upload a valid document (PDF, Word, Text, Image, or ZIP/RAR).'); n++;
            }
        }

        return n === 0;
    }

    /* ── Admin Category Ticket ── */
    function validateAdminCategory(form) {
        clearErrors(form);
        var n = 0;

        // Category Name
        var nameField = form.querySelector('input[name*="[name]"]');
        if (nameField) {
            var nv = nameField.value.trim();
            if (nv === '') { showError(nameField, 'Category name is required.'); n++; }
            else if (nv.length < 3) { showError(nameField, 'Category name must be at least 3 characters.'); n++; }
            else if (nv.length > 50) { showError(nameField, 'Category name cannot be longer than 50 characters.'); n++; }
            else if (HTML_RE.test(nv)) { showError(nameField, 'Category name must not contain HTML tags.'); n++; }
            else if (!LETTER_RE.test(nv)) { showError(nameField, 'Category name must contain at least one letter.'); n++; }
            else if (REPEAT5_RE.test(nv)) { showError(nameField, 'Category name contains suspicious repeated characters.'); n++; }
        }

        // Description (optional) — validate length only if filled
        var descField = form.querySelector('textarea[name*="[description]"]');
        if (descField) {
            var dv = descField.value.trim();
            if (dv.length > 255) { showError(descField, 'Description cannot be longer than 255 characters.'); n++; }
            else if (dv !== '' && HTML_RE.test(dv)) { showError(descField, 'Description must not contain HTML tags.'); n++; }
        }

        return n === 0;
    }

    /* ── Admin Ticket Edit ── */
    function validateAdminTicketEdit(form) {
        clearErrors(form);
        var n = 0;

        // User (createdBy)
        var userSelect = form.querySelector('select[name*="[createdBy]"]');
        if (userSelect) {
            if (!userSelect.value || userSelect.value === '') { showError(userSelect, 'Please select a user.'); n++; }
        }

        // Subject
        var subjectField = form.querySelector('input[name*="[subject]"]');
        if (subjectField) {
            var sv = subjectField.value.trim();
            if (sv === '') { showError(subjectField, 'Please enter a subject for the ticket.'); n++; }
            else if (sv.length < 5) { showError(subjectField, 'Subject must be at least 5 characters.'); n++; }
            else if (sv.length > 255) { showError(subjectField, 'Subject cannot be longer than 255 characters.'); n++; }
            else if (HTML_RE.test(sv)) { showError(subjectField, 'Subject must not contain HTML tags.'); n++; }
            else if (REPEAT5_RE.test(sv)) { showError(subjectField, 'Subject contains suspicious repeated characters.'); n++; }
        }

        // Category
        var catField = form.querySelector('select[name*="[category]"]');
        if (catField) {
            if (!catField.value || catField.value === '') { showError(catField, 'Please select a category.'); n++; }
        }

        // Priority
        var prioField = form.querySelector('select[name*="[priority]"]');
        if (prioField) {
            var validPrio = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
            if (!prioField.value || validPrio.indexOf(prioField.value) === -1) { showError(prioField, 'Please select a priority level.'); n++; }
        }

        // Status
        var statusField = form.querySelector('select[name*="[status]"]');
        if (statusField) {
            var validStatus = ['OPEN', 'IN_PROGRESS', 'WAITING_USER', 'CLOSED'];
            if (!statusField.value || validStatus.indexOf(statusField.value) === -1) { showError(statusField, 'Please select a valid status.'); n++; }
        }

        // Resolution (optional) — validate length if filled
        var resField = form.querySelector('textarea[name*="[resolution]"]');
        if (resField) {
            var rv = resField.value.trim();
            if (rv.length > 255) { showError(resField, 'Resolution cannot be longer than 255 characters.'); n++; }
            else if (rv !== '' && HTML_RE.test(rv)) { showError(resField, 'Resolution must not contain HTML tags.'); n++; }
        }

        return n === 0;
    }

    /* ── Ticket Create (User) ── */
    function validateTicketCreate(form) {
        clearErrors(form);
        var n = 0;

        // Subject
        var subjectField = form.querySelector('input[name*="[subject]"]');
        if (subjectField) {
            var sv = subjectField.value.trim();
            if (sv === '') { showError(subjectField, 'Please enter a subject for your ticket.'); n++; }
            else if (sv.length < 5) { showError(subjectField, 'Subject must be at least 5 characters.'); n++; }
            else if (sv.length > 255) { showError(subjectField, 'Subject cannot be longer than 255 characters.'); n++; }
            else if (HTML_RE.test(sv)) { showError(subjectField, 'Subject must not contain HTML tags.'); n++; }
            else if (REPEAT5_RE.test(sv)) { showError(subjectField, 'Subject contains suspicious repeated characters.'); n++; }
        }

        // Category
        var catField = form.querySelector('select[name*="[category]"]');
        if (catField) {
            if (!catField.value || catField.value === '') { showError(catField, 'Please select a category.'); n++; }
        }

        // Priority
        var prioField = form.querySelector('select[name*="[priority]"]');
        if (prioField) {
            var validPrio = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
            if (!prioField.value || validPrio.indexOf(prioField.value) === -1) { showError(prioField, 'Please select a priority level.'); n++; }
        }

        // Message
        var msgField = form.querySelector('textarea[name*="[message]"]');
        if (msgField) {
            var mv = msgField.value.trim();
            if (mv === '') { showError(msgField, 'Please enter a message.'); n++; }
            else if (mv.length < 10) { showError(msgField, 'Message must be at least 10 characters.'); n++; }
            else if (mv.length > 5000) { showError(msgField, 'Message cannot be longer than 5000 characters.'); n++; }
            else if (HTML_RE.test(mv)) { showError(msgField, 'Message must not contain HTML tags.'); n++; }
        }

        // Attachment (optional)
        var fileInput = form.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
            var file = fileInput.files[0];
            var allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'image/jpeg', 'image/png', 'application/zip', 'application/x-rar-compressed'];
            if (file.size > 10 * 1024 * 1024) {
                showError(fileInput, 'Attachment must be under 10MB.'); n++;
            } else if (allowedTypes.indexOf(file.type) === -1) {
                showError(fileInput, 'Please upload a valid document (PDF, Word, Text, Image, or ZIP/RAR).'); n++;
            }
        }

        return n === 0;
    }

    /* ── Ticket Reply (User) ── */
    function validateTicketReply(form) {
        clearErrors(form);
        var n = 0;

        // Message
        var ta = form.querySelector('textarea');
        if (ta) {
            var v = ta.value.trim();
            if (v === '') { showError(ta, 'Please enter a reply message.'); n++; }
            else if (v.length < 3) { showError(ta, 'Reply must be at least 3 characters.'); n++; }
            else if (v.length > 5000) { showError(ta, 'Reply cannot be longer than 5000 characters.'); n++; }
        }

        // Attachment (optional)
        var fileInput = form.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
            var file = fileInput.files[0];
            if (file.size > 10 * 1024 * 1024) {
                showError(fileInput, 'Attachment must be under 10MB.'); n++;
            }
        }

        return n === 0;
    }

    /* ── Admin Ticket Reply ── */
    function validateAdminTicketReply(form) {
        clearErrors(form);
        var n = 0;

        // Message — Symfony form widget, find textarea
        var ta = form.querySelector('textarea');
        if (ta) {
            var v = ta.value.trim();
            if (v === '') { showError(ta, 'Message is required.'); n++; }
            else if (v.length < 2) { showError(ta, 'Message must be at least 2 characters.'); n++; }
            else if (v.length > 5000) { showError(ta, 'Message must not exceed 5000 characters.'); n++; }
        }

        // Attachment (optional) — validate size/type if present
        var fileInput = form.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
            var file = fileInput.files[0];
            if (file.size > 10 * 1024 * 1024) {
                showError(fileInput, 'Attachment must be under 10MB.'); n++;
            }
        }

        return n === 0;
    }

    /* ── Admin Ticket Status ── */
    function validateAdminTicketStatus(form) {
        clearErrors(form);
        var n = 0;

        var st = fld(form, 'status');
        if (st) {
            var valid = ['OPEN', 'IN_PROGRESS', 'WAITING_USER', 'CLOSED'];
            if (valid.indexOf(st.value) === -1) { showError(st, 'Please select a valid status.'); n++; }
        }

        return n === 0;
    }

    /* ── Profile Picture Upload ── */
    function validateProfilePicture(form) {
        clearErrors(form);
        var n = 0;

        var fi = fld(form, 'profile_picture');
        if (fi) {
            if (fi.files.length === 0) {
                showError(fi, 'Please choose a picture.'); n++;
            } else {
                var file = fi.files[0];
                if (!file.type.startsWith('image/')) {
                    showError(fi, 'File must be an image.'); n++;
                } else if (file.size > 5 * 1024 * 1024) {
                    showError(fi, 'Image must be under 5MB.'); n++;
                }
            }
        }

        return n === 0;
    }

    /* ── Worker Contract (create — worker selects client, no past dates) ── */
    function validateWorkerContract(form) {
        clearErrors(form);
        var n = 0, f, msg;

        // Title
        msg = vTitle(val(form, 'title'));
        if (msg) { showError(fld(form, 'title'), msg); n++; }

        // Client
        f = fld(form, 'client_id');
        if (f) { msg = vSelect(f.value, 'client'); if (msg) { showError(f, msg); n++; } }

        // Price
        msg = vPrice(val(form, 'agreed_price'));
        if (msg) { showError(fld(form, 'agreed_price'), msg); n++; }

        // Currency
        f = fld(form, 'currency');
        if (f && f.tagName === 'SELECT') { msg = vCurrency(f.value); if (msg) { showError(f, msg); n++; } }

        // Start Date (no past dates for worker)
        msg = vStartDate(val(form, 'start_date'), false);
        if (msg) { showError(fld(form, 'start_date'), msg); n++; }

        // End Date
        msg = vEndDate(val(form, 'end_date'), val(form, 'start_date'), false);
        if (msg) { showError(fld(form, 'end_date'), msg); n++; }

        // Scope
        msg = vScope(val(form, 'scope'));
        if (msg) { showError(fld(form, 'scope'), msg); n++; }

        return n === 0;
    }

    /* ── Worker Contract Edit (allows past dates for existing contracts) ── */
    function validateWorkerContractEdit(form) {
        clearErrors(form);
        var n = 0, msg;

        // Title
        msg = vTitle(val(form, 'title'));
        if (msg) { showError(fld(form, 'title'), msg); n++; }

        // Price
        msg = vPrice(val(form, 'agreed_price'));
        if (msg) { showError(fld(form, 'agreed_price'), msg); n++; }

        // Start Date (allow past for edit)
        var sd = val(form, 'start_date');
        if (sd === '') { showError(fld(form, 'start_date'), 'Start date is required.'); n++; }
        else if (!DATE_RE.test(sd) || isNaN(Date.parse(sd))) { showError(fld(form, 'start_date'), 'Invalid date format.'); n++; }

        // End Date
        var ed = val(form, 'end_date');
        if (ed === '') { showError(fld(form, 'end_date'), 'End date is required.'); n++; }
        else if (!DATE_RE.test(ed) || isNaN(Date.parse(ed))) { showError(fld(form, 'end_date'), 'Invalid date format.'); n++; }
        else if (sd && DATE_RE.test(sd)) {
            var dS = new Date(sd + 'T00:00:00'), dE = new Date(ed + 'T00:00:00');
            if (dE <= dS) { showError(fld(form, 'end_date'), 'End date must be after start date.'); n++; }
            if ((dE - dS) > 5 * 365 * 24 * 3600000) { showError(fld(form, 'end_date'), 'Contract duration cannot exceed 5 years.'); n++; }
        }

        // Scope
        msg = vScope(val(form, 'scope'));
        if (msg) { showError(fld(form, 'scope'), msg); n++; }

        return n === 0;
    }

    /* ═══════════════════════════════════════════════════════════════
       VALIDATOR REGISTRY
       ═══════════════════════════════════════════════════════════════ */

    var validators = {
        'contract':               validateContract,
        'admin-contract':         validateAdminContract,
        'worker-contract':        validateWorkerContract,
        'worker-contract-edit':   validateWorkerContractEdit,
        'contract-offer':         validateContractOffer,
        'milestone':              validateMilestone,
        'login':                  validateLogin,
        'register':               validateRegister,
        'forgot-password':        validateForgotPassword,
        'face-login':             validateFaceLogin,
        'admin-ticket-reply':     validateAdminTicketReply,
        'admin-ticket-create':    validateAdminTicketCreate,
        'admin-ticket-edit':      validateAdminTicketEdit,
        'admin-category':         validateAdminCategory,
        'admin-ticket-status':    validateAdminTicketStatus,
        'ticket-create':          validateTicketCreate,
        'ticket-reply':           validateTicketReply,
        'profile-picture':        validateProfilePicture,
    };

    /* ═══════════════════════════════════════════════════════════════
       ATTACH — submit prevention + scroll to first error
       ═══════════════════════════════════════════════════════════════ */

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        if (form.dataset.validateBound === '1') return;
        form.dataset.validateBound = '1';
        var type = form.getAttribute('data-validate');
        var fn = validators[type];
        if (!fn) return;

        form.addEventListener('submit', function (e) {
            if (!fn(form)) {
                e.preventDefault();
                var first = form.querySelector('.border-red-500');
                if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });

    /* ═══════════════════════════════════════════════════════════════
       LIVE VALIDATION — validate field on blur, clear on input
       ═══════════════════════════════════════════════════════════════ */

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        if (form.dataset.validateLiveBound === '1') return;
        form.dataset.validateLiveBound = '1';
        var type = form.getAttribute('data-validate');
        var fn = validators[type];
        if (!fn) return;

        var fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function (field) {
            // Skip hidden & submit & CSRF
            if (field.type === 'hidden' || field.type === 'submit') return;

            // Clear error immediately on input
            var inputEv = (field.tagName === 'SELECT' || field.type === 'checkbox' || field.type === 'file') ? 'change' : 'input';
            field.addEventListener(inputEv, function () {
                clearFieldError(field);
            });

            // Re-validate on blur (so error shows if still invalid)
            field.addEventListener('blur', function () {
                // Run full form validation silently, then only show error for THIS field
                var bakErrors = form.querySelectorAll('.js-field-error');
                // Save current state, run validation, then restore for other fields
                fn(form);
            });

            // For selects and file inputs, validate on change immediately
            if (field.tagName === 'SELECT' || field.type === 'file' || field.type === 'checkbox') {
                field.addEventListener('change', function () {
                    fn(form);
                });
            }
        });
    });

    /* ═══════════════════════════════════════════════════════════════
       REGISTER LIVE VALIDATION (control saisie)
       Real-time UX feedback for registration forms. Server validation unchanged.
       ═══════════════════════════════════════════════════════════════ */

    (function initRegisterLiveValidation() {
        var EMAIL_LIVE_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        var USERNAME_LIVE_RE = /^[A-Za-z0-9._-]+$/;
        var CERT_EXT_RE = /\.(pdf|jpg|jpeg|png|webp)$/i;
        var CERT_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

        function sval(form, part) {
            var el = form.querySelector('[name*="' + part + '"]');
            return el ? el.value.trim() : '';
        }

        function sfld(form, part) {
            return form.querySelector('[name*="' + part + '"]');
        }

        function setLiveError(form, fieldKey, isValid, message) {
            var div = form.querySelector('.js-live-error[data-field="' + fieldKey + '"]');
            if (!div) return;
            if (isValid) {
                /* Only show feedback when there is an error — hide success messages */
                div.classList.add('hidden');
                div.textContent = '';
                return;
            }
            div.classList.remove('hidden');
            div.classList.remove('text-emerald-500', 'dark:text-emerald-400');
            div.classList.add('validation-error-text');
            div.textContent = message || '';
        }

        function hideLiveError(form, fieldKey) {
            var div = form.querySelector('.js-live-error[data-field="' + fieldKey + '"]');
            if (div) {
                div.classList.add('hidden');
                div.textContent = '';
            }
        }

        function validateFirstName(form) {
            var v = sval(form, '[firstName]');
            if (v === '') { setLiveError(form, 'firstName', false, 'First name is required.'); return; }
            if (v.length < 2) { setLiveError(form, 'firstName', false, 'Must be at least 2 characters.'); return; }
            setLiveError(form, 'firstName', true);
        }

        function validateLastName(form) {
            var v = sval(form, '[lastName]');
            if (v === '') { setLiveError(form, 'lastName', false, 'Last name is required.'); return; }
            if (v.length < 2) { setLiveError(form, 'lastName', false, 'Must be at least 2 characters.'); return; }
            setLiveError(form, 'lastName', true);
        }

        function validateUsername(form) {
            var v = sval(form, '[username]');
            if (v === '') { setLiveError(form, 'username', false, 'Username is required.'); return; }
            if (v.length < 5) { setLiveError(form, 'username', false, 'Must be at least 5 characters.'); return; }
            if (!USERNAME_LIVE_RE.test(v)) { setLiveError(form, 'username', false, 'Only letters, numbers, dots, underscores, dashes.'); return; }
            setLiveError(form, 'username', true);
        }

        function validateEmail(form) {
            var v = sval(form, '[email]');
            if (v === '') { setLiveError(form, 'email', false, 'Email is required.'); return; }
            if (!EMAIL_LIVE_RE.test(v)) { setLiveError(form, 'email', false, 'Please enter a valid email.'); return; }
            setLiveError(form, 'email', true);
        }

        function getPasswordRules(value) {
            return {
                min8: value.length >= 8,
                startsUppercase: /^[A-Z]/.test(value),
                hasUppercase: /[A-Z]/.test(value),
                hasLowercase: /[a-z]/.test(value),
                hasDigit: /\d/.test(value),
                hasSpecial: /[^A-Za-z0-9]/.test(value)
            };
        }

        function allPasswordRulesPass(pw) {
            var r = getPasswordRules(pw);
            return r.min8 && r.startsUppercase && r.hasUppercase && r.hasLowercase && r.hasDigit && r.hasSpecial;
        }

        function validatePlainPassword(form) {
            var pw = sval(form, '[plainPassword][first]');
            if (pw === '') {
                setLiveError(form, 'plainPassword', false, 'Password is required.');
                return;
            }
            if (pw.length < 8) { setLiveError(form, 'plainPassword', false, 'Must be at least 8 characters.'); return; }
            if (!/^[A-Z]/.test(pw)) { setLiveError(form, 'plainPassword', false, 'Must start with uppercase.'); return; }
            if (!/[A-Z]/.test(pw)) { setLiveError(form, 'plainPassword', false, 'Add at least 1 uppercase letter.'); return; }
            if (!/[a-z]/.test(pw)) { setLiveError(form, 'plainPassword', false, 'Add at least 1 lowercase letter.'); return; }
            if (!/\d/.test(pw)) { setLiveError(form, 'plainPassword', false, 'Add at least 1 digit.'); return; }
            if (!/[^A-Za-z0-9]/.test(pw)) { setLiveError(form, 'plainPassword', false, 'Add at least 1 special character.'); return; }
            setLiveError(form, 'plainPassword', true);
        }

        function validatePlainPasswordSecond(form) {
            var pw2 = sval(form, '[plainPassword][second]');
            var f2 = sfld(form, '[plainPassword][second]');
            if (!f2) return;
            if (pw2 === '') {
                hideLiveError(form, 'plainPasswordSecond');
                return;
            }
            var pw1 = sval(form, '[plainPassword][first]');
            if (pw1 !== pw2) { setLiveError(form, 'plainPasswordSecond', false, 'Passwords do not match.'); return; }
            setLiveError(form, 'plainPasswordSecond', true);
        }

        function validateAgreeTerms(form) {
            var cb = form.querySelector('[name*="[agreeTerms]"]');
            if (!cb) return;
            if (!cb.checked) { setLiveError(form, 'agreeTerms', false, 'You must agree to the terms.'); return; }
            setLiveError(form, 'agreeTerms', true, '');
            hideLiveError(form, 'agreeTerms');
        }

        function validateCertificateFile(form) {
            var inp = form.querySelector('[name*="certificateFile"]');
            if (!inp) return;
            if (!inp.files || inp.files.length === 0) {
                setLiveError(form, 'certificateFile', false, 'Certificate file is required.');
                return;
            }
            var f = inp.files[0];
            var extOk = CERT_EXT_RE.test(f.name);
            var mimeOk = CERT_MIMES.indexOf(f.type) !== -1;
            if (!extOk || !mimeOk) {
                setLiveError(form, 'certificateFile', false, 'File must be PDF, JPEG, PNG or WebP.');
                return;
            }
            if (f.size > 5 * 1024 * 1024) {
                setLiveError(form, 'certificateFile', false, 'File must be under 5MB.');
                return;
            }
            setLiveError(form, 'certificateFile', true);
        }

        function updatePasswordChecklist(form) {
            var list = form.querySelector('#password-checklist') || form.querySelector('.password-checklist');
            if (!list) return;
            var inp = form.querySelector('input[name*="[plainPassword][first]"]');
            if (!inp) return;
            var value = inp.value;
            var rules = getPasswordRules(value);
            list.querySelectorAll('[data-rule]').forEach(function (li) {
                var r = li.getAttribute('data-rule');
                var icon = li.querySelector('.rule-icon');
                var text = li.querySelector('.rule-text');
                var ok = rules[r];
                if (icon) {
                    icon.textContent = ok ? '\u2714\uFE0F' : '\u274C';
                    icon.classList.remove('is-valid', 'is-invalid');
                    icon.classList.add(ok ? 'is-valid' : 'is-invalid');
                }
                if (text) {
                    text.classList.remove('is-valid', 'is-invalid');
                    text.classList.add(ok ? 'is-valid' : 'is-invalid');
                }
            });
        }

        function runAllLiveValidation(form) {
            validateFirstName(form);
            validateLastName(form);
            validateUsername(form);
            validateEmail(form);
            validatePlainPassword(form);
            validatePlainPasswordSecond(form);
            validateAgreeTerms(form);
            validateCertificateFile(form);
            updatePasswordChecklist(form);
        }

        document.querySelectorAll('form.js-register-form, form[data-validate="register"]').forEach(function (form) {
            if (form.dataset.registerLiveBound === '1') return;
            form.dataset.registerLiveBound = '1';

            function onInputBlur() { runAllLiveValidation(form); }

            updatePasswordChecklist(form);

            var fields = [
                ['[firstName]', 'firstName'],
                ['[lastName]', 'lastName'],
                ['[username]', 'username'],
                ['[email]', 'email'],
                ['[plainPassword][first]', 'plainPassword'],
                ['[plainPassword][second]', 'plainPasswordSecond']
            ];
            fields.forEach(function (pair) {
                var inp = sfld(form, pair[0]);
                if (inp) {
                    inp.addEventListener('input', onInputBlur);
                    inp.addEventListener('blur', onInputBlur);
                }
            });

            var termsInp = form.querySelector('[name*="[agreeTerms]"]');
            if (termsInp) termsInp.addEventListener('change', onInputBlur);

            var certInp = form.querySelector('[name*="certificateFile"]');
            if (certInp) certInp.addEventListener('change', onInputBlur);

            var pwInp = sfld(form, '[plainPassword][first]');
            if (pwInp) pwInp.addEventListener('input', onInputBlur);
        });
    })();

}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initValidation);
} else {
    initValidation();
}
document.addEventListener('turbo:load', initValidation);
