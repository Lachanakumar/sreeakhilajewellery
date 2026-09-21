<?php
/**
 * Shared helper functions for the storefront + admin.
 */

require_once __DIR__ . '/session.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/database.php';

/* ==================== OUTPUT / INPUT ==================== */

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Root-relative URL for an asset.
 *
 * Needed whenever a path is fed into a CSS url() through a custom property: a
 * relative url() inside a var() is resolved against the STYLESHEET that uses the
 * var (assets/css/...), not the page that declared it, so a page-relative path
 * 404s. Returns a path that is correct from anywhere.
 */
function asset_url($path) {
    static $base = null;
    $path = (string) $path;
    if ($path === '' || preg_match('#^(https?:)?//#', $path) || strpos($path, 'data:') === 0) {
        return $path;
    }
    if ($base === null) {
        $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $base = implode('/', array_map('rawurlencode', explode('/', $dir)));
    }
    return $base . '/' . ltrim($path, '/');
}

function sanitize($value) {
    return trim(filter_var((string) $value, FILTER_UNSAFE_RAW));
}

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . substr(md5(uniqid('', true)), 0, 8);
}

/**
 * Decide what slug to store when a record is saved.
 *
 * The edit forms arrive with the existing slug already in the box, so the old
 * `trim($_POST['slug']) ?: slugify($name)` always kept it — renaming a
 * category from "Necklaces" to "Chains" left the public URL on ?slug=necklaces.
 *
 * A slug IS the public URL, so regenerating it every time would throw away a
 * slug an admin had deliberately typed. So compare what came back with what the
 * OLD name would have produced: if they match, the slug was never customised
 * and it follows the rename; if it differs, the admin chose it and it stands.
 *
 * $prefix is for nested categories, whose slug is "parent-child".
 */
function resolve_slug($posted, $name, $currentSlug = '', $currentName = '', $prefix = '', $currentPrefix = null) {
    $posted = trim((string) $posted);
    $build = function ($n, $p) {
        return slugify(($p !== '' ? $p . '-' : '') . $n);
    };
    // a sub-category is "parent-child", and the parent can be reassigned in the
    // same save, so the old slug has to be judged against the OLD parent
    if ($currentPrefix === null) { $currentPrefix = $prefix; }

    // blank box: always derive from the name (what the hint promises)
    if ($posted === '') {
        return $build($name, $prefix);
    }
    // unchanged, and exactly what the old name generated -> never customised
    if ($currentName !== '' && $posted === (string) $currentSlug
        && $posted === $build($currentName, $currentPrefix)) {
        return $build($name, $prefix);
    }
    // hand-written: leave it alone
    return $posted;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function base_url($path = '') {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // If running inside /admin, step up one level for storefront links.
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    $dir = $dir === '' ? '' : $dir;
    return $dir . '/' . ltrim($path, '/');
}

/* ==================== CSRF ==================== */

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && hash_equals(csrf_token(), $token);
}

/**
 * For AJAX endpoints: verify CSRF or emit a JSON error and stop.
 */
function csrf_verify_or_fail() {
    if (!csrf_verify()) {
        json_response(false, 'Invalid or expired security token. Please refresh the page.', [], 419);
    }
}

/* ==================== JSON ==================== */

function json_response($success, $message = '', $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => (bool) $success,
        'message' => $message,
        'data'    => $data,
    ]);
    exit;
}

/* ==================== FLASH MESSAGES ==================== */

function flash_set($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

function flash_get($key) {
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }
    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $message;
}

function flash_has($key) {
    return isset($_SESSION['flash'][$key]);
}

/* ==================== SETTINGS ==================== */

