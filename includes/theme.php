<?php
/**
 * Runtime theming: turns the admin-selected colours + fonts into a <style>
 * block that overrides both the storefront's CSS custom properties and
 * Bootstrap 5's --bs-* variables, so every Bootstrap component follows the
 * site theme automatically.
 */

require_once __DIR__ . '/config.php';

/**
 * Supported font choices. key => [display label, css stack, google family spec | null].
 * The google spec is what goes into fonts.googleapis.com/css2?family=...
 */
function theme_fonts() {
    return [
        // Serif / display (good for headings)
        'Cormorant Garamond' => ['Cormorant Garamond', "'Cormorant Garamond', Georgia, serif", 'Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500'],
        'Playfair Display'   => ['Playfair Display', "'Playfair Display', Georgia, serif", 'Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500'],
        'EB Garamond'        => ['EB Garamond', "'EB Garamond', Georgia, serif", 'EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400'],
        'Marcellus'          => ['Marcellus', "'Marcellus', Georgia, serif", 'Marcellus'],
        'Prata'              => ['Prata', "'Prata', Georgia, serif", 'Prata'],
        // Sans (good for body / UI)
        'Jost'               => ['Jost', "'Jost', sans-serif", 'Jost:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,500'],
        'Poppins'            => ['Poppins', "'Poppins', sans-serif", 'Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400'],
        'Inter'              => ['Inter', "'Inter', sans-serif", 'Inter:wght@300;400;500;600;700'],
        'Lato'               => ['Lato', "'Lato', sans-serif", 'Lato:ital,wght@0,300;0,400;0,700;1,400'],
        'Nunito Sans'        => ['Nunito Sans', "'Nunito Sans', sans-serif", 'Nunito+Sans:ital,wght@0,300;0,400;0,600;0,700;1,400'],
        'Mulish'             => ['Mulish', "'Mulish', sans-serif", 'Mulish:ital,wght@0,300;0,400;0,600;0,700;1,400'],
        'System UI'          => ['System default', 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', null],
    ];
}

function theme_font_stack($name, $fallback = "sans-serif") {
    $fonts = theme_fonts();
    return isset($fonts[$name]) ? $fonts[$name][1] : "'" . $name . "', " . $fallback;
}

/** <link> tag for the Google fonts needed by the current theme (+ the built-ins). */
function theme_google_fonts_link() {
    $fonts = theme_fonts();
    $families = [];
    foreach ([THEME_FONT_HEADING, THEME_FONT_BODY, 'Cormorant Garamond', 'Jost'] as $name) {
        if (isset($fonts[$name]) && $fonts[$name][2] !== null) {
            $families[$fonts[$name][2]] = true;
        }
    }
    if (!$families) {
        return '';
    }
    $url = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', array_keys($families)) . '&display=swap';
    return '<link href="' . htmlspecialchars($url) . '" rel="stylesheet">';
}

function hex_to_rgb($hex) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '185, 143, 62';
    }
    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
}

/** The <style> block to drop in <head> AFTER theme-refresh.css. */
function theme_style_block() {
    $primary   = THEME_PRIMARY;
    $secondary = THEME_SECONDARY;
    $headStack = theme_font_stack(THEME_FONT_HEADING, 'serif');
    $bodyStack = theme_font_stack(THEME_FONT_BODY, 'sans-serif');
    $primaryRgb = hex_to_rgb($primary);
    $secondaryRgb = hex_to_rgb($secondary);

    ob_start();
    ?>
<style id="site-theme">
:root{
    /* storefront theme */
    --primary-color: <?php echo $primary; ?>;
    --primary-deep: <?php echo $primary; ?>;
    --secondary-color: <?php echo $secondary; ?>;
    --font-serif: <?php echo $headStack; ?>;
    --font-jost: <?php echo $bodyStack; ?>;
    /* Bootstrap 5 */
    --bs-primary: <?php echo $primary; ?>;
    --bs-primary-rgb: <?php echo $primaryRgb; ?>;
    --bs-secondary: <?php echo $secondary; ?>;
    --bs-secondary-rgb: <?php echo $secondaryRgb; ?>;
    --bs-link-color: <?php echo $primary; ?>;
    --bs-link-hover-color: <?php echo $secondary; ?>;
    --bs-body-font-family: <?php echo $bodyStack; ?>;
    --bs-font-sans-serif: <?php echo $bodyStack; ?>;
}
body { font-family: var(--bs-body-font-family); }
h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6 { font-family: var(--font-serif); }
.btn-primary{ --bs-btn-bg: <?php echo $primary; ?>; --bs-btn-border-color: <?php echo $primary; ?>; --bs-btn-hover-bg: <?php echo $secondary; ?>; --bs-btn-hover-border-color: <?php echo $secondary; ?>; --bs-btn-active-bg: <?php echo $secondary; ?>; }
.btn-outline-primary{ --bs-btn-color: <?php echo $primary; ?>; --bs-btn-border-color: <?php echo $primary; ?>; --bs-btn-hover-bg: <?php echo $primary; ?>; --bs-btn-hover-border-color: <?php echo $primary; ?>; }
.text-primary{ color: <?php echo $primary; ?> !important; }
.bg-primary{ background-color: <?php echo $primary; ?> !important; }
a{ color: <?php echo $primary; ?>; }
</style>
    <?php
    return ob_get_clean();
}
