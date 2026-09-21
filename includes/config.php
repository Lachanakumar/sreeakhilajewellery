<?php

/**
 * Website configuration.
 *
 * Values are pulled from the `settings` table (managed in Admin -> Settings)
 * with the hardcoded values below as fallbacks when a key is missing or the
 * database is unavailable.
 */

require_once __DIR__ . '/../config/database.php';

$GLOBALS['__site_settings'] = [];
try {
    foreach (getDB()->query('SELECT setting_key, setting_value FROM settings') as $__row) {
        $GLOBALS['__site_settings'][$__row['setting_key']] = $__row['setting_value'];
    }
} catch (Throwable $e) {
    // DB not ready yet (e.g. before schema import) - fall back to defaults.
}

if (!function_exists('cfg')) {
    /** Read a site setting, or $default if unset/blank. */
    function cfg($key, $default = null) {
        $v = $GLOBALS['__site_settings'][$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }
}

/* ---- Site identity ----
 *
 * Every value here is owned by Admin -> Settings and nothing else. There are
 * deliberately NO hardcoded fallbacks: a name, email, phone or address baked
 * into the code outlives the day someone changes it in the admin panel, and
 * the moment a row goes missing the site would quietly serve the stale one --
 * publishing the wrong shop's phone number is far worse than publishing none.
 *
 * A blank value means "not configured yet". Templates skip blanks rather than
 * printing an empty tag; see site_identity_gaps() for the admin warning.
 */
define('SITE_NAME',    cfg('site_name', ''));
define('SITE_TAGLINE', cfg('site_tagline', ''));
define('SITE_EMAIL',   cfg('site_email', ''));
define('SITE_PHONE',   cfg('site_phone', ''));
define('SITE_ADDRESS', cfg('site_address', ''));
define('SITE_LOGO',    cfg('site_logo', ''));
define('SITE_FAVICON', cfg('site_favicon', ''));

/* ---- Currency ----
 * Admin -> Settings -> Store. Unlike the identity values above these keep a
 * fallback on purpose: they are rendering primitives, not business details.
 * A price with no symbol is simply wrong, and an empty CSS custom property
 * takes the whole stylesheet down with it. */
define('CURRENCY_CODE', 'INR');
define('CURRENCY_SYMBOL', cfg('currency_symbol', '&#8377;'));
define('CURRENCY_DECIMALS', 2);

// ---- Theme (drives the storefront CSS custom properties + Bootstrap vars) ----
define('THEME_PRIMARY',   cfg('theme_primary', '#b98f3e'));   // gold accent
define('THEME_SECONDARY', cfg('theme_secondary', '#1f1b16')); // ink / buttons
define('THEME_FONT_HEADING', cfg('theme_font_heading', 'Cormorant Garamond'));
define('THEME_FONT_BODY',    cfg('theme_font_body', 'Jost'));

if (!function_exists('inr_number_format')) {
    /**
     * Group digits the Indian way: the last three, then pairs.
     * 181280 -> "1,81,280"  (number_format() would give "181,280").
     * Sign and decimal part are preserved.
     */
    function inr_number_format($amount, $decimals = 2) {
        $amount   = (float) $amount;
        $negative = $amount < 0;
        $fixed    = number_format(abs($amount), (int) $decimals, '.', '');
        $parts    = explode('.', $fixed);
        $int      = $parts[0];
        $frac     = isset($parts[1]) ? '.' . $parts[1] : '';

        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest  = substr($int, 0, -3);
            // every group ahead of the last three is a pair, not a triple
            $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int   = $rest . ',' . $last3;
        }

        return ($negative ? '-' : '') . $int . $frac;
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($amount, $decimals = CURRENCY_DECIMALS) {
        return CURRENCY_SYMBOL . inr_number_format($amount, $decimals);
    }
}

// ---- Social links ---- (Admin -> Settings -> Social; blank hides the icon)
define('SOCIAL_FACEBOOK',  cfg('social_facebook', ''));
define('SOCIAL_INSTAGRAM', cfg('social_instagram', ''));
define('SOCIAL_TWITTER',   cfg('social_twitter', ''));
define('SOCIAL_YOUTUBE',   cfg('social_youtube', ''));

// ---- Meta defaults ----
$defaultMetaTitle = SITE_NAME . ' - ' . SITE_TAGLINE;
$defaultMetaDesc  = 'Welcome to ' . SITE_NAME . '. We offer exquisite gold, silver, and diamond jewelry at the best prices. Your satisfaction is our priority.';
