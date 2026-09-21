<?php
/**
 * Session bootstrap. Must be required BEFORE anything calls session_start(),
 * so include it at the very top of any entry point.
 *
 * PHP's stock session.gc_maxlifetime is 24 minutes. A customer who filled a
 * cart, went to make a cup of tea and came back found their session collected:
 * the CSRF token the page was rendered with no longer matched, so Remove (and
 * anything else guarded by the token) answered "Your session expired. Please
 * try again." while the items were still on screen. Eight hours covers a
 * realistic shopping session.
 */

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 8 * 60 * 60;

    @ini_set('session.gc_maxlifetime', (string) $lifetime);
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_strict_mode', '1');
    // the cart cookie has to survive a browser restart, hence a real lifetime
    // rather than a session cookie
    @ini_set('session.cookie_lifetime', (string) $lifetime);

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        @ini_set('session.cookie_secure', '1');
    }
    @ini_set('session.cookie_samesite', 'Lax');
}
