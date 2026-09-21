<?php
require_once __DIR__ . '/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/cart-functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/engagement.php';
require_once __DIR__ . '/social.php';

/** asset URL with a cache-busting ?v=mtime so CSS/JS changes always land. */
function asset($path) {
    $abs = __DIR__ . '/../' . ltrim($path, '/');
    $v = is_file($abs) ? filemtime($abs) : date('Ymd');
    return $path . '?v=' . $v;
}
record_visit();
$cartCount = getCartCount();
$wishlistCount = getWishlistCount();
$cartItems = getCartItems();
$cartTotal = getCartTotal();
$navCategoryTree = getCategoryTree();
$navSchemeCategories = getSchemeCategories();
$navMetalRates = get_setting('show_metal_rates', '1') === '1' ? getLatestRates() : [];
$quickContact = quick_contact();
$digiGold = digigold_config();
$currentUser = currentUser();
$flashSuccess = flash_get('success');
$flashError = flash_get('error');

// Default meta values if not set by the page
if (!isset($pageMetaTitle)) $pageMetaTitle = SITE_NAME;
if (!isset($pageMetaDesc)) $pageMetaDesc = $defaultMetaDesc;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($pageMetaTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageMetaDesc); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <meta name="base-url" content="<?php echo htmlspecialchars(rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/')); ?>">
    <?php if (SITE_FAVICON !== ''): ?><link rel="shortcut icon" href="<?php echo e(SITE_FAVICON); ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/css/plugins/swiper-bundle.min.css">
    <link rel="stylesheet" href="assets/css/plugins/glightbox.min.css">
    <?php echo theme_google_fonts_link(); ?>
    <link rel="stylesheet" href="assets/css/vendor/bootstrap.min.css">
    <?php /* through asset() like the rest: a plain path is cached by the browser
             across deploys, so a CSS change only showed up after a hard refresh */ ?>
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/theme-refresh.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/datepicker.css'); ?>">
    <?php echo theme_style_block(); ?>
    <?php
    // the select caret and the datepicker's calendar glyph are data: URIs, so
    // they can't read a CSS variable — tint them from THEME_PRIMARY here
    $dpHex = '%23' . ltrim(THEME_PRIMARY, '#');
    echo '<style>'
       . '.main__content_wrapper select,.form-select,.dp__time select{background-image:url("data:image/svg+xml,'
       . "%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none'"
       . " stroke='$dpHex' stroke-linecap='round' stroke-linejoin='round' stroke-width='2'"
       . " d='m2 5 6 6 6-6'/%3e%3c/svg%3e" . '");}'
       . '.dp__input{background-image:url("data:image/svg+xml,'
       . "%3csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'"
       . " fill='none' stroke='$dpHex' stroke-width='2' stroke-linecap='round'"
       . "%3e%3crect x='3' y='4' width='18' height='18' rx='2'/%3e"
       . "%3cpath d='M16 2v4M8 2v4M3 10h18'/%3e%3c/svg%3e" . '");}'
       . '</style>';
    ?>
    <?php foreach ((array) ($pageStyles ?? []) as $pageStyleHref): ?>
        <link rel="stylesheet" href="<?php echo asset($pageStyleHref); ?>">
    <?php endforeach; ?>
    <style>
        .header__shipping--text {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .header__shipping--text__icon {
            flex-shrink: 0;
        }

        .header__shipping--text a {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .main__header { position: relative; }

        @media only screen and (min-width: 992px) {
            .main__header--inner {
                display: grid !important;
                grid-template-columns: auto minmax(0, 1fr) auto;
                grid-template-rows: auto;
                column-gap: 4rem;
                align-items: center;
                /* the nav link's own vertical padding sets the header height, so the
                   whole row stays hoverable right down to the mega-menu edge */
                padding-top: 0;
                padding-bottom: 0;
            }
            .offcanvas__header--menu__open { display: none !important; }
            /* every zone is pinned to row 1 — the menu sits before the logo in the
               HTML, so without this the grid cursor pushes the logo to a 2nd row */
            .main__logo      { grid-area: 1 / 1 / 2 / 2; justify-self: start;  text-align: left; margin: 0; }
            .header__menu    { grid-area: 1 / 2 / 2 / 3; justify-self: center; min-width: 0; }
            .header__account { grid-area: 1 / 3 / 2 / 4; justify-self: end; }
            .main__logo--title { margin: 0; line-height: 0; }
            .header__menu--navigation > ul { flex-wrap: nowrap; align-items: center; justify-content: center; }
            .header__menu--items { margin: 0 1.6rem; }
            .header__menu--items:first-child { margin-left: 0; }
            .header__menu--items:last-child { margin-right: 0; }
            .header__account > ul { gap: 1.6rem; }
        }
        @media only screen and (min-width: 1400px) {
            .header__menu--items { margin: 0 2rem; }
            .header__menu--items:first-child { margin-left: 0; }
            .header__menu--items:last-child { margin-right: 0; }
        }

        /* Nav links */
        .header__menu--link {
            display: inline-flex; align-items: center;
            font-family: var(--font-jost, "Jost", sans-serif);
            font-size: 1.3rem; font-weight: 500; letter-spacing: 1.6px;
            text-transform: uppercase; color: var(--secondary-color, #1c1a16);
            padding: 1.5rem 0; white-space: nowrap;
        }
        .header__menu--items { position: relative; }
        .header__menu--link::after {
            content: ""; position: absolute; left: 0; bottom: 1.05rem;
            width: 0; height: 1px; background: var(--primary-color, #b0863b); transition: width .28s ease;
            top: 54px;
        }
        .header__menu--items:hover > .header__menu--link,
        .header__menu--link:hover { color: var(--primary-color, #b0863b); }
        .header__menu--items:hover > .header__menu--link::after { width: 100%; }
        .header__menu--link svg { opacity: .55; }

        /* Simple dropdown (Schemes) */
        .header__menu--items.has__submenu { position: relative; }
        .header__menu--items .header__submenu {
            position: absolute; top: 100%; left: -1.6rem; min-width: 230px;
            background: #fff; box-shadow: 0 18px 44px rgba(28,26,22,.16);
            padding: 1.2rem 0; margin: 0; list-style: none; z-index: 999;
            opacity: 0; visibility: hidden; transform: translateY(12px);
            transition: opacity .22s ease, transform .22s ease, visibility .22s;
            border-top: 2px solid var(--primary-color, #b0863b);
        }
        .header__menu--items.has__submenu:hover > .header__submenu { opacity: 1; visibility: visible; transform: translateY(0); }
        .header__submenu--group > a {
            display: block; padding: .55rem 2rem;
            font-family: var(--font-jost, sans-serif); font-size: 1.3rem; color: #6f665a;
        }
        .header__submenu--group > a:hover { color: var(--primary-color, #b0863b); }

        /* Mega menu (Shop) */
        .header__menu--items.has__megamenu { position: static; }
        .megamenu {
            /* The offset parent is .main__header--inner (position__relative), not the
               header, so left/right:0 would inset the panel by the flex container.
               left:50% + 100vw/-50vw makes it full-bleed regardless of offset parent.
               No border-top here: .main__header already draws one, and two 1px lines
               stack into a visible double rule. */
            position: absolute; top: 100%;
            left: 50%; width: 100vw; margin-left: -50vw;
            background: #fff;
            box-shadow: 0 30px 60px -18px rgba(28,26,22,.25);
            opacity: 0; visibility: hidden; transform: translateY(10px);
            transition: opacity .25s ease, transform .25s ease, visibility .25s;
            z-index: 999;
        }
        .header__menu--items.has__megamenu:hover > .megamenu { opacity: 1; visibility: visible; transform: translateY(0); }
        .megamenu .container { padding-top: 2.6rem; padding-bottom: 2.6rem; }
        .megamenu__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 2.4rem 3rem; align-items: start; }
        .megamenu__promo { grid-column: span 1; }
        .megamenu__col--title {
            display: block; margin-bottom: 1.2rem; padding-bottom: .8rem;
            border-bottom: 1px solid var(--border-color, #e6ddcc);
            font-family: var(--font-serif, Georgia, serif); font-size: 1.7rem; font-weight: 500;
            color: var(--secondary-color, #1c1a16); letter-spacing: .3px;
        }
        .megamenu__col ul { list-style: none; margin: 0; padding: 0; }
        .megamenu__col li { margin-bottom: .1rem; }
        .megamenu__col li a { display: block; padding: .5rem 0; font-size: 1.32rem; color: #6f665a; }
        .megamenu__col li a:hover { color: var(--primary-color, #b0863b); padding-left: .5rem; transition: color .15s, padding .15s; }
        .megamenu__all { color: var(--primary-deep, #8f6c2c) !important; font-weight: 600; font-size: 1.2rem !important; text-transform: uppercase; letter-spacing: 1px; margin-top: .6rem; }
        /* stretch so the promo fills the panel instead of leaving dead space below it */
        .megamenu__promo { position: relative; display: block; border-radius: 2px; overflow: hidden; min-height: 200px; align-self: stretch; }
        .megamenu__promo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .megamenu__promo::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.1), rgba(0,0,0,.62)); }
        .megamenu__promo--label, .megamenu__promo--cta { position: relative; z-index: 2; color: #fff; display: block; padding: 0 2rem; }
        .megamenu__promo { display: flex; flex-direction: column; justify-content: flex-end; }
        .megamenu__promo--label { font-family: var(--font-serif, Georgia, serif); font-size: 2rem; padding-top: 0; }
        .megamenu__promo--cta { text-transform: uppercase; letter-spacing: 1.6px; font-size: 1.1rem; font-weight: 600; padding-bottom: 2rem; padding-top: .6rem; }
        @media (max-width: 1199px) {
            .megamenu__grid { grid-template-columns: repeat(3, 1fr); }
            .megamenu__promo { display: none; }
        }

        /* Offcanvas accordion */
        .offcanvas__menu--refined .offcanvas__menu_row { display: flex; align-items: center; justify-content: space-between; }
        .offcanvas__menu--refined .offcanvas__expand {
            width: 40px; height: 40px; border: 0; background: transparent; cursor: pointer; position: relative; flex-shrink: 0;
        }
        .offcanvas__menu--refined .offcanvas__expand::before,
        .offcanvas__menu--refined .offcanvas__expand::after {
            content: ""; position: absolute; left: 50%; top: 50%; background: currentColor; transition: transform .2s ease;
        }
        .offcanvas__menu--refined .offcanvas__expand::before { width: 12px; height: 1.5px; transform: translate(-50%, -50%); }
        .offcanvas__menu--refined .offcanvas__expand::after { width: 1.5px; height: 12px; transform: translate(-50%, -50%); }
        .offcanvas__menu--refined .is-open > .offcanvas__menu_row .offcanvas__expand::after { transform: translate(-50%, -50%) scaleY(0); }
        .offcanvas__menu--refined .offcanvas__submenu {
            list-style: none; margin: 0; padding: 0 0 0 1.4rem; max-height: 0; overflow: hidden; transition: max-height .3s ease;
        }
        .offcanvas__menu--refined .is-open > .offcanvas__submenu { max-height: 1600px; }
        .offcanvas__menu--refined .offcanvas__submenu--2 { padding-left: 1.6rem; }
        .offcanvas__menu--refined .offcanvas__submenu .offcanvas__menu_item { font-size: 1.4rem; padding: .55rem 0; }
        .offcanvas__menu--refined .offcanvas__submenu--2 .offcanvas__menu_item { font-size: 1.32rem; color: #8a8175; }

        /* Account dropdown */
        .header__account--dropdown { position: relative; }
        .header__account--dropdown__list {
            position: absolute; right: 0; top: 100%; min-width: 190px; background: #fff;
            box-shadow: 0 18px 44px rgba(28,26,22,.16); padding: 1rem 0; list-style: none; margin: 0;
            opacity: 0; visibility: hidden; transform: translateY(12px); transition: all .22s ease; z-index: 999;
            border-top: 2px solid var(--primary-color, #b0863b);
        }
        .header__account--dropdown:hover .header__account--dropdown__list { opacity: 1; visibility: visible; transform: translateY(0); }
        .header__account--dropdown__list li a { display: block; padding: .6rem 2rem; color: #5b544a; font-size: 1.28rem; }
        .header__account--dropdown__list li a:hover { color: var(--primary-color, #b0863b); }

        .flash__bar { padding: 1.1rem 1.6rem; text-align: center; font-size: 1.35rem; color: #fff; }
        .flash__bar.is-success { background: #1f6d4a; }
        .flash__bar.is-error { background: #b23b30; }
        .offcanvas__submenu { list-style: none; padding-left: 1.5rem; }
        .offcanvas__submenu a { font-size: 1.3rem; }
    </style>
</head>

<body>

    <!-- Start preloader -->
    <div id="preloader">
        <div id="ctn-preloader" class="ctn-preloader">
            <div class="animation-preloader">
                <div class="spinner"></div>
                <div class="txt-loading">
                    <span data-text-preloader="L" class="letters-loading">L</span>
                    <span data-text-preloader="O" class="letters-loading">O</span>
                    <span data-text-preloader="A" class="letters-loading">A</span>
                    <span data-text-preloader="D" class="letters-loading">D</span>
                    <span data-text-preloader="I" class="letters-loading">I</span>
                    <span data-text-preloader="N" class="letters-loading">N</span>
                    <span data-text-preloader="G" class="letters-loading">G</span>
                </div>
            </div>
            <div class="loader-section section-left"></div>
            <div class="loader-section section-right"></div>
        </div>
    </div>
    <!-- End preloader -->

    <!-- Start header area -->
    <header class="header__section color-scheme-2">
        <div class="header__topbar bg__secondary header__sticky--none">
            <div class="container-fluid">
                <div class="header__topbar--inner d-flex align-items-center justify-content-between">
                    <div class="header__shipping">
                        <?php if (!empty($navMetalRates)): ?>
                            <?php
                            $rateItems = [];
                            foreach ($navMetalRates as $r) {
                                $rateItems[] = '<span class="header__rates--item"><b>' . e(str_replace(' Gold', '', $r['label'])) . '</b> ' . formatPrice($r['rate_per_gram'], 0) . '/' . e($r['unit']) . '</span>';
                            }
                            $rateGroup = '<span class="header__rates--label">Today\'s Rate</span>' . implode('<span class="header__rates--sep">&bull;</span>', $rateItems)
                                . '<a class="header__rates--link" href="digigold.php">Full rates &rsaquo;</a>';
                            ?>
                            <div class="header__rates">
                                <div class="header__rates--track">
                                    <?php echo $rateGroup; ?><?php echo $rateGroup; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (SITE_ADDRESS !== ''): ?>
                            <?php
                            /* The address used to live in the rates ticker's else-branch, so
                               switching the ticker on hid the shop address on every screen
                               size. It is rendered on its own now. CSS decides where it is
                               shown: always on mobile (its own row under the ticker), and on
                               desktop only when no ticker is competing for the same strip.

                               SITE_ADDRESS is stored with line breaks for the footer; collapse
                               them so the topbar gets one flowing line. */
                            ?>
                            <ul class="header__topbar--list header__addr<?php echo empty($navMetalRates) ? ' header__addr--solo' : ''; ?>">
                                <li class="header__shipping--text text-white">
                                    <svg class="header__shipping--text__icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <span><?php echo e(trim(preg_replace('/\s+/', ' ', SITE_ADDRESS))); ?></span>
                                </li>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div class="language__currency">
                        <ul class="d-flex align-items-center header__topbar--list">
                            <?php foreach (site_phone_numbers() as $phIndex => $ph): ?>
                                <li class="header__shipping--text text-white">
                                    <a href="<?php echo e($ph['href']); ?>" class="text-white">
                                        <?php if ($phIndex === 0): ?>
                                            <svg class="header__shipping--text__icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                            </svg>
                                        <?php endif; ?>
                                        <?php echo e($ph['display']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <?php if (SITE_EMAIL !== ''): ?>
                            <li class="header__shipping--text text-white d-none d-md-block d-lg-block">
                                <a href="mailto:<?php echo e(SITE_EMAIL); ?>" class="text-white">
                                    <svg class="header__shipping--text__icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <?php echo e(SITE_EMAIL); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="main__header header__sticky">
            <div class="container-fluid">
                <div class="main__header--inner position__relative d-flex justify-content-between align-items-center">
                    <div class="offcanvas__header--menu__open">
                        <a class="offcanvas__header--menu__open--btn" href="javascript:void(0)" data-offcanvas>
                            <svg xmlns="http://www.w3.org/2000/svg" class="ionicon offcanvas__header--menu__open--svg" viewBox="0 0 512 512">
                                <path fill="currentColor" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="32" d="M80 160h352M80 256h352M80 352h352" />
                            </svg>
                            <span class="visually-hidden">Menu Open</span>
                        </a>
                    </div>
                    <div class="header__menu d-none d-lg-block">
                        <nav class="header__menu--navigation">
                            <ul class="d-flex">
                                <li class="header__menu--items"><a class="header__menu--link" href="index.php">Home</a></li>
                                <li class="header__menu--items"><a class="header__menu--link" href="about.php">About Us</a></li>
                                <li class="header__menu--items has__megamenu">
                                    <a class="header__menu--link" href="products.php">Shop
                                        <svg width="10" height="7" viewBox="0 0 10.355 6.394" style="margin-left:4px"><path d="M15.138,8.59l-3.961,3.952L7.217,8.59,6,9.807l5.178,5.178,5.178-5.178Z" transform="translate(-6 -8.59)" fill="currentColor"></path></svg>
                                    </a>
                                    <div class="megamenu">
                                        <div class="container">
                                            <div class="megamenu__grid">
                                                <?php foreach ($navCategoryTree as $mainCat): ?>
                                                    <div class="megamenu__col">
                                                        <a class="megamenu__col--title" href="category.php?slug=<?php echo htmlspecialchars($mainCat['slug']); ?>"><?php echo htmlspecialchars($mainCat['name']); ?></a>
                                                        <ul>
                                                            <?php foreach ($mainCat['children'] as $subCat): ?>
                                                                <li><a href="category.php?slug=<?php echo htmlspecialchars($subCat['slug']); ?>"><?php echo htmlspecialchars($subCat['name']); ?></a></li>
                                                            <?php endforeach; ?>
                                                            <li><a class="megamenu__all" href="category.php?slug=<?php echo htmlspecialchars($mainCat['slug']); ?>">All <?php echo htmlspecialchars($mainCat['name']); ?> &rarr;</a></li>
                                                        </ul>
                                                    </div>
                                                <?php endforeach; ?>
                                                <a class="megamenu__promo" href="products.php?on_sale=1">
                                                    <img src="assets/img/banner/traditional.jpg" alt="Offers">
                                                    <span class="megamenu__promo--label">Festive Offers</span>
                                                    <span class="megamenu__promo--cta">Shop Sale &rarr;</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php if (!empty($navSchemeCategories)): ?>
                                    <li class="header__menu--items has__submenu">
                                        <a class="header__menu--link" href="schemes.php">Schemes
                                            <svg width="10" height="7" viewBox="0 0 10.355 6.394" style="margin-left:4px"><path d="M15.138,8.59l-3.961,3.952L7.217,8.59,6,9.807l5.178,5.178,5.178-5.178Z" transform="translate(-6 -8.59)" fill="currentColor"></path></svg>
                                        </a>
                                        <ul class="header__submenu">
                                            <li class="header__submenu--group"><a href="schemes.php">All Schemes</a></li>
                                            <?php foreach ($navSchemeCategories as $sc): ?>
                                                <li class="header__submenu--group"><a href="schemes.php?category=<?php echo htmlspecialchars($sc['slug']); ?>"><?php echo htmlspecialchars($sc['name']); ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endif; ?>
                                <li class="header__menu--items"><a class="header__menu--link" href="digigold.php">Digi Gold</a></li>
                                <li class="header__menu--items"><a class="header__menu--link" href="contact.php">Contact</a></li>
                            </ul>
                        </nav>
                    </div>
                    <div class="main__logo">
                        <h1 class="main__logo--title"><a class="main__logo--link" href="index.php">
                            <?php if (SITE_LOGO !== ''): ?>
                                <img class="main__logo--img" src="<?php echo e(SITE_LOGO); ?>" alt="<?php echo e(SITE_NAME); ?>">
                            <?php else: ?>
                                <span class="main__logo--text"><?php echo e(SITE_NAME !== '' ? SITE_NAME : 'Set your site name and logo in Admin > Settings'); ?></span>
                            <?php endif; ?>
                        </a></h1>
                    </div>
                    <div class="header__account header__account2">
                        <ul class="d-flex">
                            <li class="header__account--items header__account2--items d-none d-lg-block header__account--dropdown">
                                <a class="header__account--btn" href="<?php echo $currentUser ? 'account.php' : 'login.php'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512"><path d="M344 144c-3.92 52.87-44 96-88 96s-84.15-43.12-88-96c-4-55 35-96 88-96s92 42 88 96z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"></path><path d="M256 304c-87 0-175.3 48-191.64 138.6C62.39 453.52 68.57 464 80 464h352c11.44 0 17.62-10.48 15.65-21.4C431.3 352 343 304 256 304z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"></path></svg>
                                </a>
                                <ul class="header__account--dropdown__list">
                                    <?php if ($currentUser): ?>
                                        <li><span style="display:block;padding:8px 20px;font-weight:600;font-size:13px;">Hi, <?php echo htmlspecialchars(explode(' ', $currentUser['name'])[0]); ?></span></li>
                                        <li><a href="account.php">My Account</a></li>
                                        <li><a href="account.php?tab=orders">My Orders</a></li>
                                        <li><a href="wishlist.php">Wishlist</a></li>
                                        <li><a href="logout.php">Logout</a></li>
                                    <?php else: ?>
                                        <li><a href="login.php">Login</a></li>
                                        <li><a href="register.php">Create Account</a></li>
                                        <li><a href="wishlist.php">Wishlist</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                            <li class="header__account--items header__account2--items d-none d-lg-block">
                                <a class="header__account--btn" href="wishlist.php">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="28.51" height="23.443" viewBox="0 0 512 512">
                                        <path d="M352.92 80C288 80 256 144 256 144s-32-64-96.92-64c-52.76 0-94.54 44.14-95.08 96.81-1.1 109.33 86.73 187.08 183 252.42a16 16 0 0018 0c96.26-65.34 184.09-143.09 183-252.42-.54-52.67-42.32-96.81-95.08-96.81z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"></path>
                                    </svg>
                                    <span class="items__count wishlist style2"<?php echo $wishlistCount > 0 ? '' : ' hidden'; ?>><?php echo (int) $wishlistCount; ?></span>
                                </a>
                            </li>
                            <li class="header__account--items header__account2--items">
                                <a class="header__account--btn minicart__open--btn" href="javascript:void(0)" data-offcanvas>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26.51" height="23.443" viewBox="0 0 14.706 13.534">
                                        <g transform="translate(0 0)">
                                            <g>
                                                <path data-name="Path 16787" d="M4.738,472.271h7.814a.434.434,0,0,0,.414-.328l1.723-6.316a.466.466,0,0,0-.071-.4.424.424,0,0,0-.344-.179H3.745L3.437,463.6a.435.435,0,0,0-.421-.353H.431a.451.451,0,0,0,0,.9h2.24c.054.257,1.474,6.946,1.555,7.33a1.36,1.36,0,0,0-.779,1.242,1.326,1.326,0,0,0,1.293,1.354h7.812a.452.452,0,0,0,0-.9H4.74a.451.451,0,0,1,0-.9Zm8.966-6.317-1.477,5.414H5.085l-1.149-5.414Z" transform="translate(0 -463.248)" fill="currentColor" />
                                                <path data-name="Path 16788" d="M5.5,478.8a1.294,1.294,0,1,0,1.293-1.353A1.325,1.325,0,0,0,5.5,478.8Zm1.293-.451a.452.452,0,1,1-.431.451A.442.442,0,0,1,6.793,478.352Z" transform="translate(-1.191 -466.622)" fill="currentColor" />
                                                <path data-name="Path 16789" d="M13.273,478.8a1.294,1.294,0,1,0,1.293-1.353A1.325,1.325,0,0,0,13.273,478.8Zm1.293-.451a.452.452,0,1,1-.431.451A.442.442,0,0,1,14.566,478.352Z" transform="translate(-2.875 -466.622)" fill="currentColor" />
                                            </g>
                                        </g>
                                    </svg>
                                    <span class="items__count style2"<?php echo $cartCount > 0 ? '' : ' hidden'; ?>><?php echo (int) $cartCount; ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Start Offcanvas header menu -->
        <div class="offcanvas__header color-scheme-2">
            <div class="offcanvas__inner">
                <div class="offcanvas__logo">
                    <a class="offcanvas__logo_link" href="index.php"><?php if (SITE_LOGO !== ''): ?><img src="<?php echo e(SITE_LOGO); ?>" alt="<?php echo e(SITE_NAME); ?>"><?php else: ?><span class="main__logo--text"><?php echo e(SITE_NAME); ?></span><?php endif; ?></a>
                    <button class="offcanvas__close--btn" data-offcanvas>close</button>
                </div>
                <nav class="offcanvas__menu offcanvas__menu--refined">
                    <ul class="offcanvas__menu_ul">
                        <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="index.php">Home</a></li>
                        <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="about.php">About Us</a></li>

                        <li class="offcanvas__menu_li offcanvas__has--children">
                            <div class="offcanvas__menu_row">
                                <a class="offcanvas__menu_item" href="products.php">Shop</a>
                                <button type="button" class="offcanvas__expand" aria-label="Toggle Shop menu"></button>
                            </div>
                            <ul class="offcanvas__submenu">
                                <?php foreach ($navCategoryTree as $mainCat): ?>
                                    <li class="offcanvas__has--children">
                                        <div class="offcanvas__menu_row">
                                            <a class="offcanvas__menu_item" href="category.php?slug=<?php echo htmlspecialchars($mainCat['slug']); ?>"><?php echo htmlspecialchars($mainCat['name']); ?></a>
                                            <?php if (!empty($mainCat['children'])): ?><button type="button" class="offcanvas__expand" aria-label="Toggle"></button><?php endif; ?>
                                        </div>
                                        <?php if (!empty($mainCat['children'])): ?>
                                            <ul class="offcanvas__submenu offcanvas__submenu--2">
                                                <?php foreach ($mainCat['children'] as $subCat): ?>
                                                    <li><a class="offcanvas__menu_item" href="category.php?slug=<?php echo htmlspecialchars($subCat['slug']); ?>"><?php echo htmlspecialchars($subCat['name']); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>

                        <?php if (!empty($navSchemeCategories)): ?>
                            <li class="offcanvas__menu_li offcanvas__has--children">
                                <div class="offcanvas__menu_row">
                                    <a class="offcanvas__menu_item" href="schemes.php">Schemes</a>
                                    <button type="button" class="offcanvas__expand" aria-label="Toggle Schemes menu"></button>
                                </div>
                                <ul class="offcanvas__submenu">
                                    <li><a class="offcanvas__menu_item" href="schemes.php">All Schemes</a></li>
                                    <?php foreach ($navSchemeCategories as $sc): ?>
                                        <li><a class="offcanvas__menu_item" href="schemes.php?category=<?php echo htmlspecialchars($sc['slug']); ?>"><?php echo htmlspecialchars($sc['name']); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="digigold.php">Digi Gold</a></li>
                        <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="appointment.php">Book Appointment</a></li>
                        <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="feedback.php">Feedback</a></li>
                        <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="contact.php">Contact</a></li>
                        <?php if ($currentUser): ?>
                            <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="account.php">My Account</a></li>
                            <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="logout.php">Logout</a></li>
                        <?php else: ?>
                            <li class="offcanvas__menu_li"><a class="offcanvas__menu_item" href="login.php">Login / Register</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
        <!-- End Offcanvas header menu -->

        <!-- Start Offcanvas stikcy toolbar -->
        <div class="offcanvas__stikcy--toolbar color-scheme-2">
            <ul class="d-flex justify-content-between">
                <li class="offcanvas__stikcy--toolbar__list">
                    <a class="offcanvas__stikcy--toolbar__btn" href="index.php">
                        <span class="offcanvas__stikcy--toolbar__icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" width="21.51" height="21.443" viewBox="0 0 22 17">
                                <path fill="currentColor" d="M20.9141 7.93359c.1406.11719.2109.26953.2109.45703 0 .14063-.0469.25782-.1406.35157l-.3516.42187c-.1172.14063-.2578.21094-.4219.21094-.1406 0-.2578-.04688-.3515-.14062l-.9844-.77344V15c0 .3047-.1172.5625-.3516.7734-.2109.2344-.4687.3516-.7734.3516h-4.5c-.3047 0-.5742-.1172-.8086-.3516-.2109-.2109-.3164-.4687-.3164-.7734v-3.6562h-2.25V15c0 .3047-.11719.5625-.35156.7734-.21094.2344-.46875.3516-.77344.3516h-4.5c-.30469 0-.57422-.1172-.80859-.3516-.21094-.2109-.31641-.4687-.31641-.7734V8.46094l-.94922.77344c-.11719.09374-.24609.14062-.38672.14062-.16406 0-.30468-.07031-.42187-.21094l-.35157-.42187C.921875 8.625.875 8.50781.875 8.39062c0-.1875.070312-.33984.21094-.45703L9.73438.832031C10.1094.527344 10.5312.375 11 .375s.8906.152344 1.2656.457031l8.6485 7.101559zm-3.7266 6.50391V7.05469L11 1.99219l-6.1875 5.0625v7.38281h3.375v-3.6563c0-.3046.10547-.5624.31641-.7734.23437-.23436.5039-.35155.80859-.35155h3.375c.3047 0 .5625.11719.7734.35155.2344.211.3516.4688.3516.7734v3.6563h3.375z"></path>
                            </svg></span>
                        <span class="offcanvas__stikcy--toolbar__label">Home</span>
                    </a>
                </li>
                <li class="offcanvas__stikcy--toolbar__list">
                    <a class="offcanvas__stikcy--toolbar__btn" href="products.php">
                        <span class="offcanvas__stikcy--toolbar__icon"><svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" width="18.51" height="17.443" viewBox="0 0 448 512">
                                <path d="M416 32H32A32 32 0 0 0 0 64v384a32 32 0 0 0 32 32h384a32 32 0 0 0 32-32V64a32 32 0 0 0-32-32zm-16 48v152H248V80zm-200 0v152H48V80zM48 432V280h152v152zm200 0V280h152v152z"></path>
                            </svg></span>
                        <span class="offcanvas__stikcy--toolbar__label">Shop</span>
                    </a>
                </li>
                <li class="offcanvas__stikcy--toolbar__list">
                    <a class="offcanvas__stikcy--toolbar__btn minicart__open--btn" href="javascript:void(0)" data-offcanvas>
                        <span class="offcanvas__stikcy--toolbar__icon"><svg xmlns="http://www.w3.org/2000/svg" width="18.51" height="15.443" viewBox="0 0 18.51 15.443">
                                <path d="M79.963,138.379l-13.358,0-.56-1.927a.871.871,0,0,0-.6-.592l-1.961-.529a.91.91,0,0,0-.226-.03.864.864,0,0,0-.226,1.7l1.491.4,3.026,10.919a1.277,1.277,0,1,0,1.844,1.144.358.358,0,0,0,0-.049h6.163c0,.017,0,.034,0,.049a1.277,1.277,0,1,0,1.434-1.267c-1.531-.247-7.783-.55-7.783-.55l-.205-.8h7.8a.9.9,0,0,0,.863-.651l1.688-5.943h.62a.936.936,0,1,0,0-1.872Zm-9.934,6.474H68.568c-.04,0-.1.008-.125-.085-.034-.118-.082-.283-.082-.283l-1.146-4.037a.061.061,0,0,1,.011-.057.064.064,0,0,1,.053-.025h1.777a.064.064,0,0,1,.063.051l.969,4.34,0,.013a.058.058,0,0,1,0,.019A.063.063,0,0,1,70.03,144.853Zm3.731-4.41-.789,4.359a.066.066,0,0,1-.063.051h-1.1a.064.064,0,0,1-.063-.051l-.789-4.357a.064.064,0,0,1,.013-.055.07.07,0,0,1,.051-.025H73.7a.06.06,0,0,1,.051.025A.064.064,0,0,1,73.76,140.443Zm3.737,0L76.26,144.8a.068.068,0,0,1-.063.049H74.684a.063.063,0,0,1-.051-.025.064.064,0,0,1-.013-.055l.973-4.357a.066.066,0,0,1,.063-.051h1.777a.071.071,0,0,1,.053.025A.076.076,0,0,1,77.5,140.448Z" transform="translate(-62.393 -135.3)" fill="currentColor" />
                            </svg></span>
                        <span class="offcanvas__stikcy--toolbar__label">Cart</span>
                        <span class="items__count"<?php echo $cartCount > 0 ? '' : ' hidden'; ?>><?php echo (int) $cartCount; ?></span>
                    </a>
                </li>
                <li class="offcanvas__stikcy--toolbar__list">
                    <a class="offcanvas__stikcy--toolbar__btn" href="wishlist.php">
                        <span class="offcanvas__stikcy--toolbar__icon"><svg xmlns="http://www.w3.org/2000/svg" width="18.541" height="15.557" viewBox="0 0 18.541 15.557">
                                <path d="M71.775,135.51a5.153,5.153,0,0,1,1.267-1.524,4.986,4.986,0,0,1,6.584.358,4.728,4.728,0,0,1,1.174,4.914,10.458,10.458,0,0,1-2.132,3.808,22.591,22.591,0,0,1-5.4,4.558c-.445.282-.9.549-1.356.812a.306.306,0,0,1-.254.013,25.491,25.491,0,0,1-6.279-4.8,11.648,11.648,0,0,1-2.52-4.009,4.957,4.957,0,0,1,.028-3.787,4.629,4.629,0,0,1,3.744-2.863,4.782,4.782,0,0,1,5.086,2.447c.013.019.025.034.057.076Z" transform="translate(-62.498 -132.915)" fill="currentColor" />
                            </svg></span>
                        <span class="offcanvas__stikcy--toolbar__label">Wishlist</span>
                        <span class="items__count"<?php echo $wishlistCount > 0 ? '' : ' hidden'; ?>><?php echo (int) $wishlistCount; ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <!-- End Offcanvas stikcy toolbar -->

        <!-- Start offCanvas minicart -->
        <div class="offCanvas__minicart color-scheme-2">
            <div class="minicart__header">
                <div class="minicart__header--top d-flex justify-content-between align-items-center">
                    <h2 class="minicart__title h3">Shopping Cart</h2>
                    <button class="minicart__close--btn" aria-label="minicart close button" data-offcanvas>
                        <svg class="minicart__close--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path fill="currentColor" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M368 368L144 144M368 144L144 368" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="minicart__product">
                <?php if (empty($cartItems)): ?>
                    <p class="text-center py-4">Your cart is empty</p>
                <?php else: ?>
                    <?php foreach ($cartItems as $item): ?>
                        <div class="minicart__product--items d-flex">
                            <div class="minicart__thumb">
                                <a href="product-details.php?id=<?php echo $item['id']; ?>"><img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"></a>
                            </div>
                            <div class="minicart__text">
                                <h3 class="minicart__subtitle h4"><a href="product-details.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                <span class="color__variant"><b>Category:</b> <?php echo htmlspecialchars($item['category']); ?></span>
                                <div class="minicart__price">
                                    <span class="current__price"><?php echo formatPrice($item['price']); ?></span>
                                    <span class="old__price"><?php echo formatPrice($item['old_price']); ?></span>
                                </div>
                                <div class="minicart__text--footer d-flex align-items-center">
                                    <span>Qty: <?php echo $item['qty']; ?></span>
                                    <?php
                                    /* This form had no csrf_token field at all, so cart-actions.php
                                       rejected every submission and bounced the customer back with
                                       "Your session expired" — the mini-cart Remove could never work,
                                       on any page, in any session.

                                       data-mini-remove matches the markup renderMiniCart() produces
                                       in shop.js, so the JS path intercepts this button too instead of
                                       only the AJAX-rendered copy. Removing by row id rather than
                                       product id also drops the right line when the same product sits
                                       in the cart under two variants. */
                                    ?>
                                    <form action="cart-actions.php" method="post" style="display:inline; margin-left:10px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $item['id']; ?>">
                                        <input type="hidden" name="variant_id" value="<?php echo e($item['variant_id']); ?>">
                                        <input type="hidden" name="redirect" value="<?php echo e(basename(parse_url($_SERVER['REQUEST_URI'] ?? 'index.php', PHP_URL_PATH) ?: 'index.php')); ?>">
                                        <button type="submit" class="minicart__product--remove" data-mini-remove="<?php echo (int) $item['cart_row_id']; ?>">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($cartItems)): ?>
                <div class="minicart__amount ">
                    <div class="minicart__amount_list d-flex justify-content-between">
                        <span>Sub Total:</span>
                        <span><b><?php echo formatPrice($cartTotal); ?></b></span>
                    </div>
                    <div class="minicart__amount_list d-flex justify-content-between">
                        <span>Total:</span>
                        <span><b><?php echo formatPrice($cartTotal); ?></b></span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="minicart__button d-flex justify-content-center pt-5">
                <a class="primary__btn minicart__button--link" href="cart.php">View cart</a>
                <a class="primary__btn minicart__button--link" href="checkout.php">Checkout</a>
            </div>
        </div>
        <!-- End offCanvas minicart -->

        <!-- Start search box area -->
        <div class="predictive__search--box color-scheme-2">
            <div class="predictive__search--box__inner">
                <h2 class="predictive__search--title">Search Products</h2>
                <form class="predictive__search--form" action="products.php" method="get">
                    <label><input class="predictive__search--input" placeholder="Search Here" type="text" name="search"></label>
                    <button class="predictive__search--button" type="submit"><svg class="header__search--button__svg" xmlns="http://www.w3.org/2000/svg" width="30.51" height="25.443" viewBox="0 0 512 512">
                            <path d="M221.09 64a157.09 157.09 0 10157.09 157.09A157.1 157.1 0 00221.09 64z" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32" />
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="32" d="M338.29 338.29L448 448" />
                        </svg></button>
                </form>
            </div>
            <button class="predictive__search--close__btn" aria-label="search close button" data-offcanvas>
                <svg class="predictive__search--close__icon" xmlns="http://www.w3.org/2000/svg" width="40.51" height="30.443" viewBox="0 0 512 512">
                    <path fill="currentColor" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M368 368L144 144M368 144L144 368" />
                </svg>
            </button>
        </div>
        <!-- End search box area -->
    </header>
    <!-- End header area -->
    <?php if (!empty($flashSuccess)): ?><div class="flash__bar is-success"><?php echo htmlspecialchars($flashSuccess); ?></div><?php endif; ?>
    <?php if (!empty($flashError)): ?><div class="flash__bar is-error"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>