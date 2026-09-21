/**
 * Storefront AJAX behaviour: cart, wishlist, mini-cart, coupon, search, filters,
 * reviews. Progressive enhancement - every action still works without JS via
 * cart-actions.php form/link fallbacks.
 */
(function () {
    'use strict';

    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var BASE = (document.querySelector('meta[name="base-url"]') || {}).content || '';

    function url(path) { return (BASE ? BASE.replace(/\/$/, '') + '/' : '') + path; }

    function post(path, data) {
        var body = new URLSearchParams();
        body.append('csrf_token', CSRF);
        Object.keys(data || {}).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) body.append(k, data[k]);
        });
        return fetch(url(path), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    /* ---------- toast ---------- */
    var toastWrap;
    function toast(message, ok) {
        if (!toastWrap) {
            toastWrap = document.createElement('div');
            toastWrap.style.cssText = 'position:fixed;z-index:9999;right:20px;bottom:20px;display:flex;flex-direction:column;gap:10px;max-width:320px';
            document.body.appendChild(toastWrap);
        }
        var t = document.createElement('div');
        t.textContent = message;
        t.style.cssText = 'padding:12px 18px;border-radius:6px;color:#fff;font-size:14px;box-shadow:0 6px 20px rgba(0,0,0,.18);background:' + (ok === false ? '#c0392b' : '#1e7e4f');
        toastWrap.appendChild(t);
        setTimeout(function () {
            t.style.transition = 'opacity .4s'; t.style.opacity = '0';
            setTimeout(function () { t.remove(); }, 400);
        }, 3200);
    }

    /* ---------- counters + mini-cart ---------- */
    /* Badges show the plain count and disappear at zero — they used to be
       zero-padded to two digits, which rendered an empty cart as "00". */
    function setBadge(el, count) {
        var n = parseInt(count, 10) || 0;
        el.textContent = n;
        el.hidden = n === 0;
    }

    function refreshCounts(data) {
        if (!data) return;
        if (data.cart_count !== undefined) {
            document.querySelectorAll('.items__count.style2, .offcanvas__stikcy--toolbar__list .items__count:not(.wishlist)').forEach(function (el) {
                setBadge(el, data.cart_count);
            });
        }
        if (data.wishlist_count !== undefined) {
            document.querySelectorAll('.items__count.wishlist, .offcanvas__stikcy--toolbar__list a[href*="wishlist"] .items__count').forEach(function (el) {
                setBadge(el, data.wishlist_count);
            });
        }
        if (data.items) renderMiniCart(data);
    }

    function renderMiniCart(data) {
        var wrap = document.querySelector('.offCanvas__minicart .minicart__product');
        if (!wrap) return;
        if (!data.items.length) {
            wrap.innerHTML = '<p class="text-center py-4">Your cart is empty</p>';
        } else {
            wrap.innerHTML = data.items.map(function (i) {
                return '<div class="minicart__product--items d-flex">' +
                    '<div class="minicart__thumb"><a href="' + i.url + '"><img src="' + i.image + '" alt=""></a></div>' +
                    '<div class="minicart__text">' +
                    '<h3 class="minicart__subtitle h4"><a href="' + i.url + '">' + i.name + '</a></h3>' +
                    '<span class="color__variant"><b>Category:</b> ' + (i.category || '') + '</span>' +
                    '<div class="minicart__price"><span class="current__price">' + i.price_html + '</span></div>' +
                    '<div class="minicart__text--footer d-flex align-items-center"><span>Qty: ' + i.qty + '</span>' +
                    '<button type="button" class="minicart__product--remove" data-mini-remove="' + i.row_id + '" style="margin-left:10px">Remove</button></div>' +
                    '</div></div>';
            }).join('');
        }
        var amt = document.querySelector('.offCanvas__minicart .minicart__amount');
        if (amt && data.totals) {
            amt.querySelectorAll('.minicart__amount_list b').forEach(function (b) {
                b.textContent = data.totals.subtotal_html;
            });
        }
    }

    /**
     * Clear a star-rating widget after a successful submit.
     *
     * form.reset() is not enough. For `input type=hidden` the `value` IDL
     * attribute is in "default" mode, so `input.value = 5` writes the *content*
     * attribute — and reset() restores a control to its content attribute. The
     * old rating therefore survived the reset and was posted again on the next
     * submit, which is how a second feedback entry picked up the first one's
     * stars. Zero it explicitly.
     */
    function resetStarRating(form) {
        form.querySelectorAll('input[name="rating"]').forEach(function (input) {
            input.value = '0';
            input.setAttribute('value', '0');
        });
        form.querySelectorAll('.js-star').forEach(function (s) { s.classList.remove('active'); });
    }

    /**
     * One in-flight submit per form.
     *
     * Disabling the submit button was the only guard, and it does not cover
     * implicit submission (Enter in a text field) or a click already queued.
     * The star rating is only cleared when the response lands, so anything
     * submitted before that still carried the previous value — a second
     * feedback entry arrived stamped with the first one's stars.
     */
    function formBusy(form) { return form.getAttribute('data-submitting') === '1'; }
    function setFormBusy(form, busy) {
        if (busy) { form.setAttribute('data-submitting', '1'); }
        else { form.removeAttribute('data-submitting'); }
    }

    /* ---------- add to cart ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.classList.contains('js-cart-form')) {
            e.preventDefault();
            var pid = form.querySelector('[name="product_id"]').value;
            var variant = form.querySelector('[name="variant_id"]');
            var qty = form.querySelector('[name="qty"]');
            var btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
            post('ajax/cart.php', {
                op: 'add', product_id: pid,
                variant_id: variant ? variant.value : '',
                qty: qty ? qty.value : 1
            }).then(function (res) {
                if (btn) btn.disabled = false;
                toast(res.message, res.success);
                if (res.success) refreshCounts(res.data);
            }).catch(function () { if (btn) btn.disabled = false; toast('Something went wrong.', false); });
        }

        if (form.classList.contains('js-review-form')) {
            e.preventDefault();
            /* same star widget, same race as the feedback form */
            if (formBusy(form)) { return; }
            setFormBusy(form, true);
            var fd = new FormData(form);
            var obj = {}; fd.forEach(function (v, k) { obj[k] = v; });
            var rvBtn = form.querySelector('button[type="submit"]');
            if (rvBtn) rvBtn.disabled = true;
            post('ajax/review.php', obj).then(function (res) {
                toast(res.message, res.success);
                if (!res.success) { setFormBusy(form, false); if (rvBtn) rvBtn.disabled = false; return; }
                form.reset();
                resetStarRating(form);
                /* Reload so the page re-renders from the server: otherwise the
                   stars stay lit and the write-a-review box stays open, which
                   reads as though nothing was submitted. The short delay lets
                   the toast be read first. */
                setTimeout(function () {
                    window.location.hash = 'reviews';
                    window.location.reload();
                }, 1400);
            }).catch(function () {
                setFormBusy(form, false);
                if (rvBtn) rvBtn.disabled = false;
                toast('Something went wrong.', false);
            });
        }

        if (form.classList.contains('js-coupon-form')) {
            e.preventDefault();
            var code = form.querySelector('[name="code"]').value;
            post('ajax/coupon.php', { op: 'apply', code: code }).then(function (res) {
                toast(res.message, res.success);
                if (res.success && window.location.pathname.match(/(cart|checkout)\.php/)) window.location.reload();
            });
        }

        /* generic AJAX form: posts to [action], toasts the message, resets on success */
        if (form.classList.contains('js-ajax-form')) {
            e.preventDefault();
            if (formBusy(form)) { return; }
            setFormBusy(form, true);
            var action = form.getAttribute('action');
            var fd2 = new FormData(form);
            var obj2 = {}; fd2.forEach(function (v, k) { obj2[k] = v; });
            var sbtn = form.querySelector('button[type="submit"]');
            if (sbtn) sbtn.disabled = true;
            var rel = action.replace(/^.*\/ajax\//, 'ajax/');
            post(rel, obj2).then(function (res) {
                setFormBusy(form, false);
                if (sbtn) sbtn.disabled = false;
                toast(res.message, res.success);
                if (res.success) {
                    form.reset();
                    resetStarRating(form);
                    /* every page puts this banner immediately BEFORE the
                       form, so a form-scoped lookup never found it and the
                       only confirmation was a toast that fades after 3s —
                       which is exactly why a tester submits a second time. */
                    var ok = form.querySelector('.js-form-success')
                          || (form.parentElement && form.parentElement.querySelector('.js-form-success'));
                    if (ok) { ok.textContent = res.message; ok.hidden = false; }
                }
            }).catch(function () {
                setFormBusy(form, false);
                if (sbtn) sbtn.disabled = false;
                toast('Something went wrong.', false);
            });
        }
    });

    /* ---------- wishlist toggle ---------- */
    document.addEventListener('click', function (e) {
        var wl = e.target.closest('.js-wishlist-toggle');
        if (wl) {
            e.preventDefault();
            var pid = wl.getAttribute('data-product-id');
            post('ajax/wishlist.php', { op: 'toggle', product_id: pid }).then(function (res) {
                if (!res.success) { toast(res.message, false); return; }
                toast(res.message, true);
                var path = wl.querySelector('path');
                if (path) path.setAttribute('fill', res.data.in_wishlist ? 'currentColor' : 'none');
                wl.classList.toggle('is-active', res.data.in_wishlist);
                refreshCounts(res.data);
            });
        }

        var rm = e.target.closest('[data-mini-remove]');
        if (rm) {
            e.preventDefault();
            post('ajax/cart.php', { op: 'remove', row_id: rm.getAttribute('data-mini-remove') }).then(function (res) {
                if (!res.success) { toast(res.message, false); return; }
                refreshCounts(res.data);
                /* cart.php and checkout.php render their totals server-side, so
                   refreshing the mini-cart alone would leave the order summary
                   showing a line that is no longer in the basket. */
                if (window.location.pathname.match(/(cart|checkout)\.php/)) {
                    window.location.reload();
                }
            }).catch(function () {
                // JS path failed — let the plain form post carry it through
                if (rm.form) { rm.form.submit(); }
            });
        }

        /* Cart page Remove. Goes through AJAX with the token read from the live
           <meta> tag, so a page that has been open a while still removes the
           line instead of bouncing off the CSRF check. The button is a real
           submit inside the cart form, so without JS it still posts normally. */
        var cartRm = e.target.closest('.js-cart-remove');
        if (cartRm) {
            e.preventDefault();
            var rowId = cartRm.getAttribute('data-row-id');
            cartRm.disabled = true;
            post('ajax/cart.php', { op: 'remove', row_id: rowId }).then(function (res) {
                if (!res.success) {
                    cartRm.disabled = false;
                    toast(res.message, false);
                    return;
                }
                refreshCounts(res.data);
                window.location.reload();
            }).catch(function () {
                /* Network or token trouble — fall back to the plain form post.
                   form.submit() does not carry a submit button's name/value, so
                   the row has to be named explicitly. */
                cartRm.disabled = false;
                var form = cartRm.form;
                if (!form) { return; }
                var hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = 'remove_row';
                hid.value = rowId;
                form.appendChild(hid);
                form.submit();
            });
        }

        var star = e.target.closest('.js-star');
        if (star) {
            var group = star.parentNode;
            var val = parseInt(star.getAttribute('data-value'), 10) || 0;
            /* The hidden rating input is a sibling of the star group on the
               feedback page but sits outside it on product-details, so look in
               the form first and only then fall back to the group. */
            var scope = star.closest('form') || group;
            var input = scope.querySelector('input[name="rating"]') || group.querySelector('input[name="rating"]');
            if (input) { input.value = val; }
            group.querySelectorAll('.js-star').forEach(function (s) {
                s.classList.toggle('active', (parseInt(s.getAttribute('data-value'), 10) || 0) <= val);
            });
        }
    });

    /* ---------- cart page live qty ---------- */
    document.querySelectorAll('.js-cart-qty').forEach(function (input) {
        input.addEventListener('change', function () {
            post('ajax/cart.php', { op: 'update', row_id: input.getAttribute('data-row-id'), qty: input.value })
                .then(function (res) {
                    toast(res.message, res.success);
                    if (res.success) window.location.reload();
                });
        });
    });

    /* ---------- predictive search ---------- */
    var searchInput = document.querySelector('.predictive__search--input');
    if (searchInput) {
        var box = document.createElement('div');
        box.className = 'predictive__result';
        box.style.cssText = 'margin-top:14px;text-align:left;max-height:340px;overflow:auto';
        searchInput.closest('.predictive__search--form').appendChild(box);
        var timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            var q = searchInput.value.trim();
            if (q.length < 2) { box.innerHTML = ''; return; }
            timer = setTimeout(function () {
                fetch(url('ajax/search.php?q=' + encodeURIComponent(q)), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res.data.results.length) { box.innerHTML = '<p style="padding:10px">No products found.</p>'; return; }
                        box.innerHTML = res.data.results.map(function (p) {
                            return '<a href="' + p.url + '" style="display:flex;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #eee;color:inherit">' +
                                '<img src="' + p.image + '" style="width:46px;height:46px;object-fit:cover;border-radius:4px">' +
                                '<span><strong>' + p.name + '</strong><br><small>' + p.category + ' &middot; ' + p.price_html + '</small></span></a>';
                        }).join('');
                    });
            }, 250);
        });
    }

    /* ---------- shop filters ---------- */
    var shop = document.querySelector('[data-shop-listing]');
    if (shop) {
        var grid = shop.querySelector('[data-shop-grid]');
        var pager = shop.querySelector('[data-shop-pagination]');
        var count = shop.querySelector('[data-shop-count]');
        var forceCat = shop.getAttribute('data-force-category') || '';
        var forceMain = shop.getAttribute('data-force-main-category') || '';

        function loadShop(params) {
            var qs = new URLSearchParams(params);
            if (forceCat) qs.set('force_category', forceCat);
            if (forceMain) qs.set('force_main_category', forceMain);
            grid.style.opacity = '.45';
            fetch(url('ajax/filter-products.php?' + qs.toString()), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    grid.style.opacity = '';
                    grid.innerHTML = res.data.grid_html;
                    if (pager) pager.innerHTML = res.data.pagination_html;
                    if (count) {
                        count.textContent = res.data.total
                            ? 'Showing ' + res.data.from + '–' + res.data.to + ' of ' + res.data.total + ' products'
                            : 'No products found';
                    }
                    var clean = {}; qs.forEach(function (v, k) { if (k.indexOf('force_') !== 0) clean[k] = v; });
                    history.replaceState(null, '', location.pathname + (Object.keys(clean).length ? '?' + new URLSearchParams(clean).toString() : ''));
                });
        }

        function currentParams() {
            var f = shop.querySelector('[data-shop-filters]');
            var params = {};
            if (f) new FormData(f).forEach(function (v, k) { if (v !== '') params[k] = v; });
            return params;
        }

        var filtersForm = shop.querySelector('[data-shop-filters]');
        if (filtersForm) {
            filtersForm.addEventListener('submit', function (e) { e.preventDefault(); loadShop(currentParams()); shop.classList.remove('filters-open'); });
            filtersForm.addEventListener('change', function (e) {
                if (e.target.matches('select, input[type="checkbox"], input[type="radio"]')) loadShop(currentParams());
            });
        }
        if (pager) {
            pager.addEventListener('click', function (e) {
                var a = e.target.closest('a');
                if (!a) return;
                e.preventDefault();
                var p = new URLSearchParams(a.search);
                var params = currentParams();
                params.page = p.get('page') || 1;
                loadShop(params);
                window.scrollTo({ top: shop.offsetTop - 100, behavior: 'smooth' });
            });
        }

        /* sort <select> lives in the toolbar, outside the filter form */
        var sortProxy = document.querySelector('[data-sort-proxy]');
        if (sortProxy && filtersForm) {
            var sortHidden = filtersForm.querySelector('input[name="sort"]');
            if (!sortHidden) {
                sortHidden = document.createElement('input');
                sortHidden.type = 'hidden'; sortHidden.name = 'sort';
                filtersForm.appendChild(sortHidden);
            }
            sortHidden.value = sortProxy.value;
            sortProxy.addEventListener('change', function () {
                sortHidden.value = sortProxy.value;
                loadShop(currentParams());
            });
        }

        /* collapsible filter groups (Category, and each main category's children) */
        shop.querySelectorAll('[data-collapse-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var box = btn.closest('[data-filter-collapse]');
                if (box) box.classList.toggle('is-open');
            });
        });

        /* dual-handle price slider -> writes the hidden min_price/max_price fields */
        var range = shop.querySelector('[data-price-range]');
        if (range && filtersForm) {
            var lo = range.querySelector('[data-range-lo]'),
                hi = range.querySelector('[data-range-hi]'),
                fill = range.querySelector('[data-range-fill]'),
                outLo = range.querySelector('[data-range-out-lo]'),
                outHi = range.querySelector('[data-range-out-hi]'),
                fieldLo = range.querySelector('[data-range-field-lo]'),
                fieldHi = range.querySelector('[data-range-field-hi]'),
                bMin = Number(range.getAttribute('data-min')),
                bMax = Number(range.getAttribute('data-max'));

            function money(n) { return '₹' + Number(n).toLocaleString('en-IN'); }

            function paint() {
                var a = Number(lo.value), b = Number(hi.value);
                var span = bMax - bMin || 1;
                fill.style.left = ((a - bMin) / span * 100) + '%';
                fill.style.right = ((bMax - b) / span * 100) + '%';
                outLo.textContent = money(a);
                outHi.textContent = money(b);
            }

            /* keep the handles from crossing over each other */
            function clamp(moved) {
                var a = Number(lo.value), b = Number(hi.value);
                if (a > b) { if (moved === 'lo') lo.value = b; else hi.value = a; }
            }

            function commit() {
                fieldLo.value = Number(lo.value) > bMin ? lo.value : '';
                fieldHi.value = Number(hi.value) < bMax ? hi.value : '';
                range.querySelectorAll('[data-price-preset]').forEach(function (c) { c.classList.remove('is-active'); });
                filtersForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }

            [lo, hi].forEach(function (input, i) {
                input.addEventListener('input', function () { clamp(i === 0 ? 'lo' : 'hi'); paint(); });
                input.addEventListener('change', commit);
            });
            paint();

            shop.querySelectorAll('[data-price-preset]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var on = chip.classList.contains('is-active');
                    shop.querySelectorAll('[data-price-preset]').forEach(function (c) { c.classList.remove('is-active'); });
                    var pMin = chip.getAttribute('data-preset-min');
                    var pMax = chip.getAttribute('data-preset-max');
                    if (on) { pMin = ''; pMax = ''; } else { chip.classList.add('is-active'); }
                    fieldLo.value = pMin;
                    fieldHi.value = pMax;
                    lo.value = pMin === '' ? bMin : Math.max(bMin, Math.min(bMax, Number(pMin)));
                    hi.value = pMax === '' ? bMax : Math.max(bMin, Math.min(bMax, Number(pMax)));
                    paint();
                    filtersForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                });
            });
        }

        /* grid / list layout toggle */
        var shopMain = shop.querySelector('.shop__main');
        shop.querySelectorAll('[data-shop-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                shop.querySelectorAll('[data-shop-view]').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                if (shopMain) shopMain.classList.toggle('is-list', btn.getAttribute('data-shop-view') === 'list');
            });
        });

        /* mobile: slide the filter panel in/out */
        var filterToggle = document.querySelector('[data-filter-toggle]');
        if (filterToggle) {
            filterToggle.addEventListener('click', function () {
                shop.classList.toggle('filters-open');
            });
            shop.addEventListener('click', function (e) {
                if (e.target === shop.querySelector('.shop__filters--backdrop')) shop.classList.remove('filters-open');
            });
        }
    }

    /* ---------- offcanvas menu accordion ---------- */
    document.querySelectorAll('.offcanvas__menu--refined .offcanvas__expand').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var li = btn.closest('.offcanvas__has--children');
            if (li) li.classList.toggle('is-open');
        });
    });

    /* ---------- quick contact widget ---------- */
    var qc = document.querySelector('.quick__contact');
    if (qc) {
        var qcToggle = qc.querySelector('.quick__contact--toggle');
        qcToggle.addEventListener('click', function () { qc.classList.toggle('is-open'); });
        document.addEventListener('click', function (e) {
            if (!qc.contains(e.target)) qc.classList.remove('is-open');
        });
    }

    window.SHOP = { post: post, toast: toast, refreshCounts: refreshCounts };
})();
