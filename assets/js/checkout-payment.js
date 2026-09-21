/* =====================================================================
 * Checkout payment controller.
 *
 * Nothing here ever touches a card number, CVV or UPI PIN — those are
 * collected inside the gateway's own hosted component (Razorpay Checkout,
 * Stripe Payment Element, PayPal Buttons, the PhonePe page).
 *
 * A payment is NEVER treated as successful because the SDK said so; the
 * browser result is only a trigger to call ajax/payment/verify.php, which
 * re-checks with the gateway server-side.
 * ===================================================================== */
(function () {
    'use strict';

    var cfgEl = document.getElementById('payConfig');
    var form  = document.getElementById('checkoutForm');
    if (!cfgEl || !form) { return; }

    var CFG;
    try { CFG = JSON.parse(cfgEl.textContent || '{}'); } catch (err) { return; }

    var submitBtn  = document.getElementById('paySubmit');
    var submitTxt  = submitBtn ? submitBtn.querySelector('.pay__submit--label') : null;
    var alertBox   = document.getElementById('payAlert');
    var overlay    = document.getElementById('payOverlay');
    var overlayT   = document.getElementById('payOverlayTitle');
    var overlayP   = document.getElementById('payOverlayText');
    var stripeHost = document.getElementById('payStripeMount');
    var paypalHost = document.getElementById('payPaypalMount');

    var BASE = (document.querySelector('meta[name="base-url"]') || {}).content;
    if (typeof BASE !== 'string') { BASE = ''; }

    var originalLabel = submitTxt ? submitTxt.innerHTML : 'Place Order';

    /** Live state for the attempt in progress. */
    var state = {
        stage: 'idle',      // idle | busy | stripe_ready | paypal_ready | pending
        orderId: CFG.order_id || 0,
        paymentId: 0,
        gateway: '',
        stripe: null,
        elements: null,
        returnUrl: '',
        pollTimer: null,
        pollTries: 0,
        // true once we are navigating away on purpose (success / redirect flow)
        leaving: false,
        // set once a gateway decline has been reported for the current attempt
        failureRecorded: false
    };

    /* =================================================================
       Small helpers
       ================================================================= */

    function url(path) { return (BASE ? BASE + '/' : '') + path; }

    function post(path, data) {
        var body = new URLSearchParams();
        body.append('csrf_token', CFG.csrf);
        Object.keys(data || {}).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) { body.append(k, data[k]); }
        });

        return fetch(url(path), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () {
                throw new Error('The server sent an unexpected response. Please try again.');
            });
        });
    }

    /** Post the whole checkout form (address fields) plus extras. */
    function postForm(path, extras) {
        var body = new FormData(form);
        body.delete('place_order');
        body.delete('place_order_nojs');
        Object.keys(extras || {}).forEach(function (k) { body.set(k, extras[k]); });

        return fetch(url(path), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () {
                throw new Error('The server sent an unexpected response. Please try again.');
            });
        });
    }

    var scriptCache = {};
    function loadScript(src) {
        if (scriptCache[src]) { return scriptCache[src]; }
        scriptCache[src] = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = resolve;
            s.onerror = function () {
                delete scriptCache[src];
                reject(new Error('Could not load the payment library. Please check your connection and try again.'));
            };
            document.head.appendChild(s);
        });
        return scriptCache[src];
    }

    /* =================================================================
       UI state
       ================================================================= */

    function showAlert(kind, message, actions) {
        if (!alertBox) { return; }
        alertBox.className = 'pay__alert is-shown is-' + kind;
        alertBox.innerHTML = '';

        var p = document.createElement('div');
        p.textContent = message;
        alertBox.appendChild(p);

        if (actions && actions.length) {
            var row = document.createElement('div');
            row.className = 'pay__alert--actions';
            actions.forEach(function (a) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = a.label;
                b.addEventListener('click', a.onClick);
                row.appendChild(b);
            });
            alertBox.appendChild(row);
        }
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearAlert() {
        if (alertBox) { alertBox.className = 'pay__alert'; alertBox.innerHTML = ''; }
    }

    function busy(on, label) {
        state.stage = on ? 'busy' : 'idle';
        if (!submitBtn) { return; }
        submitBtn.disabled = !!on;
        submitBtn.classList.toggle('is-busy', !!on);
        if (submitTxt) {
            submitTxt.innerHTML = on
                ? '<span class="pay__spinner"></span>' + (label || 'Processing…')
                : originalLabel;
        }
    }

    function setLabel(html) {
        if (submitTxt) { submitTxt.innerHTML = html; }
    }

    function showOverlay(title, text) {
        if (!overlay) { return; }
        if (overlayT && title) { overlayT.textContent = title; }
        if (overlayP && text) { overlayP.textContent = text; }
        overlay.classList.add('is-shown');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function hideOverlay() {
        if (!overlay) { return; }
        overlay.classList.remove('is-shown');
        overlay.setAttribute('aria-hidden', 'true');
    }

    /**
     * Leave the checkout deliberately.
     *
     * Sets state.leaving first so the beforeunload guard below stays quiet.
     * Without it a successful payment redirecting to the confirmation page
     * tripped the browser's own "Leave site? Changes you made may not be
     * saved." dialog — asking the customer to confirm a navigation we asked
     * for, at the worst possible moment.
     */
    function go(target) {
        state.leaving = true;
        window.location.href = url(target);
    }

    /* =================================================================
       Gateway / method selection
       ================================================================= */

    function selectedGateway() {
        var el = form.querySelector('input[name="gateway"]:checked');
        return el ? el.value : '';
    }

    function selectedMethod(gateway) {
        var el = form.querySelector('input[name="method_' + gateway + '"]:checked')
              || form.querySelector('input[name="method_' + gateway + '"]');
        return el ? el.value : '';
    }

    function resetGatewayUi() {
        stopPolling();
        clearAlert();
        hideOverlay();
        state.stage = 'idle';
        state.paymentId = 0;
        state.failureRecorded = false;
        state.stripe = null;
        state.elements = null;
        if (stripeHost) { stripeHost.classList.remove('is-shown'); stripeHost.innerHTML = ''; }
        if (paypalHost) { paypalHost.classList.remove('is-shown'); paypalHost.innerHTML = ''; }
        if (submitBtn) { submitBtn.style.display = ''; submitBtn.disabled = false; submitBtn.classList.remove('is-busy'); }
        setLabel(originalLabel);
    }

    form.querySelectorAll('input[name="gateway"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            form.querySelectorAll('.pay__gateway').forEach(function (card) {
                card.classList.toggle('is-active', card.dataset.gateway === radio.value);
            });
            resetGatewayUi();
        });
    });

    // Clicking anywhere on the card selects it.
    form.querySelectorAll('.pay__gateway').forEach(function (card) {
        card.addEventListener('click', function (ev) {
            if (ev.target.closest('.pay__methods')) { return; }
            var radio = card.querySelector('input[name="gateway"]');
            if (radio && !radio.checked) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
        });
    });

    /* =================================================================
       Step 1 — create the order + gateway attempt
       ================================================================= */

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();

        // Stripe collects the card first, so the second click confirms.
        if (state.stage === 'stripe_ready') { confirmStripe(); return; }
        if (state.stage === 'busy' || state.stage === 'pending') { return; }

        var gateway = selectedGateway();
        if (!gateway) {
            showAlert('error', 'Please choose a payment method to continue.');
            return;
        }

        clearAlert();
        busy(true, 'Starting secure payment…');
        state.gateway = gateway;
        // a fresh attempt starts with no decline on record
        state.failureRecorded = false;

        postForm('ajax/payment/create.php', {
            gateway: gateway,
            method: selectedMethod(gateway),
            order_id: state.orderId || ''
        }).then(function (res) {
            var d = res.data || {};

            if (!res.success) {
                busy(false);
                if (d.order_id) { state.orderId = d.order_id; }
                showAlert('error', res.message || 'We could not start the payment. Please try again.');
                return;
            }

            if (d.order_id) { state.orderId = d.order_id; }
            if (d.payment_id) { state.paymentId = d.payment_id; }

            if (d.already_paid || d.flow === 'offline') {
                showOverlay('Placing your order', 'Almost done…');
                go(d.redirect);
                return;
            }

            switch (d.flow) {
                case 'redirect':
                    showOverlay('Redirecting to ' + gatewayLabel(gateway), 'You will come straight back once the payment is done.');
                    /* Hand-off to a hosted gateway (PhonePe and friends). go() cannot
                       be used because that URL is absolute and external, so release
                       the unload guard here or the customer is asked to confirm
                       leaving on the way to pay. */
                    state.leaving = true;
                    window.location.href = d.redirect_url;
                    return;
                case 'razorpay': return openRazorpay(d);
                case 'stripe':   return openStripe(d);
                case 'paypal':   return openPaypal(d);
                default:
                    busy(false);
                    showAlert('error', 'That payment method is not available right now.');
            }
        }).catch(function (err) {
            busy(false);
            showAlert('error', err.message || 'Something went wrong. Please try again.');
        });
    });

    function gatewayLabel(key) {
        var card = form.querySelector('.pay__gateway[data-gateway="' + key + '"] .pay__gateway--label');
        return card ? card.textContent.trim() : 'the payment page';
    }

    /* =================================================================
       Razorpay — hosted modal
       ================================================================= */

    function openRazorpay(d) {
        loadScript('https://checkout.razorpay.com/v1/checkout.js').then(function () {
            busy(false);

            var options = {
                key: d.key,
                order_id: d.rzp_order_id,
                amount: d.amount,
                currency: d.currency,
                name: d.name,
                description: d.description,
                prefill: d.prefill || {},
                notes: { order_number: d.order_number || '' },
                theme: { color: '#a67c2e' },
                handler: function (response) {
                    verify({
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature
                    });
                },
                modal: {
                    ondismiss: function () { cancelAttempt('You closed the payment window before it finished.'); }
                }
            };
            if (d.method) { options.prefill.method = d.method; }

            var rzp = new window.Razorpay(options);
            rzp.on('payment.failed', function (resp) {
                var err    = (resp && resp.error) || {};
                var reason = err.description || '';

                /* Razorpay only reports a decline to the browser, so tell the
                   server ourselves. Without this the attempt was later filed as
                   "Closed by customer" by ondismiss and the real reason was lost. */
                reportFailure({
                    reason: reason,
                    code: err.code || err.reason || '',
                    step: err.step || '',
                    source: err.source || '',
                    gateway_payment_id: (err.metadata && err.metadata.payment_id) || ''
                });

                failAndOffer(reason
                    ? reason + ' Your order has not been charged.'
                    : 'Payment failed. Your order has not been charged.');
            });
            rzp.open();
        }).catch(function (err) {
            busy(false);
            showAlert('error', err.message);
        });
    }

    /* =================================================================
       Stripe — Payment Element (two-step: collect, then confirm)
       ================================================================= */

    function openStripe(d) {
        loadScript('https://js.stripe.com/v3/').then(function () {
            state.stripe    = window.Stripe(d.publishable_key);
            state.elements  = state.stripe.elements({
                clientSecret: d.client_secret,
                appearance: { theme: 'flat', variables: { colorPrimary: '#a67c2e', borderRadius: '8px' } }
            });
            state.returnUrl = d.return_url;

            stripeHost.classList.add('is-shown');
            stripeHost.innerHTML = '';
            state.elements.create('payment').mount(stripeHost);

            busy(false);
            state.stage = 'stripe_ready';
            setLabel('Pay Securely');
            showAlert('info', 'Enter your card details above, then press “Pay Securely”. Your card details go straight to Stripe.');
            stripeHost.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }).catch(function (err) {
            busy(false);
            showAlert('error', err.message);
        });
    }

    function confirmStripe() {
        if (!state.stripe || !state.elements) { return; }
        clearAlert();
        busy(true, 'Confirming with your bank…');
        showOverlay('Confirming your payment', 'Your bank may ask you to approve this payment.');

        state.stripe.confirmPayment({
            elements: state.elements,
            confirmParams: { return_url: state.returnUrl },
            redirect: 'if_required'
        }).then(function (result) {
            hideOverlay();
            if (result.error) {
                busy(false);
                state.stage = 'stripe_ready';
                setLabel('Pay Securely');
                // Stripe's own message is customer-safe and never contains keys.
                showAlert('error', result.error.message || 'Payment failed. Your order has not been charged.');
                return;
            }
            verify({ payment_intent: result.paymentIntent ? result.paymentIntent.id : '' });
        }).catch(function () {
            hideOverlay();
            busy(false);
            state.stage = 'stripe_ready';
            setLabel('Pay Securely');
            showAlert('error', 'Payment failed. Your order has not been charged. Please try again.');
        });
    }

    /* =================================================================
       PayPal — hosted buttons
       ================================================================= */

    function openPaypal(d) {
        loadScript(d.sdk_url).then(function () {
            busy(false);
            state.stage = 'paypal_ready';

            paypalHost.classList.add('is-shown');
            paypalHost.innerHTML = '';
            if (submitBtn) { submitBtn.style.display = 'none'; }

            window.paypal.Buttons({
                style: { layout: 'vertical', shape: 'pill', label: 'pay' },
                createOrder: function () { return d.paypal_order_id; },
                onApprove: function () {
                    showOverlay('Confirming your payment', 'PayPal is completing the payment.');
                    return verify({ paypal_order_id: d.paypal_order_id });
                },
                onCancel: function () {
                    if (submitBtn) { submitBtn.style.display = ''; }
                    paypalHost.classList.remove('is-shown');
                    cancelAttempt('You cancelled the PayPal payment.');
                },
                onError: function () {
                    if (submitBtn) { submitBtn.style.display = ''; }
                    paypalHost.classList.remove('is-shown');
                    failAndOffer('PayPal could not complete this payment. Your order has not been charged.');
                }
            }).render(paypalHost);

            showAlert('info', 'Choose “PayPal” above to finish paying. You can change the method at any time.');
        }).catch(function (err) {
            busy(false);
            showAlert('error', err.message);
        });
    }

    /* =================================================================
       Step 2 — server-side verification (the only source of truth)
       ================================================================= */

    function verify(extra) {
        showOverlay('Verifying your payment', 'Checking the payment with the bank. This takes a moment.');
        busy(true, 'Verifying…');

        var payload = { payment_id: state.paymentId };
        Object.keys(extra || {}).forEach(function (k) { payload[k] = extra[k]; });

        return post('ajax/payment/verify.php', payload).then(function (res) {
            var d = res.data || {};

            if (res.success) {
                showOverlay('Payment successful', 'Taking you to your order confirmation…');
                go(d.redirect);
                return;
            }

            hideOverlay();
            busy(false);

            if (d.pending) {
                state.stage = 'pending';
                showAlert('info', res.message || 'Your payment is still being confirmed. Please keep this page open.');
                startPolling();
                return;
            }

            failAndOffer(res.message);
        }).catch(function () {
            hideOverlay();
            busy(false);
            state.stage = 'pending';
            showAlert('info', 'We could not reach the server to confirm your payment. Checking again…');
            startPolling();
        });
    }

    /* =================================================================
       Pending payments — poll until the webhook settles it
       ================================================================= */

    function startPolling() {
        stopPolling();
        state.pollTries = 0;
        state.pollTimer = setTimeout(pollOnce, 4000);
    }

    function stopPolling() {
        if (state.pollTimer) { clearTimeout(state.pollTimer); state.pollTimer = null; }
    }

    function pollOnce() {
        if (state.pollTries++ >= 30) {   // ~2.5 minutes
            showAlert('info', 'This payment is taking longer than usual. We will email you as soon as it is confirmed — you can also check the order in your account.', [
                { label: 'View my orders', onClick: function () { go('account.php?tab=orders'); } }
            ]);
            return;
        }

        post('ajax/payment/status.php', {
            payment_id: state.paymentId || '',
            order_id: state.orderId || ''
        }).then(function (res) {
            var d = res.data || {};
            if (d.redirect) { showOverlay('Payment successful', 'Taking you to your order confirmation…'); go(d.redirect); return; }
            if (d.settled)  { failAndOffer(null); return; }
            state.pollTimer = setTimeout(pollOnce, 5000);
        }).catch(function () {
            state.pollTimer = setTimeout(pollOnce, 8000);
        });
    }

    /* =================================================================
       Failure + cancellation
       ================================================================= */

    function failAndOffer(message) {
        stopPolling();
        hideOverlay();
        busy(false);
        state.stage = 'idle';
        if (stripeHost) { stripeHost.classList.remove('is-shown'); stripeHost.innerHTML = ''; }
        if (paypalHost) { paypalHost.classList.remove('is-shown'); paypalHost.innerHTML = ''; }
        if (submitBtn) { submitBtn.style.display = ''; }
        setLabel(originalLabel);

        showAlert('error', message || 'Payment failed. Your order has not been charged. Please try again or choose another payment method.', [
            {
                label: 'Try again',
                onClick: function () { clearAlert(); form.dispatchEvent(new Event('submit', { cancelable: true })); }
            },
            {
                label: 'Choose another method',
                onClick: function () {
                    clearAlert();
                    var list = document.getElementById('payGateways');
                    if (list) { list.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                }
            }
        ]);
    }

    /**
     * Tell the server a gateway declined this attempt.
     * Sets state.failureRecorded so the modal's ondismiss, which fires straight
     * afterwards when the customer closes the window, does not relabel a real
     * decline as a cancellation.
     */
    function reportFailure(info) {
        if (!state.paymentId) { return; }
        state.failureRecorded = true;
        post('ajax/payment/fail.php', {
            payment_id: state.paymentId,
            reason: info.reason || '',
            code: info.code || '',
            step: info.step || '',
            source: info.source || '',
            gateway_payment_id: info.gateway_payment_id || ''
        }).catch(function () {});
    }

    function cancelAttempt(message) {
        stopPolling();
        hideOverlay();
        busy(false);
        state.stage = 'idle';
        setLabel(originalLabel);

        // A decline we already recorded must not be rewritten as a cancellation.
        if (state.paymentId && !state.failureRecorded) {
            post('ajax/payment/cancel.php', { payment_id: state.paymentId }).catch(function () {});
        }

        showAlert('info', (message || 'Payment cancelled.') + ' Your order has not been charged — you can try again whenever you are ready.', [
            {
                label: 'Try again',
                onClick: function () { clearAlert(); form.dispatchEvent(new Event('submit', { cancelable: true })); }
            }
        ]);
    }

    /* Warn only when the customer is abandoning a payment we are still
       waiting on — never when we are the ones navigating. */
    window.addEventListener('beforeunload', function (ev) {
        if (state.leaving) { return; }
        if (state.stage === 'busy' || state.stage === 'pending') {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });
})();
