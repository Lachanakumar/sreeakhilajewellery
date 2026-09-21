<?php
/**
 * Admin layout. Set $adminPageTitle and $adminNav (nav key) before calling
 * admin_layout_start(); call admin_layout_end() at the bottom.
 */

require_once __DIR__ . '/../../includes/theme.php';
require_once __DIR__ . '/table.php';

/** Darken/lighten a hex colour by $pct (-1..1). Used for the gold gradient pair. */
function admin_shade($hex, $pct = -0.14) {
    $rgb = array_map('intval', explode(',', hex_to_rgb($hex)));
    foreach ($rgb as &$c) {
        $c = (int) max(0, min(255, $pct < 0 ? $c * (1 + $pct) : $c + (255 - $c) * $pct));
    }
    unset($c);
    return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
}

/**
 * Inline CSS for the controls whose icons are baked into a data: URI and so
 * can't read a CSS custom property — the select caret and the datepicker's
 * calendar glyph. Generated from THEME_PRIMARY so they follow the store theme
 * (Bootstrap's --bs-primary) instead of a hardcoded grey.
 */
function admin_control_icons_css() {
    $hex = '%23' . ltrim(THEME_PRIMARY, '#');

    $caret = "data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'"
        . "%3e%3cpath fill='none' stroke='$hex' stroke-linecap='round' stroke-linejoin='round'"
        . " stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e";

    $cal = "data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16'"
        . " viewBox='0 0 24 24' fill='none' stroke='$hex' stroke-width='2' stroke-linecap='round'"
        . "%3e%3crect x='3' y='4' width='18' height='18' rx='2'/%3e"
        . "%3cpath d='M16 2v4M8 2v4M3 10h18'/%3e%3c/svg%3e";

    return 'select,.form-select,.dp__time select{background-image:url("' . $caret . '");}'
         . '.dp__input{background-image:url("' . $cal . '");}';
}

/**
 * Path to the configured brand asset, or '' when it isn't set / the file is
 * gone (callers then fall back to the lettermark). Both the sidebar and
 * admin/login.php sit one level below the web root, so '../' suits each.
 */
function admin_brand_asset($which = 'logo') {
    $rel = trim((string) ($which === 'icon' ? SITE_FAVICON : SITE_LOGO));
    if ($rel === '' || !is_file(__DIR__ . '/../../' . $rel)) {
        return '';
    }
    return '../' . $rel;
}

/** The sidebar structure — shared by the nav and the command palette. */
function admin_nav_groups() {
    return [
        'Catalog'    => ['products', 'categories', 'brands', 'inventory'],
        'Sales'      => ['orders', 'payments', 'customers', 'coupons', 'reviews'],
        'Engagement' => ['enquiries', 'appointments', 'feedback', 'schemes', 'scheme_cats', 'metal_rates'],
        'Storefront' => ['sliders', 'banners', 'ads'],
        'Insights'   => ['reports', 'settings', 'pay_settings'],
    ];
}

function admin_nav_items() {
    return [
        'dashboard'    => ['Dashboard', 'dashboard.php'],
        'products'     => ['Products', 'products.php'],
        'categories'   => ['Categories', 'categories.php'],
        'brands'       => ['Brands', 'brands.php'],
        'inventory'    => ['Inventory', 'inventory.php'],
        'orders'       => ['Orders', 'orders.php'],
        'payments'     => ['Payments', 'payments.php'],
        'customers'    => ['Customers', 'customers.php'],
        'coupons'      => ['Coupons', 'coupons.php'],
        'reviews'      => ['Reviews', 'reviews.php'],
        'enquiries'    => ['Enquiries', 'enquiries.php'],
        'appointments' => ['Appointments', 'appointments.php'],
        'feedback'     => ['Feedback', 'feedback.php'],
        'schemes'      => ['Schemes', 'schemes.php'],
        'scheme_cats'  => ['Scheme Categories', 'scheme-categories.php'],
        'metal_rates'  => ['Gold / Silver Rates', 'metal-rates.php'],
        'sliders'      => ['Hero Sliders', 'sliders.php'],
        'banners'      => ['Category Banners', 'banners.php'],
        'ads'          => ['Offer Banners', 'ads.php'],
        'reports'      => ['Reports', 'reports.php'],
        'settings'     => ['Settings', 'settings.php'],
        'pay_settings' => ['Payment Gateways', 'payment-settings.php'],
    ];
}

