/* =========================================================================
   Anand Jewellers — Admin console shell
   Theme toggle · sidebar collapse · mobile drawer · command palette · toasts
   Dependency-free.
   ========================================================================= */
(function () {
    'use strict';

    var root = document.documentElement;
    var $  = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    };

    /* ---------------------------------------------------------------- theme */
    function setTheme(theme) {
        root.setAttribute('data-theme', theme);
        store.set('aj-admin-theme', theme);
    }
    function toggleTheme() {
        setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    }
    $$('[data-theme-toggle]').forEach(function (b) {
        b.addEventListener('click', toggleTheme);
    });

    /* ------------------------------------------------------- sidebar states */
    var DRAWER_MAX = 780;   // at or below this the sidebar is an off-canvas drawer
    var RAIL_MAX   = 1100;  // between the two it defaults to the icon rail

    function drawer(open) { root.classList.toggle('is-drawer', open); }

    function setCollapsed(on) {
        root.classList.toggle('is-collapsed', on);
        store.set('aj-admin-nav', on ? 'collapsed' : 'open');
    }

    /* Single source of truth for the sidebar width, so the CSS never fights JS:
       drawer mode is never "collapsed"; narrow desktops default to the rail
       unless the admin explicitly opened it; wide screens follow the choice. */
    function applyNavState() {
        var w = window.innerWidth;
        var pref = store.get('aj-admin-nav');
        if (w <= DRAWER_MAX) {
            root.classList.remove('is-collapsed');
        } else {
            root.classList.remove('is-drawer');
            root.classList.toggle('is-collapsed', w <= RAIL_MAX ? pref !== 'open' : pref === 'collapsed');
        }
    }
    applyNavState();
    window.addEventListener('resize', applyNavState);

    $$('[data-nav-collapse]').forEach(function (b) {
        b.addEventListener('click', function () {
            // in drawer mode the same button closes the drawer
            if (window.innerWidth <= DRAWER_MAX) { drawer(false); return; }
            setCollapsed(!root.classList.contains('is-collapsed'));
        });
    });
    $$('[data-drawer-open]').forEach(function (b) { b.addEventListener('click', function () { drawer(true); }); });
    $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', function () { drawer(false); }); });
    // tapping a nav link inside the drawer should close it
    $$('.admin__nav a').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.innerWidth <= DRAWER_MAX) { drawer(false); }
        });
    });

    /* -------------------------------------------- sidebar scroll continuity */
    /* .admin__navwrap scrolls on its own (overflow-y:auto), and a browser only
       restores scroll for the document — never for a nested scroller. With 23
       items over five groups the lower ones sit below the fold, so clicking
       Reports or Payment Gateways loaded the next page with the sidebar back at
       the top and the item you had just picked scrolled out of sight.

       Remember the offset for the session and put it back before the first
       paint. This script tag is the last thing in <body> and runs synchronously,
       so the assignment lands before the sidebar is ever painted scrolled. */
    var navwrap = $('.admin__navwrap');
    if (navwrap) {
        var NAV_SCROLL_KEY = 'aj-admin-navscroll';
        var session = {
            get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
            set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
        };

        var saved = parseInt(session.get(NAV_SCROLL_KEY), 10);
        if (saved > 0) { navwrap.scrollTop = saved; }

        /* A restored offset is not enough on its own: a fresh tab, a bookmark or
           a link from outside has nothing stored, and the sidebar can also have
           been scrolled elsewhere since. If the current page's item is not on
           screen, bring it in — centred, so its group heading comes with it. */
        var current = $('.admin__nav a.active', navwrap);
        if (current) {
            // rect maths rather than offsetTop: independent of which ancestor
            // happens to be the offsetParent
            var relTop = current.getBoundingClientRect().top
                       - navwrap.getBoundingClientRect().top + navwrap.scrollTop;
            var relBottom = relTop + current.offsetHeight;
            if (relTop < navwrap.scrollTop
                || relBottom > navwrap.scrollTop + navwrap.clientHeight) {
                navwrap.scrollTop = Math.max(0, relTop - (navwrap.clientHeight - current.offsetHeight) / 2);
            }
        }

        /* Recorded on scroll rather than on unload: pagehide/beforeunload are
           unreliable on mobile, and scrolling the sidebar is the only way the
           offset can change. rAF-throttled — scroll fires far more often than
           there is anything new to store. */
        var navScrollPending = false;
        navwrap.addEventListener('scroll', function () {
            if (navScrollPending) { return; }
            navScrollPending = true;
            requestAnimationFrame(function () {
                navScrollPending = false;
                session.set(NAV_SCROLL_KEY, navwrap.scrollTop);
            });
        }, { passive: true });
    }

    /* --------------------------------------------------------------- toasts */
    var stack = $('#atoasts');

    var TOAST_META = {
        ok:   { title: 'Success' },
        err:  { title: 'Error' },
        warn: { title: 'Warning' },
        info: { title: 'Info' }
    };

    /* toastr's defaults, so the admin behaves like the reference demo */
    var TOAST_OPTS = {
        timeOut: 5000,          // auto-dismiss
        extendedTimeOut: 1000,  // after the pointer leaves a hovered toast
        hideDuration: 1000,     // matches the fade-out animation
        newestOnTop: true,
        closeButton: true,
        progressBar: true,
        tapToDismiss: true,
        // toastr's own default is false — leave it off so a genuine second
        // "saved" confirmation is never silently swallowed
        preventDuplicates: false
    };
    var lastToast = '';

    /**
     * toastr-style notification.
     * @param {string} message
     * @param {string} [type]  ok | err | warn | info   (success/error/warning map too)
     * @param {number} [ms]    override the 5s timeout; 0 keeps it until dismissed
     */
    function toast(message, type, ms) {
        if (!stack || !message) { return; }
        // accept toastr's own names as well as the admin's short ones
        var alias = { success: 'ok', error: 'err', warning: 'warn', info: 'info' };
        type = TOAST_META[type] ? type : (alias[type] || 'info');

        if (TOAST_OPTS.preventDuplicates && lastToast === type + '|' + message) { return; }
        lastToast = type + '|' + message;

        var timeOut = (ms === undefined || ms === null) ? TOAST_OPTS.timeOut : ms;

        var el = document.createElement('div');
        el.className = 'atoast atoast--' + type;
        el.setAttribute('role', type === 'err' ? 'alert' : 'status');

        var title = document.createElement('strong');
        title.className = 'atoast__title';
        title.textContent = TOAST_META[type].title;

        var txt = document.createElement('span');
        txt.className = 'atoast__msg';
        txt.textContent = message;

        el.appendChild(title);
        el.appendChild(txt);

        if (TOAST_OPTS.closeButton) {
            var x = document.createElement('button');
            x.className = 'atoast__x';
            x.type = 'button';
            x.setAttribute('aria-label', 'Close');
            x.innerHTML = '&times;';
            x.addEventListener('click', function (ev) { ev.stopPropagation(); close(); });
            el.appendChild(x);
        }

        var bar = null;
        if (TOAST_OPTS.progressBar && timeOut > 0) {
            bar = document.createElement('div');
            bar.className = 'atoast__bar';
            el.appendChild(bar);
        }

        if (TOAST_OPTS.newestOnTop && stack.firstChild) {
            stack.insertBefore(el, stack.firstChild);
        } else {
            stack.appendChild(el);
        }

        var timer = null, closing = false;

        function runBar(ms2) {
            if (!bar) { return; }
            bar.style.transition = 'none';
            bar.style.width = '100%';
            // force a reflow so the new transition actually animates
            void bar.offsetWidth;
            bar.style.transition = 'width ' + ms2 + 'ms linear';
            bar.style.width = '0%';
        }
        function start(ms2) {
            if (ms2 <= 0) { return; }
            clearTimeout(timer);
            timer = setTimeout(close, ms2);
            runBar(ms2);
        }
        function close() {
            if (closing) { return; }
            closing = true;
            clearTimeout(timer);
            el.classList.add('is-out');
            setTimeout(function () {
                if (el.parentNode) { el.parentNode.removeChild(el); }
                if (lastToast === type + '|' + message) { lastToast = ''; }
            }, TOAST_OPTS.hideDuration);
        }

        // hovering holds the toast open; leaving restarts a short timer (extendedTimeOut)
        el.addEventListener('mouseenter', function () {
            clearTimeout(timer);
            if (bar) { bar.style.transition = 'none'; bar.style.width = '100%'; }
        });
        el.addEventListener('mouseleave', function () {
            if (!closing && timeOut > 0) { start(TOAST_OPTS.extendedTimeOut); }
        });
        if (TOAST_OPTS.tapToDismiss) {
            el.addEventListener('click', close);
        }

        start(timeOut);
        return el;
    }
    window.adminToast = toast;

    // server flash messages
    var flashNode = $('#admin-flashes');
    if (flashNode) {
        try {
            JSON.parse(flashNode.textContent).forEach(function (f, i) {
                setTimeout(function () { toast(f.msg, f.type); }, i * 250);
            });
        } catch (e) {}
    }

    /* ------------------------------------------------------- command palette */
    var pal   = $('#cmdk');
    var input = $('#cmdk-input');
    var list  = $('#cmdk-list');

    if (pal && input && list) {
        var items = $$('.cmdk__item', list);
        var empty = $('.cmdk__empty', pal);
        var active = -1;

        function visible() {
            return items.filter(function (i) { return !i.hidden; });
        }
        function mark(i) {
            var vis = visible();
            items.forEach(function (n) { n.classList.remove('is-active'); });
            if (!vis.length) { active = -1; return; }
            active = (i + vis.length) % vis.length;
            vis[active].classList.add('is-active');
            vis[active].scrollIntoView({ block: 'nearest' });
        }
        function filter() {
            var q = input.value.trim().toLowerCase();
            items.forEach(function (n) {
                n.hidden = q !== '' && n.textContent.toLowerCase().indexOf(q) === -1;
            });
            // hide group headings that have no visible item left
            $$('.cmdk__group', list).forEach(function (g) {
                var any = false, n = g.nextElementSibling;
                while (n && !n.classList.contains('cmdk__group')) {
                    if (n.classList.contains('cmdk__item') && !n.hidden) { any = true; break; }
                    n = n.nextElementSibling;
                }
                g.hidden = !any;
            });
            if (empty) { empty.hidden = visible().length > 0; }
            mark(0);
        }
        function open() {
            pal.classList.add('is-open');
            input.value = '';
            filter();
            setTimeout(function () { input.focus(); }, 20);
        }
        function close() {
            pal.classList.remove('is-open');
        }
        function run(node) {
            if (!node) { return; }
            if (node.dataset.action === 'theme') { toggleTheme(); close(); return; }
            var href = node.dataset.href;
            if (!href) { return; }
            if (node.dataset.blank) { window.open(href, '_blank', 'noopener'); close(); return; }
            window.location.href = href;
        }

        $$('[data-cmdk-open]').forEach(function (b) { b.addEventListener('click', open); });
        $$('[data-cmdk-close]').forEach(function (b) { b.addEventListener('click', close); });
        items.forEach(function (n) { n.addEventListener('click', function () { run(n); }); });
        input.addEventListener('input', filter);

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown') { ev.preventDefault(); mark(active + 1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); mark(active - 1); }
            else if (ev.key === 'Enter') { ev.preventDefault(); run(visible()[active]); }
        });

        document.addEventListener('keydown', function (ev) {
            var k = (ev.key || '').toLowerCase();
            if (k === 'k' && (ev.metaKey || ev.ctrlKey)) {
                ev.preventDefault();
                pal.classList.contains('is-open') ? close() : open();
            } else if (ev.key === 'Escape') {
                if (pal.classList.contains('is-open')) { close(); }
                if (root.classList.contains('is-drawer')) { drawer(false); }
            }
        });
    }

    /* --------------------------------------------------------- validation */
    // Every admin form that actually collects input gets inline validation
    // (assets/js/validate.js). Tiny action forms — delete, duplicate, status,
    // bulk — hold only hidden inputs, so they're skipped. Opt out per form
    // with data-no-validate.
    $$('form').forEach(function (form) {
        if (form.hasAttribute('data-validate') || form.hasAttribute('data-no-validate')) { return; }
        if (form.querySelector('.field, .rowform')) {
            form.setAttribute('data-validate', '');
            form.noValidate = true;
        }
    });

    /* ------------------------------------------------- popover menus (⋮ etc) */
    // One open at a time. Row menus are positioned fixed so a scrolling table
    // can't clip them; topbar menus stay anchored to their button.
    var openPop = null;

    function closePop() {
        if (!openPop) { return; }
        openPop.hidden = true;
        openPop.classList.remove('is-floating');
        openPop.removeAttribute('style');
        openPop = null;
    }

    function placeFloating(pop, btn) {
        var r = btn.getBoundingClientRect();
        pop.hidden = false;
        pop.classList.add('is-floating');
        var h = pop.offsetHeight, w = pop.offsetWidth;
        var top = r.bottom + 6;
        if (top + h > window.innerHeight - 8) { top = Math.max(8, r.top - h - 6); }
        var left = Math.min(r.right - w, window.innerWidth - w - 10);
        pop.style.top = top + 'px';
        pop.style.left = Math.max(10, left) + 'px';
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('[data-menu-toggle]') : null;
        if (btn) {
            var pop = btn.parentNode.querySelector('.act__pop, .topmenu__pop');
            if (!pop) { return; }
            var wasOpen = pop === openPop;
            closePop();
            if (!wasOpen) {
                openPop = pop;
                if (pop.classList.contains('act__pop')) { placeFloating(pop, btn); }
                else { pop.hidden = false; }
            }
            ev.preventDefault();
            return;
        }
        if (openPop && !ev.target.closest('.act__pop, .topmenu__pop')) { closePop(); }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { closePop(); }
    });
    window.addEventListener('resize', closePop);
    window.addEventListener('scroll', function () {
        if (openPop && openPop.classList.contains('is-floating')) { closePop(); }
    }, true);

    /* ------------------------------------------------------- bulk selection */
    $$('[data-check-all]').forEach(function (master) {
        var formId = master.getAttribute('form');
        var boxes = $$('input.chk[name="ids[]"]' + (formId ? '[form="' + formId + '"]' : ''));
        if (!boxes.length) { return; }

        function sync() {
            var on = boxes.filter(function (b) { return b.checked; }).length;
            master.checked = on === boxes.length && on > 0;
            master.indeterminate = on > 0 && on < boxes.length;
            boxes.forEach(function (b) {
                var row = b.closest('tr');
                if (row) { row.classList.toggle('is-picked', b.checked); }
            });
            var form = formId ? document.getElementById(formId) : null;
            if (form) { form.dataset.picked = on; }
        }
        master.addEventListener('change', function () {
            boxes.forEach(function (b) { b.checked = master.checked; });
            sync();
        });
        boxes.forEach(function (b) { b.addEventListener('change', sync); });
        sync();
    });

    // don't let a bulk action fire with nothing selected, or without an action
    $$('[data-bulk-form]').forEach(function (form) {
        /* The row checkboxes are NOT inside this form. admin_bulk_bar() closes
           </form> before the table starts, and every box joins it with
           form="bulkForm". form.querySelectorAll() only walks descendants, so
           this counted 0 on every Apply — the guard fired every time and its
           preventDefault() meant the chosen action never ran.

           form.elements is the collection that includes controls associated by
           the form attribute, and it is exactly the set the browser submits,
           so the count here can no longer disagree with what the server gets. */
        function pickedCount() {
            return Array.prototype.filter.call(form.elements, function (el) {
                return el.type === 'checkbox' && el.name === 'ids[]' && el.checked;
            }).length;
        }
        form.addEventListener('submit', function (ev) {
            var picked = pickedCount();
            var action = form.querySelector('[name=bulk_action]');
            if (!action || !action.value) {
                ev.preventDefault();
                toast('Choose a bulk action first.', 'err');
            } else if (!picked) {
                ev.preventDefault();
                toast('Select at least one row first.', 'err');
            } else if (/delete/i.test(action.value) &&
                       !confirm('Delete ' + picked + ' selected item(s)? This cannot be undone.')) {
                ev.preventDefault();
            }
        });
    });

    /* ------------------------------------------ reason required before acting */
    /* Rejecting a review has to say why. The reason rides along in a hidden
       field so the action stays a plain form post; this only fills it in.
       data-reason-when limits the ask to one bulk action, so choosing Approve
       from the same menu is not interrogated.

       This used to call window.prompt(). Two things were wrong with that. A
       browser can refuse it — Chrome's "prevent this page from creating
       additional dialogs" sticks for the rest of the visit, after which every
       Reject failed with "a reason is required" and no box ever appeared, which
       is what "the reason option is not available" was. And a prompt cannot cap
       what is typed, so a moderator writing a couple of sentences overran the
       255-character column and the save came back as a database error page.
       An ordinary dialog in the page can do both. */
    var REASON_MAX = 255;

    /**
     * Ask for a reason. Calls back with the trimmed text, or with null when the
     * admin backs out. Falls back to prompt() only where <dialog> is missing.
     */
    function askReason(title, confirmLabel, done) {
        if (!window.HTMLDialogElement || typeof document.createElement('dialog').showModal !== 'function') {
            var typed = window.prompt(title, '');
            done(typed === null ? null : typed.trim());
            return;
        }

        var dlg = document.createElement('dialog');
        dlg.className = 'adlg';
        dlg.setAttribute('aria-labelledby', 'adlgTitle');
        dlg.innerHTML =
            '<div class="adlg__head">' +
            '<span class="adlg__ic" aria-hidden="true">' +
            '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
            ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M10.3 3.9 1.8 18.4A2 2 0 0 0 3.5 21.4h17A2 2 0 0 0 22.2 18.4L13.7 3.9a2 2 0 0 0-3.4 0z"/>' +
            '<path d="M12 9v4M12 17h.01"/></svg></span>' +
            '<div class="adlg__heading">' +
            '<h2 class="adlg__title" id="adlgTitle"></h2>' +
            '<p class="adlg__sub">Kept with the review for your own records — the customer is not shown it.</p>' +
            '</div></div>' +
            '<div class="adlg__body">' +
            '<label class="adlg__label" for="adlgReason">Reason</label>' +
            '<textarea id="adlgReason" class="adlg__input" rows="3" maxlength="' + REASON_MAX + '"' +
            ' placeholder="Give the reason in a sentence or two"></textarea>' +
            '<div class="adlg__meta"><span class="adlg__req">Required</span>' +
            '<span class="adlg__count"></span></div>' +
            '</div>' +
            '<div class="adlg__foot">' +
            '<button type="button" class="btn btn--ghost" data-adlg="cancel">Cancel</button>' +
            '<button type="button" class="btn btn--danger" data-adlg="ok"></button>' +
            '</div>';
        dlg.querySelector('.adlg__title').textContent = title;
        dlg.querySelector('[data-adlg=ok]').textContent = confirmLabel;

        var box = dlg.querySelector('.adlg__input');
        var count = dlg.querySelector('.adlg__count');
        function tally() { count.textContent = box.value.length + ' / ' + REASON_MAX; }
        box.addEventListener('input', tally);
        tally();

        /* Settle from whichever handler gets there first and never from the
           `close` event alone: closing a <dialog> queues that event rather than
           firing it inline, and there are builds where it does not arrive at
           all — the box would shut with the caller still waiting. The flag
           makes a late `close` a no-op. */
        var settled = false;
        function finish(v) {
            if (settled) { return; }
            settled = true;
            if (dlg.open) { try { dlg.close(); } catch (e) { /* already closing */ } }
            dlg.remove();
            done(v);
        }

        dlg.querySelector('[data-adlg=ok]').addEventListener('click', function () {
            if (box.value.trim() === '') { box.focus(); return; }   // nothing to confirm yet
            finish(box.value.trim());
        });
        dlg.querySelector('[data-adlg=cancel]').addEventListener('click', function () { finish(null); });
        // Enter confirms, Shift+Enter keeps its newline
        box.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); dlg.querySelector('[data-adlg=ok]').click(); }
        });
        // Esc, the backdrop, anything else that shuts it: treated as backing out
        dlg.addEventListener('cancel', function () { finish(null); });
        dlg.addEventListener('close', function () { finish(null); });

        document.body.appendChild(dlg);
        dlg.showModal();
        box.focus();
    }

    $$('[data-reason-prompt]').forEach(function (form) {
        var field = form.querySelector('input[name="reason"]');
        if (!field) { return; }
        var when = form.getAttribute('data-reason-when');

        form.addEventListener('submit', function (ev) {
            // an earlier guard (no rows picked, no action chosen) already stopped this
            if (ev.defaultPrevented) { return; }

            if (when) {
                var sel = form.querySelector('[name=bulk_action]');
                if (!sel || sel.value !== when) { return; }
            }
            if (field.value.trim() !== '') { return; }

            /* The dialog cannot answer within this handler, so this submit is
               always stopped and re-raised once a reason is in the field. */
            ev.preventDefault();
            askReason(form.getAttribute('data-reason-prompt'),
                      form.getAttribute('data-reason-confirm') || 'Reject', function (answer) {
                if (answer === null || answer === '') {
                    toast('A reason is required — nothing was changed.', 'err');
                    return;
                }
                field.value = answer.slice(0, REASON_MAX);
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
                else { form.submit(); }
            });
        });
    });

    /* ------------------------------------------------- selects that navigate */
    // used by the rows-per-page control in admin_pager()
    $$('select[data-nav-select]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.value) { window.location.href = sel.value; }
        });
    });

    /* -------------------------------------------------- surface interactions */
    // pointer-tracked spotlight on .spot cards
    $$('.spot').forEach(function (card) {
        card.addEventListener('pointermove', function (ev) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
            card.style.setProperty('--my', (ev.clientY - r.top) + 'px');
        });
    });

    // Validate first, then guard against double submits. This runs at form
    // level, before validate.js's document listener, so a blocked submit never
    // locks the button.
    //
    // Nothing is toasted when validation blocks a submit. validate.js writes a
    // message under every field it rejects and the first one is focused and
    // scrolled to, so a banner in the corner only repeated what the form was
    // already saying — and on a form submitted blank, where every field is
    // flagged at once, it was pure noise on top of a screen full of messages.
    // Failures the admin cannot see on the field itself still come back from
    // the server as a flash, which is a different path.
    $$('form').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (f.hasAttribute('data-validate') && window.formValidate) {
                var bad = window.formValidate.form(f);
                if (bad) {
                    ev.preventDefault();
                    try { bad.focus({ preventScroll: true }); } catch (e) { bad.focus(); }
                    bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    return;
                }
            }
            var b = f.querySelector('button[type=submit], button:not([type])');
            if (b && !f.hasAttribute('data-no-lock')) {
                setTimeout(function () { b.disabled = true; b.style.opacity = '.7'; }, 0);
                setTimeout(function () { b.disabled = false; b.style.opacity = ''; }, 6000);
            }
        });
    });

    /* ----------------------------------------------------------- number boxes
       A number field that arrives pre-filled puts the caret AFTER the digits
       already there, so typing 1 into a Sort Order showing 0 leaves "01".
       The saved value was always right — (int) drops the zero — but the box
       read wrong while it was being filled in, which is what gets reported.

       Select the contents on focus so the first keystroke replaces them, and
       strip a leading zero if one is typed anyway. An emptied box falls back
       to the value it was rendered with, so clearing it cannot post a blank. */
    $$('input[type="number"][data-numeric]').forEach(function (input) {
        var initial = input.value;

        input.addEventListener('focus', function () {
            // deferred: Chrome places the caret after focus and would undo select()
            setTimeout(function () {
                try { input.select(); } catch (e) {}
            }, 0);
        });

        input.addEventListener('input', function () {
            // 0 on its own stays 0; "0." is the start of a decimal, not a zero pad
            if (/^0\d/.test(input.value)) {
                input.value = input.value.replace(/^0+/, '');
            }
        });

        input.addEventListener('blur', function () {
            if (input.value.trim() === '') { input.value = initial; }
        });
    });

    /* ---------------------------------------------------------------- tooltips
       Promote every [title] in the admin to the styled [data-tip] bubble and
       drop the title, so the browser's slow native tooltip never shows on top.
       Runs over the whole document, so any page (and anything injected later)
       is covered without per-page markup.                                    */
    function tipify(root) {
        var nodes = (root || document).querySelectorAll('[title]:not([data-tip])');
        Array.prototype.forEach.call(nodes, function (el) {
            var text = (el.getAttribute('title') || '').trim();
            if (!text) { el.removeAttribute('title'); return; }

            /* iframes/embeds need their title for a11y and can't show a bubble */
            if (/^(IFRAME|SVG|TITLE|OPTION)$/.test(el.tagName)) { return; }

            el.setAttribute('data-tip', text);
            /* keep the accessible name that the title was providing */
            if (!el.hasAttribute('aria-label') && !el.getAttribute('aria-labelledby') && !el.textContent.trim()) {
                el.setAttribute('aria-label', text);
            }
            if (text.length > 34 && !el.hasAttribute('data-tip-wrap')) {
                el.setAttribute('data-tip-wrap', '');
            }
            el.removeAttribute('title');
        });
    }

    tipify(document);

    /* flip the bubble to the other side when it would run off-screen */
    document.addEventListener('mouseover', function (ev) {
        var el = ev.target.closest ? ev.target.closest('[data-tip]') : null;
        if (!el || el.hasAttribute('data-tip-pos')) { return; }
        var r = el.getBoundingClientRect();
        if (r.top < 60) { el.setAttribute('data-tip-pos', 'bottom'); }
    });

    /* pick up rows/menus rendered after load */
    if (window.MutationObserver) {
        new MutationObserver(function (muts) {
            muts.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes, function (n) {
                    if (n.nodeType === 1) { tipify(n); }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.adminTipify = tipify;
})();
