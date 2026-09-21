<?php
/**
 * Payment gateway configuration.
 *
 * SECRETS ARE NOT STORED IN THIS FILE.  Real credentials belong in
 * `config/payment.local.php` (git-ignored, `Require all denied` via
 * config/.htaccess) or in real environment variables.  This file only
 * describes the gateways and resolves whichever source is available.
 *
 *   copy config/payment.local.example.php -> config/payment.local.php
 *
 * Nothing here is ever echoed to the browser; publishable/client ids are
 * exposed only through pay_public_config(), which whitelists them explicitly.
 */

require_once __DIR__ . '/../includes/functions.php';

/** Absolute path of the protected credentials file. */
function pay_local_file(): string
{
    return __DIR__ . '/payment.local.php';
}

/**
 * Load the git-ignored local secrets file once.
 * @param bool $reload force a re-read after the admin panel rewrites the file
 */
function pay_local_secrets(bool $reload = false): array
{
    static $local = null;
    if ($local === null || $reload) {
        // The secrets file refuses to run unless this is set, so a stray direct
        // request cannot execute it even on a server that ignores .htaccess.
        if (!defined('AJ_CONFIG_LOADER')) {
            define('AJ_CONFIG_LOADER', true);
        }
        $file = pay_local_file();
        if ($reload && function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        $local = is_file($file) ? (array) (require $file) : [];
    }
    return $local;
}

/** Secret lookup order: local file -> environment -> default. */
function pay_secret(string $key, string $default = ''): string
{
    $local = pay_local_secrets();
    if (array_key_exists($key, $local) && $local[$key] !== '') {
        return (string) $local[$key];
    }
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return (string) $env;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    return $default;
}

/** 'TEST' or 'LIVE'. Settings row wins, then the local file, then TEST. */
function pay_mode(): string
{
    $mode = strtoupper((string) get_setting('payment_mode', pay_secret('PAYMENT_MODE', 'TEST')));
    return $mode === 'LIVE' ? 'LIVE' : 'TEST';
}

function pay_is_live(): bool
{
    return pay_mode() === 'LIVE';
}

/** Store currency (ISO-4217). */
function pay_currency(): string
{
    return strtoupper((string) get_setting('payment_currency', 'INR'));
}

/**
 * Static description of every gateway: the methods it can present, the
 * currencies it may run in, and how the customer reaches it.
 *
 * `flow`:  'modal'    -> JS SDK opens over the page (Razorpay, Stripe, PayPal)
 *          'redirect' -> full page hand-off then return URL (PhonePe)
 *          'offline'  -> no gateway at all (COD)
 */
function pay_gateways(): array
{
    return [
        'cod' => [
            'label'       => 'Cash on Delivery',
            'blurb'       => 'Pay in cash when your order is delivered.',
            'flow'        => 'offline',
            'methods'     => [['code' => 'cod', 'label' => 'Cash on delivery']],
            'currencies'  => ['INR'],
        ],
        'razorpay' => [
            'label'      => 'Razorpay',
            'blurb'      => 'UPI, cards, net banking and wallets.',
            'flow'       => 'modal',
            'currencies' => ['INR'],
            'methods'    => [
                ['code' => 'upi',        'label' => 'UPI'],
                ['code' => 'card',       'label' => 'Credit / Debit Card'],
                ['code' => 'netbanking', 'label' => 'Net Banking'],
                ['code' => 'wallet',     'label' => 'Wallets'],
            ],
        ],
        'stripe' => [
            'label'      => 'Stripe',
            'blurb'      => 'International credit & debit cards, 3-D Secure.',
            'flow'       => 'modal',
            'currencies' => ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD'],
            'methods'    => [
                ['code' => 'card', 'label' => 'Credit / Debit Card'],
            ],
        ],
        'paypal' => [
            'label'      => 'PayPal',
            'blurb'      => 'Pay with a PayPal balance or a card.',
            'flow'       => 'modal',
            // PayPal does not settle INR — international currencies only.
            'currencies' => ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD'],
            'methods'    => [
                ['code' => 'paypal', 'label' => 'PayPal account'],
                ['code' => 'card',   'label' => 'Debit / Credit Card'],
            ],
        ],
        'phonepe' => [
            'label'      => 'PhonePe UPI',
            'blurb'      => 'Pay by UPI through the PhonePe app or web.',
            'flow'       => 'redirect',
            'currencies' => ['INR'],
            'methods'    => [
                ['code' => 'upi', 'label' => 'UPI'],
            ],
        ],
    ];
}

/** Server-side credentials + endpoints for one gateway, resolved for the current mode. */
function pay_credentials(string $gateway): array
{
    $live = pay_is_live();
    $sfx  = $live ? '_LIVE' : '_TEST';

    switch ($gateway) {
        case 'razorpay':
            return [
                'key_id'         => pay_secret('RAZORPAY_KEY_ID' . $sfx, pay_secret('RAZORPAY_KEY_ID')),
                'key_secret'     => pay_secret('RAZORPAY_KEY_SECRET' . $sfx, pay_secret('RAZORPAY_KEY_SECRET')),
                'webhook_secret' => pay_secret('RAZORPAY_WEBHOOK_SECRET' . $sfx, pay_secret('RAZORPAY_WEBHOOK_SECRET')),
                'api'            => 'https://api.razorpay.com/v1',
            ];

        case 'stripe':
            return [
                'publishable_key' => pay_secret('STRIPE_PUBLISHABLE_KEY' . $sfx, pay_secret('STRIPE_PUBLISHABLE_KEY')),
                'secret_key'      => pay_secret('STRIPE_SECRET_KEY' . $sfx, pay_secret('STRIPE_SECRET_KEY')),
                'webhook_secret'  => pay_secret('STRIPE_WEBHOOK_SECRET' . $sfx, pay_secret('STRIPE_WEBHOOK_SECRET')),
                'api'             => 'https://api.stripe.com/v1',
            ];

        case 'paypal':
            return [
                'client_id'     => pay_secret('PAYPAL_CLIENT_ID' . $sfx, pay_secret('PAYPAL_CLIENT_ID')),
                'client_secret' => pay_secret('PAYPAL_CLIENT_SECRET' . $sfx, pay_secret('PAYPAL_CLIENT_SECRET')),
                'webhook_id'    => pay_secret('PAYPAL_WEBHOOK_ID' . $sfx, pay_secret('PAYPAL_WEBHOOK_ID')),
                'api'           => $live ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com',
                'sdk'           => 'https://www.paypal.com/sdk/js',
            ];

        case 'phonepe':
            return [
                'merchant_id' => pay_secret('PHONEPE_MERCHANT_ID' . $sfx, pay_secret('PHONEPE_MERCHANT_ID')),
                'salt_key'    => pay_secret('PHONEPE_SALT_KEY' . $sfx, pay_secret('PHONEPE_SALT_KEY')),
                'salt_index'  => pay_secret('PHONEPE_SALT_INDEX' . $sfx, pay_secret('PHONEPE_SALT_INDEX', '1')),
                'api'         => $live
                    ? 'https://api.phonepe.com/apis/hermes'
                    : 'https://api-preprod.phonepe.com/apis/pg-sandbox',
            ];
    }

    return [];
}

/**
 * A gateway is usable when: it is switched on in Settings, its credentials
 * are present, and it supports the store currency.
 */
function pay_gateway_enabled(string $gateway): bool
{
    if (get_setting('gateway_' . $gateway . '_enabled', '0') !== '1') {
        return false;
    }
    $all = pay_gateways();
    if (!isset($all[$gateway])) {
        return false;
    }
    if (!in_array(pay_currency(), $all[$gateway]['currencies'], true)) {
        return false;
    }
    if ($gateway === 'cod') {
        return true;
    }

    $c = pay_credentials($gateway);
    switch ($gateway) {
        case 'razorpay': return $c['key_id'] !== '' && $c['key_secret'] !== '';
        case 'stripe':   return $c['publishable_key'] !== '' && $c['secret_key'] !== '';
        case 'paypal':   return $c['client_id'] !== '' && $c['client_secret'] !== '';
        case 'phonepe':  return $c['merchant_id'] !== '' && $c['salt_key'] !== '';
    }
    return false;
}

/** Gateways the checkout page may show, in display order. */
function pay_available_gateways(): array
{
    $out = [];
    foreach (pay_gateways() as $key => $meta) {
        if (pay_gateway_enabled($key)) {
            $out[$key] = $meta;
        }
    }
    return $out;
}

/**
 * The ONLY values allowed to reach the browser. Publishable/client ids are
 * designed to be public; secrets are never included here.
 */
function pay_public_config(string $gateway): array
{
    $c = pay_credentials($gateway);
    switch ($gateway) {
        case 'razorpay': return ['key' => $c['key_id']];
        case 'stripe':   return ['publishable_key' => $c['publishable_key']];
        case 'paypal':   return ['client_id' => $c['client_id'], 'sdk' => $c['sdk']];
    }
    return [];
}

/* ==================================================================
   Editable credential schema (admin panel)
   ==================================================================
   The admin screen writes these into config/payment.local.php — the same
   protected, git-ignored file you would edit by hand. Nothing secret is ever
   stored in the database, and `public => false` values are never rendered
   back into the form.
   ================================================================== */

/**
 * Every credential slot the admin panel may edit.
 * `public` marks values the gateways themselves publish in the browser; only
 * those are ever echoed back. Everything else is write-only from the UI.
 *
 * @return array<string, array{0:string,1:string,2:bool,3:string}>
 *         gateway => [label, hint, isPublic, placeholder] keyed by env name
 */
function pay_credential_schema(): array
{
    return [
        'general' => [
            'APP_URL' => ['Site URL', 'Public base URL of the shop. Required in LIVE so gateways can build return and webhook URLs.', true, 'https://www.yourshop.com'],
        ],
        'razorpay' => [
            'RAZORPAY_KEY_ID'         => ['Key id', 'Dashboard → Settings → API Keys', true, 'rzp_test_xxxxxxxxxxxx'],
            'RAZORPAY_KEY_SECRET'     => ['Key secret', 'Shown once when the key pair is generated.', false, ''],
            'RAZORPAY_WEBHOOK_SECRET' => ['Webhook secret', 'Settings → Webhooks → the secret you chose.', false, ''],
        ],
        'stripe' => [
            'STRIPE_PUBLISHABLE_KEY' => ['Publishable key', 'Safe in the browser.', true, 'pk_test_xxxxxxxxxxxx'],
            'STRIPE_SECRET_KEY'      => ['Secret key', 'Server only. Never share it.', false, ''],
            'STRIPE_WEBHOOK_SECRET'  => ['Webhook signing secret', 'Developers → Webhooks → the endpoint\'s signing secret (whsec_…).', false, ''],
        ],
        'paypal' => [
            'PAYPAL_CLIENT_ID'     => ['Client id', 'Apps & Credentials.', true, ''],
            'PAYPAL_CLIENT_SECRET' => ['Client secret', 'Server only.', false, ''],
            'PAYPAL_WEBHOOK_ID'    => ['Webhook id', 'From the webhook you registered.', true, ''],
        ],
        'phonepe' => [
            'PHONEPE_MERCHANT_ID' => ['Merchant id', 'e.g. PGTESTPAYUAT on the sandbox.', true, 'PGTESTPAYUAT'],
            'PHONEPE_SALT_KEY'    => ['Salt key', 'Used to sign every request. Server only.', false, ''],
            'PHONEPE_SALT_INDEX'  => ['Salt index', 'Usually 1.', true, '1'],
        ],
    ];
}

/** Env-var name for one slot in one mode. APP_URL has no per-mode variant. */
function pay_credential_env(string $base, string $mode): string
{
    return $base === 'APP_URL' ? $base : $base . '_' . ($mode === 'LIVE' ? 'LIVE' : 'TEST');
}

/** Is this slot filled in for the given mode? */
function pay_credential_filled(string $base, string $mode): bool
{
    return pay_secret(pay_credential_env($base, $mode), '') !== '';
}

/** Is the credentials file writable (or creatable) by the web server? */
function pay_local_writable(): bool
{
    $file = pay_local_file();
    return is_file($file) ? is_writable($file) : is_writable(dirname($file));
}

/**
 * Merge values into config/payment.local.php and rewrite it atomically.
 *
 * @param array<string,string|null> $updates env name => new value.
 *        '' leaves the stored value alone; null clears it.
 * @return array{0:bool,1:string} [ok, message]
 */
function pay_write_local_secrets(array $updates): array
{
    $file = pay_local_file();

    if (!pay_local_writable()) {
        return [false, 'config/payment.local.php is not writable by the web server. Fix its permissions, or edit the file by hand.'];
    }

    $current = pay_local_secrets(true);

    foreach ($updates as $key => $value) {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', (string) $key)) {
            continue;                       // never let an arbitrary key in
        }
        if ($value === null) {
            unset($current[$key]);          // explicit clear
        } elseif ($value !== '') {
            $current[$key] = (string) $value;
        }                                   // '' => keep whatever is stored
    }

    // An empty value is indistinguishable from a missing one to pay_secret(),
    // so drop the blanks instead of writing rows of => '' into the file.
    $current = array_filter($current, static fn($v) => (string) $v !== '');
    ksort($current);

    $out  = "<?php\n";
    $out .= "/**\n";
    $out .= " * Payment gateway credentials.\n";
    $out .= " *\n";
    $out .= " * Written by Admin -> Payment Gateways on " . date('d M Y H:i') . ".\n";
    $out .= " * You may also edit it by hand. It is git-ignored, blocked from the web by\n";
    $out .= " * config/.htaccess, and refuses to run unless loaded by pay_local_secrets().\n";
    $out .= " *\n";
    $out .= " * NEVER commit this file or paste its contents anywhere.\n";
    $out .= " */\n\n";
    $out .= "// Only pay_local_secrets() may load this file.\n";
    $out .= "if (!defined('AJ_CONFIG_LOADER')) {\n    http_response_code(404);\n    exit;\n}\n\n";
    $out .= "return [\n";
    foreach ($current as $k => $v) {
        $out .= '    ' . var_export((string) $k, true) . ' => ' . var_export((string) $v, true) . ",\n";
    }
    $out .= "];\n";

    // Write to a sibling temp file and rename, so a failed write can never
    // leave a half-written credentials file behind.
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $out, LOCK_EX) === false) {
        @unlink($tmp);
        return [false, 'Could not write the credentials file.'];
    }
    @chmod($tmp, 0600);

    if (!@rename($tmp, $file)) {
        // Windows will not rename onto an existing file on some filesystems.
        if (!@copy($tmp, $file)) {
            @unlink($tmp);
            return [false, 'Could not replace the credentials file.'];
        }
        @unlink($tmp);
    }
    @chmod($file, 0600);

    pay_local_secrets(true);                // drop the cached copy
    return [true, 'Credentials saved.'];
}

/** Absolute site URL, used to build return/callback URLs for the gateways. */
function pay_base_url(): string
{
    $configured = rtrim(pay_secret('APP_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    // AJAX + webhook endpoints live one level down; normalise back to the root.
    $script = preg_replace('#/(ajax(/[^/]+)?|webhooks|admin)$#', '', $script);
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($script, '/');
}

function pay_url(string $path): string
{
    return pay_base_url() . '/' . ltrim($path, '/');
}

/** Production must be HTTPS. Returns a warning string, or '' when fine. */
function pay_environment_warning(): string
{
    if (!pay_is_live()) {
        return '';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return $https ? '' : 'Live payment mode requires HTTPS.';
}