function admin_icon($name) {
    $p = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'products'   => '<path d="M20 7 12 3 4 7v10l8 4 8-4z"/><path d="m4 7 8 4 8-4M12 11v10"/>',
        'categories' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'brands'     => '<path d="m12 2 2.4 7.4H22l-6 4.4 2.3 7.2L12 16.6 5.7 21l2.3-7.2-6-4.4h7.6z"/>',
        'inventory'  => '<path d="M3 9 12 4l9 5v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 21V12h6v9"/>',
        'orders'     => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'customers'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 3-6 6.5-6s6.5 2.4 6.5 6"/><path d="M17 8a3 3 0 0 0 0 6M22 20c0-2.4-1.6-4.4-4-5.2"/>',
        'coupons'    => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M9 8v8"/>',
        'reviews'    => '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.9L12 16.5 6.8 19.2l1-5.9L3.5 9.2l5.9-.9z"/>',
        'reports'    => '<path d="M3 3v18h18"/><path d="m7 14 3-3 3 3 5-6"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 0 1-4 0v-.1A1.6 1.6 0 0 0 7 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 15H3a2 2 0 0 1 0-4h.1A1.6 1.6 0 0 0 4.6 7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.6V3a2 2 0 0 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.6 1.6 0 0 0 20.4 10H21a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'sliders'    => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 12h20M8 19v2M16 19v2"/>',
        'banners'    => '<rect x="3" y="4" width="8" height="16" rx="1"/><rect x="13" y="4" width="8" height="7" rx="1"/><rect x="13" y="13" width="8" height="7" rx="1"/>',
        'ads'        => '<path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 0 1-5.8-1.6"/>',
        'schemes'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M8 15h4"/>',
        'scheme_cats'=> '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'enquiries'  => '<path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5z"/>',
        'appointments'=> '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>',
        'feedback'   => '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.9L12 16.5 6.8 19.2l1-5.9L3.5 9.2l5.9-.9z"/>',
        'metal_rates'=> '<path d="M12 2v20M6 8h9a3 3 0 0 1 0 6H7"/>',
        'store'      => '<path d="M3 9h18l-1.5 11a2 2 0 0 1-2 1.7H6.5a2 2 0 0 1-2-1.7z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'payments'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'pay_settings' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="17" cy="15" r="1.6"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . ($p[$name] ?? '') . '</svg>';
}

