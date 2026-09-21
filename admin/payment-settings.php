<?php
/**
 * Payment gateway settings.
 *
 * SECURITY: secret keys are still never stored in the database and never sent
 * back to the browser. The credential form writes straight into
 * config/payment.local.php — the same protected, git-ignored file you would
 * edit by hand — and secret fields are write-only: they render empty, show
 * only "Configured"/"Not set", and an empty submission leaves the stored value
 * untouched. Only the publishable ids the gateways themselves put in the
 * browser (key id, publishable key, client id, merchant id) are echoed back.
 */

require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/charts.php';
require_once __DIR__ . '/../includes/payments/manager.php';
requireAdmin();

$db       = getDB();
$gateways = pay_gateways();
$schema   = pay_credential_schema();

if (admin_post_ok('payment-settings.php')) {
    $action = (string) ($_POST['do'] ?? 'settings');

    /* ---------------- mode, currency, on/off switches ---------------- */
    if ($action === 'settings') {
        $set = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

        $mode = strtoupper((string) ($_POST['payment_mode'] ?? 'TEST')) === 'LIVE' ? 'LIVE' : 'TEST';
        $set->execute(['payment_mode', $mode]);

        $currency = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_POST['payment_currency'] ?? 'INR')));
        if (strlen($currency) === 3) {
            $set->execute(['payment_currency', $currency]);
        }

        foreach (array_keys($gateways) as $key) {
            $set->execute(['gateway_' . $key . '_enabled', empty($_POST['gateway_' . $key]) ? '0' : '1']);
        }

        flash_set('admin_ok', 'Payment settings saved. Mode is now ' . $mode . '.');
        redirect('payment-settings.php');
    }

    /* ---------------- credentials -> config/payment.local.php -------- */
    if ($action === 'credentials') {
        $editMode = strtoupper((string) ($_POST['edit_mode'] ?? 'TEST')) === 'LIVE' ? 'LIVE' : 'TEST';
        $updates  = [];
        $errors   = [];

        foreach ($schema as $group => $fields) {
            foreach ($fields as $base => [$label, $hint, $isPublic, $placeholder]) {
                $env   = pay_credential_env($base, $editMode);
                $field = 'cred_' . $base;

                if (!empty($_POST['clear_' . $base])) {
                    $updates[$env] = null;              // explicit clear
                    continue;
                }
                if (!array_key_exists($field, $_POST)) {
                    continue;
                }

                $value = trim((string) $_POST[$field]);

                // Secret fields are write-only: blank means "leave it alone".
                // Public fields are shown, so blank there really means blank —
                // except APP_URL, which is legitimately optional.
                if ($value === '') {
                    if ($isPublic && pay_credential_filled($base, $editMode)) {
                        $updates[$env] = null;
                    }
                    continue;
                }

                if ($base === 'APP_URL') {
                    $value  = rtrim($value, '/');
                    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                    if (!filter_var($value, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                        $errors[] = 'Site URL must be a full http:// or https:// address.';
                        continue;
                    }
                }
                if ($base === 'PHONEPE_SALT_INDEX' && !ctype_digit($value)) {
                    $errors[] = 'Salt index must be a number.';
                    continue;
                }
                if (strlen($value) > 500) {
                    $errors[] = $label . ' is too long.';
                    continue;
                }

                $updates[$env] = $value;
            }
        }

        if ($errors) {
            flash_set('admin_err', implode(' ', array_unique($errors)));
            redirect('payment-settings.php?mode=' . $editMode . '#credentials');
        }

        [$ok, $message] = pay_write_local_secrets($updates);

        // Record that credentials changed — never what they changed to.
        pay_log('config', $ok ? 'credentials.updated' : 'credentials.error', [
            'status'        => $editMode,
            'error_message' => $ok ? null : $message,
        ]);

        flash_set($ok ? 'admin_ok' : 'admin_err', $ok ? $editMode . ' credentials saved.' : $message);
        redirect('payment-settings.php?mode=' . $editMode . '#credentials');
    }
}

