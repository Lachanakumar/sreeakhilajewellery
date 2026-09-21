/**
 * Lightweight dependency-free date / datetime picker.
 * Enhances:  <input type="text" data-datepicker readonly ...>            -> YYYY-MM-DD
 *            <input type="text" data-datepicker="datetime" readonly ...> -> YYYY-MM-DDTHH:MM
 * Optional:  data-min="YYYY-MM-DD"  data-max="YYYY-MM-DD"
 * Ranges:    data-min-input="<name>" / data-max-input="<name>" tie this field's
 *            floor / ceiling to another picker's value, so a From/To pair can
 *            never be picked out of order.
 *
 * The visible input is a label only; a hidden sibling carries the submitted
 * value. `input.dpValue` exposes that hidden input so validate.js can apply
 * `required` to the real value rather than to the label.
 */
(function () {
    'use strict';

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    var DOW = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parseYmd(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
        if (!m) return null;
        return new Date(+m[1], +m[2] - 1, +m[3]);
    }
    function fmtDisplay(iso, withTime) {
        var d = parseYmd(iso);
        if (!d) return '';
        var out = pad(d.getDate()) + ' ' + MONTHS[d.getMonth()].slice(0, 3) + ' ' + d.getFullYear();
        if (withTime) {
            var tm = /T(\d{2}):(\d{2})/.exec(iso);
            if (tm) {
                var h = +tm[1], ap = h < 12 ? 'AM' : 'PM', h12 = h % 12 || 12;
                out += ' · ' + h12 + ':' + tm[2] + ' ' + ap;
            }
        }
        return out;
    }

    function build(input) {
        var withTime = input.getAttribute('data-datepicker') === 'datetime';
        // read on each use, not captured once: a linked picker re-points these
        function minD() { return parseYmd(input.getAttribute('data-min')); }
        function maxD() { return parseYmd(input.getAttribute('data-max')); }

        // shadow input holds the real submitted value; visible input shows the label
        var real = document.createElement('input');
        real.type = 'hidden';
        real.name = input.name;
        real.value = input.value || '';
        input.removeAttribute('name');
        input.readOnly = true;
        input.autocomplete = 'off';
        input.classList.add('dp__input');
        input.parentNode.insertBefore(real, input.nextSibling);

        // let the shared validator read the real value off the label input
        input.dpValue = real;

        var wrap = document.createElement('div');
        wrap.className = 'dp';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var pop = document.createElement('div');
        pop.className = 'dp__pop';
        pop.hidden = true;
        wrap.appendChild(pop);

        var view = real.value ? (parseYmd(real.value) || new Date()) : new Date();
        view = new Date(view.getFullYear(), view.getMonth(), 1);

        /* Year range for the dropdown. Honour data-min / data-max when they are
           set (a date of birth is capped at today, an appointment starts today)
           and otherwise offer a generous window either side. */
        function yearBounds() {
            var thisYear = new Date().getFullYear();
            var lo = minD() ? minD().getFullYear() : thisYear - 100;
            var hi = maxD() ? maxD().getFullYear() : thisYear + 10;
            // never let the currently shown year fall outside the list
            lo = Math.min(lo, view.getFullYear());
            hi = Math.max(hi, view.getFullYear());
            return [lo, hi];
        }

        function syncLabel() {
            input.value = real.value ? fmtDisplay(real.value, withTime) : '';
            input.classList.toggle('is-empty', !real.value);
        }

        function disabled(d) {
            var lo = minD(), hi = maxD();
            if (lo && d < lo) return true;
            if (hi && d > hi) return true;
            return false;
        }

        /** Is any day of this month selectable? Used to grey out dead months. */
        function monthOutOfRange(y, mo) {
            var first = new Date(y, mo, 1);
            var last = new Date(y, mo + 1, 0);
            var lo = minD(), hi = maxD();
            if (hi && first > hi) return true;
            if (lo && last < lo) return true;
            return false;
        }

        function monthOptions(sel) {
            var y = view.getFullYear(), o = '';
            for (var m = 0; m < 12; m++) {
                o += '<option value="' + m + '"' + (m === sel ? ' selected' : '')
                   + (monthOutOfRange(y, m) ? ' disabled' : '') + '>' + MONTHS[m] + '</option>';
            }
            return o;
        }

        function yearOptions(sel) {
            var b = yearBounds(), o = '';
            // newest first, so a date of birth does not open a century away
            for (var y = b[1]; y >= b[0]; y--) {
                o += '<option value="' + y + '"' + (y === sel ? ' selected' : '') + '>' + y + '</option>';
            }
            return o;
        }

        function render() {
            var y = view.getFullYear(), mo = view.getMonth();
            var first = new Date(y, mo, 1);
            var startDow = first.getDay();
            var daysIn = new Date(y, mo + 1, 0).getDate();
            var today = ymd(new Date());
            var selYmd = real.value ? real.value.slice(0, 10) : '';

            /* The title is a pair of <select>s rather than plain text, so that
               clicking the month or the year lists every month and every year
               in range instead of forcing one arrow click at a time. */
            var html = '<div class="dp__head">'
                + '<button type="button" class="dp__nav" data-nav="-1" aria-label="Previous month">&#8249;</button>'
                + '<span class="dp__title">'
                +   '<select class="dp__sel dp__sel--month" aria-label="Month">' + monthOptions(mo) + '</select>'
                +   '<select class="dp__sel dp__sel--year" aria-label="Year">' + yearOptions(y) + '</select>'
                + '</span>'
                + '<button type="button" class="dp__nav" data-nav="1" aria-label="Next month">&#8250;</button>'
                + '</div><div class="dp__grid">';
            DOW.forEach(function (d) { html += '<span class="dp__dow">' + d + '</span>'; });
            for (var i = 0; i < startDow; i++) html += '<span></span>';
            for (var day = 1; day <= daysIn; day++) {
                var ds = y + '-' + pad(mo + 1) + '-' + pad(day);
                var cls = 'dp__day';
                if (ds === selYmd) cls += ' is-selected';
                if (ds === today) cls += ' is-today';
                var dis = disabled(new Date(y, mo, day)) ? ' disabled' : '';
                html += '<button type="button" class="' + cls + '" data-day="' + ds + '"' + dis + '>' + day + '</button>';
            }
            html += '</div>';

            if (withTime) {
                var t = /T(\d{2}):(\d{2})/.exec(real.value || '');
                var hh = t ? t[1] : '10', mm = t ? t[2] : '00';
                html += '<div class="dp__time"><span>Time</span>'
                    + '<select class="dp__hh">' + hours(hh) + '</select><b>:</b>'
                    + '<select class="dp__mm">' + mins(mm) + '</select></div>';
            }
            html += '<div class="dp__foot">'
                + '<button type="button" class="dp__clear">Clear</button>'
                + '<button type="button" class="dp__today">Today</button>'
                + (withTime ? '<button type="button" class="dp__done">Done</button>' : '')
                + '</div>';
            pop.innerHTML = html;
        }
        function hours(sel) { var o = ''; for (var h = 0; h < 24; h++) { var v = pad(h); o += '<option' + (v === sel ? ' selected' : '') + '>' + v + '</option>'; } return o; }
        function mins(sel) { var o = ''; [0, 15, 30, 45].forEach(function (m) { var v = pad(m); o += '<option' + (v === sel ? ' selected' : '') + '>' + v + '</option>'; }); return o; }

        function open() { render(); pop.hidden = false; wrap.classList.add('is-open'); }
        function close() { pop.hidden = true; wrap.classList.remove('is-open'); }

        function setValue(dayStr) {
            if (withTime) {
                var hh = pop.querySelector('.dp__hh'), mm = pop.querySelector('.dp__mm');
                real.value = dayStr + 'T' + (hh ? hh.value : '10') + ':' + (mm ? mm.value : '00');
            } else {
                real.value = dayStr;
            }
            syncLabel();
            // clears a "required" message the moment a date is picked
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // On a real mouse click, `focus` fires before `click`; if we let click
        // blindly toggle, it immediately closes what focus just opened. Only
        // toggle when the input was already focused before this click.
        var wasFocused = false;
        input.addEventListener('mousedown', function () { wasFocused = document.activeElement === input; });
        input.addEventListener('click', function () { if (wasFocused) { pop.hidden ? open() : close(); } });
        input.addEventListener('focus', open);

        pop.addEventListener('click', function (e) {
            /* render() replaces pop.innerHTML, which detaches e.target. The
               outside-click listener further down then saw a node that is no
               longer inside .dp and closed the picker — which is why a single
               tap on the next-month arrow shut the whole thing. Claiming the
               event here keeps the popup open. */
            e.stopPropagation();

            var nav = e.target.closest('[data-nav]');
            if (nav) { view.setMonth(view.getMonth() + (+nav.getAttribute('data-nav'))); render(); return; }
            var day = e.target.closest('[data-day]');
            if (day && !day.disabled) {
                setValue(day.getAttribute('data-day'));
                view = parseYmd(day.getAttribute('data-day'));
                view = new Date(view.getFullYear(), view.getMonth(), 1);
                if (!withTime) { close(); } else { render(); }
                return;
            }
            if (e.target.closest('.dp__clear')) {
                real.value = '';
                syncLabel();
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close();
                return;
            }
            if (e.target.closest('.dp__today')) {
                /* "Today" used to write the date straight through, ignoring the
                   bounds every day button honours — enough to put a To before
                   its From. Land on the nearest date that is actually in range. */
                var t = new Date(), lo = minD(), hi = maxD();
                if (lo && t < lo) { t = lo; }
                if (hi && t > hi) { t = hi; }
                setValue(ymd(t));
                view = new Date(t.getFullYear(), t.getMonth(), 1);
                render();
                if (!withTime) close();
                return;
            }
            if (e.target.closest('.dp__done')) { close(); return; }
        });
        // the month/year selects live inside the popup too, so their mousedown
        // must not reach the outside-click handler either
        pop.addEventListener('mousedown', function (e) { e.stopPropagation(); });

        pop.addEventListener('change', function (e) {
            e.stopPropagation();
            if (e.target.matches('.dp__sel--month')) {
                view = new Date(view.getFullYear(), +e.target.value, 1);
                render();
                return;
            }
            if (e.target.matches('.dp__sel--year')) {
                view = new Date(+e.target.value, view.getMonth(), 1);
                render();
                return;
            }
            if (e.target.matches('.dp__hh, .dp__mm') && real.value) {
                setValue(real.value.slice(0, 10));
            }
        });

        document.addEventListener('click', function (e) {
            // a node the re-render just removed is not an "outside" click
            if (!e.target || e.target.isConnected === false) return;
            if (!wrap.contains(e.target)) close();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

        // hooks linkRange() calls on the partner field
        input.dpSet = setValue;
        input.dpRefresh = function () { if (!pop.hidden) render(); };

        syncLabel();
    }

    /**
     * Tie one picker's floor / ceiling to another's value. The partner is named
     * by its original `name`, captured before build() moves it to the shadow
     * input. Picking a From past the current To drags the To along rather than
     * leaving an impossible range on screen; that write fires `change` on the
     * To, which re-runs the From's own link, but the two dates are equal by
     * then so neither clamp fires again.
     */
    function linkRange(input, byName) {
        [['data-min-input', 'data-min'], ['data-max-input', 'data-max']].forEach(function (pair) {
            var partner = byName[input.getAttribute(pair[0])];
            if (!partner) return;
            var isMin = pair[1] === 'data-min';

            function apply() {
                var v = (partner.dpValue ? partner.dpValue.value : '').slice(0, 10);
                v ? input.setAttribute(pair[1], v) : input.removeAttribute(pair[1]);

                var mine = (input.dpValue ? input.dpValue.value : '').slice(0, 10);
                if (v && mine && (isMin ? mine < v : mine > v)) { input.dpSet(v); }
                input.dpRefresh();
            }
            partner.addEventListener('change', apply);
            apply();
        });
    }

    var pickers = [].slice.call(document.querySelectorAll('input[data-datepicker]'));
    var byName = {};
    pickers.forEach(function (i) { if (i.name) byName[i.name] = i; });
    pickers.forEach(build);
    pickers.forEach(function (i) { linkRange(i, byName); });
})();