function admin_layout_start() {
    global $adminPageTitle, $adminNav;
    $admin  = currentAdmin();
    $title  = $adminPageTitle ?? 'Admin';
    $nav    = $adminNav ?? '';
    $groups = admin_nav_groups();
    $items  = admin_nav_items();

    // which group the current page sits in (for the breadcrumb)
    $crumbGroup = '';
    foreach ($groups as $label => $keys) {
        if (in_array($nav, $keys, true)) { $crumbGroup = $label; break; }
    }

    $renderLink = function ($key) use ($items, $nav) {
        [$label, $href] = $items[$key];
        echo '<li><a href="' . $href . '" data-tip="' . e($label) . '" class="' . ($nav === $key ? 'active' : '') . '">'
            . admin_icon($key) . '<span>' . e($label) . '</span></a></li>';
    };

    // queued notices are handed to JS as toasts (with a no-JS fallback below)
    $flashes = admin_notify_take();
    $GLOBALS['__admin_flashes'] = $flashes;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($title); ?> &middot; <?php echo e(SITE_NAME); ?> Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="../<?php echo e(SITE_FAVICON); ?>">
    <script>
    /* restore theme + sidebar state before first paint */
    (function () {
        try {
            var t = localStorage.getItem('aj-admin-theme');
            if (!t) { t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-theme', t);
            /* keep in sync with applyNavState() in assets/js/admin/ui.js */
            var w = innerWidth, pref = localStorage.getItem('aj-admin-nav');
            if (w > 780 && (w <= 1100 ? pref !== 'open' : pref === 'collapsed')) {
                document.documentElement.classList.add('is-collapsed');
            }
        } catch (e) {}
    })();
    </script>
    <?php echo theme_google_fonts_link(); ?>
    <link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: date('Ymd'); ?>">
    <link rel="stylesheet" href="../assets/css/datepicker.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/datepicker.css') ?: date('Ymd'); ?>">
    <?php
    $ap  = THEME_PRIMARY;
    $ah  = theme_font_stack(THEME_FONT_HEADING, 'serif');
    $ab  = theme_font_stack(THEME_FONT_BODY, 'sans-serif');
    $rgb = hex_to_rgb($ap);
    echo '<style>:root{'
       . '--a-gold:' . e($ap) . ';--a-gold-2:' . e(admin_shade($ap, -0.16)) . ';--a-gold-tint:rgba(' . $rgb . ',.12);'
       . '--bs-primary:' . e($ap) . ';--bs-primary-rgb:' . $rgb . ';'
       . '--serif:' . $ah . ';--font:' . $ab . ';--bs-body-font-family:' . $ab . ';}'
       . admin_control_icons_css() . '</style>';
    ?>
</head>
<body class="admin">
<div class="admin__wrap">

    <aside class="admin__sidebar">
        <div class="admin__brandbar">
            <?php $brandLogo = admin_brand_asset('logo'); $brandIcon = admin_brand_asset('icon'); ?>
            <a class="admin__brand<?php echo $brandLogo ? ' has-logo' : ''; ?><?php echo $brandIcon ? ' has-icon' : ''; ?>" href="dashboard.php">
                <?php if ($brandLogo): ?>
                    <img class="admin__brand--logo" src="<?php echo e($brandLogo); ?>" alt="<?php echo e(SITE_NAME); ?>">
                    <?php if ($brandIcon): ?><img class="admin__brand--icon" src="<?php echo e($brandIcon); ?>" alt=""><?php endif; ?>
                <?php else: ?>
                    <span><?php echo e(SITE_NAME); ?></span>
                <?php endif; ?>
            </a>
            <button class="admin__collapse" type="button" data-nav-collapse aria-label="Collapse sidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
        </div>

        <div class="admin__navwrap">
            <ul class="admin__nav">
                <?php $renderLink('dashboard'); ?>
                <?php foreach ($groups as $groupLabel => $keys): ?>
                    <li class="admin__nav--label"><?php echo e($groupLabel); ?></li>
                    <?php foreach ($keys as $k) { $renderLink($k); } ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php /* no user card here — the topbar avatar menu and ⌘K both carry Log out */ ?>
    </aside>

    <div class="admin__scrim" data-drawer-close></div>

    <div class="admin__main">
        <header class="admin__topbar">
            <button class="icon__btn admin__burger" type="button" data-drawer-open aria-label="Open menu">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>

            <form class="topsearch" method="get" action="products.php" role="search">
                <?php echo admin_ui_icon('search', 16); ?>
                <input type="text" name="q" placeholder="Search products, SKU, category&hellip;" aria-label="Search products"
                       value="<?php echo $nav === 'products' ? e($_GET['q'] ?? '') : ''; ?>">
            </form>

            <div class="topbar__tools">
                <button class="icon__btn" type="button" data-cmdk-open title="Jump to a page (Ctrl K)" aria-label="Jump to a page">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 9h8M8 13h5"/></svg>
                </button>

                <?php $alerts = admin_alerts(); ?>
                <div class="topmenu">
                    <button class="icon__btn topbell" type="button" data-menu-toggle title="Needs attention" aria-label="Needs attention">
                        <?php echo admin_ui_icon('bell', 17); ?>
                        <?php if ($alerts['total']): ?><span class="topbell__dot"><?php echo $alerts['total'] > 99 ? '99+' : $alerts['total']; ?></span><?php endif; ?>
                    </button>
                    <div class="topmenu__pop topmenu__pop--wide" hidden>
                        <div class="topmenu__title">Needs attention</div>
                        <?php if (!$alerts['items']): ?>
                            <div class="topmenu__empty">Nothing waiting on you.</div>
                        <?php else: foreach ($alerts['items'] as $a): ?>
                            <a class="topmenu__row" href="<?php echo e($a['href']); ?>">
                                <span class="topmenu__ic topmenu__ic--<?php echo e($a['tone']); ?>"><?php echo admin_ui_icon($a['icon'], 15); ?></span>
                                <span class="topmenu__txt"><?php echo e($a['label']); ?></span>
                                <b><?php echo (int) $a['count']; ?></b>
                            </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="topmenu">
                    <button class="topuser" type="button" data-menu-toggle aria-label="Account menu">
                        <span class="topuser__av"><?php echo e(strtoupper(substr($admin['name'] ?? 'A', 0, 1))); ?></span>
                        <?php echo admin_ui_icon('chevron', 14); ?>
                    </button>
                    <div class="topmenu__pop" hidden>
                        <div class="topmenu__who">
                            <strong><?php echo e($admin['name'] ?? 'Admin'); ?></strong>
                            <span><?php echo e($admin['email'] ?? ''); ?></span>
                        </div>
                        <button class="topmenu__item" type="button" data-theme-toggle>
                            <span class="i-moon"><?php echo admin_ui_icon('moon', 15); ?></span>
                            <span class="i-sun"><?php echo admin_ui_icon('check', 15); ?></span>
                            Switch theme
                        </button>
                        <a class="topmenu__item" href="settings.php"><?php echo admin_icon('settings'); ?>Store settings</a>
                        <a class="topmenu__item" href="../index.php" target="_blank" rel="noopener"><?php echo admin_ui_icon('external', 15); ?>View storefront</a>
                        <div class="act__sep"></div>
                        <a class="topmenu__item is-danger" href="logout.php"><?php echo admin_ui_icon('logout', 15); ?>Log out</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin__content">
            <noscript>
                <?php foreach ($flashes as $f): ?>
                    <div class="notice notice--<?php echo $f['type']; ?>"><?php echo e($f['msg']); ?></div>
                <?php endforeach; ?>
            </noscript>
            <?php
            echo admin_crumbs(array_filter([
                'Dashboard'  => 'dashboard.php',
                $crumbGroup  => '',
                $title       => '',
            ], fn($k) => $k !== '', ARRAY_FILTER_USE_KEY));
            ?>
    <?php
}

/**
 * Counts behind the topbar bell — the things actually waiting on an admin.
 * Every count is guarded so a missing table can never break the chrome.
 */
function admin_alerts() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $one = function ($sql) {
        try { return (int) getDB()->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
    };
    $defs = [
        ['label' => 'Pending orders',       'icon' => 'cart',     'tone' => 'amber',  'href' => 'orders.php?status=pending',      'sql' => "SELECT COUNT(*) FROM orders WHERE order_status='pending'"],
        ['label' => 'New enquiries',        'icon' => 'chat',     'tone' => 'blue',   'href' => 'enquiries.php?status=new',       'sql' => "SELECT COUNT(*) FROM enquiries WHERE status='new'"],
        ['label' => 'Appointment requests', 'icon' => 'calendar', 'tone' => 'violet', 'href' => 'appointments.php?status=pending','sql' => "SELECT COUNT(*) FROM appointments WHERE status='pending'"],
        ['label' => 'Reviews to moderate',  'icon' => 'star',     'tone' => 'amber',  'href' => 'reviews.php?status=pending',     'sql' => "SELECT COUNT(*) FROM reviews WHERE status='pending'"],
        ['label' => 'New feedback',         'icon' => 'chat',     'tone' => 'green',  'href' => 'feedback.php?status=new',        'sql' => "SELECT COUNT(*) FROM feedback WHERE status='new'"],
        ['label' => 'Low stock products',   'icon' => 'alert',    'tone' => 'red',    'href' => 'inventory.php',                  'sql' => 'SELECT COUNT(*) FROM products WHERE stock_quantity <= 3'],
    ];
    $items = [];
    $total = 0;
    foreach ($defs as $d) {
        $n = $one($d['sql']);
        if ($n > 0) {
            unset($d['sql']);
            $items[] = $d + ['count' => $n];
            $total += $n;
        }
    }
    return $cache = ['items' => $items, 'total' => $total];
}

/**
 * Page header: big serif title + optional subtitle + optional right-side actions HTML.
 */
function admin_page_head($title, $subtitle = '', $actionsHtml = '') {
    echo '<div class="page__head"><div><h1>' . e($title) . '</h1>'
        . ($subtitle ? '<p>' . e($subtitle) . '</p>' : '')
        . '</div>'
        . ($actionsHtml ? '<div class="page__head--actions">' . $actionsHtml . '</div>' : '')
        . '</div>';
}

function admin_layout_end() {
    $groups = admin_nav_groups();
    $items  = admin_nav_items();
    // flash messages were pulled in admin_layout_start(); re-read from the same request scope
    $flashes = $GLOBALS['__admin_flashes'] ?? [];
    ?>
        </main>
    </div>
</div>

<!-- command palette -->
<div class="cmdk" id="cmdk" role="dialog" aria-modal="true" aria-label="Jump to page">
    <div class="cmdk__scrim" data-cmdk-close></div>
    <div class="cmdk__panel">
        <div class="cmdk__search">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="text" id="cmdk-input" placeholder="Search pages and actions&hellip;" autocomplete="off" spellcheck="false">
        </div>
        <div class="cmdk__list" id="cmdk-list">
            <div class="cmdk__group">Overview</div>
            <?php [$l, $h] = $items['dashboard']; ?>
            <button class="cmdk__item" data-href="<?php echo $h; ?>"><?php echo admin_icon('dashboard'); ?><span><?php echo e($l); ?></span></button>
            <?php foreach ($groups as $groupLabel => $keys): ?>
                <div class="cmdk__group"><?php echo e($groupLabel); ?></div>
                <?php foreach ($keys as $k): [$l, $h] = $items[$k]; ?>
                    <button class="cmdk__item" data-href="<?php echo $h; ?>"><?php echo admin_icon($k); ?><span><?php echo e($l); ?></span></button>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <div class="cmdk__group">Actions</div>
            <button class="cmdk__item" data-href="product-add.php"><?php echo admin_icon('products'); ?><span>Add a new product</span></button>
            <button class="cmdk__item" data-href="../index.php" data-blank="1"><?php echo admin_icon('store'); ?><span>View storefront</span><span class="cmdk__hint">new tab</span></button>
            <button class="cmdk__item" data-action="theme"><?php echo admin_icon('settings'); ?><span>Switch light / dark theme</span></button>
            <button class="cmdk__item" data-href="logout.php"><?php echo admin_icon('logout'); ?><span>Log out</span></button>
        </div>
        <div class="cmdk__empty" hidden>Nothing matches that search.</div>
        <div class="cmdk__foot">
            <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate</span>
            <span><kbd>&crarr;</kbd> open</span>
            <span><kbd>Esc</kbd> close</span>
        </div>
    </div>
</div>

<div class="atoasts" id="atoasts"></div>
<?php if ($flashes): ?>
<script id="admin-flashes" type="application/json"><?php echo json_encode($flashes); ?></script>
<?php endif; ?>

<script src="../assets/js/vendor/popper.js"></script>
<script src="../assets/js/vendor/bootstrap.min.js"></script>
<script src="../assets/js/validate.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/validate.js') ?: date('Ymd'); ?>"></script>
<script src="../assets/js/admin/ui.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/admin/ui.js') ?: date('Ymd'); ?>"></script>
<script src="../assets/js/datepicker.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/datepicker.js') ?: date('Ymd'); ?>"></script>
</body>
</html>
    <?php
}