function get_setting($key, $default = null) {
    // config.php loads every setting into this global once per request.
    if (isset($GLOBALS['__site_settings']) && is_array($GLOBALS['__site_settings'])) {
        $v = $GLOBALS['__site_settings'][$key] ?? null;
        return ($v === null) ? $default : $v;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (getDB()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

/* ==================== SITE IDENTITY ==================== */

/**
 * Which identity settings are still empty.
 *
 * config.php carries no hardcoded company details on purpose, so an unset row
 * shows up as a blank on the storefront rather than as somebody else's data.
 * Admin -> Settings uses this to say plainly what is missing.
 *
 * @return array<string, string>  setting_key => human label
 */
function site_identity_gaps() {
    $required = [
        'site_name'    => 'Company / Site Name',
        'site_email'   => 'Contact Email',
        'site_phone'   => 'Contact Phone',
        'site_address' => 'Address',
        'site_logo'    => 'Logo',
    ];
    $gaps = [];
    foreach ($required as $key => $label) {
        if (trim((string) get_setting($key, '')) === '') {
            $gaps[$key] = $label;
        }
    }
    return $gaps;
}

/* ==================== CONTACT ==================== */

/**
 * SITE_PHONE may hold several numbers ("+91 82484 00899, +91 63834 04801").
 * Split them so each one can be rendered as its own tel: link — a single link
 * carrying both numbers hands the whole string to the dialler, which is what
 * put two numbers on the dial pad when only one was tapped.
 *
 * @return array<int, array{display: string, href: string}>
 */
function site_phone_numbers($raw = null) {
    $raw = $raw === null ? SITE_PHONE : $raw;
    $out = [];
    foreach (preg_split('/\s*[,;\/]\s*|\s{2,}|\s+\|\s+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $one) {
        $one = trim($one);
        if ($one === '') {
            continue;
        }
        // keep a single leading + and the digits; drop spaces, dashes, brackets
        $digits = preg_replace('/(?!^\+)\D/', '', $one);
        if (preg_replace('/\D/', '', $digits) === '') {
            continue;
        }
        $out[] = ['display' => $one, 'href' => 'tel:' . $digits];
    }
    return $out;
}

/**
 * Every number as its own <a href="tel:"> link, joined by $separator.
 * Use this anywhere the raw SITE_PHONE string used to be dropped into a
 * tel: href — that fed the dialler all of the numbers at once.
 */
function site_phone_links($separator = ' / ', $attrs = '') {
    $parts = [];
    foreach (site_phone_numbers() as $ph) {
        $parts[] = '<a href="' . e($ph['href']) . '"' . ($attrs ? ' ' . $attrs : '') . '>'
                 . e($ph['display']) . '</a>';
    }
    return implode($separator, $parts);
}

/** The first number only — for places with room for just one. */
function site_phone_primary() {
    $all = site_phone_numbers();
    return $all ? $all[0] : ['display' => SITE_PHONE, 'href' => 'tel:'];
}

/* ==================== MONEY ==================== */

if (!function_exists('formatPrice')) {
    function formatPrice($amount, $decimals = 2) {
        $symbol = get_setting('currency_symbol', '&#8377;');
        return $symbol . inr_number_format($amount, $decimals);
    }
}

function money($amount) {
    return formatPrice($amount, 2);
}

/* ==================== PAGINATION ==================== */

function paginate($totalItems, $perPage, $currentPage, $baseUrl) {
    $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
    $currentPage = min(max(1, (int) $currentPage), $totalPages);
    return [
        'total_items'  => (int) $totalItems,
        'per_page'     => (int) $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => ($currentPage - 1) * $perPage,
        'base_url'     => $baseUrl,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}

function pagination_url($baseUrl, $page) {
    $sep = strpos($baseUrl, '?') === false ? '?' : '&';
    return $baseUrl . $sep . 'page=' . (int) $page;
}

/* ==================== ORDER NUMBER ==================== */

function generateOrderNumber() {
    return 'AJ' . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/* ==================== UPLOAD VALIDATION ==================== */

/**
 * Validate an uploaded image. Never trusts the extension alone.
 * Returns [true, $extension] or [false, $errorMessage].
 */
function validate_uploaded_image(array $file, $maxBytes = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, 'Invalid upload.'];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return [false, 'The uploaded file is too large.'];
        case UPLOAD_ERR_NO_FILE:
            return [false, 'No file was uploaded.'];
        default:
            return [false, 'Upload failed (code ' . $file['error'] . ').'];
    }

    if ($file['size'] > $maxBytes) {
        return [false, 'Image must be ' . round($maxBytes / 1048576) . 'MB or smaller.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, 'Invalid upload source.'];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return [false, 'Only JPG, PNG and WEBP images are allowed.'];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return [false, 'The file is not a valid image.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extWhitelist = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $extWhitelist, true)) {
        return [false, 'Invalid file extension.'];
    }

    return [true, $allowed[$mime]];
}

/**
 * Whitelist-sanitise rich text produced by the admin editor before rendering it
 * on the storefront. Anything not on the list (script/style/iframe/event
 * handlers/javascript: URLs) is stripped, so admin-authored HTML is safe to
 * print unescaped. Plain-text values are escaped and nl2br'd as before, so old
 * descriptions written before the editor existed still render correctly.
 */
function sanitize_html($html) {
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    // no markup at all -> treat it as the plain text it is
    if (strip_tags($html) === $html) {
        return nl2br(e($html));
    }

    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'span' => [], 'div' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="aj-root">' . $html . '</div>', LIBXML_NONET);
    libxml_clear_errors();

    $root = $doc->getElementById('aj-root');
    if (!$root) {
        return nl2br(e(strip_tags($html)));
    }

    $walk = function (DOMNode $node) use (&$walk, $allowed) {
        // iterate over a snapshot: the list is live while we remove nodes
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (!($child instanceof DOMElement)) {
                $node->removeChild($child);   // comments, PIs, CDATA
                continue;
            }
            $tag = strtolower($child->nodeName);
            // these carry code, not prose — drop them and everything inside
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'], true)) {
                $node->removeChild($child);
                continue;
            }
            if (!isset($allowed[$tag])) {
                // keep the readable text, drop the tag itself
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $an = strtolower($attr->nodeName);
                if (!in_array($an, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->nodeName);
                    continue;
                }
                if ($an === 'href' || $an === 'src') {
                    $url = trim($attr->nodeValue);
                    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                    $ok = $scheme === '' || in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
                    if (!$ok || stripos(preg_replace('/\s+/', '', $url), 'javascript:') === 0) {
                        $child->removeAttribute($attr->nodeName);
                    }
                }
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
            $walk($child);
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function unique_filename($ext) {
    return bin2hex(random_bytes(16)) . '.' . strtolower($ext);
}

function products_upload_dir() {
    return __DIR__ . '/../uploads/products';
}

/* ==================== MISC ==================== */

function old($key, $default = '') {
    return isset($_SESSION['old'][$key]) ? $_SESSION['old'][$key] : $default;
}

function set_old(array $data) {
    unset($data['csrf_token'], $data['password'], $data['password_confirm']);
    $_SESSION['old'] = $data;
}

function clear_old() {
    unset($_SESSION['old']);
}

function star_icons($rating, $max = 5) {
    $rating = (float) $rating;
    $html = '';
    for ($i = 1; $i <= $max; $i++) {
        $fill = $i <= round($rating) ? 'currentColor' : '#d8d8d8';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 10.105 9.732" style="margin-right:2px"><path d="M9.837,3.5,6.73,3.039,5.338.179a.335.335,0,0,0-.571,0L3.375,3.039.268,3.5a.3.3,0,0,0-.178.514L2.347,6.242,1.813,9.4a.314.314,0,0,0,.464.316L5.052,8.232,7.827,9.712A.314.314,0,0,0,8.292,9.4L7.758,6.242l2.257-2.231A.3.3,0,0,0,9.837,3.5Z" transform="translate(0 -0.018)" fill="' . $fill . '"></path></svg>';
    }
    return $html;
}