// re-read after any save so the page always shows the stored truth
$settings = [];
foreach ($db->query('SELECT setting_key, setting_value FROM settings') as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$GLOBALS['__site_settings'] = array_merge($GLOBALS['__site_settings'] ?? [], $settings);

$mode     = pay_mode();
$currency = pay_currency();
$warning  = pay_environment_warning();

// which key set the credential form is editing (independent of the live mode)
$editMode = strtoupper((string) ($_GET['mode'] ?? $mode)) === 'LIVE' ? 'LIVE' : 'TEST';

$webhookUrls = [
    'razorpay' => pay_url('webhooks/razorpay.php'),
    'stripe'   => pay_url('webhooks/stripe.php'),
    'paypal'   => pay_url('webhooks/paypal.php'),
    'phonepe'  => pay_url('webhooks/phonepe.php'),
];

$hasLocal  = is_file(pay_local_file());
$writable  = pay_local_writable();

/** Gateway label for a schema group key. */
$groupLabel = function ($g) use ($gateways) {
    return $g === 'general' ? 'General' : ($gateways[$g]['label'] ?? ucfirst($g));
};

$adminPageTitle = 'Payment Gateways';
$adminNav = 'pay_settings';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Payment Gateways', 'Keys, mode, currency and which gateways the checkout offers', '<a class="btn btn--ghost" href="payments.php">View transactions</a>');
?>

<div class="mode__banner <?php echo $mode === 'LIVE' ? 'is-live' : 'is-test'; ?>">
    <strong><?php echo $mode; ?> MODE</strong>
    <span>
        <?php if ($mode === 'LIVE'): ?>
            Real money is being taken. Every payment uses your live credentials.
        <?php else: ?>
            Sandbox credentials only — no real money moves. Switch to LIVE when you are ready to sell.
        <?php endif; ?>
    </span>
</div>

<?php if ($warning !== ''): ?>
    <div class="notice notice--warn"><?php echo e($warning); ?> Online payments stay disabled until the site is served over HTTPS.</div>
<?php endif; ?>

<?php if (!$writable): ?>
    <div class="notice notice--err">
        <strong>config/payment.local.php is not writable.</strong>
        The form below cannot save until the web server can write to it. Give the file (or the
        <code>config/</code> folder) write permission, or edit the file by hand.
    </div>
<?php endif; ?>

<!-- ============ Credentials ============ -->
<div class="admin__card" id="credentials">
    <div class="gw__head" style="margin-bottom:10px">
        <h3 style="margin:0">API Keys</h3>
        <div class="mode__tabs">
            <a class="<?php echo $editMode === 'TEST' ? 'is-active' : ''; ?>" href="?mode=TEST#credentials">Test keys</a>
            <a class="<?php echo $editMode === 'LIVE' ? 'is-active' : ''; ?>" href="?mode=LIVE#credentials">Live keys</a>
        </div>
    </div>

    <p class="muted" style="margin-top:-2px">
        Saved to <code>config/payment.local.php</code> — never to the database, never into Git, never sent back to
        this page. Secret fields are write-only: leave one blank to keep the key that is already stored.
        You are editing the <strong><?php echo $editMode; ?></strong> key set<?php echo $editMode === $mode ? ' (currently live on the checkout)' : ''; ?>.
    </p>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="do" value="credentials">
        <input type="hidden" name="edit_mode" value="<?php echo e($editMode); ?>">

        <?php foreach ($schema as $group => $fields): ?>
            <div class="cred__group">
                <h4 class="cred__group--title">
                    <?php echo e($groupLabel($group)); ?>
                    <?php if ($group !== 'general'): ?>
                        <?php $liveOk = pay_gateway_enabled($group); ?>
                        <?php echo status_pill($liveOk ? 'active' : 'inactive'); ?>
                    <?php endif; ?>
                </h4>

                <div class="row2">
                <?php foreach ($fields as $base => [$label, $hint, $isPublic, $placeholder]):
                    $filled = pay_credential_filled($base, $editMode);
                    $value  = $isPublic ? pay_secret(pay_credential_env($base, $editMode), '') : '';
                ?>
                    <div class="field">
                        <label for="cred_<?php echo e($base); ?>">
                            <?php echo e($label); ?>
                            <?php if (!$isPublic): ?>
                                <span class="cred__tag <?php echo $filled ? 'is-set' : ''; ?>">
                                    <?php echo $filled ? 'Configured' : 'Not set'; ?>
                                </span>
                            <?php endif; ?>
                        </label>

                        <input type="<?php echo $isPublic ? 'text' : 'password'; ?>"
                               id="cred_<?php echo e($base); ?>"
                               name="cred_<?php echo e($base); ?>"
                               value="<?php echo e($value); ?>"
                               placeholder="<?php echo e($isPublic ? $placeholder : ($filled ? 'Leave blank to keep the stored key' : 'Paste the key')); ?>"
                               spellcheck="false"
                               autocomplete="off">

                        <p class="cred__hint"><?php echo e($hint); ?></p>

                        <?php if ($filled): ?>
                            <label class="cred__clear">
                                <input type="checkbox" name="clear_<?php echo e($base); ?>" value="1">
                                Remove this value
                            </label>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <button class="btn" type="submit" <?php echo $writable ? '' : 'disabled'; ?>>
            Save <?php echo $editMode; ?> Keys
        </button>
        <?php if (!$hasLocal): ?>
            <p class="muted" style="margin-top:10px">Saving will create <code>config/payment.local.php</code> for you.</p>
        <?php endif; ?>
    </form>
</div>

<!-- ============ Mode / currency / switches ============ -->
<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="do" value="settings">

    <div class="admin__card">
        <h3>Mode &amp; Currency</h3>
        <div class="row2">
            <div class="field">
                <label>Payment mode</label>
                <select name="payment_mode">
                    <option value="TEST" <?php echo $mode === 'TEST' ? 'selected' : ''; ?>>TEST — sandbox keys, no real charges</option>
                    <option value="LIVE" <?php echo $mode === 'LIVE' ? 'selected' : ''; ?>>LIVE — real payments</option>
                </select>
                <p class="cred__hint">Switches which key set above is used. No code change needed.</p>
            </div>
            <div class="field">
                <label>Store currency (ISO 4217)</label>
                <input type="text" name="payment_currency" maxlength="3" value="<?php echo e($currency); ?>" style="text-transform:uppercase">
                <p class="cred__hint">A gateway only appears at checkout if it settles this currency. PayPal does not settle INR.</p>
            </div>
        </div>
    </div>

    <div class="admin__card">
        <h3>Gateways</h3>
        <p class="muted" style="margin-top:-6px">
            A gateway reaches the checkout only when it is switched on <em>and</em> its credentials exist for the current mode
            <em>and</em> it settles <?php echo e($currency); ?>.
        </p>

        <div class="gw__grid">
            <?php foreach ($gateways as $key => $meta):
                $enabled = get_setting('gateway_' . $key . '_enabled', '0') === '1';
                $live    = pay_gateway_enabled($key);
                $currOk  = in_array($currency, $meta['currencies'], true);
                $slots   = $schema[$key] ?? [];
            ?>
                <div class="gw__card<?php echo $live ? '' : ' is-off'; ?>">
                    <div class="gw__head">
                        <h4 class="gw__name"><?php echo e($meta['label']); ?></h4>
                        <?php echo status_pill($live ? 'active' : ($enabled ? 'pending' : 'inactive')); ?>
                    </div>
                    <p class="gw__blurb"><?php echo e($meta['blurb']); ?></p>

                    <label style="font-weight:400;display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="gateway_<?php echo e($key); ?>" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                        Offer at checkout
                    </label>

                    <ul class="gw__rows">
                        <li><span>Currencies</span><span><?php echo e(implode(', ', $meta['currencies'])); ?></span></li>
                        <li><span>Store currency</span><span><?php echo $currOk ? 'Supported' : '<b class="text-danger">Not supported</b>'; ?></span></li>
                        <li><span>Methods</span><span><?php echo e(implode(', ', array_column($meta['methods'], 'label'))); ?></span></li>
                        <?php foreach ($slots as $base => [$label, $hint, $isPublic, $ph]): ?>
                            <li>
                                <span><?php echo e($label); ?></span>
                                <span>
                                    <?php if (!pay_credential_filled($base, $mode)): ?>
                                        <b class="text-danger">Missing</b>
                                    <?php else: ?>
                                        <b style="color:var(--green)">Configured</b>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (isset($webhookUrls[$key])): ?>
                        <div>
                            <div class="muted" style="font-size:12px;margin-bottom:4px">Webhook URL — paste into the gateway dashboard</div>
                            <div class="gw__hook"><?php echo e($webhookUrls[$key]); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <button class="btn" type="submit">Save Payment Settings</button>
</form>

<div class="admin__card mt-3">
    <h3>Where the keys live</h3>
    <p class="muted">
        Everything you type above is written to <code>config/payment.local.php</code>. That file is git-ignored,
        blocked from the web by <code>config/.htaccess</code>, and refuses to execute unless the application loads it.
        Environment variables of the same names still work and are used when the file has no value for a key.
        Nothing secret is ever written to the database, printed on this page or sent to the browser.
    </p>
    <ul class="gw__rows">
        <li><span>Credential file</span><span><?php echo $hasLocal ? '<b style="color:var(--green)">Found</b>' : '<b class="text-danger">Not created yet</b>'; ?></span></li>
        <li><span>Writable</span><span><?php echo $writable ? '<b style="color:var(--green)">Yes</b>' : '<b class="text-danger">No</b>'; ?></span></li>
        <li><span>Return URL (redirect gateways)</span><span><code><?php echo e(pay_url('payment-return.php')); ?></code></span></li>
    </ul>
</div>
<?php admin_layout_end();
