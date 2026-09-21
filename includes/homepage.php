<?php
/**
 * Homepage hero sliders + category banner tiles (admin-managed).
 */

require_once __DIR__ . '/functions.php';

function getSliders($onlyActive = true) {
    $sql = 'SELECT * FROM sliders';
    if ($onlyActive) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    try {
        return getDB()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getSlider($id) {
    $stmt = getDB()->prepare('SELECT * FROM sliders WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

/**
 * @param string|null $slot  'feature' | 'wide' | 'tile' | null (=all)
 */
function getBanners($slot = null, $onlyActive = true) {
    $sql = 'SELECT * FROM banners';
    $where = [];
    $params = [];
    if ($onlyActive) {
        $where[] = "status = 'active'";
    }
    if ($slot !== null) {
        $where[] = 'slot = ?';
        $params[] = $slot;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getBanner($id) {
    $stmt = getDB()->prepare('SELECT * FROM banners WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

/* ==================== AD / OFFER BANNERS ==================== */

const AD_POSITIONS = ['after_hero', 'after_banners', 'mid_products', 'before_footer'];

/**
 * Active ad banners for a homepage position. Filters out not-yet-started and
 * expired banners on the server (the countdown JS is only a nicety).
 * @param string|null $position  one of AD_POSITIONS, or null for all
 */
function getAdBanners($position = null, $onlyActive = true) {
    $where = [];
    $params = [];
    if ($onlyActive) {
        $where[] = "status = 'active'";
        $where[] = '(starts_at IS NULL OR starts_at <= NOW())';
        $where[] = '(expires_at IS NULL OR expires_at > NOW())';
    }
    if ($position !== null) {
        $where[] = 'position = ?';
        $params[] = $position;
    }
    $sql = 'SELECT * FROM ad_banners';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getAdBanner($id) {
    $stmt = getDB()->prepare('SELECT * FROM ad_banners WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

/**
 * Render every ad banner assigned to $position, in the theme's look.
 * Strips render full-width one after another; cards render 2-up in a row.
 */
function renderAdBanners($position) {
    $banners = getAdBanners($position);
    if (!$banners) {
        return;
    }
    $strips = array_filter($banners, fn($b) => $b['format'] === 'strip');
    $cards  = array_filter($banners, fn($b) => $b['format'] === 'card');

    foreach ($strips as $b) {
        ad_strip($b);
    }
    if ($cards) {
        echo '<section class="ad__section section--padding pt-0"><div class="container-fluid"><div class="row row-cols-lg-2 row-cols-1 mb--n28">';
        foreach ($cards as $b) {
            echo '<div class="col mb-28">';
            ad_card($b);
            echo '</div>';
        }
        echo '</div></div></section>';
    }
}

/**
 * Deliberately NOT `data-countdown`: the theme's script.js binds that attribute
 * and overwrites the element's innerHTML, which would blow away the whole banner.
 */
function ad_countdown_attr($b) {
    if (empty($b['expires_at'])) {
        return '';
    }
    return ' data-ad-expires="' . e(str_replace(' ', 'T', $b['expires_at'])) . '"';
}

function ad_strip($b) {
    $href = !empty($b['button_url']) ? $b['button_url'] : 'products.php';
    $img = trim((string) $b['image']);
    $bg  = trim((string) $b['bg_color']) ?: '#1c1a16';
    $themeClass = $b['text_theme'] === 'dark' ? 'ad-strip--dark' : 'ad-strip--light';
    // Background on the section: solid colour fallback + a side gradient so the
    // image stays visible on the right while text stays readable on the left.
    $style = 'background-color:' . e($bg) . ';';
    if ($img !== '') {
        $scrim = $b['text_theme'] === 'dark'
            ? 'linear-gradient(90deg, rgba(255,255,255,.86) 0%, rgba(255,255,255,.55) 42%, rgba(255,255,255,0) 72%)'
            : 'linear-gradient(90deg, rgba(20,18,15,.82) 0%, rgba(20,18,15,.5) 42%, rgba(20,18,15,.05) 74%)';
        $style .= "background-image:$scrim,url('" . e($img) . "');"
            . 'background-size:cover,cover;background-position:center,center;';
    }
    ?>
    <?php // .container (not container-fluid) so the copy lines up with the rest
          // of the page while the background stays full-bleed. ?>
    <?php /* stable id so admin's "View on site" can deep-link this banner */ ?>
    <section class="ad-strip <?php echo $themeClass; ?>" id="ad-<?php echo (int) $b['id']; ?>" style="<?php echo $style; ?>"<?php echo ad_countdown_attr($b); ?>>
        <div class="container">
            <div class="ad-strip__inner">
                <?php if (!empty($b['subtitle'])): ?><span class="ad-strip__label"><?php echo e($b['subtitle']); ?></span><?php endif; ?>
                <h2 class="ad-strip__title"><?php echo multiline_html($b['title']); ?></h2>
                <?php if (!empty($b['description'])): ?><p class="ad-strip__desc"><?php echo multiline_html($b['description']); ?></p><?php endif; ?>
                <?php if (!empty($b['expires_at'])): ?><div class="ad-countdown" aria-hidden="true"></div><?php endif; ?>
                <?php if (!empty($b['button_label'])): ?>
                    <a class="btn btn-primary ad-strip__btn" href="<?php echo e($href); ?>"><?php echo e($b['button_label']); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}

function ad_card($b) {
    $href = !empty($b['button_url']) ? $b['button_url'] : 'products.php';
    ?>
    <a class="ad-card" id="ad-<?php echo (int) $b['id']; ?>" href="<?php echo e($href); ?>"<?php echo ad_countdown_attr($b); ?>>
        <img class="ad-card__img" src="<?php echo e(homepage_image_src($b['image'], 'assets/img/banner/newcollection.jpg')); ?>" alt="<?php echo e($b['title']); ?>">
        <div class="ad-card__body">
            <?php if (!empty($b['subtitle'])): ?><span class="ad-card__label"><?php echo e($b['subtitle']); ?></span><?php endif; ?>
            <h3 class="ad-card__title"><?php echo multiline_html($b['title']); ?></h3>
            <?php if (!empty($b['expires_at'])): ?><div class="ad-countdown ad-countdown--sm" aria-hidden="true"></div><?php endif; ?>
            <?php if (!empty($b['button_label'])): ?><span class="ad-card__btn"><?php echo e($b['button_label']); ?> &rarr;</span><?php endif; ?>
        </div>
    </a>
    <?php
}

/** Multi-line title -> escaped HTML with <br>. Accepts real newlines or literal "\n". */
function multiline_html($text) {
    $text = str_replace(['\\n', "\r\n", "\r"], "\n", (string) $text);
    return nl2br(e($text));
}

/** Resolve a stored image path to a usable src (handles asset paths and uploads). */
function homepage_image_src($path, $fallback = 'assets/img/slider/slider1.png') {
    $path = trim((string) $path);
    return $path !== '' ? $path : $fallback;
}
